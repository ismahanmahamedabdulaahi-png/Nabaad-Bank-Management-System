<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teller_tills', function (Blueprint $table) {
            $table->id();
            $table->string('till_name', 50);
            $table->foreignId('teller_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->enum('status', ['open', 'closed', 'suspended'])->default('closed');
            $table->decimal('opening_balance', 15, 2)->default(0.00);
            $table->decimal('current_balance', 15, 2)->default(0.00);
            $table->decimal('closing_balance', 15, 2)->nullable();
            $table->decimal('expected_balance', 15, 2)->nullable();  // for reconciliation
            $table->decimal('variance', 15, 2)->nullable();          // closing vs expected
            $table->date('business_date');
            $table->timestamp('opened_at')->nullable();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['teller_id', 'business_date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teller_tills');
    }
};
