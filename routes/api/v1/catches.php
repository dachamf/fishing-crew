<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\api\v1\CatchesController;
use App\Http\Controllers\api\v1\CatchReviewController;
use App\Http\Controllers\api\v1\CatchPhotoController;
use App\Http\Controllers\api\v1\CatchesConfirmationController;

// Catches CRUD
Route::get('catches', [CatchesController::class, 'index']);   // samo moj ulov po defaultu
Route::get('catches/{id}', [CatchesController::class, 'show']);
Route::post('catches', [CatchesController::class, 'store']);
Route::patch('catches/{id}', [CatchesController::class, 'update']);
Route::delete('catches/{id}', [CatchesController::class, 'destroy']);

// Legacy review flow (catch-level)
Route::get('catches/to-review', [CatchReviewController::class, 'assignedToMe']);
Route::post('catches/{id}/reviewers', [CatchReviewController::class, 'nominate']);
Route::post('catches/{id}/request-confirmation', [CatchReviewController::class, 'nominate']);
Route::post('catches/{id}/review', [CatchReviewController::class, 'confirm']);
Route::post('catches/{id}/review/request-change', [CatchReviewController::class, 'requestChange']);
Route::get('review/assigned', [CatchReviewController::class, 'assignedToMe']);

// Photos (max 3)
Route::post('catches/{id}/photos', [CatchPhotoController::class, 'store']);   // multipart
Route::delete('catches/{id}/photos/{photoId}', [CatchPhotoController::class, 'destroy']);

// Catch confirmations (ako ih koristiš)
Route::post('/catches/{catch}/confirm', [CatchesConfirmationController::class, 'store']);
Route::post('/catches/{catch}/confirmations', [CatchesConfirmationController::class, 'store']);
Route::post('/catches/{catch}/confirmations/withdraw', [CatchesConfirmationController::class, 'withdraw']);
