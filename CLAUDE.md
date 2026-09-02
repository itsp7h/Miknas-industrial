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

45 files. Everything the SPA talks to lives under `Api/`; what is left outside it
is Breeze's auth flow and the not-yet-migrated RFQ workflow.

```
Controller.php

Api/                              ← the React SPA's JSON API
  AuthController.php              DashboardController.php
  NotificationController.php      ProfileController.php
  Purchase/  SupplierController, PurchaseOrderController,
             GoodsReceiptNoteController, SupplierInvoiceController,
             SupplierPaymentController, PurchasePipelineController,
             SupplierQuoteController (quotes workspace, award/unaward),
             PurchaseRequestController (the MPR form, sheet and delete)
  Inventory/ ItemController (import, template, exportPdf), WarehouseController,
             StockMovementController, StockReportController
             (summary, movement, lowStock, valuation)
  Production/ ProductionOrderController (+ start, complete),
             BillOfMaterialController, MaterialIssueController,
             ProductionOutputController
  Sales/     CustomerController, SalesOrderController (+ confirm),
             DeliveryNoteController (+ dispatch, update, destroy),
             SalesInvoiceController (+ update, destroy), PaymentReceiptController
  Settings/  role:Admin on every route —
             CompanyController      companies + departments
             ProjectController      projects + locations + import + template
             UserController         users, roles, permissions
             IntegrationController  WhatsApp (UltraMSG)
             MailAccountController  mail accounts
             VatController          the global VAT rate

Auth/                             (Breeze defaults, session-establishing)

Purchase/                         ← what has to stay server-rendered
  PurchaseRequestController.php   ← print only (the MPR document)
  PurchaseOrderController.php     ← print/pdf only (LPO documents)
  RfqPortalController.php         ← public, token-based, no auth
```

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
| `ProjectTemplateGenerator.php` | Builds the projects import template (`storage/app/projects_template.xlsx`) |
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

