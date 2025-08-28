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
        return $group->isMember($user->id);
    }

    // Alias to match controller ability name
    public function createEvent(User $user, Group $group): bool
    {
        return $this->createInGroup($user, $group);
    }

    public function view(User $user, Event $event): bool {
        return !$event->group_id || $user->groups()->where('groups.id', $event->group_id)->exists();
    }

    public function rsvp(User $user, Event $event): bool
    {
        // samo članovi grupe mogu RSVP
        return $this->view($user, $event);
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
