<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cheque_books', function (Blueprint $table) {
            $table->id();
            $table->string('book_number', 20)->unique();   // CB-20260626-001
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->uuid('account_id');
            $table->string('series_start', 15);            // e.g. GAR0000001
            $table->string('series_end', 15);
            $table->integer('total_leaves');
            $table->integer('used_leaves')->default(0);
            $table->integer('remaining_leaves')->virtualAs('total_leaves - used_leaves');
            $table->enum('status', ['active', 'exhausted', 'cancelled'])->default('active');
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('issued_at');
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('account_id')->references('id')->on('accounts')->restrictOnDelete();
            $table->index('customer_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cheque_books');
    }
};
