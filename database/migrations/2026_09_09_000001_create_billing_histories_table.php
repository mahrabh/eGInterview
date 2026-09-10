<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_histories', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->string('plan_name')->nullable();
            $table->string('plan_module')->nullable();
            $table->unsignedInteger('recruitment_limit')->nullable();
            $table->unsignedInteger('loan_limit')->nullable();
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('currency', 8)->default('USD');
            $table->string('gateway')->nullable();
            $table->string('description');
            $table->string('status')->default('pending'); // pending|paid|failed|void
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_histories');
    }
};
