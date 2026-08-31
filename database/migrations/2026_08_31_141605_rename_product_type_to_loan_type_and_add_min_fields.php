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
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->renameColumn('product_type', 'loan_type');
        });

        Schema::table('loan_product_rules', function (Blueprint $table) {
            $table->renameColumn('product_type', 'loan_type');
            $table->decimal('min_loan_amount', 15, 2)->nullable()->after('max_dbr_percentage');
            $table->integer('min_tenure_months')->nullable()->after('max_loan_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_product_rules', function (Blueprint $table) {
            $table->dropColumn(['min_loan_amount', 'min_tenure_months']);
            $table->renameColumn('loan_type', 'product_type');
        });

        Schema::table('loan_applications', function (Blueprint $table) {
            $table->renameColumn('loan_type', 'product_type');
        });
    }
};
