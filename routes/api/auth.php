<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\api\Auth\EmailVerificationController;
use App\Http\Controllers\api\Auth\ForgotPasswordController;
use App\Http\Controllers\api\Auth\LoginController;
use App\Http\Controllers\api\Auth\LogoutController;
use App\Http\Controllers\api\Auth\RegisterController;
use App\Http\Controllers\api\Auth\ResetPasswordController;

Route::prefix('auth')->group(function () {
    Route::post('/login', LoginController::class)->middleware('throttle:auth');
    Route::post('/register', RegisterController::class)->middleware('throttle:auth');
    Route::post('/logout', LogoutController::class)->middleware('auth:sanctum');
    Route::post('/forgot-password', ForgotPasswordController::class)->middleware('throttle:auth');
    Route::post('/reset-password', ResetPasswordController::class)->middleware('throttle:auth');

    // Email verification
    Route::get('/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')
        ->name('verification.verify');

    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware(['auth:sanctum', 'throttle:6,1']);
});
