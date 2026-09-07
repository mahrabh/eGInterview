<?php

namespace App\Models;

use App\Enums\PlanModuleType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'slug',
    'module_type',
    'price',
    'candidate_limit',
    'interview_limit',
    'recruitment_interview_limit',
    'loan_interview_limit',
    'ai_generation_limit',
    'team_member_limit',
    'is_active',
])]
class Plan extends Model
{
    use HasFactory;

    protected $casts = [
        'is_active' => 'boolean',
        'price' => 'decimal:2',
        'candidate_limit' => 'integer',
        'interview_limit' => 'integer',
        'recruitment_interview_limit' => 'integer',
        'loan_interview_limit' => 'integer',
        'ai_generation_limit' => 'integer',
        'team_member_limit' => 'integer',
        'module_type' => PlanModuleType::class,
    ];

    protected static function booted(): void
    {
        static::saving(function (Plan $plan) {
            // Keep legacy interview_limit aligned with recruitment quota for older UI/code.
            if ($plan->isDirty('recruitment_interview_limit') || $plan->interview_limit === null) {
                $plan->interview_limit = (int) $plan->recruitment_interview_limit;
            } elseif ($plan->isDirty('interview_limit') && !$plan->isDirty('recruitment_interview_limit')) {
                $plan->recruitment_interview_limit = (int) $plan->interview_limit;
            }

            if ($plan->module_type === null) {
                $plan->module_type = PlanModuleType::Recruitment;
            }
        });
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function moduleTypeEnum(): PlanModuleType
    {
        if ($this->module_type instanceof PlanModuleType) {
            return $this->module_type;
        }

        return PlanModuleType::tryFrom((string) $this->module_type) ?? PlanModuleType::Recruitment;
    }

    public function coversRecruitment(): bool
    {
        return $this->moduleTypeEnum()->includesRecruitment();
    }

    public function coversLoans(): bool
    {
        return $this->moduleTypeEnum()->includesLoans();
    }

    public function effectiveRecruitmentLimit(): int
    {
        if ($this->recruitment_interview_limit !== null) {
            return (int) $this->recruitment_interview_limit;
        }

        return (int) $this->interview_limit;
    }

    public function effectiveLoanLimit(): int
    {
        return (int) ($this->loan_interview_limit ?? 0);
    }

    public function moduleLabel(): string
    {
        return $this->moduleTypeEnum()->label();
    }

    public function isCompatibleWithRole(string $role): bool
    {
        return $this->moduleTypeEnum()->isCompatibleWithRole($role);
    }
}
