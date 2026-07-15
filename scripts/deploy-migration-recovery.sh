#!/bin/bash
# ==============================================================================
# PRODUCTION RECOVERY SCRIPT — Service Booking Migration Fix
# ==============================================================================
# Target Host:  admin.urbangoodzdelivery.com
# Laravel Root: /home/urbakkej/admin.urbangoodzdelivery.com
# Branch:       adminpanel-v39-backend-sprint
# Date:         2026-07-14
# Purpose:      Deploy fixed migration and clear caches safely
# ==============================================================================

set -euo pipefail

LARAVEL_ROOT="/home/urbakkej/admin.urbangoodzdelivery.com"
SOURCE_REPO="${LARAVEL_ROOT}/AdminPanel_Update_V39"
BACKUP_DIR="${LARAVEL_ROOT}/backups/migration-fix-$(date +%Y%m%d_%H%M%S)"
FAILED=0

echo "============================================="
echo "  PRODUCTION MIGRATION RECOVERY"
echo "  $(date)"
echo "============================================="

# STEP 1: Verify we're in the right place
if [ ! -f "${LARAVEL_ROOT}/artisan" ]; then
    echo "ERROR: artisan not found at ${LARAVEL_ROOT}/artisan"
    exit 1
fi

cd "${LARAVEL_ROOT}"

# STEP 2: Record current state
echo ""
echo "[STEP 2] Recording current state..."
php artisan --version 2>/dev/null || echo "WARNING: artisan --version failed"
php artisan migrate:status 2>/dev/null | head -20 || echo "WARNING: migrate:status failed"

# STEP 3: Backup files that will be replaced
echo ""
echo "[STEP 3] Backing up current migration files..."
mkdir -p "${BACKUP_DIR}/database/migrations"
cp -v database/migrations/2026_07_12_130000_complete_service_booking_workflow.php "${BACKUP_DIR}/database/migrations/" 2>/dev/null || echo "  (file not present to backup)"
cp -v database/migrations/2026_07_13_000001_create_delivery_man_vehicles_table.php "${BACKUP_DIR}/database/migrations/" 2>/dev/null || echo "  (file not present to backup)"
cp -v database/migrations/2026_07_13_000002_create_driver_certifications_table.php "${BACKUP_DIR}/database/migrations/" 2>/dev/null || echo "  (file not present to backup)"
cp -v database/migrations/2026_07_13_000003_create_vendor_notifications_table.php "${BACKUP_DIR}/database/migrations/" 2>/dev/null || echo "  (file not present to backup)"
cp -v database/migrations/2026_07_13_000004_add_delivery_man_id_and_applied_at_to_earn_money_applications_table.php "${BACKUP_DIR}/database/migrations/" 2>/dev/null || echo "  (file not present to backup)"
echo "  Backup saved to: ${BACKUP_DIR}"

# STEP 4: Pull latest code from remote
echo ""
echo "[STEP 4] Pulling latest code..."
cd "${SOURCE_REPO}"
git fetch origin adminpanel-v39-backend-sprint
git checkout adminpanel-v39-backend-sprint
git pull origin adminpanel-v39-backend-sprint
DEPLOYED_SHA=$(git rev-parse --short HEAD)
echo "  Deployed commit: ${DEPLOYED_SHA}"

# STEP 5: Copy fixed migration files to Laravel root
echo ""
echo "[STEP 5] Copying fixed migration files..."
cp -v "${SOURCE_REPO}/database/migrations/2026_07_12_130000_complete_service_booking_workflow.php" "${LARAVEL_ROOT}/database/migrations/"
cp -v "${SOURCE_REPO}/database/migrations/2026_07_13_000001_create_delivery_man_vehicles_table.php" "${LARAVEL_ROOT}/database/migrations/"
cp -v "${SOURCE_REPO}/database/migrations/2026_07_13_000002_create_driver_certifications_table.php" "${LARAVEL_ROOT}/database/migrations/"
cp -v "${SOURCE_REPO}/database/migrations/2026_07_13_000003_create_vendor_notifications_table.php" "${LARAVEL_ROOT}/database/migrations/"
cp -v "${SOURCE_REPO}/database/migrations/2026_07_13_000004_add_delivery_man_id_and_applied_at_to_earn_money_applications_table.php" "${LARAVEL_ROOT}/database/migrations/"

