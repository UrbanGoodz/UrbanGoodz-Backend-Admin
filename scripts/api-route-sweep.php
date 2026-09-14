<?php
/**
 * GET-only sweep of the mobile API, as the app that actually calls it.
 *
 * The portal sweep covers what a browser reaches. This covers what the customer,
 * driver and vendor apps reach - the layer those apps depend on and the only way
 * to exercise their backend without a device in hand.
 *
 *   php scripts/api-route-sweep.php customer
 *   php scripts/api-route-sweep.php driver
 *   php scripts/api-route-sweep.php vendor
 *
 * LOCAL/TEST DATABASES ONLY: it sets a known password on one account per role so
 * it can obtain a bearer token. Read-only - GET only, and any path whose name
 * suggests it mutates is skipped.
 *
 * Every request carries moduleId and zoneId. Those two headers are how this
 * codebase scopes nearly every customer endpoint; without them you get empty
 * results or a redirect, which reads like a broken API and is not.
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

$role = $argv[1] ?? 'customer';
$base = rtrim(getenv('SWEEP_BASE_URL') ?: 'http://127.0.0.1:8080', '/') . '/api/v1/';

const PW = 'ApiSweep!2026';

function call(string $url, array $headers, string $method = 'GET', array $body = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 45,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => array_merge(['Accept: application/json', 'Content-Type: application/json'], $headers),
    ]);
    if ($body) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $raw  = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [$code, $raw];
}

// A store that is open now gives us a realistic module/zone pair to scope with.
$store = DB::table('stores')->where('status', 1)->where('active', 1)
    ->whereIn('id', DB::table('items')->where('status', 1)->distinct()->pluck('store_id'))
    ->orderBy('id')->first();
if (!$store) { fwrite(STDERR, "no active store to scope with\n"); exit(2); }

$headers = [
    'moduleId: ' . $store->module_id,
    'zoneId: [' . $store->zone_id . ']',
    'X-localization: en',
];

switch ($role) {
    case 'driver':
        $account = DB::table('delivery_men')->where('status', 1)->orderBy('id')->first();
        if (!$account) { fwrite(STDERR, "no active delivery man\n"); exit(2); }
        DB::table('delivery_men')->where('id', $account->id)->update(['password' => Hash::make(PW)]);
        [$c, $raw] = call($base . 'auth/delivery-man/login', $headers, 'POST',
            ['phone' => $account->phone, 'password' => PW]);
        $prefix = 'api/v1/delivery-man';
        $who = $account->phone;
        break;

    case 'vendor':
        $account = DB::table('vendors')->orderBy('id')->first();
        if (!$account) { fwrite(STDERR, "no vendor\n"); exit(2); }
        DB::table('vendors')->where('id', $account->id)->update(['password' => Hash::make(PW)]);
        [$c, $raw] = call($base . 'auth/vendor/login', $headers, 'POST',
            ['email' => $account->email, 'password' => PW, 'vendor_type' => 'owner']);
        // VendorTokenIsValid reads the vendor type from a header, not the body.
        $headers[] = 'vendorType: owner';
        $prefix = 'api/v1/vendor';
        $who = $account->email;
        break;

    default:
        $phone = '+15550001111';
        $existing = DB::table('users')->where('phone', $phone)->first();
        $data = ['f_name' => 'ApiSweep', 'l_name' => 'Fixture', 'email' => 'api.sweep@urbangoodz.test',
                 'phone' => $phone, 'password' => Hash::make(PW), 'status' => 1,
                 'is_phone_verified' => 1, 'updated_at' => now()];
        if ($existing) {
            DB::table('users')->where('id', $existing->id)->update($data);
        } else {
            $data['created_at'] = now();
            DB::table('users')->insert($data);
        }
        [$c, $raw] = call($base . 'auth/login', $headers, 'POST',
            ['email_or_phone' => $phone, 'field_type' => 'phone', 'password' => PW, 'login_type' => 'manual']);
        // The customer app does not only call api/v1/customer. It calls the
        // shared catalogue and the Urban Goodz surface too - 201 further routes,
        // urban-goodz alone being the largest - so sweep those as a signed-in
        // customer rather than leaving the app's real surface untested.
        $prefix = ['api/v1/customer', 'api/v1/urban-goodz', 'api/v1/items', 'api/v1/item',
                   'api/v1/stores', 'api/v1/order-anywhere', 'api/v1/fashion-fit',
                   'api/v1/categories', 'api/v1/config', 'api/v1/campaigns',
                   'api/v1/coupon', 'api/v1/banners', 'api/v1/other-banners',
                   'api/v1/flash-sales', 'api/v1/brand', 'api/v1/module',
                   'api/v1/zone', 'api/v1/cashback', 'api/v1/advertisement'];
        $who = $phone;
}

$token = json_decode($raw, true)['token'] ?? null;
printf("role     : %s\nas       : %s\nlogin    : HTTP %d %s\n", $role, $who, $c, $token ? '(token)' : '(NO TOKEN)');
if (!$token) {
    fwrite(STDERR, "could not authenticate; results would be meaningless\n" . substr($raw, 0, 200) . "\n");
    exit(3);
}
$headers[] = 'Authorization: Bearer ' . $token;

$skip = '/(delete|destroy|remove|purge|reset|logout|export|download|clear|flush|sync|retry|import|install|activate|deactivate|approve|reject|publish|toggle|rollback|generate|cancel|refund|place|store|update|status|accept|pay)/i';

$targets = [];
foreach (app('router')->getRoutes() as $route) {
    if (!in_array('GET', $route->methods(), true)) continue;
    $uri = $route->uri();
    $matched = false;
    foreach ((array) $prefix as $candidate) {
        if (str_starts_with($uri, $candidate)) { $matched = true; break; }
    }
    if (!$matched) continue;
    if (preg_match($skip, $uri)) continue;
    if (str_contains($uri, '{')) continue;   // needs an id we cannot invent safely
    $targets[$uri] = true;
}
$targets = array_keys($targets);
sort($targets);
printf("crawling : %d GET routes under %s\n\n", count($targets), implode(', ', (array) $prefix));

$counts = [];
$fails = [];
foreach ($targets as $uri) {
    $path = substr($uri, strlen('api/v1/'));
    [$code, $raw] = call($base . $path, $headers);
    $counts[$code] = ($counts[$code] ?? 0) + 1;

    if ($code >= 500 || $code === 0) {
        $msg = json_decode($raw, true)['message'] ?? '';
        $fails[] = $uri;
        printf("FAIL %-3s %-52s %s\n", $code ?: 'to', $path, substr((string) $msg, 0, 60));
        flush();
    }
}

echo "\nsummary:\n";
ksort($counts);
foreach ($counts as $code => $k) printf("  HTTP %-4s %d\n", $code, $k);
printf("\n5xx: %d\n", count($fails));

exit(count($fails) > 0 ? 1 : 0);
