# Supplier Import/Export/Delete Follow-Up — Design Spec

**Date:** 2026-08-02
**Follows:** `2026-08-02-mobile-view-and-live-everywhere-design.md`, closes the accepted gap from that phase's final review.

## Overview

Phase 1 of the React migration converted the Supplier page to React (list/create/edit, live updates) and deleted the old Blade page. The old Blade page also had four capabilities the React page never got: Excel import, template download, PDF export, and delete. This was accepted as a known gap (import stayed usable via `php artisan suppliers:import`) with a follow-up planned. This spec is that follow-up.

Also folds in `secondary_email`, `phone2`, `whatsapp`, `website`, `credit_terms`, `remarks` — fields the old Blade form had that the React form doesn't (found by the same final review, deferred alongside the import/export/delete gap). `is_active`/`tax_number` were already added in the Phase 1 fix wave; this is the rest of the field set.

## What the old Blade controller did (reference, being ported to API)

- `store()`/`update()` accepted: `supplier_code, name, category, contact_person, email, secondary_email, phone, phone2, whatsapp, whatsapp_number, address, website, tax_number, credit_terms, credit_days, remarks, is_active`.
- `destroy(Supplier $supplier)` — hard delete, redirect with flash message.
- `import(Request $request)` — validates `file` (xlsx/xls, max 10MB), calls `SupplierImportService::import($path)` returning `{imported, updated, skipped}`, flashes a summary message.
- `downloadTemplate()` — generates (if missing) and streams `storage/app/suppliers_template.xlsx` via `artisan suppliers:template`.
- `exportPdf()` — renders `purchase.suppliers.pdf` (a Blade view, still exists — not deleted, since only the controller/CRUD views were removed... verify this) via dompdf, landscape A4, downloads `suppliers_YYYY-MM-DD.pdf`.

## Architecture

All four capabilities become API endpoints under `/api/v1/purchase/suppliers/*`, called from the React `SupplierListPage` (both desktop and mobile — file upload and downloads work identically on both, no viewport-specific UI needed beyond button placement):

- `DELETE /api/v1/purchase/suppliers/{supplier}` — hard delete, fires `SupplierSaved`-sibling event `SupplierDeleted` (new event, broadcasts `{id}` only, so other open list pages remove the row live) on the `purchase` channel as `.supplier.deleted`.
- `POST /api/v1/purchase/suppliers/import` — accepts multipart `file`, reuses `SupplierImportService::import()` unchanged, returns `{imported, updated, skipped}` as JSON (no redirect/flash — the frontend shows a toast from the JSON response). Do NOT fire per-row `SupplierSaved` events for every imported/updated row (could be hundreds) — instead fire one summary broadcast, or simplest: let the frontend that triggered the import refetch its own list once after a successful import (not live-broadcast to other viewers, since a bulk import isn't a live single-user-visible interaction in the same way a single CRUD write is — acceptable to not broadcast this one).
- `GET /api/v1/purchase/suppliers/template` — same `artisan suppliers:template` generate-if-missing + stream logic, returns the file as a binary download response (not JSON) — the frontend triggers this via a plain `<a href="/api/v1/purchase/suppliers/template">` or `window.location`, not `fetch`, since file downloads don't need AJAX handling.
- `GET /api/v1/purchase/suppliers/export-pdf` — same dompdf rendering, binary PDF download response, triggered the same way (plain link/`window.location`, not fetch).

**Resolved:** `resources/views/purchase/suppliers/pdf.blade.php` was deleted in Phase 1's Task 9 cutover (confirmed via `git show 10b142f --stat`). It must be restored — recover its content via `git show 10b142f~1:resources/views/purchase/suppliers/pdf.blade.php` and re-add it unchanged at the same path, since dompdf views are plain Blade with no dependency on the deleted controller.

## Frontend Changes

- `SupplierListPage.jsx` (desktop): add a toolbar row above the table with "Import" (file input trigger + hidden `<input type="file">`), "Download Template" (plain link), "Export PDF" (plain link), and a per-row "Delete" action (using the existing `ConfirmModal` component per CLAUDE.md gotcha #7 — never `confirm()`).
- `SupplierListPage.jsx` (mobile): same four actions, laid out for mobile (e.g. an overflow/kebab menu given limited width, or a simple stacked action row — implementer's call, following existing mobile patterns in this codebase).
- `SupplierForm.jsx`: add the six remaining fields (`secondary_email, phone2, whatsapp, website, credit_terms, remarks`) using the existing `FormField` component, matching the old Blade form's field order/labels.
- Delete removes the row from local list state immediately on success (optimistic or on the 200 response), AND is picked up live by other open sessions via the new `.supplier.deleted` broadcast.
- Import shows a success toast with the returned `{imported, updated, skipped}` summary, then refetches the current page's own supplier list once.

## Backend Changes

- `app/Http/Controllers/Api/Purchase/SupplierController.php`: add `destroy`, `import`, `downloadTemplate`, `exportPdf` methods; widen `store`/`update` validation to the full old field set.
- `app/Http/Resources/SupplierResource.php`: add the six new fields to the returned payload.
- `app/Events/SupplierDeleted.php`: new, mirrors `SupplierSaved`'s shape but payload is just `{id}`.
- `routes/api.php`: add the four new routes inside the existing `purchase` group. Import/template/export-pdf routes must be declared in a way that doesn't conflict with the `{supplier}` wildcard on `DELETE .../{supplier}` (same gotcha as CLAUDE.md #3 — custom routes before resource-style wildcards; since these are all explicit method+path combos rather than a `Route::resource()`, verify no route-order collision, e.g. `GET .../template` vs `PUT .../{supplier}` are different HTTP verbs so no collision, but double check `DELETE .../{supplier}` doesn't accidentally match something else).

## Testing

- Backend: feature tests for `destroy` (200, row gone, `SupplierDeleted` broadcast asserted), `import` (valid file → summary JSON, invalid file → 422), `downloadTemplate` (200, correct content-type/filename), `exportPdf` (200, correct content-type/filename). Reuse existing `SupplierImportService` — no changes to it, so no new tests needed there beyond the controller wiring.
- Frontend: `SupplierForm` test additions for the six new fields (at minimum, confirm they render and submit). `SupplierListPage` tests for: delete confirmation flow (opens `ConfirmModal`, confirms, row removed), a `.supplier.deleted` broadcast removing a row live, import flow (file selected, success toast with summary, list refetches), and the two download links pointing at the correct URLs (a full `window.location`/browser-navigation download isn't practically testable in jsdom — assert the link `href` is correct instead of simulating the actual download).

## Non-Goals

- No bulk-select delete, no drag-and-drop import — matching the old Blade page's UI exactly, not adding anything beyond parity.
- No live-broadcasting of individual imported/updated rows during a bulk import.
