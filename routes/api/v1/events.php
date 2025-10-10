<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\api\v1\EventPhotoController;


Route::get   ('/events/{event}/photos', [EventPhotoController::class, 'index']);
Route::post  ('/events/{event}/photos', [EventPhotoController::class, 'store'])->middleware('throttle:20,1');
Route::delete('/events/{event}/photos/{photo}', [EventPhotoController::class, 'destroy'])->middleware('can:delete,photo');
