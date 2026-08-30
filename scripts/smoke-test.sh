#!/usr/bin/env bash
#
# Post-deploy checks against a running SteelERP instance. These are
# deliberately black-box: they prove the deployed site actually serves, not
# that the code compiles — CI already covers the latter.
#
#   scripts/smoke-test.sh http://192.168.0.38          # staging
#   scripts/smoke-test.sh https://steelerp.p7h.me      # production
#
# Optional: REVERB_URL=http://192.168.0.38:8080 to also check websockets.
#
set -uo pipefail

BASE_URL="${1:?usage: smoke-test.sh <base-url>}"
BASE_URL="${BASE_URL%/}"
CURL=(curl --silent --show-error --max-time 20 --location)

PASS=0
FAIL=0

ok()   { printf '  \033[32mPASS\033[0m %s\n' "$1"; PASS=$((PASS + 1)); }
bad()  { printf '  \033[31mFAIL\033[0m %s\n'  "$1"; FAIL=$((FAIL + 1)); }

check_status() {
  local label="$1" path="$2" expected="$3"
  local got
  got=$("${CURL[@]}" -o /dev/null -w '%{http_code}' "$BASE_URL$path" 2>/dev/null)
  if [ "$got" = "$expected" ]; then ok "$label ($path -> $got)"
  else bad "$label ($path -> got $got, want $expected)"; fi
}

check_contains() {
  local label="$1" path="$2" needle="$3" body
  # Capture first, then match. Piping straight into `grep -q` makes grep exit
  # on the first match and close the pipe, curl dies of SIGPIPE, and pipefail
  # reports the pipeline as failed even though the content was there.
  body=$("${CURL[@]}" "$BASE_URL$path" 2>/dev/null)
  if printf '%s' "$body" | grep -q -- "$needle"; then
    ok "$label"
  else
    bad "$label (response from $path did not contain '$needle')"
  fi
}

echo "Smoke-testing $BASE_URL"

# 1. Framework is up at all. Laravel's built-in health route.
check_status "health endpoint" /up 200

# 2. The login page renders — proves PHP, the DB connection behind the
#    session driver, and the view layer are all working, not just the router.
check_status "login page renders" /login 200
check_contains "login page is the app, not an error page" /login "csrf-token"

# 3. Auth actually guards the app rather than the dashboard being public.
echo -n ""
REDIRECT=$(curl --silent --max-time 20 -o /dev/null -w '%{redirect_url}' "$BASE_URL/dashboard" 2>/dev/null)
case "$REDIRECT" in
  */login) ok "dashboard redirects anonymous users to login" ;;
  *)       bad "dashboard did not redirect to login (got '${REDIRECT:-no redirect}')" ;;
esac

# 4. The built frontend bundle is actually being served. A deploy that skips
#    `npm run build` leaves a manifest pointing at files that 404 — the page
#    still returns 200 while being completely broken in the browser.
MANIFEST=$("${CURL[@]}" "$BASE_URL/build/manifest.json" 2>/dev/null)
if printf '%s' "$MANIFEST" | grep -q '"file"'; then
  ok "vite manifest is served"
  ASSET=$(printf '%s' "$MANIFEST" | grep -oE '"file"[[:space:]]*:[[:space:]]*"[^"]*"' | head -1 | sed 's/.*"\([^"]*\)"$/\1/')
  if [ -n "$ASSET" ]; then
    check_status "hashed asset from manifest is served" "/build/$ASSET" 200
  fi
else
  bad "vite manifest missing or malformed at /build/manifest.json"
fi

# 5. Debug mode must never be on where the public can reach it. A 500 page
#    with APP_DEBUG=true leaks env values; this asserts the generic page.
NOT_FOUND=$("${CURL[@]}" "$BASE_URL/__smoke_test_missing_route_$$" 2>/dev/null)
if [ -z "$NOT_FOUND" ]; then
  # No body at all means the host is unreachable, not that it is safe. Without
  # this guard the check passes trivially against a dead server.
  bad "error page check got no response from $BASE_URL"
elif printf '%s' "$NOT_FOUND" | grep -qiE 'APP_KEY|DB_PASSWORD|vendor/laravel/framework|Whoops'; then
  bad "error page leaks internals — APP_DEBUG is likely true"
else
  ok "error page does not leak internals"
fi

# 6. Websockets, when a Reverb URL is given. The React app's live updates
#    depend on this and nothing else in the suite would notice it being down.
if [ -n "${REVERB_URL:-}" ]; then
  CODE=$(curl --silent --max-time 10 -o /dev/null -w '%{http_code}' \
    -H "Connection: Upgrade" -H "Upgrade: websocket" \
    -H "Sec-WebSocket-Version: 13" -H "Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==" \
    "${REVERB_URL%/}/app/${REVERB_APP_KEY:-invalid}?protocol=7&client=js&version=8.4.0" 2>/dev/null)
  if [ "$CODE" = "101" ]; then ok "reverb accepts websocket upgrades"
  else bad "reverb did not upgrade (got $CODE, want 101)"; fi
fi

echo
echo "  $PASS passed, $FAIL failed"
[ "$FAIL" -eq 0 ]
