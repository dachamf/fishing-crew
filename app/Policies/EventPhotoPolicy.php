<?php

namespace App\Policies;

use App\Models\EventPhoto;
use App\Models\User;

class EventPhotoPolicy
{
    public function delete(User $user, EventPhoto $photo): bool
    {
        if ($user->id === $photo->user_id) return true;
        $event = $photo->event()->first();
        return $event && (int)$event->user_id === (int)$user->id;
    }
}
