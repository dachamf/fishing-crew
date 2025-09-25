<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\api\v1\SpeciesController;

Route::get('/species', [SpeciesController::class, 'index']);
