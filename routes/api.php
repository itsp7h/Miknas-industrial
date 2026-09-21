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
use App\Http\Controllers\Api\Settings\FinanceController;
use App\Http\Controllers\Api\Settings\IntegrationController;
use App\Http\Controllers\Api\Settings\ItemCategoryController;
use App\Http\Controllers\Api\Settings\LpoNumberingController;
use App\Http\Controllers\Api\Settings\MailAccountController;
use App\Http\Controllers\Api\Settings\ProjectController;
use App\Http\Controllers\Api\Settings\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('login', [AuthController::class, 'login']);

    // The guest half of the password-reset flow. Breeze's own POST routes
    // still exist and still behave as Breeze intends; these are the same
    // policy answering in JSON, for the React pages that replaced its forms.
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);

    // The public quote portal. No auth by design — the invitation token is the
    // credential, and the controller resolves it on every call. Sits outside
    // the auth:sanctum group for that reason, not by oversight.
    Route::get('rfq/{token}', [RfqPortalController::class, 'show']);
    Route::post('rfq/{token}', [RfqPortalController::class, 'submit']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('confirm-password', [AuthController::class, 'confirmPassword']);
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
            Route::get('items', [ItemController::class, 'index'])->middleware('permission:raw-materials.view|finished-goods.view');
            Route::post('items', [ItemController::class, 'store'])->middleware('permission:raw-materials.create|finished-goods.create');
            Route::post('items/import', [ItemController::class, 'import'])->middleware('permission:raw-materials.create|finished-goods.create');
            Route::get('items/template', [ItemController::class, 'downloadTemplate'])->middleware('permission:raw-materials.view|finished-goods.view');
            Route::get('items/export-pdf', [ItemController::class, 'exportPdf'])->middleware('permission:raw-materials.view|finished-goods.view');
            Route::put('items/{item}', [ItemController::class, 'update'])->middleware('permission:raw-materials.edit|finished-goods.edit');
            Route::delete('items/{item}', [ItemController::class, 'destroy'])->middleware('permission:raw-materials.delete|finished-goods.delete');

            Route::get('warehouses', [WarehouseController::class, 'index'])->middleware('permission:warehouses.view');
            Route::get('warehouses/{warehouse}', [WarehouseController::class, 'show'])->middleware('permission:warehouses.view');
            Route::post('warehouses', [WarehouseController::class, 'store'])->middleware('permission:warehouses.create');
            Route::put('warehouses/{warehouse}', [WarehouseController::class, 'update'])->middleware('permission:warehouses.edit');
            Route::delete('warehouses/{warehouse}', [WarehouseController::class, 'destroy'])->middleware('permission:warehouses.delete');

            Route::get('movements', [StockMovementController::class, 'index'])->middleware('permission:stock-movements.view');
            Route::get('movements/form-options', [StockMovementController::class, 'formOptions'])->middleware('permission:stock-movements.create');
            Route::post('movements', [StockMovementController::class, 'store'])->middleware('permission:stock-movements.create');

            Route::get('reports/movement', [StockReportController::class, 'movement'])->middleware('permission:movement-report.view');
            Route::get('reports/low-stock', [StockReportController::class, 'lowStock'])->middleware('permission:low-stock.view');
            Route::get('reports/valuation', [StockReportController::class, 'valuation'])->middleware('permission:valuation.view');
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
        // Users and Integrations are Admin's alone — they carry no permission
        // name at all, so nobody can be granted them. The rest of Settings is
        // grantable per tab like everything else.
        Route::prefix('settings')->group(function () {
            Route::get('companies', [CompanyController::class, 'index'])->middleware('permission:companies.view');
            Route::post('companies', [CompanyController::class, 'store'])->middleware('permission:companies.create');
            Route::put('companies/{company}', [CompanyController::class, 'update'])->middleware('permission:companies.edit');
            Route::delete('companies/{company}', [CompanyController::class, 'destroy'])->middleware('permission:companies.delete');
            Route::post('companies/{company}/departments', [CompanyController::class, 'storeDepartment'])->middleware('permission:companies.create');
            Route::put('companies/{company}/departments/{department}', [CompanyController::class, 'updateDepartment'])->middleware('permission:companies.edit');
            Route::delete('companies/{company}/departments/{department}', [CompanyController::class, 'destroyDepartment'])->middleware('permission:companies.delete');

            // `projects/import` and `projects/template` must precede
            // `projects/{project}` or the wildcard swallows them.
            Route::get('projects', [ProjectController::class, 'index'])->middleware('permission:projects.view');
            Route::post('projects/import', [ProjectController::class, 'import'])->middleware('permission:projects.create');
            Route::get('projects/template', [ProjectController::class, 'downloadTemplate'])->middleware('permission:projects.view');
            Route::post('projects', [ProjectController::class, 'store'])->middleware('permission:projects.create');
            Route::put('projects/{project}', [ProjectController::class, 'update'])->middleware('permission:projects.edit');
            Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->middleware('permission:projects.delete');
            Route::post('projects/{project}/locations', [ProjectController::class, 'storeLocation'])->middleware('permission:projects.create');
            Route::put('projects/{project}/locations/{location}', [ProjectController::class, 'updateLocation'])->middleware('permission:projects.edit');
            Route::delete('projects/{project}/locations/{location}', [ProjectController::class, 'destroyLocation'])->middleware('permission:projects.delete');

            // Item sections — "Raw Materials / Chemical Materials".
            Route::get('item-categories', [ItemCategoryController::class, 'index'])->middleware('permission:item-categories.view');
            Route::post('item-categories', [ItemCategoryController::class, 'store'])->middleware('permission:item-categories.create');
            Route::put('item-categories/{itemCategory}', [ItemCategoryController::class, 'update'])->middleware('permission:item-categories.edit');
            Route::delete('item-categories/{itemCategory}', [ItemCategoryController::class, 'destroy'])->middleware('permission:item-categories.delete');

            Route::get('users', [UserController::class, 'index'])->middleware('role:Admin');
            Route::post('users', [UserController::class, 'store'])->middleware('role:Admin');
            Route::put('users/{user}', [UserController::class, 'update'])->middleware('role:Admin');
            // The suffix keeps this clear of the `users/{user}` wildcard above.
            Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->middleware('role:Admin');

            Route::get('integrations/whatsapp', [IntegrationController::class, 'whatsapp'])->middleware('role:Admin');
            Route::put('integrations/whatsapp', [IntegrationController::class, 'updateWhatsapp'])->middleware('role:Admin');
            Route::post('integrations/whatsapp/test', [IntegrationController::class, 'testConnection'])->middleware('role:Admin');
            Route::post('integrations/whatsapp/test-message', [IntegrationController::class, 'sendTestMessage'])->middleware('role:Admin');

            Route::get('mail-accounts', [MailAccountController::class, 'index'])->middleware('role:Admin');
            Route::get('mail-accounts/{mailAccount}', [MailAccountController::class, 'show'])->middleware('role:Admin');
            Route::post('mail-accounts', [MailAccountController::class, 'store'])->middleware('role:Admin');
            Route::put('mail-accounts/{mailAccount}', [MailAccountController::class, 'update'])->middleware('role:Admin');
            Route::delete('mail-accounts/{mailAccount}', [MailAccountController::class, 'destroy'])->middleware('role:Admin');
            Route::patch('mail-accounts/{mailAccount}/toggle', [MailAccountController::class, 'toggleEnabled']);
            Route::post('mail-accounts/{mailAccount}/test', [MailAccountController::class, 'testConnection'])->middleware('role:Admin');
            Route::post('mail-accounts/{mailAccount}/send-test', [MailAccountController::class, 'sendTestEmail'])->middleware('role:Admin');

            // How each company's LPOs are numbered.
            Route::get('lpo-numbering', [LpoNumberingController::class, 'index'])->middleware('permission:settings.view');
            Route::put('lpo-numbering', [LpoNumberingController::class, 'update'])->middleware('permission:settings.edit');

            // VAT and the display currency — one subject, one page.
            Route::get('finance', [FinanceController::class, 'show'])->middleware('permission:finance.view');
            Route::put('finance', [FinanceController::class, 'update'])->middleware('permission:finance.edit');
        });

        Route::prefix('purchase')->group(function () {
            // `pipeline/{purchaseRequest}/…` action paths sit under the
            // wildcard, so they must come after the bare show route but their
            // own suffixes keep them distinct.
            Route::get('pipeline', [PurchasePipelineController::class, 'index'])->middleware('permission:pipeline.view');
            Route::get('pipeline/{purchaseRequest}', [PurchasePipelineController::class, 'show'])->middleware('permission:pipeline.view');
            Route::get('pipeline/{purchaseRequest}/form-options', [PurchasePipelineController::class, 'formOptions'])->middleware('permission:pipeline.view');
            Route::post('pipeline/{purchaseRequest}/suppliers', [PurchasePipelineController::class, 'selectSuppliers']);
            Route::post('pipeline/{purchaseRequest}/send-invitations', [PurchasePipelineController::class, 'sendInvitations']);
            Route::post('pipeline/{purchaseRequest}/lpo', [PurchasePipelineController::class, 'generateLpo']);
            Route::post('pipeline/{purchaseRequest}/signature', [PurchasePipelineController::class, 'storeSignature']);
            Route::post('pipeline/{purchaseRequest}/reject', [PurchasePipelineController::class, 'reject']);

            // The MPR create/edit forms. `requests/form-options` must precede
            // the `{purchaseRequest}` routes or the wildcard swallows it.
            Route::get('requests/form-options', [PurchaseRequestController::class, 'formOptions'])->middleware('permission:pipeline.create|pipeline.edit');
            Route::post('requests', [PurchaseRequestController::class, 'store'])->middleware('permission:pipeline.create');
            Route::get('requests/{purchaseRequest}/edit', [PurchaseRequestController::class, 'edit'])->middleware('permission:pipeline.edit');
            Route::get('requests/{purchaseRequest}', [PurchaseRequestController::class, 'show'])->middleware('permission:pipeline.view');
            Route::put('requests/{purchaseRequest}', [PurchaseRequestController::class, 'update'])->middleware('permission:pipeline.edit');
            Route::delete('requests/{purchaseRequest}', [PurchaseRequestController::class, 'destroy'])->middleware('permission:pipeline.delete');

            // The quotes workspace: one page for "view quotes" and "compare &
            // award", as in Blade.
            Route::get('requests/{purchaseRequest}/quotes', [SupplierQuoteController::class, 'index']);
            Route::post('requests/{purchaseRequest}/quotes/items/{quoteItem}/award', [SupplierQuoteController::class, 'award']);
            Route::post('requests/{purchaseRequest}/quotes/items/{quoteItem}/unaward', [SupplierQuoteController::class, 'unaward']);
            Route::get('suppliers', [SupplierController::class, 'index'])->middleware('permission:suppliers.view');
            Route::post('suppliers', [SupplierController::class, 'store'])->middleware('permission:suppliers.create');
            Route::post('suppliers/import', [SupplierController::class, 'import'])->middleware('permission:suppliers.create');
            Route::get('suppliers/template', [SupplierController::class, 'downloadTemplate'])->middleware('permission:suppliers.view');
            Route::get('suppliers/export-pdf', [SupplierController::class, 'exportPdf'])->middleware('permission:suppliers.view');
            Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])->middleware('permission:suppliers.edit');
            Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])->middleware('permission:suppliers.delete');

            // `orders/form-options` must precede `orders/{purchaseOrder}` or the
            // wildcard swallows it.
            Route::get('orders', [PurchaseOrderController::class, 'index'])->middleware('permission:purchase-orders.view');
            Route::get('orders/form-options', [PurchaseOrderController::class, 'formOptions'])->middleware('permission:purchase-orders.create|purchase-orders.edit');
            Route::get('orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->middleware('permission:purchase-orders.view');
            Route::post('orders', [PurchaseOrderController::class, 'store'])->middleware('permission:purchase-orders.create');
            Route::put('orders/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->middleware('permission:purchase-orders.edit');
            Route::post('orders/{purchaseOrder}/send', [PurchaseOrderController::class, 'send'])->middleware('permission:purchase-orders.edit');
            Route::delete('orders/{purchaseOrder}', [PurchaseOrderController::class, 'destroy'])->middleware('permission:purchase-orders.delete');

            // `grns/form-options` must precede `grns/{grn}` or the wildcard eats it.
            Route::get('grns', [GoodsReceiptNoteController::class, 'index'])->middleware('permission:goods-receipts.view');
            Route::get('grns/form-options', [GoodsReceiptNoteController::class, 'formOptions'])->middleware('permission:goods-receipts.create');
            Route::get('grns/{grn}', [GoodsReceiptNoteController::class, 'show'])->middleware('permission:goods-receipts.view');
            Route::post('grns', [GoodsReceiptNoteController::class, 'store'])->middleware('permission:goods-receipts.create');
            Route::patch('grns/{grn}/confirm', [GoodsReceiptNoteController::class, 'confirm'])->middleware('permission:goods-receipts.edit');
            Route::delete('grns/{grn}', [GoodsReceiptNoteController::class, 'destroy'])->middleware('permission:goods-receipts.delete');

            // `invoices/form-options` must precede `invoices/{supplierInvoice}`.
            Route::get('invoices', [PurchaseInvoiceController::class, 'index'])->middleware('permission:supplier-invoices.view');
            Route::get('invoices/form-options', [PurchaseInvoiceController::class, 'formOptions'])->middleware('permission:supplier-invoices.create|supplier-invoices.edit');
            Route::get('invoices/{supplierInvoice}', [PurchaseInvoiceController::class, 'show'])->middleware('permission:supplier-invoices.view');
            Route::post('invoices', [PurchaseInvoiceController::class, 'store'])->middleware('permission:supplier-invoices.create');
            Route::put('invoices/{supplierInvoice}', [PurchaseInvoiceController::class, 'update'])->middleware('permission:supplier-invoices.edit');
            Route::delete('invoices/{supplierInvoice}', [PurchaseInvoiceController::class, 'destroy'])->middleware('permission:supplier-invoices.delete');

            // `payments/form-options` must precede `payments/{supplierPayment}`.
            Route::get('payments', [PurchasePaymentController::class, 'index'])->middleware('permission:supplier-payments.view');
            Route::get('payments/form-options', [PurchasePaymentController::class, 'formOptions'])->middleware('permission:supplier-payments.create|supplier-payments.edit');
            Route::get('payments/{supplierPayment}', [PurchasePaymentController::class, 'show'])->middleware('permission:supplier-payments.view');
            Route::post('payments', [PurchasePaymentController::class, 'store'])->middleware('permission:supplier-payments.create');
            Route::put('payments/{supplierPayment}', [PurchasePaymentController::class, 'update'])->middleware('permission:supplier-payments.edit');
            Route::delete('payments/{supplierPayment}', [PurchasePaymentController::class, 'destroy'])->middleware('permission:supplier-payments.delete');
        });
    });
});
