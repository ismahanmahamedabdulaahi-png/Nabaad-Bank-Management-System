<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\LoanRepaymentSchedule;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoanService
{
    public function __construct(private GeneralLedgerService $gl)
    {
    }

    // ── EMI (Reducing Balance) ────────────────────────────────────────────────

    public function calculateEMI(float $principal, float $annualRate, int $tenureMonths): array
    {
        if ($annualRate <= 0) {
            $emi          = round($principal / $tenureMonths, 2);
            $totalPayable = $emi * $tenureMonths;
            return [
                'monthly_emi'    => $emi,
                'total_payable'  => $totalPayable,
                'total_interest' => 0.00,
            ];
        }

        $r   = $annualRate / 12 / 100;
        $pow = pow(1 + $r, $tenureMonths);
        $emi = round($principal * $r * $pow / ($pow - 1), 2);

        $totalInterest = round($emi * $tenureMonths - $principal, 2);
        $totalPayable  = round($principal + $totalInterest, 2);

        return [
            'monthly_emi'    => $emi,
            'total_payable'  => $totalPayable,
            'total_interest' => $totalInterest,
        ];
    }

    // ── Apply ─────────────────────────────────────────────────────────────────

    public function apply(Customer $customer, Account $account, array $data): Loan
    {
        $principal   = (float) $data['amount'];
        $rate        = (float) $data['interest_rate'];
        $tenure      = (int)   $data['tenure_months'];
        $graceDays   = (int)   $this->setting('loan_grace_period_days', 5);
        $penaltyRate = (float) $this->setting('loan_penalty_rate', 2.00);

        $emiData = $this->calculateEMI($principal, $rate, $tenure);

        $firstRepayment = Carbon::parse($data['first_repayment_date'] ?? today()->addMonth());

        $loan = Loan::create([
            'loan_number'                 => $this->generateLoanNumber(),
            'customer_id'                 => $customer->id,
            'account_id'                  => $account->id,
            'amount'                      => $principal,
            'interest_rate'               => $rate,
            'tenure_months'               => $tenure,
            'monthly_emi'                 => $emiData['monthly_emi'],
            'total_payable'               => $emiData['total_payable'],
            'total_interest'              => $emiData['total_interest'],
            'outstanding_balance'         => $principal,
            'total_paid'                  => 0.00,
            'purpose'                     => $data['purpose']    ?? null,
            'collateral'                  => $data['collateral'] ?? null,
            'status'                      => 'pending',
            'grace_period_days'           => $graceDays,
            'penalty_rate'                => $penaltyRate,
            'first_repayment_date'        => $firstRepayment,
            'account_age_months'          => $this->accountAgeMonths($account),
            'prev_year_transaction_volume'=> $this->prevYearVolume($account),
        ]);

        $this->generateSchedule($loan);

        AuditLog::record('loan.apply', 'loans',
            "Loan application {$loan->loan_number} for {$customer->name}: USD {$principal}", [], [
                'loan_id' => $loan->id,
                'amount'  => $principal,
            ]);

        return $loan->fresh(['repaymentSchedules']);
    }

    // ── Generate Repayment Schedule ───────────────────────────────────────────

    private function generateSchedule(Loan $loan): void
    {
        $outstanding = (float) $loan->amount;
        $r           = (float) $loan->interest_rate / 12 / 100;
        $emi         = (float) $loan->monthly_emi;
        $grace       = (int)   $loan->grace_period_days;
        $tenure      = (int)   $loan->tenure_months;
        $startDate   = Carbon::parse($loan->first_repayment_date);

        $schedules = [];

        for ($i = 1; $i <= $tenure; $i++) {
            $interest  = $r > 0 ? round($outstanding * $r, 2) : 0.00;
            $principal = ($i === $tenure)
                ? round($outstanding, 2)             // last installment clears the balance
                : round($emi - $interest, 2);

            $dueDate      = $startDate->copy()->addMonths($i - 1);
            $graceDeadline= $dueDate->copy()->addDays($grace);
            $balanceAfter = round(max(0, $outstanding - $principal), 2);

            $schedules[] = [
                'loan_id'                 => $loan->id,
                'installment_number'      => $i,
                'due_date'                => $dueDate->toDateString(),
                'grace_deadline'          => $graceDeadline->toDateString(),
                'principal_amount'        => $principal,
                'interest_amount'         => $interest,
                'emi_amount'              => $emi,
                'penalty_amount'          => 0.00,
                'total_due'               => round($interest + $principal, 2),
                'amount_paid'             => 0.00,
                'outstanding_balance_after' => $balanceAfter,
                'status'                  => 'pending',
                'created_at'              => now(),
                'updated_at'              => now(),
            ];

            $outstanding = $balanceAfter;
        }

        LoanRepaymentSchedule::insert($schedules);
    }

    // ── Review ────────────────────────────────────────────────────────────────

    public function review(Loan $loan, string $notes): Loan
    {
        if ($loan->status !== 'pending') {
            throw ValidationException::withMessages(['loan' => 'Only pending loans can be sent for review.']);
        }

        $loan->update([
            'status'      => 'under_review',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        AuditLog::record('loan.review', 'loans', "Loan {$loan->loan_number} sent for review", [], ['loan_id' => $loan->id]);

        return $loan->fresh();
    }

    // ── Approve ───────────────────────────────────────────────────────────────

    public function approve(Loan $loan, ?string $notes): Loan
    {
        if (!in_array($loan->status, ['pending', 'under_review'])) {
            throw ValidationException::withMessages(['loan' => 'Loan cannot be approved in its current status.']);
        }

        $loan->update([
            'status'      => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        AuditLog::record('loan.approve', 'loans', "Loan {$loan->loan_number} approved", [], ['loan_id' => $loan->id]);

        return $loan->fresh();
    }

    // ── Reject ────────────────────────────────────────────────────────────────

    public function reject(Loan $loan, string $reason): Loan
    {
        if (!in_array($loan->status, ['pending', 'under_review', 'approved'])) {
            throw ValidationException::withMessages(['loan' => 'This loan cannot be rejected in its current status.']);
        }

        $loan->update([
            'status'           => 'rejected',
            'rejection_reason' => $reason,
        ]);

        AuditLog::record('loan.reject', 'loans', "Loan {$loan->loan_number} rejected: {$reason}", [], ['loan_id' => $loan->id]);

        return $loan->fresh();
    }

    // ── Disburse ──────────────────────────────────────────────────────────────

    public function disburse(Loan $loan): Loan
    {
        if ($loan->status !== 'approved') {
            throw ValidationException::withMessages(['loan' => 'Only approved loans can be disbursed.']);
        }

        return DB::transaction(function () use ($loan) {
            $account = Account::lockForUpdate()->findOrFail($loan->account_id);

            if (!$account->isOperational()) {
                throw ValidationException::withMessages([
                    'account' => "Cannot disburse into a {$account->status} account.",
                ]);
            }

            $amount = (float) $loan->amount;
            $before = (float) $account->balance;
            $after  = $before + $amount;

            $account->update(['balance' => $after, 'last_activity_date' => today()]);

            // Create disbursement transaction
            $ref = $this->generateTransactionRef();
            $txn = Transaction::create([
                'reference'         => $ref,
                'account_id'        => $account->id,
                'type'              => 'loan_disbursement',
                'amount'            => $amount,
                'balance_before'    => $before,
                'balance_after'     => $after,
                'status'            => 'completed',
                'description'       => "Loan disbursement — {$loan->loan_number}",
                'currency'          => $account->currency,
                'processed_by'      => Auth::id(),
                'completed_at'      => now(),
                'requires_approval' => false,
            ]);

            $loan->update([
                'status'                    => 'active',
                'disbursed_by'              => Auth::id(),
                'disbursed_at'              => now(),
                'disbursement_transaction_id' => $txn->id,
            ]);

            AuditLog::record('loan.disburse', 'loans',
                "Loan {$loan->loan_number} disbursed: USD {$amount} → {$account->account_number}", [], [
                    'loan_id' => $loan->id,
                    'txn_ref' => $ref,
                ]);

            $this->gl->post([
                ['account' => $this->gl->accountByCode('1100'), 'debit'  => $amount],
                ['account' => $this->gl->depositAccountFor($account), 'credit' => $amount],
            ], "Loan disbursement — {$loan->loan_number} → {$account->account_number}", 'transaction', $txn->id);

            return $loan->fresh();
        });
    }

    // ── Repayment ─────────────────────────────────────────────────────────────

    public function makeRepayment(Loan $loan, LoanRepaymentSchedule $installment, float $amountPaid, ?string $notes): LoanPayment
    {
        if (!in_array($loan->status, ['active', 'overdue'])) {
            throw ValidationException::withMessages(['loan' => 'Repayments can only be made on active or overdue loans.']);
        }

        if ($installment->isPaid()) {
            throw ValidationException::withMessages(['schedule_id' => 'This installment is already fully paid.']);
        }

        return DB::transaction(function () use ($loan, $installment, $amountPaid, $notes) {
            $account = Account::lockForUpdate()->findOrFail($loan->account_id);

            $totalDue = (float) $installment->total_due - (float) $installment->amount_paid;

            if ($amountPaid > (float) $account->balance) {
                throw ValidationException::withMessages([
                    'amount' => "Insufficient account balance. Available: USD {$account->balance}.",
                ]);
            }

            // Split payment: penalty first, then interest, then principal
            $penaltyDue   = max(0, (float) $installment->penalty_amount - (float) $installment->amount_paid);
            $penaltyPaid  = min($amountPaid, $penaltyDue);
            $remaining    = $amountPaid - $penaltyPaid;
            $interestDue  = (float) $installment->interest_amount;
            $interestPaid = min($remaining, $interestDue);
            $principalPaid= round($remaining - $interestPaid, 2);

            $before = (float) $account->balance;
            $after  = $before - $amountPaid;

            $account->update(['balance' => $after, 'last_activity_date' => today()]);

            // Debit transaction
            $ref = $this->generateTransactionRef();
            $txn = Transaction::create([
                'reference'         => $ref,
                'account_id'        => $account->id,
                'type'              => 'loan_repayment',
                'amount'            => $amountPaid,
                'balance_before'    => $before,
                'balance_after'     => $after,
                'status'            => 'completed',
                'description'       => "Loan repayment — {$loan->loan_number} Installment #{$installment->installment_number}",
                'currency'          => $account->currency,
                'processed_by'      => Auth::id(),
                'completed_at'      => now(),
                'requires_approval' => false,
            ]);

            // Update installment
            $newAmountPaid = (float) $installment->amount_paid + $amountPaid;
            $isPaid        = $newAmountPaid >= (float) $installment->total_due;

            $installment->update([
                'amount_paid' => $newAmountPaid,
                'status'      => $isPaid ? 'paid' : 'partially_paid',
                'paid_date'   => $isPaid ? today() : null,
            ]);

            // Record payment
            $payment = LoanPayment::create([
                'loan_id'        => $loan->id,
                'schedule_id'    => $installment->id,
                'amount_paid'    => $amountPaid,
                'principal_paid' => $principalPaid,
                'interest_paid'  => $interestPaid,
                'penalty_paid'   => $penaltyPaid,
                'payment_date'   => today(),
                'transaction_id' => $txn->id,
                'processed_by'   => Auth::id(),
                'notes'          => $notes,
            ]);

            // Update loan totals
            $newOutstanding = max(0, (float) $loan->outstanding_balance - $principalPaid);
            $newTotalPaid   = (float) $loan->total_paid + $amountPaid;

            $loanStatus = $loan->status;
            if ($newOutstanding <= 0 || $this->allInstallmentsPaid($loan)) {
                $loanStatus = 'closed';
                $loan->update(['closed_at' => now()]);
            }

            $loan->update([
                'outstanding_balance' => $newOutstanding,
                'total_paid'          => $newTotalPaid,
                'status'              => $loanStatus,
            ]);

            AuditLog::record('loan.repayment', 'loans',
                "Repayment on {$loan->loan_number}: USD {$amountPaid} (Installment #{$installment->installment_number})", [], [
                    'loan_id'    => $loan->id,
                    'payment_id' => $payment->id,
                    'txn_ref'    => $ref,
                ]);

            $creditLines = array_filter([
                $principalPaid > 0 ? ['account' => $this->gl->accountByCode('1100'), 'credit' => $principalPaid] : null,
                $interestPaid  > 0 ? ['account' => $this->gl->accountByCode('4000'), 'credit' => $interestPaid]  : null,
                $penaltyPaid   > 0 ? ['account' => $this->gl->accountByCode('4200'), 'credit' => $penaltyPaid]   : null,
            ]);

            $this->gl->post([
                ['account' => $this->gl->depositAccountFor($account), 'debit' => $amountPaid],
                ...$creditLines,
            ], "Loan repayment — {$loan->loan_number} Installment #{$installment->installment_number}", 'transaction', $txn->id);

            return $payment;
        });
    }

    // ── Apply Penalties (artisan command) ─────────────────────────────────────

    public function applyPenalties(): int
    {
        $today = today();
        $count = 0;

        $overdueSchedules = LoanRepaymentSchedule::where('status', '!=', 'paid')
            ->where('grace_deadline', '<', $today)
            ->whereHas('loan', fn ($q) => $q->whereIn('status', ['active', 'overdue']))
            ->with('loan')
            ->get();

        foreach ($overdueSchedules as $schedule) {
            $loan        = $schedule->loan;
            $monthlyRate = (float) $loan->penalty_rate / 100;
            $outstanding = (float) $schedule->emi_amount - (float) $schedule->amount_paid;
            $penalty     = round($outstanding * $monthlyRate, 2);

            if ($penalty > (float) $schedule->penalty_amount) {
                $schedule->update([
                    'penalty_amount' => $penalty,
                    'total_due'      => (float) $schedule->emi_amount + $penalty,
                    'status'         => 'overdue',
                ]);
                $count++;
            }

            // Mark the loan itself as overdue
            if ($loan->status === 'active') {
                $loan->update(['status' => 'overdue']);
            }
        }

        return $count;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function allInstallmentsPaid(Loan $loan): bool
    {
        return !LoanRepaymentSchedule::where('loan_id', $loan->id)
            ->whereNotIn('status', ['paid'])
            ->exists();
    }

    private function generateLoanNumber(): string
    {
        $date  = now()->format('Ymd');
        $key   = "loan_seq_{$date}";
        $count = DB::table('settings')->where('key', $key)->value('value');

        if (!$count) {
            DB::table('settings')->insert([
                'key' => $key, 'value' => '1', 'group' => 'system',
                'label' => "Loan Sequence {$date}", 'type' => 'integer',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $next = 1;
        } else {
            $next = (int) $count + 1;
            DB::table('settings')->where('key', $key)->update(['value' => (string) $next, 'updated_at' => now()]);
        }

        return 'LN-' . $date . '-' . str_pad($next, 5, '0', STR_PAD_LEFT);
    }

    private function generateTransactionRef(): string
    {
        $date  = now()->format('Ymd');
        $key   = "txn_seq_{$date}";
        $count = DB::table('settings')->where('key', $key)->lockForUpdate()->value('value');

        if (!$count) {
            DB::table('settings')->insert([
                'key' => $key, 'value' => '1', 'group' => 'system',
                'label' => "Transaction Sequence {$date}", 'type' => 'integer',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $next = 1;
        } else {
            $next = (int) $count + 1;
            DB::table('settings')->where('key', $key)->update(['value' => (string) $next, 'updated_at' => now()]);
        }

        return 'TXN-' . $date . '-' . str_pad($next, 5, '0', STR_PAD_LEFT);
    }

    private function accountAgeMonths(Account $account): int
    {
        return (int) Carbon::parse($account->opened_at ?? $account->created_at)->diffInMonths(now());
    }

    private function prevYearVolume(Account $account): float
    {
        return (float) Transaction::where('account_id', $account->id)
            ->where('status', 'completed')
            ->whereBetween('created_at', [now()->subYear()->startOfYear(), now()->subYear()->endOfYear()])
            ->sum('amount');
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return DB::table('settings')->where('key', $key)->value('value') ?? $default;
    }

    public function loanDefaults(): array
    {
        return [
            'interest_rate'       => (float) $this->setting('loan_interest_rate', 5.00),
            'grace_period_days'   => (int)   $this->setting('loan_grace_period_days', 5),
            'penalty_rate'        => (float) $this->setting('loan_penalty_rate', 2.00),
            'min_account_age'     => (int)   $this->setting('loan_min_account_age_months', 12),
            'min_txn_volume'      => (float) $this->setting('loan_min_transaction_volume', 20000),
        ];
    }
}
