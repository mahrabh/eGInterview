<?php

namespace App\Policies;

use App\Models\LoanApplicant;
use App\Models\User;

class LoanApplicantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessLoans();
    }

    public function view(User $user, LoanApplicant $loanApplicant): bool
    {
        if (!$user->canAccessLoans()) {
            return false;
        }

        return $user->isAdmin() || $user->id === $loanApplicant->created_by;
    }

    public function create(User $user): bool
    {
        return $user->canAccessLoans();
    }

    public function update(User $user, LoanApplicant $loanApplicant): bool
    {
        if (!$user->canAccessLoans()) {
            return false;
        }

        return $user->isAdmin() || $user->id === $loanApplicant->created_by;
    }

    public function delete(User $user, LoanApplicant $loanApplicant): bool
    {
        if (!$user->canAccessLoans()) {
            return false;
        }

        return $user->isAdmin() || $user->id === $loanApplicant->created_by;
    }

    public function restore(User $user, LoanApplicant $loanApplicant): bool
    {
        return $this->update($user, $loanApplicant);
    }

    public function forceDelete(User $user, LoanApplicant $loanApplicant): bool
    {
        return $this->delete($user, $loanApplicant);
    }
}
