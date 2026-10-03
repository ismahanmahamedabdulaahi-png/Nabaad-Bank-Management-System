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
        Schema::table('accounts', function (Blueprint $table) {
            $table->decimal('interest_rate', 5, 2)->default(0.00)->after('balance');
            $table->decimal('minimum_balance', 15, 2)->default(0.00)->after('interest_rate');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['interest_rate', 'minimum_balance']);
        });
    }
};
