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
        Schema::create('service_request_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['withdrawal', 'deposit']);
            $table->decimal('amount', 15, 2);
            $table->string('code', 6)->unique();
            $table->enum('status', ['pending', 'redeemed', 'expired', 'cancelled'])->default('pending');
            // Withdrawal: set immediately (a pending Transaction is created up
            // front, since the debit is real and awaiting counter pickup).
            // Deposit: null until redeemed — no money exists in the system to
            // record until the teller actually receives the cash.
            // No DB-level FK here, matching how `transactions` itself
            // references other UUIDs (related_account_id, reversal_of) —
            // application-referenced only, not a hard constraint.
            $table->uuid('transaction_id')->nullable();
            $table->timestamp('expires_at');
            $table->foreignId('redeemed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_request_codes');
    }
};
