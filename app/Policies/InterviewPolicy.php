<?php

namespace App\Policies;

use App\Models\Interview;
use App\Models\User;

class InterviewPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessRecruitment();
    }

    public function view(User $user, Interview $interview): bool
    {
        return $user->isAdmin() || ($user->isRecruiter() && $user->id === $interview->user_id);
    }

    public function create(User $user): bool
    {
        return $user->canAccessRecruitment();
    }

    public function update(User $user, Interview $interview): bool
    {
        return $user->isAdmin() || ($user->isRecruiter() && $user->id === $interview->user_id);
    }

    public function delete(User $user, Interview $interview): bool
    {
        return $user->isAdmin() || ($user->isRecruiter() && $user->id === $interview->user_id);
    }
}
