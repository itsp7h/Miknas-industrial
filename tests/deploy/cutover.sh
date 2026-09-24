#!/usr/bin/env bash
#
# End-to-end test of scripts/cutover-to-releases.sh on a throwaway in-place
# checkout: real git, composer, a Laravel boot, maintenance mode, a SQLite
# database with a row in it, an uploaded file, and HTTP /up from `current`.
#
#   tests/deploy/cutover.sh            # from the repo root
#
# What it proves:
#   1. the plan changes nothing
#   2. --apply produces the releases layout, keeps the old checkout, and loses
#      neither the database row nor the uploaded file; the site ends up out
#      of maintenance mode and answering /up
#   3. steelerp-deploy then deploys on the result (the two scripts compose)
#   4. a cutover that fails after the swap puts the old checkout back by itself
#
set -euo pipefail

ROOT=$(cd "$(dirname "$0")/../.." && pwd)
CUTOVER="$ROOT/scripts/cutover-to-releases.sh"
DEPLOY="$ROOT/scripts/steelerp-deploy"
T=$(mktemp -d)
PORT=${PORT:-18766}
SERVER_PID=

cleanup() {
  [ -n "$SERVER_PID" ] && kill "$SERVER_PID" 2>/dev/null || true
  rm -rf "$T"
}
trap cleanup EXIT

pass() { printf '  \033[32mPASS\033[0m %s\n' "$*"; }
fail() { printf '  \033[31mFAIL\033[0m %s\n' "$*"; exit 1; }

export RETRY_DELAY=0 SKIP_HOST_CHECK=1 SKIP_SERVICES=1 SKIP_PROVISION=1 SKIP_CHOWN=1
export SKIP_FRONTEND=1
export HEALTH_URL="http://127.0.0.1:$PORT/up"

# An in-place checkout like the boxes have today: code, vendor/, .env, a
# database with data in it, and an uploaded file under storage/.
make_checkout() {
  local dir="$1"
  git clone --quiet "$ROOT" "$dir"
  git -C "$dir" remote add github "$ROOT"
  (cd "$dir" && COMPOSER_ALLOW_SUPERUSER=1 composer install --quiet --no-interaction --no-dev --prefer-dist)
  cp "$ROOT/.env.example" "$dir/.env"
  {
    echo "APP_KEY=base64:$(head -c 32 /dev/urandom | base64)"
    echo "APP_ENV=testing"
    echo "APP_URL=http://127.0.0.1:$PORT"
    echo "DB_CONNECTION=sqlite"
    echo "BROADCAST_CONNECTION=null"
    echo "CACHE_STORE=file"
    echo "SESSION_DRIVER=file"
    echo "QUEUE_CONNECTION=sync"
  } >>"$dir/.env"
  touch "$dir/database/database.sqlite"
  php -r '$d = new SQLite3($argv[1]); $d->exec("CREATE TABLE marker (v TEXT)"); $d->exec("INSERT INTO marker VALUES (\"survived\")");' "$dir/database/database.sqlite"
  mkdir -p "$dir/storage/app/public"
  echo "an upload" >"$dir/storage/app/public/upload.txt"
}

serve_current() {
  cat >"$T/router.php" <<PHP
<?php
\$_SERVER['SCRIPT_FILENAME'] = '$1/current/public/index.php';
chdir('$1/current/public');
require '$1/current/public/index.php';
PHP
  php -S "127.0.0.1:$PORT" "$T/router.php" >/dev/null 2>&1 &
  SERVER_PID=$!
}

# --- 1-3: a clean cutover --------------------------------------------------
export APP_DIR="$T/a/ProjectsERP"
mkdir -p "$T/a"
make_checkout "$APP_DIR"
serve_current "$APP_DIR"

echo "== 1. the plan changes nothing"
"$CUTOVER" staging >/dev/null
[ -d "$APP_DIR/.git" ] && [ ! -e "$APP_DIR/releases" ] && [ ! -e "$APP_DIR.next" ] || fail "the plan changed something"
pass "still an in-place checkout"

echo "== 2. --apply"
"$CUTOVER" staging --apply >/dev/null
for p in repo.git releases shared/.env shared/storage shared/database/database.sqlite current; do
  [ -e "$APP_DIR/$p" ] || fail "missing $p"
done
[ -d "$APP_DIR.pre-releases/.git" ] || fail "the old checkout was not kept"
grep -q "^DB_DATABASE=$APP_DIR/shared/database/database.sqlite$" "$APP_DIR/shared/.env" || fail "DB_DATABASE is not the absolute shared path"
got=$(php -r '$d = new SQLite3($argv[1]); echo $d->querySingle("SELECT v FROM marker");' "$APP_DIR/shared/database/database.sqlite")
[ "$got" = survived ] || fail "the database row did not survive (got '$got')"
[ "$(cat "$APP_DIR/current/storage/app/public/upload.txt")" = "an upload" ] || fail "the upload did not survive"
[ ! -e "$APP_DIR/shared/storage/framework/down" ] || fail "left in maintenance mode"
code=$(curl -s -o /dev/null -w '%{http_code}' "$HEALTH_URL")
[ "$code" = 200 ] || fail "/up answered $code"
pass "releases layout; old checkout kept; data and upload intact; up; /up 200"

echo "== 3. steelerp-deploy deploys on the result"
FIRST=$(basename "$(readlink "$APP_DIR/current")")
sleep 1
# No CI publishes builds to this throwaway checkout, so build on the box.
"$DEPLOY" staging "$(git -C "$ROOT" rev-parse HEAD)" --build-here >/dev/null
[ "$(basename "$(readlink "$APP_DIR/current")")" != "$FIRST" ] || fail "current did not move"
got=$(php -r '$d = new SQLite3($argv[1]); echo $d->querySingle("SELECT v FROM marker");' "$APP_DIR/shared/database/database.sqlite")
[ "$got" = survived ] || fail "the deploy lost the data"
pass "deployed a new release on the cut-over layout; data intact"
kill "$SERVER_PID"; SERVER_PID=

# --- 4: a cutover that fails after the swap --------------------------------
echo "== 4. a failure after the swap puts the old checkout back"
export APP_DIR="$T/b/ProjectsERP"
mkdir -p "$T/b"
make_checkout "$APP_DIR"
if HEALTH_URL="http://127.0.0.1:1/up" "$CUTOVER" staging --apply >/dev/null 2>&1; then
  fail "a cutover whose /up fails reported success"
fi
[ -d "$APP_DIR/.git" ] && [ ! -e "$APP_DIR/releases" ] || fail "the old checkout is not back in place"
[ ! -e "$APP_DIR.pre-releases" ] || fail "the old checkout was left at .pre-releases"
[ ! -e "$APP_DIR/storage/framework/down" ] || fail "the restored checkout was left in maintenance mode"
ls -d "$APP_DIR".failed-cutover-* >/dev/null 2>&1 || fail "the failed attempt was not kept for inspection"
pass "old checkout restored, back up, failed attempt kept"

echo
echo "All cutover checks passed."
