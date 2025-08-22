<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Group;

class EventPolicy
{
    public function createInGroup(User $user, Group $group): bool
    {
        // primer: član grupe
        return $group->members()->where('users.id', $user->id)->exists();
    }
}
