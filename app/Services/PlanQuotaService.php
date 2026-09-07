<?php

namespace App\Services;

use App\Enums\PlanModuleType;
use App\Exceptions\PlanQuotaExceededException;
use App\Models\Interview;
use App\Models\LoanApplicant;
use App\Models\Plan;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlanQuotaService
{
    public function currentPeriodStart(?CarbonInterface $now = null): CarbonInterface
    {
        return ($now ?? now())->copy()->startOfMonth();
    }

    public function currentPeriodEnd(?CarbonInterface $now = null): CarbonInterface
    {
        return ($now ?? now())->copy()->endOfMonth();
    }

    public function monthlyRecruitmentUsage(User $user, ?CarbonInterface $now = null): int
    {
        return Interview::query()
            ->where('user_id', $user->id)
            ->whereBetween('created_at', [
                $this->currentPeriodStart($now),
                $this->currentPeriodEnd($now),
            ])
            ->count();
    }

    public function monthlyLoanUsage(User $user, ?CarbonInterface $now = null): int
    {
        return LoanApplicant::query()
            ->where('created_by', $user->id)
            ->whereBetween('created_at', [
                $this->currentPeriodStart($now),
                $this->currentPeriodEnd($now),
            ])
            ->count();
    }

    /**
     * Null means unlimited (admin / unrestricted).
     */
    public function recruitmentLimit(User $user): ?int
    {
        if ($user->isAdmin()) {
            return null;
        }

        $plan = $user->plan;
        if (!$plan || !$plan->is_active || !$plan->coversRecruitment()) {
            return 0;
        }

        return (int) $plan->effectiveRecruitmentLimit();
    }

    /**
     * Null means unlimited (admin / unrestricted).
     */
    public function loanLimit(User $user): ?int
    {
        if ($user->isAdmin()) {
            return null;
        }

        $plan = $user->plan;
        if (!$plan || !$plan->is_active || !$plan->coversLoans()) {
            return 0;
        }

        return (int) $plan->effectiveLoanLimit();
    }

    public function remainingRecruitment(User $user, ?CarbonInterface $now = null): ?int
    {
        $limit = $this->recruitmentLimit($user);
        if ($limit === null) {
            return null;
        }

        return max(0, $limit - $this->monthlyRecruitmentUsage($user, $now));
    }

    public function remainingLoan(User $user, ?CarbonInterface $now = null): ?int
    {
        $limit = $this->loanLimit($user);
        if ($limit === null) {
            return null;
        }

        return max(0, $limit - $this->monthlyLoanUsage($user, $now));
    }

    public function assertCanCreateRecruitment(User $user, int $quantity = 1): void
    {
        if ($quantity < 1) {
            return;
        }

        if ($user->isAdmin()) {
            return;
        }

        $plan = $user->relationLoaded('plan') ? $user->plan : $user->plan()->first();
        if (!$plan) {
            throw new PlanQuotaExceededException(
                'You need an active Recruitment or Combined plan to import candidates.',
                'recruitment'
            );
        }

        if (!$plan->is_active) {
            throw new PlanQuotaExceededException(
                'Your plan is inactive. Ask an admin to activate or reassign a plan.',
                'recruitment'
            );
        }

        if (!$plan->coversRecruitment()) {
            throw new PlanQuotaExceededException(
                'Your plan does not include Recruitment interviews.',
                'recruitment'
            );
        }

        $remaining = $this->remainingRecruitment($user);
        if ($remaining !== null && $remaining < $quantity) {
            throw new PlanQuotaExceededException(
                'You have reached your monthly Recruitment interview quota. Please upgrade your plan or wait until next month.',
                'recruitment'
            );
        }
    }

    public function assertCanCreateLoan(User $user, int $quantity = 1): void
    {
        if ($quantity < 1) {
            return;
        }

        if ($user->isAdmin()) {
            return;
        }

        $plan = $user->relationLoaded('plan') ? $user->plan : $user->plan()->first();
        if (!$plan) {
            throw new PlanQuotaExceededException(
                'You need an active Loan Applicants or Combined plan to import applicants.',
                'loan'
            );
        }

        if (!$plan->is_active) {
            throw new PlanQuotaExceededException(
                'Your plan is inactive. Ask an admin to activate or reassign a plan.',
                'loan'
            );
        }

        if (!$plan->coversLoans()) {
            throw new PlanQuotaExceededException(
                'Your plan does not include Loan Applicant interviews.',
                'loan'
            );
        }

        $remaining = $this->remainingLoan($user);
        if ($remaining !== null && $remaining < $quantity) {
            throw new PlanQuotaExceededException(
                'You have reached your monthly Loan interview quota. Please upgrade your plan or wait until next month.',
                'loan'
            );
        }
    }

    /**
     * Atomically reserve one recruitment slot under a user row lock (concurrent-safe).
     * The callback runs while the lock is held so the create counts toward the same check.
     *
     * @template T
     * @param  callable():T  $callback
     * @return T
     */
    public function withRecruitmentSlot(User $user, callable $callback): mixed
    {
        if ($user->isAdmin()) {
            return $callback();
        }

        return DB::transaction(function () use ($user, $callback) {
            User::query()->whereKey($user->id)->lockForUpdate()->first();
            $user->unsetRelation('plan');
            $user->load('plan');
            $this->assertCanCreateRecruitment($user, 1);

            return $callback();
        });
    }

    /**
     * Atomically reserve one loan slot under a user row lock (concurrent-safe).
     *
     * @template T
     * @param  callable():T  $callback
     * @return T
     */
    public function withLoanSlot(User $user, callable $callback): mixed
    {
        if ($user->isAdmin()) {
            return $callback();
        }

        return DB::transaction(function () use ($user, $callback) {
            User::query()->whereKey($user->id)->lockForUpdate()->first();
            $user->unsetRelation('plan');
            $user->load('plan');
            $this->assertCanCreateLoan($user, 1);

            return $callback();
        });
    }

    /**
     * @deprecated Use withRecruitmentSlot()
     */
    public function consumeRecruitmentSlot(User $user): void
    {
        $this->withRecruitmentSlot($user, fn () => null);
    }

    /**
     * @deprecated Use withLoanSlot()
     */
    public function consumeLoanSlot(User $user): void
    {
        $this->withLoanSlot($user, fn () => null);
    }

    /**
     * Link generation / regeneration for an already-created record must never
     * consume quota or be blocked solely because the monthly limit was reached later.
     */
    public function assertExistingRecordLinkAllowed(User $user): void
    {
        // Intentionally no-op for quota: existing candidates/applicants already counted once.
        // Admins and non-admins may continue working with already-created records.
        unset($user);
    }

    public function validateAssignablePlan(?int $planId, string $role): ?Plan
    {
        if ($planId === null) {
            return null;
        }

        $plan = Plan::query()->find($planId);
        if (!$plan) {
            throw ValidationException::withMessages([
                'plan_id' => 'Selected plan does not exist.',
            ]);
        }

        if (!$plan->is_active) {
            throw ValidationException::withMessages([
                'plan_id' => 'Inactive plans cannot be assigned.',
            ]);
        }

        $module = $plan->moduleTypeEnum();
        if (!$module->isCompatibleWithRole($role)) {
            throw ValidationException::withMessages([
                'plan_id' => sprintf(
                    'The %s plan is not compatible with the %s role.',
                    $module->label(),
                    ucfirst($role)
                ),
            ]);
        }

        return $plan;
    }

    /**
     * @return array{module: string, recruitment_used: int, recruitment_limit: int|null, recruitment_remaining: int|null, loan_used: int, loan_limit: int|null, loan_remaining: int|null, account_status: string}
     */
    public function usageSnapshot(User $user): array
    {
        if ($user->isAdmin()) {
            return [
                'module' => 'All modules',
                'recruitment_used' => $this->monthlyRecruitmentUsage($user),
                'recruitment_limit' => null,
                'recruitment_remaining' => null,
                'loan_used' => $this->monthlyLoanUsage($user),
                'loan_limit' => null,
                'loan_remaining' => null,
                'account_status' => 'Unrestricted',
            ];
        }

        $plan = $user->plan;
        $moduleLabel = $plan?->moduleTypeEnum()->label() ?? 'None';

        if (!$plan) {
            $status = 'No plan';
        } elseif (!$plan->is_active) {
            $status = 'Plan inactive';
        } else {
            $status = 'Active';
        }

        return [
            'module' => $moduleLabel,
            'recruitment_used' => $this->monthlyRecruitmentUsage($user),
            'recruitment_limit' => $this->recruitmentLimit($user),
            'recruitment_remaining' => $this->remainingRecruitment($user),
            'loan_used' => $this->monthlyLoanUsage($user),
            'loan_limit' => $this->loanLimit($user),
            'loan_remaining' => $this->remainingLoan($user),
            'account_status' => $status,
        ];
    }

    public function plansCompatibleWithRole(string $role, bool $activeOnly = true)
    {
        $query = Plan::query()->orderBy('name');

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        $allowed = collect(PlanModuleType::cases())
            ->filter(fn (PlanModuleType $type) => $type->isCompatibleWithRole($role))
            ->map(fn (PlanModuleType $type) => $type->value)
            ->values()
            ->all();

        return $query->whereIn('module_type', $allowed);
    }
}
