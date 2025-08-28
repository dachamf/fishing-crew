<?php

namespace App\Policies;

use App\Models\FishingSession;
use App\Models\User;

class FishingSessionPolicy
{
    public function view(User $u, FishingSession $s): bool
    {
        // vlasnik ili bilo ko iz iste grupe (po potrebi pooštri)
        return $u->id === $s->user_id || ($s->group_id && $u->groups()->where('groups.id', $s->group_id)->exists());
    }

    public function create(User $u): bool
    {
        return !is_null($u->id);
    }

    public function update(User $u, FishingSession $s): bool
    {
        // vlasnik ili owner/moderator grupe (prilagodi kako ti je pivot role rešena)
        if ($u->id === $s->user_id) return true;
        if ($s->group_id) {
            return $u->groups()->where('groups.id', $s->group_id)
                ->whereIn('group_user.role', ['owner', 'moderator'])->exists();
        }
        return false;
    }

    public function delete(User $u, FishingSession $s): bool
    {
        return $this->update($u, $s);
    }

    public function nominate(User $user, FishingSession $session): bool
    {
        return $session->user_id === $user->id && !$session->isFinal();
    }

    public function confirm(User $user, FishingSession $session): bool
    {
        return $session->confirmations()->where('nominee_user_id', $user->id)->exists() && !$session->isFinal();
    }

    public function finalize(User $user, FishingSession $session): bool
    {
        return $session->user_id === $user->id && !$session->isFinal();
    }
}
