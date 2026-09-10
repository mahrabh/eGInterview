<?php

use App\Models\User;
use App\Services\BillingService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $billing = app(BillingService::class);

        User::query()
            ->with('plan')
            ->whereNotNull('plan_id')
            ->where('role', '!=', 'admin')
            ->whereDoesntHave('billingHistories')
            ->orderBy('id')
            ->each(function (User $user) use ($billing) {
                if (! $user->plan) {
                    return;
                }

                $billing->recordPlanAssignment(
                    $user,
                    $user->plan,
                    'Existing plan assignment',
                    null,
                    'pending',
                );
            });
    }

    public function down(): void
    {
        // Keep historical invoices; do not delete on rollback.
    }
};
