<?php

namespace App\Providers;

use App\Models\EventPhoto;
use App\Models\Group;
use App\Models\User;
use App\Policies\EventPhotoPolicy;
use App\Policies\GroupPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Group::class => GroupPolicy::class,
        EventPhoto::class => EventPhotoPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        Gate::define('createInGroup', function (User $user, Group $group) {
            // korisnik mora biti član grupe
            return $group->members()->where('users.id', $user->id)->exists();
        });
    }
}
