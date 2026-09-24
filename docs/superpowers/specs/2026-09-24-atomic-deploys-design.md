# Atomic Deploys, Build Once, Separate Provisioning — Design Spec

**Date:** 2026-09-24
**Status:** proposed, awaiting review
**Replaces:** the in-place `scripts/deploy.sh` flow described in `docs/ci-cd-setup.md`
**Supersedes:** PR #17 (`chore/deploy-applies-reverb-proxy`), which should be closed unmerged

## Why

The gates in front of a deploy are sound: CI is verified by SHA, production waits
for a human approval, the ref is validated before anything runs as root, the
database is backed up before migrating, and every deploy ends with a smoke test.
The deploy itself is the weak part. Three things went wrong in one day
(2026-09-24), and each one traces back to how `deploy.sh` works on the box.

1. **Deploys are in place and not atomic.** `deploy.sh` runs
   `git reset --hard` inside the live directory, then `composer install`,
   `npm run build` and the migrations, all in that same directory. For the
   length of the deploy the running site is a mixture of new code and old
   dependencies. When a step fails, it stays that way. PR #12 did exactly
   this to staging: the code switched over, `package:discover` crashed while
   booting it, and every URL answered 500 until PR #13 landed. An atomic
   deploy would have failed before the switch, and staging would have kept
   serving the previous release.
2. **Each box builds its own copy.** Staging and production each run
   `composer` and `npm` from source. What was tested on staging is therefore
   not byte-for-byte what production runs, and the production box has to
   carry Node and a build toolchain for no reason except building.
3. **Server setup is not part of the pipeline, or it is mixed into the
   deploy.** The Apache websocket proxy existed on neither box until it was
   added by hand on 2026-09-24. PR #17 bolted it onto `deploy.sh`, which means
   a routine app deploy now edits Apache. Setting up a machine and shipping an
   app are different jobs with different triggers and different risks.

Rollback today is also manual: stop the services, copy a database backup back
into place, and redeploy an older ref, which is four steps done under pressure.

## Goals

- A failed deploy **never** changes what the live site serves.
- Rollback is **one command** and takes seconds.
- **One artifact** per commit, built once in CI and promoted unchanged from
  staging to production.
- Machine setup (Apache, systemd units, the Reverb proxy, sudoers, directory
  layout) is **code**. It lives in its own script and workflow, runs when the
  machine changes, and is idempotent.
- The existing gates stay exactly as they are: CI verified by SHA, the
  production approval, the database backup and the smoke test.

## Non-goals

- Containers, Kubernetes, Ansible or any other new tooling. Two LXC boxes and a
  sole developer do not need them. Everything below is bash plus GitHub
  Actions, which is what the project already uses.
- Leaving SQLite. Its constraints are handled below.
- Zero-downtime migrations in general. What is required is backward-compatible
  migrations (see Migrations), not an online schema-change system.

---

## 1. Layout on each box

```
/var/www/ProjectsERP/
  releases/
    20260924T093134-ab68932/        one directory per deploy, immutable once live
    20260924T101502-cd5b01b/
  shared/
    .env                            the box's only .env
    storage/                        logs, sessions, cache, uploads, backups
    database/database.sqlite        the live database
  current -> releases/20260924T101502-cd5b01b    the only thing that ever "switches"
```

Each release links to `shared/` for the three things that must outlive a release:

| In the release | Links to | Why |
|---|---|---|
| `.env` | `shared/.env` | secrets and per-box config are never part of a build |
| `storage/` | `shared/storage/` | sessions, logs, uploaded files, `storage/backups` |
| `public/storage` | `shared/storage/app/public` | the `storage:link` target, created once |

**The database is not a symlink.** `shared/.env` sets
`DB_DATABASE=/var/www/ProjectsERP/shared/database/database.sqlite` as an
absolute path. SQLite names its `-wal` and `-shm` files after the path it was
opened with, so opening it through a symlink that moves on every deploy is a
risk not worth taking.

