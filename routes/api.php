<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\Inventory\ItemController;
use App\Http\Controllers\Api\Inventory\StockMovementController;
use App\Http\Controllers\Api\Inventory\StockReportController;
use App\Http\Controllers\Api\Inventory\WarehouseController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\Production\BillOfMaterialController;
use App\Http\Controllers\Api\Production\MaterialIssueController;
use App\Http\Controllers\Api\Production\ProductionOrderController;
use App\Http\Controllers\Api\Production\ProductionOutputController;
use App\Http\Controllers\Api\Purchase\GoodsReceiptNoteController;
use App\Http\Controllers\Api\Purchase\PurchaseOrderController;
use App\Http\Controllers\Api\Purchase\PurchasePipelineController;
use App\Http\Controllers\Api\Purchase\SupplierController;
use App\Http\Controllers\Api\Sales\CustomerController;
use App\Http\Controllers\Api\Sales\DeliveryNoteController;
use App\Http\Controllers\Api\Sales\PaymentReceiptController;
use App\Http\Controllers\Api\Sales\SalesInvoiceController;
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
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);

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

            Route::get('delivery-notes', [DeliveryNoteController::class, 'index']);
            Route::get('delivery-notes/form-options', [DeliveryNoteController::class, 'formOptions']);
            Route::get('delivery-notes/{deliveryNote}', [DeliveryNoteController::class, 'show']);
            Route::post('delivery-notes', [DeliveryNoteController::class, 'store']);
            Route::patch('delivery-notes/{deliveryNote}/dispatch', [DeliveryNoteController::class, 'dispatchNote']);

            Route::get('invoices', [SalesInvoiceController::class, 'index']);
            Route::get('invoices/form-options', [SalesInvoiceController::class, 'formOptions']);
            Route::get('invoices/{salesInvoice}', [SalesInvoiceController::class, 'show']);
            Route::post('invoices', [SalesInvoiceController::class, 'store']);

            Route::get('payments', [PaymentReceiptController::class, 'index']);
            Route::get('payments/form-options', [PaymentReceiptController::class, 'formOptions']);
            Route::post('payments', [PaymentReceiptController::class, 'store']);
        });

        Route::prefix('production')->group(function () {
            Route::get('orders', [ProductionOrderController::class, 'index']);
            Route::get('orders/form-options', [ProductionOrderController::class, 'formOptions']);
            Route::get('orders/{productionOrder}', [ProductionOrderController::class, 'show']);
            Route::post('orders', [ProductionOrderController::class, 'store']);
            Route::put('orders/{productionOrder}', [ProductionOrderController::class, 'update']);
            Route::patch('orders/{productionOrder}/start', [ProductionOrderController::class, 'start']);
            Route::patch('orders/{productionOrder}/complete', [ProductionOrderController::class, 'complete']);
            Route::delete('orders/{productionOrder}', [ProductionOrderController::class, 'destroy']);

            Route::get('bom', [BillOfMaterialController::class, 'index']);
            Route::get('bom/form-options', [BillOfMaterialController::class, 'formOptions']);
            Route::post('bom', [BillOfMaterialController::class, 'store']);
            Route::put('bom/{bom}', [BillOfMaterialController::class, 'update']);
            Route::delete('bom/{bom}', [BillOfMaterialController::class, 'destroy']);

            Route::get('material-issues', [MaterialIssueController::class, 'index']);
            Route::get('material-issues/form-options', [MaterialIssueController::class, 'formOptions']);
            Route::post('material-issues', [MaterialIssueController::class, 'store']);

            Route::get('outputs', [ProductionOutputController::class, 'index']);
            Route::get('outputs/form-options', [ProductionOutputController::class, 'formOptions']);
            Route::post('outputs', [ProductionOutputController::class, 'store']);
        });

        Route::prefix('purchase')->group(function () {
            Route::get('pipeline', [PurchasePipelineController::class, 'index']);
            Route::get('pipeline/{purchaseRequest}', [PurchasePipelineController::class, 'show']);
            Route::get('suppliers', [SupplierController::class, 'index']);
            Route::post('suppliers', [SupplierController::class, 'store']);
            Route::post('suppliers/import', [SupplierController::class, 'import']);
            Route::get('suppliers/template', [SupplierController::class, 'downloadTemplate']);
            Route::get('suppliers/export-pdf', [SupplierController::class, 'exportPdf']);
            Route::put('suppliers/{supplier}', [SupplierController::class, 'update']);
            Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy']);

            // `orders/form-options` must precede `orders/{purchaseOrder}` or the
            // wildcard swallows it.
            Route::get('orders', [PurchaseOrderController::class, 'index']);
            Route::get('orders/form-options', [PurchaseOrderController::class, 'formOptions']);
            Route::get('orders/{purchaseOrder}', [PurchaseOrderController::class, 'show']);
            Route::post('orders', [PurchaseOrderController::class, 'store']);
            Route::put('orders/{purchaseOrder}', [PurchaseOrderController::class, 'update']);
            Route::delete('orders/{purchaseOrder}', [PurchaseOrderController::class, 'destroy']);

            // `grns/form-options` must precede `grns/{grn}` or the wildcard eats it.
            Route::get('grns', [GoodsReceiptNoteController::class, 'index']);
            Route::get('grns/form-options', [GoodsReceiptNoteController::class, 'formOptions']);
            Route::get('grns/{grn}', [GoodsReceiptNoteController::class, 'show']);
            Route::post('grns', [GoodsReceiptNoteController::class, 'store']);
            Route::patch('grns/{grn}/confirm', [GoodsReceiptNoteController::class, 'confirm']);
            Route::delete('grns/{grn}', [GoodsReceiptNoteController::class, 'destroy']);
        });
    });
});
