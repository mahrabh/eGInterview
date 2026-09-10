<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_histories', function (Blueprint $table) {
            if (! Schema::hasColumn('billing_histories', 'voided_at')) {
                $table->timestamp('voided_at')->nullable()->after('paid_at');
            }
        });

        // Existing void rows: start the 30-day clock from when they were last updated.
        DB::table('billing_histories')
            ->where('status', 'void')
            ->whereNull('voided_at')
            ->update([
                'voided_at' => DB::raw('COALESCE(updated_at, created_at, CURRENT_TIMESTAMP)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('billing_histories', function (Blueprint $table) {
            if (Schema::hasColumn('billing_histories', 'voided_at')) {
                $table->dropColumn('voided_at');
            }
        });
    }
};
