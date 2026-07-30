# Purchase Pipeline Access Control — Design Spec

**Date:** 2026-07-30

## Overview

The Purchase Request → GM Signature → RFQ → Quoting → Comparison/Award → LPO pipeline is fully built and functional (`PurchaseRequest` model, `PurchaseStageService`, and the Purchase controllers/views), but has **zero access control** today: any authenticated user can view and act on any stage of any request. This spec adds role-based enforcement matching the real-world workflow, plus the User Management page needed to actually assign those roles to employee accounts — without which the access control would be unusable in practice (no way to grant it).

This is phase 1 of a larger initiative. Phase 2 (a separate, later spec) will redesign these same pages mobile-first, once the access rules here define exactly what each role needs to see and do.

## Goals

- Enforce three new purchase-workflow roles (Requester, Purchase Manager, Procurement Officer) on top of the existing pipeline, with Admin retaining full override access.
- Scope list views so each role only sees the requests relevant to them.
- Give Admins a way to actually assign these (and existing) roles to user accounts, since none exists today.

## Non-goals

- Receiving (GRN) and payment stages are unchanged in this phase — they keep today's behavior (any authenticated user can act on them). A future spec can lock those down to Store Manager / Accounts if desired.
- No mobile-specific UI work — that's phase 2.
- No per-department/per-project scoping for Purchase Managers or Procurement Officers — every holder of a role sees/acts on every relevant request (shared queue), not a subset.
- No invite-new-user flow. The User Management page manages roles for users who already exist (self-registered via Breeze, or created some other way) — it does not add a "send invite email" feature.

## Roles & Rules

Three new Spatie roles are added to the existing set (Admin, Accounts, Store Manager, Production Manager, Sales Manager): **Requester**, **Purchase Manager**, **Procurement Officer**. A user can hold multiple roles simultaneously.

| Action | Requester | Purchase Manager | Procurement Officer | Admin |
|---|---|---|---|---|
| Create a purchase request | ✅ | ❌ | ❌ | ✅ |
| Edit a purchase request | ✅, only while `stage = draft`, only their own | ❌ | ❌ | ✅, any stage |
| View a purchase request | ✅, only their own, any stage (read-only once past draft) | ✅, all requests, any stage | ✅, only requests at `rfq` stage or later | ✅, all |
| Approve/reject (GM signature) | ❌ | ✅, any request, at `gm_approval` stage | ❌ | ✅ |
| Select/invite suppliers, send RFQ | ❌ | ❌ | ✅, any request, at `rfq` stage | ✅ |
| View/manage quotes | ❌ | ✅ (monitoring only) | ✅, any request, at `quoting`/`comparison` stage | ✅ |
| Award items to suppliers | ❌ | ❌ | ✅, any request, at `comparison` stage | ✅ |
| Generate LPO | ❌ | ❌ | ✅, any request, at `lpo` stage | ✅ |

Purchase Managers and Procurement Officers are **not** scoped by department or project — any holder of the role can act on any relevant request (shared queue). Multiple people can hold each role.

Rejection keeps a request in `draft` (existing behavior), so a Requester can still edit and resubmit after rejection — no special-casing needed since the policy keys off `stage`, not `status`.

## Architecture

- **`PurchaseRequestPolicy`** (new, `app/Policies/PurchaseRequestPolicy.php`), registered for the `PurchaseRequest` model via Laravel's standard policy discovery. Methods: `view`, `update` (create/edit), `approve`, `manageRfq` (select suppliers + send RFQ), `award` (comparison-stage awarding), `generateLpo`. A `Gate::before()` callback short-circuits to `true` whenever the user has the `Admin` role, so Admin bypasses every rule above without duplicating logic in each method.
- **Controllers** call `$this->authorize('approve', $purchaseRequest)` (or the relevant ability) at the start of each guarded action: `PurchaseRequestController::store/update/approve/reject`, `RfqController::selectSuppliers/sendAll/store`, `SupplierQuoteController::awardItem/unawardItem`, `PurchaseOrderController::generateFromRequest`. An unauthorized call returns Laravel's standard 403 response — the real enforcement lives here, not in the UI.
- **List queries** get role-aware scoping in `PurchasePipelineController::index` and `PurchaseRequestController::index`:
  - Requester → `where('requested_by', auth()->id())`
  - Procurement Officer → `whereIn('stage', ['rfq', 'quoting', 'comparison', 'lpo', 'receiving', 'payment', 'complete'])`
  - Purchase Manager, Admin → no filter (all requests)
