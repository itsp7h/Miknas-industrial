# Purchase Pipeline: Detail Page (React) — Phase 1 Design Spec

**Date:** 2026-08-03
**Follows:** `2026-08-02-purchase-requests-pipeline-board-design.md` (board), continues the Purchase module migration. Explicitly picks up the detail page that spec deferred.

## Scope Decomposition

The Pipeline detail page (`resources/views/purchase/pipeline/show.blade.php`, 977 lines) bundles a read-only header/timeline/sidebar shell, GM signature capture, and five separate action flows (RFQ supplier selection + send, quote comparison/award, LPO generation, GRN recording, payment issuance). Porting all of it in one pass repeats the mistake the board spec already called out.

**This spec (Phase 1) covers only:**
- The read-only page shell: header, stage timeline (view state + contextual sub-text per stage, no unported action buttons), and sidebar (request details, suppliers, items, quotes, linked LPOs).
- GM signature: viewing an existing signature and capturing a new one (canvas pad), since signing is the only action on the `draft` stage and has no other home.
- Full cutover of `show.blade.php` and its route/controller method — no coexisting old+new detail page.

**Explicitly NOT in this phase** (each becomes its own future spec+plan):
- Send RFQs, Compare & Award, Issue Payment — these keep working exactly as today, but reached via a real "link out" navigation from the React page's stage timeline to the existing standalone Blade pages/routes (`purchase.requests.quotes`, `purchase.requests.compare`, `purchase.payments.create`), instead of an inline modal/POST on the same page.
- Add Suppliers (RFQ selection), Record GRN (selection), and Issue LPO — today all three are POST-only actions or modals with **no standalone GET page**, only ever reachable from inside `show.blade.php` (the first two via embedded modals, the third via an inline form button). Since deleting `show.blade.php` would otherwise remove the only way to perform them, Phase 1 extracts each into a small new standalone Blade page/route (`GET requests/{purchaseRequest}/rfq/select-suppliers`, `GET requests/{purchaseRequest}/grn/select`, `GET requests/{purchaseRequest}/generate-lpo/confirm`) reusing the existing modal markup or wrapping the existing POST action in a confirm page. These are not ported to React in this phase — they're a minimal stopgap so the cutover doesn't strand any action.

## What Changes

- New route `/app/purchase/pipeline/:id` (React Router), rendered via `useViewport` as `pages/desktop/purchase/PipelinePage.jsx` or `pages/mobile/purchase/PipelinePage.jsx` — same desktop/mobile split pattern as the board.
- The board's existing detail links (`pages/desktop/purchase/PipelineBoardPage.jsx:37`, mobile `:175`) change from a plain `<a href="/purchase/pipeline/{id}">` (full page nav to Blade) to a React Router `<Link to={"/app/purchase/pipeline/"+id}>` (in-app nav — the destination is now a React route).
- `resources/views/purchase/pipeline/show.blade.php` is deleted. `PurchasePipelineController::show()` is deleted along with the `purchase.pipeline.show` route. Nothing else should reference this route after implementation — confirm during implementation and fix any remaining reference before deleting (same check the board spec used for `purchase.pipeline.index`).
- Two new minimal Blade pages/routes added (see Scope Decomposition) so Add Suppliers / Record GRN retain a working destination.
- "GM Signature" capture moves from an inline canvas embedded in Blade to a shared (non-viewport-split) React component, `components/purchase/SignaturePad.jsx` — the first canvas-based component in the React codebase.

## Backend

- New `Api/Purchase/PurchasePipelineController@show($purchaseRequest)`: `authorize('view', $purchaseRequest)`, then load the same relation graph the Blade `show()` currently loads — `requestedBy, items, signature.signedBy, rfqInvitations.supplier, supplierQuotes.supplier, supplierQuotes.items, purchaseOrders.supplier`. Route: `GET /api/purchase/pipeline/{purchaseRequest}`.
- Response shaped by a new `PurchaseRequestDetailResource`, adding to the raw model data:
  - `stage_index` / `progress_pct`, computed from `PurchaseStageService::stageIndex()` against its `STAGES` list (same math the Blade progress bar uses).
  - `permissions: {approve, manageRfq, manageQuotes, award, generateLpo, update}` — booleans evaluated server-side from `PurchaseRequestPolicy` (the same gates the Blade view checks inline today), so the React page never duplicates policy logic client-side.
