<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('end_of_day_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->date('business_date')->unique();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->decimal('vault_opening_balance', 15, 2)->nullable();
            $table->decimal('vault_closing_balance', 15, 2)->nullable();
            $table->integer('total_transactions')->default(0);
            $table->decimal('total_deposits', 15, 2)->default(0.00);
            $table->decimal('total_withdrawals', 15, 2)->default(0.00);
            $table->decimal('total_transfers', 15, 2)->default(0.00);
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('business_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('end_of_day_records');
    }
};
