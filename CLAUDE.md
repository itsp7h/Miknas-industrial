# OperationModule — SteelERP

Manufacturing & Trading ERP. Laravel 12 / PHP 8.2. SQLite. Four modules: Purchase, Inventory, Production, Sales.

---

## Quick Start

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
npm run dev          # Vite dev server (keep running)
php artisan serve    # http://localhost:8000
```

---

## Tech Stack

| | |
|---|---|
| Framework | Laravel 12, PHP 8.2 |
| Frontend | Tailwind CSS v3 (JIT), Alpine.js v3, Vite 7 |
| Database | SQLite — `database/database.sqlite` |
| Auth | Laravel Breeze (email+password, verified) |
| RBAC | spatie/laravel-permission v6 — middleware: `role`, `permission`, `role_or_permission` |
| PDF | barryvdh/laravel-dompdf v3 |
| Excel | phpoffice/phpspreadsheet v5 |
| Dev tools | Pint, Pail, Sail, PHPUnit 11 |

**Roles (seeded):** Admin, Accounts, Store Manager, Production Manager, Sales Manager

---

## Environments

| | Production | Staging |
|---|---|---|
| URL | https://steelerp.p7h.me | http://192.168.0.38 |
| Host | LXC `SteelERP`, 192.168.0.46 | LXC `steelERPstaging`, 192.168.0.38 |
| Branch | `main` | `development` |
| Deploys | tag `v*` or manual, behind approval | automatic, once CI is green |

Public traffic reaches production through Cloudflare → a tunnel host on
192.168.1.10 → Apache on port 80. Nothing inbound reaches either container
directly, which is why deploys run on **self-hosted** GitHub Actions runners
(`steelerp-production`, `steelerp-staging`) rather than GitHub-hosted ones.

Both boxes run `steelerp-reverb`, `steelerp-queue` and `steelerp-scheduler`
as systemd units. Staging has `ULTRAMSG_ENABLED=false` — it carries a copy of
live customer data, so an enabled WhatsApp integration there would message
real customers.

---

## Testing & CI/CD

```bash
php artisan test        # PHPUnit — in-memory SQLite
npm test                # Vitest — React components
vendor/bin/pint         # format; CI gates on `pint --test` repo-wide
scripts/smoke-test.sh <base-url>   # black-box checks against a running site
```

CI (`.github/workflows/ci.yml`) runs a PHP syntax lint, Pint, PHPUnit on 8.2
and 8.3, Vitest, and a Vite build. The syntax lint exists because three
notification classes once shipped as invalid PHP (`??` inside `"{...}"`
interpolation): a file that does not parse never gets far enough to fail a
test, so no unit suite could have caught it.

Deploy scripts live in `scripts/`; see `docs/ci-cd-setup.md` for runner setup
and rollback. Every deploy backs up the SQLite database before migrating.

**Any Vitest test that renders a component reaching Echo must `vi.mock` it.**
`resources/js-app/echo.js` instantiates Pusher at import time, so without a
`VITE_REVERB_APP_KEY` it throws and the whole suite file fails to load with
zero tests run. CI has no `.env`, so this passes locally and fails there —
mock `'../echo'` the way `NotificationBell.test.jsx` does.

---

## Controllers — `app/Http/Controllers/`

```
Controller.php            DashboardController.php   ProfileController.php
SettingsController.php    MailAccountController.php
Auth/                     (Breeze defaults)
Api/                      ← React SPA's JSON API
  AuthController.php              DashboardController.php
  NotificationController.php
  Purchase/PurchasePipelineController.php
  Purchase/SupplierController.php
  Purchase/PurchaseOrderController.php
  Purchase/GoodsReceiptNoteController.php
  Purchase/SupplierInvoiceController.php
  Purchase/SupplierPaymentController.php
Settings/
  LocationController.php          ProjectSettingController.php
  UrgencyLevelController.php      UserManagementController.php
  VatSettingController.php
Purchase/
  PurchaseRequestController.php   + approve, reject, print
  PurchaseOrderController.php     ← only generateLpo + print/pdf survive
  PurchasePipelineController.php
  PurchaseSignatureController.php RfqController.php
  RfqPortalController.php         ← public, token-based, no auth
  SupplierQuoteController.php
