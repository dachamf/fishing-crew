<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\api\v1\HomeController;
use App\Http\Controllers\api\v1\GeoController;
use App\Http\Controllers\api\v1\NotificationsController;
use App\Http\Controllers\Auth\MeController;
use App\Http\Controllers\api\v1\WeatherController;

Route::get('/home', [HomeController::class, 'show']);
Route::get('/geocode/reverse', [GeoController::class, 'reverse'])->middleware('throttle:30,1');
Route::get('/notifications/unread-count', [NotificationsController::class, 'unreadCount']);

Route::get('/me', MeController::class);
Route::get('/me/roles', [MeController::class, 'roles']);
Route::get('/weather/summary', [WeatherController::class, 'summary']);

// Debug/helper (postojao u originalu)
Route::get('/user', function (\Illuminate\Http\Request $request) {
    return $request->user();
});
