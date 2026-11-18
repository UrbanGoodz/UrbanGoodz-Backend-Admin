#!/usr/bin/env bash
# Swap a freshly built release into place, safely, with an automatic rollback that cannot nest.
#
#   bash cutover.sh <LIVE_DIR> <RELEASE_DIR> <ROLLBACK_DIR> [PUBLIC_URL] [PHP_BIN]
#
# Lessons built in (all learned the hard way on this host):
#  * NEVER cache config/routes/views in the release folder before the swap: Laravel bakes absolute paths into
#    bootstrap/cache/*.php, so the app keeps looking in the OLD folder after it is renamed and every request 500s.
#    This script removes any such files before the swap and builds the caches AFTER it, in the final location.
#  * The rollback must not nest: it refuses to move anything onto a path that already exists.
#  * The release must be a real app root (artisan + vendor/autoload.php at its top level), not nested a level down.
# The DATABASE steps (final dump, migrate, checksum compare) are done by the operator around this script.
set -euo pipefail
LIVE="${1:?live dir}"; REL="${2:?release dir}"; PREV="${3:?rollback dir}"; URL="${4:-}"; PHP="${5:-php}"

say() { echo "[cutover] $*"; }
die() { echo "[cutover] ABORT: $*" >&2; exit 1; }

# ---- pre-flight (nothing is changed until all of this passes)
[ -d "$LIVE" ] || die "live dir missing: $LIVE"
[ -f "$LIVE/artisan" ] || die "live dir is not an app root: $LIVE"
[ -f "$REL/artisan" ] || die "release is not an app root (artisan not at top level; is it nested?): $REL"
[ -f "$REL/vendor/autoload.php" ] || die "release has no vendor/autoload.php (composer install did not finish)"
[ -f "$REL/.env" ] || die "release has no .env"
[ ! -e "$PREV" ] || die "rollback dir already exists, refusing to overwrite: $PREV"
[ "$(cd "$LIVE" && pwd)" != "$(cd "$REL" && pwd)" ] || die "live and release are the same folder"

# ---- the path-poisoned caches must not travel
rm -f "$REL"/bootstrap/cache/config.php "$REL"/bootstrap/cache/routes-*.php "$REL"/bootstrap/cache/events.php "$REL"/bootstrap/cache/modules.php 2>/dev/null || true
rm -f "$REL"/storage/framework/views/*.php 2>/dev/null || true
for f in "$REL"/bootstrap/cache/*.php; do
  [ -e "$f" ] || continue
  case "$(basename "$f")" in packages.php|services.php) ;; *) die "unexpected cache file in release: $f";; esac
done

# Healthy = HTTP 200, or a redirect to a login page (this app's root redirects to /login/admin by design).
health() {
  [ -z "$URL" ] && return 0
  local out code loc
  out="$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' --max-time 15 "$URL")"
  code="${out%% *}"; loc="${out#* }"
  [ "$code" = "200" ] && return 0
  case "$code" in 301|302|303|307|308) case "$loc" in *login*) return 0;; esac;; esac
  return 1
}

rollback() {
  say "ROLLING BACK"
  FAILED="${LIVE}_failed_$(date +%Y%m%d%H%M%S)"
  [ ! -e "$FAILED" ] || { echo "[cutover] cannot roll back safely: $FAILED exists" >&2; exit 2; }
  mv "$LIVE" "$FAILED"                         # the broken release steps aside to a UNIQUE name (never onto an existing path)
  mv "$PREV" "$LIVE"                           # the old live folder comes back
  rm -f "$LIVE"/bootstrap/cache/config.php "$LIVE"/bootstrap/cache/routes-*.php 2>/dev/null || true
  ( cd "$LIVE" && "$PHP" artisan config:cache >/dev/null 2>&1 && "$PHP" artisan route:cache >/dev/null 2>&1 && "$PHP" artisan up >/dev/null 2>&1 ) || true
  say "rolled back; failed release kept at $FAILED"
}

# ---- the swap
( cd "$LIVE" && "$PHP" artisan down --retry=60 >/dev/null 2>&1 ) || say "note: could not put the live site in maintenance mode"
mv "$LIVE" "$PREV"
mv "$REL" "$LIVE"
say "swapped: $LIVE is now the release; the old app is at $PREV"

# ---- caches are built NOW, in the final location
if ! ( cd "$LIVE" && "$PHP" artisan config:cache && "$PHP" artisan route:cache && "$PHP" artisan view:cache ) >/dev/null 2>&1; then
  say "cache build failed"; rollback; exit 3
fi
grep -q "$REL" "$LIVE/bootstrap/cache/config.php" 2>/dev/null && { say "cached config still points at the old build path"; rollback; exit 4; }

( cd "$LIVE" && "$PHP" artisan up >/dev/null 2>&1 ) || true
sleep 2
if ! health; then
  say "health check failed ($URL)"; rollback; exit 5
fi
say "OK: live is serving the new release. Rollback point: $PREV"
