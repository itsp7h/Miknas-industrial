<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\Inventory\ItemController;
use App\Http\Controllers\Api\Inventory\StockMovementController;
use App\Http\Controllers\Api\Inventory\StockReportController;
use App\Http\Controllers\Api\Inventory\WarehouseController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\Purchase\PurchasePipelineController;
use App\Http\Controllers\Api\Purchase\SupplierController;
use App\Http\Controllers\Api\Sales\CustomerController;
use App\Http\Controllers\Api\Sales\SalesOrderController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('dashboard/ping', [DashboardController::class, 'ping']);
        Route::get('dashboard/summary', [DashboardController::class, 'summary']);
        Route::get('notifications/unread', [NotificationController::class, 'unread']);

        Route::prefix('inventory')->group(function () {
            Route::get('items', [ItemController::class, 'index']);
            Route::post('items', [ItemController::class, 'store']);
            Route::post('items/import', [ItemController::class, 'import']);
            Route::get('items/template', [ItemController::class, 'downloadTemplate']);
            Route::get('items/export-pdf', [ItemController::class, 'exportPdf']);
            Route::put('items/{item}', [ItemController::class, 'update']);
            Route::delete('items/{item}', [ItemController::class, 'destroy']);

            Route::get('warehouses', [WarehouseController::class, 'index']);
            Route::post('warehouses', [WarehouseController::class, 'store']);
            Route::put('warehouses/{warehouse}', [WarehouseController::class, 'update']);
            Route::delete('warehouses/{warehouse}', [WarehouseController::class, 'destroy']);

            Route::get('movements', [StockMovementController::class, 'index']);
            Route::get('movements/form-options', [StockMovementController::class, 'formOptions']);
            Route::post('movements', [StockMovementController::class, 'store']);

            Route::get('reports/summary', [StockReportController::class, 'summary']);
            Route::get('reports/movement', [StockReportController::class, 'movement']);
            Route::get('reports/low-stock', [StockReportController::class, 'lowStock']);
            Route::get('reports/valuation', [StockReportController::class, 'valuation']);
        });

        Route::prefix('sales')->group(function () {
            Route::get('customers', [CustomerController::class, 'index']);
            Route::post('customers', [CustomerController::class, 'store']);
            Route::put('customers/{customer}', [CustomerController::class, 'update']);
            Route::delete('customers/{customer}', [CustomerController::class, 'destroy']);

            Route::get('orders', [SalesOrderController::class, 'index']);
            Route::get('orders/form-options', [SalesOrderController::class, 'formOptions']);
            Route::get('orders/{salesOrder}', [SalesOrderController::class, 'show']);
            Route::post('orders', [SalesOrderController::class, 'store']);
            Route::put('orders/{salesOrder}', [SalesOrderController::class, 'update']);
            Route::patch('orders/{salesOrder}/confirm', [SalesOrderController::class, 'confirm']);
            Route::delete('orders/{salesOrder}', [SalesOrderController::class, 'destroy']);
        });

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
