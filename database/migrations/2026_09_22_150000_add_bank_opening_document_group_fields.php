<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_opening_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('bank_opening_applications', 'documents_submitted_at')) {
                $table->timestamp('documents_submitted_at')->nullable()->after('submitted_at');
            }
        });

        Schema::table('bank_opening_documents', function (Blueprint $table) {
            if (! Schema::hasColumn('bank_opening_documents', 'removed_at')) {
                $table->timestamp('removed_at')->nullable()->after('size_bytes');
            }
            if (! Schema::hasColumn('bank_opening_documents', 'replaced_by_id')) {
                $table->uuid('replaced_by_id')->nullable()->after('removed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bank_opening_applications', function (Blueprint $table) {
            if (Schema::hasColumn('bank_opening_applications', 'documents_submitted_at')) {
                $table->dropColumn('documents_submitted_at');
            }
        });

        Schema::table('bank_opening_documents', function (Blueprint $table) {
            if (Schema::hasColumn('bank_opening_documents', 'replaced_by_id')) {
                $table->dropColumn('replaced_by_id');
            }
            if (Schema::hasColumn('bank_opening_documents', 'removed_at')) {
                $table->dropColumn('removed_at');
            }
        });
    }
};
