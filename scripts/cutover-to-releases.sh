#!/usr/bin/env bash
#
# One-time move of a box from the in-place checkout to the releases layout
# that scripts/steelerp-deploy deploys into. Phase 3b of
# docs/superpowers/specs/2026-09-24-atomic-deploys-design.md.
#
#   sudo scripts/cutover-to-releases.sh <staging|production>           # plan: checks + steps, changes nothing
#   sudo scripts/cutover-to-releases.sh <staging|production> --apply   # do it
#
# Before:  /var/www/ProjectsERP            a git checkout, served in place
# After:   /var/www/ProjectsERP/repo.git   that checkout's .git, now bare
#                               releases/<id>/   the checkout's code, as the first release
#                               shared/.env      its .env, with DB_DATABASE made absolute
#                               shared/storage/  its storage/
#                               shared/database/database.sqlite
#                               current -> releases/<id>
#          /var/www/ProjectsERP.pre-releases   the old checkout, untouched, kept
#                                              as the way back
#
# The site is in maintenance mode for the part that must be consistent: from
# the final copy of storage and the database, through the swap, to Apache and
# the services being repointed. That is typically well under a minute.
#
# Everything is built beside the live checkout (ProjectsERP.next) first. The
# swap is two renames. If anything after the swap fails, the script puts the
# old checkout back, re-provisions it and brings it back up by itself.
#
# Test hooks (CI runs this against a throwaway checkout): APP_DIR,
# SKIP_HOST_CHECK=1, SKIP_SERVICES=1, SKIP_PROVISION=1, SKIP_CHOWN=1,
# HEALTH_URL.
#
set -euo pipefail

ENVIRONMENT="${1:?usage: cutover-to-releases.sh <staging|production> [--apply]}"
MODE="${2:-plan}"
case "$ENVIRONMENT" in staging | production) ;; *) echo "error: staging or production" >&2; exit 2 ;; esac
case "$MODE" in plan | --apply) ;; *) echo "error: second argument must be --apply, or nothing" >&2; exit 2 ;; esac

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# The box's config sets APP_DIR too; one given in the environment (the tests)
# must win over it, not be silently replaced by it.
APP_DIR_FROM_ENV="${APP_DIR:-}"
# shellcheck source=/dev/null
. "$HERE/provision/$ENVIRONMENT.conf"
APP_DIR="${APP_DIR_FROM_ENV:-$APP_DIR}"
NEXT="$APP_DIR.next"
OLD="$APP_DIR.pre-releases"
WEB_USER="${WEB_USER:-www-data}"

log() { printf '\n\033[1m==> %s\033[0m\n' "$*"; }
note() { printf '    %s\n' "$*"; }
warn() { printf '\n\033[1;33m!!> %s\033[0m\n' "$*" >&2; }
die() { printf '\n\033[1;31mxx> %s\033[0m\n' "$*" >&2; exit 1; }
problem() { note "PROBLEM  $*"; PROBLEMS=$((PROBLEMS + 1)); }
okay() { note "ok       $*"; }

env_value() { grep -E "^$1=" "$2" | tail -1 | cut -d= -f2- | tr -d '"' || true; }

# ---------------------------------------------------------------------------
# Checks. The plan is only these plus the list of steps; --apply refuses to
# start while any of them fails.
# ---------------------------------------------------------------------------
log "Cutover of $ENVIRONMENT to the releases layout ($MODE)"
PROBLEMS=0

if [ "${SKIP_HOST_CHECK:-}" != 1 ]; then
  [ "$(id -u)" -eq 0 ] && okay "running as root" || problem "not root"
  [ "$(hostname)" = "$EXPECTED_HOSTNAME" ] && okay "on $EXPECTED_HOSTNAME" \
    || problem "this is '$(hostname)', not $EXPECTED_HOSTNAME"
fi

[ -d "$APP_DIR/.git" ] && okay "$APP_DIR is a git checkout" || problem "$APP_DIR is not a git checkout"
[ ! -d "$APP_DIR/releases" ] && okay "not cut over yet" || problem "$APP_DIR/releases already exists: already cut over?"
[ ! -e "$NEXT" ] && okay "$NEXT is free" || problem "$NEXT exists (left by an earlier attempt?). Inspect it, then remove it"
[ ! -e "$OLD" ] && okay "$OLD is free" || problem "$OLD exists. An earlier cutover was reverted? Move it aside first"
[ -f "$APP_DIR/.env" ] && okay ".env present" || problem "no $APP_DIR/.env"

