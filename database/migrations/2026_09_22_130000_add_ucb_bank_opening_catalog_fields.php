<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bank_opening_applications')) {
            return;
        }

        Schema::table('bank_opening_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('bank_opening_applications', 'bank_code')) {
                $table->string('bank_code', 16)->nullable()->after('account_type');
            }
            if (! Schema::hasColumn('bank_opening_applications', 'catalog_version')) {
                $table->string('catalog_version', 64)->nullable()->after('bank_code');
            }
            if (! Schema::hasColumn('bank_opening_applications', 'account_type_name_snapshot')) {
                $table->string('account_type_name_snapshot')->nullable()->after('catalog_version');
            }
            if (! Schema::hasColumn('bank_opening_applications', 'confirmed_summary')) {
                $table->json('confirmed_summary')->nullable()->after('structured_answers');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('bank_opening_applications')) {
            return;
        }

        Schema::table('bank_opening_applications', function (Blueprint $table) {
            foreach (['bank_code', 'catalog_version', 'account_type_name_snapshot', 'confirmed_summary'] as $col) {
                if (Schema::hasColumn('bank_opening_applications', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
