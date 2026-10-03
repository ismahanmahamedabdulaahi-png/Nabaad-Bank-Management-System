<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vault', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->decimal('balance', 15, 2)->default(0.00);
            $table->string('currency', 3)->default('USD');
            $table->foreignId('last_updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('vault_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vault_id')->constrained('vault')->restrictOnDelete();
            $table->enum('type', ['cash_in', 'cash_out', 'transfer_to_teller', 'returned_from_teller']);
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_before', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->foreignId('teller_till_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference', 30)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('processed_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vault_transactions');
        Schema::dropIfExists('vault');
    }
};
