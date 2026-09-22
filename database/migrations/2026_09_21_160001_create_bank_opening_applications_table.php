<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_opening_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bank_opening_applicant_id')
                ->constrained('bank_opening_applicants')
                ->cascadeOnDelete();
            $table->string('account_type')->nullable();
            $table->string('stage')->default('invited')->index();
            $table->unsignedTinyInteger('documents_required')->default(3);
            $table->unsignedTinyInteger('documents_uploaded')->default(0);
            $table->string('public_token_hash')->nullable()->unique();
            $table->timestamp('public_token_expiry')->nullable();
            $table->timestamp('information_submitted_at')->nullable();
            $table->timestamp('interview_completed_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_opening_applications');
    }
};
