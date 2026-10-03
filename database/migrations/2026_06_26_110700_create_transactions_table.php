<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference', 30)->unique();  // TXN-20260626-00001
            $table->uuid('account_id');
            $table->uuid('related_account_id')->nullable();  // destination for transfers
            $table->enum('type', ['deposit', 'withdrawal', 'transfer', 'reversal', 'loan_disbursement', 'loan_repayment']);
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_before', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->enum('status', ['pending', 'approved', 'rejected', 'completed', 'cancelled'])->default('pending');
            $table->string('description')->nullable();
            $table->text('notes')->nullable();
            $table->string('currency', 3)->default('USD');

            // Approval workflow
            $table->boolean('requires_approval')->default(false);
            $table->integer('approval_level_required')->default(0);  // 0=none, 1=manager, 2=+compliance, 3=+superadmin
            $table->integer('approval_level_reached')->default(0);

            // Teller link
            $table->foreignId('teller_till_id')->nullable()->constrained()->nullOnDelete();

            // Who processed it
            $table->foreignId('processed_by')->constrained('users')->restrictOnDelete();

            // For reversals
            $table->uuid('reversal_of')->nullable();
            $table->string('reversal_reason')->nullable();

            // Receipt
            $table->string('receipt_path')->nullable();

            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // FK for related accounts and reversal reference (added after table creation)
            $table->foreign('account_id')->references('id')->on('accounts')->restrictOnDelete();
            $table->foreign('related_account_id')->references('id')->on('accounts')->nullOnDelete();
            $table->foreign('reversal_of')->references('id')->on('transactions')->nullOnDelete();

            $table->index('account_id');
            $table->index('related_account_id');
            $table->index('status');
            $table->index('type');
            $table->index('created_at');
            $table->index('requires_approval');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
