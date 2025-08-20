<?php

use App\Http\Controllers\api\Auth\LoginController;
use App\Http\Controllers\api\Auth\LogoutController;
use App\Http\Controllers\api\Auth\RegisterController;
use App\Http\Controllers\api\v1\CatchesController;
use App\Http\Controllers\api\v1\EventsController;
use App\Http\Controllers\api\v1\GroupsController;
use App\Http\Controllers\api\v1\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', LoginController::class);
    Route::post('/register', RegisterController::class);
    Route::post('/logout', LogoutController::class)->middleware('auth:sanctum');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('v1')->group(function () {
        Route::apiResource('groups', GroupsController::class);
        Route::get('groups/{group}/members', [GroupsController::class, 'members']);
        Route::post('groups/{group}/invite', [GroupsController::class, 'invite']);

        Route::get('groups/{group}/events', [EventsController::class, 'index']);
        Route::post('groups/{group}/events', [EventsController::class, 'store']);

        Route::get('events/{event}', [EventsController::class, 'show']);
        Route::post('events/{event}/rsvp', [EventsController::class, 'rsvp']);
        Route::post('events/{event}/checkin', [EventsController::class, 'checkin']);
        Route::post('events/{event}/postpone/propose', [EventsController::class, 'proposePostpone']);
        Route::post('events/{event}/postpone/vote', [EventsController::class, 'votePostpone']);

        Route::post('catches', [CatchesController::class, 'store']);
        Route::get('catches/all', [CatchesController::class, 'listByAll']);
        Route::post('catches/{catch}/confirm', [CatchesController::class, 'confirm']);
        Route::get('users/{user}/catches', [CatchesController::class, 'listByUser']);

        Route::get('profile/me', [ProfileController::class, 'me']);
        Route::patch('profile', [ProfileController::class, 'update']);
        Route::post('profile/avatar', [ProfileController::class, 'uploadAvatar']);
        Route::delete('profile/avatar', [ProfileController::class, 'deleteAvatar']);

        Route::get('users/{user}/profile', [ProfileController::class, 'showPublic']); // po želji public bez auth

    });

    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
