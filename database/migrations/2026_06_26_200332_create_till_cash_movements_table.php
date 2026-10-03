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
        Schema::create('till_cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('till_id')->constrained('teller_tills')->cascadeOnDelete();
            $table->foreignId('related_till_id')->nullable()->constrained('teller_tills')->nullOnDelete();
            $table->enum('type', ['replenishment', 'return', 'transfer_in', 'transfer_out']);
            $table->decimal('amount', 15, 2);
            $table->text('notes')->nullable();
            $table->foreignId('processed_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('till_id');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('till_cash_movements');
    }
};
