<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_opening_applications', function (Blueprint $table) {
            $table->string('question_profile')->nullable()->after('account_type');
            $table->unsignedTinyInteger('interview_follow_ups_used')->default(0)->after('question_profile');
            $table->unsignedTinyInteger('interview_question_index')->default(0)->after('interview_follow_ups_used');
            $table->boolean('account_type_confirmed')->default(false)->after('interview_question_index');
        });

        Schema::create('bank_opening_interview_turns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bank_opening_application_id')
                ->constrained('bank_opening_applications')
                ->cascadeOnDelete();
            $table->string('speaker', 16); // ai | applicant
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

    public function down(): void
    {
        Schema::dropIfExists('bank_opening_interview_turns');

        Schema::table('bank_opening_applications', function (Blueprint $table) {
            $table->dropColumn([
                'question_profile',
                'interview_follow_ups_used',
                'interview_question_index',
                'account_type_confirmed',
            ]);
        });
    }
};