The last **5** releases are kept and older ones are pruned. Staging's disk is
71% used out of 19.5 GB, and a release that ships `vendor/` but no
`node_modules/` is roughly 100 MB. That estimate needs confirming against a
real artifact before the retention count is fixed.

Apache's `DocumentRoot` becomes `/var/www/ProjectsERP/current/public`, and
every systemd unit's `WorkingDirectory` becomes `/var/www/ProjectsERP/current`.
Provisioning owns both changes (section 4).

## 2. Build once, in CI

A new `build` job in `ci.yml` runs on a GitHub-hosted runner, only for pushes
to `development` and `main`, and only once the tests have passed:

1. `composer install --no-dev --prefer-dist --optimize-autoloader`, with
   `config.platform.php` pinned in `composer.json` to the servers' PHP version
   so the lock resolves for the machine that will run it. **The servers' PHP
   version needs confirming first** (`php -v` on each box).
2. `npm ci && npm run build`.
3. Package the tree **without** `node_modules/`, `tests/`, `.git/`, `.env`, or
   `storage/` contents, into `steelerp-<sha>.tar.gz`, and write
   `steelerp-<sha>.sha256` next to it.
4. Upload both as a workflow artifact named `release-<sha>`, kept for 30 days.

Deploys download that artifact by SHA. The production `verify` job already
resolves the exact SHA and proves CI was green for it, so it now also proves
the artifact exists. The deploy then fetches **that** artifact, not a rebuild.

### The one thing a build cannot know: the Reverb address

`resources/js-app/echo.js` reads `VITE_REVERB_APP_KEY`, `VITE_REVERB_HOST`,
`VITE_REVERB_PORT` and `VITE_REVERB_SCHEME` through `import.meta.env`, which
bakes them into the bundle at build time. They differ per box (staging dials
`staging-steelerp.p7h.me`, production dials `steelerp.p7h.me`), so a single
build cannot carry them.

They move to **runtime**:

- `app-shell.blade.php` renders them from `config('broadcasting.connections.reverb')`
  plus a new `config/reverb-client.php`, into a
  `<meta name="reverb" content='{"key":…,"host":…,"port":…,"scheme":…}'>`
  tag. The page already carries the CSRF token the same way.
- `echo.js` reads that tag, and falls back to `import.meta.env` so local
  `npm run dev` keeps working unchanged.
- The `VITE_REVERB_*` lines leave `.env.example` for server use. Local
  development may keep them.

`VITE_APP_NAME` is not read anywhere in `resources/js-app`, so it needs
nothing. Echo is imported only from inside the main SPA's tree (`echo.js`,
`NotificationBell`, `useLiveList` and the detail hooks). The `auth.jsx` and
`rfq.jsx` entries reach none of them, so they are unaffected.

This is its own phase (Phase 2), and it ships before build-once. It is useful
on its own because it ends "edit `.env`, then redeploy to rebuild" for the
websocket address.

## 3. The deploy (`steelerp-deploy`)

The runner's sudo rule today points at `/var/www/ProjectsERP/scripts/deploy.sh`,
a file that the deploy itself overwrites with `git reset --hard`. Root
therefore runs whatever is in the repo, and a change to the script only takes
effect on the deploy **after** the one that ships it (the
"deploy script lags one deploy" problem).

The deploy script moves to **`/usr/local/sbin/steelerp-deploy`**. Provisioning
installs it from the repo, and the sudoers rule points there. It changes only
when someone provisions, and provisioning happens behind the same approval as
production. The one-deploy lag disappears, because the script that runs is
always the one provisioning installed.

`sudo steelerp-deploy <staging|production> <sha>`:

1. **Validate** the SHA (40 hex characters) and the environment, as today.
2. **Back up** the database with `sqlite3 .backup` into
   `shared/storage/backups/`, as today.
3. **Fetch** the artifact `release-<sha>`, check its `sha256`, and unpack it
   into `releases/<timestamp>-<short-sha>/`.
