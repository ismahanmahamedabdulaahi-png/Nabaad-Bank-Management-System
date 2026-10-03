<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\JournalEntry;
use App\Models\TellerTill;
use App\Models\TillCashMovement;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionService
{
    public function __construct(private GeneralLedgerService $gl)
    {
    }

    public function deposit(Account $account, array $data): Transaction
    {
        $amount = (float) $data['amount'];
        $level  = $this->determineApprovalLevel($amount);

        if ($level > 0) {
            return $this->createPending($account, 'deposit', $amount, $data, $level);
        }

        return DB::transaction(function () use ($account, $data) {
            $locked = Account::lockForUpdate()->findOrFail($account->id);

            if (!$locked->isOperational()) {
                throw ValidationException::withMessages([
                    'account' => "Cannot deposit into a {$locked->status} account.",
                ]);
            }

            $amount = (float) $data['amount'];
            $before = (float) $locked->balance;
            $after  = $before + $amount;

            // Lock teller till before any balance changes
            $till = TellerTill::where('teller_id', Auth::id())
                ->where('business_date', today()->toDateString())
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            $locked->update(['balance' => $after, 'last_activity_date' => today()]);

            $tillId = $till?->id;

            $txn = Transaction::create([
                'reference'         => $this->generateReference(),
                'account_id'        => $locked->id,
                'type'              => 'deposit',
                'amount'            => $amount,
                'balance_before'    => $before,
                'balance_after'     => $after,
                'status'            => 'completed',
                'description'       => $data['description'] ?? null,
                'notes'             => $data['notes'] ?? null,
                'currency'          => $locked->currency,
                'teller_till_id'    => $tillId,
                'processed_by'      => Auth::id(),
                'completed_at'      => now(),
                'requires_approval' => false,
            ]);

            // Cash received by teller → increment till balance
            if ($till) {
                $till->increment('current_balance', $amount);
                TillCashMovement::create([
                    'till_id'      => $till->id,
                    'type'         => 'customer_deposit',
                    'amount'       => $amount,
                    'notes'        => "Cash deposit — {$locked->account_number} ref {$txn->reference}",
                    'processed_by' => Auth::id(),
                ]);
            }

            AuditLog::record('deposit', 'transactions',
                "Deposited {$locked->currency} {$amount} into {$locked->account_number}", [], [
                    'reference' => $txn->reference,
                    'amount'    => $amount,
                    'account'   => $locked->account_number,
                ]);

            $this->gl->post([
                ['account' => $this->gl->cashAccountFor($till), 'debit'  => $amount],
                ['account' => $this->gl->depositAccountFor($locked), 'credit' => $amount],
            ], "Deposit — {$locked->account_number} ({$txn->reference})", 'transaction', $txn->id);

            return $txn;
        });
    }

    public function withdraw(Account $account, array $data): Transaction
    {
        $amount = (float) $data['amount'];
        $level  = $this->determineApprovalLevel($amount);

        if ($level > 0) {
            // Validate balance at initiation even for pending
            if ($amount > (float) $account->balance) {
                throw ValidationException::withMessages([
                    'amount' => "Insufficient balance. Available: {$account->currency} {$account->balance}.",
                ]);
            }
            return $this->createPending($account, 'withdrawal', $amount, $data, $level);
        }

        return DB::transaction(function () use ($account, $data) {
            $locked = Account::lockForUpdate()->findOrFail($account->id);

            if (!$locked->isOperational()) {
                throw ValidationException::withMessages([
                    'account' => "Cannot withdraw from a {$locked->status} account.",
                ]);
            }

            $amount = (float) $data['amount'];
            $before = (float) $locked->balance;

            if ($amount > $before) {
                throw ValidationException::withMessages([
                    'amount' => "Insufficient balance. Available: {$locked->currency} {$before}.",
                ]);
            }

            if (($before - $amount) < (float) $locked->minimum_balance) {
                throw ValidationException::withMessages([
                    'amount' => "Withdrawal would breach the minimum balance of {$locked->currency} {$locked->minimum_balance}.",
                ]);
            }

            // Lock teller till and verify it holds enough cash before debiting account
            $till = TellerTill::where('teller_id', Auth::id())
                ->where('business_date', today()->toDateString())
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            if ($till && $amount > (float) $till->current_balance) {
                throw ValidationException::withMessages([
                    'amount' => 'Insufficient cash in teller till. Available: USD ' . number_format($till->current_balance, 2) . '. Request replenishment first.',
                ]);
            }

            $after = $before - $amount;

            $locked->update(['balance' => $after, 'last_activity_date' => today()]);

            $tillId = $till?->id;

            $txn = Transaction::create([
                'reference'         => $this->generateReference(),
                'account_id'        => $locked->id,
                'type'              => 'withdrawal',
                'amount'            => $amount,
                'balance_before'    => $before,
                'balance_after'     => $after,
                'status'            => 'completed',
                'description'       => $data['description'] ?? null,
                'notes'             => $data['notes'] ?? null,
                'currency'          => $locked->currency,
                'teller_till_id'    => $tillId,
                'processed_by'      => Auth::id(),
                'completed_at'      => now(),
                'requires_approval' => false,
            ]);

            // Cash paid out by teller → decrement till balance
            if ($till) {
                $till->decrement('current_balance', $amount);
                TillCashMovement::create([
                    'till_id'      => $till->id,
                    'type'         => 'customer_withdrawal',
                    'amount'       => $amount,
                    'notes'        => "Cash withdrawal — {$locked->account_number} ref {$txn->reference}",
                    'processed_by' => Auth::id(),
                ]);
            }

            AuditLog::record('withdrawal', 'transactions',
                "Withdrew {$locked->currency} {$amount} from {$locked->account_number}", [], [
                    'reference' => $txn->reference,
                    'amount'    => $amount,
                    'account'   => $locked->account_number,
                ]);

            $this->gl->post([
                ['account' => $this->gl->depositAccountFor($locked), 'debit'  => $amount],
                ['account' => $this->gl->cashAccountFor($till), 'credit' => $amount],
            ], "Withdrawal — {$locked->account_number} ({$txn->reference})", 'transaction', $txn->id);

            return $txn;
        });
    }

    public function transfer(Account $from, Account $to, array $data): array
    {
        if ($from->id === $to->id) {
            throw ValidationException::withMessages([
                'to_account_id' => 'Source and destination accounts cannot be the same.',
            ]);
        }

        $amount = (float) $data['amount'];
        $level  = $this->determineApprovalLevel($amount);

        if ($level > 0) {
            if ($amount > (float) $from->balance) {
                throw ValidationException::withMessages([
                    'amount' => "Insufficient balance in source account.",
                ]);
            }
            $ref = $this->generateReference();
            $fromBefore = (float) $from->balance;
            $toBefore   = (float) $to->balance;

            $tillId = $this->getTellerTillId();

            $fromTxn = Transaction::create([
                'reference'              => $ref,
                'account_id'             => $from->id,
                'related_account_id'     => $to->id,
                'type'                   => 'transfer',
                'amount'                 => $amount,
                'balance_before'         => $fromBefore,
                'balance_after'          => $fromBefore - $amount,
                'status'                 => 'pending',
                'description'            => $data['description'] ?? null,
                'notes'                  => $data['notes'] ?? null,
                'currency'               => $from->currency,
                'teller_till_id'         => $tillId,
                'requires_approval'      => true,
                'approval_level_required'=> $level,
                'approval_level_reached' => 0,
                'processed_by'           => Auth::id(),
                'completed_at'           => null,
            ]);

            $toTxn = Transaction::create([
                'reference'              => $ref . '-CR',
                'account_id'             => $to->id,
                'related_account_id'     => $from->id,
                'type'                   => 'transfer',
                'amount'                 => $amount,
                'balance_before'         => $toBefore,
                'balance_after'          => $toBefore + $amount,
                'status'                 => 'pending',
                'description'            => $data['description'] ?? null,
                'currency'               => $to->currency,
                'teller_till_id'         => $tillId,
                'requires_approval'      => true,
                'approval_level_required'=> $level,
                'approval_level_reached' => 0,
                'processed_by'           => Auth::id(),
                'completed_at'           => null,
            ]);

            return [$fromTxn, $toTxn];
        }

        return DB::transaction(function () use ($from, $to, $data) {
            // Lock both accounts in consistent order to avoid deadlock
            $ids     = [$from->id, $to->id];
            sort($ids);
            $locked  = Account::lockForUpdate()->whereIn('id', $ids)->get()->keyBy('id');
            $lockedFrom = $locked[$from->id];
            $lockedTo   = $locked[$to->id];

            if (!$lockedFrom->isOperational()) {
                throw ValidationException::withMessages([
                    'from_account_id' => "Source account is {$lockedFrom->status} and cannot be debited.",
                ]);
            }
            if (!$lockedTo->isOperational()) {
                throw ValidationException::withMessages([
                    'to_account_id' => "Destination account is {$lockedTo->status} and cannot receive funds.",
                ]);
            }

            $amount     = (float) $data['amount'];
            $fromBefore = (float) $lockedFrom->balance;
            $toBefore   = (float) $lockedTo->balance;

            if ($amount > $fromBefore) {
                throw ValidationException::withMessages([
                    'amount' => "Insufficient balance in source account. Available: {$lockedFrom->currency} {$fromBefore}.",
                ]);
            }

            if (($fromBefore - $amount) < (float) $lockedFrom->minimum_balance) {
                throw ValidationException::withMessages([
                    'amount' => "Transfer would breach the source account's minimum balance of {$lockedFrom->currency} {$lockedFrom->minimum_balance}.",
                ]);
            }

            $fromAfter = $fromBefore - $amount;
            $toAfter   = $toBefore + $amount;

            $lockedFrom->update(['balance' => $fromAfter, 'last_activity_date' => today()]);
            $lockedTo->update(['balance'   => $toAfter,   'last_activity_date' => today()]);

            $ref    = $this->generateReference();
            $desc   = $data['description'] ?? null;
            $tillId = $this->getTellerTillId();

            $fromTxn = Transaction::create([
                'reference'          => $ref,
                'account_id'         => $lockedFrom->id,
                'related_account_id' => $lockedTo->id,
                'type'               => 'transfer',
                'amount'             => $amount,
                'balance_before'     => $fromBefore,
                'balance_after'      => $fromAfter,
                'status'             => 'completed',
                'description'        => $desc,
                'notes'              => $data['notes'] ?? null,
                'currency'           => $lockedFrom->currency,
                'teller_till_id'     => $tillId,
                'processed_by'       => Auth::id(),
                'completed_at'       => now(),
                'requires_approval'  => false,
            ]);

            $toTxn = Transaction::create([
                'reference'          => $ref . '-CR',
                'account_id'         => $lockedTo->id,
                'related_account_id' => $lockedFrom->id,
                'type'               => 'transfer',
                'amount'             => $amount,
                'balance_before'     => $toBefore,
                'balance_after'      => $toAfter,
                'status'             => 'completed',
                'description'        => $desc,
                'currency'           => $lockedTo->currency,
                'teller_till_id'     => $tillId,
                'processed_by'       => Auth::id(),
                'completed_at'       => now(),
                'requires_approval'  => false,
            ]);

            AuditLog::record('transfer', 'transactions',
                "Transferred {$lockedFrom->currency} {$amount} from {$lockedFrom->account_number} to {$lockedTo->account_number}", [], [
                    'reference' => $ref,
                    'amount'    => $amount,
                    'from'      => $lockedFrom->account_number,
                    'to'        => $lockedTo->account_number,
                ]);

            $this->gl->post([
                ['account' => $this->gl->depositAccountFor($lockedFrom), 'debit'  => $amount],
                ['account' => $this->gl->depositAccountFor($lockedTo), 'credit' => $amount],
            ], "Transfer — {$lockedFrom->account_number} → {$lockedTo->account_number} ({$ref})", 'transaction', $ref);

            return [$fromTxn, $toTxn];
        });
    }

    public function reverse(Transaction $original, string $reason): Transaction
    {
        if (!$original->isCompleted()) {
            throw ValidationException::withMessages([
                'transaction' => 'Only completed transactions can be reversed.',
            ]);
        }

        if ($original->type === 'reversal') {
            throw ValidationException::withMessages([
                'transaction' => 'A reversal cannot itself be reversed.',
            ]);
        }

        $alreadyReversed = Transaction::where('reversal_of', $original->id)->exists();
        if ($alreadyReversed) {
            throw ValidationException::withMessages([
                'transaction' => 'This transaction has already been reversed.',
            ]);
        }

        return DB::transaction(function () use ($original, $reason) {
            $account = Account::lockForUpdate()->findOrFail($original->account_id);

            $amount = (float) $original->amount;
            $before = (float) $account->balance;

            // Determine reversal direction: opposite of original
            $isCredit = $original->balance_after > $original->balance_before;

            if ($isCredit) {
                // Original was a credit → reversal is a debit
                if ($amount > $before) {
                    throw ValidationException::withMessages([
                        'transaction' => 'Insufficient balance to reverse this credit transaction.',
                    ]);
                }
                $after = $before - $amount;
            } else {
                // Original was a debit → reversal is a credit
                $after = $before + $amount;
            }

            $account->update(['balance' => $after, 'last_activity_date' => today()]);

            $reversal = Transaction::create([
                'reference'         => $this->generateReference(),
                'account_id'        => $account->id,
                'type'              => 'reversal',
                'amount'            => $amount,
                'balance_before'    => $before,
                'balance_after'     => $after,
                'status'            => 'completed',
                'description'       => "Reversal of {$original->reference}",
                'reversal_of'       => $original->id,
                'reversal_reason'   => $reason,
                'currency'          => $account->currency,
                'teller_till_id'    => $this->getTellerTillId(),
                'processed_by'      => Auth::id(),
                'completed_at'      => now(),
                'requires_approval' => false,
            ]);

            AuditLog::record('reversal', 'transactions',
                "Reversed {$original->reference}: {$reason}", [], [
                    'reversal_ref'  => $reversal->reference,
                    'original_ref'  => $original->reference,
                    'amount'        => $amount,
                ]);

            // Reverse the GL effect of whatever was originally posted for this transaction.
            // Transfers post a single entry keyed to the base reference (shared by both legs);
            // everything else is keyed to the transaction's own id.
            $sourceId = $original->type === 'transfer'
                ? preg_replace('/-CR$/', '', $original->reference)
                : $original->id;

            $originalEntry = JournalEntry::where('source_type', 'transaction')
                ->where('source_id', $sourceId)
                ->latest('created_at')
                ->first();

            if ($originalEntry) {
                $this->gl->reverse($originalEntry, "Reversal of {$original->reference}: {$reason}", 'transaction', $reversal->id);
            }

            return $reversal;
        });
    }

    public function determineApprovalLevel(float $amount): int
    {
        $noMax = (float) (DB::table('settings')->where('key', 'txn_no_approval_max')->value('value') ?? 5000);
        $l1Max = (float) (DB::table('settings')->where('key', 'txn_approval_level1_max')->value('value') ?? 20000);
        $l2Max = (float) (DB::table('settings')->where('key', 'txn_approval_level2_max')->value('value') ?? 50000);

        if ($amount <= $noMax) return 0;
        if ($amount <= $l1Max) return 1;
        if ($amount <= $l2Max) return 2;
        return 3;
    }

    private function createPending(Account $account, string $type, float $amount, array $data, int $level): Transaction
    {
        $before    = (float) $account->balance;
        $projected = in_array($type, ['withdrawal']) ? $before - $amount : $before + $amount;

        return Transaction::create([
            'reference'              => $this->generateReference(),
            'account_id'             => $account->id,
            'type'                   => $type,
            'amount'                 => $amount,
            'balance_before'         => $before,
            'balance_after'          => $projected,
            'status'                 => 'pending',
            'description'            => $data['description'] ?? null,
            'notes'                  => $data['notes'] ?? null,
            'currency'               => $account->currency,
            'teller_till_id'         => $this->getTellerTillId(),
            'requires_approval'      => true,
            'approval_level_required'=> $level,
            'approval_level_reached' => 0,
            'processed_by'           => Auth::id(),
            'completed_at'           => null,
        ]);
    }

    private function getTellerTillId(): ?int
    {
        return TellerTill::where('teller_id', Auth::id())
            ->where('business_date', today()->toDateString())
            ->where('status', 'open')
            ->value('id');
    }

    private function generateReference(): string
    {
        $date = now()->format('Ymd');
        $key  = "txn_seq_{$date}";

        $setting = DB::table('settings')->where('key', $key)->lockForUpdate()->first();

        if (!$setting) {
            $next = 1;
            DB::table('settings')->insert([
                'key'        => $key,
                'value'      => '1',
                'group'      => 'system',
                'label'      => "Transaction Sequence {$date}",
                'type'       => 'integer',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $next = (int) $setting->value + 1;
            DB::table('settings')->where('key', $key)->update([
                'value'      => (string) $next,
                'updated_at' => now(),
            ]);
        }

        return 'TXN-' . $date . '-' . str_pad($next, 5, '0', STR_PAD_LEFT);
    }
}
