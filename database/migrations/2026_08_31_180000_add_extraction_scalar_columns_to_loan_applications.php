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
            if (!Schema::hasColumn('loan_applications', 'income_source')) {
                $table->string('income_source')->nullable()->after('employment_status');
            }
            if (!Schema::hasColumn('loan_applications', 'employer_name')) {
                $table->string('employer_name')->nullable()->after('income_source');
            }
            if (!Schema::hasColumn('loan_applications', 'other_monthly_income')) {
                $table->decimal('other_monthly_income', 15, 2)->nullable()->after('monthly_income');
            }
            // Alias used by some clients; canonical persisted column remains `transcript`.
            if (!Schema::hasColumn('loan_applications', 'transcript_text')) {
                $table->text('transcript_text')->nullable()->after('transcript');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            $columns = ['income_source', 'employer_name', 'other_monthly_income', 'transcript_text'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('loan_applications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
