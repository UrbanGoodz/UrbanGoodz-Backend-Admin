#!/bin/bash
# =============================================================================
# UrbanGoodz AI Workforce Backend Deploy — 2026-07-19
# Deploys AI Workforce models, controllers, services, routes, views, migrations, and seeders
# Run from cPanel Terminal after pulling latest commits
# =============================================================================
set -e

REPO="/home/urbakkej/admin.urbangoodzdelivery.com/AdminPanel_Update_V39"
LIVE="/home/urbakkej/admin.urbangoodzdelivery.com"
BACKUP_DIR="/home/urbakkej/backups/ai-workforce-deploy_$(date +%Y%m%d_%H%M%S)"

echo "============================================="
echo " UrbanGoodz AI Workforce Deploy — $(date)"
echo "============================================="
echo ""

# --- Step 1: Git Pull ---
echo "[1/8] Pulling latest from GitHub..."
cd "$REPO"
git fetch origin
git checkout adminpanel-v39-backend-sprint
git pull --ff-only origin adminpanel-v39-backend-sprint
DEPLOYED_HEAD=$(git rev-parse HEAD)
echo "  Source HEAD: $DEPLOYED_HEAD"
echo ""

# --- Step 2: Create Backup ---
echo "[2/8] Creating backup at $BACKUP_DIR..."
mkdir -p "$BACKUP_DIR"
# Backup live database using credentials from .env
DB_HOST=$(grep DB_HOST "$LIVE/.env" | cut -d '=' -f2 | tr -d '\r')
DB_DATABASE=$(grep DB_DATABASE "$LIVE/.env" | cut -d '=' -f2 | tr -d '\r')
DB_USERNAME=$(grep DB_USERNAME "$LIVE/.env" | cut -d '=' -f2 | tr -d '\r')
DB_PASSWORD=$(grep DB_PASSWORD "$LIVE/.env" | cut -d '=' -f2 | tr -d '\r')

echo "  Backing up database $DB_DATABASE..."
mysqldump -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" > "$BACKUP_DIR/db_backup.sql" 2>/dev/null || echo "  WARNING: Database backup failed (verify mysqldump credentials)"

# Backup files we are about to replace/update
for f in \
    "app/Http/Controllers/Admin/AiOperationsController.php" \
    "app/Http/Controllers/Api/V1/UrbanGoodz/NotificationAIController.php" \
    "config/urban_goodz.php" \
    "database/seeders/DatabaseSeeder.php" \
    "routes/admin_ai_operations.php"
do
    if [ -f "$LIVE/$f" ]; then
        mkdir -p "$BACKUP_DIR/$(dirname $f)"
        cp "$LIVE/$f" "$BACKUP_DIR/$f"
    fi
done
echo "  Backup complete."
echo ""

# --- Step 3: Copy Changed & New Files ---
echo "[3/8] Copying changed and new files to production..."
cd "$LIVE"

# Models
cp "$REPO/app/Models/AiAgent.php" "app/Models/"
cp "$REPO/app/Models/AiApproval.php" "app/Models/"
cp "$REPO/app/Models/AiAuditEvent.php" "app/Models/"
cp "$REPO/app/Models/AiCompanionContext.php" "app/Models/"
cp "$REPO/app/Models/AiOutreachMessage.php" "app/Models/"
cp "$REPO/app/Models/AiOutreachTemplate.php" "app/Models/"
cp "$REPO/app/Models/AiTask.php" "app/Models/"
cp "$REPO/app/Models/AiWorkforceAction.php" "app/Models/"
cp "$REPO/app/Models/BusinessNeed.php" "app/Models/"
cp "$REPO/app/Models/HumanActionItem.php" "app/Models/"
cp "$REPO/app/Models/MerchantProspect.php" "app/Models/"

# Services
mkdir -p "app/Services/UrbanGoodz"
cp "$REPO/app/Services/UrbanGoodz/AiChiefOfStaffService.php" "app/Services/UrbanGoodz/"
cp "$REPO/app/Services/UrbanGoodz/AiCompanionApiService.php" "app/Services/UrbanGoodz/"
cp "$REPO/app/Services/UrbanGoodz/AiMerchantAcquisitionService.php" "app/Services/UrbanGoodz/"
cp "$REPO/app/Services/UrbanGoodz/AiWorkforceAutonomyService.php" "app/Services/UrbanGoodz/"

# Controllers
cp "$REPO/app/Http/Controllers/Admin/AiOperationsController.php" "app/Http/Controllers/Admin/"
mkdir -p "app/Http/Controllers/Api/V1/UrbanGoodz"
cp "$REPO/app/Http/Controllers/Api/V1/UrbanGoodz/NotificationAIController.php" "app/Http/Controllers/Api/V1/UrbanGoodz/"

# Config
cp "$REPO/config/urban_goodz.php" "config/"

# Migrations & Seeders
mkdir -p "database/migrations"
cp "$REPO"/database/migrations/2026_07_19_160*.php "database/migrations/"
cp "$REPO/database/seeders/DatabaseSeeder.php" "database/seeders/"
cp "$REPO/database/seeders/UrbanGoodzAiWorkforceSeeder.php" "database/seeders/"

# Routes
cp "$REPO/routes/admin_ai_operations.php" "routes/"

# Views
mkdir -p "resources/views/admin-views/urban-goodz/ai-operations/workforce"
cp -r "$REPO/resources/views/admin-views/urban-goodz/ai-operations/workforce/"* "resources/views/admin-views/urban-goodz/ai-operations/workforce/"

# Cleanup browser deploy helper if it exists in public
rm -f "public/deploy_helper.php"

echo "  All files copied."
echo ""

# --- Step 4: Syntax Check ---
echo "[4/8] Running syntax checks..."
SYNTAX_ERRORS=0
for f in \
    "app/Models/AiAgent.php" \
    "app/Models/AiApproval.php" \
    "app/Services/UrbanGoodz/AiChiefOfStaffService.php" \
    "app/Http/Controllers/Admin/AiOperationsController.php" \
    "routes/admin_ai_operations.php"
do
    result=$(php -l "$f" 2>&1)
    if echo "$result" | grep -q "No syntax errors"; then
        echo "  ✓ $f"
    else
        echo "  ✗ $f: $result"
        SYNTAX_ERRORS=$((SYNTAX_ERRORS + 1))
    fi
done
if [ $SYNTAX_ERRORS -gt 0 ]; then
    echo "  FATAL: $SYNTAX_ERRORS syntax errors found. Fix before continuing."
    exit 1
fi
echo "  All syntax checks passed."
echo ""

# --- Step 5: Clear Caches & Install Vendors ---
echo "[5/8] Clearing caches and optimizing autoloader..."
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
echo ""

# --- Step 6: Run Migrations ---
echo "[6/8] Running migrations..."
php artisan migrate --force
echo ""

# --- Step 7: Run Seeder ---
echo "[7/8] Running seeder..."
php artisan db:seed --class=UrbanGoodzAiWorkforceSeeder --force
echo ""

# --- Step 8: Warm Up Cache ---
echo "[8/8] Caching configuration and views..."
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
echo ""

echo "============================================="
echo " DEPLOY COMPLETE — $(date)"
echo " Deployed commit: $DEPLOYED_HEAD"
echo " Backup directory: $BACKUP_DIR"
echo "============================================="
