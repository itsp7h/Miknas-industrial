# CI/CD setup

## What runs where

| Workflow | Trigger | Runner | What it does |
|---|---|---|---|
| `ci.yml` | push to `main` or `development`, any PR | GitHub-hosted | PHP syntax check, Pint (changed files), PHPUnit on 8.2 + 8.3, Vitest, Vite build |
| `deploy-staging.yml` | CI green on `development`, or manual | self-hosted `staging` | Deploy + smoke test `http://192.168.0.38` |
| `deploy-production.yml` | CI green on `main`, tag `v*`, or manual | self-hosted `production` | Verify CI is green for the commit, then deploy + smoke test `https://steelerp.p7h.me`, behind an approval gate |
| `rollback.yml` | manual only | self-hosted, the chosen box | `steelerp-deploy <env> rollback` + smoke test; production behind the approval gate |
| `provision.yml` | manual only | self-hosted, the chosen box | `provision.sh` plan, then `--apply` if ticked; production's apply behind the approval gate |

## Branching

| Branch | Deploys to | How |
|---|---|---|
| `development` | staging (`192.168.0.38`) | automatically, once CI is green |
| `main` | production (`steelerp.p7h.me`) | merge from `development`, then tag `v*` or run the workflow manually |

Day-to-day work lands on `development`. Nothing reaches production without an
explicit tag or manual run, a green CI run for that exact commit, *and* an
approval on the `production` environment.

### The production CI gate

Staging consumes CI's verdict through `workflow_run`. Production cannot — it is
triggered by a tag push or a manual run, and CI does not run on tags. So
`deploy-production.yml` has a `verify` job that runs first, on a GitHub-hosted
runner:

1. It resolves the requested ref (tag, branch or SHA) to an immutable commit
   SHA through the API, and the deploy job then deploys *that SHA* — so a branch
   ref cannot move between the check and the deploy.
2. It looks up `ci.yml` runs by that **head SHA** (not by ref — the tagged
   commit was pushed to `main` first, and that run shares its SHA) and fails
   unless one concluded `success`.

Because `verify` is a `needs:` dependency of the deploy job, the approval
request never reaches a reviewer for a commit CI has not passed.

**Emergency override:** run the workflow manually with `allow_untested=true`.
That downgrades the missing-CI failure to a warning in the run log. It is a
separate explicit input rather than an implicit property of manual runs, so an
untested deploy is visible as such afterwards.

Refs handed to `deploy.sh` are validated by the script itself — `github/main`,
`github/development`, a `v*` tag, or a commit SHA. Anything else exits 2 before
the script touches the tree, because that argument reaches a root shell through
the NOPASSWD sudo rule.

## Why the deploys need self-hosted runners

Both containers sit on a private LAN (`192.168.0.46` production, `192.168.0.38`
staging). Nothing inbound reaches them directly: a Cloudflare Tunnel on
`192.168.1.10` carries public traffic *outbound-only*, and no port is forwarded.
A GitHub-hosted runner therefore cannot open an SSH connection to either box.

A self-hosted runner solves this from the other side — it sits on the LAN and
polls GitHub over outbound HTTPS, which both containers already have.

## Installing a runner

Use `scripts/install-runner.sh` — it creates the unprivileged `runner` user,
grants it sudo for the deploy script *only*, downloads and registers the runner
with the right labels, and installs it as a service.

Grab a registration token first (it expires in about an hour):
<https://github.com/itsp7h/Miknas-industrial/settings/actions/runners/new>

```bash
# on staging, 192.168.0.38
sudo scripts/install-runner.sh staging <REGISTRATION_TOKEN>

# on production, 192.168.0.46
sudo scripts/install-runner.sh production <REGISTRATION_TOKEN>
```

Each box needs its own token. The labels the script applies
(`self-hosted,steelerp,staging` / `...,production`) must match `runs-on` in the
deploy workflows, which is how a job lands on the right machine.

Verify afterwards at
<https://github.com/itsp7h/Miknas-industrial/settings/actions/runners> — both
should show as Idle.

## Repository configuration

- **Secret `STAGING_REVERB_APP_KEY`** — the staging `REVERB_APP_KEY`, used by
  the staging smoke test to open a websocket against Reverb.
- **Environment `production`** — add required reviewers under
  Settings → Environments. That is what makes production deploys wait for a
  human. It is the second of two gates; the first is the `verify` job's CI
  check, which runs before the approval request is raised.

## Deploying by hand

The scripts are standalone — nothing about them depends on Actions:

```bash
sudo /var/www/ProjectsERP/scripts/deploy.sh staging          # defaults to github/development
/var/www/ProjectsERP/scripts/smoke-test.sh http://192.168.0.38

sudo /var/www/ProjectsERP/scripts/deploy.sh production v1.2.0
/var/www/ProjectsERP/scripts/smoke-test.sh https://steelerp.p7h.me
```

## Provisioning a box

Everything about a box that is not the app is owned by `scripts/provision.sh`,
from the templates in `scripts/provision/templates/` and the per-box values in
`scripts/provision/<env>.conf`. It covers:

