<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('entry_number', 30)->unique();  // JE-20260910-00001
            $table->date('entry_date');
            $table->string('description');
            $table->string('source_type', 50)->nullable();   // transaction, loan_payment, cheque, vault_transaction, opening_balance, manual...
            $table->string('source_id', 40)->nullable();     // polymorphic id (uuid or int as string)
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_reversal')->default(false);
            $table->uuid('reversed_journal_entry_id')->nullable();
            $table->timestamps();

            $table->foreign('reversed_journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();

            $table->index('entry_date');
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
