# Supplier Import/Export/Delete Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restore Excel import, template download, PDF export, and delete for the React Supplier page — the gap accepted when Phase 1 cut over from Blade — plus the remaining old-form fields (`secondary_email, phone2, whatsapp, website, credit_terms, remarks`).

**Architecture:** New endpoints on the existing `Api/Purchase/SupplierController`, a new `SupplierDeleted` broadcast event mirroring `SupplierSaved`, restoring the deleted `pdf.blade.php` view for dompdf reuse, and toolbar/field additions to the existing React `SupplierListPage`/`SupplierForm` (both desktop and mobile).

**Tech Stack:** Laravel 12 (dompdf, PhpSpreadsheet via existing `SupplierImportService`), React 19, Vitest, PHPUnit.

## Global Constraints

- No `alert()`/`confirm()`/`prompt()` — delete uses the existing `ConfirmModal` component. — CLAUDE.md gotcha #7
- Data entry is AJAX-only; downloads (template/PDF) are plain links/`window.location`, not fetch — file streaming responses don't fit the JSON AJAX pattern. — CLAUDE.md gotcha #11, adapted for binary downloads
- Any state change other users should see live goes through a Reverb broadcast — applies to delete (single-row, broadcasts live) but NOT to bulk import (explicitly out of scope for live-broadcasting per the design spec).
- Custom routes must not collide with wildcard routes — verify `template`/`export-pdf`/`import` (all distinct paths, not `{supplier}`-shaped) don't collide with `DELETE .../{supplier}`. — CLAUDE.md gotcha #3, adapted

---

### Task 1: Restore `pdf.blade.php` + backend `exportPdf`/`downloadTemplate`/`import`/`destroy` endpoints

**Files:**
- Create: `resources/views/purchase/suppliers/pdf.blade.php` (restored from git history, unchanged)
- Create: `app/Events/SupplierDeleted.php`
- Modify: `app/Http/Controllers/Api/Purchase/SupplierController.php`
- Modify: `app/Http/Resources/SupplierResource.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/Purchase/SupplierControllerTest.php` (extend existing file)
- Test: `tests/Feature/SupplierDeletedBroadcastTest.php`

**Interfaces:**
- Produces: `DELETE /api/v1/purchase/suppliers/{supplier}` → 204, fires `SupplierDeleted{id}` on `private-purchase` as `.supplier.deleted`.
- Produces: `POST /api/v1/purchase/suppliers/import` → `{imported, updated, skipped}` (200) or `{message, errors}` (422 on invalid file).
- Produces: `GET /api/v1/purchase/suppliers/template` → binary xlsx download.
- Produces: `GET /api/v1/purchase/suppliers/export-pdf` → binary PDF download.
- Produces: `SupplierResource` now also returns `secondary_email, phone2, whatsapp, website, credit_terms, remarks`.

- [ ] **Step 1: Restore the deleted PDF view**