- the Apache vhost;
- the Reverb websocket proxy (`/etc/apache2/steelerp-reverb.conf`) and the
  modules it needs;
- the `steelerp-reverb`, `steelerp-queue` and `steelerp-scheduler` units (the
  scheduler as a oneshot plus a one-minute timer);
- the runner's sudoers rule.

It never writes `.env` and never deploys.

```bash
sudo /var/www/ProjectsERP/scripts/provision.sh staging            # plan: prints a diff, changes nothing
sudo /var/www/ProjectsERP/scripts/provision.sh staging --apply    # back up, validate, write, reload
     scripts/provision.sh production --render /tmp/out            # render only, no root needed
```

Before anything reloads, `--apply` validates each group as a whole: the
sudoers rule with `visudo -c`, the units with `systemd-analyze verify`, and
Apache with `apache2ctl configtest`. Apache is rolled back from its backups if
the configtest refuses the new config. Every replaced file keeps a
`.bak-<timestamp>` copy next to it. Units are restarted only when their own
file changed, so a scheduler change does not drop everyone's websocket.

`provision.sh` refuses to run on a machine whose hostname is not the one its
config names, so the wrong environment cannot be applied to the wrong box.

**From Actions:** the **Provision** workflow (`provision.yml`, manual) always
runs a plan first. Tick *apply* to make the changes. Applying production waits
for the same approval a production deploy does. The workflow runs the
`provision.sh` deployed on the box, so a template change reaches a box by
**deploying it first and provisioning second**.

**A new box:** check out the repo at `/var/www/ProjectsERP`, write its `.env`,
run `scripts/install-runner.sh`, then `sudo scripts/provision.sh <env> --apply`
by hand. The first apply is what grants the runner its sudo rule, so it cannot
come from Actions.

CI renders both boxes' config on every push (the *Deploy & provision scripts*
job), so a template with an unreplaced `@PLACEHOLDER@` fails the PR, not a
server.

## Atomic deploys (`steelerp-deploy`)

A box cut over to the releases layout deploys with
`/usr/local/sbin/steelerp-deploy`, which provisioning installs from
`scripts/steelerp-deploy`. A box still on the in-place checkout keeps using
`scripts/deploy.sh`; the workflows pick whichever fits the box.

```
/var/www/ProjectsERP/
  repo.git/                 git source, fetched and never checked out
  releases/<UTC>-<sha>/     one per deploy; the last 5 are kept
  shared/.env               the box's only .env (DB_DATABASE is absolute)
  shared/storage/           logs, sessions, uploads, storage/backups
  current -> releases/…     the only thing that ever switches
```

A deploy builds the release, links in `shared/`, checks that it **boots**
(`artisan about` and `route:list`), backs up the database, and migrates. Only
**then** does it switch `current`, in a single `rename`. Anything that fails
before the switch removes the half-built release and leaves the live site
untouched. After the switch it reloads Apache and restarts the workers, then
checks `/up`. **If `/up` fails, it switches back by itself.**

```bash
sudo steelerp-deploy staging                 # deploy github/development
sudo steelerp-deploy production <sha|v-tag>
sudo steelerp-deploy staging status          # list releases, mark the live one
sudo steelerp-deploy staging rollback        # back to the previous release
```

**Migrations must be backward-compatible with the release before them.** From
the migration until the switch, the old code runs against the new schema.
Rollback also relies on it, because it moves the code and never the database.
Add a column in one release and drop the old one in a later release, never
both at once.

`steelerp-deploy` is what sudo runs, so a change to it reaches a box when the
box is **provisioned**, not when it is deployed. A deploy that finds the
installed copy older than the one in the release says so, in yellow.

`tests/deploy/atomic-deploy.sh` runs the real script end to end on every push
(CI's *Atomic deploy* job): the switch, a release that cannot boot never going
live, rollback, the switch back after a failed health check, and pruning.

## Rollback

**On the releases layout:** run the **Rollback** workflow, or
`sudo steelerp-deploy <env> rollback`. It moves the code back one release;
the database stays as it is.

**On the in-place layout, or to undo a migration:** every deploy backs the SQLite database up to `storage/backups/` *before*
migrating, keeping the last 20. To roll back:

```bash
sudo systemctl stop steelerp-queue steelerp-reverb
cp storage/backups/database-<timestamp>.sqlite database/database.sqlite
sudo scripts/deploy.sh production <previous-tag-or-sha>
```

## What the smoke tests actually check

`scripts/smoke-test.sh` is black-box — it verifies a *running* site, which is
the part unit tests cannot reach:

1. `/up` health route responds
2. `/login` renders real app HTML (proves PHP, DB-backed sessions, views)
3. `/dashboard` redirects anonymous users to login (auth is actually guarding)
4. The Vite manifest is served, and a hashed asset from it resolves — catches a
   deploy that skipped `npm run build`, where pages still return 200 while the
   frontend is broken
5. An unknown route does not leak `APP_KEY`, DB credentials, or framework paths
   (i.e. `APP_DEBUG` is not on in a public environment)
6. Reverb accepts a websocket upgrade, when `REVERB_URL` is set

It exits non-zero if any check fails, and treats an unreachable host as a
failure rather than a pass.
