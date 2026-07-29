<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('dashboard/ping', [\App\Http\Controllers\Api\DashboardController::class, 'ping']);
        Route::get('dashboard/summary', [\App\Http\Controllers\Api\DashboardController::class, 'summary']);
    });
});
