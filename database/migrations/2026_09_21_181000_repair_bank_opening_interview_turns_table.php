<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Repair: create interview turns + columns if a prior migrate was recorded
 * without the table existing on this Postgres database.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bank_opening_applications', 'question_profile')) {
            Schema::table('bank_opening_applications', function (Blueprint $table) {
                $table->string('question_profile')->nullable()->after('account_type');
            });
        }

        if (! Schema::hasColumn('bank_opening_applications', 'interview_follow_ups_used')) {
            Schema::table('bank_opening_applications', function (Blueprint $table) {
                $table->unsignedTinyInteger('interview_follow_ups_used')->default(0);
            });
        }

        if (! Schema::hasColumn('bank_opening_applications', 'interview_question_index')) {
            Schema::table('bank_opening_applications', function (Blueprint $table) {
                $table->unsignedTinyInteger('interview_question_index')->default(0);
            });
        }

        if (! Schema::hasColumn('bank_opening_applications', 'account_type_confirmed')) {
            Schema::table('bank_opening_applications', function (Blueprint $table) {
                $table->boolean('account_type_confirmed')->default(false);
            });
        }

        if (! Schema::hasTable('bank_opening_interview_turns')) {
            Schema::create('bank_opening_interview_turns', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('bank_opening_application_id')
                    ->constrained('bank_opening_applications')
                    ->cascadeOnDelete();
                $table->string('speaker', 16);
                $table->string('question_key')->nullable()->index();
                $table->text('question_text')->nullable();
                $table->text('answer_text')->nullable();
                $table->unsignedInteger('sequence');
                $table->timestamp('spoken_at')->useCurrent();
                $table->timestamps();

                $table->unique(
                    ['bank_opening_application_id', 'sequence'],
                    'bao_interview_turns_app_sequence_unique'
                );
            });
        }
    }

    public function down(): void
    {
        // Non-destructive repair — do not drop data on rollback.
    }
};
