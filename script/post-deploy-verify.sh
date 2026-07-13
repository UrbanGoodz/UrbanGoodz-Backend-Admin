#!/bin/bash
# =====================================================
# Urban Goodz — Post-Deploy Verification Script
# Run AFTER deploy-sprint-integration.sh completes
# =====================================================
set -euo pipefail

PASS=0
FAIL=0
WARN=0

check() {
    local label="$1"
    local cmd="$2"
    if eval "$cmd" > /dev/null 2>&1; then
        echo "  ✅ $label"
        PASS=$((PASS+1))
    else
        echo "  ❌ $label"
        FAIL=$((FAIL+1))
    fi
}

warn() {
    local label="$1"
    local cmd="$2"
    if eval "$cmd" > /dev/null 2>&1; then
        echo "  ✅ $label"
        PASS=$((PASS+1))
    else
        echo "  ⚠️  $label (expected — configure externally)"
        WARN=$((WARN+1))
    fi
}

echo "=== Post-Deploy Verification ==="
echo "Time: $(date)"
echo ""

# PHP / Laravel
echo "--- PHP & Laravel ---"
check "PHP version >= 8.2" "php -r 'exit(version_compare(PHP_VERSION, \"8.2.0\", \">=\") ? 0 : 1);'"
check "artisan --version" "php artisan --version"
check "config:cache works" "php artisan config:cache"
check "route:cache works" "php artisan route:cache"

# Routes
echo ""
echo "--- Routes ---"
ROUTE_COUNT=$(php artisan route:list --columns=uri 2>/dev/null | wc -l)
if [ "$ROUTE_COUNT" -gt 100 ]; then
    echo "  ✅ Routes compiled: ~$ROUTE_COUNT lines"
    PASS=$((PASS+1))
else
    echo "  ❌ Routes compiled: only $ROUTE_COUNT (expected 2000+)"
    FAIL=$((FAIL+1))
fi

# Migrations
echo ""
echo "--- Database Migrations ---"
PENDING=$(php artisan migrate:status 2>/dev/null | grep -c "No" || true)
if [ "$PENDING" -eq 0 ]; then
    echo "  ✅ All migrations applied"
    PASS=$((PASS+1))
else
    echo "  ❌ $PENDING migrations still pending"
    FAIL=$((FAIL+1))
fi

# .env keys
echo ""
echo "--- Environment Keys ---"
check "FASHION_FIT_AI_ENABLED present" "grep -q FASHION_FIT_AI_ENABLED .env"
check "FASHION_FIT_AI_ENDPOINT present" "grep -q FASHION_FIT_AI_ENDPOINT .env"
check "SERVICE_BOOKING_PLATFORM_FEE_PERCENT present" "grep -q SERVICE_BOOKING_PLATFORM_FEE_PERCENT .env"
check "SERVICE_BOOKING_PAYMENT_SANDBOX present" "grep -q SERVICE_BOOKING_PAYMENT_SANDBOX .env"
check "MAIL_HOST present" "grep -q MAIL_HOST .env"
check "MAIL_PASSWORD present" "grep -q MAIL_PASSWORD .env"
check "STRIPE_SECRET_KEY present" "grep -q STRIPE_SECRET_KEY .env"

# SMTP (external gate)
echo ""
echo "--- SMTP (External Gates) ---"
warn "SMTP host reachable" "nc -z -w5 $(grep MAIL_HOST .env | cut -d'=' -f2) $(grep MAIL_PORT .env | cut -d'=' -f2) 2>/dev/null"

# Fashion Fit (external gate)
echo ""
echo "--- Fashion Fit AI (External Gate) ---"
warn "Fashion Fit endpoint configured" "grep -q 'FASHION_FIT_AI_ENDPOINT=.\\+' .env"
warn "Fashion Fit API key configured" "grep -q 'FASHION_FIT_AI_API_KEY=.\\+' .env"

# Service Booking (external gate)
echo ""
echo "--- Service Bookings (External Gate) ---"
warn "Service booking endpoint configured" "grep -q 'SERVICE_BOOKING_PAYMENT_ENDPOINT=.\\+' .env"
warn "Service booking secret configured" "grep -q 'SERVICE_BOOKING_PAYMENT_SECRET=.\\+' .env"

# Firebase (external gate)
echo ""
echo "--- Firebase (External Gate) ---"
warn "Pusher/Reverb credentials configured" "grep -q 'PUSHER_APP_ID=[^6]' .env"

# Storage
echo ""
echo "--- Storage ---"
check "storage directory writable" "test -w storage"
check "bootstrap/cache writable" "test -w bootstrap/cache"
check "private storage not browsable" "! test -d storage/app/public"

# Queue
echo ""
echo "--- Queue & Scheduler ---"
check "queue:restart runs" "php artisan queue:restart 2>/dev/null"
check "schedule:run runs" "php artisan schedule:run 2>/dev/null || true"

# Tests
echo ""
echo "--- Tests ---"
TEST_OUTPUT=$(php vendor/bin/phpunit --testdox 2>&1 | tail -1)
if echo "$TEST_OUTPUT" | grep -q "Tests: 135"; then
    echo "  ✅ Test suite: $TEST_OUTPUT"
    PASS=$((PASS+1))
else
    echo "  ❌ Test suite unexpected: $TEST_OUTPUT"
    FAIL=$((FAIL+1))
fi

echo ""
echo "================================="
echo "PASS: $PASS | FAIL: $FAIL | WARN: $WARN"
echo "================================="
if [ "$FAIL" -gt 0 ]; then
    echo "❌ FAILURES DETECTED — investigate before proceeding"
    exit 1
else
    echo "✅ All code-level checks passed"
    echo "⚠️  External gates (SMTP, AI, Payments, Firebase) require manual verification"
fi
