<?php

namespace App\Enums;

enum PlanModuleType: string
{
    case Recruitment = 'recruitment';
    case LoanApplicants = 'loan_applicants';
    case Combined = 'combined';

    public function label(): string
    {
        return match ($this) {
            self::Recruitment => 'Recruitment',
            self::LoanApplicants => 'Loan Applicants',
            self::Combined => 'Combined',
        };
    }

    public function includesRecruitment(): bool
    {
        return $this === self::Recruitment || $this === self::Combined;
    }

    public function includesLoans(): bool
    {
        return $this === self::LoanApplicants || $this === self::Combined;
    }

    /** @return list<string> */
    public function compatibleRoles(): array
    {
        return match ($this) {
            self::Recruitment => ['recruiter', 'both'],
            self::LoanApplicants => ['analyst', 'both'],
            self::Combined => ['recruiter', 'analyst', 'both'],
        };
    }

    public function isCompatibleWithRole(string $role): bool
    {
        return in_array($role, $this->compatibleRoles(), true);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
