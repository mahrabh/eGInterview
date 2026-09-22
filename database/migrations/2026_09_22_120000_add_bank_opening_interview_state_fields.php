<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bank_opening_applications')) {
            Schema::table('bank_opening_applications', function (Blueprint $table) {
                if (! Schema::hasColumn('bank_opening_applications', 'interview_language')) {
                    $table->string('interview_language', 8)->nullable()->after('account_type_confirmed');
                }
                if (! Schema::hasColumn('bank_opening_applications', 'interview_session_id')) {
                    $table->uuid('interview_session_id')->nullable()->after('interview_language');
                }
                if (! Schema::hasColumn('bank_opening_applications', 'summary_confirmed_at')) {
                    $table->timestamp('summary_confirmed_at')->nullable()->after('interview_completed_at');
                }
                if (! Schema::hasColumn('bank_opening_applications', 'structured_answers')) {
                    $table->json('structured_answers')->nullable()->after('meta');
                }
            });
        }

        if (Schema::hasTable('bank_opening_interview_turns')) {
            Schema::table('bank_opening_interview_turns', function (Blueprint $table) {
                if (! Schema::hasColumn('bank_opening_interview_turns', 'answer_raw')) {
                    $table->text('answer_raw')->nullable()->after('answer_text');
                }
                if (! Schema::hasColumn('bank_opening_interview_turns', 'answer_normalized')) {
                    $table->json('answer_normalized')->nullable()->after('answer_raw');
                }
                if (! Schema::hasColumn('bank_opening_interview_turns', 'answer_status')) {
                    $table->string('answer_status', 32)->nullable()->after('answer_normalized');
                }
                if (! Schema::hasColumn('bank_opening_interview_turns', 'client_turn_id')) {
                    $table->string('client_turn_id', 64)->nullable()->after('answer_status');
                }
            });

            Schema::table('bank_opening_interview_turns', function (Blueprint $table) {
                try {
                    $table->unique(
                        ['bank_opening_application_id', 'client_turn_id'],
                        'bao_turns_app_client_turn_unique'
                    );
                } catch (\Throwable) {
                    // Index may already exist on re-run.
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bank_opening_interview_turns')) {
            Schema::table('bank_opening_interview_turns', function (Blueprint $table) {
                try {
                    $table->dropUnique('bao_turns_app_client_turn_unique');
                } catch (\Throwable) {
                }
                foreach (['answer_raw', 'answer_normalized', 'answer_status', 'client_turn_id'] as $col) {
                    if (Schema::hasColumn('bank_opening_interview_turns', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('bank_opening_applications')) {
            Schema::table('bank_opening_applications', function (Blueprint $table) {
                foreach (['interview_language', 'interview_session_id', 'summary_confirmed_at', 'structured_answers'] as $col) {
                    if (Schema::hasColumn('bank_opening_applications', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
