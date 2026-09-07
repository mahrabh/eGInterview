<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            if (!Schema::hasColumn('plans', 'module_type')) {
                $table->string('module_type', 32)->default('recruitment')->after('slug');
            }
            if (!Schema::hasColumn('plans', 'recruitment_interview_limit')) {
                $table->unsignedInteger('recruitment_interview_limit')->default(0)->after('interview_limit');
            }
            if (!Schema::hasColumn('plans', 'loan_interview_limit')) {
                $table->unsignedInteger('loan_interview_limit')->default(0)->after('recruitment_interview_limit');
            }
        });

        // Preserve existing interview_limit values as recruitment monthly quotas.
        if (Schema::hasColumn('plans', 'interview_limit') && Schema::hasColumn('plans', 'recruitment_interview_limit')) {
            foreach (DB::table('plans')->get() as $plan) {
                DB::table('plans')->where('id', $plan->id)->update([
                    'module_type' => $plan->module_type ?: 'recruitment',
                    'recruitment_interview_limit' => (int) (($plan->recruitment_interview_limit ?? 0) ?: $plan->interview_limit),
                    'loan_interview_limit' => (int) ($plan->loan_interview_limit ?? 0),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            if (Schema::hasColumn('plans', 'loan_interview_limit')) {
                $table->dropColumn('loan_interview_limit');
            }
            if (Schema::hasColumn('plans', 'recruitment_interview_limit')) {
                $table->dropColumn('recruitment_interview_limit');
            }
            if (Schema::hasColumn('plans', 'module_type')) {
                $table->dropColumn('module_type');
            }
        });
    }
};
