#!/usr/bin/env bash
# Nightly backup: database dump + user files, verified, with an optional copy OFF the server.
#
# Usage (cron, once a day):  bash /path/to/app/scripts/ops/backup.sh /path/to/app
#
# Reads database settings from the app's .env WITHOUT printing them (they are written to a 0600 temp file that
# is removed on exit). Off-host copy: if `rclone` is installed and ~/.ug-backup.env defines
#   BACKUP_RCLONE_REMOTE=remotename:folder
# the new files are copied there. The rclone remote itself (and its credentials) is configured by the owner
# with `rclone config`; this script never sees or stores them.
set -euo pipefail
APP="${1:?usage: backup.sh /path/to/app}"
OUT="${BACKUP_DIR:-$HOME/backups}"
# Disk is tight on shared hosting, so: database dumps are tiny (kept 30 days, never fewer than 10); the user-files archive
# is big, so it is made weekly (Sunday) unless BACKUP_FILES_EVERY_RUN=1, and only the newest FILES_KEEP are kept.
KEEP_DAYS="${BACKUP_KEEP_DAYS:-30}"
FILES_KEEP="${BACKUP_FILES_KEEP:-3}"
MIN_KEEP=10
STAMP="$(date +%Y%m%d-%H%M%S)"
mkdir -p "$OUT"; chmod 700 "$OUT"
LOG="$OUT/backup.log"
log() { echo "$(date -u +%FT%TZ) $*" | tee -a "$LOG"; }

# Tolerates indented keys, an "export " prefix, quotes and Windows line endings. Never fails the script
# by itself: a missing key comes back empty and is reported loudly below.
env_val() { { grep -E "^[[:space:]]*(export[[:space:]]+)?$1[[:space:]]*=" "$APP/.env" || true; } | head -1 | cut -d= -f2- | tr -d '\r' | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//' -e 's/^["'\'']//' -e 's/["'\'']$//'; }
DB_NAME="$(env_val DB_DATABASE)"; DB_USER="$(env_val DB_USERNAME)"; DB_PASS="$(env_val DB_PASSWORD)"; DB_HOST="$(env_val DB_HOST)"
[ -f "$APP/.env" ] || { log "FAIL no .env at $APP"; exit 1; }
[ -n "$DB_NAME" ] || { log "FAIL no DB_DATABASE found in $APP/.env (keys may be formatted unexpectedly)"; exit 1; }
[ -n "$DB_USER" ] || { log "FAIL no DB_USERNAME found in $APP/.env"; exit 1; }

CNF="$(mktemp)"; trap 'rm -f "$CNF"' EXIT; chmod 600 "$CNF"
# Option-file values are quoted (a "#" in a password otherwise starts a comment and truncates it);
# backslashes and double quotes inside a value are escaped.
q() { printf '%s' "$1" | sed -e 's/\\/\\\\/g' -e 's/"/\\"/g'; }
printf '[client]\nuser="%s"\npassword="%s"\nhost="%s"\n' "$(q "$DB_USER")" "$(q "$DB_PASS")" "$(q "${DB_HOST:-localhost}")" > "$CNF"

DB_FILE="$OUT/db-$STAMP.sql.gz"
# A failed dump must never leave a real-looking file behind, and must never look like success.
if ! mysqldump --defaults-extra-file="$CNF" --single-transaction --no-tablespaces --routines "$DB_NAME" 2>"$OUT/.mysqldump.err" | gzip -9 > "$DB_FILE"; then
  rm -f "$DB_FILE"; log "FAIL mysqldump: $(head -c 300 "$OUT/.mysqldump.err" | tr '\n' ' ')"; rm -f "$OUT/.mysqldump.err"; exit 1
fi
rm -f "$OUT/.mysqldump.err"
gzip -t "$DB_FILE"
TABLES="$(zcat "$DB_FILE" | grep -c '^CREATE TABLE' || true)"
zcat "$DB_FILE" | tail -n 3 | grep -q 'Dump completed' || { log "FAIL dump incomplete: $DB_FILE"; rm -f "$DB_FILE"; exit 1; }
[ "$TABLES" -gt 50 ] || { log "FAIL suspiciously few tables ($TABLES): $DB_FILE"; rm -f "$DB_FILE"; exit 1; }
log "OK db $DB_FILE tables=$TABLES size=$(du -h "$DB_FILE" | cut -f1)"

FILES="$OUT/files-$STAMP.tar.gz"
if [ "$(date +%u)" = "7" ] || [ "${BACKUP_FILES_EVERY_RUN:-0}" = "1" ]; then
  tar -czf "$FILES" -C "$APP" storage/app public/Modules oauth-private.key oauth-public.key storage/oauth-private.key storage/oauth-public.key 2>/dev/null || true
  tar -tzf "$FILES" >/dev/null
  log "OK files $FILES size=$(du -h "$FILES" | cut -f1)"
else
  FILES=""
  log "NOTE files archive skipped (weekly on Sunday)"
fi
log "DISK backups dir=$(du -sh "$OUT" | cut -f1) home=$(du -sh "$HOME" 2>/dev/null | cut -f1)"

if [ -f "$HOME/.ug-backup.env" ]; then . "$HOME/.ug-backup.env"; fi
if [ -n "${BACKUP_RCLONE_REMOTE:-}" ] && command -v rclone >/dev/null 2>&1; then
  if rclone copy "$DB_FILE" "$BACKUP_RCLONE_REMOTE" && { [ -z "$FILES" ] || rclone copy "$FILES" "$BACKUP_RCLONE_REMOTE"; }; then
    log "OK offsite copy to $BACKUP_RCLONE_REMOTE"; touch "$OUT/LAST_OFFSITE_OK"
  else
    log "WARN offsite copy FAILED (local backup is still good)"
  fi
else
  log "NOTE no off-host destination configured (set BACKUP_RCLONE_REMOTE in ~/.ug-backup.env)"
fi

# Retention: only ever prune AUTOMATIC backups made by this script, and never go below MIN_KEEP of each kind.
# The big files archive: keep only the newest FILES_KEEP.
mapfile -t extra < <(find "$OUT" -maxdepth 1 -name 'files-*' | sort | head -n -"$FILES_KEEP")
for f in "${extra[@]:-}"; do [ -n "$f" ] && rm -f "$f" && log "pruned $f"; done
for kind in db; do
  mapfile -t old < <(find "$OUT" -maxdepth 1 -name "$kind-*" -mtime +"$KEEP_DAYS" | sort)
  total="$(find "$OUT" -maxdepth 1 -name "$kind-*" | wc -l)"
  for f in "${old[@]:-}"; do
    [ -n "$f" ] || continue
    [ "$total" -gt "$MIN_KEEP" ] || break
    rm -f "$f"; total=$((total-1)); log "pruned $f"
  done
done
touch "$OUT/LAST_OK"
log "DONE"
