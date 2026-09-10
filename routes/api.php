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
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\Purchase\GoodsReceiptNoteController;
use App\Http\Controllers\Api\Purchase\PurchaseOrderController;
use App\Http\Controllers\Api\Purchase\PurchasePipelineController;
use App\Http\Controllers\Api\Purchase\PurchaseRequestController;
use App\Http\Controllers\Api\Purchase\RfqPortalController;
use App\Http\Controllers\Api\Purchase\SupplierController;
use App\Http\Controllers\Api\Purchase\SupplierInvoiceController as PurchaseInvoiceController;
use App\Http\Controllers\Api\Purchase\SupplierPaymentController as PurchasePaymentController;
use App\Http\Controllers\Api\Purchase\SupplierQuoteController;
use App\Http\Controllers\Api\Sales\CustomerController;
use App\Http\Controllers\Api\Sales\DeliveryNoteController;
use App\Http\Controllers\Api\Sales\PaymentReceiptController;
use App\Http\Controllers\Api\Sales\SalesInvoiceController;
use App\Http\Controllers\Api\Sales\SalesOrderController;
use App\Http\Controllers\Api\Settings\CompanyController;
use App\Http\Controllers\Api\Settings\IntegrationController;
use App\Http\Controllers\Api\Settings\MailAccountController;
use App\Http\Controllers\Api\Settings\ProjectController;
use App\Http\Controllers\Api\Settings\UserController;
use App\Http\Controllers\Api\Settings\VatController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('login', [AuthController::class, 'login']);

    // The public quote portal. No auth by design — the invitation token is the
    // credential, and the controller resolves it on every call. Sits outside
    // the auth:sanctum group for that reason, not by oversight.
    Route::get('rfq/{token}', [RfqPortalController::class, 'show']);
    Route::post('rfq/{token}', [RfqPortalController::class, 'submit']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('dashboard/ping', [DashboardController::class, 'ping']);
        Route::get('dashboard/summary', [DashboardController::class, 'summary']);
        Route::get('notifications/unread', [NotificationController::class, 'unread']);
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);

        // Every user's own profile — not behind role:Admin, unlike settings.
        Route::get('profile', [ProfileController::class, 'show']);
        Route::put('profile', [ProfileController::class, 'update']);
        Route::put('profile/password', [ProfileController::class, 'updatePassword']);
        Route::delete('profile', [ProfileController::class, 'destroy']);
        Route::post('profile/verification-notification', [ProfileController::class, 'sendVerificationNotification']);

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
            Route::put('delivery-notes/{deliveryNote}', [DeliveryNoteController::class, 'update']);
            Route::patch('delivery-notes/{deliveryNote}/dispatch', [DeliveryNoteController::class, 'dispatchNote']);
            Route::delete('delivery-notes/{deliveryNote}', [DeliveryNoteController::class, 'destroy']);

            Route::get('invoices', [SalesInvoiceController::class, 'index']);
            Route::get('invoices/form-options', [SalesInvoiceController::class, 'formOptions']);
            Route::get('invoices/{salesInvoice}', [SalesInvoiceController::class, 'show']);
            Route::post('invoices', [SalesInvoiceController::class, 'store']);
            Route::put('invoices/{salesInvoice}', [SalesInvoiceController::class, 'update']);
            Route::delete('invoices/{salesInvoice}', [SalesInvoiceController::class, 'destroy']);

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

        // Settings are Admin-only, matching the `role:Admin` group the Blade
        // settings pages live in.
        Route::prefix('settings')->middleware('role:Admin')->group(function () {
            Route::get('companies', [CompanyController::class, 'index']);
            Route::post('companies', [CompanyController::class, 'store']);
            Route::put('companies/{company}', [CompanyController::class, 'update']);
            Route::delete('companies/{company}', [CompanyController::class, 'destroy']);
            Route::post('companies/{company}/departments', [CompanyController::class, 'storeDepartment']);
            Route::put('companies/{company}/departments/{department}', [CompanyController::class, 'updateDepartment']);
            Route::delete('companies/{company}/departments/{department}', [CompanyController::class, 'destroyDepartment']);

            // `projects/import` and `projects/template` must precede
            // `projects/{project}` or the wildcard swallows them.
            Route::get('projects', [ProjectController::class, 'index']);
            Route::post('projects/import', [ProjectController::class, 'import']);
            Route::get('projects/template', [ProjectController::class, 'downloadTemplate']);
            Route::post('projects', [ProjectController::class, 'store']);
            Route::put('projects/{project}', [ProjectController::class, 'update']);
            Route::delete('projects/{project}', [ProjectController::class, 'destroy']);
            Route::post('projects/{project}/locations', [ProjectController::class, 'storeLocation']);
            Route::put('projects/{project}/locations/{location}', [ProjectController::class, 'updateLocation']);
            Route::delete('projects/{project}/locations/{location}', [ProjectController::class, 'destroyLocation']);

            Route::get('users', [UserController::class, 'index']);
            Route::post('users', [UserController::class, 'store']);
            Route::put('users/{user}', [UserController::class, 'update']);

            Route::get('integrations/whatsapp', [IntegrationController::class, 'whatsapp']);
            Route::put('integrations/whatsapp', [IntegrationController::class, 'updateWhatsapp']);
            Route::post('integrations/whatsapp/test', [IntegrationController::class, 'testConnection']);
            Route::post('integrations/whatsapp/test-message', [IntegrationController::class, 'sendTestMessage']);

            Route::get('mail-accounts', [MailAccountController::class, 'index']);
            Route::get('mail-accounts/{mailAccount}', [MailAccountController::class, 'show']);
            Route::post('mail-accounts', [MailAccountController::class, 'store']);
            Route::put('mail-accounts/{mailAccount}', [MailAccountController::class, 'update']);
            Route::delete('mail-accounts/{mailAccount}', [MailAccountController::class, 'destroy']);
            Route::patch('mail-accounts/{mailAccount}/toggle', [MailAccountController::class, 'toggleEnabled']);
            Route::post('mail-accounts/{mailAccount}/test', [MailAccountController::class, 'testConnection']);
            Route::post('mail-accounts/{mailAccount}/send-test', [MailAccountController::class, 'sendTestEmail']);

            Route::get('vat', [VatController::class, 'show']);
            Route::put('vat', [VatController::class, 'update']);
        });

        Route::prefix('purchase')->group(function () {
            // `pipeline/{purchaseRequest}/…` action paths sit under the
            // wildcard, so they must come after the bare show route but their
            // own suffixes keep them distinct.
            Route::get('pipeline', [PurchasePipelineController::class, 'index']);
            Route::get('pipeline/{purchaseRequest}', [PurchasePipelineController::class, 'show']);
            Route::get('pipeline/{purchaseRequest}/form-options', [PurchasePipelineController::class, 'formOptions']);
            Route::post('pipeline/{purchaseRequest}/suppliers', [PurchasePipelineController::class, 'selectSuppliers']);
            Route::post('pipeline/{purchaseRequest}/send-invitations', [PurchasePipelineController::class, 'sendInvitations']);
            Route::post('pipeline/{purchaseRequest}/lpo', [PurchasePipelineController::class, 'generateLpo']);
            Route::post('pipeline/{purchaseRequest}/signature', [PurchasePipelineController::class, 'storeSignature']);
            Route::post('pipeline/{purchaseRequest}/reject', [PurchasePipelineController::class, 'reject']);

            // The MPR create/edit forms. `requests/form-options` must precede
            // the `{purchaseRequest}` routes or the wildcard swallows it.
            Route::get('requests/form-options', [PurchaseRequestController::class, 'formOptions']);
            Route::post('requests', [PurchaseRequestController::class, 'store']);
            Route::get('requests/{purchaseRequest}/edit', [PurchaseRequestController::class, 'edit']);
            Route::get('requests/{purchaseRequest}', [PurchaseRequestController::class, 'show']);
            Route::put('requests/{purchaseRequest}', [PurchaseRequestController::class, 'update']);
            Route::delete('requests/{purchaseRequest}', [PurchaseRequestController::class, 'destroy']);

            // The quotes workspace: one page for "view quotes" and "compare &
            // award", as in Blade.
            Route::get('requests/{purchaseRequest}/quotes', [SupplierQuoteController::class, 'index']);
            Route::post('requests/{purchaseRequest}/quotes/items/{quoteItem}/award', [SupplierQuoteController::class, 'award']);
            Route::post('requests/{purchaseRequest}/quotes/items/{quoteItem}/unaward', [SupplierQuoteController::class, 'unaward']);
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

            // `invoices/form-options` must precede `invoices/{supplierInvoice}`.
            Route::get('invoices', [PurchaseInvoiceController::class, 'index']);
            Route::get('invoices/form-options', [PurchaseInvoiceController::class, 'formOptions']);
            Route::get('invoices/{supplierInvoice}', [PurchaseInvoiceController::class, 'show']);
            Route::post('invoices', [PurchaseInvoiceController::class, 'store']);
            Route::put('invoices/{supplierInvoice}', [PurchaseInvoiceController::class, 'update']);
            Route::delete('invoices/{supplierInvoice}', [PurchaseInvoiceController::class, 'destroy']);

            // `payments/form-options` must precede `payments/{supplierPayment}`.
            Route::get('payments', [PurchasePaymentController::class, 'index']);
            Route::get('payments/form-options', [PurchasePaymentController::class, 'formOptions']);
            Route::get('payments/{supplierPayment}', [PurchasePaymentController::class, 'show']);
            Route::post('payments', [PurchasePaymentController::class, 'store']);
            Route::put('payments/{supplierPayment}', [PurchasePaymentController::class, 'update']);
            Route::delete('payments/{supplierPayment}', [PurchasePaymentController::class, 'destroy']);
        });
    });
});
