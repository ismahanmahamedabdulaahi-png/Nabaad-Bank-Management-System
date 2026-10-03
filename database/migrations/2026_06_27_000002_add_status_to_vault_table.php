<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vault', function (Blueprint $table) {
            $table->enum('status', ['open', 'closed'])->default('closed')->after('balance');
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete()->after('status');
            $table->timestamp('opened_at')->nullable()->after('opened_by');
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete()->after('opened_at');
            $table->timestamp('closed_at')->nullable()->after('closed_by');
        });
    }

    public function down(): void
    {
        Schema::table('vault', function (Blueprint $table) {
            $table->dropForeign(['opened_by']);
            $table->dropForeign(['closed_by']);
            $table->dropColumn(['status', 'opened_by', 'opened_at', 'closed_by', 'closed_at']);
        });
    }
};
