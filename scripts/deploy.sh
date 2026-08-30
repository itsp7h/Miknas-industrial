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
APP_DIR="${APP_DIR:-/var/www/ProjectsERP}"
WEB_USER="${WEB_USER:-www-data}"

case "$ENVIRONMENT" in
  staging|production) ;;
  *) echo "error: environment must be 'staging' or 'production', got '$ENVIRONMENT'" >&2; exit 2 ;;
esac

log() { printf '\n\033[1m==> %s\033[0m\n' "$*"; }

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

log "Clearing caches"
php artisan optimize:clear
php artisan storage:link || true

log "Fixing ownership"
chown -R "$WEB_USER:$WEB_USER" storage bootstrap/cache database public/build public/storage 2>/dev/null || true

# ---------------------------------------------------------------------------
# Restart background workers if this host runs them. Guarded, because the unit
# names only exist where they have been installed.
# ---------------------------------------------------------------------------
for unit in steelerp-reverb steelerp-queue; do
  if systemctl list-unit-files "$unit.service" >/dev/null 2>&1 \
     && systemctl is-enabled "$unit" >/dev/null 2>&1; then
    log "Restarting $unit"
    systemctl restart "$unit"
  fi
done

if systemctl is-active apache2 >/dev/null 2>&1; then
  log "Reloading apache2"
  systemctl reload apache2
fi

log "Deploy of $ENVIRONMENT complete"
