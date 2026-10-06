<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceCodeService
{
    public function __construct(private ApprovalService $approvals) {}

    // Self-service withdrawal straight from the portal — no code, no teller.
    // Runs through the same executor as approve() so the live balance,
    // minimum balance and GL posting are checked in one place; with no till
    // attached, the GL credit goes to the 1020 clearing account.
    public function withdrawDirect(Customer $customer, Account $account, float $amount, ?string $description): Transaction
    {
        return $this->executeDirect($customer, $account, 'withdrawal', $amount, $description);
    }

    // Demo counterpart of withdrawDirect(): credits the account straight away
    // with no cash or teller involved — the GL debit lands on 1020 clearing.
    public function depositDirect(Customer $customer, Account $account, float $amount, ?string $description): Transaction
    {
        return $this->executeDirect($customer, $account, 'deposit', $amount, $description);
    }

    private function executeDirect(Customer $customer, Account $account, string $type, float $amount, ?string $description): Transaction
    {
        abort_unless($account->customer_id === $customer->id, 403);

        if (!$account->isOperational()) {
            $verb = $type === 'deposit' ? 'deposit into' : 'withdraw from';
            throw ValidationException::withMessages(['account' => "Cannot {$verb} a {$account->status} account."]);
        }

        return DB::transaction(function () use ($customer, $account, $type, $amount, $description) {
            $before = (float) $account->balance;

            $txn = Transaction::create([
                'reference'                => $this->generateTxnReference(),
                'account_id'               => $account->id,
                'type'                     => $type,
                'amount'                   => $amount,
                'balance_before'           => $before,
                'balance_after'            => $type === 'deposit' ? $before + $amount : $before - $amount,
                'status'                   => 'pending',
                'description'              => $description ?: 'Portal ' . $type,
                'currency'                 => $account->currency,
                'requires_approval'        => false,
                'initiated_by_customer_id' => $customer->id,
            ]);

            $this->approvals->execute($txn);

            AuditLog::record("portal.{$type}", 'transactions',
                "Customer {$customer->name} made a portal {$type} of {$account->currency} {$amount} on {$account->account_number} ({$txn->reference})");

            return $txn->fresh();
        });
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
