# Purchase Requests: Pipeline Board (React) — Design Spec

**Date:** 2026-08-02
**Follows:** `2026-08-02-mobile-view-and-live-everywhere-design.md` (Phase 1: shell + Supplier), continues the Purchase module migration.

## Scope Decomposition

Purchase Requests are far more complex than Suppliers: a 9-stage pipeline (`draft → gm_approval → rfq → quoting → comparison → lpo → receiving → payment → complete`, `app/Services/PurchaseStageService.php`), permission-gated visibility (`view-all` / `view-active-pipeline` / `view-own`, `app/Policies/PurchaseRequestPolicy.php`), RFQ invitations, supplier quote comparison, physical signatures, and LPO generation. Converting all of that in one pass repeats the mistake this migration is trying to avoid — a plan too large to review or trust as one unit.

This spec covers only the **Pipeline Board** — the read-only list view at `/purchase/pipeline` (`PurchasePipelineController::index`, currently `resources/views/purchase/pipeline/index.blade.php`) — converted to React with live updates. It does NOT cover:
- Creating a request (stays on the existing `<x-purchase.request-modal />` Blade component, per CLAUDE.md gotcha #10 — that component is explicitly the required flow and isn't being replaced here)
- The per-request detail/show page (`PurchasePipelineController::show`) — RFQ, supplier quotes, comparison, signature, LPO generation all stay Blade for now
- Approve/reject actions — these happen on the detail page, out of scope here

Each of those becomes its own future spec+plan when picked up, exactly as Inventory/Production/Sales are each their own future phase.

## What Changes

- `/app/purchase/pipeline` (new React route) replaces `/purchase/pipeline` as the primary board view. It's read-only: it lists active and completed requests grouped by stage, live-updated as requests move through stages elsewhere (e.g. an approval on the still-Blade detail page).
- Clicking a request in the React board navigates (full page nav, not a React route) to the existing Blade `/purchase/pipeline/{purchaseRequest}` detail page — same cross-shell-boundary link pattern already used for Suppliers→Inventory navigation in Phase 1.
- The Blade sidebar's "Pipeline" nav link (and the React shell's nav, from `navItems.js`) both point at the new `/app/purchase/pipeline` route.
- The old Blade `/purchase/pipeline` route/view are NOT deleted this time — unlike Suppliers, the detail/show page still needs a working "back to pipeline" link and the Blade board view might still be linked from elsewhere. Confirm during implementation whether anything else links to `purchase.pipeline.index` before deciding whether to delete it (if nothing does, delete it for full parity with the "no coexistence" rule; if something still depends on it, that dependency itself becomes a small task to fix first).

## Backend

- New broadcast events mirroring the `Supplier` pattern: `PurchaseRequestStageChanged` (fires whenever `PurchaseStageService::setStage`/`advance`/`setStageIfNotPast` changes a request's stage — hook into the service itself, not each call site, so nothing can bypass it) broadcasting `{id, request_number, stage}` on the shared `purchase` channel as `.purchase-request.stage-changed`. Also `PurchaseRequestCreated` (fires from the existing `store()` in `PurchaseRequestController`, unchanged Blade flow) so a new request appears on the React board live without the board's own reload — broadcasting the fields the board needs to render a row: `{id, request_number, date, project_name, requested_by_name, stage}`.
- New read-only `Api/Purchase/PurchasePipelineController@index` — mirrors `PurchasePipelineController::withRelations()`'s permission filtering exactly (reuse the same query logic, don't reimplement the permission gate), returns `{active: [...], completed: [...]}` shaped for the board (no need for the full eager-loaded relations the Blade `show` page needs — a lighter resource is fine for the board: id, request_number, date, project_name, requested_by_name, stage, department).

## Frontend

**Resolved (checked the actual Blade view, `resources/views/purchase/pipeline/index.blade.php` — it is much simpler than a kanban board):** it's a two-tab page (Active / Completed, tab-switched client-side already), each tab a plain table of requests via a shared `_table` partial, with a `stage-pill` badge column. No multi-column kanban exists today.

- `pages/desktop/purchase/PipelineBoardPage.jsx` — two tabs (Active/Completed, counts in each tab label), each a `Table` (existing shared component) with columns: request number, date, project, requested by, department, stage (rendered as a colored pill matching the existing stage-pill visual style).
- `pages/mobile/purchase/PipelineBoardPage.jsx` — same two tabs, card list instead of a table (existing mobile pattern from Suppliers).
- Both subscribe to `.purchase-request.stage-changed` (patch the matching request's `stage` in place, and move it between the active/completed lists if the new stage crosses that boundary) and `.purchase-request.created` (insert into the active list) on the `purchase` channel, via `useLiveList` (extend it if its current shape doesn't fit a two-list read model, or use two instances of it, one per tab, whichever is simpler given its actual current implementation — check before deciding).
- Clicking a row navigates via a plain `<a href="/purchase/pipeline/{id}">` (real navigation, not React Router) to the still-Blade detail page — matching the established shell-coexistence pattern.
- **"New Request" button:** the existing `<x-purchase.request-modal />` Blade component opens via a plain global `window.mprModalOpen()` function (confirmed in `resources/views/components/purchase/request-modal.blade.php:37,330`) — it is NOT React-aware and isn't being ported. Include `<x-purchase.request-modal />` once in `app-shell.blade.php` (outside the React mount div, since it's plain Blade+Alpine and works independently), and have the React board's "New Request" button call `window.mprModalOpen()` directly. **Confirmed:** the modal's form (`id="mpr-modal-form"`) is a genuine HTML `<form>` submit, not AJAX/fetch — submitting it does a real full-page POST to `purchase.requests.store`, which redirects to `purchase.requests.index`, which itself redirects to the still-Blade `purchase.pipeline.index`. So a user creating a request from the new React board is bounced to the old Blade board on success, not back to the React one. Accept this for this slice (request creation showing up live via `.purchase-request.created` for OTHER open sessions is the actual live-update goal here, not a seamless no-reload experience for the submitter) — porting the modal to a real AJAX/React flow is out of scope, deferred to a future spec alongside the rest of the create/RFQ/quotes/signature work.

## Non-Goals (this spec)

- No React port of the request-creation modal, RFQ, supplier quotes, comparison, signature, or LPO generation flows.
- No permission-model changes — the API endpoint reuses the exact existing Blade controller's authorization logic.
- No mobile kanban board — mobile gets a stage-filtered list, a deliberately different (not degraded) mobile-appropriate pattern.