4. **Link** in `shared/.env`, `shared/storage`, and `public/storage`.
5. **Prepare, in the new release, while the old one still serves:**
   `php artisan package:discover`, `config:cache`, `route:cache`,
   `view:cache`, then `php artisan about` as a boot check. **A release that
   cannot boot stops here.** This is the step that would have caught PR #12.
6. **Migrate**, from the new release, against the shared database (see
   Migrations).
7. **Seed access** (`db:seed --class=AccessSeeder`), as today.
8. **Switch.** `ln -sfn` to a temporary name, then `mv -T` over `current`.
   This is a single atomic rename, so no request ever sees half a release.
9. **Reload.** Apache gets a graceful reload. `php artisan queue:restart` runs,
   then `steelerp-reverb` and `steelerp-queue` restart. The scheduler needs
   nothing, because each run reads `current`.
10. **Health check** `/up` through Apache. If it fails, **switch back
    automatically** to the previous release and exit non-zero.
11. **Prune** releases beyond the last 5.

Steps 1–7 never touch `current`, so a failure in any of them leaves the live
site exactly as it was.

The PHP handler matters for step 9. With mod_php, the realpath cache can keep
resolving the old target of `current` until Apache reloads. With PHP-FPM, FPM
itself needs the reload. **Which one the boxes run needs confirming**
(`apache2ctl -M | grep php`, `systemctl list-units '*fpm*'`). The graceful
reload in step 9 covers mod_php either way.

### Rollback

`sudo steelerp-deploy rollback` points `current` at the previous release and
runs steps 9 and 10. It does **not** touch the database: migrations are
forward-only (next section). If a migration has to be undone, restore the
backup from step 2 by hand, as documented in `docs/ci-cd-setup.md`. A
`rollback.yml` workflow exposes the same command with `workflow_dispatch`, and
production's run sits behind the same approval gate.

### Migrations

For the length of steps 6–8, the **old** code runs against the **new**
schema. Migrations therefore have to be backward-compatible with the release
before them. That is expand then contract: add a column in one release and
drop the old one in a later release, never both at once. That was already
true in practice, because the in-place deploy had the same window, only
without anyone having named it. It becomes a written rule in CLAUDE.md.

A migration that cannot be made compatible, such as a destructive rewrite,
runs inside `php artisan down` / `up` around steps 6–8. The deploy offers that
as a flag, `--maintenance`, which is off by default.

SQLite allows one writer, so migrations briefly lock out the old release's
writes. That is acceptable at this scale and is the same as today.

## 4. Provisioning (`scripts/provision.sh`)

`sudo scripts/provision.sh <staging|production>` is idempotent and safe to
re-run. It owns everything about the machine that is not the app:

