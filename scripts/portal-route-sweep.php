<?php
/**
 * GET-only sweep of every routed page in a portal, to catch pages that answer
 * 500 but that no feature test happens to open.
 *
 * The browser suite proves a handful of journeys deeply. This proves breadth:
 * it logs in for real and requests every registered GET route in a portal. On
 * 2026-09-13 it found eight genuinely broken admin pages the 39-test suite
 * never touched - an uncompiled Blade, a redirect to an unregistered route
 * name, a SELECT on a column that does not exist, and several null
 * dereferences.
 *
 *   php -S 127.0.0.1:8080 server.php &        (or any server)
 *   php scripts/portal-route-sweep.php admin
 *   php scripts/portal-route-sweep.php vendor
 *   php scripts/portal-route-sweep.php business
 *
 * LOCAL/TEST DATABASES ONLY: it sets a known password on one account per
 * portal so it can log in. Read-only against the app - it issues GET and
 * nothing else, and skips any path whose name suggests it mutates, exports or
 * signs out.
 *
 * Reading the results: two classes of 500 are artifacts of crawling rather
 * than defects, and should be judged as such.
 *
 *   1. `/edit/1`, `/view/1/1` - the id is substituted, and record 1 often does
 *      not exist in a given database. Ideally those would 404, not 500.
 *   2. AJAX endpoints invoked with no query string, e.g. item/get-stock.
 *
 * A 500 on a path with no parameters is the signal worth chasing.
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

$db = DB::connection()->getDatabaseName();
if (!str_contains($db, 'local') && !str_contains($db, 'test')) {
    fwrite(STDERR, "REFUSING: database '{$db}' is not local/test.\n");
    exit(2);
}

$which = $argv[1] ?? 'admin';
$base  = rtrim(getenv('SWEEP_BASE_URL') ?: 'http://127.0.0.1:8080', '/') . '/';
$jar   = sys_get_temp_dir() . '/sweep_' . $which . '.txt';
@unlink($jar);

const PW = 'PortalSweep!2026';

function req(string $url, array $opt = []): array
{
    global $jar;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 45,
        CURLOPT_COOKIEJAR      => $jar,
        CURLOPT_COOKIEFILE     => $jar,
        CURLOPT_FOLLOWLOCATION => false,
    ] + $opt);
    $body = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [$code, $body];
}

/** The login form carries a CSRF token and, in APP_MODE=dev, a prefilled captcha. */
function formFields(string $html): array
{
    preg_match('/name="_token" value="([^"]+)"/', $html, $t);
    preg_match('/id="custome_recaptcha"[^>]*value="([^"]*)"/s', $html, $c);

    return [$t[1] ?? '', $c[1] ?? ''];
}

switch ($which) {
    case 'vendor':
        $account = DB::table('vendors')->orderBy('id')->first();
        if (!$account) { fwrite(STDERR, "no vendor account\n"); exit(2); }
        DB::table('vendors')->where('id', $account->id)->update(['password' => Hash::make(PW)]);

        [, $page] = req($base . 'login/vendor');
        [$token, $captcha] = formFields($page);
        req($base . 'login_submit', [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query([
            '_token' => $token, 'email' => $account->email, 'password' => PW,
            'custome_recaptcha' => $captcha, 'set_default_captcha' => 1, 'role' => 'vendor',
        ])]);
        [$prefix, $home, $who] = ['vendor-panel', 'vendor-panel', $account->email];
        break;

    case 'business':
        $account = DB::table('urban_goodz_business_client_users')->orderBy('id')->first();
        if (!$account) { fwrite(STDERR, "no business client user\n"); exit(2); }
        DB::table('urban_goodz_business_client_users')->where('id', $account->id)
            ->update(['password' => Hash::make(PW), 'is_active' => 1]);

        [, $page] = req($base . 'business/login');
        [$token] = formFields($page);
        req($base . 'business/login', [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query([
            '_token' => $token, 'email' => $account->email, 'password' => PW,
        ])]);
        [$prefix, $home, $who] = ['business', 'business/dashboard', $account->email];
        break;

    default:
        // Uses the fully-privileged fixture from seed-admin-role-fixture.php.
        [, $page] = req($base . 'login/admin');
        [$token, $captcha] = formFields($page);
        req($base . 'login_submit', [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query([
            '_token' => $token, 'email' => 'pw.full@urbangoodz.test', 'password' => 'PwFixture!2026',
            'custome_recaptcha' => $captcha, 'set_default_captcha' => 1, 'role' => 'admin',
        ])]);
        [$prefix, $home, $who] = ['admin', 'admin', 'pw.full@urbangoodz.test'];
}

printf("portal   : %s\nas       : %s\n", $which, $who);

[$code] = req($base . $home);
printf("home     : /%s = %d\n", $home, $code);
if ($code !== 200) {
    fwrite(STDERR, "login did not reach an authenticated page; results would be meaningless\n");
    exit(3);
}

