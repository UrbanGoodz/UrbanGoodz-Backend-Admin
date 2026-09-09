#!/bin/bash
# =====================================================
# Urban Goodz - production deployment
#
# Replaces script/deploy-sprint-integration.sh, which had a DEPLOY_SHA from
# 2026-07-13 hardcoded into it. That SHA is now 505 commits behind the branch,
# so running the old script would have rolled production BACK to July, losing
# the IDOR fix, the repaired admin routes and every subsequent fix. This script
# takes the target explicitly and refuses to move backwards by accident.
#
# Usage:
#   bash script/deploy.sh                  # deploy origin/<branch> head
#   bash script/deploy.sh <sha-or-ref>     # deploy a specific commit
#   ALLOW_ROLLBACK=1 bash script/deploy.sh <older-sha>   # deliberate rollback
# =====================================================
set -euo pipefail

BRANCH="${DEPLOY_BRANCH:-adminpanel-v39-backend-sprint}"
BACKUP_DIR="backups/$(date +%Y%m%d_%H%M%S)"
ALLOW_ROLLBACK="${ALLOW_ROLLBACK:-0}"

echo "=== Urban Goodz deployment ==="
echo "Started: $(date)"

# ---------- 1. pre-flight -------------------------------------------------
echo "[1/11] Pre-flight..."
[ -f .env ]          || { echo "FATAL: .env not found - wrong directory."; exit 1; }
[ -f composer.json ] || { echo "FATAL: composer.json not found - wrong directory."; exit 1; }
php -v >/dev/null 2>&1 || { echo "FATAL: PHP not available"; exit 1; }
command -v git >/dev/null || { echo "FATAL: git not available"; exit 1; }
echo "  OK ($(pwd))"

