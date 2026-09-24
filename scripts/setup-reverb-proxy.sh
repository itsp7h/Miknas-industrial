#!/usr/bin/env bash
#
# Let browsers reach Reverb through Apache, on the box this runs on.
#
#   sudo scripts/setup-reverb-proxy.sh <public-domain>
#   sudo scripts/setup-reverb-proxy.sh steelerp.p7h.me
#   sudo scripts/setup-reverb-proxy.sh staging-steelerp.p7h.me
#
# The SPA is served over HTTPS, so it can only open wss://<domain>:443.
# Apache is what answers there, so it has to hand that one request shape
# to Reverb on this box. Written after both boxes were set up by hand on
# 2026-09-24 (see CLAUDE.md, Environments).
#
# Apache only. It never edits .env, rebuilds or deploys; a bundle built
# for the wrong address is reported, with the fix, and left alone.
# Safe to re-run: every step checks whether it is already done.
#
set -euo pipefail

DOMAIN="${1:?usage: setup-reverb-proxy.sh <public-domain>}"
APP_DIR="${APP_DIR:-/var/www/ProjectsERP}"
SNIPPET=/etc/apache2/steelerp-reverb.conf
STAMP=$(date +%F-%H%M%S)

log() { printf '\n\033[1m==> %s\033[0m\n' "$*"; }
warn() { printf '\n\033[1;33m!!> %s\033[0m\n' "$*" >&2; }
die() { printf '\n\033[1;31mxx> %s\033[0m\n' "$*" >&2; exit 1; }

[ "$(id -u)" -eq 0 ] || die "run as root: it edits Apache's config"
cd "$APP_DIR"

env_value() {
  grep -E "^$1=" .env | tail -1 | cut -d= -f2- | tr -d '"' || true
}

# ---------------------------------------------------------------------------
log "What the browser is built to dial"
# ---------------------------------------------------------------------------
# VITE_ values are baked in at `npm run build`, so the bundle is the truth,
# not .env: .env may have been edited since the last build.
BUILT=$(grep -aohE 'wsHost:"[^"]*",wsPort:"[^"]*"' \
  public/build/assets/main-*.js 2>/dev/null | head -1 || true)
echo "  ${BUILT:-no built bundle found}"
if [ "$BUILT" != "wsHost:\"$DOMAIN\",wsPort:\"443\"" ]; then
  warn "The bundle does not dial wss://$DOMAIN:443. The proxy will
    still be set up, but browsers will not use it until .env has
      VITE_REVERB_HOST=\"$DOMAIN\"
      VITE_REVERB_PORT=443
      VITE_REVERB_SCHEME=https
    as literals, followed by a redeploy (scripts/deploy.sh rebuilds)."
fi

# ---------------------------------------------------------------------------
log "Where Reverb listens"
# ---------------------------------------------------------------------------
PORT=$(env_value REVERB_SERVER_PORT)
PORT=${PORT:-8080}
LISTEN=$(ss -ltnH "sport = :$PORT" | awk '{print $4}' | head -1)
[ -n "$LISTEN" ] || die "nothing listens on :$PORT. Is steelerp-reverb
    installed and running? systemctl status steelerp-reverb"
TARGET=${LISTEN%:*}
case "$TARGET" in
  0.0.0.0 | \* | "[::]") TARGET=127.0.0.1 ;;
esac
echo "  $LISTEN, so Apache proxies to $TARGET:$PORT"

# ---------------------------------------------------------------------------
log "Apache modules"
# ---------------------------------------------------------------------------
a2enmod -q proxy proxy_http proxy_wstunnel rewrite

# ---------------------------------------------------------------------------
log "Proxy rules in $SNIPPET"
# ---------------------------------------------------------------------------
# Reverb speaks the Pusher protocol at /app/{key}, and /app is also the
# SPA's own prefix. So the rule needs BOTH the upgrade header and the
# key's shape (32 hex) before it fires; any other /app URL reaches Laravel.
cat > "$SNIPPET" <<RULES
# Reverb websocket proxy. Managed by scripts/setup-reverb-proxy.sh
RewriteEngine On
RewriteCond %{HTTP:Upgrade} =websocket [NC]
RewriteRule ^/app/([a-f0-9]{32})\$ ws://$TARGET:$PORT/app/\$1 [P,L]
RULES
sed 's/^/  /' "$SNIPPET"

# ---------------------------------------------------------------------------
log "Including it from the site's vhost"
# ---------------------------------------------------------------------------
# -R, not -r: sites-enabled holds symlinks and -r does not follow them.
VHOSTS=$(grep -Rl -- "$APP_DIR" /etc/apache2/sites-enabled/ || true)
[ -n "$VHOSTS" ] || die "no enabled vhost mentions $APP_DIR"
for f in $VHOSTS; do
  real=$(readlink -f "$f")
  if grep -q -- "$SNIPPET" "$real"; then
    echo "  already included: $real"
    continue
  fi
  cp "$real" "$real.bak-$STAMP"
  sed -i "s|</VirtualHost>|    Include $SNIPPET\n</VirtualHost>|" "$real"
  echo "  patched: $real (backup: $real.bak-$STAMP)"
done

apache2ctl configtest
systemctl reload apache2

# ---------------------------------------------------------------------------
log "Verifying"
# ---------------------------------------------------------------------------
# Through Apache, as the tunnel would send it. A websocket stays open, so
# curl is capped with --max-time and only the status line is read. Retried
# because a Reverb that has just been restarted can take a moment.
KEY=$(env_value REVERB_APP_KEY)
URL="http://127.0.0.1/app/$KEY?protocol=7&client=js&version=8.4.0"
STATUS=""
for _ in 1 2 3 4 5; do
  STATUS=$(curl -sS -i --max-time 3 \
    -H "Host: $DOMAIN" \
    -H 'X-Forwarded-Proto: https' \
    -H 'Connection: Upgrade' \
    -H 'Upgrade: websocket' \
    -H 'Sec-WebSocket-Version: 13' \
    -H 'Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==' \
    "$URL" 2>/dev/null | head -1 | tr -d '\r' || true)
  case "$STATUS" in *" 101 "*) break ;; esac
  sleep 2
done
echo "  ${STATUS:-no response}"
case "$STATUS" in
  *" 101 "*) log "Done: wss://$DOMAIN reaches Reverb" ;;
  *) die "Apache did not upgrade the connection. Check
    /var/log/apache2/*error.log and systemctl status steelerp-reverb" ;;
esac
