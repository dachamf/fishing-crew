<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\api\v1\StatsController;
use App\Http\Controllers\api\v1\LeaderboardController;
use App\Http\Controllers\api\v1\ActivityController;
use App\Http\Controllers\api\v1\AchievementsController;

Route::get('/stats/season', [StatsController::class, 'mySeason']);
Route::get('/stats/species-top', [StatsController::class, 'speciesTop']);

Route::get('/leaderboard', [LeaderboardController::class, 'index']);
Route::get('/activity', [ActivityController::class, 'index']);
Route::get('/achievements', [AchievementsController::class, 'index']);
