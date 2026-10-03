<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_repayment_schedules', function (Blueprint $table) {
            $table->id();
            $table->uuid('loan_id');
            $table->integer('installment_number');
            $table->date('due_date');
            $table->date('grace_deadline');                    // due_date + grace_period_days
            $table->decimal('principal_amount', 15, 2);
            $table->decimal('interest_amount', 15, 2);
            $table->decimal('emi_amount', 15, 2);              // principal + interest
            $table->decimal('penalty_amount', 15, 2)->default(0.00);
            $table->decimal('total_due', 15, 2);               // emi + penalty
            $table->decimal('amount_paid', 15, 2)->default(0.00);
            $table->decimal('outstanding_balance_after', 15, 2);  // remaining principal after this payment
            $table->enum('status', ['pending', 'paid', 'partially_paid', 'overdue'])->default('pending');
            $table->date('paid_date')->nullable();
            $table->timestamps();

            $table->foreign('loan_id')->references('id')->on('loans')->cascadeOnDelete();
            $table->unique(['loan_id', 'installment_number']);
            $table->index(['loan_id', 'status']);
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_repayment_schedules');
    }
};