```bash
git show 10b142f~1:resources/views/purchase/suppliers/pdf.blade.php > resources/views/purchase/suppliers/pdf.blade.php
mkdir -p resources/views/purchase/suppliers
```
(Create the directory first if `git show >` fails because it doesn't exist yet — reorder as needed.)

- [ ] **Step 2: Write the failing `SupplierDeleted` event test**

```php
<?php
// tests/Feature/SupplierDeletedBroadcastTest.php

namespace Tests\Feature;

use App\Events\SupplierDeleted;
use Tests\TestCase;

class SupplierDeletedBroadcastTest extends TestCase
{
    public function test_it_broadcasts_the_deleted_suppliers_id_on_the_purchase_channel(): void
    {
        $event = new SupplierDeleted(42);

        $channels = $event->broadcastOn();

        $this->assertSame('private-purchase', $channels[0]->name());
        $this->assertSame('supplier.deleted', $event->broadcastAs());
        $this->assertSame(['id' => 42], $event->broadcastWith());
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test --filter=SupplierDeletedBroadcastTest`
Expected: FAIL — class not found

- [ ] **Step 4: Write the event**

```php
<?php
// app/Events/SupplierDeleted.php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class SupplierDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $supplierId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('purchase')];
    }

    public function broadcastAs(): string
    {
        return 'supplier.deleted';
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->supplierId];
    }
}
```

Note: uses `ShouldBroadcastNow` (not `ShouldBroadcast`), matching the fix applied to `SupplierSaved`/`NotificationPushed` in Phase 1 — queued broadcasting silently never delivers without a running queue worker.

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=SupplierDeletedBroadcastTest`

- [ ] **Step 6: Write the failing controller tests**

```php
<?php
// Add to tests/Feature/Api/Purchase/SupplierControllerTest.php

use App\Events\SupplierDeleted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

public function test_destroy_deletes_a_supplier_and_broadcasts(): void
{
    Event::fake([SupplierDeleted::class]);
    $this->actingUser();
    $supplier = Supplier::factory()->create();

    $response = $this->deleteJson("/api/v1/purchase/suppliers/{$supplier->id}");

    $response->assertNoContent();
    $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
    Event::assertDispatched(SupplierDeleted::class, fn ($e) => $e->supplierId === $supplier->id);
}

public function test_import_rejects_a_non_excel_file(): void
{
    $this->actingUser();

    $response = $this->postJson('/api/v1/purchase/suppliers/import', [
        'file' => UploadedFile::fake()->create('not-excel.txt', 10),
    ]);

    $response->assertStatus(422);
}

public function test_download_template_returns_a_file(): void
{
    $this->actingUser();

    $response = $this->get('/api/v1/purchase/suppliers/template');

    $response->assertOk();
    $response->assertHeader('content-disposition');
}

public function test_export_pdf_returns_a_pdf(): void
{
    $this->actingUser();
    Supplier::factory()->count(2)->create();

    $response = $this->get('/api/v1/purchase/suppliers/export-pdf');

    $response->assertOk();
    $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
}
```

- [ ] **Step 7: Run tests to verify they fail**

Run: `php artisan test --filter=SupplierControllerTest`
Expected: FAIL — routes/methods don't exist (404)

- [ ] **Step 8: Add the controller methods**

```php
// app/Http/Controllers/Api/Purchase/SupplierController.php — add these methods,
// and add `use App\Events\SupplierDeleted;`, `use App\Services\SupplierImportService;`,
// `use Barryvdh\DomPDF\Facade\Pdf;`, `use Illuminate\Support\Facades\Artisan;` to the top

public function destroy(Supplier $supplier)
{
    $id = $supplier->id;
    $supplier->delete();

    event(new SupplierDeleted($id));

    return response()->noContent();
}

public function import(Request $request)
{
    $request->validate([
        'file' => 'required|file|mimes:xlsx,xls|max:10240',
    ]);

    try {
        $result = app(SupplierImportService::class)->import(
            $request->file('file')->getPathname()
        );

        return response()->json($result);
    } catch (\Exception $e) {
        return response()->json(['message' => 'Import failed: '.$e->getMessage()], 422);
    }
}

public function downloadTemplate()
{
    $path = storage_path('app/suppliers_template.xlsx');

    if (! file_exists($path)) {
        Artisan::call('suppliers:template');
    }

    return response()->download($path, 'suppliers_import_template.xlsx');
}

public function exportPdf()
{
    $suppliers = Supplier::orderBy('name')->get();

    $pdf = Pdf::loadView('purchase.suppliers.pdf', compact('suppliers'))
        ->setPaper('a4', 'landscape');

    return $pdf->download('suppliers_'.now()->format('Y-m-d').'.pdf');
}
```

Also widen `store()`/`update()` validation to add: `secondary_email` (nullable|email|max:255), `phone2` (nullable|string|max:20), `whatsapp` (nullable|string|max:20), `website` (nullable|string|max:255), `credit_terms` (nullable|string|max:255), `remarks` (nullable|string), matching the old Blade controller's accepted field list (`tax_number`/`is_active` already added in Phase 1's fix wave — don't duplicate).

- [ ] **Step 9: Add the six new fields to `SupplierResource`**

```php
// app/Http/Resources/SupplierResource.php — add to the returned array:
'secondary_email' => $this->secondary_email,
'phone2' => $this->phone2,
'whatsapp' => $this->whatsapp,
'website' => $this->website,
'credit_terms' => $this->credit_terms,
'remarks' => $this->remarks,
```

- [ ] **Step 10: Register the routes**

```php
// routes/api.php — inside the existing purchase prefix group, alongside the suppliers routes
Route::delete('suppliers/{supplier}', [\App\Http\Controllers\Api\Purchase\SupplierController::class, 'destroy']);
Route::post('suppliers/import', [\App\Http\Controllers\Api\Purchase\SupplierController::class, 'import']);
Route::get('suppliers/template', [\App\Http\Controllers\Api\Purchase\SupplierController::class, 'downloadTemplate']);
Route::get('suppliers/export-pdf', [\App\Http\Controllers\Api\Purchase\SupplierController::class, 'exportPdf']);
```

Verify no collision: `template`/`export-pdf`/`import` are literal path segments on `GET`/`POST`, distinct from the `{supplier}` wildcard on `DELETE`/`PUT` — different HTTP verbs and different literal vs. wildcard segments, so Laravel resolves them unambiguously regardless of declaration order. Still, declare them before nothing else conflicts (no `Route::apiResource` exists here to worry about, per Task 6's original plan).

- [ ] **Step 11: Run tests to verify they pass**

Run: `php artisan test --filter=SupplierControllerTest`
Run: `php artisan test` (full suite, confirm no regressions)

- [ ] **Step 12: Commit**

```bash
git add resources/views/purchase/suppliers/pdf.blade.php app/Events/SupplierDeleted.php app/Http/Controllers/Api/Purchase/SupplierController.php app/Http/Resources/SupplierResource.php routes/api.php tests/Feature/Api/Purchase/SupplierControllerTest.php tests/Feature/SupplierDeletedBroadcastTest.php
git commit -m "feat: add Supplier delete/import/template/PDF-export API endpoints"
```

---

### Task 2: Frontend — delete, import, template download, PDF export, remaining fields (desktop + mobile)

**Files:**
- Modify: `resources/js-app/components/purchase/supplier/SupplierForm.jsx`
- Modify: `resources/js-app/components/purchase/supplier/SupplierForm.test.jsx`
- Modify: `resources/js-app/pages/desktop/purchase/SupplierListPage.jsx`
- Modify: `resources/js-app/pages/desktop/purchase/SupplierListPage.test.jsx`
- Modify: `resources/js-app/pages/mobile/purchase/SupplierListPage.jsx`
- Modify: `resources/js-app/pages/mobile/purchase/SupplierListPage.test.jsx`
- Modify: `resources/js-app/hooks/useLiveList.js` (if delete needs list-removal support the hook doesn't currently have — check first)

**Interfaces:**
- Consumes: `apiDelete` (add to `api/client.js` if not already present — check first), `ConfirmModal` (existing shared component), the four new backend endpoints from Task 1.

- [ ] **Step 1: Check `api/client.js` for `apiDelete`**

Run: `grep -n "apiDelete" resources/js-app/api/client.js`. If missing, add:

```javascript
export const apiDelete = (path) => request(path, { method: 'DELETE' });
```

- [ ] **Step 2: Check `useLiveList.js` for a remove/upsert-by-id-removal capability**

Read the hook's current implementation. If it only supports upsert (add/update), add a `removeItem(id)` function to its returned object that filters the item out of state, and wire the hook's Echo subscription to also listen for a `deleteEvent` name (make it a new optional hook parameter, e.g. `deleteEvent: '.supplier.deleted'`, calling `removeItem(payload.id)` when it fires) — following the same pattern the hook already uses for the upsert event.

- [ ] **Step 3: Write failing tests for `SupplierForm`'s new fields**

Add to `SupplierForm.test.jsx`: a test asserting `secondary_email`, `phone2`, `whatsapp`, `website`, `credit_terms`, `remarks` fields render and are included in the submitted payload (extend the existing "posts a new supplier" test's assertion, or add a new one following the same pattern as the existing `tax_number` test from Phase 1's fix wave).

- [ ] **Step 4: Run test to verify it fails, then add the fields to `SupplierForm.jsx`**

Add six `FormField` entries matching the existing pattern (label, name, value, onChange), placed after the existing fields in a sensible order (matching the old Blade form: contact fields grouped, then business fields). Update the component's `BLANK`/`normalize()` handling to include the new field names.

- [ ] **Step 5: Run test to verify it passes**

Run: `npm run test -- SupplierForm`

- [ ] **Step 6: Write failing tests for delete + import + download links (desktop page)**

```jsx
// Add to resources/js-app/pages/desktop/purchase/SupplierListPage.test.jsx

it('deletes a supplier after confirming', async () => {
    vi.spyOn(client, 'apiGet').mockResolvedValue({ data: [{ id: 1, name: 'Acme Steel', category: null, is_active: true }] });
    vi.spyOn(client, 'apiDelete').mockResolvedValue({});

    render(<ToastProvider><SupplierListPage /></ToastProvider>);
    await waitFor(() => expect(screen.getByText('Acme Steel')).toBeInTheDocument());

    fireEvent.click(screen.getByText('Delete'));
    fireEvent.click(screen.getByText('Confirm')); // or whatever ConfirmModal's confirm button text is — check the component first

    await waitFor(() => expect(client.apiDelete).toHaveBeenCalledWith('/purchase/suppliers/1'));
    await waitFor(() => expect(screen.queryByText('Acme Steel')).not.toBeInTheDocument());
});

it('renders template and PDF export as plain download links', async () => {
    vi.spyOn(client, 'apiGet').mockResolvedValue({ data: [] });

    render(<ToastProvider><SupplierListPage /></ToastProvider>);

    expect(screen.getByText('Download Template').closest('a')).toHaveAttribute('href', '/api/v1/purchase/suppliers/template');
    expect(screen.getByText('Export PDF').closest('a')).toHaveAttribute('href', '/api/v1/purchase/suppliers/export-pdf');
});

it('imports a file and shows a summary toast', async () => {
    vi.spyOn(client, 'apiGet').mockResolvedValue({ data: [] });
    const apiPostSpy = vi.spyOn(client, 'apiPostForm') // check api/client.js — file uploads need FormData, not JSON apiPost; add apiPostForm if it doesn't exist, following the same pattern as apiPost but sending FormData without a Content-Type header override
        .mockResolvedValue({ imported: 2, updated: 1, skipped: 0 });

    render(<ToastProvider><SupplierListPage /></ToastProvider>);
    const file = new File(['dummy'], 'suppliers.xlsx', { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
    fireEvent.change(screen.getByLabelText('Import'), { target: { files: [file] } });

    await waitFor(() => expect(apiPostSpy).toHaveBeenCalled());
    await waitFor(() => expect(screen.getByText(/2 added, 1 updated/i)).toBeInTheDocument());
});
```

Check `api/client.js` first — if it lacks a multipart/FormData POST helper, add `apiPostForm(path, formData)` alongside the existing `apiPost`, reusing the same CSRF-cookie logic but passing `formData` as the body without setting `Content-Type` (the browser sets the multipart boundary automatically).

- [ ] **Step 7: Run tests to verify they fail**

Run: `npm run test -- SupplierListPage`

- [ ] **Step 8: Implement the desktop page's toolbar (import/template/PDF/delete)**

Add above the `Table`:

```jsx
<div style={{ display: 'flex', gap: 8, marginBottom: 12, alignItems: 'center' }}>
    <label style={{ cursor: 'pointer' }}>
        Import
        <input type="file" accept=".xlsx,.xls" style={{ display: 'none' }} onChange={handleImport} aria-label="Import" />
    </label>
    <a href="/api/v1/purchase/suppliers/template">Download Template</a>
    <a href="/api/v1/purchase/suppliers/export-pdf">Export PDF</a>
</div>
```

Add `handleImport` (builds `FormData`, calls `apiPostForm('/purchase/suppliers/import', formData)`, shows a toast with the summary on success, refetches the list, shows an error toast on failure) and a per-row "Delete" action opening the existing `ConfirmModal` (check how `ConfirmModal` is used elsewhere in this codebase — likely another page or component already demonstrates the pattern; follow it exactly), calling `apiDelete` and removing the row from local state (or relying on the live `.supplier.deleted` broadcast via the hook's new `removeItem`, whichever the Task 1 hook change makes simpler — prefer relying on the broadcast for consistency with how create/update already work, but confirm the DELETE response's own success path also updates the local page immediately rather than waiting on the broadcast round-trip, since the current user shouldn't have to wait for their own action to echo back).

- [ ] **Step 9: Run tests to verify they pass**

Run: `npm run test -- SupplierListPage`

- [ ] **Step 10: Repeat Steps 6-9 for the mobile page**

Same four actions, laid out appropriately for the narrower mobile viewport (e.g. a simple stacked toolbar above the cards, delete as a per-card action button). Write the equivalent tests first.

- [ ] **Step 11: Run the full frontend suite**

Run: `npm run test`

- [ ] **Step 12: Commit**

```bash
git add resources/js-app
git commit -m "feat: add Supplier delete/import/template/PDF-export UI and remaining fields (desktop + mobile)"
```

---

### Task 3: Verification and cleanup

- [ ] **Step 1: Run full backend and frontend suites**

Run: `php artisan test` — expect same pass count as Phase 1's baseline plus new tests, no regressions.
Run: `npm run test` — expect same plus new tests.
Run: `npm run build` — expect clean build.

- [ ] **Step 2: Manual verification**

Via `npm run dev` + `php artisan serve`: confirm import (upload a real xlsx using the existing test fixture if one exists, or a minimal one), template download, PDF export, and delete (with live removal in a second browser session) all work end-to-end on both a desktop-width and mobile-width browser window.

- [ ] **Step 3: Commit any final fixes from manual verification, if needed**
