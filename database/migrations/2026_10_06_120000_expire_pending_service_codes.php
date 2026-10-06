<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Service codes were retired in favour of direct portal withdrawals/deposits,
// and the admin redeem page is gone — so any code still pending can never be
// used. Expire them, and reject their pending withdrawal transactions. Those
// never touched the account balance, so rejecting releases the money with
// nothing to reverse (same as the scheduled service-codes:expire command).
return new class extends Migration
{
    public function up(): void
    {
        $pending = DB::table('service_request_codes')->where('status', 'pending');

        $txnIds = (clone $pending)->where('type', 'withdrawal')->whereNotNull('transaction_id')->pluck('transaction_id');

        DB::table('transactions')->whereIn('id', $txnIds)->where('status', 'pending')
            ->update(['status' => 'rejected', 'updated_at' => now()]);

        $pending->update(['status' => 'expired', 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Irreversible data cleanup.
    }
};
