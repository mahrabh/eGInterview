<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interviews', function (Blueprint $table) {
            if (!Schema::hasColumn('interviews', 'candidate_photo')) {
                $table->longText('candidate_photo')->nullable()->after('evaluation_json');
            }
            if (!Schema::hasColumn('interviews', 'link_expires_at')) {
                $table->timestamp('link_expires_at')->nullable()->after('candidate_photo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('interviews', function (Blueprint $table) {
            $table->dropColumn(['candidate_photo', 'link_expires_at']);
        });
    }
};
