<?php

namespace Database\Seeders;

use App\Models\GlAccount;
use Illuminate\Database\Seeder;

class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['code' => '1000', 'name' => 'Cash — Vault',                    'type' => 'asset',     'normal_balance' => 'debit',  'description' => 'Physical cash held in the branch vault.'],
            ['code' => '1010', 'name' => 'Cash — Teller Tills',             'type' => 'asset',     'normal_balance' => 'debit',  'description' => 'Physical cash held across all teller tills.'],
            ['code' => '1020', 'name' => 'Cash — Off-Till',                 'type' => 'asset',     'normal_balance' => 'debit',  'description' => 'Cash side of a deposit/withdrawal posted by a user with no open teller till (e.g. a manager-entered adjustment). Not backed by the Vault or any till, so it has no subsidiary ledger to reconcile against — a nonzero balance here is a signal to investigate.'],
            ['code' => '1100', 'name' => 'Loans Receivable',                'type' => 'asset',     'normal_balance' => 'debit',  'description' => 'Outstanding principal owed by borrowers on active/overdue loans.'],
            ['code' => '2000', 'name' => 'Customer Deposits — Savings',     'type' => 'liability', 'normal_balance' => 'credit', 'description' => 'Control account for all savings account balances.'],
            ['code' => '2010', 'name' => 'Customer Deposits — Current',     'type' => 'liability', 'normal_balance' => 'credit', 'description' => 'Control account for all current account balances.'],
            ['code' => '2020', 'name' => 'Customer Deposits — Fixed Deposits', 'type' => 'liability', 'normal_balance' => 'credit', 'description' => 'Control account for all fixed deposit balances.'],
            ['code' => '2100', 'name' => 'Cheques in Clearing',             'type' => 'liability', 'normal_balance' => 'credit', 'description' => 'Cheques lodged for deposit, debited from the drawer, pending credit to the beneficiary.'],
            ['code' => '2200', 'name' => 'Due to/from Head Office',         'type' => 'liability', 'normal_balance' => 'credit', 'description' => 'Counter-account for vault cash brought in from or sent to an external source (e.g. head office, cash-in-transit).'],
            ['code' => '3000', 'name' => 'Opening Balance Equity',          'type' => 'equity',    'normal_balance' => 'credit', 'description' => 'One-time balancing entry used when the ledger was first brought live against pre-existing balances.'],
            ['code' => '4000', 'name' => 'Interest Income — Loans',        'type' => 'income',    'normal_balance' => 'credit', 'description' => 'Interest collected on loan repayments.'],
            ['code' => '4100', 'name' => 'Fee Income',                     'type' => 'income',    'normal_balance' => 'credit', 'description' => 'Reserved for a future fee/charges engine (account maintenance, cheque book issuance, etc.).'],
            ['code' => '4200', 'name' => 'Penalty Income — Loans',         'type' => 'income',    'normal_balance' => 'credit', 'description' => 'Late-payment penalties collected on loan repayments.'],
            ['code' => '5000', 'name' => 'Interest Expense — Deposits',    'type' => 'expense',   'normal_balance' => 'debit',  'description' => 'Reserved for a future deposit interest accrual/posting feature.'],
        ];

        foreach ($accounts as $account) {
            GlAccount::firstOrCreate(
                ['code' => $account['code']],
                $account + ['is_system' => true, 'is_active' => true]
            );
        }
    }
}
