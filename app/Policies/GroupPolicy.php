<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\User;

class GroupPolicy
{
    public function createEvent(User $user, Group $group): bool
    {
        return $group->users()->where('users.id', $user->id)->exists();
    }
}
