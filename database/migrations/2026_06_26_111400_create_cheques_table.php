<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cheques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cheque_book_id')->constrained()->restrictOnDelete();
            $table->uuid('account_id');
            $table->string('cheque_number', 15)->unique();
            $table->string('payee_name')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->enum('status', [
                'issued', 'pending_clearance', 'cleared', 'used', 'bounced', 'cancelled', 'expired'
            ])->default('issued');
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();                // cheques expire after 6 months typically
            $table->timestamp('deposited_at')->nullable();
            $table->date('clearing_date')->nullable();              // T+1 clearance date
            $table->timestamp('cleared_at')->nullable();
            $table->string('bounce_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('transaction_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('account_id')->references('id')->on('accounts')->restrictOnDelete();
            $table->foreign('transaction_id')->references('id')->on('transactions')->nullOnDelete();
            $table->index('status');
            $table->index('clearing_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cheques');
    }
};
