<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Nullable and additive — every existing (teller-initiated) row
            // stays null. Lets AML/reporting tell self-service activity apart
            // from teller-initiated activity without touching TransactionService.
            $table->foreignId('initiated_by_customer_id')->nullable()->after('processed_by')
                ->constrained('customers')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['initiated_by_customer_id']);
            $table->dropColumn('initiated_by_customer_id');
        });
    }
};
