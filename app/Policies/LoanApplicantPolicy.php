<?php

namespace App\Policies;

use App\Models\LoanApplicant;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class LoanApplicantPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, LoanApplicant $loanApplicant): bool
    {
        return $user->isAdmin() || $user->id === $loanApplicant->created_by;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, LoanApplicant $loanApplicant): bool
    {
        return $user->isAdmin() || $user->id === $loanApplicant->created_by;
    }

    public function delete(User $user, LoanApplicant $loanApplicant): bool
    {
        return $user->isAdmin() || $user->id === $loanApplicant->created_by;
    }

    public function restore(User $user, LoanApplicant $loanApplicant): bool
    {
        return $user->isAdmin() || $user->id === $loanApplicant->created_by;
    }

    public function forceDelete(User $user, LoanApplicant $loanApplicant): bool
    {
        return $user->isAdmin() || $user->id === $loanApplicant->created_by;
    }
}
