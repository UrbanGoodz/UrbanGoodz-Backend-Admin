#!/bin/bash
# =====================================================
# Urban Goodz — Sprint Integration Deployment Script
# Branch: adminpanel-v39-backend-sprint
# Locked SHA: 087cf2c020b793610f9fff0cf4136b333c541ce4
# Date: 2026-07-13
# =====================================================
set -euo pipefail

DEPLOY_SHA="087cf2c020b793610f9fff0cf4136b333c541ce4"
BACKUP_DIR="backups/$(date +%Y%m%d_%H%M%S)"
PUBLIC_DIR="public_html"
APP_DIR="."

echo "=== Urban Goodz Sprint Integration Deployment ==="
echo "SHA: $DEPLOY_SHA"
echo "Started: $(date)"
echo ""

# STEP 1: Pre-flight checks
echo "[1/10] Pre-flight checks..."
if [ ! -f ".env" ]; then
    echo "FATAL: .env not found. Aborting."
    exit 1
fi
if [ ! -f "composer.json" ]; then
    echo "FATAL: composer.json not found. Wrong directory."
    exit 1
fi
php -v > /dev/null 2>&1 || { echo "FATAL: PHP not available"; exit 1; }
echo "  Pre-flight OK"

# STEP 2: Backup current files
echo "[2/10] Backing up current files..."
mkdir -p "$BACKUP_DIR"
tar -czf "$BACKUP_DIR/files_$(date +%s).tar.gz" \
    --exclude='vendor' \
    --exclude='node_modules' \
    --exclude='.git' \
    --exclude='storage/logs/*' \
    --exclude='storage/framework/cache/*' \
    --exclude='storage/framework/sessions/*' \
    --exclude='storage/framework/views/*' \
    . 2>/dev/null || true
echo "  Files backed up to $BACKUP_DIR"

# STEP 3: Backup database
echo "[3/10] Backing up database..."
DB_NAME=$(grep DB_DATABASE .env | cut -d'=' -f2 | tr -d '"' | tr -d "'")
DB_USER=$(grep DB_USERNAME .env | cut -d'=' -f2 | tr -d '"' | tr -d "'")
DB_PASS=$(grep DB_PASSWORD .env | cut -d'=' -f2 | tr -d '"' | tr -d "'")
DB_HOST=$(grep DB_HOST .env | cut -d'=' -f2 | tr -d '"' | tr -d "'")
DB_PORT=$(grep DB_PORT .env | cut -d'=' -f2 | tr -d '"' | tr -d "'")

if command -v mysqldump &> /dev/null; then
    mysqldump -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" \
        "$DB_NAME" > "$BACKUP_DIR/database_$(date +%s).sql" 2>/dev/null
    echo "  Database backed up to $BACKUP_DIR"
else
    echo "  WARNING: mysqldump not found. Manual DB backup required."
fi

# STEP 4: Pull the locked SHA
echo "[4/10] Pulling locked SHA $DEPLOY_SHA..."
git fetch origin
git checkout "$DEPLOY_SHA"
if [ $? -ne 0 ]; then
    echo "  ERROR: Failed to checkout locked SHA $DEPLOY_SHA. Aborting deploy."
    exit 1
fi
echo "  SHA checked out"

# STEP 5: Install PHP dependencies
echo "[5/10] Running composer install..."
composer install --no-dev --optimize-autoloader --no-interaction
echo "  Dependencies installed"

# STEP 6: Check migration status
echo "[6/10] Checking migration status..."
php artisan migrate:status
echo ""
echo "  Review the above. Pending migrations:"
echo "    - 2026_07_12_000003_encrypt_mail_config_password"
echo "    - 2026_07_12_100000_create_fashion_fit_ai_workflow_tables"
echo "    - 2026_07_12_120000_complete_creator_reel_commerce"
echo "    - 2026_07_12_130000_complete_service_booking_workflow"
echo "    - 2026_07_12_150000_add_soft_deletes_to_urban_goodz_medical_courier_jobs"
echo "    - 2026_07_13_000001_create_delivery_man_vehicles_table"
echo "    - 2026_07_13_000002_create_driver_certifications_table"
echo "    - 2026_07_13_000003_create_vendor_notifications_table"
echo "    - 2026_07_13_000004_add_delivery_man_id_and_applied_at_to_earn_money_applications_table"
echo ""
read -p "  Apply pending migrations? (yes/no): " CONFIRM_MIGRATE
if [ "$CONFIRM_MIGRATE" = "yes" ]; then
    php artisan migrate --force
    echo "  Migrations applied"
else
    echo "  Migrations SKIPPED — run manually later"
fi

# STEP 7: Clear and rebuild caches
echo "[7/10] Clearing and rebuilding caches..."
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
echo "  Caches rebuilt"

# STEP 8: Restart queue workers
echo "[8/10] Restarting queue workers..."
php artisan queue:restart 2>/dev/null || echo "  WARNING: queue:restart requires Supervisor"
echo "  Queue restarted"

# STEP 9: Set permissions
echo "[9/10] Setting permissions..."
chmod -R 775 storage 2>/dev/null || true
chmod -R 775 bootstrap/cache 2>/dev/null || true
echo "  Permissions set"

# STEP 10: Verify
echo "[10/10] Post-deploy verification..."
echo ""
php artisan route:list --columns=method,uri 2>/dev/null | head -5
echo "  ..."
ROUTES=$(php artisan route:list --columns=method,uri 2>/dev/null | wc -l)
echo "  Total route lines: $ROUTES"
echo ""
php artisan migrate:status 2>/dev/null | head -5
echo ""
echo "=== Deployment Complete ==="
echo "Finished: $(date)"
echo "SHA: $DEPLOY_SHA"
echo "Backup: $BACKUP_DIR"
echo ""
echo "NEXT: Run script/post-deploy-verify.sh"
