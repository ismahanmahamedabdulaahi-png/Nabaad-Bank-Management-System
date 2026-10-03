<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->decimal('low_balance_alert_threshold', 15, 2)->nullable()->after('minimum_balance');
            $table->boolean('low_balance_alerted')->default(false)->after('low_balance_alert_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['low_balance_alert_threshold', 'low_balance_alerted']);
        });
    }
};
