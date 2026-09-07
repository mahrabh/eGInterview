<?php

namespace Database\Factories;

use App\Enums\PlanModuleType;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => ucwords($name),
            'slug' => Str::slug($name) . '-' . fake()->unique()->numerify('###'),
            'module_type' => PlanModuleType::Recruitment,
            'price' => fake()->randomFloat(2, 0, 99),
            'candidate_limit' => 0,
            'interview_limit' => 10,
            'recruitment_interview_limit' => 10,
            'loan_interview_limit' => 0,
            'ai_generation_limit' => 50,
            'team_member_limit' => 0,
            'is_active' => true,
        ];
    }

    public function recruitment(int $limit = 10): static
    {
        return $this->state(fn () => [
            'module_type' => PlanModuleType::Recruitment,
            'recruitment_interview_limit' => $limit,
            'interview_limit' => $limit,
            'loan_interview_limit' => 0,
        ]);
    }

    public function loanApplicants(int $limit = 10): static
    {
        return $this->state(fn () => [
            'module_type' => PlanModuleType::LoanApplicants,
            'recruitment_interview_limit' => 0,
            'interview_limit' => 0,
            'loan_interview_limit' => $limit,
        ]);
    }

    public function combined(int $recruitmentLimit = 10, int $loanLimit = 10): static
    {
        return $this->state(fn () => [
            'module_type' => PlanModuleType::Combined,
            'recruitment_interview_limit' => $recruitmentLimit,
            'interview_limit' => $recruitmentLimit,
            'loan_interview_limit' => $loanLimit,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
