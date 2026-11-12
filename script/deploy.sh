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
MIGRATIONS_SKIPPED=0

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

# Being already at the target does NOT mean there is nothing to do. A run that
# stops between the checkout and step 10 - which is exactly what an EOF on the
# old migration prompt used to cause - leaves new code live on the PREVIOUS
# route and config cache, and any migration it shipped unapplied. Exiting 0
# here made that state unrecoverable by re-running the script. Steps 9-11 are
# idempotent, so finish them instead.
ALREADY_DEPLOYED=0
if [ "$DEPLOY_SHA" = "$CURRENT_SHA" ]; then
    ALREADY_DEPLOYED=1
    echo "  Already at this commit - code is current."
    echo "  Continuing to migrations and caches in case an earlier run stopped short."
fi

# Refuse to silently move production backwards.
if [ "$ALREADY_DEPLOYED" = "0" ] && git merge-base --is-ancestor "$DEPLOY_SHA" "$CURRENT_SHA" 2>/dev/null; then
    BEHIND=$(git rev-list --count "$DEPLOY_SHA..$CURRENT_SHA")
    if [ "$ALLOW_ROLLBACK" != "1" ]; then
        echo "  FATAL: target is $BEHIND commits BEHIND what is deployed."
        echo "         This would roll production back. If genuinely intended,"
        echo "         re-run with ALLOW_ROLLBACK=1."
        exit 1
    fi
    echo "  WARNING: rolling back $BEHIND commits (ALLOW_ROLLBACK=1)"
fi
if [ "$ALREADY_DEPLOYED" = "0" ]; then
    echo "  Deploying $(git rev-list --count "$CURRENT_SHA..$DEPLOY_SHA") new commit(s)"
fi

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
if [ "$ALREADY_DEPLOYED" = "1" ]; then
    echo "  SKIPPED - already at this commit, the working tree is not changing."
else
    tar -czf "$BACKUP_DIR/files_$(date +%s).tar.gz" \
        --exclude=vendor --exclude=node_modules --exclude=.git \
        --exclude=backups --exclude="storage/logs/*" \
        --exclude="storage/framework/cache/*" --exclude="storage/framework/sessions/*" \
        --exclude="storage/framework/views/*" . 2>/dev/null || true
    echo "  -> $BACKUP_DIR"
fi

# ---------- 5. database backup -------------------------------------------
echo "[5/11] Backing up database..."
# Production's .env indents most keys, so an anchored grep on the raw file
# finds no DB_* at all and the backup runs with empty credentials.
envval() { sed -E 's/^[[:space:]]+//' .env | grep -m1 "^$1=" | cut -d= -f2- | tr -d '"' | tr -d "'"; }
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
# The flip observed on this host was in config/system-addons.php, not only the
# table, so keep a byte-exact copy to restore from.
cp -p config/system-addons.php "$BACKUP_DIR/system-addons.php.before" 2>/dev/null || true
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
if [ "$ALREADY_DEPLOYED" = "1" ]; then
    echo "  SKIPPED - already at $(git log -1 --format='%h %s')"
else
    git checkout --quiet "$DEPLOY_SHA"
    echo "  now at $(git log -1 --format='%h %s')"
fi

# ---------- 8. dependencies ----------------------------------------------
echo "[8/11] composer install..."
# The production host has no composer on PATH. That is only safe to skip
# when the lock file did not change, so vendor/ already matches.
if command -v composer >/dev/null 2>&1; then
    composer install --no-dev --optimize-autoloader --no-interaction
elif git diff --quiet "$CURRENT_SHA" "$DEPLOY_SHA" -- composer.lock composer.json; then
    echo "  composer not installed; composer.lock unchanged - existing vendor/ is current, skipping."
else
    echo "  FATAL: composer.lock changed but composer is not installed. Rolling code back."
    git checkout --quiet "$CURRENT_SHA"
    exit 1
fi

# ---------- 9. migrations -------------------------------------------------
# The real pending list, not a hardcoded one that goes stale.
echo "[9/11] Migrations..."
PENDING=$(php artisan migrate:status 2>/dev/null | grep -ci "pending" || true)
php artisan migrate:status 2>/dev/null | grep -i "pending" || echo "  (none pending)"
if [ "${PENDING:-0}" -gt 0 ]; then
    # This prompt used to run unconditionally. Over a non-interactive SSH the
    # read got EOF, returned non-zero, and 'set -e' killed the deploy right
    # here - after the checkout but before the caches were rebuilt. The script
    # had already printed seven successful steps, and the caller saw whatever
    # its own pipeline exited with, so a deploy that silently skipped its
    # migrations AND its cache rebuild looked like a clean success.
    APPLY=""
    case "${AUTO_MIGRATE:-}" in
        1) APPLY=yes ;;
        0) APPLY=no  ;;
        *)
            if [ -t 0 ]; then
                read -r -p "  Apply $PENDING pending migration(s)? (yes/no): " CONFIRM || CONFIRM=""
                if [ "$CONFIRM" = "yes" ]; then APPLY=yes; else APPLY=no; fi
            else
                echo "  FATAL: $PENDING migration(s) pending and stdin is not a terminal,"
                echo "         so there is nobody to answer a prompt. Re-run with"
                echo "         AUTO_MIGRATE=1 to apply them, or AUTO_MIGRATE=0 to skip"
                echo "         them deliberately. Refusing to guess."
                exit 1
            fi
            ;;
    esac
    if [ "$APPLY" = "yes" ]; then
        php artisan migrate --force
        echo "  Applied."
    else
        echo "  SKIPPED - $PENDING migration(s) still pending."
        MIGRATIONS_SKIPPED=1
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

if [ -f "$BACKUP_DIR/system-addons.php.before" ] && ! cmp -s "$BACKUP_DIR/system-addons.php.before" config/system-addons.php; then
    echo "  *** config/system-addons.php CHANGED DURING DEPLOY ***"
    echo "  Restore with: cp -p $BACKUP_DIR/system-addons.php.before config/system-addons.php"
fi

echo ""
echo "=== Deployment complete ==="
echo "SHA:      $DEPLOY_SHA"
echo "Backup:   $BACKUP_DIR"
echo "Rollback: git checkout $CURRENT_SHA && php artisan optimize:clear && php artisan config:cache"

# A deploy whose migrations did not run is not a successful deploy. Say so in
# the exit status as well as on stdout, so a caller that only checks $? cannot
# read it as a clean run.
if [ "$MIGRATIONS_SKIPPED" = "1" ]; then
    echo ""
    echo "*** WARNING: migrations were SKIPPED - the deployed code is running"
    echo "    against an older schema. Apply them with:"
    echo "      AUTO_MIGRATE=1 bash script/deploy.sh $DEPLOY_SHA"
    exit 2
fi
