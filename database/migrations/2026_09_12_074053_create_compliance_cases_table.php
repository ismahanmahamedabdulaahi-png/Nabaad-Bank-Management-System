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
        Schema::create('compliance_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            // No DB-level FK — transactions.id is a UUID and this app doesn't
            // constrain other UUID references to it (see transactions itself).
            $table->uuid('transaction_id')->nullable();
            $table->enum('type', ['large_transaction', 'structuring', 'manual_flag']);
            $table->enum('severity', ['low', 'medium', 'high'])->default('medium');
            $table->enum('status', ['open', 'reviewing', 'cleared', 'reported'])->default('open');
            $table->text('notes')->nullable();
            // Null means system-generated (e.g. automated structuring detection).
            $table->foreignId('flagged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compliance_cases');
    }
};
