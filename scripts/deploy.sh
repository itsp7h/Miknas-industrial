#!/usr/bin/env bash
#
# Deploy SteelERP to the machine this runs on. Intended to be invoked by a
# self-hosted GitHub Actions runner sitting on the same LAN as the target
# container (see .github/workflows/deploy-*.yml), but it is safe to run by
# hand: everything here is idempotent.
#
#   scripts/deploy.sh <staging|production> [git-ref]
#
# The ref defaults to github/development for staging and github/main for
# production.
#
set -euo pipefail

ENVIRONMENT="${1:?usage: deploy.sh <staging|production> [git-ref]}"

# Staging follows `development`, production follows `main`. An explicit ref as
# $2 overrides this (e.g. deploying a tag to production).
case "${1:-}" in
  staging)    DEFAULT_REF="github/development" ;;
  production) DEFAULT_REF="github/main" ;;
  *)          DEFAULT_REF="github/main" ;;
esac
REF="${2:-$DEFAULT_REF}"

# The ref reaches `git reset --hard` inside a script that runs as root via a
# NOPASSWD sudo rule, and in CI it originates from a workflow input. Validate it
# here rather than trusting the caller: the workflow that passes it is itself
# part of the deployable tree, so "the caller already validated it" is not a
# property this script can rely on. Allowed shapes are the two tracked
# branches, a `v*` release tag, and a commit SHA (what the production workflow
# actually sends).
case "$REF" in
  github/main|github/development) ;;
  *)
    if ! printf '%s' "$REF" | grep -Eq '^([0-9a-fA-F]{7,40}|v[0-9][A-Za-z0-9._-]*)$'; then
      echo "error: refusing to deploy ref '$REF' — expected github/main, github/development, a v* tag, or a commit SHA" >&2
      exit 2
    fi
    ;;
esac

APP_DIR="${APP_DIR:-/var/www/ProjectsERP}"
WEB_USER="${WEB_USER:-www-data}"

case "$ENVIRONMENT" in
  staging|production) ;;
  *) echo "error: environment must be 'staging' or 'production', got '$ENVIRONMENT'" >&2; exit 2 ;;
esac

log() { printf '\n\033[1m==> %s\033[0m\n' "$*"; }
# Yellow, and on stderr, so a skipped step is not mistaken for a done one
# when the log is skimmed for `==>`.
warn() { printf '\n\033[1;33m!!> %s\033[0m\n' "$*" >&2; }

cd "$APP_DIR"

log "Deploying $ENVIRONMENT from $REF"
git --no-pager log -1 --format='  currently at %h %s'

# ---------------------------------------------------------------------------
# Back up the database BEFORE anything can migrate it. The app is on SQLite,
# so a backup is a file copy — cheap enough to do on every single deploy.
# ---------------------------------------------------------------------------
DB_FILE="$APP_DIR/database/database.sqlite"
if [ -f "$DB_FILE" ]; then
  BACKUP_DIR="$APP_DIR/storage/backups"
  mkdir -p "$BACKUP_DIR"
  BACKUP="$BACKUP_DIR/database-$(date +%Y%m%d%H%M%S).sqlite"
  if command -v sqlite3 >/dev/null 2>&1; then
    # .backup is WAL-safe; a plain cp of a live database can tear.
    sqlite3 "$DB_FILE" ".backup '$BACKUP'"
  else
    cp "$DB_FILE" "$BACKUP"
  fi
  log "Database backed up to $BACKUP"
  # Keep the last 20 backups, drop the rest.
  ls -1t "$BACKUP_DIR"/database-*.sqlite 2>/dev/null | tail -n +21 | xargs -r rm --
else
  log "No SQLite database at $DB_FILE — skipping backup"
fi

log "Fetching code"
git fetch --all --prune
git reset --hard "$REF"
git --no-pager log -1 --format='  now at %h %s'

log "Installing PHP dependencies"
composer install --no-interaction --prefer-dist --no-progress --no-dev --optimize-autoloader

