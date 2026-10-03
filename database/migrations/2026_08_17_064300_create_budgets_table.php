<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->uuid('account_id')->nullable();  // null = applies to all of the customer's accounts
            $table->string('name');
            $table->decimal('limit_amount', 15, 2);
            $table->enum('period', ['monthly'])->default('monthly');
            $table->unsignedTinyInteger('threshold_percent')->default(80);
            $table->unsignedTinyInteger('last_alert_percent')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
