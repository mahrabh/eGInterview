<?php

namespace App\Policies;

use App\Models\LoanApplication;
use App\Models\User;

class LoanApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessLoans();
    }

    public function view(User $user, LoanApplication $loanApplication): bool
    {
        if (!$user->canAccessLoans()) {
            return false;
        }

        return $user->isAdmin() || $user->id === $loanApplication->applicant->created_by;
    }

    public function create(User $user): bool
    {
        return $user->canAccessLoans();
    }

    public function update(User $user, LoanApplication $loanApplication): bool
    {
        if (!$user->canAccessLoans()) {
            return false;
        }

        return $user->isAdmin() || $user->id === $loanApplication->applicant->created_by;
    }

    public function delete(User $user, LoanApplication $loanApplication): bool
    {
        if (!$user->canAccessLoans()) {
            return false;
        }

        return $user->isAdmin() || $user->id === $loanApplication->applicant->created_by;
    }

    public function restore(User $user, LoanApplication $loanApplication): bool
    {
        return $this->update($user, $loanApplication);
    }

    public function forceDelete(User $user, LoanApplication $loanApplication): bool
    {
        return $this->delete($user, $loanApplication);
    }
}
