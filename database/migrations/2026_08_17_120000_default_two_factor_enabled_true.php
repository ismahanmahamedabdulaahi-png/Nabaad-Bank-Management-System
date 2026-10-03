<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Schema::change() (native since Laravel 11, no doctrine/dbal needed) compiles to the
        // right ALTER for the active driver, unlike a raw MySQL-only ALTER ... MODIFY.
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('two_factor_enabled')->default(true)->change();
        });
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('two_factor_enabled')->default(true)->change();
        });

        // Two-factor login is now mandatory for everyone — backfill existing rows.
        DB::table('users')->update(['two_factor_enabled' => true]);
        DB::table('customers')->update(['two_factor_enabled' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('two_factor_enabled')->default(false)->change();
        });
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('two_factor_enabled')->default(false)->change();
        });
    }
};