- **Blade views** (`purchase/pipeline/show.blade.php` and related partials) wrap each action button in `@can('approve', $pr)`, `@can('manageRfq', $pr)`, etc. — this hides buttons a user can't use, as a UX nicety on top of (never instead of) the controller-level `authorize()` calls.
- **Seeder**: `DatabaseSeeder` adds `Requester` and `Purchase Manager` (`Procurement Officer` too) to its role list. No new Spatie *permissions* are created — this is role-based, not permission-based, matching the fixed rule set above.

## User Management Page

Since no user/role management UI exists today (the only role assignment anywhere is one hardcoded line in `DatabaseSeeder` for a single admin), this spec adds:

- **Route**: `settings/users`, Admin-only (`role:Admin` middleware, same pattern as existing Settings routes). Linked from the sidebar's existing Admin-only "System" section, alongside "Companies" and "Projects".
- **List view**: every user in the system, their current role(s) shown as badges, with an instant client-side search box (per CLAUDE.md's search convention — all users loaded once, filtered in-browser, live "N of M" count).
- **Assign roles**: clicking a user opens a modal with a checkbox per role (a user may hold multiple, e.g. Admin + Requester); saving is AJAX (`fetch`, JSON request/response, toast on success/error) — no page reload, per CLAUDE.md's data-entry convention.
- **Guardrail**: an Admin cannot remove their own Admin role through this page, preventing accidental self-lockout with no other Admin able to restore it.
- **Effect of the security gap this closes**: public registration (Breeze) remains open, but a newly self-registered user now has zero roles and is therefore locked out of the entire purchase pipeline (403 everywhere a policy applies) until an Admin assigns them a role here. This is the correct default-deny behavior, not a special case to handle separately.

## Data Flow & Error Handling

- A direct URL hit to a guarded action the user isn't allowed (e.g. a Requester navigating straight to a `generate-lpo` route) returns Laravel's standard 403 Forbidden page.
- A Requester attempting to edit their own request after it has left `draft` gets the same 403 — the edit route becomes unreachable for them past that stage, not merely hidden from the UI.
- Role assignment itself has no approval workflow — an Admin's change via the User Management page takes effect immediately (Spatie roles are checked live on each request, no caching to invalidate beyond Spatie's own permission cache, which is auto-flushed on role changes).

## Testing

- **Policy unit tests** (`tests/Unit/PurchaseRequestPolicyTest.php`): one test per role × stage combination in the rules table above (e.g. "Requester cannot update their own request once stage is past draft", "Procurement Officer cannot award before rfq stage", "Purchase Manager cannot award items", "Admin can always do everything regardless of role").
- **Feature tests**: extend existing controller tests (`PurchaseRequestControllerTest`, `RfqControllerTest`, `SupplierQuoteControllerTest`, `PurchaseOrderControllerTest` — creating them where they don't already exist) with 403 assertions for each unauthorized case, alongside the existing happy-path coverage.
- **List-scoping tests**: assert `PurchasePipelineController::index` and `PurchaseRequestController::index` return exactly the expected subset of requests for each role (Requester sees only their own; Procurement Officer sees only `rfq`-stage-or-later; Purchase Manager/Admin see all).
- **User Management feature tests**: Admin can list users and assign/change roles via AJAX; a non-Admin gets 403 on the same routes; an Admin cannot remove their own Admin role (guardrail).

## Risks

- **No department/project scoping** means every Purchase Manager and every Procurement Officer sees the full shared queue — acceptable per the confirmed rules, but could become a visibility/noise problem if the number of concurrent requests grows large; not addressed in this phase.
- **Existing users have no role today** apart from the single seeded Admin — after this ships, every other existing user is locked out of the purchase pipeline until an Admin manually assigns them a role via the new User Management page. This is a one-time manual rollout step, not an ongoing concern, but it should happen promptly after deploy to avoid disrupting active work.
