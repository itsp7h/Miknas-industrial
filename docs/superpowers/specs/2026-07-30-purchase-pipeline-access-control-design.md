# Purchase Pipeline Access Control — Design Spec

**Date:** 2026-07-30

## Overview

The Purchase Request → GM Signature → RFQ → Quoting → Comparison/Award → LPO pipeline is fully built and functional (`PurchaseRequest` model, `PurchaseStageService`, and the Purchase controllers/views), but has **zero access control** today: any authenticated user can view and act on any stage of any request. This spec adds permission-based enforcement matching the real-world workflow, built on pre-defined **profiles** (bundles of permissions) plus **per-person custom permission overrides**, along with the User Management page needed to actually assign those to employee accounts — without which the access control would be unusable in practice (no way to grant it).

This is phase 1 of a larger initiative. Phase 2 (a separate, later spec) will redesign these same pages mobile-first, once the access rules here define exactly what each profile needs to see and do.

## Goals

- Enforce a granular permission model on the existing pipeline, with three pre-built profiles (Requester, Purchase Manager, Procurement Officer) as starting bundles, and the ability to grant/revoke individual permissions per person on top of their profile(s).
- Scope list views so each person only sees the requests relevant to their actual (profile + custom) permissions.
- Give Admins a UI to assign profiles and toggle individual permissions per employee, since no such UI exists today.

## Non-goals

- Receiving (GRN) and payment stages are unchanged in this phase — they keep today's behavior (any authenticated user can act on them). A future spec can lock those down to Store Manager / Accounts if desired.
- No mobile-specific UI work — that's phase 2.
- No per-department/per-project scoping — every permission holder sees/acts on every relevant request (shared queue), not a subset.
- No invite-new-user flow. The User Management page manages profiles/permissions for users who already exist (self-registered via Breeze, or created some other way) — it does not add a "send invite email" feature.

## Permissions, Profiles & Custom Rights