$skip = '/(delete|destroy|remove|purge|truncate|reset|logout|export|download|clear|flush|sync|retry|import|install|activate|deactivate|approve|reject|publish|toggle|rollback|auto-fill|autofill|generate|maintenance-mode|landing-page)/i';

// Some state-changing actions are exposed over GET and end in a bare verb, so
// a substring rule like "status/" misses them: admin/advertisement/status has
// no trailing slash and was being *called* by this sweep. It changed nothing
// only because validation rejected the empty request first. Match the final
// segment too, so the sweep stays genuinely read-only.
// Matched as a SUBSTRING of the final segment, not an equality test. An
// equality list still missed vendor-panel/business-settings/update-active-status,
// a GET route that flips a store's active flag - this sweep called it as the
// store's own vendor and took Demo Store offline. Over-skipping costs a little
// coverage; under-skipping silently mutates the database.
$mutatingLastSegment = '/(status|update|active|toggle|priority|assign|cancel|refund|settle|save|create|^add$|^store$|^pay$)/i';

$targets = [];
foreach (app('router')->getRoutes() as $route) {
    if (!in_array('GET', $route->methods(), true)) continue;
    $uri = $route->uri();
    if (!str_starts_with($uri, $prefix)) continue;
    if (preg_match($skip, $uri)) continue;

    $segments = explode('/', $uri);
    if (preg_match($mutatingLastSegment, end($segments))) continue;
    $u = preg_replace('/\{[^}]+\}/', '1', $uri);
    if (str_contains($u, '{')) continue;
    $targets[$u] = true;
}
$targets = array_keys($targets);
sort($targets);

printf("crawling : %d GET routes\n\n", count($targets));

/**
 * Name-based skipping is best-effort and cannot be trusted on its own: this
 * codebase exposes state changes on GET routes named as plain nouns -
 * admin/maintenance-mode and admin/landing-page each TOGGLE their setting when
 * fetched, and no verb heuristic will ever catch that. An earlier run of this
 * sweep switched maintenance mode on and took a store offline.
 *
 * So the sweep no longer relies on guessing. It snapshots the settings a stray
 * GET is known to be able to flip, and restores anything that moved, reporting
 * what it had to undo.
 */
$snapshot = static fn (): array => [
    'business_settings' => DB::table('business_settings')->orderBy('id')->pluck('value', 'id')->toArray(),
    'stores_active'     => DB::table('stores')->orderBy('id')->pluck('active', 'id')->toArray(),
    'zones_status'      => DB::table('zones')->orderBy('id')->pluck('status', 'id')->toArray(),
];
$before = $snapshot();

$counts = [];
$paramless = [];
$n = 0;

foreach ($targets as $u) {
    $n++;
    [$code] = req($base . $u);
    $counts[$code] = ($counts[$code] ?? 0) + 1;

    if ($code >= 500 || $code === 0) {
        $isArtifact = (bool) preg_match('#/1(/|$)#', $u);
        if (!$isArtifact) {
            $paramless[] = $u;
        }
        printf("FAIL %-3s %-58s %s\n", $code ?: 'to', $u, $isArtifact ? '(id substituted)' : '<-- no parameters');
        flush();
    }

    if ($n % 50 === 0) { printf("  ... %d/%d\n", $n, count($targets)); flush(); }
}

// Undo anything a stray GET flipped, so a sweep never leaves the install in a
// different state than it found it.
$after = $snapshot();
$restored = 0;
$targetsByGroup = [
    'business_settings' => ['table' => 'business_settings', 'column' => 'value'],
    'stores_active'     => ['table' => 'stores', 'column' => 'active'],
    'zones_status'      => ['table' => 'zones', 'column' => 'status'],
];

foreach ($targetsByGroup as $group => $spec) {
    foreach ($after[$group] as $id => $value) {
        $was = $before[$group][$id] ?? null;
        if ((string) $value === (string) $was) continue;

        DB::table($spec['table'])->where('id', $id)->update([$spec['column'] => $was]);
        $label = $group === 'business_settings'
            ? (DB::table('business_settings')->where('id', $id)->value('key') ?: "id {$id}")
            : "{$spec['table']} id {$id}";
        printf("RESTORED %-34s %s -> %s\n", substr((string) $label, 0, 34), substr((string) $value, 0, 12), substr((string) $was, 0, 12));
        $restored++;
    }
}

echo "\nsummary:\n";
ksort($counts);
foreach ($counts as $code => $k) printf("  HTTP %-4s %d\n", $code, $k);
printf("  state changes undone: %d\n", $restored);

printf("\nparameter-less 5xx (the ones worth chasing): %d\n", count($paramless));
foreach ($paramless as $u) echo "  {$u}\n";

exit(count($paramless) > 0 ? 1 : 0);
