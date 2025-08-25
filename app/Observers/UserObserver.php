<?php

namespace App\Observers;

use App\Models\User;

class UserObserver
{
    public function created(User $user): void
    {
        // Dodaj u grupu #1 kao "member" (bez dupliranja ako već postoji)
        $user->groups()->syncWithoutDetaching([
            1 => ['role' => 'member'],
        ]);
    }
}
