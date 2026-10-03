<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eod_runs', function (Blueprint $table) {
            $table->enum('status', ['running', 'completed', 'failed', 'superseded'])->default('running')->change();
        });
    }

    public function down(): void
    {
        DB::statement("UPDATE eod_runs SET status = 'completed' WHERE status = 'superseded'");

        Schema::table('eod_runs', function (Blueprint $table) {
            $table->enum('status', ['running', 'completed', 'failed'])->default('running')->change();
        });
    }
};
