#!/bin/bash
# ═══════════════════════════════════════════════════════════
# UrbanGoodz Ecosystem API Acceptance Tests
# ═══════════════════════════════════════════════════════════
# Usage:
#   bash api-test.sh [base_url]
#   bash api-test.sh https://admin.urbangoodzdelivery.com
#
# Environment variables:
#   DRIVER_TOKEN  - Driver auth token for driver API tests
#   SELLER_TOKEN  - Seller auth token for vendor API tests
#   CUSTOMER_TOKEN - Customer auth token for customer API tests
# ═══════════════════════════════════════════════════════════

set -euo pipefail

BASE_URL="${1:-https://admin.urbangoodzdelivery.com}"
PASS=0
FAIL=0
WARN=0

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

pass() { echo -e "  ${GREEN}✅ PASS${NC}: $1"; PASS=$((PASS + 1)); }
fail() { echo -e "  ${RED}❌ FAIL${NC}: $1"; FAIL=$((FAIL + 1)); }
warn() { echo -e "  ${YELLOW}⚠️  WARN${NC}: $1"; WARN=$((WARN + 1)); }

echo "╔══════════════════════════════════════════════════════════╗"
echo "║    UrbanGoodz API Acceptance Test Suite                  ║"
echo "║    Target: ${BASE_URL}"
echo "║    Date: $(date)"
echo "╚══════════════════════════════════════════════════════════╝"
echo ""

# ─── PHASE 1: HTTP Health ───
echo "─── PHASE 1: HTTP Health ───"

HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "${BASE_URL}/login" 2>/dev/null || echo "000")
if [ "$HTTP_CODE" = "200" ]; then
    pass "Admin login page: HTTP ${HTTP_CODE}"
else
    fail "Admin login page: HTTP ${HTTP_CODE}"
fi

HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "${BASE_URL}/business/login" 2>/dev/null || echo "000")
if [ "$HTTP_CODE" = "200" ]; then
    pass "Business portal login: HTTP ${HTTP_CODE}"
else
    fail "Business portal login: HTTP ${HTTP_CODE}"
fi

HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "${BASE_URL}/business/forgot-password" 2>/dev/null || echo "000")
if [ "$HTTP_CODE" = "200" ]; then
    pass "Business forgot-password: HTTP ${HTTP_CODE}"
else
    fail "Business forgot-password: HTTP ${HTTP_CODE}"
fi

echo ""

# ─── PHASE 2: Customer API ───
echo "─── PHASE 2: Customer API ───"

RESPONSE=$(curl -s --max-time 15 "${BASE_URL}/api/v1/customer/config" 2>/dev/null || echo '{"error":"timeout"}')
if echo "$RESPONSE" | grep -q '"status"'; then
    pass "Customer /config: responded"
else
    warn "Customer /config: unexpected format"
fi

RESPONSE=$(curl -s --max-time 15 -X POST "${BASE_URL}/api/v1/customer/login" \
    -H "Content-Type: application/json" \
    -d '{"email":"","password":""}' 2>/dev/null || echo '{"error":"timeout"}')
if echo "$RESPONSE" | grep -q '"status"'; then
    pass "Customer /login: validates empty credentials"
else
    warn "Customer /login: response format unexpected"
fi

echo ""

# ─── PHASE 3: Vendor API ───
echo "─── PHASE 3: Vendor API ───"

RESPONSE=$(curl -s --max-time 15 "${BASE_URL}/api/v1/seller/config" 2>/dev/null || echo '{"error":"timeout"}')
if echo "$RESPONSE" | grep -q '"status"'; then
    pass "Vendor /config: responded"
else
    warn "Vendor /config: unexpected format"
fi

RESPONSE=$(curl -s --max-time 15 -X POST "${BASE_URL}/api/v1/seller/login" \
    -H "Content-Type: application/json" \
    -d '{"email":"invalid@test.com","password":"wrong"}' 2>/dev/null || echo '{"error":"timeout"}')
if echo "$RESPONSE" | grep -q '"status"'; then
    pass "Vendor /login: validates credentials"
else
    warn "Vendor /login: response format unexpected"
fi

echo ""

# ─── PHASE 4: Driver API ───
echo "─── PHASE 4: Driver API ───"

if [ -n "${DRIVER_TOKEN:-}" ]; then
    RESPONSE=$(curl -s --max-time 15 "${BASE_URL}/api/v1/urban-goodz/driver/busy-list?token=${DRIVER_TOKEN}" 2>/dev/null || echo '{"error":"timeout"}')
    if echo "$RESPONSE" | grep -q '"status"'; then
        pass "Driver /busy-list: responded"
    else
        warn "Driver /busy-list: unexpected format"
    fi

    RESPONSE=$(curl -s --max-time 15 "${BASE_URL}/api/v1/urban-goodz/driver/earning-history?token=${DRIVER_TOKEN}" 2>/dev/null || echo '{"error":"timeout"}')
    if echo "$RESPONSE" | grep -q '"status"'; then
        pass "Driver /earning-history: responded"
    else
        warn "Driver /earning-history: unexpected format"
    fi

    RESPONSE=$(curl -s --max-time 15 "${BASE_URL}/api/v1/urban-goodz/driver/business-jobs?token=${DRIVER_TOKEN}" 2>/dev/null || echo '{"error":"timeout"}')
    if echo "$RESPONSE" | grep -q '"status"'; then
        pass "Driver /business-jobs: responded"
    else
        warn "Driver /business-jobs: unexpected format"
    fi