`routes/web.php` (the redirects, the DomPDF documents and the public portal),
`routes/api.php`
(the React SPA's JSON API, Sanctum), `routes/auth.php` (Breeze),
`routes/channels.php` (broadcast auth), `routes/console.php`.

`/up` is Laravel's built-in health route — the smoke tests key off it.

`/dashboard` is a redirect to `/app`, not a page: the dashboard is React. The
named route survives because Breeze's login and email-verification flows and the
root route all send people to `route('dashboard')`.

### web.php — what is left

Everything else moved to `routes/api.php`. Two kinds of entry remain:

**Redirects into the shell** — the URLs were live long enough to be bookmarked,
and a dead end is worse than a hop:

```
/dashboard  → /app                     /profile             → /app/profile
/purchase/pipeline, orders, orders/{id}, grns, grns/{id}, grns/create,
  invoices, invoices/{id}, invoices/create, payments, payments/{id},
  payments/create                      → the matching /app/purchase/… page
                                         (create links forward their query string)
/settings/projects           → /app/settings/companies
/settings/projects-overview  → /app/settings/projects
/settings/users              → /app/settings/users
/settings/integrations       → /app/settings/integrations
/settings/vat                → /app/settings/vat
```

**Real Blade pages** — the RFQ workflow, the DomPDF documents, the public portal:

```
GET  purchase/requests                            → redirect /app/purchase/pipeline
GET  purchase/requests/create                     → redirect …/pipeline?new=1
GET  purchase/requests/{purchaseRequest}          → redirect /app/purchase/requests/{id}
GET  purchase/requests/{purchaseRequest}/edit     → redirect …/pipeline/{id}
GET  purchase/requests/{purchaseRequest}/print    MPR document
GET  purchase/pipeline/{purchaseRequest}          request detail
GET  purchase/requests/{purchaseRequest}/quotes   → redirect to /app/…/quotes
GET  purchase/requests/{purchaseRequest}/compare  → redirect to the same page
GET  purchase/orders/{order}/print|pdf            LPO documents (DomPDF)
GET/POST rfq/{token}                              public portal, no auth
```

Route parameter names must never be `{request}` — see gotcha #8.

## Views — `resources/views/`

38 files, and every one of them is deliberate: what is left is either the SPA's
host page, a session-establishing auth page, a DomPDF/print document, the public
RFQ portal, an email, or the not-yet-migrated RFQ workflow.

```
app-shell.blade.php        ← the React SPA's host page

layouts/
  guest.blade.php          chrome for the auth pages (the only Blade chrome
                           left — layouts/app went with the last page on it)

auth/                      login, forgot-password, reset-password,
                           verify-email, confirm-password
components/                the 6 Breeze partials the auth pages still use:
                           application-logo, auth-session-status, input-error,
                           input-label, primary-button, text-input

── Blade permanently ───────────────────────────────────────────────────────
purchase/orders/print, purchase/orders/pdf      LPO documents (DomPDF)
purchase/requests/print                         MPR document
inventory/items/pdf, purchase/suppliers/pdf     list exports
rfq/show, rfq/show-mobile, rfq/submitted,       public token portal,
rfq/expired                                     no auth, outside the shell
mail/lpo-issued, mail/rfq-invitation            emails
```

## Database — `database/`

**Driver:** SQLite — `database/database.sqlite`

### Migrations (60 files — the domain tables, plus the alters that followed)
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
add_rejection_record_to_purchase_requests
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
Route::put('requests/{request}', [PurchaseRequestController::class, 'update']);
// update(Request $request, PurchaseRequest $purchaseRequest) — $purchaseRequest gets null

// CORRECT
Route::put('requests/{purchaseRequest}', [PurchaseRequestController::class, 'update']);
// update(Request $request, PurchaseRequest $purchaseRequest) — binding works
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
All success/error/info/warning messages MUST be displayed as toasts, not as
`<div>` banners inside the page content. The toast system is React
(`components/ui/Toast.jsx`), mounted once in `main.jsx` above the router:
```jsx
const { showToast } = useToast();
showToast('Message text', 'success'); // types: success | error | info | warn
```
Session-flash toasts are gone with `layouts/app.blade.php`: there is no Blade
page left to render them, and API writes answer with a `message` the caller
toasts itself. So in an `Api/` controller return the message in the payload —
```php
return response()->json(['data' => …, 'message' => 'Done.']);
```
— rather than flashing to the session. The toast appears bottom-right and
auto-dismisses after 4 s.

### 10. Purchase Request create/edit — one React modal, opened through `useRequestModal()`
The MPR form is `resources/js-app/components/purchase/requests/RequestModal.jsx`,
hosted by `RequestModalProvider` above the router in `App.jsx`. Any page opens it
through the context rather than owning a copy:
```jsx
const { openNew, openEdit } = useRequestModal();
openNew();                       // blank MPR
openEdit(id, applyUpdate);       // loads the record; the callback gets the saved payload
```
- **Never** link to `route('purchase.requests.create')` or `/purchase/requests/{id}/edit` from new UI — those Blade pages survive only as a fallback.
- One component renders both forms; `requestModalChrome.js` holds what differs (title, gradient, accent, submit label, icon). The two Blade components had drifted — the edit copy had a plain project select, a free-text department and a free-text unit where the create copy had a searchable picker and cascading selects — and a single tree is what stops that recurring.
- It is one tree with a `compact` flag from `useViewport()`, not a desktop/mobile pair: two copies of a form this long would drift, which is the same reasoning as Integrations, Profile and the quotes workspace.
- Writes go to `Api/Purchase/PurchaseRequestController`. `store` answers with a board row (the board opens it), `update` answers with the pipeline detail payload (the detail page opens it), and each broadcasts so the *other* screen stays in sync.
- The read-only sheet is its own page (`/app/purchase/requests/{id}`) with its own resource: the pipeline detail payload shapes items for the timeline and never carries remarks or the approval record. Delete lives there too — it is the only place the old `requests.destroy` route's capability is offered.
- **GM approval is the signature action, not a separate button.** `storeSignature` records the signature *and* writes `status`/`approved_by`/`approved_at` in one transaction — the dialog has always been titled "Approve & Sign" and said so, but until it did this nothing wrote those columns (the Blade `approve` action was their only writer and lost its UI in a cutover), so every signed request stayed `pending` and the sheet's approval block could never appear. Rejection is `POST pipeline/{id}/reject` behind the same policy, offered in the same dialog, and takes a **required** `rejection_reason` (min 5 chars, the same rule an award follows) recorded with `rejected_by`/`rejected_at`. It has its own three columns rather than borrowing `approved_by`/`approved_at`, which mean what they say. Both records survive on the row as history, so **anything rendering an approval or a refusal must key off `status`** — `=== 'approved'` and `=== 'rejected'` respectively — not off the columns or the relation being present. A request approved after a refusal carries both.
- Both modals wait for their data before mounting (options on the first open, the record when editing) and are keyed per target, because the form seeds itself from `initial` at mount. Do not add an effect that re-seeds from `initial`: a late render changing its identity would wipe what the user had typed.
- A row is `required` only once the user has put something in it. Blade marked every added row required unconditionally, so adding a row and leaving it alone made the form refuse to submit with nothing but a browser tooltip to explain why.

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
page is finished. Every module has now been through this — Purchase, Inventory,
Production and Sales — so a React page here should be treated as ported, not
merely present.

**Where the migration stands.** React (desktop + mobile pair each): Dashboard (`/app`, and `/dashboard` redirects to it), Purchase Pipeline board **and detail**, the **supplier quotes workspace** (`/app/purchase/requests/{id}/quotes`, which the old `/quotes` and `/compare` Blade URLs both redirect to), the **MPR create and edit forms** (a modal any page opens through `useRequestModal()` — see gotcha #10 — which also took the last Alpine.js out of `app-shell.blade.php`), the **MPR sheet** (`/app/purchase/requests/{id}`, the pipeline header's "View Full Request"), Suppliers, **Purchase Orders**, **Goods Receipt Notes**, **Supplier Invoices**, **Supplier Payments**, all of Inventory, all of Production, all of Sales, and **Settings → Companies & Departments** (`/app/settings/companies`) plus **Settings → Projects** (`/app/settings/projects`), **Settings → Users** (`/app/settings/users`), **Settings → Integrations** (`/app/settings/integrations`) and **Settings → VAT** (`/app/settings/vat`) — i.e. **all of Settings** — and **Profile** (`/app/profile`, reached from the user card in either chrome). **Every page in the app is React** — every one of the 29 sidebar entries is a React route, and no `type: 'href'` entry remains in `navItems.js`. Still Blade: only the Breeze auth pages. The public token RFQ portal (`/rfq/{token}`) and every `print`/`pdf` view stay Blade permanently — they render outside the SPA shell or are DomPDF documents.

**The cutover checklist.** Every module is through it, so this is now the recipe
for adding a *new* page rather than for converting one — each step is still a
way a cutover broke before:
1. Add the `Api/` controller, an `App\Http\Resources\` resource, and `…Saved`/`…Deleted` broadcast events; wire routes in `routes/api.php` — custom paths like `orders/form-options` go **before** the `{wildcard}`.
2. Build `pages/desktop/…` and `pages/mobile/…`, register both in `App.jsx` behind `useViewport()`, and add a `type: 'link'` entry to `navItems.js`.
3. Repoint every referrer at the plain `/app/...` URL. (The Blade sidebar this step used to mean is gone; what is left are deep links between React pages, which are `<Link>`s.)
4. If the page replaces something server-rendered, delete its views, routes and controller methods. Keep `print`/`pdf`, and give any URL that could have been bookmarked a redirect into the shell rather than a 404.
5. Port the deleted routes' authorization tests onto the new API endpoints — never just delete them — and add the URLs to `tests/Feature/BladePagesStillRenderTest.php`, which fails if a supposedly-deleted Blade URL still answers or a route that should have moved to the API still exists.

### 13. There is no Blade page mechanism left — React only
This slot used to describe a Blade mobile/desktop split (a `viewport` cookie, a
`resolveView()` helper, a parallel `resources/views/mobile/` tree) for modules
that had not converted yet. Every module has converted, so all of it has been
deleted: the helper file, the cookie script, the mobile view tree, and
`layouts/app.blade.php` itself along with the last two pages that used it.
`app/View/Components/AppLayout.php` went with them.

What that means for new work:

- **There is no shared Blade chrome.** A new Blade page has nothing to extend.
  New pages are React (gotcha #12), full stop.
- The only Blade left is the SPA host page (`app-shell`), the Breeze auth pages
  on `layouts/guest`, the DomPDF/print documents, the public token RFQ portal,
  and the two mail views. Each is Blade for a reason it cannot stop being:
  it establishes the session, it is a PDF, it renders outside the shell, or it
  is an email.
- The design spec `docs/superpowers/specs/2026-08-03-blade-mobile-desktop-split-design.md`
  is history now, not a plan.
