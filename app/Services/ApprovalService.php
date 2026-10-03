<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Transaction;
use App\Models\TransactionApproval;
use App\Models\TillCashMovement;
use App\Models\User;
use App\Notifications\TransactionRejected;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovalService
{
    public function __construct(private GeneralLedgerService $gl)
    {
    }

    public function approve(Transaction $transaction, ?string $notes): void
    {
        // Quick pre-flight check before acquiring the lock
        if ($transaction->processed_by === Auth::id()) {
            throw ValidationException::withMessages([
                'approval' => 'You cannot approve a transaction you initiated.',
            ]);
        }

        $user = Auth::user();
        $userLevel = $this->userApprovalLevel($user);
        $isSuperAdmin = $user->hasRole('Super Admin');

        DB::transaction(function () use ($transaction, $notes, $userLevel, $isSuperAdmin) {
            // Lock the transaction row — prevents two concurrent approvals racing past each other
            $locked = Transaction::lockForUpdate()->findOrFail($transaction->id);

            if ($locked->status !== 'pending' || !$locked->requires_approval) {
                throw ValidationException::withMessages([
                    'transaction' => 'This transaction is no longer pending approval.',
                ]);
            }

            $nextLevel = $isSuperAdmin
                ? max($locked->approval_level_reached + 1, $locked->approval_level_required)
                : $locked->approval_level_reached + 1;

            if ((!$isSuperAdmin && $userLevel !== $nextLevel) || $locked->approval_level_required < $nextLevel) {
                throw ValidationException::withMessages([
                    'approval' => "A Level {$nextLevel} approver is required for this transaction.",
                ]);
            }

            $alreadyActed = TransactionApproval::where('transaction_id', $locked->id)
                ->where('approver_id', Auth::id())
                ->exists();

            if ($alreadyActed) {
                throw ValidationException::withMessages([
                    'approval' => 'You have already acted on this transaction.',
                ]);
            }

            TransactionApproval::create([
                'transaction_id' => $locked->id,
                'approver_id'    => Auth::id(),
                'level'          => $nextLevel,
                'action'         => 'approved',
                'notes'          => $notes,
                'acted_at'       => now(),
            ]);

            $locked->update(['approval_level_reached' => $nextLevel]);
            $locked->refresh();

            if ($nextLevel >= $locked->approval_level_required) {
                $this->executeTransaction($locked);
            }

            AuditLog::record('approval', 'transactions',
                "Approved transaction {$locked->reference} at level {$nextLevel}", [], [
                    'reference' => $locked->reference,
                    'level'     => $nextLevel,
                    'approver'  => Auth::user()->name,
                ]);
        });
    }

    public function reject(Transaction $transaction, string $notes): void
    {
        if ($transaction->status !== 'pending') {
            throw ValidationException::withMessages([
                'transaction' => 'This transaction is not pending.',
            ]);
        }

        $user = Auth::user();
        $userLevel = $this->userApprovalLevel($user);
        $isSuperAdmin = $user->hasRole('Super Admin');
        $nextLevel = $isSuperAdmin
            ? max($transaction->approval_level_reached + 1, $transaction->approval_level_required)
            : $transaction->approval_level_reached + 1;

        if ((!$isSuperAdmin && $userLevel !== $nextLevel) || $transaction->approval_level_required < $nextLevel) {
            throw ValidationException::withMessages([
                'approval' => "A Level {$nextLevel} approver is required for this transaction.",
            ]);
        }

        if ($transaction->processed_by === Auth::id()) {
            throw ValidationException::withMessages([
                'approval' => 'You cannot reject a transaction you initiated.',
            ]);
        }

        DB::transaction(function () use ($transaction, $notes, $nextLevel) {
            TransactionApproval::create([
                'transaction_id' => $transaction->id,
                'approver_id'    => Auth::id(),
                'level'          => $nextLevel,
                'action'         => 'rejected',
                'notes'          => $notes,
                'acted_at'       => now(),
            ]);

            $transaction->update(['status' => 'rejected']);

            // Also reject the corresponding CR leg of a transfer
            if ($transaction->type === 'transfer') {
                Transaction::where('reference', $transaction->reference . '-CR')
                    ->update(['status' => 'rejected', 'updated_at' => now()]);
            }

            $transaction->account->customer?->notify(new TransactionRejected($transaction, $notes));

            AuditLog::record('rejection', 'transactions',
                "Rejected transaction {$transaction->reference}: {$notes}", [], [
                    'reference' => $transaction->reference,
                    'level'     => $nextLevel,
                    'approver'  => Auth::user()->name,
                ]);
        });
    }

    // ── Execution ─────────────────────────────────────────────────────────────

    // Exposes the same locked-account/GL-posting executor approve() uses,
    // for callers that complete a pending transaction through a different
    // authorization mechanism entirely (e.g. a customer's cardless
    // withdrawal code being redeemed at the counter) rather than the
    // approval-level workflow. Never call this without your own equivalent
    // authorization check — it performs no approval-level validation itself.
    public function execute(Transaction $transaction): void
    {
        $this->executeTransaction($transaction);
    }

    private function executeTransaction(Transaction $transaction): void
    {
        if ($transaction->type === 'transfer') {
            $this->executeTransfer($transaction);
        } else {
            $this->executeSimple($transaction);
        }
    }

    private function executeSimple(Transaction $transaction): void
    {
        $account = Account::lockForUpdate()->findOrFail($transaction->account_id);

        if (!$account->isOperational()) {
            throw ValidationException::withMessages([
                'account' => "Account is now {$account->status} and cannot be processed.",
            ]);
        }

        $amount   = (float) $transaction->amount;
        $before   = (float) $account->balance;
        $isCashOut = in_array($transaction->type, ['withdrawal', 'loan_repayment']);
        $till     = $transaction->tellerTill;

        if ($isCashOut) {
            if ($amount > $before) {
                throw ValidationException::withMessages([
                    'balance' => "Insufficient balance at time of execution. Available: {$account->currency} {$before}.",
                ]);
            }
            if (($before - $amount) < (float) $account->minimum_balance) {
                throw ValidationException::withMessages([
                    'balance' => "Execution would breach minimum balance.",
                ]);
            }
            if ($till && $amount > (float) $till->current_balance) {
                throw ValidationException::withMessages([
                    'balance' => 'Insufficient cash in teller till at time of execution. Available: USD ' . number_format($till->current_balance, 2) . '.',
                ]);
            }
            $after = $before - $amount;
        } else {
            $after = $before + $amount;
        }

        $account->update(['balance' => $after, 'last_activity_date' => today()]);

        $transaction->update([
            'balance_before' => $before,
            'balance_after'  => $after,
            'status'         => 'completed',
            'completed_at'   => now(),
        ]);

        // Cash for a pending transaction only physically moves through the teller's
        // till once it clears approval — mirror the immediate (no-approval) deposit/
        // withdrawal paths in TransactionService so the till balance and its cash
        // movement log stay in sync with what actually got approved.
        if ($till) {
            if ($isCashOut) {
                $till->decrement('current_balance', $amount);
                TillCashMovement::create([
                    'till_id'      => $till->id,
                    'type'         => 'customer_withdrawal',
                    'amount'       => $amount,
                    'notes'        => "Approved cash withdrawal — {$account->account_number} ref {$transaction->reference}",
                    'processed_by' => Auth::id(),
                ]);
            } else {
                $till->increment('current_balance', $amount);
                TillCashMovement::create([
                    'till_id'      => $till->id,
                    'type'         => 'customer_deposit',
                    'amount'       => $amount,
                    'notes'        => "Approved cash deposit — {$account->account_number} ref {$transaction->reference}",
                    'processed_by' => Auth::id(),
                ]);
            }
        }

        $depositAcc = $this->gl->depositAccountFor($account);
        $cashAcc    = $this->gl->cashAccountFor($till);

        $lines = $isCashOut
            ? [['account' => $depositAcc, 'debit' => $amount], ['account' => $cashAcc, 'credit' => $amount]]
            : [['account' => $cashAcc, 'debit' => $amount], ['account' => $depositAcc, 'credit' => $amount]];

        $this->gl->post($lines, "Approved {$transaction->type} — {$account->account_number} ({$transaction->reference})", 'transaction', $transaction->id);
    }

    private function executeTransfer(Transaction $transaction): void
    {
        $ids    = [$transaction->account_id, $transaction->related_account_id];
        sort($ids);
        $locked = Account::lockForUpdate()->whereIn('id', $ids)->get()->keyBy('id');

        $source = $locked[$transaction->account_id];
        $dest   = $locked[$transaction->related_account_id];

        if (!$source->isOperational()) {
            throw ValidationException::withMessages(['account' => "Source account is now {$source->status}."]);
        }
        if (!$dest->isOperational()) {
            throw ValidationException::withMessages(['account' => "Destination account is now {$dest->status}."]);
        }

        $amount     = (float) $transaction->amount;
        $fromBefore = (float) $source->balance;
        $toBefore   = (float) $dest->balance;

        if ($amount > $fromBefore) {
            throw ValidationException::withMessages([
                'balance' => "Insufficient balance in source account at time of execution.",
            ]);
        }
        if (($fromBefore - $amount) < (float) $source->minimum_balance) {
            throw ValidationException::withMessages([
                'balance' => "Execution would breach source account minimum balance.",
            ]);
        }

        $fromAfter = $fromBefore - $amount;
        $toAfter   = $toBefore + $amount;

        $source->update(['balance' => $fromAfter, 'last_activity_date' => today()]);
        $dest->update(['balance'   => $toAfter,   'last_activity_date' => today()]);

        $transaction->update([
            'balance_before' => $fromBefore,
            'balance_after'  => $fromAfter,
            'status'         => 'completed',
            'completed_at'   => now(),
        ]);

        Transaction::where('reference', $transaction->reference . '-CR')->update([
            'balance_before'         => $toBefore,
            'balance_after'          => $toAfter,
            'status'                 => 'completed',
            'completed_at'           => now(),
            'approval_level_reached' => $transaction->approval_level_required,
            'updated_at'             => now(),
        ]);

        $this->gl->post([
            ['account' => $this->gl->depositAccountFor($source), 'debit'  => $amount],
            ['account' => $this->gl->depositAccountFor($dest), 'credit' => $amount],
        ], "Approved transfer — {$source->account_number} → {$dest->account_number} ({$transaction->reference})", 'transaction', $transaction->reference);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function userApprovalLevel(User $user): int
    {
        if ($user->hasRole('Super Admin'))        return 3;
        if ($user->hasRole('Compliance Officer')) return 2;
        if ($user->hasRole('Branch Manager'))     return 1;
        if ($user->hasPermissionTo('approvals.approve')) return 1;
        return 0;
    }

    public function pendingForUser(User $user): \Illuminate\Support\Collection
    {
        $level = $this->userApprovalLevel($user);
        $query = Transaction::where('status', 'pending')
            ->where('requires_approval', true)
            ->where('processed_by', '!=', $user->id);

        if ($user->hasRole('Super Admin')) {
            $query->whereColumn('approval_level_reached', '<', 'approval_level_required');
        } else {
            $query->where('approval_level_required', '>=', $level)
                ->where('approval_level_reached', $level - 1);
        }

        return $query
            ->with(['account.customer', 'processedBy'])
            ->orderBy('created_at')
            ->get();
    }
}
