<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_opening_application_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bank_opening_application_id')
                ->constrained('bank_opening_applications')
                ->cascadeOnDelete();
            $table->string('event_type');
            $table->json('payload')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_opening_application_events');
    }
};
