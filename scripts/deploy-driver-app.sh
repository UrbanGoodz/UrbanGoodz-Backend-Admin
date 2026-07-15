#!/bin/bash
# ============================================================
# URBANGOODZ DRIVER APP - PRODUCTION DEPLOYMENT SCRIPT
# ============================================================
# Run this on the production server after git pull
#
# WHAT THIS SCRIPT DOES:
# 1. Clears all Laravel caches
# 2. Rebuilds route + config + view caches
# 3. Creates a test driver for acceptance testing
# 4. Outputs test credentials
#
# PREREQUISITES:
# - Latest code pushed to adminpanel-v39-backend-sprint
# - SSH access to production server
# - Run from Laravel root directory
# ============================================================

set -e

LARAVEL_ROOT="/home/urbakkej/admin.urbangoodzdelivery.com"
cd "$LARAVEL_ROOT"

echo "=== UrbanGoodz Driver Deployment ==="
echo "Time: $(date)"
echo ""

# Step 1: Pull latest code
echo "[1/5] Pulling latest code..."
cd "$LARAVEL_ROOT/AdminPanel_Update_V39"
git pull origin adminpanel-v39-backend-sprint
echo "  Done."

# Step 2: Clear caches
echo ""
echo "[2/5] Clearing caches..."
cd "$LARAVEL_ROOT"
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan optimize:clear
echo "  All caches cleared."

# Step 3: Rebuild caches
echo ""
echo "[3/5] Rebuilding caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
echo "  Caches rebuilt."

# Step 4: Create test driver
echo ""
echo "[4/5] Creating test driver..."
php artisan urban-goods:create-test-driver --zone=1
echo "  Test driver created."

# Step 5: Verify
echo ""
echo "[5/5] Verification..."
echo "  Route count: $(php artisan route:list --columns=method,uri 2>/dev/null | wc -l)"
echo "  Delivery-man routes:"
php artisan route:list --columns=method,uri 2>/dev/null | grep -i "delivery-man" || echo "  (none found)"
echo ""
echo "  UrbanGoodz driver routes:"
php artisan route:list --columns=method,uri 2>/dev/null | grep -i "urban-goodz/driver" || echo "  (none found)"

echo ""
echo "=== Deployment Complete ==="
echo ""
echo "NEXT STEPS:"
echo "1. Test the driver app with the auth_token output above"
echo "2. Test each endpoint as per the acceptance checklist"
echo "3. Report results to the DCP checkpoint"
