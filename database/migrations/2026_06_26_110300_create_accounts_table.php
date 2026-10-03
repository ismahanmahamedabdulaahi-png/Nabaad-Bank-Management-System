<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('account_number', 20)->unique();   // GAR102000001
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->enum('account_type', ['savings', 'current', 'fixed_deposit']);
            $table->enum('status', ['active', 'frozen', 'closed', 'dormant', 'matured'])->default('active');
            $table->decimal('balance', 15, 2)->default(0.00);
            $table->string('currency', 3)->default('USD');
            $table->date('opening_date');
            $table->date('last_activity_date')->nullable();
            $table->date('dormant_since')->nullable();

            // Fixed Deposit fields
            $table->integer('fd_tenure_months')->nullable();
            $table->date('fd_maturity_date')->nullable();
            $table->enum('fd_maturity_action', ['renew', 'transfer_to_savings', 'pending'])->nullable();

            // Audit trail
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('frozen_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('frozen_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index('customer_id');
            $table->index('status');
            $table->index('account_type');
            $table->index('fd_maturity_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
