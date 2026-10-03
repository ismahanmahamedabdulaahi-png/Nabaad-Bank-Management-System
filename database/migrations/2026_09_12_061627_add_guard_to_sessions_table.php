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
        Schema::table('sessions', function (Blueprint $table) {
            // Laravel's DatabaseSessionHandler stamps user_id from the DEFAULT
            // guard ('web'/staff) only — a customer-guard session leaves it
            // NULL. `guard` lets us reliably scope "my sessions" per portal.
            $table->string('guard', 20)->nullable()->after('user_id');
            $table->index(['user_id', 'guard']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'guard']);
            $table->dropColumn('guard');
        });
    }
};
