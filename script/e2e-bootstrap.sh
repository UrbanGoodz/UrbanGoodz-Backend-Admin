#!/bin/bash
# =====================================================
# Urban Goodz - local E2E environment bootstrap
#
# Stands up a throwaway MySQL schema, migrates it, seeds fixtures and creates
# the exact accounts tests/playwright/*.spec.js authenticate as, so the suite
# can be run locally without hand-assembly.
#
# Usage:
#   bash script/e2e-bootstrap.sh            # build/refresh the environment
#   bash script/e2e-bootstrap.sh --fresh    # drop and rebuild from zero
#
# Then:
#   php artisan serve --host=127.0.0.1 --port=8000
#   npx playwright test
#
# NEVER point this at a real database. StagingRoleFixtureSeeder deliberately
# refuses any schema whose name does not match /staging|test/i, which is why
# the default name ends in _test.
# =====================================================
set -euo pipefail

DB_NAME="${E2E_DB:-urbangoodz_e2e_test}"
MYSQL_BIN="${MYSQL_BIN:-/c/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql}"
FIXTURE_PASSWORD="${STAGING_FIXTURE_PASSWORD:-TestPass123!}"
export STAGING_FIXTURE_PASSWORD="$FIXTURE_PASSWORD"

case "$DB_NAME" in
    *staging*|*test*) ;;
    *) echo "FATAL: refusing to use '$DB_NAME' - the name must contain 'staging' or 'test'."; exit 1 ;;
esac

echo "=== Urban Goodz local E2E bootstrap ==="
echo "schema: $DB_NAME"

# ---------- 1. schema -----------------------------------------------------
if [ "${1:-}" = "--fresh" ]; then
    echo "[1/5] Dropping and recreating $DB_NAME..."
    "$MYSQL_BIN" -u root --protocol=TCP -e "DROP DATABASE IF EXISTS \`$DB_NAME\`;"
else
    echo "[1/5] Ensuring $DB_NAME exists..."
fi
"$MYSQL_BIN" -u root --protocol=TCP \
    -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# ---------- 2. point .env at it -------------------------------------------
echo "[2/5] Pointing .env at $DB_NAME..."
php -r '
$p=".env"; $s=file_get_contents($p);
$set=function($s,$k,$v){ return preg_match("/^$k=/m",$s)
    ? preg_replace("/^$k=.*$/m","$k=$v",$s)
    : rtrim($s,"\n")."\n$k=$v\n"; };
foreach ([
  "DB_CONNECTION"=>"mysql","DB_HOST"=>"127.0.0.1","DB_PORT"=>"3306",
  "DB_DATABASE"=>getenv("E2E_DB") ?: "urbangoodz_e2e_test",
  "DB_USERNAME"=>"root","DB_PASSWORD"=>"",
  "APP_ENV"=>"local","APP_DEBUG"=>"true","APP_URL"=>"http://127.0.0.1:8000",
  "SESSION_DRIVER"=>"file","CACHE_DRIVER"=>"file","QUEUE_CONNECTION"=>"sync",
] as $k=>$v) { $s=$set($s,$k,$v); }
file_put_contents($p,$s);
'
php artisan config:clear >/dev/null 2>&1 || true

# ---------- 3. migrate ----------------------------------------------------
echo "[3/5] Migrating..."
php artisan migrate --force >/dev/null
echo "  $(php -r 'require "vendor/autoload.php"; $a=require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo DB::select("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE()")[0]->c;') tables"

# ---------- 4. seed -------------------------------------------------------
# Order matters. StagingRoleFixtureSeeder replaces the admins table with its
# own deterministic fixtures (ids 9001+), so any account the specs rely on must
# be created AFTER it, not before - otherwise it is silently wiped and every
# admin test fails on a login page it cannot get past.
echo "[4/5] Seeding fixtures..."
for s in StagingRoleFixtureSeeder QaTestAccountsSeeder QaAutomationAccountsSeeder \
         QaTestProductSeeder QaAutomationProductSeeder; do
    printf "  %-30s " "$s"
    if out=$(php artisan db:seed --class="$s" --force 2>&1); then
        if echo "$out" | grep -qiE "SQLSTATE|Unknown column|RuntimeException"; then
            echo "FAILED"; echo "$out" | grep -oiE "Unknown column '[a-z_]+'|SQLSTATE\[[0-9A-Z]+\]" | head -1 | sed 's/^/      /'
        else
            echo "ok"
        fi
    else
        echo "FAILED (non-zero exit)"
    fi
done

# ---------- 5. spec accounts ---------------------------------------------
# The Playwright specs hardcode these three identities. The seeders create
# equivalent roles under @fixture.invalid addresses, so the accounts the specs
# actually log in as have to be materialised here.
echo "[5/5] Creating the accounts the specs authenticate as..."
php -r '
require "vendor/autoload.php"; $a=require "bootstrap/app.php";
$a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$pw = getenv("STAGING_FIXTURE_PASSWORD") ?: "TestPass123!";

$role = DB::table("admin_roles")->orderBy("id")->first();
$admin = App\Models\Admin::updateOrCreate(
  ["email"=>"admin_test@urbangoodz.test"],
  ["f_name"=>"QA","l_name"=>"Admin","phone"=>"5550000001","password"=>bcrypt($pw),
   "role_id"=>$role->id ?? 1,"image"=>"def.png","is_logged_in"=>1]
);
printf("  admin_test@urbangoodz.test        id=%s role=%s\n", $admin->id, $admin->role_id);

$hash = Hash::make($pw);
foreach ([
  ["staging.business.owner@fixture.invalid","business_test@urbangoodz.test","QA","BusinessOwner"],
  ["staging.dispatcher@fixture.invalid","dispatcher_test@urbangoodz.test","QA","Dispatcher"],
] as [$src,$email,$first,$last]) {
    $row = DB::table("urban_goodz_business_client_users")->where("email",$src)->first();
    if (!$row) { echo "  MISSING source fixture $src\n"; continue; }
    $data = (array) $row; unset($data["id"]);
    $data["email"]=$email; $data["first_name"]=$first; $data["last_name"]=$last;
    $data["password"]=$hash; $data["created_at"]=now(); $data["updated_at"]=now();
    DB::table("urban_goodz_business_client_users")->updateOrInsert(["email"=>$email], $data);
    printf("  %-34s role=%s\n", $email, $data["role"]);
}
'

echo ""
echo "=== ready ==="
echo "  php artisan serve --host=127.0.0.1 --port=8000"
echo "  npx playwright test"
echo "  password for every fixture account: $FIXTURE_PASSWORD"
