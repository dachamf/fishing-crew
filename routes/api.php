<?php

use Illuminate\Support\Facades\Route;

// Ograniči route parametre (štiti od kolizija tipa `assigned-count`)
Route::pattern('session', '[0-9]+');
Route::pattern('event', '[0-9]+');
Route::pattern('group', '[0-9]+');

// AUTH (van v1 grupe)
require __DIR__ . '/api/auth.php';

// v1 API
Route::middleware(['auth:sanctum', 'verified'])
    ->prefix('v1')
    ->group(function () {
        require __DIR__ . '/api/v1/core.php';
        require __DIR__ . '/api/v1/groups_events.php';
        require __DIR__ . '/api/v1/species.php';
        require __DIR__ . '/api/v1/catches.php';
        require __DIR__ . '/api/v1/sessions.php';
        require __DIR__ . '/api/v1/stats_leaderboard.php';
        require __DIR__ . '/api/v1/profile_account.php';
    });

// Token-based potvrda sesije (bez auth, ostaje van v1 grupe)
Route::post('/v1/sessions/{session}/confirm/{token}', [\App\Http\Controllers\api\v1\SessionReviewController::class, 'confirmByToken']);
