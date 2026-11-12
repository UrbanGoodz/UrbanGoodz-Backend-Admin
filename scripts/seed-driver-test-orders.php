<?php
/**
 * Place real delivery orders through the customer API and leave each at a
 * requested point in the driver lifecycle, so the driver app has genuine work
 * to show on a device.
 *
 *   php scripts/seed-driver-test-orders.php            # default spread
 *   php scripts/seed-driver-test-orders.php pending,accepted,picked_up,delivered
 *
 * Everything is placed cash_on_delivery, so no payment provider is involved.
 * The Stripe test-money path is covered separately.
 *
 * LOCAL/TEST DATABASES ONLY.
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

define('BASE', getenv('E2E_BASE_URL') ?: 'http://127.0.0.1:8080/api/v1');
const PHONE = '+15550001111';
const CPW   = 'OrderE2E!2026';
const VPW   = 'VendorE2E!2026';
const DPW   = 'DriverE2E!2026';

$stages = array_values(array_filter(explode(',', $argv[1] ?? 'pending,accepted,picked_up,delivered')));

function api(string $m, string $p, array $b = [], array $h = []): array
{
    $ch = curl_init(BASE . $p);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 45, CURLOPT_CUSTOMREQUEST => $m,
        CURLOPT_HTTPHEADER => array_merge(['Accept: application/json', 'Content-Type: application/json'], $h),
    ]);
    if ($b) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($b));
    $raw = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $code, 'raw' => $raw, 'json' => json_decode($raw, true)];
}

function err(array $r): string
{
    return 'HTTP ' . $r['code'] . ' ' . trim(preg_replace('/\s+/', ' ',
        (string) ($r['json']['errors'][0]['message'] ?? $r['json']['message'] ?? substr(strip_tags($r['raw']), 0, 80))));
}

function orderStatus(int $id): string
{
    return (string) DB::table('orders')->where('id', $id)->value('order_status');
}

// A store that is open now AND sits in a zone with an active rider, so the
// driver leg is real rather than skipped.
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
    ->whereIn('stores.zone_id', DB::table('delivery_men')->where('status', 1)->distinct()->pluck('zone_id'))
    ->orderByDesc('stores.id')->first();

if (!$store) { fwrite(STDERR, "SKIP: no open store in a zone with an active rider.\n"); exit(3); }

$item = DB::table('items')->where('store_id', $store->id)->where('status', 1)->first();
$zone = DB::table('zones')->where('id', $store->zone_id)
    ->selectRaw('id, name, ST_Y(ST_Centroid(coordinates)) as lat, ST_X(ST_Centroid(coordinates)) as lng')->first();
$dm = DB::table('delivery_men')->where('zone_id', $store->zone_id)->where('status', 1)->first();
$vendor = DB::table('vendors')->where('id', $store->vendor_id)->first();

printf("store  : %d %s (zone %d %s)\n", $store->id, $store->name, $zone->id, $zone->name);
printf("rider  : %d %s\n", $dm->id, $dm->phone);
printf("stages : %s\n\n", implode(', ', $stages));

// ── Credentials for the three roles ─────────────────────────────────────────
$customer = DB::table('users')->where('phone', PHONE)->first();
if ($customer) {
    DB::table('users')->where('id', $customer->id)->update(['password' => Hash::make(CPW), 'status' => 1]);
} else {
    DB::table('users')->insert(['f_name' => 'OrderE2E', 'l_name' => 'Fixture',
        'email' => 'order.e2e@urbangoodz.test', 'phone' => PHONE, 'password' => Hash::make(CPW),
        'status' => 1, 'is_phone_verified' => 1, 'created_at' => now(), 'updated_at' => now()]);
}
DB::table('vendors')->where('id', $vendor->id)->update(['password' => Hash::make(VPW)]);
DB::table('delivery_men')->where('id', $dm->id)->update(['password' => Hash::make(DPW)]);

$HDR = ['moduleId: ' . $store->module_id, 'zoneId: [' . $store->zone_id . ']', 'X-localization: en'];

$r = api('POST', '/auth/login', ['email_or_phone' => PHONE, 'field_type' => 'phone',
    'password' => CPW, 'login_type' => 'manual'], $HDR);
$cAuth = array_merge($HDR, ['Authorization: Bearer ' . ($r['json']['token'] ?? '')]);
if (!($r['json']['token'] ?? null)) { fwrite(STDERR, "customer login failed: " . err($r) . "\n"); exit(1); }

$r = api('POST', '/auth/vendor/login', ['email' => $vendor->email, 'password' => VPW, 'vendor_type' => 'owner'], $HDR);
$vAuth = ($r['json']['token'] ?? null)
    ? array_merge($HDR, ['Authorization: Bearer ' . $r['json']['token'], 'vendorType: owner']) : null;

$r = api('POST', '/auth/delivery-man/login', ['phone' => $dm->phone, 'password' => DPW], $HDR);
$dAuth = ($r['json']['token'] ?? null) ? array_merge($HDR, ['Authorization: Bearer ' . $r['json']['token']]) : null;
if ($dAuth && (int) DB::table('delivery_men')->where('id', $dm->id)->value('active') !== 1) {
    api('POST', '/delivery-man/update-active-status', [], $dAuth);
}

printf("logins : customer=%s vendor=%s driver=%s  rider_online=%s\n\n",
    'ok', $vAuth ? 'ok' : 'FAILED', $dAuth ? 'ok' : 'FAILED',
    DB::table('delivery_men')->where('id', $dm->id)->value('active') ? 'yes' : 'no');

// ── Place one order per requested stage ─────────────────────────────────────
$placed = [];
foreach ($stages as $stage) {
    api('DELETE', '/customer/cart/remove', [], $cAuth);
    api('POST', '/customer/cart/add', ['item_id' => (int) $item->id, 'model' => 'Item',
        'price' => (float) $item->price, 'quantity' => 1, 'variation' => [],
        'add_on_ids' => [], 'add_on_qtys' => []], $cAuth);

    $r = api('POST', '/customer/order/place', [
        'order_type' => 'delivery', 'payment_method' => 'cash_on_delivery',
        'store_id' => (int) $store->id, 'distance' => 2.5,
        'address' => '1200 McKinney St, Houston, TX 77010',
        'longitude' => (string) $zone->lng, 'latitude' => (string) $zone->lat,
        'address_type' => 'others', 'contact_person_name' => 'OrderE2E Fixture',
        'contact_person_number' => PHONE, 'road' => 'McKinney', 'house' => '1200', 'floor' => '1',
        'order_amount' => (float) $item->price, 'dm_tips' => 0,
    ], $cAuth);

    $id = $r['json']['order_id'] ?? null;
    if (!$id) { printf("[FAIL] place (%s): %s\n", $stage, err($r)); continue; }

    // Advance to the requested point, exactly as each app would.
    $reached = 'pending';
    if ($stage !== 'pending' && $dAuth) {
        api('PUT', '/delivery-man/accept-order', ['order_id' => $id], $dAuth);
        $reached = orderStatus($id);
    }
    if (in_array($stage, ['picked_up', 'delivered'], true) && $vAuth) {
        foreach (['processing', 'handover'] as $s) {
            api('PUT', '/vendor/update-order-status', ['order_id' => $id, 'status' => $s], $vAuth);
        }
        $reached = orderStatus($id);
    }
    if (in_array($stage, ['picked_up', 'delivered'], true) && $dAuth) {
        api('PUT', '/delivery-man/update-order-status', ['order_id' => $id, 'status' => 'picked_up'], $dAuth);
        $reached = orderStatus($id);
    }
    if ($stage === 'delivered' && $dAuth) {
        api('PUT', '/delivery-man/update-order-status', ['order_id' => $id, 'status' => 'delivered',
            'otp' => DB::table('orders')->where('id', $id)->value('otp')], $dAuth);
        $reached = orderStatus($id);
    }

    $assigned = DB::table('orders')->where('id', $id)->value('delivery_man_id');
    $ok = $stage === 'pending' ? ($reached === 'pending') : ($reached === $stage);
    printf("[%s] order %-7s wanted=%-10s got=%-10s rider=%s\n",
        $ok ? ' OK ' : 'FAIL', $id, $stage, $reached, $assigned ?: '-');
    $placed[] = ['id' => $id, 'stage' => $stage, 'reached' => $reached, 'ok' => $ok, 'dm' => $assigned];
}

// ── What the driver app should now show ─────────────────────────────────────
$running = DB::table('orders')->where('delivery_man_id', $dm->id)
    ->whereIn('order_status', ['accepted', 'confirmed', 'processing', 'handover', 'picked_up'])->count();
$deliveredToday = DB::table('orders')->where('delivery_man_id', $dm->id)
    ->where('order_status', 'delivered')->whereDate('updated_at', now()->toDateString())->count();

printf("\nrider %d now has %d running and %d delivered today\n", $dm->id, $running, $deliveredToday);
printf("driver app login: phone %s / password %s\n", $dm->phone, DPW);

$failed = count(array_filter($placed, fn ($p) => !$p['ok']));
printf("\n%d placed, %d at the wrong stage\n", count($placed), $failed);
exit($failed > 0 ? 1 : 0);