# STEP 6: Syntax check
echo ""
echo "[STEP 6] Running syntax check..."
SYNTAX_ERROR=0
for f in \
    "${LARAVEL_ROOT}/database/migrations/2026_07_12_130000_complete_service_booking_workflow.php" \
    "${LARAVEL_ROOT}/database/migrations/2026_07_13_000001_create_delivery_man_vehicles_table.php" \
    "${LARAVEL_ROOT}/database/migrations/2026_07_13_000002_create_driver_certifications_table.php" \
    "${LARAVEL_ROOT}/database/migrations/2026_07_13_000003_create_vendor_notifications_table.php" \
    "${LARAVEL_ROOT}/database/migrations/2026_07_13_000004_add_delivery_man_id_and_applied_at_to_earn_money_applications_table.php"; do
    if php -l "$f" 2>&1 | grep -q "error"; then
        echo "  SYNTAX ERROR: $f"
        SYNTAX_ERROR=1
    fi
done
if [ "$SYNTAX_ERROR" -ne 0 ]; then
    echo "ABORT: Syntax errors found. Fix before proceeding."
    exit 1
fi
echo "  All syntax checks passed."

# STEP 7: Enable maintenance mode
echo ""
echo "[STEP 7] Enabling maintenance mode..."
php artisan down --retry=60 || echo "WARNING: Could not enable maintenance mode (continuing anyway)"

# STEP 8: Clear and rebuild caches
echo ""
echo "[STEP 8] Clearing and rebuilding caches..."
php artisan optimize:clear
php artisan route:clear
php artisan config:clear
php artisan cache:clear
php artisan view:clear

# STEP 9: Run migration
echo ""
echo "[STEP 9] Running migration (this may take a moment)..."
if php artisan migrate --force 2>&1; then
    echo "  Migration completed successfully."
else
    echo "  WARNING: Migration may have encountered issues. Check output above."
    FAILED=1
fi

# STEP 10: Rebuild caches
echo ""
echo "[STEP 10] Rebuilding caches..."
php artisan route:cache
php artisan config:cache
php artisan view:cache
php artisan optimize

# STEP 11: Verify routes
echo ""
echo "[STEP 11] Verifying routes..."
echo "  Purchase-card routes:"
PURCHASE_CARD_COUNT=$(php artisan route:list --path=purchase-card --no-ansi 2>/dev/null | grep -c "purchase-card" || echo "0")
echo "    Count: ${PURCHASE_CARD_COUNT}"
if [ "$PURCHASE_CARD_COUNT" -ne 3 ]; then
    echo "    WARNING: Expected 3 purchase-card routes, got ${PURCHASE_CARD_COUNT}"
    FAILED=1
fi

echo ""
echo "  Migration status:"
php artisan migrate:status 2>/dev/null | head -20

# STEP 12: Bring app back online
echo ""
echo "[STEP 12] Bringing application back online..."
php artisan up

# STEP 13: Verification summary
echo ""
echo "============================================="
echo "  DEPLOYMENT COMPLETE"
echo "============================================="
echo "  Deployed SHA:    ${DEPLOYED_SHA}"
echo "  Backup Location: ${BACKUP_DIR}"
echo "  Status:          $([ "$FAILED" -eq 0 ] && echo 'SUCCESS' || echo 'PARTIAL — CHECK WARNINGS')"
echo ""
echo "  Verify live:"
echo "    curl -s https://admin.urbangoodzdelivery.com/api/v1/urban-goodz/driver/order-anywhere/1/purchase-card | head -c 200"
echo "    (Should return JSON 401, not HTML)"
echo ""
echo "  Rollback command:"
echo "    cp ${BACKUP_DIR}/database/migrations/*.php ${LARAVEL_ROOT}/database/migrations/"
echo "    cd ${LARAVEL_ROOT} && php artisan optimize:clear && php artisan route:cache && php artisan config:cache"
echo "============================================="

exit $FAILED