Inventory/
  ItemController.php              import, downloadTemplate, exportPdf + CRUD
  WarehouseController.php         StockMovementController.php
  StockReportController.php       summary, movement, lowStock, valuation
Production/
  ProductionOrderController.php   + start, complete
  BillOfMaterialController.php    MaterialIssueController.php
  ProductionOutputController.php
Sales/
  CustomerController.php          SalesOrderController.php        + confirm
  DeliveryNoteController.php      + dispatch
  SalesInvoiceController.php      PaymentReceiptController.php
```

Note: `SupplierController` (Purchase) is gone — suppliers are served by the
React SPA through `Api/Purchase/SupplierController`.

## Models — `app/Models/`

```
User.php
Supplier.php          SupplierInvoice.php     SupplierPayment.php
PurchaseRequest.php   PurchaseRequestItem.php
PurchaseOrder.php     PurchaseOrderItem.php
GoodsReceiptNote.php  GrnItem.php
Item.php              Warehouse.php           StockLevel.php    StockMovement.php
ProductionOrder.php   BillOfMaterial.php      MaterialIssue.php
ProductionOutput.php  ProductionCost.php
Customer.php          SalesOrder.php          SalesOrderItem.php
DeliveryNote.php      DeliveryNoteItem.php
SalesInvoice.php      PaymentReceipt.php
```

---

## Services — `app/Services/`

| File | Purpose |
|------|---------|
| `SupplierImportService.php` | Excel import — detects MRF vs template format, skips duplicates |
| `ItemImportService.php` | Excel import — detects Forkoll vs template format, skips duplicates |
| `ProjectImportService.php` | Excel import for projects |
| `PurchaseStageService.php` | The purchase pipeline's stage machine (`draft → … → complete`). `setStageIfNotPast()` is the guard that stops a re-award rolling a request backwards. Covered by `tests/Unit/PurchaseStageServiceTest.php` |
| `RfqInvitationService.php` | Builds tokenised RFQ invitations for the public supplier portal |
| `LpoGenerationService.php` | Generates LPOs from awarded quote items |

---

## Artisan Commands — `app/Console/Commands/`

```bash
php artisan suppliers:import "path/to/file.xlsx" [--dry-run]
php artisan suppliers:template [--output=path]     # → storage/app/suppliers_template.xlsx
php artisan items:template [--output=path]         # → storage/app/items_template.xlsx
```

Files: `ImportSuppliers.php`, `GenerateSupplierTemplate.php`, `GenerateItemTemplate.php`

---

## Routes

`routes/web.php` (Blade pages), `routes/api.php` (React SPA JSON, Sanctum),
`routes/auth.php` (Breeze), `routes/channels.php` (broadcast auth),
`routes/console.php`.

`/up` is Laravel's built-in health route — the smoke tests key off it.

### web.php — all protected by `['auth', 'verified']`. Prefix groups:

### Purchase — `prefix('purchase')->name('purchase.')`
```
GET/POST  suppliers              purchase.suppliers.*
POST      suppliers/import       purchase.suppliers.import       ← must be BEFORE resource
GET       suppliers/template     purchase.suppliers.template     ← must be BEFORE resource
GET       suppliers/export-pdf   purchase.suppliers.export-pdf  ← must be BEFORE resource
GET/POST  requests               purchase.requests.*
PATCH     requests/{id}/approve  purchase.requests.approve
PATCH     requests/{id}/reject   purchase.requests.reject
GET       requests/{id}/print    purchase.requests.print
GET       orders/{id}/print      purchase.orders.print   ← LPO document, Blade
GET       orders/{id}/pdf        purchase.orders.pdf     ← LPO document, Blade
POST      requests/{id}/generate-lpo  purchase.requests.generate-lpo
```

### Inventory — `prefix('inventory')->name('inventory.')`
```
GET/POST  items                  inventory.items.*
POST      items/import           inventory.items.import          ← must be BEFORE resource
GET       items/template         inventory.items.template        ← must be BEFORE resource
GET       items/export-pdf       inventory.items.export-pdf     ← must be BEFORE resource
GET/POST  warehouses             inventory.warehouses.*
GET/POST  movements              inventory.movements.*
GET       reports/summary        inventory.reports.summary
GET       reports/movement       inventory.reports.movement
GET       reports/low-stock      inventory.reports.low-stock
GET       reports/valuation      inventory.reports.valuation
```

### Production — `prefix('production')->name('production.')`
```
GET/POST  orders                 production.orders.*
PATCH     orders/{id}/start      production.orders.start
PATCH     orders/{id}/complete   production.orders.complete
GET/POST  bom                    production.bom.*
GET/POST  material-issues        production.material-issues.*
GET/POST  outputs                production.outputs.*
```

### Sales — `prefix('sales')->name('sales.')`
```
GET/POST  customers              sales.customers.*
GET/POST  orders                 sales.orders.*
PATCH     orders/{id}/confirm    sales.orders.confirm
GET/POST  delivery-notes         sales.delivery-notes.*
PATCH     delivery-notes/{id}/dispatch  sales.delivery-notes.dispatch
GET/POST  invoices               sales.invoices.*
GET/POST  payments               sales.payments.*
```

---

## Views — `resources/views/`

```
dashboard.blade.php
welcome.blade.php
layouts/
  app.blade.php          main layout (sidebar + topbar)
  navigation.blade.php
  guest.blade.php
