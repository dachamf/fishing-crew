<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pokreće proveru na 10 minuta
Schedule::command('sessions:maybe-finalize')
    ->everyTenMinutes()
    ->withoutOverlapping(9)
    ->onOneServer()
    ->runInBackground();