log "Building frontend"
npm ci
npm run build

log "Running migrations"
php artisan migrate --force

# Permissions are declared in config/access.php and only exist in the database
# once this has run. Without it every `permission:` route refuses everyone and
# the app is a locked door with a working login. AccessSeeder only ever adds —
# it creates missing permissions and leaves every grant alone — so it is safe
# on each deploy, and a new tab is reachable the moment its code lands.
log "Seeding access permissions"
php artisan db:seed --class=AccessSeeder --force

log "Clearing caches"
php artisan optimize:clear
php artisan storage:link || true

log "Fixing ownership"
chown -R "$WEB_USER:$WEB_USER" storage bootstrap/cache database public/build public/storage 2>/dev/null || true

# ---------------------------------------------------------------------------
# Restart background workers if this host runs them. Guarded, because the unit
# names only exist where they have been installed.
#
# The guard used to pass over a missing unit in silence, and that is how
# production ran without Reverb long enough for it to be noticed as "deleting
# an item gives a server error" rather than as "the websocket server is not
# running". A skipped worker is not a normal outcome on a host that is meant to
# have one, so say so, loudly enough to read in the deploy log.
# ---------------------------------------------------------------------------
for unit in steelerp-reverb steelerp-queue; do
  if ! systemctl list-unit-files "$unit.service" >/dev/null 2>&1; then
    warn "$unit is not installed on this host — skipping. Live updates will not work."
    continue
  fi

  if ! systemctl is-enabled "$unit" >/dev/null 2>&1; then
    warn "$unit is installed but not enabled — skipping. Run: systemctl enable --now $unit"
    continue
  fi

  log "Restarting $unit"
  systemctl restart "$unit"

  if ! systemctl is-active "$unit" >/dev/null 2>&1; then
    warn "$unit did not come back up after a restart. Check: systemctl status $unit"
  fi
done

if systemctl is-active apache2 >/dev/null 2>&1; then
  log "Reloading apache2"
  systemctl reload apache2
fi

# ---------------------------------------------------------------------------
# Keep the Reverb websocket proxy in place. Browsers reach Reverb at
# wss://<domain>:443 through Apache, and that rule lives in Apache's config
# rather than in the app, so nothing re-created it: until 2026-09-24 neither
# box had it at all. Re-applying it on every deploy means a rebuilt box gets
# live updates back at its next deploy. setup-reverb-proxy.sh is idempotent
# and runs `apache2ctl configtest` before it reloads, so a bad rule fails the
# deploy instead of reaching the running Apache.
#
# The domain is the one the bundle was just built to dial. A box that does not
# serve the SPA over HTTPS (a LAN-only setup) needs no proxy, so it is skipped.
# ---------------------------------------------------------------------------
env_value() { grep -E "^$1=" .env | tail -1 | cut -d= -f2- | tr -d '"' || true; }
WS_HOST=$(env_value VITE_REVERB_HOST)
# Unset means https, the same default resources/js-app/echo.js applies.
WS_SCHEME=$(env_value VITE_REVERB_SCHEME)
WS_SCHEME=${WS_SCHEME:-https}

if [ "$WS_SCHEME" != "https" ]; then
  warn "VITE_REVERB_SCHEME is '$WS_SCHEME', not https — skipping the Reverb proxy."
elif [ -z "$WS_HOST" ] || [[ "$WS_HOST" == *'$'* ]]; then
  warn "VITE_REVERB_HOST is '${WS_HOST:-unset}' — set it to the public domain as a literal. Skipping the Reverb proxy."
elif ! systemctl is-active steelerp-reverb >/dev/null 2>&1; then
  warn "steelerp-reverb is not running — skipping the Reverb proxy. Live updates will not work."
else
  log "Ensuring the Reverb websocket proxy for $WS_HOST"
  APP_DIR="$APP_DIR" "$APP_DIR/scripts/setup-reverb-proxy.sh" "$WS_HOST"
fi

log "Deploy of $ENVIRONMENT complete"