components/              (Breeze defaults: modal, dropdown, buttons, inputs, etc.)
auth/                    (login, register, forgot-password, reset-password, verify-email, confirm-password)
profile/edit.blade.php   + partials/

purchase/
  suppliers/   index, create, edit, pdf
  requests/    index, create, edit, show
  orders/      pdf, print          ← list/detail/forms are React
                                   (grns/, invoices/ and payments/ are fully React)
  invoices/    index, create, edit
  payments/    index, create

inventory/
  items/       index, create, edit, pdf
  warehouses/  index, create, edit
  movements/   index, create
  reports/     summary, movement, low-stock, valuation

production/
  orders/          index, create, edit, show
  bom/             index, create, edit
  material-issues/ index
  outputs/         index

sales/
  customers/      index, create, edit
  orders/         index, create, edit, show
  delivery-notes/ index, create, edit
  invoices/       index, create, edit
  payments/       index, create
```

---

## Database — `database/`

**Driver:** SQLite — `database/database.sqlite`

### Migrations (29 total)
```
users, cache, jobs (Laravel defaults)
permission_tables (Spatie)
suppliers, items, warehouses, stock_levels, stock_movements
purchase_orders, purchase_order_items, goods_receipt_notes, purchase_requests
grn_items, supplier_invoices, supplier_payments
production_orders, bill_of_materials, material_issues
production_outputs, production_costs
customers, sales_orders, sales_order_items, delivery_notes
delivery_note_items, sales_invoices, payment_receipts
purchase_request_items
```

### Seeders
`database/seeders/DatabaseSeeder.php` — creates roles (Admin, Accounts, Store Manager, Production Manager, Sales Manager)

---

## MCP Server — `.claude/mcp-server.py`

Read-only SQLite access. 16 tools:

```
list_tables                    describe_table(table)
run_query(sql)                 — SELECT/WITH only, max 200 rows

get_suppliers(active_only)     get_purchase_orders(status)
get_purchase_requests(status)  get_supplier_invoices(status)

get_items(category,active)     get_stock_levels(warehouse_id,low_stock_only)
get_warehouses()

get_production_orders(status)  get_bill_of_materials(product_id)

