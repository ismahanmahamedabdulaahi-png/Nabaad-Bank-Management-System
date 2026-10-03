<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eod_runs', function (Blueprint $table) {
            $table->id();
            $table->date('run_date');
            $table->enum('status', ['running', 'completed', 'failed'])->default('running');
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();

            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_manual')->default(false);

            // Per-process counts
            $table->unsignedInteger('standing_orders_success')->default(0);
            $table->unsignedInteger('standing_orders_failed')->default(0);
            $table->unsignedInteger('standing_orders_skipped')->default(0);
            $table->unsignedInteger('cheques_cleared')->default(0);
            $table->unsignedInteger('loans_marked_overdue')->default(0);

            $table->json('errors')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('run_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eod_runs');
    }
};
