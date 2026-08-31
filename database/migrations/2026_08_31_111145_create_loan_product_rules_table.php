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
        Schema::create('loan_product_rules', function (Blueprint $table) {
            $table->id();
            $table->string('product_type');
            $table->decimal('interest_rate', 5, 2);
            $table->string('interest_method')->default('flat');
            $table->decimal('max_dbr_percentage', 5, 2);
            $table->decimal('min_income', 15, 2)->nullable();
            $table->decimal('max_loan_amount', 15, 2)->nullable();
            $table->integer('max_tenure_months')->nullable();
            $table->decimal('max_ltv_percentage', 5, 2)->nullable();
            $table->integer('version')->default(1);
            $table->date('effective_date');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_product_rules');
    }
};
