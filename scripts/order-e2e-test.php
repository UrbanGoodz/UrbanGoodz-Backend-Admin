<?php
/**
 * End-to-end order test against the REAL HTTP API, driven the way the customer
 * app drives it: bearer token, moduleId/zoneId headers, the same endpoints.
 *
 * This exists because "the order flow works" had only ever been asserted from
 * route listings and static analysis. Placing an order touches cart validation,
 * zone resolution, store scheduling, distance/fee calculation, order_details
 * expansion and the status machine - none of which a route listing exercises.
 *
 * LOCAL/TEST DATABASES ONLY. It seeds one disposable customer so the run is
 * repeatable; it never creates anything on a live service, and it only ever
 * places cash-on-delivery orders so no payment provider is involved.
 *
 *   php -S 127.0.0.1:8000 server.php &
 *   php scripts/order-e2e-test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

$db = DB::connection()->getDatabaseName();
if (!str_contains($db, 'local') && !str_contains($db, 'test')) {
    fwrite(STDERR, "REFUSING: database '{$db}' is not local/test.\n");
    exit(2);
}

define('BASE', getenv('E2E_BASE_URL') ?: 'http://127.0.0.1:8000/api/v1');
const PHONE = '+15550001111';
const PASS  = 'OrderE2E!2026';
const ITEM_TYPE = 'App\\Models\\Item';

$pass = 0;
$fail = 0;

function step(string $name, bool $ok, string $detail = ''): bool
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    printf("[%s] %-46s %s\n", $ok ? ' OK ' : 'FAIL', $name, $detail);
    return $ok;
}

function api(string $method, string $path, array $body = [], array $hdr = []): array
{
    $ch = curl_init(BASE . $path);
    $h = array_merge(['Accept: application/json', 'Content-Type: application/json'], $hdr);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 45,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $h,
    ]);
    if ($body) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $raw  = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $code, 'json' => json_decode($raw, true), 'raw' => $raw];
}

function err(array $r): string
{
    $e = $r['json']['errors'][0]['message']
        ?? $r['json']['message']
        ?? substr(strip_tags($r['raw']), 0, 150);

    return 'HTTP ' . $r['code'] . ' ' . trim(preg_replace('/\s+/', ' ', (string) $e));
}

// ── Pick a real, orderable store ────────────────────────────────────────────
// It has to be open *now*, not merely active. new_place_order enforces the
// store schedule and answers "Store is closed at order time" otherwise, so
// selecting on status alone makes the run pass or fail by wall clock.
$today = now()->dayOfWeek;
$clock = now()->format('H:i:s');

$store = DB::table('stores')->where('stores.status', 1)->where('stores.active', 1)
    ->whereIn('stores.id', DB::table('items')->where('status', 1)->distinct()->pluck('store_id'))
    ->whereExists(function ($q) use ($today, $clock) {
        $q->select(DB::raw(1))->from('store_schedule')
            ->whereColumn('store_schedule.store_id', 'stores.id')
            ->where('store_schedule.day', $today)
            ->whereRaw('? BETWEEN store_schedule.opening_time AND store_schedule.closing_time', [$clock]);
    })
    ->orderByDesc('stores.id')->first();

if (!$store) {
    fwrite(STDERR, "SKIP: no active store is open at {$clock} on day {$today}.\n");
    exit(3);
}

$item = DB::table('items')->where('store_id', $store->id)->where('status', 1)->first();

// The delivery point must fall inside the zone POLYGON, not merely near it -
// getZoneAndStore resolves the zone with ST_Contains and returns null otherwise.
// A hardcoded downtown lat/long silently lands outside most zones, so take a
// point the geometry itself guarantees is inside.
$zone = DB::table('zones')->where('id', $store->zone_id)
    ->selectRaw('id, name, ST_Y(ST_Centroid(coordinates)) as lat, ST_X(ST_Centroid(coordinates)) as lng')
    ->first();

if (!$zone) {
    fwrite(STDERR, "SKIP: store {$store->id} has no resolvable zone geometry.\n");
    exit(3);
}

printf("store   : %d %s (zone %s, module %s)\n", $store->id, $store->name, $store->zone_id, $store->module_id);
printf("zone    : %d %s  centroid=%.5f,%.5f\n", $zone->id, $zone->name, $zone->lat, $zone->lng);
printf("item    : %d %s  price=%s\n", $item->id, $item->name, $item->price);

// ── Disposable local customer ───────────────────────────────────────────────
$existing = DB::table('users')->where('phone', PHONE)->first();
$data = [
    'f_name'            => 'OrderE2E',
    'l_name'            => 'Fixture',
    'email'             => 'order.e2e@urbangoodz.test',
    'phone'             => PHONE,
    'password'          => Hash::make(PASS),
    'status'            => 1,
    'is_phone_verified' => 1,
    'updated_at'        => now(),
];

if ($existing) {
    DB::table('users')->where('id', $existing->id)->update($data);
    $userId = (int) $existing->id;
} else {
    $data['created_at'] = now();
    $userId = (int) DB::table('users')->insertGetId($data);
}

printf("customer: %d %s\n\n", $userId, PHONE);

$HDR = [
    'moduleId: ' . $store->module_id,
    'zoneId: [' . $store->zone_id . ']',
    'X-localization: en',
];

// ── 1. Login ────────────────────────────────────────────────────────────────
$r = api('POST', '/auth/login', [
    'email_or_phone' => PHONE,
    'field_type'     => 'phone',
    'password'       => PASS,
    'login_type'     => 'manual',
], $HDR);
$token = $r['json']['token'] ?? null;
if (!step('customer logs in', $token !== null, $token ? '' : err($r))) {
    echo "\n--- login response ---\n" . substr($r['raw'], 0, 600) . "\n";
    exit(1);
}
$AUTH = array_merge($HDR, ['Authorization: Bearer ' . $token]);

// ── 2. Browse ───────────────────────────────────────────────────────────────
$r = api('GET', '/stores/details/' . $store->id, [], $AUTH);
step('store details load', $r['code'] === 200, $r['code'] === 200 ? '' : err($r));

$r = api('GET', '/items/details/' . $item->id, [], $AUTH);
step('item details load', $r['code'] === 200, $r['code'] === 200 ? '' : err($r));

// ── 3. Add to cart ──────────────────────────────────────────────────────────
// new_place_order reads the SERVER-SIDE cart, not the request payload, unless
// is_buy_now=1. Posting a cart array straight to /order/place therefore always
// answers "You can not place empty orders" - which is what a user would hit if
// the app skipped this step, so the test performs it the way the app does.
api('DELETE', '/customer/cart/remove', [], $AUTH);

$r = api('POST', '/customer/cart/add', [
    'item_id'   => (int) $item->id,
    'model'     => 'Item',
    'price'     => (float) $item->price,
    'quantity'  => 1,
    'variation' => [],
    'add_on_ids'  => [],
    'add_on_qtys' => [],
], $AUTH);
step('item added to cart', $r['code'] === 200, $r['code'] === 200 ? '' : err($r));

$r = api('GET', '/customer/cart/list', [], $AUTH);
$cartCount = is_array($r['json']) ? count($r['json']) : 0;
step('cart lists the item', $cartCount > 0, "items={$cartCount}");

// ── 4. Place the order ──────────────────────────────────────────────────────
$payload = [
    'order_type'            => 'delivery',
    'payment_method'        => 'cash_on_delivery',
    'store_id'              => (int) $store->id,
    'distance'              => 2.5,
    'address'               => '1200 McKinney St, Houston, TX 77010',
    'longitude'             => (string) $zone->lng,
    'latitude'              => (string) $zone->lat,
    'address_type'          => 'others',
    'contact_person_name'   => 'OrderE2E Fixture',
    'contact_person_number' => PHONE,
    'road'                  => 'McKinney',
    'house'                 => '1200',
    'floor'                 => '1',
    'order_amount'          => (float) $item->price,
    'dm_tips'               => 0,
];

$r = api('POST', '/customer/order/place', $payload, $AUTH);
$orderId = $r['json']['order_id'] ?? null;

if (!step('order is placed', $orderId !== null, $orderId ? "order_id={$orderId}" : err($r))) {
    echo "\n--- place response ---\n" . substr($r['raw'], 0, 1200) . "\n";
    printf("\n%d passed, %d failed\n", $pass, $fail);
    exit(1);
}

// ── 5. It really exists ─────────────────────────────────────────────────────
$row = DB::table('orders')->where('id', $orderId)->first();
step('order row persisted', $row !== null, $row ? "status={$row->order_status} total={$row->order_amount}" : 'missing');

$dc = DB::table('order_details')->where('order_id', $orderId)->count();
step('order_details written', $dc > 0, "rows={$dc}");

$r = api('GET', '/customer/order/details?order_id=' . $orderId, [], $AUTH);
step('customer reads order details', $r['code'] === 200, $r['code'] === 200 ? '' : err($r));

$r = api('GET', '/customer/order/track?order_id=' . $orderId, [], $AUTH);
step('order tracking responds', $r['code'] === 200, $r['code'] === 200 ? '' : err($r));

$r = api('GET', '/customer/order/running-orders?limit=50&offset=1', [], $AUTH);
$found = false;
foreach ((array) ($r['json']['orders'] ?? $r['json'] ?? []) as $o) {
    if ((int) ($o['id'] ?? 0) === (int) $orderId) {
        $found = true;
        break;
    }
}
step('order appears in running orders', $found, $found ? '' : err($r));

echo "\nORDER_ID={$orderId}\n";
printf("%d passed, %d failed\n", $pass, $fail);
exit($fail > 0 ? 1 : 0);
