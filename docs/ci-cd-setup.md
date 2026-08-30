# CI/CD setup

## What runs where

| Workflow | Trigger | Runner | What it does |
|---|---|---|---|
| `ci.yml` | push to `main` or `development`, any PR | GitHub-hosted | PHP syntax check, Pint (changed files), PHPUnit on 8.2 + 8.3, Vitest, Vite build |
| `deploy-staging.yml` | CI green on `development`, or manual | self-hosted `staging` | Deploy + smoke test `http://192.168.0.38` |
| `deploy-production.yml` | tag `v*`, or manual | self-hosted `production` | Deploy + smoke test `https://steelerp.p7h.me`, behind an approval gate |

## Branching

| Branch | Deploys to | How |
|---|---|---|
| `development` | staging (`192.168.0.38`) | automatically, once CI is green |
| `main` | production (`steelerp.p7h.me`) | merge from `development`, then tag `v*` or run the workflow manually |

Day-to-day work lands on `development`. Nothing reaches production without an
explicit tag or manual run *and* an approval on the `production` environment.

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
  human; the workflow itself has no other gate.

## Deploying by hand

The scripts are standalone — nothing about them depends on Actions:

```bash
sudo /var/www/ProjectsERP/scripts/deploy.sh staging          # defaults to github/development
/var/www/ProjectsERP/scripts/smoke-test.sh http://192.168.0.38

sudo /var/www/ProjectsERP/scripts/deploy.sh production v1.2.0
/var/www/ProjectsERP/scripts/smoke-test.sh https://steelerp.p7h.me
```

## Rollback

Every deploy backs the SQLite database up to `storage/backups/` *before*
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
