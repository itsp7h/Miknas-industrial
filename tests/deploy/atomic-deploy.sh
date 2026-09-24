#!/usr/bin/env bash
#
# End-to-end test of scripts/steelerp-deploy against a throwaway releases
# layout. It runs the real script: real git archive, real composer install,
# a real Laravel boot, real migrations on a SQLite file, and a real HTTP /up
# served from `current` by php -S. Only systemd, the frontend build and chown
# are switched off.
#
#   tests/deploy/atomic-deploy.sh            # from the repo root
#
# What it proves:
#   1. a first deploy creates a release and points `current` at it
#   2. a second deploy switches to a new release
#   3. a release that cannot boot fails BEFORE the switch: `current` is
#      untouched and the broken release is removed
#   4. rollback switches to the previous release
#   5. a release that fails its health check AFTER the switch is switched
#      back automatically
#   6. old releases are pruned to KEEP_RELEASES
#
set -euo pipefail

ROOT=$(cd "$(dirname "$0")/../.." && pwd)
DEPLOY="$ROOT/scripts/steelerp-deploy"
T=$(mktemp -d)
PORT=${PORT:-18765}
SERVER_PID=

cleanup() {
  [ -n "$SERVER_PID" ] && kill "$SERVER_PID" 2>/dev/null || true
  rm -rf "$T"
}
trap cleanup EXIT

pass() { printf '  \033[32mPASS\033[0m %s\n' "$*"; }
fail() { printf '  \033[31mFAIL\033[0m %s\n' "$*"; exit 1; }
live() { basename "$(readlink "$APP_DIR/current")"; }
count_releases() { find "$APP_DIR/releases" -mindepth 1 -maxdepth 1 -type d | wc -l; }

export APP_DIR="$T/app"
export SKIP_SERVICES=1 SKIP_FRONTEND=1 SKIP_CHOWN=1
export HEALTH_URL="http://127.0.0.1:$PORT/up"
export KEEP_RELEASES=3

# --- a layout like a cut-over box -----------------------------------------
mkdir -p "$APP_DIR/releases" "$APP_DIR/shared/database"
mkdir -p "$APP_DIR"/shared/storage/{app/public,framework/{cache/data,sessions,views},logs}
git clone --quiet --bare "$ROOT" "$APP_DIR/repo.git"
GOOD=$(git -C "$ROOT" rev-parse HEAD)

# The committed tree is what gets deployed; a release that cannot boot is the
# same tree plus one commit that throws from routes/channels.php, which is how
# PR #12 broke staging.
WORK="$T/work"
git clone --quiet "$APP_DIR/repo.git" "$WORK"
git -C "$WORK" -c user.name=t -c user.email=t@t commit --quiet --allow-empty -m "same code, new sha"
GOOD2=$(git -C "$WORK" rev-parse HEAD)
echo '<?php throw new RuntimeException("this release does not boot");' >> "$WORK/routes/channels.php"
git -C "$WORK" -c user.name=t -c user.email=t@t commit --quiet -am "broken"
BROKEN=$(git -C "$WORK" rev-parse HEAD)
git -C "$WORK" push --quiet origin HEAD:refs/heads/broken

cp "$ROOT/.env.example" "$APP_DIR/shared/.env"
{
  echo "APP_KEY=base64:$(head -c 32 /dev/urandom | base64)"
  echo "APP_ENV=testing"
  echo "APP_URL=http://127.0.0.1:$PORT"
  echo "DB_CONNECTION=sqlite"
  echo "DB_DATABASE=$APP_DIR/shared/database/database.sqlite"
  echo "BROADCAST_CONNECTION=null"
  echo "CACHE_STORE=file"
  echo "SESSION_DRIVER=file"
  echo "QUEUE_CONNECTION=sync"
} >> "$APP_DIR/shared/.env"
touch "$APP_DIR/shared/database/database.sqlite"

# /up served from whatever `current` points at, re-resolved on every request.
cat > "$T/router.php" <<PHP
<?php
\$_SERVER['SCRIPT_FILENAME'] = '$APP_DIR/current/public/index.php';
chdir('$APP_DIR/current/public');
require '$APP_DIR/current/public/index.php';
PHP
php -S "127.0.0.1:$PORT" "$T/router.php" >/dev/null 2>&1 &
SERVER_PID=$!

echo "== 1. first deploy"
"$DEPLOY" staging "$GOOD" >/dev/null
R1=$(live)
[ "$(count_releases)" = 1 ] || fail "expected 1 release, got $(count_releases)"
[ -L "$APP_DIR/current/.env" ] && [ -L "$APP_DIR/current/storage" ] || fail ".env and storage are not linked to shared/"
pass "current -> $R1, with .env and storage linked to shared/"

sleep 1
echo "== 2. second deploy switches"
"$DEPLOY" staging "$GOOD2" >/dev/null
R2=$(live)
[ "$R2" != "$R1" ] || fail "current did not move"
pass "current -> $R2"

sleep 1
echo "== 3. a release that cannot boot never goes live"
if "$DEPLOY" staging "$BROKEN" >/dev/null 2>&1; then fail "the broken release deployed"; fi
[ "$(live)" = "$R2" ] || fail "current moved to $(live)"
[ "$(count_releases)" = 2 ] || fail "the broken release was left behind"
pass "failed before the switch; current still $R2; broken release removed"

echo "== 4. rollback"
"$DEPLOY" staging rollback >/dev/null
[ "$(live)" = "$R1" ] || fail "rollback went to $(live), not $R1"
pass "current -> $R1"

sleep 1
echo "== 5. a failed health check switches back"
if HEALTH_URL="http://127.0.0.1:1/up" "$DEPLOY" staging "$GOOD" >/dev/null 2>&1; then
  fail "a deploy whose /up fails reported success"
fi
[ "$(live)" = "$R1" ] || fail "current is $(live), not back on $R1"
[ "$(count_releases)" = 2 ] || fail "the unhealthy release was left behind"
pass "switched back to $R1; unhealthy release removed"

echo "== 6. pruning keeps $KEEP_RELEASES"
for _ in 1 2 3; do sleep 1; "$DEPLOY" staging "$GOOD" >/dev/null; done
[ "$(count_releases)" = "$KEEP_RELEASES" ] || fail "expected $KEEP_RELEASES releases, got $(count_releases)"
pass "$(count_releases) releases kept"

echo "== status"
"$DEPLOY" staging status

echo
echo "All atomic-deploy checks passed."
