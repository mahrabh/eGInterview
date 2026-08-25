<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

public function up(): void
    {
        Schema::create('interviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('candidate_name');
            $table->string('candidate_email')->nullable();
            $table->string('applied_role');
            $table->text('job_description')->nullable();
            $table->json('approved_questions')->nullable();
            $table->enum('status', ['draft', 'pending', 'approved', 'completed'])->default('draft');
            $table->string('public_url')->nullable();
            $table->longText('transcript_text')->nullable();
            $table->json('evaluation_json')->nullable(); // <-- YOU MUST ADD THIS
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }
    
    public function down(): void {
        Schema::dropIfExists('interviews');
    }
};