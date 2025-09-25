<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\api\v1\GroupsController;
use App\Http\Controllers\api\v1\EventsController;
use App\Http\Controllers\api\v1\EventAttendeeController;

Route::apiResource('groups', GroupsController::class);
Route::get('groups/{group}/members', [GroupsController::class, 'members']);
Route::post('groups/{group}/invite', [GroupsController::class, 'invite']);

// Events by group
Route::get('groups/{group}/events', [EventsController::class, 'index']);
Route::post('groups/{group}/events', [EventsController::class, 'store']);

// Single event
Route::get('events/{event}', [EventsController::class, 'show']);
Route::patch('events/{event}', [EventsController::class, 'update']);
Route::post('events/{event}/rsvp', [EventsController::class, 'rsvp'])->middleware('throttle:20,1');
Route::post('events/{event}/checkin', [EventsController::class, 'checkin']);
Route::post('events/{event}/postpone/propose', [EventsController::class, 'proposePostpone']);
Route::post('events/{event}/postpone/vote', [EventsController::class, 'votePostpone']);

// Events index
Route::get('/events', [EventsController::class, 'index']);

// Attendees
Route::get('/events/{event}/attendees', [EventAttendeeController::class, 'index']);
Route::post('/events/{event}/attendees', [EventAttendeeController::class, 'store']);
Route::patch('/events/{event}/attendees', [EventAttendeeController::class, 'update']);
Route::delete('/events/{event}/attendees', [EventAttendeeController::class, 'destroy']);
