#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════════════
# URBANGOODZ ECOSYSTEM TEST HARNESS — Run AFTER deployment
# ═══════════════════════════════════════════════════════════════════════════════

set -e
cd /home/urbakkej/admin.urbangoodzdelivery.com

echo "═══════════════════════════════════════════════════════════"
echo "  ECOSYSTEM TEST HARNESS"
echo "  Time: $(date)"
echo "═══════════════════════════════════════════════════════════"
echo ""

# Phase 1: Ecosystem test
echo "[1/3] Running ecosystem test with seed data..."
php artisan urban-goods:ecosystem-test \
    --create-seed \
    --base-url=https://admin.urbangoodzdelivery.com \
    --verbose-output 2>&1
echo ""

# Phase 2: Route checks
echo "[2/3] Checking routes..."
echo "  Business routes:"
php artisan route:list --path=business 2>/dev/null | head -20
echo ""
echo "  Urban-goodz routes:"
php artisan route:list --path=urban-goodz 2>/dev/null | head -20
echo ""
echo "  Driver routes:"
php artisan route:list --path=driver 2>/dev/null | head -20
echo ""

# Phase 3: Curl API tests
echo "[3/3] Running curl API tests..."
echo "  Admin login page..."
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "https://admin.urbangoodzdelivery.com/login" 2>/dev/null || echo "000")
echo "    /login => HTTP $HTTP_CODE"

echo "  Business portal login..."
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "https://admin.urbangoodzdelivery.com/business/login" 2>/dev/null || echo "000")
echo "    /business/login => HTTP $HTTP_CODE"

echo "  Business forgot-password..."
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "https://admin.urbangoodzdelivery.com/business/forgot-password" 2>/dev/null || echo "000")
echo "    /business/forgot-password => HTTP $HTTP_CODE"

echo "  Customer API config..."
RESPONSE=$(curl -s --max-time 10 "https://admin.urbangoodzdelivery.com/api/v1/customer/config" 2>/dev/null)
echo "    /api/v1/customer/config => $(echo $RESPONSE | head -c 100)..."

echo "  Driver API (no token)..."
RESPONSE=$(curl -s --max-time 10 "https://admin.urbangoodzdelivery.com/api/v1/urban-goodz/driver/busy-list" 2>/dev/null)
echo "    /api/v1/urban-goodz/driver/busy-list => $(echo $RESPONSE | head -c 100)..."

echo ""
echo "═══════════════════════════════════════════════════════════"
echo "  TEST COMPLETE"
echo "  Review results above. All checks should show PASS/200."
echo "═══════════════════════════════════════════════════════════"
echo ""
echo "VERIFICATION CHECKLIST (manual):"
echo "  1. Open https://admin.urbangoodzdelivery.com/login in browser"
echo "     - Should show orange gradient left panel (not black/teal)"
echo "     - Should show Urban Goodz branding"
echo "     - reCAPTCHA should auto-detect and fall back to custom captcha"
echo "  2. Open https://admin.urbangoodzdelivery.com/business/login"
echo "     - Should show UG branded login with orange accents"
echo "     - 'Welcome back' heading, password toggle, forgot-password link"
echo "  3. Login with admin credentials at /login"
echo "  4. Login with business owner at /business/login"
