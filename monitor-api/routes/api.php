<?php

use App\Http\Controllers\ServerController;
use App\Http\Controllers\MetricsController;
use App\Http\Controllers\UptimeController;
use App\Http\Controllers\AlertController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Server list & status
    Route::get('/servers', [ServerController::class, 'index']);
    Route::get('/servers/{instance}', [ServerController::class, 'show']);

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
