<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Purchase\PurchaseOrderController;
use App\Http\Controllers\Purchase\PurchasePipelineController;
use App\Http\Controllers\Purchase\PurchaseRequestController;
use App\Http\Controllers\Purchase\PurchaseSignatureController;
use App\Http\Controllers\Purchase\RfqController;
use App\Http\Controllers\Purchase\RfqPortalController;
use App\Http\Controllers\Purchase\SupplierQuoteController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Public RFQ portal — no auth required
Route::get('/rfq/{token}', [RfqPortalController::class, 'show'])->name('rfq.show');
Route::post('/rfq/{token}', [RfqPortalController::class, 'submit'])->name('rfq.submit');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/notifications/unread', fn () => response()->json([
        'count' => auth()->user()->unreadNotifications()->count(),
        'items' => auth()->user()->unreadNotifications()->latest()->take(10)->get()->map(fn ($n) => [
            'id' => $n->id,
            'message' => $n->data['message'] ?? '',
            'go_url' => route('notifications.go', $n->id),
            'ago' => $n->created_at->diffForHumans(),
        ]),
    ]))->name('notifications.unread');

    Route::get('/notifications/{id}/go', function (string $id) {
        $n = auth()->user()->notifications()->findOrFail($id);
        $n->markAsRead();
        $dest = $n->data['url'] ?? route('dashboard');

        return redirect($dest);
    })->name('notifications.go');

    Route::post('/notifications/read-all', fn () => response()->json(
        tap(auth()->user()->unreadNotifications()->update(['read_at' => now()]))
    ))->name('notifications.read-all');

    // The profile page is served by the React shell at /app/profile; its writes
    // live in routes/api.php. The named route stays as a redirect because Breeze
    // still points at it after a password update.
    Route::redirect('/profile', '/app/profile')->name('profile.edit');

    // Purchase Module
    Route::prefix('purchase')->name('purchase.')->group(function () {
        // ── Bookmark safety nets ────────────────────────────────────────────
        // These pages all live in the React shell now. Rather than 404 anyone
        // holding an old link, each redirects to its /app equivalent. Only the
        // DomPDF print/pdf documents are still served here, and they must be
        // declared BEFORE the wildcard redirects or those would swallow them.
        Route::redirect('pipeline', '/app/purchase/pipeline')->name('pipeline.index');
        Route::get('pipeline/{purchaseRequest}', [PurchasePipelineController::class, 'show'])->name('pipeline.show');

        // GM Signature
        Route::get('requests/{purchaseRequest}/sign', [PurchaseSignatureController::class, 'show'])->name('requests.sign');
        Route::post('requests/{purchaseRequest}/sign', [PurchaseSignatureController::class, 'store'])->name('requests.sign.store');

        // RFQ
        Route::post('requests/{purchaseRequest}/rfq/select', [RfqController::class, 'selectSuppliers'])->name('requests.rfq.select');
        Route::post('requests/{purchaseRequest}/rfq/send-all', [RfqController::class, 'sendAll'])->name('requests.rfq.send-all');
        Route::get('requests/{purchaseRequest}/rfq', [RfqController::class, 'show'])->name('requests.rfq');
        Route::post('requests/{purchaseRequest}/rfq', [RfqController::class, 'store'])->name('requests.rfq.store');

        // Quotes
        Route::get('requests/{purchaseRequest}/quotes', [SupplierQuoteController::class, 'index'])->name('requests.quotes');
        Route::get('requests/{purchaseRequest}/compare', [SupplierQuoteController::class, 'compare'])->name('requests.compare');
        Route::post('requests/{purchaseRequest}/quotes/items/{quoteItem}/award', [SupplierQuoteController::class, 'awardItem'])->name('requests.quotes.items.award');
        Route::post('requests/{purchaseRequest}/quotes/items/{quoteItem}/unaward', [SupplierQuoteController::class, 'unawardItem'])->name('requests.quotes.items.unaward');

        Route::resource('requests', PurchaseRequestController::class)->parameters(['requests' => 'purchaseRequest']);
        Route::patch('requests/{purchaseRequest}/approve', [PurchaseRequestController::class, 'approve'])->name('requests.approve');
        Route::patch('requests/{purchaseRequest}/reject', [PurchaseRequestController::class, 'reject'])->name('requests.reject');
        Route::get('requests/{purchaseRequest}/print', [PurchaseRequestController::class, 'print'])->name('requests.print');
        Route::post('requests/{purchaseRequest}/generate-lpo', [PurchaseOrderController::class, 'generateFromRequest'])->name('requests.generate-lpo');
        // Purchase orders are served by the React SPA at /app/purchase/orders.
        // Only the DomPDF-backed print/pdf documents stay server-rendered.
        Route::get('orders/{order}/print', [PurchaseOrderController::class, 'print'])->name('orders.print');
        Route::get('orders/{order}/pdf', [PurchaseOrderController::class, 'pdf'])->name('orders.pdf');

        Route::redirect('orders', '/app/purchase/orders');
        Route::get('orders/{order}', fn ($order) => redirect("/app/purchase/orders/{$order}"))
            ->whereNumber('order');

        // grns/create carried a ?purchase_order_id=… the React list also accepts,
        // so the query string is forwarded rather than dropped.
        Route::get('grns/create', fn (Request $request) => redirect()->to(
            '/app/purchase/grns'.($request->query('purchase_order_id')
                ? '?purchase_order_id='.$request->query('purchase_order_id')
                : '')
        ));
        Route::redirect('grns', '/app/purchase/grns');
        Route::get('grns/{grn}', fn ($grn) => redirect("/app/purchase/grns/{$grn}"))
            ->whereNumber('grn');
        Route::redirect('invoices', '/app/purchase/invoices');
        Route::get('invoices/{invoice}', fn ($invoice) => redirect('/app/purchase/invoices'))
            ->whereNumber('invoice');
        Route::get('invoices/create', fn () => redirect('/app/purchase/invoices'));
        Route::redirect('payments', '/app/purchase/payments');
        // The invoices page linked here with ?invoice_id=…; the React page accepts
        // the same parameter, so it is forwarded rather than dropped.
        Route::get('payments/create', fn (Request $request) => redirect()->to(
            '/app/purchase/payments'.($request->query('invoice_id')
                ? '?invoice_id='.$request->query('invoice_id')
                : '')
        ));
        Route::get('payments/{payment}', fn ($payment) => redirect('/app/purchase/payments'))
            ->whereNumber('payment');
    });

    // Inventory Module
    Route::prefix('inventory')->name('inventory.')->group(function () {});

    // Sales Module
    // Settings (Admin only)
    Route::middleware('role:Admin')->group(function () {
        // Integrations is served by the React shell at /app/settings/integrations;
        // the WhatsApp settings and the mail-account endpoints live in
        // routes/api.php.
        Route::redirect('settings/integrations', '/app/settings/integrations')->name('settings.integrations');

        // Both projects settings pages are served by the React shell now
        // (/app/settings/companies and /app/settings/projects); their writes,
        // import and template live in routes/api.php. Only redirects for old
        // links stay here.
        Route::redirect('settings/projects', '/app/settings/companies')->name('settings.projects.index');
        Route::redirect('settings/projects-overview', '/app/settings/projects')->name('settings.projects.overview');

        // VAT is served by the React shell at /app/settings/vat; the rate itself
        // lives behind GET/PUT /api/v1/settings/vat.
        Route::redirect('settings/vat', '/app/settings/vat')->name('settings.vat');

        // User management is served by the React shell at /app/settings/users;
        // its endpoints live in routes/api.php.
        Route::redirect('settings/users', '/app/settings/users')->name('settings.users.index');
    });

    // React SPA shell (catch-all — must stay last so it never shadows a more specific route)
    Route::get('/app/{any?}', fn () => view('app-shell'))
        ->where('any', '.*')
        ->name('app.shell');
});

require __DIR__.'/auth.php';
