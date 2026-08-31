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
        Schema::create('loan_applicants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('application_reference')->unique()->nullable();
            $table->string('name');
            $table->text('phone');
            $table->string('phone_hash')->index();
            $table->text('nid')->nullable();
            $table->string('nid_hash')->nullable()->index();
            $table->string('nid_last_four', 4)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_applicants');
    }
};
