<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/health', \Spatie\Health\Http\Controllers\HealthCheckResultsController::class);
