# Blade Mobile/Desktop Page Split — Design Spec

**Date:** 2026-08-03
**Relates to:** `2026-08-02-mobile-view-and-live-everywhere-design.md` (the React shell's mobile/desktop split, CLAUDE.md gotcha #12) — this spec brings the same *outcome* (a dedicated, professionally-designed mobile page for every screen) to the modules that are still Blade, using a mechanism suited to server-rendered pages instead of React's live `useViewport()` swap.

## Scope Decomposition

This covers every still-Blade page across four modules — Production, Inventory, Sales, and the Purchase pages not yet converted to React. That's ~30 view files, each needing its own hand-designed mobile counterpart. Doing all of it as one implementation plan would be too large to review as a unit, so this spec defines the **shared architecture once** (switching mechanism, file layout, design language, CLAUDE.md rule) and the **full page inventory and rollout order**; each module then gets its own implementation plan, executed one at a time. Production is first (Task order confirmed with the user), covering the page that most recently broke on mobile.

## Switching Mechanism

Blade renders server-side once per request — there's no equivalent to React's `useViewport()` hook swapping a mounted tree live on resize. Instead:

1. **Detection + cookie:** a script in `layouts/app.blade.php` (added once, used by every page) measures `window.innerWidth` on load, buckets it at the same 768px breakpoint the React shell already uses (`< 768` → `mobile`, else `desktop`), and compares that to a `viewport` cookie. If the cookie is missing or doesn't match, it sets the cookie and reloads the page once — guarded by a `sessionStorage` flag (`viewport_reload_done`) so a detection mismatch can never loop. This self-corrects on rotation or a resized desktop window, at the cost of one reload when the mismatch is first detected — an acceptable tradeoff for server-rendered pages (unlike React, there's no "live" option that doesn't also double every page's markup and JS).
2. **View resolution helper:** `resolveView(string $view, array $data = [])` (new, `app/Support/resolveView.php` or a small class — implementation plan decides), used in controllers instead of calling `view($view, $data)` directly. It checks `request()->cookie('viewport') === 'mobile'` AND `View::exists("mobile.$view")`; only when both are true does it render `"mobile.$view"` — otherwise it renders `$view` (the desktop version) unchanged. This means every page keeps working exactly as today until its specific mobile counterpart is added, which is what makes a genuinely incremental, one-page-at-a-time rollout possible.
3. **Middleware is not needed** — the cookie is read directly by `resolveView()` at render time; no request-lifecycle changes.

## File Layout

Desktop views are never moved or renamed. A page's mobile counterpart lives at the same relative path under a new `mobile/` root:

```
resources/views/production/orders/index.blade.php        (desktop, unchanged)
resources/views/mobile/production/orders/index.blade.php  (new)
```

Both extend `layouts.app` (the shared shell — sidebar/topbar/bottom-tab-bar — is out of scope here; it already has its own mobile treatment from the earlier "mobile layout overflow" fix). Only the `@section('content')` body differs between the two.

## Mobile Design Language

Not a shrunk desktop page — each mobile view is redesigned for touch and a narrow viewport, following the pattern already established by the React mobile Pipeline Board (`pages/mobile/purchase/PipelineBoardPage.jsx`):

- **Card lists instead of wide tables.** Every `index` page's `<table>` becomes a vertically stacked list of cards (one record per card, key fields shown, secondary fields de-emphasized), eliminating horizontal scrolling entirely rather than relying on `overflow-x-auto`.
- **Single-column forms.** `create`/`edit` pages drop any side-by-side field grid in favor of one field per row, larger touch targets (min 44px tap height), and full-width primary actions pinned near the thumb (bottom of the form, not top-right).
- **Bottom-sheet-style modals** instead of centered desktop modals, where a page has an in-page modal (matching the mobile board's mobile-first modal patterns already in the codebase).
- **Client-side search stays client-side** (CLAUDE.md gotcha #6 applies unchanged) — mobile search inputs behave identically, just restyled for full width.
- **Hero-style page header** for `index` pages (gradient card, title, primary action) matching the mobile Pipeline Board's header, replacing the desktop card-with-title-bar header.
- No native `alert()`/`confirm()`/`prompt()` (gotcha #7), no inline session banners (gotcha #9) — same rules as desktop, unchanged.

## CLAUDE.md Rule Addition

A new gotcha (added in the first implementation task, before any page work) states: once a Blade page has a `mobile/` counterpart, any change to shared data, fields, validation, or actions must be applied to **both** files — each restyled for its own platform, not copy-pasted verbatim. This mirrors the existing React rule (gotcha #12) for the Blade side of the app.

## Full Page Inventory & Rollout Order

Excluded from all phases: `*/pdf.blade.php` and `*/print.blade.php` files (print/PDF output, not browsed on a phone), and `purchase/pipeline/show.blade.php` (being deleted by the separate Pipeline-detail-page React plan, not redesigned here).

**Phase 1 — Production (9 files, first implementation plan):**
`production/orders/{index,create,edit,show}`, `production/bom/{index,create,edit}`, `production/material-issues/index`, `production/outputs/index`.

**Phase 2 — Inventory (10 files):**
`inventory/items/{index,create,edit}`, `inventory/warehouses/{index,create,edit}`, `inventory/movements/{index,create}`, `inventory/reports/{summary,movement,low-stock,valuation}`.

**Phase 3 — Sales (13 files):**
`sales/customers/{index,create,edit}`, `sales/orders/{index,create,edit,show}`, `sales/delivery-notes/{index,create,edit}`, `sales/invoices/{index,create,edit}`, `sales/payments/{index,create}`.

**Phase 4 — remaining Purchase Blade pages (11 files):**
`purchase/requests/{index,create,edit,show}`, `purchase/orders/{index,create,edit,show}`, `purchase/grns/{index,create,show}`, `purchase/invoices/{index,create,edit}`, `purchase/payments/{index,create}`, `purchase/quotes/workspace`, `purchase/rfq/show`, `purchase/signature/show`, `purchase/suppliers/{create,edit}` (the suppliers *list* is already React — only its create/edit forms, if still Blade, are in scope; confirm during Phase 4 planning). Any new stopgap pages created by the separate Pipeline-detail plan (`rfq/select-suppliers`, `grn/select`, `orders/generate-confirm`) get their mobile counterparts added to this phase too, since they won't exist yet when this spec is written.

Each phase is its own implementation plan, written and executed after the prior phase's plan completes — not all four at once.

## Non-Goals

- No redesign of the shared shell (sidebar, topbar, bottom-tab-bar) — already has mobile handling from the earlier overflow fix.
- No changes to backend logic, validation rules, or routes — this is view-layer only. A page's controller keeps its existing `view()` call sites replaced 1:1 with `resolveView()`; no new business logic.
- No live (no-reload) viewport switching — one reload on mismatch is accepted, per the Switching Mechanism decision above.
- Print/PDF views are not touched.
