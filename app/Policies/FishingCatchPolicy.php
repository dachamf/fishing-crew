<?php

namespace App\Policies;

use App\Models\FishingCatch;
use App\Models\User;

class FishingCatchPolicy
{
    public function view(User $u, FishingCatch $s): bool {
        return $u->id === $s->user_id || ($s->group_id && $u->groups()->where('groups.id',$s->group_id)->exists());
    }

    public function create(User $u): bool {
        return !is_null($u->id);
    }

    public function update(User $u, FishingCatch $s): bool {
        // vlasnik ili owner/moderator grupe (prilagodi kako ti je pivot role rešena)
        if ($u->id === $s->user_id) return true;
        if ($s->group_id) {
            return $u->groups()->where('groups.id',$s->group_id)
                ->whereIn('group_user.role',['owner','moderator'])->exists();
        }
        return false;
    }

    public function delete(User $u, FishingCatch $s): bool {
        return $this->update($u,$s);
    }
}