DB_NOW=$(env_value DB_DATABASE "$APP_DIR/.env" 2>/dev/null || true)
DB_NOW=${DB_NOW:-$APP_DIR/database/database.sqlite}
[[ "$DB_NOW" = /* ]] || DB_NOW="$APP_DIR/$DB_NOW"
[ -f "$DB_NOW" ] && okay "database at $DB_NOW ($(du -h "$DB_NOW" | cut -f1))" || problem "no database at $DB_NOW"
DB_NEW="$APP_DIR/shared/database/database.sqlite"

if command -v sqlite3 >/dev/null 2>&1 || php -r 'exit(class_exists("SQLite3") ? 0 : 1);'; then
  okay "can take an online SQLite backup"
else
  problem "neither the sqlite3 CLI nor PHP's SQLite3 extension is available"
fi

SHA=unknown
if [ -d "$APP_DIR" ]; then
  # The release copies code and vendor/ (not node_modules/), storage is copied
  # once: roughly the checkout minus .git and node_modules, plus storage again.
  NEED_KB=$(du -sk --exclude=.git --exclude=node_modules "$APP_DIR" 2>/dev/null | cut -f1)
  FREE_KB=$(df -Pk "$(dirname "$APP_DIR")" | awk 'NR==2 {print $4}')
  if [ "$FREE_KB" -gt $((NEED_KB * 2)) ]; then
    okay "disk: needs ~$((NEED_KB / 1024)) MB, $((FREE_KB / 1024)) MB free"
  else
    problem "disk: needs ~$((NEED_KB / 1024)) MB (x2 for headroom), only $((FREE_KB / 1024)) MB free"
  fi
  SHA=$(git -C "$APP_DIR" rev-parse HEAD 2>/dev/null || echo unknown)
  if [ -z "$(git -C "$APP_DIR" status --porcelain --untracked-files=no 2>/dev/null)" ]; then
    okay "checkout is clean at ${SHA:0:7}"
  else
    problem "checkout has local changes to tracked files; they would be carried into the first release unrecorded"
  fi
fi

ID="$(date -u +%Y%m%dT%H%M%S)-${SHA:0:7}"

log "Steps"
cat <<STEPS
    1. Build $NEXT beside the live site: repo.git (a bare copy of .git),
       releases/$ID (the checkout minus .git, storage, node_modules, .env
       and the database), shared/.env (DB_DATABASE -> $DB_NEW),
       a first copy of storage, and current -> releases/$ID.
    2. Maintenance mode on; stop steelerp-queue.        <- downtime starts
    3. Copy storage again (to pick up what changed) and take an online
       backup of the database into shared/.
    4. Swap: $APP_DIR -> $OLD, then $NEXT -> $APP_DIR.
    5. Provision from the new release: Apache DocumentRoot and the units
       move to current/, and steelerp-deploy is installed.
    6. Maintenance mode off; check /up.                  <- downtime ends
    If any step after 4 fails, the old checkout is swapped back, provisioned
    and brought back up automatically. $OLD is kept either way.
STEPS

if [ "$PROBLEMS" -gt 0 ]; then
  log "$PROBLEMS problem(s): fix them before --apply"
  exit 1
fi
if [ "$MODE" != --apply ]; then
  log "Plan only: nothing was changed. Re-run with --apply to cut over."
  exit 0
fi

# ===========================================================================
# Apply
# ===========================================================================
services() { [ "${SKIP_SERVICES:-}" = 1 ] || systemctl "$@"; }

copy_storage() {
  # First pass copies everything; the second only what changed since.
  if command -v rsync >/dev/null 2>&1; then
    rsync -a "$APP_DIR/storage/" "$NEXT/shared/storage/"
  else
    cp -au "$APP_DIR/storage/." "$NEXT/shared/storage/"
  fi
}

backup_db() {
  if command -v sqlite3 >/dev/null 2>&1; then
    sqlite3 "$1" ".backup '$2'"
  else
    php -r '$s = new SQLite3($argv[1], SQLITE3_OPEN_READONLY); $d = new SQLite3($argv[2]);
            if (! $s->backup($d)) { fwrite(STDERR, "backup failed\n"); exit(1); }' "$1" "$2"
  fi
}

health_ok() {
  local url="${HEALTH_URL:-http://127.0.0.1/up}" code i
  for i in 1 2 3 4 5; do
    code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 5 \
      -H "Host: $DOMAIN" -H 'X-Forwarded-Proto: https' "$url" || true)
    [ "$code" = 200 ] && return 0
    sleep 2
  done
  note "/up answered ${code:-nothing}"
  return 1
}

STAGE=before-swap

# Before the swap nothing live has moved: undoing is lifting maintenance mode
# and discarding .next. After it, the old checkout goes back where it was and
# is re-provisioned, which restores its vhost, units and sudoers.
undo() {
  local rc=$?
  [ $rc -eq 0 ] && return
  set +e
  if [ "$STAGE" = after-swap ]; then
    warn "cutover failed after the swap; putting the old checkout back"
    mv "$APP_DIR" "$APP_DIR.failed-cutover-$(date +%H%M%S)"
    mv "$OLD" "$APP_DIR"
    if [ "${SKIP_PROVISION:-}" != 1 ]; then "$APP_DIR/scripts/provision.sh" "$ENVIRONMENT" --apply; fi
    (cd "$APP_DIR" && php artisan up)
    services start steelerp-queue
    warn "back on the in-place checkout. The failed attempt is kept at $APP_DIR.failed-cutover-* for inspection."
  elif [ "$STAGE" = down ]; then
    (cd "$APP_DIR" && php artisan up)
    services start steelerp-queue
    rm -rf "$NEXT"
    warn "cutover failed before the swap; nothing live was moved, and the site is back up."
  else
    rm -rf "$NEXT"
    warn "cutover failed while preparing; nothing live was touched."
  fi
}
trap undo EXIT

log "1. Building $NEXT"
mkdir -p "$NEXT/releases/$ID" "$NEXT/shared/database" "$NEXT/shared/storage"
cp -a "$APP_DIR/.git" "$NEXT/repo.git"
git --git-dir="$NEXT/repo.git" config core.bare true
note "repo.git"

tar -C "$APP_DIR" \
  --exclude=./.git --exclude=./storage --exclude=./node_modules --exclude=./.env \
  --exclude='./database/*.sqlite' --exclude='./database/*.sqlite-*' \
  -cf - . | tar -C "$NEXT/releases/$ID" -xf -
# storage:link made public/storage an absolute link into the old checkout.
rm -f "$NEXT/releases/$ID/public/storage"
ln -s ../storage/app/public "$NEXT/releases/$ID/public/storage"
ln -s ../../shared/storage "$NEXT/releases/$ID/storage"
ln -s ../../shared/.env "$NEXT/releases/$ID/.env"
note "releases/$ID"

cp -a "$APP_DIR/.env" "$NEXT/shared/.env"
if grep -q '^DB_DATABASE=' "$NEXT/shared/.env"; then
  sed -i "s|^DB_DATABASE=.*|DB_DATABASE=$DB_NEW|" "$NEXT/shared/.env"
else
  printf '\n# The live database, outside every release (see steelerp-deploy).\nDB_DATABASE=%s\n' "$DB_NEW" >>"$NEXT/shared/.env"
fi
note "shared/.env (DB_DATABASE=$DB_NEW)"

copy_storage
note "shared/storage (first pass)"
ln -s "releases/$ID" "$NEXT/current"
# Until provisioning repoints Apache at current/public, the old DocumentRoot
# ($APP_DIR/public) must keep answering after the swap.
ln -s current/public "$NEXT/public"
note "current -> releases/$ID"

log "2. Maintenance mode"
STAGE=down
(cd "$APP_DIR" && php artisan down --retry=15)
services stop steelerp-queue

log "3. Final copy of storage and the database"
copy_storage
backup_db "$DB_NOW" "$NEXT/shared/database/database.sqlite"
note "database -> shared/database/database.sqlite"
if [ "${SKIP_CHOWN:-}" != 1 ]; then
  chown -R "$WEB_USER:$WEB_USER" "$NEXT/shared/storage" "$NEXT/shared/database" "$NEXT/releases/$ID/bootstrap/cache"
fi

log "4. Swap"
mv "$APP_DIR" "$OLD"
mv "$NEXT" "$APP_DIR"
STAGE=after-swap
note "$APP_DIR is the releases layout; the old checkout is $OLD"

log "5. Provisioning from the new release"
if [ "${SKIP_PROVISION:-}" != 1 ]; then
  "$APP_DIR/current/scripts/provision.sh" "$ENVIRONMENT" --apply
fi
(cd "$APP_DIR/current" && php artisan optimize:clear --quiet)

log "6. Back up"
(cd "$APP_DIR/current" && php artisan up)
services start steelerp-queue
health_ok || die "/up is not answering 200 after the cutover"
# Apache serves current/public now; the stop-gap link has done its job.
[ "${SKIP_PROVISION:-}" = 1 ] || rm -f "$APP_DIR/public"

STAGE=done
log "Cut over: $ENVIRONMENT deploys atomically from now on"
note "live release  $ID"
note "old checkout  $OLD (keep it until production has run a week on the new layout)"
note "status        sudo steelerp-deploy $ENVIRONMENT status"
