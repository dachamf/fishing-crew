<?php

use App\Http\Controllers\api\Auth\EmailVerificationController;
use App\Http\Controllers\api\Auth\LoginController;
use App\Http\Controllers\api\Auth\LogoutController;
use App\Http\Controllers\api\Auth\RegisterController;
use App\Http\Controllers\api\v1\AccountController;
use App\Http\Controllers\api\v1\CatchesConfirmationController;
use App\Http\Controllers\api\v1\CatchesController;
use App\Http\Controllers\api\v1\CatchPhotoController;
use App\Http\Controllers\api\v1\CatchReviewController;
use App\Http\Controllers\api\v1\EventAttendeeController;
use App\Http\Controllers\api\v1\EventsController;
use App\Http\Controllers\api\v1\FishingSessionController;
use App\Http\Controllers\api\v1\GroupsController;
use App\Http\Controllers\api\v1\ProfileController;
use App\Http\Controllers\api\v1\SessionCatchController;
use App\Http\Controllers\api\v1\SpeciesController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', LoginController::class);
    Route::post('/register', RegisterController::class);
    Route::post('/logout', LogoutController::class)->middleware('auth:sanctum');

    // Info o ulogovanom (za FE)
    Route::middleware('auth:sanctum')->get('/me', fn (Request $r) => $r->user()->load('profile'));

    // Email verification
    Route::get('/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed') // potpisan URL
        ->name('verification.verify');

    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware(['auth:sanctum','throttle:6,1']);
});

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::prefix('v1')->group(function () {
        Route::apiResource('groups', GroupsController::class);
        Route::get('groups/{group}/members', [GroupsController::class, 'members']);
        Route::post('groups/{group}/invite', [GroupsController::class, 'invite']);

        Route::get('groups/{group}/events', [EventsController::class, 'index']);
        Route::post('groups/{group}/events', [EventsController::class, 'store']);

        Route::get('events/{event}', [EventsController::class, 'show']);
        Route::patch('events/{event}', [EventsController::class, 'update']);
        Route::post('events/{event}/rsvp', [EventsController::class, 'rsvp']);
        Route::post('events/{event}/checkin', [EventsController::class, 'checkin']);
        Route::post('events/{event}/postpone/propose', [EventsController::class, 'proposePostpone']);
        Route::post('events/{event}/postpone/vote', [EventsController::class, 'votePostpone']);

        Route::get   ('/events/{event}/attendees', [EventAttendeeController::class, 'index']);
        Route::post  ('/events/{event}/attendees', [EventAttendeeController::class, 'store']);
        Route::patch ('/events/{event}/attendees', [EventAttendeeController::class, 'update']);
        Route::delete('/events/{event}/attendees', [EventAttendeeController::class, 'destroy']);

        Route::get('/species', [SpeciesController::class, 'index']);

        Route::get('catches/to-review',            [CatchReviewController::class, 'assignedToMe']);
        Route::get('catches',        [CatchesController::class, 'index']);   // samo moj ulov po defaultu
        Route::get('catches/{id}',   [CatchesController::class, 'show']);
        Route::post('catches',       [CatchesController::class, 'store']);
        Route::patch('catches/{id}', [CatchesController::class, 'update']);
        Route::delete('catches/{id}',[CatchesController::class, 'destroy']);

        // nominacije & review flow

        Route::post('catches/{id}/nominate',       [CatchReviewController::class, 'nominate']);
        Route::post('catches/{id}/confirm',        [CatchReviewController::class, 'confirm']);        // approve/reject
        Route::post('catches/{id}/request-change', [CatchReviewController::class, 'requestChange']);  // traži izmenu// ulovi koji čekaju mene

        // fotke (max 3)
        Route::post('catches/{id}/photos',                 [CatchPhotoController::class, 'store']);   // multipart
        Route::delete('catches/{id}/photos/{photoId}',     [CatchPhotoController::class, 'destroy']);


        Route::post('/catches/{catch}/confirm', [CatchesConfirmationController::class, 'store']);

        Route::apiResource('sessions', FishingSessionController::class)->only(['index','show','store','update','destroy']);
        Route::post('sessions/{session}/close', [FishingSessionController::class, 'close']);
        Route::post('sessions/{session}/catches/stack', [SessionCatchController::class, 'upsert']);

        Route::get('profile/me', [ProfileController::class, 'me']);
        Route::patch('profile', [ProfileController::class, 'update']);
        Route::post('profile/avatar', [ProfileController::class, 'uploadAvatar']);
        Route::delete('profile/avatar', [ProfileController::class, 'deleteAvatar']);

        Route::patch('profile/password', [AccountController::class, 'changePassword']);
        Route::delete('account', [AccountController::class, 'destroy']);

        Route::get('users/{user}/profile', [ProfileController::class, 'showPublic']); // po želji public bez auth

    });

    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
