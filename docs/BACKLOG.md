# Backlog — parked, not now

Things found while working. **None of these are design work.** Written down so
they stop interrupting the design task. Revisit when the design push is done.

## 1. Security — dev quick-login exposed publicly (URGENT, not design)

`resources/views/auth/login.blade.php:48` renders the "Dev Quick Login" panel
with no environment guard, so it appears on every host. Verified present on
both public login pages:

- `https://staging-steelerp.p7h.me/login`
- `https://steelerp.p7h.me/login`  ← production

It publishes an admin email and the literal text "Password for all accounts:
`password`". Not tested against either host — deliberately left alone.

Fix when ready:
1. Wrap the block in `@if (app()->environment('local'))`, plus a test that it
   is absent outside `local`.
2. Rotate the password on any account still using `password`.

## 2. Migration — remaining Blade → React groups

Done: Purchase Orders (list, detail, create, edit).

Remaining, in order:
1. Purchase — GRNs, Supplier Invoices, Payments (9 views, plain CRUD)
2. Purchase — Requests, quotes workspace, RFQ, signature, pipeline detail
   (10 views, the RFQ workflow — the hard part)
3. Settings — integrations, projects, projects-overview, users, VAT
4. Profile

Permanently Blade: `print`/`pdf` views (DomPDF), public `/rfq/{token}` portal,
Breeze auth pages.

## 3. Branch housekeeping

- `origin/chore/harden-production-deploy` (3 commits, unmerged) carries a
  `tests/TestCase.php` Vite stub that CI needs. If `development` merges first
  without it, view-rendering tests fail there.
- Work so far is on `feat/purchase-settings-react-port`, unpushed.

## 4. Local dev notes (already applied to local .env, which is gitignored)

- `SANCTUM_STATEFUL_DOMAINS` only listed `:8000`; on any other port the API
  401s and lists render empty.
- `REVERB_APP_KEY` blank breaks the SPA — `echo.js` builds Pusher at import.
- Local demo data seeded: 3 suppliers, 4 items, 6 POs, 1 GRN, `admin@erp.com`.
