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
        Schema::create('loan_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('loan_applicant_id')->constrained('loan_applicants')->onDelete('cascade');
            $table->string('product_type')->nullable();
            $table->decimal('requested_amount', 15, 2)->nullable();
            $table->string('purpose')->nullable();
            $table->string('employment_status')->nullable();
            $table->decimal('monthly_income', 15, 2)->nullable();
            $table->decimal('existing_emi', 15, 2)->nullable();
            $table->integer('tenure_months')->nullable();
            $table->decimal('asset_value', 15, 2)->nullable();
            $table->decimal('down_payment', 15, 2)->nullable();
            $table->string('status')->default('draft');
            $table->string('public_token_hash')->nullable()->unique();
            $table->timestamp('public_token_expiry')->nullable();
            $table->text('transcript')->nullable();
            $table->json('extracted_data')->nullable();
            $table->json('calculation_data')->nullable();
            $table->string('outcome')->nullable();
            $table->json('reason_codes')->nullable();
            $table->foreignId('rule_version_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_applications');
    }
};
