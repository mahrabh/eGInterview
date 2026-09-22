<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_opening_applicants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('application_reference')->unique();
            $table->string('name')->nullable();
            $table->text('phone')->nullable();
            $table->string('phone_hash')->nullable()->index();
            $table->string('phone_masked')->nullable();
            $table->text('nid')->nullable();
            $table->string('nid_hash')->nullable()->index();
            $table->string('nid_last_four', 4)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->unique(['phone_hash', 'created_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_opening_applicants');
    }
};
