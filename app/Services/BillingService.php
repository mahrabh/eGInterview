<?php

namespace App\Services;

use App\Models\BillingHistory;
use App\Models\Plan;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class BillingService
{
    public const VOID_RETENTION_DAYS = 30;

    public function recordPlanAssignment(
        User $user,
        ?Plan $plan,
        string $description,
        ?User $actor = null,
        string $status = 'pending',
    ): ?BillingHistory {
        if ($plan === null) {
            return null;
        }

        $periodStart = now()->startOfDay();
        $periodEnd = $user->expires_at
            ? $user->expires_at->copy()->startOfDay()
            : now()->addMonth()->startOfDay();

        return $this->createInvoice([
            'user' => $user,
            'plan' => $plan,
            'amount' => (float) $plan->price,
            'description' => $description,
            'status' => $status,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'gateway' => 'Bank Transfer',
            'actor' => $actor,
        ]);
    }

    /**
     * @param  array{
     *   user: User,
     *   plan?: Plan|null,
     *   amount: float|int|string,
     *   description: string,
     *   status?: string,
     *   gateway?: string|null,
     *   notes?: string|null,
     *   period_start?: CarbonInterface|string|null,
     *   period_end?: CarbonInterface|string|null,
     *   actor?: User|null
     * }  $data
     */
    public function createInvoice(array $data): BillingHistory
    {
        $user = $data['user'];
        $plan = $data['plan'] ?? $user->plan;
        $status = $data['status'] ?? 'pending';
        $actor = $data['actor'] ?? auth()->user();

        return DB::transaction(function () use ($data, $user, $plan, $status, $actor) {
            $invoice = BillingHistory::query()->create([
                'invoice_number' => $this->nextInvoiceNumber(),
                'user_id' => $user->id,
                'plan_id' => $plan?->id,
                'plan_name' => $plan?->name,
                'plan_module' => $plan?->moduleLabel(),
                'recruitment_limit' => $plan?->coversRecruitment() ? $plan->effectiveRecruitmentLimit() : null,
                'loan_limit' => $plan?->coversLoans() ? $plan->effectiveLoanLimit() : null,
                'amount' => round((float) $data['amount'], 2),
                'currency' => 'USD',
                'gateway' => $data['gateway'] ?? null,
                'description' => $data['description'],
                'status' => $status,
                'period_start' => $data['period_start'] ?? null,
                'period_end' => $data['period_end'] ?? null,
                'paid_at' => $status === 'paid' ? now() : null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor?->id,
            ]);

            return $invoice;
        });
    }

    public function nextInvoiceNumber(): string
    {
        $prefix = 'INV-'.now()->format('Ymd').'-';

        $latest = BillingHistory::query()
            ->where('invoice_number', 'like', $prefix.'%')
            ->orderByDesc('invoice_number')
            ->value('invoice_number');

        $seq = 1;
        if (is_string($latest) && preg_match('/-(\d+)$/', $latest, $matches)) {
            $seq = ((int) $matches[1]) + 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public function hasActiveInvoice(User $user): bool
    {
        return BillingHistory::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'paid'])
            ->exists();
    }

    public function hasRestorableVoidInvoice(User $user): bool
    {
        return BillingHistory::query()
            ->where('user_id', $user->id)
            ->where('status', 'void')
            ->exists();
    }

    public function voidInvoice(BillingHistory $billing): BillingHistory
    {
        $billing->update([
            'status' => 'void',
            'paid_at' => null,
            'voided_at' => now(),
        ]);

        return $billing->fresh();
    }

    public function restoreVoidedInvoice(BillingHistory $billing): BillingHistory
    {
        if ($billing->status !== 'void') {
            return $billing;
        }

        $billing->update([
            'status' => 'pending',
            'paid_at' => null,
            'voided_at' => null,
        ]);

        return $billing->fresh();
    }

    /**
     * Permanently delete voided invoices past the retention window.
     */
    public function purgeExpiredVoidedInvoices(?\Carbon\CarbonInterface $now = null): int
    {
        $cutoff = ($now ?? now())->copy()->subDays(self::VOID_RETENTION_DAYS);

        return BillingHistory::query()
            ->where('status', 'void')
            ->whereNotNull('voided_at')
            ->where('voided_at', '<=', $cutoff)
            ->delete();
    }

    /**
     * Create a fresh pending invoice when the user still has a plan
     * and has nothing to restore (no pending/paid/void invoices).
     */
    public function recreateForAssignedPlan(User $user, ?User $actor = null): ?BillingHistory
    {
        $user->loadMissing('plan');

        if ($user->isAdmin() || ! $user->plan_id || ! $user->plan) {
            return null;
        }

        if ($this->hasActiveInvoice($user) || $this->hasRestorableVoidInvoice($user)) {
            return null;
        }

        return $this->recordPlanAssignment(
            $user,
            $user->plan,
            'Invoice recreated for assigned plan',
            $actor,
            'pending',
        );
    }

    /**
     * Users with an assigned plan who have no pending/paid invoice
     * and also no void invoice left to restore.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function usersNeedingInvoiceRecreation()
    {
        return User::query()
            ->with('plan')
            ->whereNotNull('plan_id')
            ->where('role', '!=', 'admin')
            ->whereDoesntHave('billingHistories', function ($query) {
                $query->whereIn('status', ['pending', 'paid', 'void']);
            })
            ->orderBy('name')
            ->get();
    }
}
