<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Purchase\GoodsReceiptNoteController;
use App\Http\Controllers\Purchase\PurchaseOrderController;
use App\Http\Controllers\Purchase\PurchaseRequestController;
use App\Http\Controllers\Purchase\SupplierInvoiceController;
use App\Http\Controllers\Purchase\SupplierPaymentController;
use App\Http\Controllers\Purchase\PurchasePipelineController;
use App\Http\Controllers\Purchase\PurchaseSignatureController;
use App\Http\Controllers\Purchase\RfqController;
use App\Http\Controllers\Purchase\SupplierQuoteController;
use App\Http\Controllers\Purchase\RfqPortalController;
use App\Http\Controllers\Inventory\ItemController;
use App\Http\Controllers\Inventory\StockMovementController;
use App\Http\Controllers\Inventory\StockReportController;
use App\Http\Controllers\Inventory\WarehouseController;
use App\Http\Controllers\Production\BillOfMaterialController;
use App\Http\Controllers\Production\MaterialIssueController;
use App\Http\Controllers\Production\ProductionOrderController;
use App\Http\Controllers\Production\ProductionOutputController;
use App\Http\Controllers\Sales\CustomerController;
use App\Http\Controllers\Sales\DeliveryNoteController;
use App\Http\Controllers\Sales\PaymentReceiptController;
use App\Http\Controllers\Sales\SalesInvoiceController;
use App\Http\Controllers\Sales\SalesOrderController;
use App\Http\Controllers\MailAccountController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\Settings\ProjectSettingController;
use App\Http\Controllers\Settings\UserManagementController;
use App\Http\Controllers\Settings\VatSettingController;
use App\Models\Settings\Location;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Public RFQ portal — no auth required
Route::get('/rfq/{token}',  [RfqPortalController::class, 'show'])->name('rfq.show');
Route::post('/rfq/{token}', [RfqPortalController::class, 'submit'])->name('rfq.submit');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/notifications/unread',  fn() => response()->json([
        'count' => auth()->user()->unreadNotifications()->count(),
        'items' => auth()->user()->unreadNotifications()->latest()->take(10)->get()->map(fn($n) => [
            'id'      => $n->id,
            'message' => $n->data['message'] ?? '',
            'go_url'  => route('notifications.go', $n->id),
            'ago'     => $n->created_at->diffForHumans(),
        ]),
    ]))->name('notifications.unread');

    Route::get('/notifications/{id}/go', function (string $id) {
        $n = auth()->user()->notifications()->findOrFail($id);
        $n->markAsRead();
        $dest = $n->data['url'] ?? route('dashboard');
        return redirect($dest);
    })->name('notifications.go');

    Route::post('/notifications/read-all', fn() => response()->json(
        tap(auth()->user()->unreadNotifications()->update(['read_at' => now()]))
    ))->name('notifications.read-all');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Purchase Module
    Route::prefix('purchase')->name('purchase.')->group(function () {
        // Pipeline — the index view was replaced by the React board at
        // /app/purchase/pipeline; this route is now a safety net for anyone with the
        // old URL bookmarked. The per-request detail page stays Blade.
        Route::redirect('pipeline', '/app/purchase/pipeline')->name('pipeline.index');
        Route::get('pipeline/{purchaseRequest}', [PurchasePipelineController::class, 'show'])->name('pipeline.show');

        // GM Signature
        Route::get('requests/{purchaseRequest}/sign',  [PurchaseSignatureController::class, 'show'])->name('requests.sign');
        Route::post('requests/{purchaseRequest}/sign', [PurchaseSignatureController::class, 'store'])->name('requests.sign.store');

        // RFQ
        Route::post('requests/{purchaseRequest}/rfq/select',   [RfqController::class, 'selectSuppliers'])->name('requests.rfq.select');
        Route::post('requests/{purchaseRequest}/rfq/send-all', [RfqController::class, 'sendAll'])->name('requests.rfq.send-all');
        Route::get('requests/{purchaseRequest}/rfq',  [RfqController::class, 'show'])->name('requests.rfq');
        Route::post('requests/{purchaseRequest}/rfq', [RfqController::class, 'store'])->name('requests.rfq.store');

        // Quotes
        Route::get('requests/{purchaseRequest}/quotes',                    [SupplierQuoteController::class, 'index'])->name('requests.quotes');
        Route::get('requests/{purchaseRequest}/compare',                   [SupplierQuoteController::class, 'compare'])->name('requests.compare');
        Route::post('requests/{purchaseRequest}/quotes/items/{quoteItem}/award',   [SupplierQuoteController::class, 'awardItem'])->name('requests.quotes.items.award');
        Route::post('requests/{purchaseRequest}/quotes/items/{quoteItem}/unaward', [SupplierQuoteController::class, 'unawardItem'])->name('requests.quotes.items.unaward');

        Route::resource('requests', PurchaseRequestController::class)->parameters(['requests' => 'purchaseRequest']);
        Route::patch('requests/{purchaseRequest}/approve', [PurchaseRequestController::class, 'approve'])->name('requests.approve');
        Route::patch('requests/{purchaseRequest}/reject', [PurchaseRequestController::class, 'reject'])->name('requests.reject');
        Route::get('requests/{purchaseRequest}/print', [PurchaseRequestController::class, 'print'])->name('requests.print');
        Route::post('requests/{purchaseRequest}/generate-lpo', [PurchaseOrderController::class, 'generateFromRequest'])->name('requests.generate-lpo');
        Route::get('orders/{order}/print', [PurchaseOrderController::class, 'print'])->name('orders.print');
        Route::get('orders/{order}/pdf', [PurchaseOrderController::class, 'pdf'])->name('orders.pdf');
        Route::resource('orders', PurchaseOrderController::class);
        Route::resource('grns', GoodsReceiptNoteController::class);
        Route::patch('grns/{grn}/confirm', [GoodsReceiptNoteController::class, 'confirm'])->name('grns.confirm');
        Route::resource('invoices', SupplierInvoiceController::class);
        Route::resource('payments', SupplierPaymentController::class);
    });

    // Inventory Module
    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::post('items/import',    [ItemController::class, 'import'])->name('items.import');
        Route::get('items/template',   [ItemController::class, 'downloadTemplate'])->name('items.template');
        Route::get('items/export-pdf', [ItemController::class, 'exportPdf'])->name('items.export-pdf');
        Route::resource('items', ItemController::class);
        Route::resource('warehouses', WarehouseController::class);
        Route::resource('movements', StockMovementController::class);
        Route::get('reports/summary', [StockReportController::class, 'summary'])->name('reports.summary');
        Route::get('reports/movement', [StockReportController::class, 'movement'])->name('reports.movement');
        Route::get('reports/low-stock', [StockReportController::class, 'lowStock'])->name('reports.low-stock');
        Route::get('reports/valuation', [StockReportController::class, 'valuation'])->name('reports.valuation');
    });

    // Production Module
    Route::prefix('production')->name('production.')->group(function () {
        Route::resource('orders', ProductionOrderController::class);
        Route::patch('orders/{order}/start', [ProductionOrderController::class, 'start'])->name('orders.start');
        Route::patch('orders/{order}/complete', [ProductionOrderController::class, 'complete'])->name('orders.complete');
        Route::resource('bom', BillOfMaterialController::class);
        Route::resource('material-issues', MaterialIssueController::class);
        Route::resource('outputs', ProductionOutputController::class);
    });

    // Sales Module
    Route::prefix('sales')->name('sales.')->group(function () {
        Route::resource('customers', CustomerController::class);
        Route::resource('orders', SalesOrderController::class);
        Route::patch('orders/{order}/confirm', [SalesOrderController::class, 'confirm'])->name('orders.confirm');
        Route::resource('delivery-notes', DeliveryNoteController::class);
        Route::patch('delivery-notes/{note}/dispatch', [DeliveryNoteController::class, 'dispatch'])->name('delivery-notes.dispatch');
        Route::resource('invoices', SalesInvoiceController::class);
        Route::resource('payments', PaymentReceiptController::class);
    });

    // Settings (Admin only)
    Route::middleware('role:Admin')->group(function () {
        Route::get('settings/integrations', [SettingsController::class, 'integrations'])->name('settings.integrations');
        Route::post('settings/integrations/whatsapp', [SettingsController::class, 'updateWhatsapp'])->name('settings.integrations.whatsapp');
        Route::post('settings/integrations/test-whatsapp', [SettingsController::class, 'testWhatsappConnection'])->name('settings.integrations.test-whatsapp');
        Route::post('settings/integrations/send-test-message', [SettingsController::class, 'sendTestMessage'])->name('settings.integrations.send-test-message');
        Route::get('settings/integrations/mail-accounts', [MailAccountController::class, 'index'])->name('settings.mail-accounts.index');
        Route::post('settings/integrations/mail-accounts', [MailAccountController::class, 'store'])->name('settings.mail-accounts.store');
        Route::get('settings/integrations/mail-accounts/{mailAccount}', [MailAccountController::class, 'show'])->name('settings.mail-accounts.show');
        Route::put('settings/integrations/mail-accounts/{mailAccount}', [MailAccountController::class, 'update'])->name('settings.mail-accounts.update');
        Route::delete('settings/integrations/mail-accounts/{mailAccount}', [MailAccountController::class, 'destroy'])->name('settings.mail-accounts.destroy');
        Route::post('settings/integrations/mail-accounts/{mailAccount}/test', [MailAccountController::class, 'testConnection'])->name('settings.mail-accounts.test');
        Route::post('settings/integrations/mail-accounts/{mailAccount}/send-test', [MailAccountController::class, 'sendTestEmail'])->name('settings.mail-accounts.send-test');
        Route::patch('settings/integrations/mail-accounts/{mailAccount}/toggle', [MailAccountController::class, 'toggleEnabled'])->name('settings.mail-accounts.toggle');

        // Projects settings
        Route::get('settings/projects', [ProjectSettingController::class, 'index'])->name('settings.projects.index');
        Route::get('settings/projects-overview', [ProjectSettingController::class, 'projectsOverview'])->name('settings.projects.overview');
        Route::post('settings/projects', [ProjectSettingController::class, 'store'])->name('settings.projects.store');
        Route::post('settings/projects/import', [ProjectSettingController::class, 'import'])->name('settings.projects.import');
        Route::get('settings/projects/template', [ProjectSettingController::class, 'downloadTemplate'])->name('settings.projects.template');
        Route::post('settings/projects/companies', [ProjectSettingController::class, 'storeCompany'])->name('settings.projects.companies.store');
        Route::patch('settings/projects/companies/{company}', [ProjectSettingController::class, 'updateCompany'])->name('settings.projects.companies.update');
        Route::delete('settings/projects/companies/{company}', [ProjectSettingController::class, 'destroyCompany'])->name('settings.projects.companies.destroy');
        Route::patch('settings/projects/{project}', [ProjectSettingController::class, 'update'])->name('settings.projects.update');
        Route::delete('settings/projects/{project}', [ProjectSettingController::class, 'destroy'])->name('settings.projects.destroy');
        Route::post('settings/projects/{project}/locations', [ProjectSettingController::class, 'storeLocation'])->name('settings.projects.locations.store');
        Route::patch('settings/projects/{project}/locations/{location}', [ProjectSettingController::class, 'updateLocation'])->name('settings.projects.locations.update');
        Route::delete('settings/projects/{project}/locations/{location}', [ProjectSettingController::class, 'destroyLocation'])->name('settings.projects.locations.destroy');
        Route::post('settings/projects/companies/{company}/departments', [ProjectSettingController::class, 'storeDepartment'])->name('settings.projects.companies.departments.store');
        Route::patch('settings/projects/companies/{company}/departments/{department}', [ProjectSettingController::class, 'updateDepartment'])->name('settings.projects.companies.departments.update');
        Route::delete('settings/projects/companies/{company}/departments/{department}', [ProjectSettingController::class, 'destroyDepartment'])->name('settings.projects.companies.departments.destroy');

        // VAT settings
        Route::get('settings/vat',  [VatSettingController::class, 'index'])->name('settings.vat');
        Route::post('settings/vat', [VatSettingController::class, 'update'])->name('settings.vat.update');

        // User management
        Route::get('settings/users', [UserManagementController::class, 'index'])->name('settings.users.index');
        Route::post('settings/users', [UserManagementController::class, 'store'])->name('settings.users.store');
        Route::patch('settings/users/{user}', [UserManagementController::class, 'update'])->name('settings.users.update');
    });

    // React SPA shell (catch-all — must stay last so it never shadows a more specific route)
    Route::get('/app/{any?}', fn () => view('app-shell'))
        ->where('any', '.*')
        ->name('app.shell');
});

require __DIR__.'/auth.php';
