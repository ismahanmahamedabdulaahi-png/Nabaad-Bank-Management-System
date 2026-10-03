<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('standing_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('source_account_id');
            $table->uuid('beneficiary_account_id');
            $table->decimal('amount', 15, 2);
            $table->enum('frequency', ['weekly', 'monthly']);
            $table->date('start_date');
            $table->date('next_execution_date');
            $table->date('end_date')->nullable();
            $table->string('description')->nullable();
            $table->enum('status', ['active', 'paused', 'cancelled', 'expired'])->default('active');
            $table->integer('executions_count')->default(0);
            $table->timestamp('last_executed_at')->nullable();
            $table->string('last_execution_status')->nullable();   // success / failed
            $table->foreignId('created_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_customer')->nullable()->constrained('customers')->nullOnDelete();
            $table->timestamps();

            $table->foreign('source_account_id')->references('id')->on('accounts')->restrictOnDelete();
            $table->foreign('beneficiary_account_id')->references('id')->on('accounts')->restrictOnDelete();
            $table->index('next_execution_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('standing_orders');
    }
};