- Signature capture: reuse the existing `requests.sign.store` POST logic — add (or expose) an API-accessible equivalent action returning JSON, following the existing controller's validation/authorization (`approve` gate), so `SignaturePad.jsx` can POST directly.
- No new broadcast events needed. The existing `PurchaseRequestStageChanged` (`purchase-request.stage-changed` on the `purchase` private channel, `{id, request_number, stage}`) already fires on every stage transition, including signing (`draft` → `rfq`). Since its payload is stage-only (not full detail), the detail page treats it as an invalidation signal: on receipt matching the current `id`, refetch the detail endpoint rather than trying to merge partial data.

## Frontend

- `pages/desktop/purchase/PipelinePage.jsx` / `pages/mobile/purchase/PipelinePage.jsx`, each with a co-located `.test.jsx`, both built on the existing `Card`, `Table`, `Modal`, `Button` primitives from `components/ui/`.
- New shared `components/ui/StagePill.jsx` — no pill/badge component exists yet; the board currently uses inline classes for its stage pill. Both the new detail page and (as a follow-up cleanup, not required for this spec) the board can use it.
- **Desktop layout:** header card (request number, `StagePill`, meta row, progress bar) → two-column body: stage timeline (left) + sidebar card stack (right, ~320px) — matching the current Blade layout.
- **Mobile layout:** header card stays full-width and compact; stage timeline renders as the same vertical stepper (already a single-column pattern, no rework needed); sidebar cards stack full-width *below* the timeline instead of beside it — same card order as desktop, just linearized.
- Stage timeline, both shells: for the current/relevant stage, render a "view-only" contextual line for anything already ported (signature status, RFQ/quote/comparison counts pulled from the loaded relations) and a single link-out button for anything not yet ported in this phase, pointed at its existing Blade destination:
  - `draft` / `gm_approval` → **Sign** (opens `SignaturePad.jsx` modal) or **View Signature**, using `permissions.approve`.
  - `rfq` → **Add Suppliers** (links to the new `requests/{id}/rfq/select-suppliers` page) and **Send RFQs** (links to the existing RFQ page/action), gated on `permissions.manageRfq`.
  - `quoting` → **View Quotes** (links to `purchase.requests.quotes`).
  - `comparison` → **Compare & Award** (links to `purchase.requests.compare`), gated on `permissions.manageQuotes`/`award`.
  - `lpo` → **Issue LPO** (links to the new `requests/{id}/generate-lpo/confirm` page) / **View LPO(s)** (link to `purchase.orders.show`), gated on `permissions.generateLpo`.
  - `receiving` → **Record GRN** (links to the new `requests/{id}/grn/select` page).
  - `payment` → **Issue Payment** (links to `purchase.payments.create`).
  - Completed stages show the same read-only summaries the Blade page shows today (view request/signature/suppliers/quotes/comparison/LPO/GRNs/payments) — as links out, not inline data, for anything not covered by the loaded relations.
- Sidebar cards (both shells): Request Details, Suppliers (with status pill, WhatsApp quick-link for pending), Items (quote count / awarded badge), Quotes (per-supplier total, lowest/awarded tags), Linked LPOs — all read-only, sourced directly from the API response; no comparison/quote-detail modals in this phase (those belong to the Compare & Award / View Quotes flows, out of scope).
- Live updates: on `purchase-request.stage-changed` matching this request's `id` (via Echo on the `purchase` private channel, same channel the board already subscribes to), refetch the detail endpoint and re-render.

## Non-Goals (this phase)

- No React port of RFQ management, quote comparison/award, LPO generation, GRN recording, or payment issuance — all reached via link-out to existing Blade pages, per Scope Decomposition.
- No changes to the permission model — the API endpoint reuses the exact existing `PurchaseRequestPolicy` gates.
- The three new standalone Blade pages (RFQ supplier-select, GRN-select, Issue LPO confirm) are a deliberate stopgap, not a design decision to keep them Blade long-term — they get ported (or replaced) when their parent flows (RFQ management, GRN recording, LPO generation) are picked up in a future phase.
