<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Add customer_deposit / customer_withdrawal to till movement types ─
        Schema::table('till_cash_movements', function (Blueprint $table) {
            $table->enum('type', [
                'replenishment', 'return', 'transfer_in', 'transfer_out',
                'customer_deposit', 'customer_withdrawal',
            ])->change();
        });

        // ── 2. Migrate existing bad cheque statuses before altering enum ─────────
        DB::statement("UPDATE cheques SET status = 'used'             WHERE status = 'paid'");
        DB::statement("UPDATE cheques SET status = 'pending_clearance' WHERE status = 'deposited'");

        // ── 3. Remove 'paid' and 'deposited' from cheques status enum ─────────────
        Schema::table('cheques', function (Blueprint $table) {
            $table->enum('status', [
                'issued', 'pending_clearance', 'cleared', 'used',
                'bounced', 'cancelled', 'expired',
            ])->default('issued')->change();
        });
    }

    public function down(): void
    {
        Schema::table('till_cash_movements', function (Blueprint $table) {
            $table->enum('type', ['replenishment', 'return', 'transfer_in', 'transfer_out'])->change();
        });

        Schema::table('cheques', function (Blueprint $table) {
            $table->enum('status', [
                'issued', 'pending_clearance', 'cleared', 'used',
                'bounced', 'cancelled', 'expired', 'paid', 'deposited',
            ])->default('issued')->change();
        });
    }
};
