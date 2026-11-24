<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkPattern;

class WorkPatternPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, WorkPattern $pattern): bool
    {
        return $user->company_id === $pattern->company_id;
    }
}
