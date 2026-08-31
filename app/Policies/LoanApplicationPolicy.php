<?php

namespace App\Policies;

use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class LoanApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, LoanApplication $loanApplication): bool
    {
        return $user->isAdmin() || $user->id === $loanApplication->applicant->created_by;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, LoanApplication $loanApplication): bool
    {
        return $user->isAdmin() || $user->id === $loanApplication->applicant->created_by;
    }

    public function delete(User $user, LoanApplication $loanApplication): bool
    {
        return $user->isAdmin() || $user->id === $loanApplication->applicant->created_by;
    }

    public function restore(User $user, LoanApplication $loanApplication): bool
    {
        return $user->isAdmin() || $user->id === $loanApplication->applicant->created_by;
    }

    public function forceDelete(User $user, LoanApplication $loanApplication): bool
    {
        return $user->isAdmin() || $user->id === $loanApplication->applicant->created_by;
    }
}
