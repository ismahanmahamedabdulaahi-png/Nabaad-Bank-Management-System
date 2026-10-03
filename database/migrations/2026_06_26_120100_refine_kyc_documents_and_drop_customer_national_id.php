<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove national_id from customers — captured in kyc_documents.document_number instead
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['national_id']);
            $table->dropColumn('national_id');
        });

        // Add back and selfie image paths to kyc_documents
        Schema::table('kyc_documents', function (Blueprint $table) {
            $table->string('file_path_back')->nullable()->after('file_path');
            $table->string('file_path_selfie')->nullable()->after('file_path_back');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('national_id', 50)->nullable()->unique()->after('email');
        });

        Schema::table('kyc_documents', function (Blueprint $table) {
            $table->dropColumn(['file_path_back', 'file_path_selfie']);
        });
    }
};
