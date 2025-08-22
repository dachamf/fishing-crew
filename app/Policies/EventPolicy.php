<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;
use App\Models\Group;

class EventPolicy
{
    public function createInGroup(User $user, Group $group): bool
    {
        // član grupe može kreirati dogadjaj u grupi
        return $group->members()->where('users.id', $user->id)->exists();
    }

    // Alias to match controller ability name
    public function createEvent(User $user, Group $group): bool
    {
        return $this->createInGroup($user, $group);
    }

    public function rsvp(User $user, Event $event): bool
    {
        // samo članovi grupe mogu RSVP
        return $event->group->members()->where('users.id', $user->id)->exists();
    }

    public function update(User $user, Event $event): bool
    {
        // minimal: dozvoli owner/admin grupe; ako role nije dostupna, bar član grupe
        return $event->group->members()
            ->where('users.id', $user->id)
            ->wherePivotIn('role', ['owner', 'admin'])
            ->exists();
    }
}
