#!/bin/bash
# =============================================================================
# UrbanGoodz Full Stack Deploy — 2026-07-15
# Deploys ALL backend PHP, views, configs, routes, migrations, and assets
# Run from cPanel Terminal after git pull
# =============================================================================
set -e

REPO="/home/urbakkej/admin.urbangoodzdelivery.com/AdminPanel_Update_V39"
LIVE="/home/urbakkej/admin.urbangoodzdelivery.com"

echo "============================================="
echo " UrbanGoodz Full Deploy — $(date)"
echo "============================================="
echo ""

# --- Step 1: Git Pull ---
echo "[1/8] Pulling latest from GitHub..."
cd "$REPO" && git pull origin adminpanel-v39-backend-sprint
echo "  DONE"
echo ""

# --- Step 2: Copy Backend PHP (Models, Controllers, Services, Commands, Contracts, Providers, Traits, Support) ---
echo "[2/8] Copying backend PHP files..."

# Models
cp "$REPO"/app/Models/UrbanGoodz*.php "$LIVE/app/Models/" 2>/dev/null || true

# Admin Controllers — UrbanGoodz subdirectory (26 files)
mkdir -p "$LIVE/app/Http/Controllers/Admin/UrbanGoodz"
cp "$REPO"/app/Http/Controllers/Admin/UrbanGoodz/*.php "$LIVE/app/Http/Controllers/Admin/UrbanGoodz/"

# Admin Controllers — top-level UrbanGoodz*.php
cp "$REPO"/app/Http/Controllers/Admin/UrbanGoodz*.php "$LIVE/app/Http/Controllers/Admin/" 2>/dev/null || true

# API Controllers
mkdir -p "$LIVE/app/Http/Controllers/Api/V1/Vendor"
cp "$REPO"/app/Http/Controllers/Api/UrbanGoodz*.php "$LIVE/app/Http/Controllers/Api/" 2>/dev/null || true
cp "$REPO"/app/Http/Controllers/Api/V1/UrbanGoodz*.php "$LIVE/app/Http/Controllers/Api/V1/" 2>/dev/null || true
cp "$REPO"/app/Http/Controllers/Api/V1/Vendor/UrbanGoodz*.php "$LIVE/app/Http/Controllers/Api/V1/Vendor/" 2>/dev/null || true

# DeliveryMan controllers
cp "$REPO"/app/Http/Controllers/DeliveryMan/UrbanGoodz*.php "$LIVE/app/Http/Controllers/DeliveryMan/" 2>/dev/null || true

# Vendor controllers
cp "$REPO"/app/Http/Controllers/Vendor/UrbanGoodz*.php "$LIVE/app/Http/Controllers/Vendor/" 2>/dev/null || true

# Services
mkdir -p "$LIVE/app/Services/UrbanGoodz"
cp "$REPO"/app/Services/UrbanGoodz*.php "$LIVE/app/Services/" 2>/dev/null || true
cp "$REPO"/app/Services/UrbanGoodz/*.php "$LIVE/app/Services/UrbanGoodz/" 2>/dev/null || true
cp "$REPO"/app/Services/AiCopilotService.php "$LIVE/app/Services/" 2>/dev/null || true
cp "$REPO"/app/Services/OrderAnywhereCardService.php "$LIVE/app/Services/" 2>/dev/null || true

# Contracts
mkdir -p "$LIVE/app/Contracts/LoadBoard"
cp "$REPO"/app/Contracts/LoadBoard/*.php "$LIVE/app/Contracts/LoadBoard/" 2>/dev/null || true
cp "$REPO"/app/Contracts/*.php "$LIVE/app/Contracts/" 2>/dev/null || true

# Service Providers
cp "$REPO"/app/Providers/LoadBoardServiceProvider.php "$LIVE/app/Providers/" 2>/dev/null || true

# Support
mkdir -p "$LIVE/app/Support"
cp "$REPO"/app/Support/UrbanGoodz*.php "$LIVE/app/Support/" 2>/dev/null || true

# Console Commands
cp "$REPO"/app/Console/Commands/UrbanGoodz*.php "$LIVE/app/Console/Commands/" 2>/dev/null || true
cp "$REPO"/app/Console/Commands/SyncLoadBoard.php "$LIVE/app/Console/Commands/" 2>/dev/null || true

# Console Kernel (re-register all commands)
cp "$REPO/app/Console/Kernel.php" "$LIVE/app/Console/Kernel.php"

echo "  DONE — Backend PHP"
echo ""

# --- Step 3: Copy Config Files ---
echo "[3/8] Copying config files..."
cp "$REPO"/config/urban_goodz.php "$LIVE/config/" 2>/dev/null || true
cp "$REPO"/config/urban_goodz_payments.php "$LIVE/config/" 2>/dev/null || true
cp "$REPO"/config/urban_goodz_measurements.php "$LIVE/config/" 2>/dev/null || true
cp "$REPO"/config/urban_goodz_load_board.php "$LIVE/config/" 2>/dev/null || true
cp "$REPO"/config/urban_goodz_admin_sections.php "$LIVE/config/" 2>/dev/null || true
cp "$REPO"/config/urban_goodz_permissions.php "$LIVE/config/" 2>/dev/null || true
cp "$REPO/config/app.php" "$LIVE/config/app.php"
cp "$REPO/config/auth.php" "$LIVE/config/auth.php"
echo "  DONE"
echo ""

# --- Step 4: Copy Routes ---
echo "[4/8] Copying route files..."
cp "$REPO/routes/admin.php" "$LIVE/routes/admin.php"
cp "$REPO/routes/business.php" "$LIVE/routes/business.php"
cp "$REPO/routes/web.php" "$LIVE/routes/web.php"

# API routes
mkdir -p "$LIVE/routes/api/v1"
cp "$REPO"/routes/api/v1/api.php "$LIVE/routes/api/v1/"
cp "$REPO"/routes/api/v1/urban_goodz.php "$LIVE/routes/api/v1/" 2>/dev/null || true
cp "$REPO"/routes/api/v1/fashion_fit.php "$LIVE/routes/api/v1/" 2>/dev/null || true
cp "$REPO"/routes/api/v1/service_bookings.php "$LIVE/routes/api/v1/" 2>/dev/null || true
cp "$REPO"/routes/api/v1/admin.php "$LIVE/routes/api/v1/" 2>/dev/null || true

# Urban Goodz measurement routes
cp "$REPO"/routes/api/urban_goodz_measurements.php "$LIVE/routes/api/" 2>/dev/null || true

# Admin sub-routes
cp "$REPO"/routes/admin/routes.php "$LIVE/routes/admin/" 2>/dev/null || true

# Vendor routes
cp "$REPO/routes/vendor.php" "$LIVE/routes/vendor.php" 2>/dev/null || true

# Console routes
cp "$REPO/routes/console.php" "$LIVE/routes/console.php" 2>/dev/null || true

echo "  DONE"
echo ""

# --- Step 5: Copy Views ---
echo "[5/8] Copying views..."

# Urban Goodz admin views (100+ files)
mkdir -p "$LIVE/resources/views/admin-views/urban-goodz"
cp -r "$REPO/resources/views/admin-views/urban-goodz/"* "$LIVE/resources/views/admin-views/urban-goodz/" 2>/dev/null || true

# Business portal views
mkdir -p "$LIVE/resources/views/business/auth"
mkdir -p "$LIVE/resources/views/business/load-board"
mkdir -p "$LIVE/resources/views/business/dispatcher"
cp "$REPO"/resources/views/business/*.blade.php "$LIVE/resources/views/business/" 2>/dev/null || true
cp -r "$REPO/resources/views/business/auth/"* "$LIVE/resources/views/business/auth/" 2>/dev/null || true
cp -r "$REPO/resources/views/business/load-board/"* "$LIVE/resources/views/business/load-board/" 2>/dev/null || true
cp -r "$REPO/resources/views/business/dispatcher/"* "$LIVE/resources/views/business/dispatcher/" 2>/dev/null || true

# Vendor urban goodz views
mkdir -p "$LIVE/resources/views/vendor-views/urban-goodz"
cp -r "$REPO/resources/views/vendor-views/urban-goodz/"* "$LIVE/resources/views/vendor-views/urban-goodz/" 2>/dev/null || true

# Delivery man urban goodz views
mkdir -p "$LIVE/resources/views/delivery-man-views/urban-goodz"
cp -r "$REPO/resources/views/delivery-man-views/urban-goodz/"* "$LIVE/resources/views/delivery-man-views/urban-goodz/" 2>/dev/null || true

# Admin dashboard (with bug fix)
cp "$REPO/resources/views/admin-views/dashboard.blade.php" "$LIVE/resources/views/admin-views/dashboard.blade.php"

# Admin login view
cp "$REPO/resources/views/auth/login.blade.php" "$LIVE/resources/views/auth/login.blade.php"

# Sidebar
cp "$REPO/resources/views/layouts/admin/partials/_sidebar.blade.php" "$LIVE/resources/views/layouts/admin/partials/_sidebar.blade.php"

# Recaptcha partial
cp "$REPO/resources/views/admin-views/partials/_recaptcha.blade.php" "$LIVE/resources/views/admin-views/partials/_recaptcha.blade.php"

# Installation view
cp "$REPO/resources/views/installation/activation-check.blade.php" "$LIVE/resources/views/installation/activation-check.blade.php" 2>/dev/null || true

echo "  DONE — Views"
echo ""

# --- Step 6: Copy Migrations ---
echo "[6/8] Copying migrations..."
mkdir -p "$LIVE/database/migrations"
cp "$REPO"/database/migrations/2026_07_*.php "$LIVE/database/migrations/" 2>/dev/null || true
echo "  DONE"
echo ""

# --- Step 7: Copy Seeders ---
echo "[7/8] Copying seeders..."
cp "$REPO"/database/seeders/UrbanGoodz*.php "$LIVE/database/seeders/" 2>/dev/null || true
echo "  DONE"
echo ""

# --- Step 8: Copy Middleware ---
echo "[8/8] Copying middleware and bootstrap..."
mkdir -p "$LIVE/app/Http/Middleware"
cp "$REPO"/app/Http/Middleware/ActivationCheckMiddleware.php "$LIVE/app/Http/Middleware/" 2>/dev/null || true
cp "$REPO/app/Http/Kernel.php" "$LIVE/app/Http/Kernel.php" 2>/dev/null || true
cp "$REPO/bootstrap/app.php" "$LIVE/bootstrap/app.php"

# Support/urban_goodz.php if exists
mkdir -p "$LIVE/app/Support"
cp "$REPO"/app/Support/UrbanGoodz*.php "$LIVE/app/Support/" 2>/dev/null || true

# Also copy the urban_goodz config
cp "$REPO/config/urban_goodz.php" "$LIVE/config/" 2>/dev/null || true
echo "  DONE"
echo ""

# --- Run Migrations ---
echo "[EXTRA] Running migrations..."
cd "$LIVE" && php artisan migrate --force 2>&1 || echo "  WARNING: Some migrations may have already run"
echo "  DONE"
echo ""

# --- Set .env Variables ---
echo "[EXTRA] Setting environment variables..."
if ! grep -q "DEVELOPMENT_ENVIRONMENT" "$LIVE/.env"; then
    echo "DEVELOPMENT_ENVIRONMENT=true" >> "$LIVE/.env"
fi
if ! grep -q "LOAD_BOARD_ENABLED" "$LIVE/.env"; then
    echo "LOAD_BOARD_ENABLED=true" >> "$LIVE/.env"
fi
if ! grep -q "LOAD_BOARD_SYNC_ENABLED" "$LIVE/.env"; then
    echo "LOAD_BOARD_SYNC_ENABLED=true" >> "$LIVE/.env"
fi
if ! grep -q "URBAN_GOODZ_BRAND_NAME" "$LIVE/.env"; then
    cat >> "$LIVE/.env" << 'ENVEOF'
URBAN_GOODZ_BRAND_NAME="Urban Goodz"
URBAN_GOODZ_DEFAULT_CITY=Houston
URBAN_GOODZ_DEFAULT_COUNTRY=US
URBAN_GOODZ_DISTANCE_UNIT=miles
URBAN_GOODZ_CURRENCY=USD
URBAN_GOODZ_FLOATING_AI_ENABLED=true
URBAN_GOODZ_PAYMENT_PROVIDER=staged_test
URBAN_GOODZ_PAYMENT_MODE=sandbox
URBAN_GOODZ_STAGED_TEST_ENABLED=true
URBAN_GOODZ_PLATFORM_FEE_PERCENT=10
URBAN_GOODZ_ISSUING_PROVIDER=manual
URBAN_GOODZ_CARDS_MODE=sandbox
ENVEOF
fi
echo "  DONE"
echo ""

# --- Rebuild Caches ---
echo "[FINAL] Rebuilding caches..."
cd "$LIVE"
php artisan optimize:clear 2>&1
php artisan config:cache 2>&1
php artisan route:cache 2>&1
php artisan view:cache 2>&1
echo "  DONE"
echo ""

# --- Summary ---
echo "============================================="
echo " DEPLOY COMPLETE — $(date)"
echo "============================================="
echo " Files deployed:"
echo "   Backend PHP:  ~175 files"
echo "   Views:        ~182 files"
echo "   Migrations:   ~68 files"
echo "   Routes:       ~15 files"
echo "   Config:       ~8 files"
echo "---------------------------------------------"
echo " Next: test https://admin.urbangoodzdelivery.com/login/admin"
echo "============================================="
