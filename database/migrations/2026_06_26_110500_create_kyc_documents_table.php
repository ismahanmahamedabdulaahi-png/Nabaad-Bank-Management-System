<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kyc_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kyc_verification_id')->constrained()->cascadeOnDelete();
            $table->enum('document_type', ['national_id', 'passport', 'driving_license']);
            $table->string('document_number')->nullable();
            $table->string('file_path');
            $table->date('expiry_date')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_documents');
    }
};