| Owns | Today |
|---|---|
| The `releases/`, `shared/`, `current` layout | does not exist |
| The Apache vhost: `DocumentRoot …/current/public`, `ServerName`, and the HTTPS redirect | hand-written, differs per box |
| The Reverb websocket proxy (`steelerp-reverb.conf`) | `setup-reverb-proxy.sh` (#16), run by hand |
| The systemd units `steelerp-reverb`, `steelerp-queue`, `steelerp-scheduler` | hand-installed; **production has never had the scheduler** |
| `/usr/local/sbin/steelerp-deploy` and the sudoers rule for it | `install-runner.sh` |
| The Apache modules `proxy`, `proxy_http`, `proxy_wstunnel`, `rewrite` | enabled by hand |

The per-box values (domain, whether Reverb binds to `127.0.0.1` or `0.0.0.0`)
live in `scripts/provision/<env>.conf`, in the repo, and never include
secrets. Secrets stay in `shared/.env`, which provisioning **never writes**.

Every file it writes is compared first, backed up before it is changed, and
followed by `apache2ctl configtest` or `systemd-analyze verify` before any
reload. A configuration that does not pass is never loaded.

A new `provision.yml` workflow runs it with `workflow_dispatch` and an
`environment` input. Production's run sits behind the `production` approval
gate. **It never runs as part of a deploy.** The deploy only **checks** what it
depends on (the `current` layout, the units, the proxy), and fails fast with
"run provision" when something is missing.

`scripts/setup-reverb-proxy.sh` (#16) becomes one function inside
provisioning. PR #17 is closed unmerged.

## 5. The workflows after the change

| Workflow | Trigger | Does |
|---|---|---|
| `ci.yml` | as today | tests, and for `development`/`main` pushes: **build and upload `release-<sha>`** |
| `deploy-staging.yml` | CI green on `development` | download `release-<sha>`, `steelerp-deploy staging <sha>`, smoke test |
| `deploy-production.yml` | CI green on `main`, a `v*` tag, or manual; approval | `verify` (unchanged, now also checks the artifact exists), `steelerp-deploy production <sha>`, smoke test |
| `provision.yml` **new** | manual, per environment; production needs approval | `provision.sh <env>` |
| `rollback.yml` **new** | manual, per environment; production needs approval | `steelerp-deploy rollback`, smoke test |

Both smoke tests check the **public** websocket
(`REVERB_URL=https://<domain>`), not port 8080 on the LAN, so they test what
a browser actually uses. That needs a `PRODUCTION_REVERB_APP_KEY` secret.

## 6. Cutting the boxes over

This is done once per box, **staging first**, with production following only
after staging has run a week of deploys and one rollback:

1. Merge the provisioning branch. Run `provision.yml` for staging. It creates
   `shared/`, copies in the existing `.env` and `storage/`, and moves the
   database there after a `sqlite3 .backup`. It sets `DB_DATABASE` in
   `shared/.env`, and turns the current checkout into the first release.
   Only then does it repoint Apache and the units at `current`, as one
   configtested reload.
2. The next merge to `development` deploys through the new path.
3. Rehearse a rollback on staging with `rollback.yml`.
4. Repeat for production, behind approval.

The old checkout stays in place, renamed `…/ProjectsERP.pre-releases`, until
production has run on the new layout for a week. Reverting to it means
pointing Apache back at it, which provisioning can do.

## 7. Phases — one branch and one PR each

| # | Branch | Delivers | Depends on |
|---|---|---|---|
| 1 | `feat/provision-script` | `provision.sh` plus `provision.yml`: layout, vhost, proxy, all three units (including production's missing scheduler), sudoers, installed deploy script. **Also still accepts the old in-place layout**, so it can merge before the cutover | — |
| 2 | `feat/runtime-reverb-config` | Reverb address read at runtime from a meta tag, with the `import.meta.env` fallback | — |
| 3 | `feat/atomic-deploy` | `steelerp-deploy` with releases, boot check, atomic switch, auto-revert on a failed `/up`, `rollback`, `rollback.yml`. Still **builds on the box** at this phase | 1 |
| 4 | `feat/build-once-artifact` | CI `build` job; deploys download `release-<sha>` instead of building; Node leaves the servers | 2, 3 |
| 5 | `chore/public-websocket-smoke-test` | smoke tests on `wss://<domain>`, plus the production secret | — |
| 6 | `docs/deploy-architecture` | `docs/ci-cd-setup.md` and CLAUDE.md rewritten for the new flow, including the backward-compatible-migrations rule | 3, 4 |

Phases 1, 2 and 5 are independent and can go in any order. Each phase gets its
own plan and its own review.

## Open questions for review

1. **The PHP version and handler on each box** (`php -v`,
   `apache2ctl -M | grep php`). This decides the `config.platform.php` pin and
   the reload in step 9.
2. **Artifact storage.** Are Actions artifacts (30 days) enough, or should
   production releases also be attached to a GitHub Release when tagged `v*`,
   for a long-term copy? Rollback does not need either, because the last 5
   releases are on disk.
3. **Release retention.** Is 5 right, given staging's disk? That depends on
   the real artifact size from Phase 4.
4. **The staging scheduler.** Does staging really run `steelerp-scheduler`?
   Provisioning will install it on both boxes either way.
5. **PR #17.** Recommended: close it unmerged, and let Phase 1 replace it.
