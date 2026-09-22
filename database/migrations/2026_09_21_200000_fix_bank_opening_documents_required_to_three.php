<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bank_opening_applications')) {
            return;
        }

        DB::table('bank_opening_applications')
            ->where('documents_required', '>', 3)
            ->update(['documents_required' => 3]);
    }

    public function down(): void
    {
        // Intentionally left blank — previous value of 4 was incorrect.
    }
};
