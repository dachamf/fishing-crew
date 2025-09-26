<?php

namespace App\Providers;

use App\Events\SessionFinalized;
use App\Listeners\LogSessionFinalized;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        SessionFinalized::class => [
            LogSessionFinalized::class,
        ],
    ];
}
