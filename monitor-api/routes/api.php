<?php

use App\Http\Controllers\ServerController;
use App\Http\Controllers\MetricsController;
use App\Http\Controllers\UptimeController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public routes (Auth)
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        // Server management
        Route::apiResource('users', UserController::class);
        Route::apiResource('servers', ServerController::class);

        // Real-time metrics
        Route::get('/metrics/{instance}', [MetricsController::class, 'current']);
        Route::get('/metrics/{instance}/history', [MetricsController::class, 'history']);

        // Uptime
        Route::get('/uptime', [UptimeController::class, 'summary']);
        Route::get('/uptime/{instance}', [UptimeController::class, 'history']);

        // Alerts
        Route::get('/alerts', [AlertController::class, 'index']);
        Route::get('/alerts/active', [AlertController::class, 'active']);
    });
});
