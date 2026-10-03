<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // ── General ──────────────────────────────────────────────────
            ['key' => 'bank_name',       'value' => 'NABAAD Bank',    'group' => 'general', 'label' => 'Bank Name',         'type' => 'string'],
            ['key' => 'bank_branch',     'value' => 'Garowe Branch',  'group' => 'general', 'label' => 'Branch Name',       'type' => 'string'],
            ['key' => 'bank_currency',   'value' => 'USD',            'group' => 'general', 'label' => 'Currency',          'type' => 'string'],
            ['key' => 'bank_timezone',   'value' => 'Africa/Mogadishu','group' => 'general','label' => 'Timezone',          'type' => 'string'],
            ['key' => 'bank_logo_path',  'value' => null,             'group' => 'general', 'label' => 'Logo Path',         'type' => 'string'],

            // ── Account Number Sequence ───────────────────────────────────
            ['key' => 'account_number_sequence', 'value' => '0', 'group' => 'general', 'label' => 'Account Number Sequence', 'type' => 'integer'],

            // ── Staff ID Sequence (1 seeded account: STF-0001) ──
            ['key' => 'staff_id_sequence', 'value' => '1', 'group' => 'system', 'label' => 'Staff ID Sequence', 'type' => 'integer'],

            // ── Session ───────────────────────────────────────────────────
            ['key' => 'session_timeout_minutes', 'value' => '10', 'group' => 'session', 'label' => 'Session Timeout (minutes)', 'type' => 'integer'],

            // ── Loan ──────────────────────────────────────────────────────
            ['key' => 'loan_interest_rate',      'value' => '5.00', 'group' => 'loan', 'label' => 'Loan Interest Rate (%)',     'type' => 'decimal'],
            ['key' => 'loan_penalty_rate',        'value' => '2.00', 'group' => 'loan', 'label' => 'Penalty Rate (% per month)', 'type' => 'decimal'],
            ['key' => 'loan_grace_period_days',   'value' => '5',    'group' => 'loan', 'label' => 'Grace Period (days)',        'type' => 'integer'],
            ['key' => 'loan_min_account_age_months',    'value' => '12',       'group' => 'loan', 'label' => 'Minimum Account Age (months)',           'type' => 'integer'],
            ['key' => 'loan_min_transaction_volume',    'value' => '20000.00', 'group' => 'loan', 'label' => 'Min Previous Year Transaction Volume ($)','type' => 'decimal'],

            // ── Transaction Limits ────────────────────────────────────────
            ['key' => 'txn_limit_teller',              'value' => '5000.00',   'group' => 'transaction', 'label' => 'Teller Transaction Limit ($)',          'type' => 'decimal'],
            ['key' => 'txn_limit_cso',                 'value' => '5000.00',   'group' => 'transaction', 'label' => 'CSO Transaction Limit ($)',             'type' => 'decimal'],
            ['key' => 'txn_approval_level1_max',       'value' => '20000.00',  'group' => 'transaction', 'label' => 'Approval Level 1 Ceiling ($)',          'type' => 'decimal'],
            ['key' => 'txn_approval_level2_max',       'value' => '50000.00',  'group' => 'transaction', 'label' => 'Approval Level 2 Ceiling ($)',          'type' => 'decimal'],
            ['key' => 'txn_no_approval_max',           'value' => '5000.00',   'group' => 'transaction', 'label' => 'No-Approval Ceiling ($)',               'type' => 'decimal'],

            // ── Cheque ────────────────────────────────────────────────────
            ['key' => 'cheque_clearing_days',     'value' => '1',  'group' => 'cheque', 'label' => 'Cheque Clearing Days (T+N)',    'type' => 'integer'],
            ['key' => 'cheque_expiry_months',     'value' => '6',  'group' => 'cheque', 'label' => 'Cheque Validity (months)',      'type' => 'integer'],
            ['key' => 'cheque_book_leaves',       'value' => '25', 'group' => 'cheque', 'label' => 'Default Cheque Book Leaves',    'type' => 'integer'],

            // ── Dormancy ──────────────────────────────────────────────────
            ['key' => 'dormancy_months',          'value' => '6',  'group' => 'general', 'label' => 'Dormancy Period (months)',      'type' => 'integer'],

            // ── Email ─────────────────────────────────────────────────────
            ['key' => 'email_from_name',    'value' => 'NABAAD Bank',         'group' => 'email', 'label' => 'From Name',    'type' => 'string'],
            ['key' => 'email_from_address', 'value' => 'noreply@nabaadbank.so','group' => 'email', 'label' => 'From Address', 'type' => 'string'],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->updateOrInsert(
                ['key' => $setting['key']],
                array_merge($setting, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
