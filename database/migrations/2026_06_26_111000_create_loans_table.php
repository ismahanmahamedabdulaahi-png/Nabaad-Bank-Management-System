<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('loan_number', 25)->unique();   // LN-20260626-00001
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->uuid('account_id');
            $table->decimal('amount', 15, 2);              // principal
            $table->decimal('interest_rate', 5, 2);        // 5.00 %
            $table->integer('tenure_months');
            $table->decimal('monthly_emi', 15, 2);
            $table->decimal('total_payable', 15, 2);       // principal + total interest
            $table->decimal('total_interest', 15, 2);
            $table->decimal('outstanding_balance', 15, 2);
            $table->decimal('total_paid', 15, 2)->default(0.00);
            $table->string('purpose')->nullable();
            $table->text('collateral')->nullable();
            $table->enum('status', [
                'pending', 'under_review', 'approved', 'rejected', 'disbursed', 'active', 'overdue', 'closed'
            ])->default('pending');

            // Workflow actors
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('disbursed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disbursed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('rejection_reason')->nullable();

            // Disbursement transaction link
            $table->uuid('disbursement_transaction_id')->nullable();

            // Eligibility snapshot at application time
            $table->integer('account_age_months')->nullable();
            $table->decimal('prev_year_transaction_volume', 15, 2)->nullable();

            // Grace period (days) from settings at time of loan creation
            $table->integer('grace_period_days')->default(5);
            $table->decimal('penalty_rate', 5, 2)->default(2.00);  // per month

            $table->date('first_repayment_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')->references('id')->on('accounts')->restrictOnDelete();
            $table->foreign('disbursement_transaction_id')->references('id')->on('transactions')->nullOnDelete();

            $table->index('customer_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
