<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eod_runs', function (Blueprint $table) {
            $table->unsignedInteger('dormant_accounts')->default(0)->after('loans_marked_overdue');
            $table->unsignedInteger('matured_fds')->default(0)->after('dormant_accounts');
        });
    }

    public function down(): void
    {
        Schema::table('eod_runs', function (Blueprint $table) {
            $table->dropColumn(['dormant_accounts', 'matured_fds']);
        });
    }
};
