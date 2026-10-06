<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\ServiceRequestCode;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\ServiceCodeIssued;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceCodeService
{
    public function __construct(
        private ApprovalService     $approvals,
        private TransactionService  $transactions,
    ) {}

    // A cardless cash pickup: debits the account right away (as a pending
    // transaction, same as any other in-flight debit) so the customer can't
    // spend the same money elsewhere and also collect it at the counter —
    // though, like the rest of this app's approval-pending transactions,
    // this doesn't hard-reserve funds; redeem() re-checks the live balance
    // as the actual safety net, via the same executor approve() uses.
    public function requestWithdrawal(Customer $customer, Account $account, float $amount, ?string $description): ServiceRequestCode
    {
        abort_unless($account->customer_id === $customer->id, 403);

        if (!$account->isOperational()) {
            throw ValidationException::withMessages(['account' => "Cannot withdraw from a {$account->status} account."]);
        }
        if ($amount > (float) $account->balance) {
            throw ValidationException::withMessages([
                'amount' => "Insufficient balance. Available: {$account->currency} {$account->balance}.",
            ]);
        }

        return DB::transaction(function () use ($customer, $account, $amount, $description) {
            $before = (float) $account->balance;

            $txn = Transaction::create([
                'reference'                => $this->generateTxnReference(),
                'account_id'               => $account->id,
                'type'                     => 'withdrawal',
                'amount'                   => $amount,
                'balance_before'           => $before,
                'balance_after'            => $before - $amount,
                'status'                   => 'pending',
                'description'              => $description ?: 'Cardless withdrawal code',
                'currency'                 => $account->currency,
                'requires_approval'        => false,
                'initiated_by_customer_id' => $customer->id,
            ]);

            $ttl = (int) (DB::table('settings')->where('key', 'withdrawal_code_ttl_minutes')->value('value') ?? 120);

            $code = ServiceRequestCode::create([
                'customer_id'    => $customer->id,
                'account_id'     => $account->id,
                'type'           => 'withdrawal',
                'amount'         => $amount,
                'code'           => $this->generateCode(),
                'status'         => 'pending',
                'transaction_id' => $txn->id,
                'expires_at'     => now()->addMinutes($ttl),
            ]);

            AuditLog::record('service_code.requested', 'service_request_codes',
                "Customer {$customer->name} requested a withdrawal code for {$account->currency} {$amount} on {$account->account_number}");

            $customer->notify(new ServiceCodeIssued($code));

            return $code;
        });
    }

    // Self-service withdrawal straight from the portal — no code, no teller.
    // Runs through the same executor as approve()/redeemWithdrawal() so the
    // live balance, minimum balance and GL posting are checked in one place;
    // with no till attached, the GL credit goes to the 1020 clearing account.
    public function withdrawDirect(Customer $customer, Account $account, float $amount, ?string $description): Transaction
    {
        abort_unless($account->customer_id === $customer->id, 403);

        if (!$account->isOperational()) {
            throw ValidationException::withMessages(['account' => "Cannot withdraw from a {$account->status} account."]);
        }

        return DB::transaction(function () use ($customer, $account, $amount, $description) {
            $before = (float) $account->balance;

            $txn = Transaction::create([
                'reference'                => $this->generateTxnReference(),
                'account_id'               => $account->id,
                'type'                     => 'withdrawal',
                'amount'                   => $amount,
                'balance_before'           => $before,
                'balance_after'            => $before - $amount,
                'status'                   => 'pending',
                'description'              => $description ?: 'Portal withdrawal',
                'currency'                 => $account->currency,
                'requires_approval'        => false,
                'initiated_by_customer_id' => $customer->id,
            ]);

            $this->approvals->execute($txn);

            AuditLog::record('portal.withdrawal', 'transactions',
                "Customer {$customer->name} withdrew {$account->currency} {$amount} from {$account->account_number} via the portal ({$txn->reference})");

            return $txn->fresh();
        });
    }

    // No money exists in the system yet, so — unlike withdrawal — nothing is
    // debited or created here; it's purely a heads-up code the teller can
    // look up to speed up the counter visit. The real deposit only comes
    // into existence at redemption, when cash is actually handed over.
    public function requestDeposit(Customer $customer, Account $account, float $amount, ?string $description): ServiceRequestCode
    {
        abort_unless($account->customer_id === $customer->id, 403);

        if (!$account->isOperational()) {
            throw ValidationException::withMessages(['account' => "Cannot deposit into a {$account->status} account."]);
        }

        $ttl = (int) (DB::table('settings')->where('key', 'deposit_code_ttl_minutes')->value('value') ?? 1440);

        $code = ServiceRequestCode::create([
            'customer_id' => $customer->id,
            'account_id'  => $account->id,
            'type'        => 'deposit',
            'amount'      => $amount,
            'code'        => $this->generateCode(),
            'status'      => 'pending',
            'expires_at'  => now()->addMinutes($ttl),
        ]);

        AuditLog::record('service_code.requested', 'service_request_codes',
            "Customer {$customer->name} pre-registered a deposit of {$account->currency} {$amount} on {$account->account_number}");

        $customer->notify(new ServiceCodeIssued($code));

        return $code;
    }

    public function findActiveCode(string $rawCode): ServiceRequestCode
    {
        $code = ServiceRequestCode::where('code', strtoupper(trim($rawCode)))->first();

        if (!$code) {
            throw ValidationException::withMessages(['code' => 'No matching code found.']);
        }

        if ($code->isExpired()) {
            $code->update(['status' => 'expired']);
        }

        if ($code->status !== 'pending') {
            throw ValidationException::withMessages(['code' => "This code is {$code->status} and can no longer be used."]);
        }

        return $code->load(['customer', 'account']);
    }

    public function redeemWithdrawal(ServiceRequestCode $code, User $teller): Transaction
    {
        abort_if($code->type !== 'withdrawal', 422, 'Not a withdrawal code.');

        $transaction = $code->transaction;

        // Re-checks operational status + live balance and posts the GL entry
        // — the exact same path approve() uses for any other pending
        // transaction, so there is only one place withdrawals actually execute.
        $this->approvals->execute($transaction);

        $code->update(['status' => 'redeemed', 'redeemed_by' => $teller->id, 'redeemed_at' => now()]);

        AuditLog::record('service_code.redeemed', 'service_request_codes',
            "Withdrawal code {$code->code} redeemed by {$teller->name} for {$transaction->fresh()->reference}");

        return $transaction->fresh();
    }

    public function redeemDeposit(ServiceRequestCode $code, User $teller, float $confirmedAmount, ?string $notes): Transaction
    {
        abort_if($code->type !== 'deposit', 422, 'Not a deposit code.');

        // TransactionService::deposit() attributes the transaction via
        // Auth::id() — the caller (the teller's own authenticated request)
        // is expected to already be that user, same convention every other
        // money-movement call in this app relies on.
        $transaction = $this->transactions->deposit($code->account, [
            'amount'      => $confirmedAmount,
            'description' => 'Cash deposit — pre-registered code ' . $code->code,
            'notes'       => $notes,
        ]);

        $transaction->update([
            'initiated_by_customer_id' => $code->customer_id,
        ]);

        $code->update([
            'status'         => 'redeemed',
            'transaction_id' => $transaction->id,
            'redeemed_by'    => $teller->id,
            'redeemed_at'    => now(),
        ]);

        AuditLog::record('service_code.redeemed', 'service_request_codes',
            "Deposit code {$code->code} redeemed by {$teller->name} for {$transaction->reference}");

        return $transaction;
    }

    private function generateCode(): string
    {
        do {
            $code = (string) random_int(100000, 999999);
        } while (ServiceRequestCode::where('code', $code)->where('status', 'pending')->exists());

        return $code;
    }

    private function generateTxnReference(): string
    {
        $date = now()->format('Ymd');
        $key  = "txn_seq_{$date}";

        $current = DB::table('settings')->where('key', $key)->lockForUpdate()->value('value') ?? '0';
        $next    = (int) $current + 1;

        DB::table('settings')->updateOrInsert(
            ['key' => $key],
            ['value' => (string) $next, 'group' => 'system', 'label' => "Transaction Sequence {$date}",
             'type' => 'integer', 'updated_at' => now(), 'created_at' => now()]
        );

        return 'TXN-' . $date . '-' . str_pad($next, 5, '0', STR_PAD_LEFT);
    }
}
