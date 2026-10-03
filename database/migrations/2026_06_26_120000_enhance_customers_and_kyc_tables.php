<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Add new columns to customers ──────────────────────────────────────
        Schema::table('customers', function (Blueprint $table) {
            $table->string('national_id', 50)->nullable()->unique()->after('email');
            $table->enum('gender', ['male', 'female', 'other'])->nullable()->after('date_of_birth');
            $table->string('nationality', 100)->nullable()->after('gender');
            $table->enum('marital_status', ['single', 'married', 'divorced', 'widowed'])->nullable()->after('nationality');
            $table->string('city', 100)->nullable()->after('address');
            $table->string('next_of_kin_name')->nullable()->after('city');
            $table->string('next_of_kin_phone', 20)->nullable()->after('next_of_kin_name');
            $table->string('next_of_kin_relationship', 100)->nullable()->after('next_of_kin_phone');
        });

        // ── Add 'pending' to customers.status enum and change default ─────────
        // Schema::change() (native since Laravel 11, no doctrine/dbal needed) compiles to the
        // right ALTER for the active driver, unlike a raw MySQL-only ALTER ... MODIFY COLUMN.
        Schema::table('customers', function (Blueprint $table) {
            $table->enum('status', ['pending', 'active', 'inactive', 'blacklisted', 'deceased'])
                ->default('pending')->change();
        });

        // ── Add 'state_id' to kyc_documents.document_type enum ───────────────
        Schema::table('kyc_documents', function (Blueprint $table) {
            $table->enum('document_type', ['national_id', 'passport', 'driving_license', 'state_id'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'national_id', 'gender', 'nationality', 'marital_status',
                'city', 'next_of_kin_name', 'next_of_kin_phone', 'next_of_kin_relationship',
            ]);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->enum('status', ['active', 'inactive', 'blacklisted', 'deceased'])
                ->default('active')->change();
        });

        Schema::table('kyc_documents', function (Blueprint $table) {
            $table->enum('document_type', ['national_id', 'passport', 'driving_license'])->change();
        });
    }
};
