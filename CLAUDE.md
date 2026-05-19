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

## Controllers — `app/Http/Controllers/`

```
Controller.php
DashboardController.php
ProfileController.php
Auth/
  AuthenticatedSessionController.php
  ConfirmablePasswordController.php
  EmailVerificationNotificationController.php
  EmailVerificationPromptController.php
  NewPasswordController.php
  PasswordController.php
  PasswordResetLinkController.php
  RegisteredUserController.php
  VerifyEmailController.php
Purchase/
  SupplierController.php          import, downloadTemplate, exportPdf + CRUD
  PurchaseRequestController.php   + approve, reject, print
  PurchaseOrderController.php
  GoodsReceiptNoteController.php  + confirm
  SupplierInvoiceController.php
  SupplierPaymentController.php
Inventory/
  ItemController.php              import, downloadTemplate, exportPdf + CRUD
  WarehouseController.php
  StockMovementController.php
  StockReportController.php       summary, movement, lowStock, valuation
Production/
  ProductionOrderController.php   + start, complete
  BillOfMaterialController.php
  MaterialIssueController.php
  ProductionOutputController.php
Sales/
  CustomerController.php
  SalesOrderController.php        + confirm
  DeliveryNoteController.php      + dispatch
  SalesInvoiceController.php
  PaymentReceiptController.php
```

---

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

---

## Artisan Commands — `app/Console/Commands/`

```bash
php artisan suppliers:import "path/to/file.xlsx" [--dry-run]
php artisan suppliers:template [--output=path]     # → storage/app/suppliers_template.xlsx
php artisan items:template [--output=path]         # → storage/app/items_template.xlsx
```

Files: `ImportSuppliers.php`, `GenerateSupplierTemplate.php`, `GenerateItemTemplate.php`

---

## Routes — `routes/web.php`

All protected by `['auth', 'verified']`. Prefix groups:

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
GET/POST  orders                 purchase.orders.*
GET/POST  grns                   purchase.grns.*
PATCH     grns/{id}/confirm      purchase.grns.confirm
GET/POST  invoices               purchase.invoices.*
GET/POST  payments               purchase.payments.*
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
  orders/      index, create, edit, show
  grns/        index, create, show
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
