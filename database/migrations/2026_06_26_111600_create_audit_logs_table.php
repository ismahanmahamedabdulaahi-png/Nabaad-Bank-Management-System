<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_type')->nullable();   // App\Models\User or App\Models\Customer
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name')->nullable();   // snapshot at time of action
            $table->string('action', 50);              // created, updated, deleted, login, logout, approved, etc.
            $table->string('module', 50);              // customers, accounts, transactions, loans, etc.
            $table->string('description');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('url')->nullable();
            $table->timestamps();

            $table->index(['user_type', 'user_id']);
            $table->index('action');
            $table->index('module');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
