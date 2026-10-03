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
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->string('complaint_number', 20)->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->enum('category', ['transaction_dispute', 'service_quality', 'account_issue', 'fraud_report', 'other']);
            $table->string('subject', 150);
            $table->text('description');
            // No DB-level FK on related_transaction_id — transactions.id is a
            // UUID and this app doesn't constrain other UUID references to it
            // either (see related_account_id/reversal_of on transactions itself).
            $table->uuid('related_transaction_id')->nullable();
            $table->foreignUuid('related_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->enum('status', ['open', 'in_progress', 'resolved', 'closed'])->default('open');
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
