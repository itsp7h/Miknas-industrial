<?php

use App\Http\Controllers\Purchase\PurchaseOrderController;
use App\Http\Controllers\Purchase\PurchaseRequestController;
use App\Http\Controllers\Purchase\RfqPortalController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Public RFQ portal — no auth required
Route::get('/rfq/{token}', [RfqPortalController::class, 'show'])->name('rfq.show');
Route::post('/rfq/{token}', [RfqPortalController::class, 'submit'])->name('rfq.submit');

Route::middleware(['auth', 'verified'])->group(function () {
    // The dashboard is the React page at /app. The named route stays as a
    // redirect: Breeze's login and email-verification flows both send people to
    // route('dashboard'), and so does `/`.
    Route::redirect('/dashboard', '/app')->name('dashboard');

    // The notification bell is React and talks to routes/api.php. The three
    // web routes that used to back the Blade topbar's bell went with it.

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
        // The request detail page is React now, with the supplier picker, the
        // signature pad, the LPO issue and the GRN hand-off as its own dialogs.
        Route::get('pipeline/{purchaseRequest}', fn ($purchaseRequest) => redirect("/app/purchase/pipeline/{$purchaseRequest}"))
            ->whereNumber('purchaseRequest')->name('pipeline.show');

        // The GM signature pad and the RFQ supplier picker are React dialogs on
        // the pipeline detail page, writing to routes/api.php. Their Blade pages
        // and POST endpoints are gone; unlike the pages above these had no
        // sharable URL worth redirecting — nothing ever linked to them.

        // The quotes workspace is React now — one page for both of the old URLs,
        // with the award writes in routes/api.php. Both redirect, since either
        // could have been bookmarked.
        Route::get('requests/{purchaseRequest}/quotes', fn ($purchaseRequest) => redirect("/app/purchase/requests/{$purchaseRequest}/quotes"))
            ->whereNumber('purchaseRequest')->name('requests.quotes');
        Route::get('requests/{purchaseRequest}/compare', fn ($purchaseRequest) => redirect("/app/purchase/requests/{$purchaseRequest}/quotes"))
            ->whereNumber('purchaseRequest')->name('requests.compare');

        // The MPR form is a React modal and the request sheet a React page, so
        // the create/edit/show pages are gone. Their URLs redirect: all three
        // were live long enough to be bookmarked. `requests/create` must come
        // before the `{purchaseRequest}` wildcard, and `requests/print` after
        // it is fine because it carries an extra segment.
        Route::redirect('requests', '/app/purchase/pipeline')->name('requests.index');
        Route::redirect('requests/create', '/app/purchase/pipeline?new=1')->name('requests.create');
        Route::get('requests/{purchaseRequest}/edit', fn ($purchaseRequest) => redirect("/app/purchase/pipeline/{$purchaseRequest}"))
            ->whereNumber('purchaseRequest')->name('requests.edit');
        Route::get('requests/{purchaseRequest}/print', [PurchaseRequestController::class, 'print'])->name('requests.print');
        Route::get('requests/{purchaseRequest}', fn ($purchaseRequest) => redirect("/app/purchase/requests/{$purchaseRequest}"))
            ->whereNumber('purchaseRequest')->name('requests.show');
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
