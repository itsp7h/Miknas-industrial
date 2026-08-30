<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\Purchase\PurchasePipelineController;
use App\Http\Controllers\Api\Purchase\SupplierController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('dashboard/ping', [DashboardController::class, 'ping']);
        Route::get('dashboard/summary', [DashboardController::class, 'summary']);
        Route::get('notifications/unread', [NotificationController::class, 'unread']);

        Route::prefix('purchase')->group(function () {
            Route::get('pipeline', [PurchasePipelineController::class, 'index']);
            Route::get('suppliers', [SupplierController::class, 'index']);
            Route::post('suppliers', [SupplierController::class, 'store']);
            Route::post('suppliers/import', [SupplierController::class, 'import']);
            Route::get('suppliers/template', [SupplierController::class, 'downloadTemplate']);
            Route::get('suppliers/export-pdf', [SupplierController::class, 'exportPdf']);
            Route::put('suppliers/{supplier}', [SupplierController::class, 'update']);
            Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy']);
        });
    });
});
