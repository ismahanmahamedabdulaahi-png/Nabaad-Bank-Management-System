<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // `processed_by` was NOT NULL, but system-initiated postings (e.g. the beneficiary
    // credit leg of ChequeService::processClearing(), run from the unattended EOD job)
    // legitimately have no acting user. Every such credit was silently failing — caught
    // and logged in processClearing()'s per-cheque try/catch, never surfaced — so cheques
    // lodged for clearing never actually reached their beneficiary. Nullable fixes it.
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('processed_by')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('processed_by')->nullable(false)->change();
        });
    }
};