get_customers(active_only)     get_sales_orders(status)
get_sales_invoices(status)     get_dashboard_summary()
```

**Activation:** run `claude mcp add operation-module-db python -- .claude/mcp-server.py` once, then restart Claude Code. Use these tools before reading PHP files or running artisan for data questions.

---

## Critical Gotchas

### 1. Tailwind JIT — use inline styles for modals/dynamic classes
Vite JIT only compiles classes found in scanned source files at build time. Classes like `max-w-lg`, `max-w-md`, arbitrary colors used only in a modal or added dynamically **will not appear in the compiled CSS**. Always use `style="..."` inline for modal widths, custom colors, and one-off layout values.

### 2. PhpSpreadsheet v5 — `getCellByColumnAndRow()` removed
Use `Coordinate::stringFromColumnIndex($col) . $row` then `$sheet->getCell($coord)` instead:
```php
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
$val = $sheet->getCell(Coordinate::stringFromColumnIndex($col) . $row)->getValue();
```

### 3. Custom routes BEFORE Route::resource()
`Route::resource('suppliers', ...)` registers `{supplier}` wildcard that captures `import`, `template`, `export-pdf`. Custom routes MUST be declared first:
```php
Route::post('suppliers/import', ...);    // first
Route::get('suppliers/template', ...);   // first
Route::get('suppliers/export-pdf', ...); // first
Route::resource('suppliers', ...);       // last
```

### 4. MRFMI supplier import — contact fields are always empty
The MRFMI price-comparison Excel file only contains supplier names in row 4 as column headers. There is no email, phone, or contact data anywhere in the file. This is expected — not a bug.

### 5. Forkoll import — "DESCRIPTION" header row
The Forkoll inventory file has a header row (col A = SI.NO, col B = DESCRIPTION). `ItemImportService` skips col B values in `$headerWords = ['description', 'item name', 'item_name', 'material']` to avoid importing headers as items.

### 6. Search bars — always client-side, never page-refreshing
All search/filter inputs MUST filter records instantly using JavaScript. No `?search=` URL params, no form submissions, no `debounce` + fetch, no page reloads of any kind.

**The pattern to follow (used on the Suppliers index):**
```javascript
var searchInput = document.getElementById('my-search');
var rows        = document.querySelectorAll('.data-row');
var countEl     = document.getElementById('search-count');
var total       = rows.length;

searchInput.addEventListener('input', function() {
    var q = this.value.trim().toLowerCase();
    var visible = 0;
    rows.forEach(function(row) {
        var match = !q || row.textContent.toLowerCase().indexOf(q) !== -1;
        row.classList.toggle('hidden-by-search', !match);
        if (match) visible++;
    });
    countEl.textContent = q ? (visible + ' of ' + total) : total;
});
```
- All records are loaded on page load — the server query returns everything, JS does the filtering
- Add `.hidden-by-search { display:none; }` to the page `<style>` block
- Show a live count ("12 of 47 suppliers") next to the search input
- Show a "No results" message when `visible === 0 && q !== ''`
- Never add `?search=` query params or submit a form for search

### 7. Never use `alert()`, `confirm()`, or `prompt()` — use toasts and modals
These native browser dialogs are ugly, block the thread, and cannot be styled. They are **banned** across the entire project.
- Use `showToast('msg', 'type')` for notifications instead of `alert()`
- Use a custom modal (like the delete confirmation modal) instead of `confirm()`
- Use a styled modal with an `<input>` instead of `prompt()`

### 8. Route parameter names must never be `{request}`
Laravel injects `Illuminate\Http\Request` by type-hint into `$request`. If a route parameter is also named `{request}`, implicit model binding silently fails — the model parameter receives null/empty, causing NOT NULL violations or UrlGenerationException when generating URLs from that model.

**Rule:** Always name route parameters after the model, matching the controller variable name exactly:
```php
// WRONG — {request} clashes with Request $request injection
Route::post('requests/{request}/sign', [PurchaseSignatureController::class, 'store']);
// store(Request $request, PurchaseRequest $purchaseRequest) — $purchaseRequest gets null

// CORRECT
Route::post('requests/{purchaseRequest}/sign', [PurchaseSignatureController::class, 'store']);
// store(Request $request, PurchaseRequest $purchaseRequest) — binding works
```

**Route::resource also generates `{request}` for a resource named `requests`.** Always override it:
```php
// WRONG
Route::resource('requests', PurchaseRequestController::class);
// Generates {request} — clashes with $request injection in every method

