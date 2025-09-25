<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\api\v1\ProfileController;
use App\Http\Controllers\api\v1\AccountController;

Route::get('profile/me', [ProfileController::class, 'me']);
Route::patch('profile', [ProfileController::class, 'update']);
Route::post('profile/avatar', [ProfileController::class, 'uploadAvatar']);
Route::delete('profile/avatar', [ProfileController::class, 'deleteAvatar']);

Route::patch('profile/password', [AccountController::class, 'changePassword']);
Route::delete('account', [AccountController::class, 'destroy']);

// Public profil
Route::get('users/{user}/profile', [ProfileController::class, 'showPublic']);