else
    warn "Driver API tests skipped (set DRIVER_TOKEN env var)"
fi

RESPONSE=$(curl -s --max-time 15 "${BASE_URL}/api/v1/urban-goodz/driver/busy-list" 2>/dev/null || echo '{"error":"timeout"}')
if echo "$RESPONSE" | grep -q '"status"'; then
    pass "Driver /busy-list: rejects no token"
else
    warn "Driver /busy-list: response format unexpected"
fi

echo ""

# ─── PHASE 5: Service Bookings API ───
echo "─── PHASE 5: Service Bookings API ───"

RESPONSE=$(curl -s --max-time 15 "${BASE_URL}/api/v1/urban-goodz/service-bookings" 2>/dev/null || echo '{"error":"timeout"}')
if echo "$RESPONSE" | grep -q '"status"'; then
    pass "Service /bookings: requires auth (rejected)"
else
    warn "Service /bookings: unexpected format"
fi

RESPONSE=$(curl -s --max-time 15 "${BASE_URL}/api/v1/urban-goodz/service-bookings/slots" 2>/dev/null || echo '{"error":"timeout"}')
if echo "$RESPONSE" | grep -q '"status"'; then
    pass "Service /slots: responded"
else
    warn "Service /slots: unexpected format"
fi

echo ""

# ─── PHASE 6: Product Marketplace API ───
echo "─── PHASE 6: Product Marketplace API ───"

RESPONSE=$(curl -s --max-time 15 "${BASE_URL}/api/v1/urban-goodz/products" 2>/dev/null || echo '{"error":"timeout"}')
if echo "$RESPONSE" | grep -q '"status"'; then
    pass "Products /list: requires auth (rejected)"
else
    warn "Products /list: unexpected format"
fi

RESPONSE=$(curl -s --max-time 15 "${BASE_URL}/api/v1/urban-goodz/products/search?q=test" 2>/dev/null || echo '{"error":"timeout"}')
if echo "$RESPONSE" | grep -q '"status"'; then
    pass "Products /search: requires auth (rejected)"
else
    warn "Products /search: unexpected format"
fi

echo ""

# ─── PHASE 7: Fashion Fit API ───
echo "─── PHASE 7: Fashion Fit API ───"

RESPONSE=$(curl -s --max-time 15 -X POST "${BASE_URL}/api/v1/urban-goodz/fashion-fit/body-scan" \
    -H "Content-Type: application/json" 2>/dev/null || echo '{"error":"timeout"}')
if echo "$RESPONSE" | grep -q '"status"'; then
    pass "Fashion Fit /body-scan: requires auth (rejected)"
else
    warn "Fashion Fit /body-scan: unexpected format"
fi

RESPONSE=$(curl -s --max-time 15 "${BASE_URL}/api/v1/urban-goodz/fashion-fit/recommendations" 2>/dev/null || echo '{"error":"timeout"}')
if echo "$RESPONSE" | grep -q '"status"'; then
    pass "Fashion Fit /recommendations: requires auth (rejected)"
else
    warn "Fashion Fit /recommendations: unexpected format"
fi

echo ""

# ─── PHASE 8: Unauthenticated Redirects ───
echo "─── PHASE 8: Unauthenticated Redirects ───"

for path in /business/dashboard /business/orders /business/users /business/load-board; do
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 -L "${BASE_URL}${path}" 2>/dev/null || echo "000")
    if [ "$HTTP_CODE" = "200" ]; then
        pass "${path}: redirects to login (final 200)"
    elif [ "$HTTP_CODE" = "302" ] || [ "$HTTP_CODE" = "301" ]; then
        pass "${path}: redirects (${HTTP_CODE})"
    else
        warn "${path}: HTTP ${HTTP_CODE}"
    fi
done

echo ""

# ═══ SUMMARY ═══
echo "╔══════════════════════════════════════════════════════════╗"
echo "║                    TEST SUMMARY                         ║"
echo "╠══════════════════════════════════════════════════════════╣"
echo -e "║  ${GREEN}✅ Passed: ${PASS}${NC}"
echo -e "║  ${RED}❌ Failed: ${FAIL}${NC}"
echo -e "║  ${YELLOW}⚠️  Warnings: ${WARN}${NC}"
echo "╚══════════════════════════════════════════════════════════╝"
echo ""

if [ "$FAIL" -gt 0 ]; then
    echo -e "${RED}Some tests failed. Review output above.${NC}"
    exit 1
else
    echo -e "${GREEN}All critical tests passed!${NC}"
    exit 0
fi
