<?php
/**
 * End-to-end test of the service-booking module over the real HTTP API:
 * browse -> request -> provider quotes -> customer accepts -> confirm, plus the
 * admin views. This is the "services" side of the business, separate from the
 * order/delivery flow that order-e2e-test.php covers.
 *
 *   php scripts/service-booking-e2e-test.php
 *
 * LOCAL/TEST DATABASES ONLY. Seeds a bookable service if the provider has none,
 * so the run does not silently pass by skipping everything.
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

$pass = 0; $fail = 0;
function step(string $n, bool $ok, string $d = ''): bool {
    global $pass, $fail; $ok ? $pass++ : $fail++;
    printf("[%s] %-46s %s\n", $ok ? ' OK ' : 'FAIL', $n, $d);
    return $ok;
}
function api(string $m, string $p, array $b = [], array $h = []): array {
    $ch = curl_init(BASE . $p);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 45,
        CURLOPT_CUSTOMREQUEST => $m,
        CURLOPT_HTTPHEADER => array_merge(['Accept: application/json', 'Content-Type: application/json'], $h)]);
    if ($b) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($b));
    $raw = (string) curl_exec($ch);
    $c = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $c, 'raw' => $raw, 'json' => json_decode($raw, true)];
}
function err(array $r): string {
    return 'HTTP ' . $r['code'] . ' ' . trim(preg_replace('/\s+/', ' ',
        (string) ($r['json']['errors'][0]['message'] ?? $r['json']['message'] ?? substr(strip_tags($r['raw']), 0, 90))));
}

$provider = DB::table('urban_goodz_service_providers')->where('is_active', 1)->orderBy('id')->first();
if (!$provider) { fwrite(STDERR, "SKIP: no active service provider.\n"); exit(3); }

// The customer endpoints require approval_status=approved AND is_verified AND
// is_active - correct behaviour, an unvetted provider must not be bookable. The
// fixture provider is seeded unverified, so approve it here deliberately rather
// than let every booking step 404 and call that a pass.
if ($provider->approval_status !== 'approved' || !$provider->is_verified) {
    DB::table('urban_goodz_service_providers')->where('id', $provider->id)
        ->update(['approval_status' => 'approved', 'is_verified' => 1, 'updated_at' => now()]);
    $provider = DB::table('urban_goodz_service_providers')->where('id', $provider->id)->first();
    echo "approved + verified the fixture provider so it is bookable\n";
}

// store() rejects anything outside config('service_bookings.categories'), which
// is a different vocabulary from the provider's free-text service_category - so
// seed and repair against that list rather than guessing.
$allowed = (array) config('service_bookings.categories');
$bookableCategory = in_array($provider->service_category, $allowed, true)
    ? $provider->service_category
    : ($allowed[0] ?? 'barber');

$service = DB::table('urban_goodz_provider_services')
    ->where('provider_id', $provider->id)->where('is_active', 1)->first();

if (!$service) {
    $id = DB::table('urban_goodz_provider_services')->insertGetId([
        'provider_id' => $provider->id, 'category' => $bookableCategory,
        'name' => 'Test Service - Standard Visit', 'description' => 'Seeded for end-to-end service booking tests.',
        'duration_minutes' => 60, 'price_minor' => 4500, 'deposit_minor' => 0, 'currency' => 'USD',
        'requires_quote' => 0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $service = DB::table('urban_goodz_provider_services')->where('id', $id)->first();
    echo "seeded a bookable service (id {$id})\n";
}

if (!in_array($service->category, $allowed, true)) {
    DB::table('urban_goodz_provider_services')->where('id', $service->id)
        ->update(['category' => $bookableCategory, 'updated_at' => now()]);
    $service = DB::table('urban_goodz_provider_services')->where('id', $service->id)->first();
    echo "re-categorised the fixture service to a bookable category
";
}

// store() also refuses a time outside the provider's weekly availability. A
// provider with no windows can never be booked, so give the fixture a full
// Mon-Sun 08:00-20:00 schedule rather than have every request 422.
if (DB::table('urban_goodz_provider_availability')->where('provider_id', $provider->id)->count() === 0) {
    for ($day = 0; $day <= 6; $day++) {
        DB::table('urban_goodz_provider_availability')->insert([
            'provider_id' => $provider->id, 'day_of_week' => $day,
            'starts_at' => '08:00:00', 'ends_at' => '20:00:00',
            'timezone' => config('app.timezone') ?: 'UTC', 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
    echo "seeded Mon-Sun 08:00-20:00 availability for the fixture provider\n";
}

printf("provider : %d %s\nservice  : %d %s (%d min)\n\n",
    $provider->id, $provider->business_name, $service->id, $service->name, $service->duration_minutes);

$HDR = ['moduleId: 1', 'zoneId: [1]', 'X-localization: en'];

// ── Public browse ───────────────────────────────────────────────────────────
$r = api('GET', '/customer/service-bookings/categories', [], $HDR);
step('service categories load', $r['code'] === 200, $r['code'] === 200 ? '' : err($r));

$r = api('GET', '/customer/service-bookings/providers', [], $HDR);
step('provider directory loads', $r['code'] === 200, $r['code'] === 200 ? '' : err($r));

$r = api('GET', '/customer/service-bookings/providers/' . $provider->id, [], $HDR);
step('provider profile loads', $r['code'] === 200, $r['code'] === 200 ? '' : err($r));

$start = now()->addDays(2)->setTime(14, 0);
$r = api('GET', sprintf('/customer/service-bookings/providers/%d/services/%d/slots?from=%s&until=%s',
    $provider->id, $service->id, $start->toDateString(), $start->copy()->addDays(3)->toDateString()), [], $HDR);
step('availability slots load', $r['code'] === 200, $r['code'] === 200 ? '' : err($r));

// ── Customer requests a booking ─────────────────────────────────────────────
$u = DB::table('users')->where('phone', PHONE)->first();
if ($u) DB::table('users')->where('id', $u->id)->update(['password' => Hash::make(CPW), 'status' => 1]);

$r = api('POST', '/auth/login', ['email_or_phone' => PHONE, 'field_type' => 'phone',
    'password' => CPW, 'login_type' => 'manual'], $HDR);
$token = $r['json']['token'] ?? null;
if (!step('customer logs in', $token !== null, $token ? '' : err($r))) {
    printf("\n%d passed, %d failed\n", $pass, $fail); exit(1);
}
$AUTH = array_merge($HDR, ['Authorization: Bearer ' . $token]);

// store() checks location_mode against $provider->location_modes, which
// defaults to ['in_person'] when the column is null. Ask for what this provider
// actually offers rather than assuming mobile.
$modes = json_decode((string) ($provider->location_modes ?? ''), true);
$locationMode = is_array($modes) && $modes ? $modes[0] : 'in_person';

$r = api('POST', '/customer/service-bookings', [
    'provider_id' => (int) $provider->id,
    'service_id' => (int) $service->id,
    'requested_start_at' => $start->toIso8601String(),
    'location_mode' => $locationMode,
    'location_details' => '1200 McKinney St, Houston, TX 77010',
    'notes' => 'Placed by service-booking-e2e-test.php',
], $AUTH);

$bookingId = $r['json']['data']['id'] ?? $r['json']['id'] ?? $r['json']['booking']['id'] ?? null;
if (!step('customer requests a booking', $bookingId !== null, $bookingId ? "booking={$bookingId}" : err($r))) {
    echo "\n--- response ---\n" . substr($r['raw'], 0, 500) . "\n";
    printf("\n%d passed, %d failed\n", $pass, $fail); exit(1);
}

$row = DB::table('urban_goodz_service_requests')->where('id', $bookingId)->first();
step('booking row persisted', $row !== null, $row ? "status={$row->status}" : 'missing');

$r = api('GET', '/customer/service-bookings', [], $AUTH);
step('booking appears in customer list', $r['code'] === 200, $r['code'] === 200 ? '' : err($r));

$r = api('GET', '/customer/service-bookings/' . $bookingId, [], $AUTH);
step('customer reads the booking', $r['code'] === 200, $r['code'] === 200 ? '' : err($r));

// ── Provider quotes it ──────────────────────────────────────────────────────
$vendorId = $provider->vendor_id ?: DB::table('vendors')->orderBy('id')->value('id');
$vendor = DB::table('vendors')->where('id', $vendorId)->first();
$VAUTH = null;

if ($vendor) {
    DB::table('vendors')->where('id', $vendor->id)->update(['password' => Hash::make(VPW)]);
    $r = api('POST', '/auth/vendor/login', ['email' => $vendor->email, 'password' => VPW, 'vendor_type' => 'owner'], $HDR);
    $vt = $r['json']['token'] ?? null;
    if (step('provider (vendor) logs in', $vt !== null, $vt ? '' : err($r))) {
        $VAUTH = array_merge($HDR, ['Authorization: Bearer ' . $vt, 'vendorType: owner']);
    }
} else {
    step('provider has a vendor account', false, 'none linked');
}

if ($VAUTH) {
    $r = api('GET', '/vendor/service-bookings/bookings', [], $VAUTH);
    step('provider sees their bookings', $r['code'] === 200, $r['code'] === 200 ? '' : err($r));

    // quote() requires scheduled_at (a future slot the provider is confirming),
    // not just an amount.
    $r = api('POST', "/vendor/service-bookings/bookings/{$bookingId}/quote", [
        'amount_minor' => 5500,
        'scheduled_at' => $start->toIso8601String(),
        'notes' => 'Quoted by service-booking-e2e-test.php',
    ], $VAUTH);
    $quoted = DB::table('urban_goodz_service_requests')->where('id', $bookingId)->value('quoted_amount_minor');
    step('provider quotes the booking', $r['code'] === 200 || $r['code'] === 201,
        ($r['code'] < 300 ? "quoted_minor={$quoted}" : err($r)));
}

// ── Customer accepts the quote ──────────────────────────────────────────────
$r = api('POST', "/customer/service-bookings/{$bookingId}/accept-quote", [], $AUTH);
$after = DB::table('urban_goodz_service_requests')->where('id', $bookingId)->first();
step('customer accepts the quote', $r['code'] < 300, $r['code'] < 300 ? "status={$after->status}" : err($r));

// ── Admin oversight ─────────────────────────────────────────────────────────
foreach ([['dashboard', 'admin dashboard'], ['providers', 'admin provider list'],
          ['bookings', 'admin booking list'], ['earnings', 'admin earnings'], ['audit', 'admin audit log']] as [$path, $label]) {
    $r = api('GET', "/admin/service-bookings/{$path}", [], $HDR);
    // auth:admin - an unauthenticated call must be refused, not crash.
    step($label . ' responds (not 5xx)', $r['code'] < 500, 'HTTP ' . $r['code']);
}

$final = DB::table('urban_goodz_service_requests')->where('id', $bookingId)->first();
printf("\nBOOKING_ID=%d status=%s payment_status=%s quoted=%s\n",
    $bookingId, $final->status, $final->payment_status ?? '-', $final->quoted_amount_minor ?? '-');
printf("%d passed, %d failed\n", $pass, $fail);
exit($fail > 0 ? 1 : 0);
