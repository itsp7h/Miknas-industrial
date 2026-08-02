# Mobile View + Live-Everywhere — Design Spec

**Date:** 2026-08-02
**Supersedes (in part):** `2026-07-29-react-spa-migration-design.md`

## Overview

Amends and continues the React SPA migration with two changes learned since 2026-07-29:

1. **Mobile is now in scope.** The prior spec's non-goal ("no mobile app / native client — web-only, responsive") is reversed. Every converted page must have a genuinely separate mobile component, not a CSS-responsive variant of one file.
2. **Cutover mechanics change.** The prior spec allowed old Blade views and new React views to coexist in the sidebar until a module fully migrated. In practice (Suppliers, commit `575eb7a`), this produced two confusing sidebar entries for one entity and was reverted. Going forward: **a module's Blade pages are deleted in the same change that ships its React replacement.** No coexistence period.

Everything else in the prior spec (Sanctum SPA auth, Reverb, Echo, shared `ui/` component library, domain component families, testing approach) stands and is reused as-is.

## Standing Rule (→ CLAUDE.md)

- All new/converted frontend work is React, not Blade.
- Every page has a desktop version and a mobile version as **separate files** — `pages/desktop/...` vs `pages/mobile/...` — never one file branching on device inline.
- A `useViewport()` hook decides which tree renders, breakpoint **768px**. Resizing/rotating swaps the tree live — no reload, no route change.
- Any state that changes in the database (new record, status change, notification) reaches the screen via Reverb broadcast + Echo, not polling.
- Modules convert one at a time, full cutover: a module's Blade routes/views/controllers are deleted the same PR its React equivalent ships. Never side-by-side.

## Architecture: App Shell

`layouts/app.blade.php` (sidebar, topbar, notification bell, toast container — currently Blade + vanilla JS with 30s polling) is replaced by the existing minimal `app-shell.blade.php`, which only mounts React at `#react-app`. The shell itself becomes React so mobile/desktop switching and live notifications work everywhere, not just inside page content.

```
resources/js-app/
  hooks/
    useViewport.js            returns 'mobile' | 'desktop', live on resize
  layouts/
    AppShell.jsx              picks Desktop/Mobile shell below, renders <Outlet/>
    DesktopShell.jsx          sidebar + topbar (ported from current Blade look)
    MobileShell.jsx           hamburger menu + bottom tab bar
  components/
    NotificationBell.jsx      Reverb-pushed, replaces 30s poll
    ToastProvider.jsx         (exists — reused)
  pages/
    desktop/DashboardPage.jsx  (moved from pages/, ported)
    mobile/DashboardPage.jsx   (new)
    desktop/purchase/          Phase 1 (this spec)
    mobile/purchase/           Phase 1 (this spec)
  echo.js, api/client.js       (exist — reused)
```

React Router owns navigation for every converted module (e.g. `/app/purchase/suppliers`). Auth (login/logout/password reset) stays Blade/Breeze — outside the sidebar shell, not part of this effort.

## Phase 1 Scope: Shell + Purchase Module

**Backend:**
- New `ShouldBroadcast` events, fired from existing controllers immediately after each write: `SupplierSaved`, `PurchaseRequestSaved`, `PurchaseRequestApproved`, `PurchaseRequestRejected`, `PurchaseOrderSaved`, `GrnSaved`, `GrnConfirmed`, `SupplierInvoiceSaved`, `SupplierPaymentSaved`.
- One shared private broadcast channel, `purchase`, authorized for any user with purchase-module access (`routes/channels.php`). Per-row channels are unnecessary at this scale.
- New `Api/Purchase/*` JSON controllers (Supplier, PurchaseRequest, PurchaseOrder, Grn, SupplierInvoice, SupplierPayment) reusing existing validation/business logic. Existing `role`/`permission` middleware applies identically.
- Existing Blade `purchase/*` controllers, web routes, and views are deleted once their React replacement ships — not kept as fallback.

**Frontend:**
- Desktop pages: list/create/edit/show for all 6 Purchase entities, matching current visual style.
- Mobile pages: same 6 entities, card-based lists and full-screen forms instead of tables/modals — in `pages/mobile/purchase/`.
- Each list page subscribes to the `purchase` channel and patches its in-memory list on the matching event — no whole-page refetch.
- Data entry stays AJAX-only (existing rule #11) — maps directly to React state + `fetch`, no `<form>` submits.
- Domain component families (`SupplierRow/Card/Form/Detail`, etc.) follow the pattern already established in the prior spec, duplicated per viewport where the mobile layout genuinely differs (card vs. row), sharing logic via hooks where it doesn't.

**Out of scope for Phase 1:** Inventory, Production, Sales modules (Phases 2–4, each gets its own spec when started); Settings pages; Auth pages.

## Data Flow (live update example)

1. User A confirms a GRN → `GoodsReceiptNoteController::confirm()` saves, then fires `GrnConfirmed`.
2. Laravel broadcasts it on the `purchase` private channel via Reverb.
3. User B, viewing the GRN list on desktop or the GRN cards on mobile, has an open Echo subscription (`echo.private('purchase').listen('.grn.confirmed', ...)`) that patches the matching row/card's status in place and fires a toast.
4. No polling, no manual refresh, works identically whether User B is on the desktop or mobile tree.

## Error Handling

- Echo/Reverb connection drop: shell shows a small persistent "reconnecting…" indicator (topbar/mobile header), auto-resubscribes on reconnect; no user action required, no data loss since the API remains the source of truth on next fetch.
- Broadcast event received for a record the current filter/search excludes: patch is a no-op (list logic already handles "not in current view").
- API/broadcast payload shape mismatch (defensive): component ignores the event and logs to console rather than throwing, since a missed live update is recoverable (next full load fixes it) but a crashed page is not.

## Testing

- **Backend:** PHPUnit feature tests for each new `Api/Purchase/*` endpoint (validation, RBAC, response shape) and a test per event asserting it broadcasts on the `purchase` channel with the expected payload.
- **Frontend:** Vitest + React Testing Library.
  - `useViewport()` hook: unit test for breakpoint logic and resize handling.
  - `AppShell`/`DesktopShell`/`MobileShell`: render tests confirming correct shell mounts per viewport.
  - Per Purchase entity: existing domain-component test pattern (form validation, permission-gated rendering), once per viewport where markup differs.
  - At least one integration test per entity verifying a mocked broadcast event patches the list without a refetch.
- No E2E framework in this phase (unchanged from prior spec).

## Shell Coexistence (unconverted modules)

`layouts/app.blade.php` (Blade sidebar/topbar) is **not deleted in Phase 1** — it keeps serving Inventory, Production, Sales, and Settings pages exactly as today. `app-shell.blade.php` (the new React shell) is used only for routes already converted: Dashboard and, after Phase 1, `/app/purchase/*`. The two shells must stay visually consistent (same sidebar items, same look) so navigating between a converted and unconverted page doesn't feel jarring. `layouts/app.blade.php` is only retired once every module has moved to the React shell (i.e., after Phase 4).

## Risks

- **Two view-trees to maintain per page** (desktop + mobile) roughly doubles frontend surface area per entity — mitigated by sharing data-fetching/mutation logic in hooks, only duplicating the render layer.
- **Full-cutover-per-module** means a module's Blade pages are unavailable during that module's conversion PR — larger PRs, no gradual rollback via "keep old page around." Mitigated by shipping one module at a time and testing thoroughly before merge.
- **Two shells coexist until Phase 4 completes** — the Blade shell and React shell must be kept visually in sync by hand any time the sidebar changes, until the old one is retired.
