#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════════════
# URBANGOODZ ECOSYSTEM DEPLOYMENT — 2026-07-15
# Deploy commits e0e98af + c19616e + 8904709 to production
# ═══════════════════════════════════════════════════════════════════════════════
# PASTE THIS ENTIRE SCRIPT into cPanel Terminal
# ═══════════════════════════════════════════════════════════════════════════════

set -e

LARAVEL_ROOT="/home/urbakkej/admin.urbangoodzdelivery.com"
SRC="$LARAVEL_ROOT/AdminPanel_Update_V39"
BACKUP_DIR="/home/urbakkej/backups/ecosystem-deploy_$(date +%Y%m%d_%H%M%S)"

echo "═══════════════════════════════════════════════════════════"
echo "  URBANGOODZ ECOSYSTEM DEPLOYMENT"
echo "  Time: $(date)"
echo "═══════════════════════════════════════════════════════════"
echo ""

# ─── STEP 1: Update source repo ───
echo "[1/9] Updating source repo..."
cd "$SRC"
git fetch origin
git checkout adminpanel-v39-backend-sprint
git pull --ff-only origin adminpanel-v39-backend-sprint
DEPLOYED_HEAD=$(git rev-parse --short HEAD)
echo "  Source HEAD: $DEPLOYED_HEAD"
if [ "$DEPLOYED_HEAD" != "8904709" ]; then
    echo "  WARNING: Expected 8904709, got $DEPLOYED_HEAD"
fi
echo ""

# ─── STEP 2: Create backup ───
echo "[2/9] Creating backup at $BACKUP_DIR..."
mkdir -p "$BACKUP_DIR"
# Backup files we're about to replace
for f in \
    "app/Console/Kernel.php" \
    "app/Console/Commands/CreateTestDriver.php" \
    "app/Console/Commands/CreateBusinessOwner.php" \
    "app/Console/Commands/UrbanGoodzEcosystemTest.php" \
    "app/Http/Controllers/Admin/UrbanGoodz/BusinessForgotPasswordController.php" \
    "app/Http/Controllers/Admin/UrbanGoodz/BusinessResetPasswordController.php" \
    "config/auth.php" \
    "public/assets/admin/css/ug-admin.css" \
    "public/assets/admin/svg/logos/logo.svg" \
    "public/assets/admin/svg/logos/logo-white.svg" \
    "public/assets/admin/svg/logos/logo-short.svg" \
    "public/assets/admin/svg/logos/logo-short-white.svg" \
    "resources/views/admin-views/partials/_recaptcha.blade.php" \
    "resources/views/auth/login.blade.php" \
    "resources/views/business/auth/login.blade.php" \
    "routes/business.php"
do
    if [ -f "$LARAVEL_ROOT/$f" ]; then
        mkdir -p "$BACKUP_DIR/$(dirname $f)"
        cp "$LARAVEL_ROOT/$f" "$BACKUP_DIR/$f"
    fi
done
echo "  Backup complete."
echo ""

# ─── STEP 3: Copy changed files ───
echo "[3/9] Copying changed files to production..."
cd "$LARAVEL_ROOT"

# App files
cp "$SRC/app/Console/Kernel.php" "app/Console/Kernel.php"
cp "$SRC/app/Console/Commands/CreateTestDriver.php" "app/Console/Commands/CreateTestDriver.php"
cp "$SRC/app/Console/Commands/CreateBusinessOwner.php" "app/Console/Commands/CreateBusinessOwner.php"
cp "$SRC/app/Console/Commands/UrbanGoodzEcosystemTest.php" "app/Console/Commands/UrbanGoodzEcosystemTest.php"
cp "$SRC/app/Http/Controllers/Admin/UrbanGoodz/BusinessForgotPasswordController.php" "app/Http/Controllers/Admin/UrbanGoodz/BusinessForgotPasswordController.php"
cp "$SRC/app/Http/Controllers/Admin/UrbanGoodz/BusinessResetPasswordController.php" "app/Http/Controllers/Admin/UrbanGoodz/BusinessResetPasswordController.php"

