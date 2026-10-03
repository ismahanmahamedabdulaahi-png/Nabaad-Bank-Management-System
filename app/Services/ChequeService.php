<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Cheque;
use App\Models\ChequeBook;
use App\Models\Customer;
use App\Models\TellerTill;
use App\Models\TillCashMovement;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChequeService
{
    public function __construct(private GeneralLedgerService $gl)
    {
    }

    // ── Issue Cheque Book ─────────────────────────────────────────────────────

    public function issueBook(Customer $customer, Account $account, array $data): ChequeBook
    {
        $leaves       = (int) ($data['total_leaves'] ?? $this->setting('cheque_book_leaves', 25));
        $expiryMonths = (int)  $this->setting('cheque_expiry_months', 6);
        $expiryDate   = now()->addMonths($expiryMonths)->toDateString();

        return DB::transaction(function () use ($customer, $account, $leaves, $expiryDate, $data) {
            $start = $this->reserveLeafBlock($leaves);
            $end   = $start + $leaves - 1;

            $prefix      = 'GAR';
            $seriesStart = $prefix . str_pad($start, 7, '0', STR_PAD_LEFT);
            $seriesEnd   = $prefix . str_pad($end,   7, '0', STR_PAD_LEFT);

            $book = ChequeBook::create([
                'book_number'  => $this->generateBookNumber(),
                'customer_id'  => $customer->id,
                'account_id'   => $account->id,
                'series_start' => $seriesStart,
                'series_end'   => $seriesEnd,
                'total_leaves' => $leaves,
                'used_leaves'  => 0,
                'status'       => 'active',
                'issued_by'    => Auth::id(),
                'issued_at'    => now(),
            ]);

            $rows = [];
            for ($i = 0; $i < $leaves; $i++) {
                $rows[] = [
                    'cheque_book_id' => $book->id,
                    'account_id'     => $account->id,
                    'cheque_number'  => $prefix . str_pad($start + $i, 7, '0', STR_PAD_LEFT),
                    'status'         => 'issued',
                    'issue_date'     => today()->toDateString(),
                    'expiry_date'    => $expiryDate,
                    'notes'          => $data['notes'] ?? null,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ];
            }
            Cheque::insert($rows);

            AuditLog::record('cheque.issue_book', 'cheque_books',
                "Cheque book {$book->book_number} issued to {$customer->name} ({$seriesStart}–{$seriesEnd})", [], [
                    'book_id' => $book->id, 'leaves' => $leaves,
                ]);

            return $book->fresh();
        });
    }

    // ── Scenario 1: Cash Encashment ───────────────────────────────────────────
    // Drawer account debited + Teller till decreased + cash given to beneficiary
    // Cheque status → paid

    public function encashCheque(Cheque $cheque, array $data): Cheque
    {
        $this->validateForPresentation($cheque);

        $amount    = (float) $data['amount'];
        $payeeName = trim($data['payee_name'] ?? '');

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
        }

        return DB::transaction(function () use ($cheque, $amount, $payeeName, $data) {
            // Validate & debit the drawer account
            $account = Account::lockForUpdate()->findOrFail($cheque->account_id);
            $this->validateAccount($account, $amount);

            $beforeAcc = (float) $account->balance;
            $afterAcc  = $beforeAcc - $amount;
            $account->update(['balance' => $afterAcc, 'last_activity_date' => today()]);

            // Debit the teller till if the current user has an open till today
            $till = $this->currentTill();
            if ($till) {
                $till->lockForUpdate();
                $till->decrement('current_balance', $amount);
                TillCashMovement::create([
                    'till_id'      => $till->id,
                    'type'         => 'transfer_out',
                    'amount'       => $amount,
                    'notes'        => "Cash cheque payment — {$cheque->cheque_number} to {$payeeName}",
                    'processed_by' => Auth::id(),
                ]);
            }

            $ref = $this->generateTxnRef();
            $txn = Transaction::create([
                'reference'         => $ref,
                'account_id'        => $account->id,
                'type'              => 'withdrawal',
                'amount'            => $amount,
                'balance_before'    => $beforeAcc,
                'balance_after'     => $afterAcc,
                'status'            => 'completed',
                'description'       => "Cash cheque encashment — {$cheque->cheque_number} to {$payeeName}",
                'notes'             => $data['notes'] ?? null,
                'currency'          => $account->currency,
                'processed_by'      => Auth::id(),
                'completed_at'      => now(),
                'requires_approval' => false,
            ]);

            $cheque->update([
                'payee_name'      => $payeeName ?: null,
                'amount'          => $amount,
                'status'          => 'used',
                'settlement_type' => 'cash',
                'deposited_at'    => now(),
                'cleared_at'      => now(),
                'processed_by'    => Auth::id(),
                'transaction_id'  => $txn->id,
                'notes'           => $data['notes'] ?? null,
            ]);

            $this->markLeafUsed($cheque);

            AuditLog::record('cheque.encash', 'cheques',
                "Cheque {$cheque->cheque_number} cashed: USD {$amount} to {$payeeName}", [], [
                    'cheque_id' => $cheque->id, 'amount' => $amount, 'txn_ref' => $ref,
                    'till_id'   => $till?->id,
                ]);

            $this->gl->post([
                ['account' => $this->gl->depositAccountFor($account), 'debit'  => $amount],
                ['account' => $this->gl->cashAccountFor($till), 'credit' => $amount],
            ], "Cheque encashment — {$cheque->cheque_number} to {$payeeName}", 'transaction', $txn->id);

            return $cheque->fresh();
        });
    }

    // ── Scenario 2: Account Deposit (T+1 Clearing Cycle) ─────────────────────
    // Drawer account debited immediately. Beneficiary credited only after T+1 via processClearing().
    // Cheque status → pending_clearance until EOD clearing runs.

    public function depositCheque(Cheque $cheque, array $data): Cheque
    {
        $this->validateForPresentation($cheque);

        $amount               = (float) $data['amount'];
        $payeeName            = trim($data['payee_name'] ?? '');
        $beneficiaryAccountId = $data['beneficiary_account_id'] ?? null;

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
        }
        if (!$beneficiaryAccountId) {
            throw ValidationException::withMessages(['beneficiary_account_id' => 'Beneficiary account is required.']);
        }

        return DB::transaction(function () use ($cheque, $amount, $payeeName, $beneficiaryAccountId, $data) {
            $drawer = Account::lockForUpdate()->findOrFail($cheque->account_id);
            $this->validateAccount($drawer, $amount);

            $beneficiary = Account::findOrFail($beneficiaryAccountId);
            if ($drawer->id === $beneficiary->id) {
                throw ValidationException::withMessages([
                    'beneficiary_account_id' => 'Beneficiary account cannot be the same as the drawer account.',
                ]);
            }
            if (!$beneficiary->isOperational()) {
                throw ValidationException::withMessages([
                    'beneficiary_account_id' => "Beneficiary account is {$beneficiary->status}.",
                ]);
            }

            // Debit drawer immediately (funds leave drawer on presentation)
            $drawerBefore = (float) $drawer->balance;
            $drawerAfter  = $drawerBefore - $amount;
            $drawer->update(['balance' => $drawerAfter, 'last_activity_date' => today()]);

            $refDebit = $this->generateTxnRef();
            $txnDebit = Transaction::create([
                'reference'         => $refDebit,
                'account_id'        => $drawer->id,
                'type'              => 'withdrawal',
                'amount'            => $amount,
                'balance_before'    => $drawerBefore,
                'balance_after'     => $drawerAfter,
                'status'            => 'completed',
                'description'       => "Cheque lodged for clearing — {$cheque->cheque_number} to {$beneficiary->account_number}",
                'notes'             => $data['notes'] ?? null,
                'currency'          => $drawer->currency,
                'processed_by'      => Auth::id(),
                'completed_at'      => now(),
                'requires_approval' => false,
            ]);

            // Clearing date = T + cheque_clearing_days (default 1 business day)
            $clearingDays = (int) $this->setting('cheque_clearing_days', 1);
            $clearingDate = now()->addDays($clearingDays)->toDateString();

            // Cheque moves to pending_clearance; beneficiary credit deferred to processClearing()
            $cheque->update([
                'payee_name'             => $payeeName ?: null,
                'amount'                 => $amount,
                'status'                 => 'pending_clearance',
                'settlement_type'        => 'account_transfer',
                'beneficiary_account_id' => $beneficiary->id,
                'deposited_at'           => now(),
                'clearing_date'          => $clearingDate,
                'cleared_at'             => null,
                'processed_by'           => Auth::id(),
                'transaction_id'         => $txnDebit->id,
                'notes'                  => $data['notes'] ?? null,
            ]);

            $this->markLeafUsed($cheque);

            AuditLog::record('cheque.deposit', 'cheques',
                "Cheque {$cheque->cheque_number} lodged for clearing — USD {$amount}. Clears on {$clearingDate}", [], [
                    'cheque_id'      => $cheque->id,
                    'amount'         => $amount,
                    'debit_ref'      => $refDebit,
                    'clearing_date'  => $clearingDate,
                    'beneficiary_id' => $beneficiary->id,
                ]);

            $this->gl->post([
                ['account' => $this->gl->depositAccountFor($drawer), 'debit'  => $amount],
                ['account' => $this->gl->accountByCode('2100'), 'credit' => $amount],
            ], "Cheque lodged for clearing — {$cheque->cheque_number}", 'cheque', $cheque->id);

            return $cheque->fresh();
        });
    }

    // ── Cancel (Stop Payment) ─────────────────────────────────────────────────

    public function cancelCheque(Cheque $cheque, string $reason): Cheque
    {
        if ($cheque->status !== 'issued') {
            throw ValidationException::withMessages([
                'cheque' => "Only undeposited (issued) cheques can be cancelled. This cheque is {$cheque->status}.",
            ]);
        }

        $cheque->update([
            'status'       => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => Auth::id(),
            'notes'        => $reason,
        ]);

        AuditLog::record('cheque.cancel', 'cheques',
            "Cheque {$cheque->cheque_number} cancelled: {$reason}", [], ['cheque_id' => $cheque->id]);

        return $cheque->fresh();
    }

    // ── Bounce ────────────────────────────────────────────────────────────────

    public function bounceCheque(Cheque $cheque, string $reason): Cheque
    {
        if ($cheque->status !== 'pending_clearance') {
            throw ValidationException::withMessages([
                'cheque' => "Only cheques pending clearance can be bounced. This cheque is {$cheque->status}.",
            ]);
        }

        return DB::transaction(function () use ($cheque, $reason) {
            $account = Account::lockForUpdate()->findOrFail($cheque->account_id);
            $amount  = (float) $cheque->amount;
            $before  = (float) $account->balance;
            $after   = $before + $amount;

            $account->update(['balance' => $after, 'last_activity_date' => today()]);

            $ref = $this->generateTxnRef();
            Transaction::create([
                'reference'         => $ref,
                'account_id'        => $account->id,
                'type'              => 'reversal',
                'amount'            => $amount,
                'balance_before'    => $before,
                'balance_after'     => $after,
                'status'            => 'completed',
                'description'       => "Bounced cheque reversal — {$cheque->cheque_number}",
                'notes'             => $reason,
                'currency'          => $account->currency,
                'processed_by'      => Auth::id(),
                'completed_at'      => now(),
                'requires_approval' => false,
            ]);

            $cheque->update(['status' => 'bounced', 'bounce_reason' => $reason]);

            $book = $cheque->chequeBook;
            $book->decrement('used_leaves');
            if ($book->status === 'exhausted') {
                $book->update(['status' => 'active']);
            }

            AuditLog::record('cheque.bounce', 'cheques',
                "Cheque {$cheque->cheque_number} bounced: {$reason}", [], [
                    'cheque_id' => $cheque->id, 'amount' => $amount,
                ]);

            $this->gl->post([
                ['account' => $this->gl->accountByCode('2100'), 'debit'  => $amount],
                ['account' => $this->gl->depositAccountFor($account), 'credit' => $amount],
            ], "Cheque bounced, refunded to drawer — {$cheque->cheque_number}: {$reason}", 'cheque', $cheque->id);

            return $cheque->fresh();
        });
    }

    // ── Clear a single cheque manually (pending_clearance → cleared) ──────────

    public function clearCheque(Cheque $cheque): Cheque
    {
        if ($cheque->status !== 'pending_clearance') {
            throw ValidationException::withMessages([
                'cheque' => "Only cheques pending clearance can be cleared. This cheque is {$cheque->status}.",
            ]);
        }

        $cheque->update(['status' => 'cleared', 'cleared_at' => now()]);

        AuditLog::record('cheque.clear', 'cheques',
            "Cheque {$cheque->cheque_number} manually cleared.", [], ['cheque_id' => $cheque->id]);

        return $cheque->fresh();
    }

    // ── Process Clearing (artisan batch) ─────────────────────────────────────
    // Credits beneficiary accounts for all pending_clearance cheques whose
    // clearing_date has arrived. Returns count of cheques cleared.

    public function processClearing(): int
    {
        $due = Cheque::where('status', 'pending_clearance')
            ->where('clearing_date', '<=', today())
            ->with(['account', 'beneficiaryAccount'])
            ->get();

        $count = 0;

        foreach ($due as $cheque) {
            try {
                DB::transaction(function () use ($cheque) {
                    $amount = (float) $cheque->amount;

                    // Credit the beneficiary account if it exists
                    if ($cheque->beneficiary_account_id && $cheque->beneficiaryAccount) {
                        $beneficiary = Account::lockForUpdate()->findOrFail($cheque->beneficiary_account_id);
                        $benBefore   = (float) $beneficiary->balance;
                        $benAfter    = $benBefore + $amount;
                        $beneficiary->update(['balance' => $benAfter, 'last_activity_date' => today()]);

                        $refCredit = $this->generateTxnRef();
                        $txnCredit = Transaction::create([
                            'reference'         => $refCredit,
                            'account_id'        => $beneficiary->id,
                            'type'              => 'deposit',
                            'amount'            => $amount,
                            'balance_before'    => $benBefore,
                            'balance_after'     => $benAfter,
                            'status'            => 'completed',
                            'description'       => "Cheque cleared — {$cheque->cheque_number} from {$cheque->account->account_number}",
                            'currency'          => $beneficiary->currency,
                            'processed_by'      => null,
                            'completed_at'      => now(),
                            'requires_approval' => false,
                        ]);

                        $cheque->update([
                            'status'               => 'cleared',
                            'cleared_at'           => now(),
                            'credit_transaction_id'=> $txnCredit->id,
                        ]);

                        AuditLog::record('cheque.cleared', 'cheques',
                            "Cheque {$cheque->cheque_number} cleared — USD {$amount} credited to {$beneficiary->account_number}", [], [
                                'cheque_id'  => $cheque->id,
                                'credit_ref' => $refCredit,
                            ]);

                        $this->gl->post([
                            ['account' => $this->gl->accountByCode('2100'), 'debit'  => $amount],
                            ['account' => $this->gl->depositAccountFor($beneficiary), 'credit' => $amount],
                        ], "Cheque cleared — {$cheque->cheque_number} to {$beneficiary->account_number}", 'cheque', $cheque->id);
                    } else {
                        // No beneficiary — just mark cleared (cash cheque or orphaned record)
                        $cheque->update(['status' => 'cleared', 'cleared_at' => now()]);
                    }
                });
                $count++;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("[ChequeClearing] Failed for cheque {$cheque->id}: " . $e->getMessage());
            }
        }

        return $count;
    }

    // ── Verify ────────────────────────────────────────────────────────────────

    public function verify(string $number): ?Cheque
    {
        return Cheque::where('cheque_number', $number)
            ->with(['chequeBook.customer', 'account.customer', 'beneficiaryAccount.customer', 'processedBy', 'transaction', 'creditTransaction'])
            ->first();
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function validateForPresentation(Cheque $cheque): void
    {
        if ($cheque->status !== 'issued') {
            throw ValidationException::withMessages(['cheque' => "Cheque is {$cheque->status} and cannot be processed."]);
        }
        if ($cheque->expiry_date && $cheque->expiry_date->isPast()) {
            throw ValidationException::withMessages(['cheque' => 'This cheque has expired.']);
        }
    }

    private function validateAccount(Account $account, float $amount): void
    {
        if (!$account->isOperational()) {
            throw ValidationException::withMessages(['cheque' => "Drawer account is {$account->status}."]);
        }
        if ($amount > (float) $account->balance) {
            throw ValidationException::withMessages([
                'amount' => "Insufficient balance. Available: USD {$account->balance}.",
            ]);
        }
    }

    private function markLeafUsed(Cheque $cheque): void
    {
        $cheque->chequeBook()->increment('used_leaves');
        $book = $cheque->chequeBook()->first();
        if ($book->used_leaves >= $book->total_leaves) {
            $book->update(['status' => 'exhausted']);
        }
    }

    private function currentTill(): ?TellerTill
    {
        return TellerTill::where('teller_id', Auth::id())
            ->where('business_date', today())
            ->where('status', 'open')
            ->first();
    }

    private function reserveLeafBlock(int $count): int
    {
        $key     = 'cheque_leaf_sequence';
        $current = DB::table('settings')->where('key', $key)->lockForUpdate()->value('value') ?? '0';
        $next    = (int) $current + 1;

        DB::table('settings')->updateOrInsert(
            ['key' => $key],
            ['value' => (string) ($next + $count - 1), 'group' => 'system',
             'label' => 'Cheque Leaf Sequence', 'type' => 'integer',
             'updated_at' => now(), 'created_at' => now()]
        );

        return $next;
    }

    private function generateBookNumber(): string
    {
        $date  = now()->format('Ymd');
        $key   = "cheque_book_seq_{$date}";
        $count = DB::table('settings')->where('key', $key)->lockForUpdate()->value('value') ?? '0';
        $next  = (int) $count + 1;
        DB::table('settings')->updateOrInsert(
            ['key' => $key],
            ['value' => (string) $next, 'group' => 'system',
             'label' => "Cheque Book Seq {$date}", 'type' => 'integer',
             'updated_at' => now(), 'created_at' => now()]
        );
        return 'CB-' . $date . '-' . str_pad($next, 3, '0', STR_PAD_LEFT);
    }

    private function generateTxnRef(): string
    {
        $date  = now()->format('Ymd');
        $key   = "txn_seq_{$date}";
        $count = DB::table('settings')->where('key', $key)->lockForUpdate()->value('value') ?? '0';
        $next  = (int) $count + 1;
        DB::table('settings')->updateOrInsert(
            ['key' => $key],
            ['value' => (string) $next, 'group' => 'system',
             'label' => "Transaction Sequence {$date}", 'type' => 'integer',
             'updated_at' => now(), 'created_at' => now()]
        );
        return 'TXN-' . $date . '-' . str_pad($next, 5, '0', STR_PAD_LEFT);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return DB::table('settings')->where('key', $key)->value('value') ?? $default;
    }
}
