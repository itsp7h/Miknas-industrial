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
        Route::get('notifications/unread', [\App\Http\Controllers\Api\NotificationController::class, 'unread']);

        Route::prefix('purchase')->group(function () {
            Route::get('suppliers', [\App\Http\Controllers\Api\Purchase\SupplierController::class, 'index']);
            Route::post('suppliers', [\App\Http\Controllers\Api\Purchase\SupplierController::class, 'store']);
            Route::post('suppliers/import', [\App\Http\Controllers\Api\Purchase\SupplierController::class, 'import']);
            Route::get('suppliers/template', [\App\Http\Controllers\Api\Purchase\SupplierController::class, 'downloadTemplate']);
            Route::get('suppliers/export-pdf', [\App\Http\Controllers\Api\Purchase\SupplierController::class, 'exportPdf']);
            Route::put('suppliers/{supplier}', [\App\Http\Controllers\Api\Purchase\SupplierController::class, 'update']);
            Route::delete('suppliers/{supplier}', [\App\Http\Controllers\Api\Purchase\SupplierController::class, 'destroy']);
        });
    });
});