# Config
cp "$SRC/config/auth.php" "config/auth.php"

# Public assets
cp "$SRC/public/assets/admin/css/ug-admin.css" "public/assets/admin/css/ug-admin.css"
cp "$SRC/public/assets/admin/svg/logos/logo.svg" "public/assets/admin/svg/logos/logo.svg"
cp "$SRC/public/assets/admin/svg/logos/logo-white.svg" "public/assets/admin/svg/logos/logo-white.svg"
cp "$SRC/public/assets/admin/svg/logos/logo-short.svg" "public/assets/admin/svg/logos/logo-short.svg"
cp "$SRC/public/assets/admin/svg/logos/logo-short-white.svg" "public/assets/admin/svg/logos/logo-short-white.svg"

# Resources/Views
cp "$SRC/resources/views/admin-views/partials/_recaptcha.blade.php" "resources/views/admin-views/partials/_recaptcha.blade.php"
cp "$SRC/resources/views/auth/login.blade.php" "resources/views/auth/login.blade.php"

# Business Portal views
mkdir -p "resources/views/business/auth"
cp "$SRC/resources/views/business/auth/login.blade.php" "resources/views/business/auth/login.blade.php"
cp "$SRC/resources/views/business/auth/forgot-password.blade.php" "resources/views/business/auth/forgot-password.blade.php"
cp "$SRC/resources/views/business/auth/reset-password.blade.php" "resources/views/business/auth/reset-password.blade.php"

# Business Portal layout
mkdir -p "resources/views/business/layouts"
cp "$SRC/resources/views/business/layouts/app.blade.php" "resources/views/business/layouts/app.blade.php" 2>/dev/null || true

# Routes
cp "$SRC/routes/business.php" "routes/business.php"

echo "  All files copied."
echo ""

# ─── STEP 4: Syntax check ───
echo "[4/9] Running syntax checks..."
SYNTAX_ERRORS=0
for f in \
    "app/Console/Kernel.php" \
    "app/Console/Commands/CreateTestDriver.php" \
    "app/Console/Commands/CreateBusinessOwner.php" \
    "app/Console/Commands/UrbanGoodzEcosystemTest.php" \
    "app/Http/Controllers/Admin/UrbanGoodz/BusinessForgotPasswordController.php" \
    "app/Http/Controllers/Admin/UrbanGoodz/BusinessResetPasswordController.php" \
    "config/auth.php" \
    "routes/business.php"
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

# ─── STEP 5: Clear caches ───
echo "[5/9] Clearing caches..."
php artisan cache:clear 2>&1 || true
php artisan config:clear 2>&1 || true
php artisan route:clear 2>&1 || true
php artisan view:clear 2>&1 || true
php artisan optimize:clear 2>&1 || true
echo "  Caches cleared."
echo ""

# ─── STEP 6: Migrate ───
echo "[6/9] Running migrations..."
php artisan migrate --force 2>&1
echo "  Migrations complete."
echo ""

# ─── STEP 7: Rebuild caches ───
echo "[7/9] Rebuilding caches..."
php artisan config:cache 2>&1
php artisan route:cache 2>&1
php artisan view:cache 2>&1
echo "  Caches rebuilt."
echo ""

# ─── STEP 8: Verify artisan command ───
echo "[8/9] Verifying ecosystem test command..."
php artisan list 2>/dev/null | grep -i "ecosystem-test" && echo "  ✓ Command registered" || echo "  ✗ Command NOT found"
echo ""

# ─── STEP 9: Bring site up ───
echo "[9/9] Bringing site up..."
php artisan up 2>&1 || true
echo ""

# ═══ SUMMARY ═══
echo "═══════════════════════════════════════════════════════════"
echo "  DEPLOYMENT COMPLETE"
echo "  Deployed commit: $DEPLOYED_HEAD"
echo "  Backup: $BACKUP_DIR"
echo "═══════════════════════════════════════════════════════════"
echo ""
echo "NEXT: Run the ecosystem test harness:"
echo "  php artisan urban-goods:ecosystem-test --create-seed --base-url=https://admin.urbangoodzdelivery.com"
echo ""