// CORRECT
Route::resource('requests', PurchaseRequestController::class)->parameters(['requests' => 'purchaseRequest']);
// Generates {purchaseRequest} — matches $purchaseRequest in controller methods
```
Use `{purchaseRequest}`, `{purchaseOrder}`, `{supplier}`, etc. — never `{request}`.

### 9. Status notifications — always use toasts, never inline banners
All success/error/info/warning messages MUST be displayed as toasts, not as `<div>` banners inside the page content. The global toast system is wired into `layouts/app.blade.php` and fires automatically from Laravel session flash keys (`success`, `error`, `info`, `warning`). In controllers, use:
```php
return redirect()->route('...')->with('success', 'Done.');
return redirect()->route('...')->with('error', 'Something failed.');
```
To trigger a toast from JavaScript (e.g. after an in-page action), call:
```javascript
showToast('Message text', 'success'); // types: success | error | info | warn
```
**Never** add inline `@if(session('success'))` banner divs to individual views — the layout handles all of them. The toast appears bottom-right, auto-dismisses after 4 s, has a shrinking progress bar, and can be clicked or ×-closed early.

### 10. Purchase Request creation — always use `<x-purchase.request-modal />`
The create form lives in `resources/views/components/purchase/request-modal.blade.php` as a reusable Blade component. Wherever a "New Purchase Request" trigger is needed, drop in the component tag — it renders the button and the full modal itself:
```blade
<x-purchase.request-modal />
```
- **Never** link to `route('purchase.requests.create')` for creating new requests — the component replaces that flow entirely.
- The component is self-contained: it owns the trigger button, the Alpine.js open/close state, the full MPR form (POSTing to `purchase.requests.store`), dynamic item rows, and validation-error auto-reopen logic.
- The `/purchase/requests/create` page and route remain as a fallback but should not be referenced in new UI.

### 11. Data entry pages — AJAX only, no page refreshes
All settings and management pages where users create, edit, or delete records MUST use `fetch()` AJAX. No `<form>` submissions, no page reloads, no redirects after data entry.

**Controller:** return `response()->json(...)` for all create/update/delete endpoints. Laravel auto-returns 422 JSON on validation failure when `Accept: application/json` is set.

**Frontend fetch helper pattern:**
```javascript
var CSRF = document.querySelector('meta[name="csrf-token"]').content;
function api(url, method, data) {
    var opts = { method: method, headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' } };
    if (data) opts.body = JSON.stringify(data);
    return fetch(url, opts).then(function(r) {
        return r.json().then(function(body) {
            if (!r.ok) return Promise.reject(body);
            return body;
        });
    });
}
```

**After each operation:**
- On success: update the DOM in-place (append row, update text, remove element), then call `showToast('Done.', 'success')`
- On error: call `showToast(err.message || 'Error', 'error')`
- For deletes: use `confirmAction(title, body, onConfirm)` (not `confirm()`) before calling the API

**Never** use `<form method="POST">` for inline data entry on settings/management pages. `<form>` submissions that navigate away from the page are banned for these flows.

### 12. All frontend work is React — mobile and desktop are separate files, everything is live
This project is migrating from Blade/Alpine to a React SPA (`resources/js-app/`), module by module. See `docs/superpowers/specs/2026-07-29-react-spa-migration-design.md` and `docs/superpowers/specs/2026-08-02-mobile-view-and-live-everywhere-design.md` for the full architecture.

- **React only, going forward.** New pages and any page being converted are built in React, not Blade. Blade/Alpine remain only for not-yet-migrated modules.
- **Mobile and desktop are separate component files, never one file branching on device.** Every page has a `pages/desktop/...` version and a `pages/mobile/...` version. A `useViewport()` hook (breakpoint: 768px) picks which tree renders and swaps it live on resize/rotate — no reload, no route change.
- **Everything live, no polling.** Any change to the database that matters to a user on screen (new record, status change, notification) reaches them via Laravel Reverb broadcast + Laravel Echo, not a polling `setInterval`. If you add a feature that changes state other users can see, add a `ShouldBroadcast` event for it.
- **Full cutover per module, no coexistence.** When converting a module to React, delete its Blade controllers/routes/views in the same change — never leave old and new versions of the same page both linked in the sidebar. (Lesson from commit `575eb7a`: a side-by-side React Suppliers page caused two confusing sidebar entries and was reverted.)
- **Migration order:** Foundation/shell → Purchase → Inventory → Production → Sales. Each module is its own phase with its own spec.

**A page being React is not the same as it being ported.** The Inventory, Sales
and Production modules were cut over to React in one sweep each (`0aaad1b`,
`8cb2cc9`, `c494fbe`, `d6ae2f2`, `5834b68`), built on the generic `Card` +
`Table` primitives rather than reproducing the Blade page each replaced. The
result renders the right data with the wrong design — green primaries where
Blade used blue `.btn-primary`, `Yes`/`No` where Blade badged, text links where
Blade used `.btn-sm`, missing page headers and subtitles — and in several cases
dropped real function (a whole column, a drag-and-drop import, low-stock
signalling). When touching one of these pages, recover its Blade original from
the commit that deleted it (`git log --diff-filter=D -- 'resources/views/<path>'`
then `git show <sha>^:<path>`) and diff against it before assuming the React
page is finished. Purchase, Inventory and Production have all been through
this; **Sales has not.**

**Where the migration stands.** React (desktop + mobile pair each): Dashboard, Purchase Pipeline board **and detail**, Suppliers, **Purchase Orders**, **Goods Receipt Notes**, **Supplier Invoices**, **Supplier Payments**, all of Inventory, all of Production, all of Sales. Still Blade: the rest of Purchase (requests, quotes workspace, RFQ, signature — the RFQ workflow, none of which has a sidebar entry), all of Settings, Profile, and the Breeze auth pages. The public token RFQ portal (`/rfq/{token}`) and every `print`/`pdf` view stay Blade permanently — they render outside the SPA shell or are DomPDF documents.

**The cutover checklist** (each step is a way a cutover has broken before):
1. Add the `Api/` controller, an `App\Http\Resources\` resource, and `…Saved`/`…Deleted` broadcast events; wire routes in `routes/api.php` — custom paths like `orders/form-options` go **before** the `{wildcard}`.
2. Build `pages/desktop/…` and `pages/mobile/…`, register both in `App.jsx` behind `useViewport()`, and flip the `navItems.js` entry from `type: 'href'` to `type: 'link'`.
3. Repoint every Blade referrer: the `layouts/app.blade.php` sidebar (pull the entry out of the `route()` `@foreach` into a hardcoded `<a href="/app/…">` with `request()->is(...)` for active state), `dashboard.blade.php` quick links, and any deep link from a still-Blade page — those become plain `/app/...` URLs, since the named route is about to disappear.
4. Delete the Blade views, routes, and controller methods. Keep `print`/`pdf` and re-point any controller redirect that named a deleted route.
5. Port the deleted routes' authorization tests onto the new API endpoints — never just delete them — and add the module's URLs to `tests/Feature/BladePagesStillRenderTest.php`, which fails if the sidebar still names a dead route or a supposedly-deleted Blade URL still answers.

### 13. Still-Blade pages get a mobile counterpart too — same "separate files" rule, different mechanism
Not every module is React yet (gotcha #12's migration order). Until a module converts, its pages still get a dedicated, professionally-designed mobile version — same principle as #12 (mobile and desktop are separate files, never one file branching on device), but Blade has no live `useViewport()` swap, so it uses a different mechanism. See `docs/superpowers/specs/2026-08-03-blade-mobile-desktop-split-design.md` for the full design and page rollout order (Production → Inventory → Sales → remaining Purchase).

- **File layout:** desktop stays at `resources/views/{module}/{entity}/{action}.blade.php`, unchanged. Its mobile counterpart goes at the same relative path under `resources/views/mobile/...` — e.g. `resources/views/mobile/production/orders/index.blade.php`.
- **Switching:** a script in `layouts/app.blade.php` buckets `window.innerWidth` at the 768px breakpoint into a `viewport` cookie (reloading once on a mismatch, guarded against loops via `sessionStorage`). Controllers call `resolveView($view, $data)` instead of `view($view, $data)` — it renders `mobile.$view` only when the cookie says mobile AND that mobile view actually exists, otherwise it falls back to the desktop view unchanged. This is what makes the rollout genuinely incremental: a page with no mobile counterpart yet keeps working exactly as before.
- **Design language:** card lists instead of wide tables, single-column forms with full-width bottom-pinned primary actions, bottom-sheet-style modals, hero-style gradient header — matching the pattern already used by the React mobile Pipeline Board (`pages/mobile/purchase/PipelineBoardPage.jsx`). Not a shrunk desktop page.
- **Keep both in sync:** once a page has a `mobile/` counterpart, any change to its shared data/fields/actions (new column, new validation rule, new button) must be applied to **both** files, each restyled for its own platform — never just the one you're looking at.
