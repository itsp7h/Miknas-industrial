# React SPA Migration — Design Spec

**Date:** 2026-07-29
**Branch:** `yousif/react-spa-migration`

## Overview

SteelERP is currently a Laravel 12 monolith rendering server-side Blade views with Alpine.js for interactivity. This project migrates the frontend to a React single-page application backed by a Laravel JSON API, with real-time updates (no page refresh) and a fully componentized UI.

This is a large, multi-phase effort. This spec covers the full intended end state and the phase order; each phase is still built and verified in sequence as part of one continuous effort (no per-module approval gate).

## Goals

- Replace Blade/Alpine views with a React SPA, module by module, across all four modules (Purchase, Inventory, Production, Sales).
- Live, instant UI updates (stock levels, order status, new records) via WebSocket broadcasts — no polling, no full-page refresh.
- Every domain object (Supplier, Item, PurchaseOrder, StockLevel, ProductionOrder, SalesOrder, etc.) gets one canonical React component family, reused everywhere it appears instead of being re-templated per page.
- Existing UX rules (instant client-side search, toast notifications, custom confirm modals, AJAX-only data entry) carry over as reusable component behavior.

## Non-goals (this phase)

- No E2E browser test framework (Playwright/Cypress) — deferred until the SPA has real usage patterns to script against.
- No SQLite replacement — flagged as a future risk if migrated-module traffic increases concurrent write load, not addressed now.
- No mobile app / native client — React SPA is web-only, responsive.

## Architecture

- **Backend API**: new `routes/api.php`, versioned under `/api/v1/`. Each existing controller gets an API counterpart returning JSON instead of a Blade view. Existing `role`/`permission`/`role_or_permission` middleware apply identically on API routes.
- **Auth**: Laravel Sanctum in SPA (cookie) mode — same top-level domain, session-based. Breeze's login, email verification, and password reset flows are reused as-is; React calls them via `fetch` with credentials instead of following redirects.
- **RBAC exposure**: on login, the API returns the authenticated user's roles/permissions once. React uses this to gate UI (hide/disable actions); the server-side middleware remains the actual enforcement boundary.
- **Live updates**: Laravel Reverb (new dependency) as a self-hosted WebSocket server — no third-party service. Domain events (e.g. `PurchaseOrderIssued`, `StockLevelChanged`, `SalesOrderConfirmed`) broadcast on model-scoped channels. React subscribes via Laravel Echo and patches local state directly on receipt.
- **Frontend**: new React SPA (Vite + React Router), living in the same repo (e.g. `resources/js-app/`) as a separate build entry from the existing Blade/Alpine assets — not a separately deployed app.
- **Ops**: Reverb requires a new supervisor-managed daemon (`php artisan reverb:start`) in every environment alongside the existing queue worker.

## Cutover Mechanics

- Old Blade routes and views are left untouched until their module is migrated — no feature flags, no dead-code toggles.
- Each module gets a parallel catch-all route (e.g. `/app/purchase/{any?}`) that mounts the React Router app, which handles all of that module's pages internally (list, detail, create/edit) client-side.
- Navigation links for a migrated module point at its new `/app/...` path; unmigrated modules keep pointing at their existing Blade routes. Both coexist in the same sidebar during the transition.
- **Migration order:** Foundation → Purchase → Inventory → Production → Sales (Purchase has had the most recent activity; Inventory/Production/Sales follow their existing data-dependency order).
- A module is considered migrated when: its API endpoints exist and are tested, its React pages/components fully replace the Blade equivalent, live-update events are wired for anything that changes state, and its nav links point at `/app/...`. Old Blade views/routes for that module are then deleted, not kept as legacy fallback.

## Foundation Phase (first phase of work)

Delivers no new user-facing feature on its own, but everything after depends on it:

1. Install and configure Sanctum (SPA mode), Reverb, Laravel Echo.
2. Scaffold the React SPA build (Vite entry, React Router, base layout shell matching the current sidebar/topbar visually).
3. Build the shared UI component library (`components/ui/`): Button, Table (with built-in instant client-side search per existing convention), Modal, Toast, FormField, Card, ConfirmModal.
4. Wire one full vertical slice end-to-end as proof: login → dashboard page fetching one real piece of data via the API → a Reverb-broadcast event updating it live.
5. Establish the domain-entity component pattern (Row/Card/Form/Detail) with one real example (likely Supplier, as the simplest existing entity) before repeating it across every module.

## Component Strategy

- Shared `ui/` components are the vocabulary everything else is built from — no module reaches past them to hand-roll markup for buttons, tables, modals, or toasts.
- Each domain entity gets one component family, colocated with its module (e.g. `components/purchase/supplier/`):
  - `EntityRow` — table/list contexts
  - `EntityCard` — compact/dashboard contexts
  - `EntityForm` — create/edit, usable inside a modal or a page
  - `EntityDetail` — full show-page view
- A component family is the *only* place that entity's markup is defined. Pages compose these; they do not reimplement entity-specific rendering inline.
- Global singletons: one `<ToastProvider/>` fed by both API responses and broadcast events, one `<ConfirmModal/>` instance used by every delete/destructive action.

## Testing

- **API**: PHPUnit feature tests per endpoint — request validation, RBAC gating, response shape — following the existing test pattern in the repo.
- **Frontend**: Vitest + React Testing Library.
  - Shared `ui/` components: unit tests for render/interaction/accessibility, written once, covering every consumer.
  - Domain-entity components: tests for their own logic (form validation, permission-based conditional rendering).
  - Per migrated module: a handful of integration tests covering realistic flows (e.g. "load list, client-side search filters correctly, open edit form, submit, row updates") rather than exhaustive per-page coverage.
  - At least one integration test per module verifying a broadcast event updates the UI without a refetch (Echo/Reverb mocked in tests, not a real WebSocket server in CI).
- No E2E framework in this phase (see Non-goals).

## Risks

- **Reverb daemon** is new operational surface (process supervision, restarts) that doesn't exist in the stack today.
- **SQLite single-writer lock** could become a bottleneck sooner than today if migrated modules increase concurrent request volume; not a phase-one blocker, worth monitoring.
- **Two frontends coexist** for the full migration duration — the Blade layout shell and the React layout shell must stay visually consistent so the app doesn't feel broken mid-migration.
- **Foundation phase carries the most architectural risk** — every convention it sets (API shape, auth flow, component patterns) is copied by every module after it, so mistakes here compound.
