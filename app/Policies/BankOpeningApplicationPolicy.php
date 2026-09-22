<?php

namespace App\Policies;

use App\Models\BankOpeningApplication;
use App\Models\User;

class BankOpeningApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessBankOpening();
    }

    public function view(User $user, BankOpeningApplication $application): bool
    {
        if (! $user->canAccessBankOpening()) {
            return false;
        }

        return $user->isAdmin() || $user->id === $application->applicant->created_by;
    }

    public function create(User $user): bool
    {
        return $user->canAccessBankOpening();
    }

    public function update(User $user, BankOpeningApplication $application): bool
    {
        if (! $user->canAccessBankOpening()) {
            return false;
        }

        return $user->isAdmin() || $user->id === $application->applicant->created_by;
    }

    public function delete(User $user, BankOpeningApplication $application): bool
    {
        if (! $user->canAccessBankOpening()) {
            return false;
        }

        return $user->isAdmin() || $user->id === $application->applicant->created_by;
    }
}