# ---------- 2. resolve the target ----------------------------------------
echo "[2/11] Resolving deploy target..."
git fetch origin --quiet
if [ $# -ge 1 ]; then
    DEPLOY_SHA="$(git rev-parse --verify "$1^{commit}")"
else
    DEPLOY_SHA="$(git rev-parse --verify "origin/$BRANCH^{commit}")"
fi
CURRENT_SHA="$(git rev-parse HEAD)"
echo "  currently deployed: $(git log -1 --format='%h %ad %s' --date=short "$CURRENT_SHA")"
echo "  deploying:          $(git log -1 --format='%h %ad %s' --date=short "$DEPLOY_SHA")"

if [ "$DEPLOY_SHA" = "$CURRENT_SHA" ]; then
    echo "  Already at this commit. Nothing to do."
    exit 0
fi

# Refuse to silently move production backwards.
if git merge-base --is-ancestor "$DEPLOY_SHA" "$CURRENT_SHA" 2>/dev/null; then
    BEHIND=$(git rev-list --count "$DEPLOY_SHA..$CURRENT_SHA")
    if [ "$ALLOW_ROLLBACK" != "1" ]; then
        echo "  FATAL: target is $BEHIND commits BEHIND what is deployed."
        echo "         This would roll production back. If genuinely intended,"
        echo "         re-run with ALLOW_ROLLBACK=1."
        exit 1
    fi
    echo "  WARNING: rolling back $BEHIND commits (ALLOW_ROLLBACK=1)"
fi
echo "  Deploying $(git rev-list --count "$CURRENT_SHA..$DEPLOY_SHA") new commit(s)"

# ---------- 3. protect uncommitted production-only work ------------------
# The live tree has historically carried hotfixes never committed to git;
# `git checkout` would discard them silently. Capture first, then require an
# explicit acknowledgement before discarding anything.
echo "[3/11] Checking for uncommitted changes on the server..."
mkdir -p "$BACKUP_DIR"
if ! git diff --quiet || ! git diff --cached --quiet; then
    git diff HEAD > "$BACKUP_DIR/uncommitted-prod-changes.patch"
    echo "  WARNING: the live tree has uncommitted modifications:"
    git status --porcelain | grep -v "^??" | head -20
    echo "  Saved to $BACKUP_DIR/uncommitted-prod-changes.patch"
    if [ "${ACCEPT_DISCARD:-0}" != "1" ]; then
        echo "  FATAL: refusing to discard live changes."
        echo "         Review that patch, then re-run with ACCEPT_DISCARD=1."
        exit 1
    fi
    echo "  ACCEPT_DISCARD=1 - continuing, patch retained."
else
    echo "  Clean."
fi

# ---------- 4. file backup ------------------------------------------------
echo "[4/11] Backing up files..."
tar -czf "$BACKUP_DIR/files_$(date +%s).tar.gz" \
    --exclude=vendor --exclude=node_modules --exclude=.git \
    --exclude=backups --exclude="storage/logs/*" \
    --exclude="storage/framework/cache/*" --exclude="storage/framework/sessions/*" \
    --exclude="storage/framework/views/*" . 2>/dev/null || true
echo "  -> $BACKUP_DIR"

# ---------- 5. database backup -------------------------------------------
echo "[5/11] Backing up database..."
envval() { grep -m1 "^$1=" .env | cut -d= -f2- | tr -d '"' | tr -d "'"; }
DB_NAME=$(envval DB_DATABASE)
DB_USER=$(envval DB_USERNAME)
DB_PASS=$(envval DB_PASSWORD)
DB_HOST=$(envval DB_HOST)
DB_PORT=$(envval DB_PORT)
if command -v mysqldump >/dev/null 2>&1; then
    mysqldump -h "$DB_HOST" -P "${DB_PORT:-3306}" -u "$DB_USER" -p"$DB_PASS" \
        "$DB_NAME" > "$BACKUP_DIR/database_$(date +%s).sql"
    echo "  -> database dumped"
else
    echo "  WARNING: mysqldump unavailable."
    if [ "${SKIP_DB_BACKUP:-0}" != "1" ]; then
        echo "  FATAL: take a manual backup, then re-run with SKIP_DB_BACKUP=1."
        exit 1
    fi
fi

# ---------- 6. addon state snapshot --------------------------------------
# Booting artisan on this host has previously flipped system_addons.active
# from 1 to 0. Snapshot before, compare after, and say so loudly.
echo "[6/11] Snapshotting addon state..."
ADDONS_BEFORE=""
if command -v mysql >/dev/null 2>&1; then
    ADDONS_BEFORE=$(mysql -h "$DB_HOST" -P "${DB_PORT:-3306}" -u "$DB_USER" -p"$DB_PASS" \
        -N -B -e "SELECT id,active FROM system_addons ORDER BY id;" "$DB_NAME" 2>/dev/null || true)
    printf '%s\n' "$ADDONS_BEFORE" > "$BACKUP_DIR/system_addons_before.tsv"
    echo "  captured addon rows"
else
    echo "  SKIPPED (no mysql client) - verify addons manually after deploy."
fi

# ---------- 7. checkout ---------------------------------------------------
echo "[7/11] Checking out $DEPLOY_SHA..."
git checkout --quiet "$DEPLOY_SHA"
echo "  now at $(git log -1 --format='%h %s')"

# ---------- 8. dependencies ----------------------------------------------
echo "[8/11] composer install..."
composer install --no-dev --optimize-autoloader --no-interaction

# ---------- 9. migrations -------------------------------------------------
# The real pending list, not a hardcoded one that goes stale.
echo "[9/11] Migrations..."
PENDING=$(php artisan migrate:status 2>/dev/null | grep -ci "pending" || true)
php artisan migrate:status 2>/dev/null | grep -i "pending" || echo "  (none pending)"
if [ "${PENDING:-0}" -gt 0 ]; then
    if [ "${AUTO_MIGRATE:-0}" = "1" ]; then
        php artisan migrate --force
        echo "  Applied."
    else
        read -r -p "  Apply $PENDING pending migration(s)? (yes/no): " CONFIRM
        if [ "$CONFIRM" = "yes" ]; then
            php artisan migrate --force
            echo "  Applied."
        else
            echo "  SKIPPED."
        fi
    fi
fi

# ---------- 10. caches + queue -------------------------------------------
echo "[10/11] Rebuilding caches..."
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart 2>/dev/null || echo "  NOTE: queue:restart needs Supervisor"
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# ---------- 11. verify ----------------------------------------------------
echo "[11/11] Verifying..."
if php artisan route:list >/dev/null 2>&1; then
    echo "  route table builds cleanly"
else
    echo "  WARNING: route:list failed"
fi

if [ -n "$ADDONS_BEFORE" ] && command -v mysql >/dev/null 2>&1; then
    ADDONS_AFTER=$(mysql -h "$DB_HOST" -P "${DB_PORT:-3306}" -u "$DB_USER" -p"$DB_PASS" \
        -N -B -e "SELECT id,active FROM system_addons ORDER BY id;" "$DB_NAME" 2>/dev/null || true)
    printf '%s\n' "$ADDONS_AFTER" > "$BACKUP_DIR/system_addons_after.tsv"
    if [ "$ADDONS_BEFORE" != "$ADDONS_AFTER" ]; then
        echo "  *** ADDON STATE CHANGED DURING DEPLOY ***"
        diff "$BACKUP_DIR/system_addons_before.tsv" "$BACKUP_DIR/system_addons_after.tsv" || true
        echo "  Re-activate any addon whose active flag flipped 1 -> 0."
    else
        echo "  addon state unchanged"
    fi
fi

echo ""
echo "=== Deployment complete ==="
echo "SHA:      $DEPLOY_SHA"
echo "Backup:   $BACKUP_DIR"
echo "Rollback: git checkout $CURRENT_SHA && php artisan optimize:clear && php artisan config:cache"
