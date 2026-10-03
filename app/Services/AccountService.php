<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Repositories\Contracts\AccountRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountService
{
    public function __construct(private AccountRepositoryInterface $repo) {}

    public function list(array $filters = []): LengthAwarePaginator
    {
        return $this->repo->paginate($filters);
    }

    public function open(Customer $customer, array $data): Account
    {
        if (!$customer->isActive()) {
            throw ValidationException::withMessages([
                'customer_id' => 'Only active customers can open accounts.',
            ]);
        }

        if (!$customer->hasApprovedKyc()) {
            throw ValidationException::withMessages([
                'customer_id' => 'Customer must have approved KYC before opening an account.',
            ]);
        }

        $branch        = Branch::findOrFail($data['branch_id']);
        $accountNumber = $this->generateAccountNumber($branch, $data['account_type']);

        $fields = [
            'account_number'  => $accountNumber,
            'customer_id'     => $customer->id,
            'branch_id'       => $branch->id,
            'account_type'    => $data['account_type'],
            'status'          => 'active',
            'balance'         => $data['opening_balance'] ?? 0.00,
            'interest_rate'   => $data['interest_rate'] ?? 0.00,
            'minimum_balance' => $data['minimum_balance'] ?? 0.00,
            'currency'        => $data['currency'] ?? 'USD',
            'opening_date'    => now()->toDateString(),
            'opened_by'       => Auth::id(),
            'notes'           => $data['notes'] ?? null,
        ];

        if ($data['account_type'] === 'fixed_deposit') {
            $tenure = (int) $data['fd_tenure_months'];
            $fields['fd_tenure_months']   = $tenure;
            $fields['fd_maturity_date']   = now()->addMonths($tenure)->toDateString();
            $fields['fd_maturity_action'] = $data['fd_maturity_action'] ?? 'pending';
        }

        $account = $this->repo->create($fields);

        AuditLog::record('account_opened', 'accounts', "Opened {$account->getTypeLabel()} account {$accountNumber} for {$customer->name}", [], [
            'account_number' => $accountNumber,
            'account_type'   => $account->account_type,
            'customer'       => $customer->customer_number,
        ]);

        return $account;
    }

    public function freeze(Account $account, ?string $reason = null): Account
    {
        if (!$account->isActive() && !$account->isDormant()) {
            throw ValidationException::withMessages([
                'account' => "Only active or dormant accounts can be frozen (current status: {$account->status}).",
            ]);
        }

        $account = $this->repo->update($account, [
            'status'    => 'frozen',
            'frozen_by' => Auth::id(),
            'frozen_at' => now(),
            'notes'     => $reason ?? $account->notes,
        ]);

        AuditLog::record('account_frozen', 'accounts', "Frozen account {$account->account_number}" . ($reason ? ": {$reason}" : ''));

        return $account;
    }

    public function unfreeze(Account $account): Account
    {
        if (!$account->isFrozen()) {
            throw ValidationException::withMessages([
                'account' => 'Only frozen accounts can be unfrozen.',
            ]);
        }

        $account = $this->repo->update($account, [
            'status'    => 'active',
            'frozen_by' => null,
            'frozen_at' => null,
        ]);

        AuditLog::record('account_unfrozen', 'accounts', "Unfrozen account {$account->account_number}");

        return $account;
    }

    public function close(Account $account, ?string $reason = null): Account
    {
        if ($account->isClosed()) {
            throw ValidationException::withMessages([
                'account' => 'Account is already closed.',
            ]);
        }

        if ($account->balance > 0) {
            throw ValidationException::withMessages([
                'account' => 'Cannot close an account with a positive balance. Withdraw the remaining balance first.',
            ]);
        }

        $account = $this->repo->update($account, [
            'status'    => 'closed',
            'closed_by' => Auth::id(),
            'closed_at' => now(),
            'notes'     => $reason ?? $account->notes,
        ]);

        AuditLog::record('account_closed', 'accounts', "Closed account {$account->account_number}" . ($reason ? ": {$reason}" : ''));

        return $account;
    }

    public function reactivate(Account $account): Account
    {
        if (!in_array($account->status, ['dormant', 'frozen'])) {
            throw ValidationException::withMessages([
                'account' => 'Only dormant or frozen accounts can be reactivated.',
            ]);
        }

        $account = $this->repo->update($account, [
            'status'       => 'active',
            'dormant_since'=> null,
            'frozen_by'    => null,
            'frozen_at'    => null,
        ]);

        AuditLog::record('account_reactivated', 'accounts', "Reactivated account {$account->account_number}");

        return $account;
    }

    private function generateAccountNumber(Branch $branch, string $type): string
    {
        $typeAbbr = match ($type) {
            'savings'       => 'SA',
            'current'       => 'CA',
            'fixed_deposit' => 'FD',
            default         => 'XX',
        };

        $key = "account_seq_{$branch->code}_{$typeAbbr}";

        return DB::transaction(function () use ($branch, $typeAbbr, $key) {
            $setting = DB::table('settings')->where('key', $key)->lockForUpdate()->first();

            if (!$setting) {
                $next = 1;
                DB::table('settings')->insert([
                    'key'        => $key,
                    'value'      => '1',
                    'group'      => 'system',
                    'label'      => "Account Sequence {$branch->code} {$typeAbbr}",
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

            return $branch->code . $typeAbbr . str_pad($next, 5, '0', STR_PAD_LEFT);
        });
    }
}
