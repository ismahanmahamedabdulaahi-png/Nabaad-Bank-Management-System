<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Expand the status enum to include paid and deposited
        Schema::table('cheques', function (Blueprint $table) {
            $table->enum('status', [
                'issued', 'pending_clearance', 'cleared', 'used',
                'bounced', 'cancelled', 'expired', 'paid', 'deposited',
            ])->default('issued')->change();
        });

        Schema::table('cheques', function (Blueprint $table) {
            $table->enum('settlement_type', ['cash', 'account_transfer'])->nullable()->after('status');
            $table->uuid('beneficiary_account_id')->nullable()->after('settlement_type');
            $table->uuid('credit_transaction_id')->nullable()->after('transaction_id');

            $table->foreign('beneficiary_account_id')->references('id')->on('accounts')->nullOnDelete();
            $table->foreign('credit_transaction_id')->references('id')->on('transactions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cheques', function (Blueprint $table) {
            $table->dropForeign(['beneficiary_account_id']);
            $table->dropForeign(['credit_transaction_id']);
            $table->dropColumn(['settlement_type', 'beneficiary_account_id', 'credit_transaction_id']);
        });

        Schema::table('cheques', function (Blueprint $table) {
            $table->enum('status', [
                'issued', 'pending_clearance', 'cleared', 'used',
                'bounced', 'cancelled', 'expired',
            ])->default('issued')->change();
        });
    }
};
