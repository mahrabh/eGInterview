<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_opening_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('bank_opening_applications', 'transcript_text')) {
                $table->longText('transcript_text')->nullable()->after('notes');
            }
            if (! Schema::hasColumn('bank_opening_applications', 'transcript')) {
                $table->longText('transcript')->nullable()->after('transcript_text');
            }
        });

        if (! Schema::hasTable('bank_opening_documents')) {
            Schema::create('bank_opening_documents', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('bank_opening_application_id')
                    ->constrained('bank_opening_applications')
                    ->cascadeOnDelete();
                $table->string('document_type', 64);
                $table->string('original_name');
                $table->string('disk_path');
                $table->string('mime_type', 128)->nullable();
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->timestamps();

                $table->index(['bank_opening_application_id', 'document_type'], 'bao_docs_app_type_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_opening_documents');

        Schema::table('bank_opening_applications', function (Blueprint $table) {
            if (Schema::hasColumn('bank_opening_applications', 'transcript')) {
                $table->dropColumn('transcript');
            }
            if (Schema::hasColumn('bank_opening_applications', 'transcript_text')) {
                $table->dropColumn('transcript_text');
            }
        });
    }
};
