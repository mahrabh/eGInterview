<?php

namespace App\Policies;

use App\Models\LoanProductRule;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class LoanProductRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, LoanProductRule $loanProductRule): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, LoanProductRule $loanProductRule): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, LoanProductRule $loanProductRule): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, LoanProductRule $loanProductRule): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, LoanProductRule $loanProductRule): bool
    {
        return $user->isAdmin();
    }
}
