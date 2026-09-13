<?php
/**
 * Seed the Playwright Admin role-fixture pair. LOCAL/TEST DATABASES ONLY.
 *
 * The browser suite's authorization tests prove one thing: that a single
 * module, `urban_goodz_view`, is what gates the module-protected Urban Goodz
 * route. To prove it rather than assume it they need two admins who are
 * identical in every other respect - same modules, both non-primary - and
 * differ by exactly that one entry.
 *
 * Building that pair by hand is fiddly and easy to get subtly wrong (grant one
 * role an extra module and the boundary test silently stops proving anything),
 * so it lives here.
 *
 *   php scripts/seed-admin-role-fixture.php
 *   php scripts/verify-admin-role-fixture.php \
 *       --authorized=pw.authorized@urbangoodz.test \
 *       --restricted=pw.restricted@urbangoodz.test
 *
 * The module list is derived from the admin sidebar itself - every name passed
 * to Helpers::module_permission_check() - so the fixture can actually see the
 * navigation the specs click through. module_permission_check does an exact
 * in_array() match, so approximate names are the same as no permission.
 *
 * These are disposable accounts with a known password. They must never exist
 * on production; the guard below refuses any database that does not look
 * local or test.
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

const DIFFERENTIATOR = 'urban_goodz_view';
const PASSWORD       = 'PwFixture!2026';
const AUTHORIZED     = 'pw.authorized@urbangoodz.test';
const RESTRICTED     = 'pw.restricted@urbangoodz.test';

$db = DB::connection()->getDatabaseName();
if (!str_contains($db, 'local') && !str_contains($db, 'test')) {
    fwrite(STDERR, "REFUSING: database '{$db}' does not look local or test.\n");
    exit(2);
}
echo "database: {$db}\n";

/** Every module the admin sidebar gates on, read from the sidebar partials. */
function sidebarModules(): array
{
    $modules = [];
    foreach (glob(__DIR__ . '/../resources/views/layouts/admin/partials/_sidebar*.blade.php') as $file) {
        if (preg_match_all("/module_permission_check\('([a-z0-9_]+)'\)/", (string) file_get_contents($file), $m)) {
            $modules = array_merge($modules, $m[1]);
        }
    }
    $modules = array_values(array_unique($modules));
    sort($modules);
    return $modules;
}

function upsertRole(string $name, array $modules): int
{
    $row = DB::table('admin_roles')->where('name', $name)->first();
    $data = ['name' => $name, 'modules' => json_encode($modules), 'status' => 1, 'updated_at' => now()];

    if ($row) {
        DB::table('admin_roles')->where('id', $row->id)->update($data);
        return (int) $row->id;
    }

    $data['created_at'] = now();
    return (int) DB::table('admin_roles')->insertGetId($data);
}

function upsertAdmin(string $email, string $first, int $roleId): int
{
    $row = DB::table('admins')->where('email', $email)->first();
    $data = [
        'f_name'     => $first,
        'l_name'     => 'Fixture',
        'email'      => $email,
        'role_id'    => $roleId,
        'password'   => Hash::make(PASSWORD),
        'updated_at' => now(),
    ];

    if ($row) {
        DB::table('admins')->where('id', $row->id)->update($data);
        return (int) $row->id;
    }

    $data['created_at'] = now();
    return (int) DB::table('admins')->insertGetId($data);
}

$all        = sidebarModules();
$authorized = $all;
$restricted = array_values(array_diff($all, [DIFFERENTIATOR]));

if (count($authorized) === count($restricted)) {
    fwrite(STDERR, "REFUSING: '" . DIFFERENTIATOR . "' is not gated by any sidebar partial, so the pair would be identical.\n");
    exit(3);
}

$authRole = upsertRole('PW Fixture Authorized', $authorized);
$restRole = upsertRole('PW Fixture Restricted', $restricted);

if ($authRole === 1 || $restRole === 1) {
    fwrite(STDERR, "REFUSING: a fixture role resolved to the primary Admin role.\n");
    exit(4);
}

$authAdmin = upsertAdmin(AUTHORIZED, 'PwAuthorized', $authRole);
$restAdmin = upsertAdmin(RESTRICTED, 'PwRestricted', $restRole);

$difference = array_values(array_merge(
    array_diff($authorized, $restricted),
    array_diff($restricted, $authorized)
));

printf("authorized  role_id=%d admin_id=%d modules=%d  %s\n", $authRole, $authAdmin, count($authorized), AUTHORIZED);
printf("restricted  role_id=%d admin_id=%d modules=%d  %s\n", $restRole, $restAdmin, count($restricted), RESTRICTED);
printf("symmetric difference: %s\n", implode(',', $difference));
printf("password: %s\n", PASSWORD);

echo "\nRun the browser suite with:\n";
echo "  BASE_URL=http://127.0.0.1:8000 \\\n";
echo "  ADMIN_TEST_EMAIL=" . AUTHORIZED . " ADMIN_TEST_PASSWORD='" . PASSWORD . "' \\\n";
echo "  ADMIN_RESTRICTED_TEST_EMAIL=" . RESTRICTED . " ADMIN_RESTRICTED_TEST_PASSWORD='" . PASSWORD . "' \\\n";
echo "  npx playwright test --config=tests/Browser/playwright.config.js\n";
echo "\nThe target server must run APP_MODE=dev - the suite relies on the\n";
echo "custom CAPTCHA being pre-filled server-side. Production runs APP_MODE=live.\n";
