<?php

use App\Http\Controllers\ServerController;
use App\Http\Controllers\MetricsController;
use App\Http\Controllers\PrometheusTargetController;
use App\Http\Controllers\UptimeController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\MaintenanceController;
use Illuminate\Support\Facades\Route;

Route::get('/prometheus/targets/{exporter}', [PrometheusTargetController::class, 'index']);

Route::prefix('v1')->group(function () {
    // Public routes (Auth)
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        // Server management
        Route::apiResource('users', UserController::class);
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword']);
        Route::apiResource('servers', ServerController::class);
        Route::put('/servers/{server}/db-credentials', [ServerController::class, 'updateDbCredentials']);
        Route::put('/servers/{server}/ssh-credentials', [ServerController::class, 'updateSshCredentials']);
        Route::put('/servers/{server}/thresholds', [ServerController::class, 'updateThresholds']);

        // Real-time metrics
        Route::get('/metrics/{instance}', [MetricsController::class, 'current']);
        Route::get('/metrics/{instance}/history', [MetricsController::class, 'history']);

        // Uptime
        Route::get('/uptime', [UptimeController::class, 'summary']);
        Route::get('/uptime/{instance}', [UptimeController::class, 'history']);

        // Alerts
        Route::get('/alerts', [AlertController::class, 'index']);
        Route::get('/alerts/active', [AlertController::class, 'active']);

        // Maintenance
        Route::get('/maintenance/smtp', [MaintenanceController::class, 'getSmtpSettings']);
        Route::put('/maintenance/smtp', [MaintenanceController::class, 'updateSmtpSettings']);
        Route::post('/maintenance/smtp/test', [MaintenanceController::class, 'testSmtpSettings']);
    });
});
