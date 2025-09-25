<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\api\v1\FishingSessionController;
use App\Http\Controllers\api\v1\SessionCatchController;
use App\Http\Controllers\api\v1\SessionReviewController;

// Assigned (session-level) – važno: PRE apiResource!
Route::get('/sessions/assigned-count', [SessionReviewController::class, 'assignedCount']);
Route::get('/sessions/assigned-to-me', [SessionReviewController::class, 'assignedToMe']);

// Sessions CRUD
Route::apiResource('sessions', FishingSessionController::class)->only(['index', 'show', 'store', 'update', 'destroy']);

// Session actions
Route::post('sessions/{session}/close', [FishingSessionController::class, 'close']);
Route::post('sessions/{session}/catches/stack', [SessionCatchController::class, 'upsert']);
Route::post('/sessions/{session}/close-and-nominate', [FishingSessionController::class, 'closeAndNominate']);

// Session review (session-level)
Route::post('/sessions/{session}/review', [SessionReviewController::class, 'review']);       // legacy UI još uvek koristi
Route::post('/sessions/{session}/nominate', [SessionReviewController::class, 'nominate']);
Route::post('/sessions/{session}/confirm', [SessionReviewController::class, 'confirm']);
Route::post('/sessions/{session}/finalize', [SessionReviewController::class, 'finalize']);
