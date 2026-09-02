<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_applicants', function (Blueprint $table) {
            $table->unique(['phone_hash', 'created_by'], 'loan_applicants_phone_hash_creator_unique');
        });
    }

    public function down(): void
    {
        Schema::table('loan_applicants', function (Blueprint $table) {
            $table->dropUnique('loan_applicants_phone_hash_creator_unique');
        });
    }
};