Access is enforced via **Spatie permissions**, not role checks directly. **Profiles** are Spatie roles repurposed as named permission bundles — assigning a profile grants its permissions as a starting point. An Admin can then flip individual permission **toggle switches** for a specific person, granting or revoking permissions directly on that user — these direct grants persist independently of later profile changes (changing someone's profile never wipes their custom toggles). A person's *effective* permissions are the union of every profile they hold plus their individual overrides. A person can hold multiple profiles at once.

**Granular permissions:**

| Permission | Meaning |
|---|---|
| `purchase-requests.create` | Create a new purchase request |
| `purchase-requests.edit` | Edit a request (policy additionally enforces: only your own, only while `stage = draft`) |
| `purchase-requests.view-own` | See your own requests, any stage |
| `purchase-requests.view-active-pipeline` | See any request at `rfq` stage or later, regardless of owner |
| `purchase-requests.view-all` | See every request, any stage, any owner (monitoring) |
| `purchase-requests.approve` | Approve/reject via GM signature, at `gm_approval` stage |
| `purchase-requests.manage-rfq` | Select/invite suppliers, send RFQ, at `rfq` stage |
| `purchase-requests.manage-quotes` | View/interact with quotes at `quoting`/`comparison` stage |
| `purchase-requests.award` | Award items to suppliers, at `comparison` stage |
| `purchase-requests.generate-lpo` | Generate the LPO, at `lpo` stage |

**Pre-built profiles (bundles):**

| Profile | Permissions granted |
|---|---|
| Requester | create, edit, view-own |
| Purchase Manager | approve, view-all |
| Procurement Officer | manage-rfq, manage-quotes, award, generate-lpo, view-active-pipeline |

Admin is **not** a profile — it remains a distinct full-bypass, not a customizable bundle (see Architecture below). Rejection keeps a request in `draft` (existing behavior), so a Requester can still edit and resubmit after rejection — no special-casing needed since the policy keys off `stage`, not `status`.

## Architecture

- **`PurchaseRequestPolicy`** (new, `app/Policies/PurchaseRequestPolicy.php`), registered for the `PurchaseRequest` model via Laravel's standard policy discovery. Methods: `view`, `update` (create/edit), `approve`, `manageRfq`, `award`, `generateLpo` — each checks `$user->can('purchase-requests.<permission>')` (plus the stage/ownership business rule for `update`) rather than checking role names directly. A `Gate::before()` callback short-circuits to `true` whenever the user has the `Admin` role, so Admin bypasses every permission check without needing every permission explicitly assigned.
- **Controllers** call `$this->authorize('approve', $purchaseRequest)` (or the relevant ability) at the start of each guarded action: `PurchaseRequestController::store/update/approve/reject`, `RfqController::selectSuppliers/sendAll/store`, `SupplierQuoteController::awardItem/unawardItem`, `PurchaseOrderController::generateFromRequest`. An unauthorized call returns Laravel's standard 403 response — the real enforcement lives here, not in the UI.
- **List queries** get permission-aware scoping in `PurchasePipelineController::index` and `PurchaseRequestController::index`, picking the *widest* visibility permission the person actually has (from profile + custom toggles combined): `view-all` → no filter; else `view-active-pipeline` → stage filter (`rfq` or later); else `view-own` → owner filter; else they see nothing.
- **Blade views** (`purchase/pipeline/show.blade.php` and related partials) wrap each action button in `@can('approve', $pr)`, `@can('manageRfq', $pr)`, etc. — this hides buttons a user can't use, as a UX nicety on top of (never instead of) the controller-level `authorize()` calls.
- **Seeder**: `DatabaseSeeder` creates the 10 permissions above and the 3 profiles (as roles), attaching each profile's permission bundle per the table above. No changes to how Admin itself is seeded — it stays a plain role relying on `Gate::before()`.

## User Management Page

Since no user/role management UI exists today (the only role assignment anywhere is one hardcoded line in `DatabaseSeeder` for a single admin), this spec adds:

- **Route**: `settings/users`, Admin-only (`role:Admin` middleware, same pattern as existing Settings routes). Linked from the sidebar's existing Admin-only "System" section, alongside "Companies" and "Projects".
- **List view**: every user in the system, their assigned profile(s) shown as badges, with an instant client-side search box (per CLAUDE.md's search convention — all users loaded once, filtered in-browser, live "N of M" count).
- **Edit a person's access**: clicking a user opens a modal with (1) checkboxes for each profile (a person may hold multiple), and (2) a toggle switch per individual permission, pre-set to reflect whatever their current profile(s) + existing overrides grant. Flipping a toggle applies a direct permission grant/revoke on that specific user, independent of their profile(s). Saving is AJAX (`fetch`, JSON request/response, toast on success/error) — no page reload, per CLAUDE.md's data-entry convention.
- **Guardrail**: an Admin cannot remove their own Admin role through this page, preventing accidental self-lockout with no other Admin able to restore it.
- **Effect of the security gap this closes**: public registration (Breeze) remains open, but a newly self-registered user now has zero profiles/permissions and is therefore locked out of the entire purchase pipeline (403 everywhere a policy applies) until an Admin grants them access here. This is the correct default-deny behavior, not a special case to handle separately.

## Data Flow & Error Handling

- A direct URL hit to a guarded action the user isn't allowed (e.g. a Requester navigating straight to a `generate-lpo` route) returns Laravel's standard 403 Forbidden page.
- A Requester attempting to edit their own request after it has left `draft` gets the same 403 — the edit route becomes unreachable for them past that stage, not merely hidden from the UI.
- Permission assignment itself has no approval workflow — an Admin's change via the User Management page takes effect immediately (Spatie checks permissions live on each request; its internal permission cache is auto-flushed on any role/permission change, so no manual cache-clear step is needed).

## Testing

- **Policy unit tests** (`tests/Unit/PurchaseRequestPolicyTest.php`): one test per permission × stage combination in the tables above (e.g. "a user with only view-own cannot update a request once stage is past draft", "a user without award cannot award items", "a user with view-active-pipeline cannot see a draft-stage request", "Admin can always do everything regardless of assigned permissions"), plus at least one test proving a custom per-user toggle grants access beyond what their profile alone would allow.
- **Feature tests**: extend existing controller tests (`PurchaseRequestControllerTest`, `RfqControllerTest`, `SupplierQuoteControllerTest`, `PurchaseOrderControllerTest` — creating them where they don't already exist) with 403 assertions for each unauthorized case, alongside the existing happy-path coverage.
- **List-scoping tests**: assert `PurchasePipelineController::index` and `PurchaseRequestController::index` return exactly the expected subset of requests for each permission combination (view-own → only their own; view-active-pipeline → only `rfq`-stage-or-later; view-all/Admin → all).
- **User Management feature tests**: Admin can list users, assign/remove profiles, and toggle individual permissions via AJAX; a non-Admin gets 403 on the same routes; an Admin cannot remove their own Admin role (guardrail); changing a person's profile does not clear their existing custom toggles.

## Risks

- **No department/project scoping** means every permission holder sees/acts on the full shared queue for their scope — acceptable per the confirmed rules, but could become a visibility/noise problem if the number of concurrent requests grows large; not addressed in this phase.
- **Existing users have no profile or permissions today** apart from the single seeded Admin — after this ships, every other existing user is locked out of the purchase pipeline until an Admin manually grants them access via the new User Management page. This is a one-time manual rollout step, not an ongoing concern, but it should happen promptly after deploy to avoid disrupting active work.
- **Toggle sprawl**: since any of the 10 permissions can be individually toggled per person, over time it may become hard to tell at a glance "why does this person have this access" (profile vs. custom override). Not addressed in this phase — the User Management UI at minimum should visually distinguish profile-granted vs. custom-toggled permissions so this stays legible.
