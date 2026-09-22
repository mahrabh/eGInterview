<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_opening_applications', function (Blueprint $table) {
            $table->index('created_at', 'bank_opening_applications_created_at_index');
            $table->index(
                ['stage', 'created_at'],
                'bank_opening_applications_stage_created_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('bank_opening_applications', function (Blueprint $table) {
            $table->dropIndex('bank_opening_applications_created_at_index');
            $table->dropIndex('bank_opening_applications_stage_created_index');
        });
    }
};
