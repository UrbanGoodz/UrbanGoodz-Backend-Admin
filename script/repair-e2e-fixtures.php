<?php
/**
 * Idempotent repair for the ISOLATED Playwright e2e database.
 *
 * Fixes fixture drift in one place so the tests/playwright suite describes
 * the actual data:
 *
 *   1. admin_roles id 1 (System Super Admin) with the full literal module
 *      list, and admin_test pointed at it — the dashboard and
 *      Helpers::module_permission_check() both treat role_id == 1 as full
 *      access, and the module check matches exact names, never 'all'.
 *   2. role 9001 modules widened from ["all"] to the real module names for
 *      any other admin attached to it.
 *   3. Business client 9001 renamed to "Smoke Test Company" and marked
 *      account_type=business; client 9002 created as "Dispatch Test Co"
 *      account_type=dispatch_company.
 *   4. Owner users (source fixture + *_test clone) pointed at client 9001
 *      with role owner; dispatcher users pointed at client 9002 with role
 *      dispatch_owner, so BusinessAuthController routes them to the
 *      dispatcher dashboard and the non-dispatch denial test still holds.
 *   5. A small set of FK-safe Order rows against fixture store 9001 /
 *      user 9001 / zone 9001 / module 9001 so the admin orders list and
 *      order-details specs have a real order to render.
 *
 * It never touches password hashes, admin accounts other than
 * admin_test's role_id, or any row outside the fixture id block / test
 * emails. Safe to re-run.
 */

require __DIR__ . '/../vendor/autoload.php';
$a = require __DIR__ . '/../bootstrap/app.php';
$a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order;
use Illuminate\Support\Facades\DB;

$db = DB::connection()->getDatabaseName();
if (app()->environment('production') || ! preg_match('/staging|test/i', (string) $db)) {
    fwrite(STDERR, "Refusing to repair fixtures in database '{$db}' (looks like production).\n");
    exit(1);
}

$modules = [
    'account', 'addon', 'advertisement', 'advertisement_list', 'attribute',
    'banner', 'business_plan', 'business_settings', 'campaign', 'cashback',
    'category', 'chat', 'collect_cash', 'coupon', 'custom_role',
    'customer_management', 'customer_wallet', 'deliveryman',
    'deliveryman_list', 'disbursement_report', 'employee', 'expense_report',
    'item', 'module', 'my_shop', 'notification', 'notification_setup',
    'order', 'parcel', 'pos', 'profile', 'provide_dm_earning', 'report',
    'reviews', 'role', 'settings', 'store', 'store_setup', 'subscription',
    'unit', 'urban_goodz_ai_copilot_use', 'urban_goodz_ai_settings_manage',
    'urban_goodz_ai_settings_view', 'urban_goodz_ai_usage_view',
    'urban_goodz_view', 'user_management', 'vat_report', 'wallet',
    'wallet_method', 'withdraw_list', 'zone',
];

DB::table('admin_roles')->updateOrInsert(
    ['id' => 1],
    ['name' => 'System Super Admin', 'modules' => json_encode($modules), 'status' => 1, 'updated_at' => now()]
);
DB::table('admin_roles')->where('id', 9001)->update(['modules' => json_encode($modules)]);
DB::table('admins')->where('email', 'admin_test@urbangoodz.test')->update(['role_id' => 1]);

DB::table('urban_goodz_business_clients')->updateOrInsert(
    ['id' => 9001],
    ['company_name' => 'Smoke Test Company', 'account_type' => 'business',
     'email' => 'staging.business@fixture.invalid', 'phone' => '+15550009401',
     'status' => 'approved', 'updated_at' => now()]
);
DB::table('urban_goodz_business_clients')->updateOrInsert(
    ['id' => 9002],
    ['company_name' => 'Dispatch Test Co', 'account_type' => 'dispatch_company',
     'email' => 'staging.dispatch@fixture.invalid', 'phone' => '+15550009402',
     'status' => 'approved']
);

foreach (['staging.business.owner@fixture.invalid', 'business_test@urbangoodz.test'] as $e) {
    DB::table('urban_goodz_business_client_users')
        ->where('email', $e)
        ->update(['business_client_id' => 9001, 'role' => 'owner', 'updated_at' => now()]);
}
foreach (['staging.dispatcher@fixture.invalid', 'dispatcher_test@urbangoodz.test'] as $e) {
    DB::table('urban_goodz_business_client_users')
        ->where('email', $e)
        ->update(['business_client_id' => 9002, 'role' => 'dispatch_owner', 'portal_role' => 'dispatcher', 'updated_at' => now()]);
}

// FK-safe order rows for the admin orders list / order details specs.
if (Order::query()->count() === 0) {
    $statuses = ['pending', 'confirmed', 'processing', 'delivered', 'failed'];
    foreach (['pending', 'confirmed', 'processing', 'delivered', 'canceled'] as $i => $status) {
        Order::query()->create([
            'user_id'             => 9001,
            'store_id'            => 9001,
            'zone_id'             => 9001,
            'module_id'           => 9001,
            'order_status'        => $status,
            'order_amount'        => 100 + $i * 25,
            'store_discount_amount' => 0,
            'payment_status'      => $i % 2 ? 'paid' : 'unpaid',
            'transaction_reference' => 'repair-order-' . ($i + 1),
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);
    }
    echo "  seeded 5 FK-safe orders against store 9001 / user 9001\n";
} else {
    echo "  orders already present (" . Order::query()->count() . "), leaving intact\n";
}

echo "  admin_test role_id -> 1\n";
echo "  clients -> Smoke Test Company (business) / Dispatch Test Co (dispatch_company)\n";
echo "  business_test -> client 9001 (owner); dispatcher_test -> client 9002 (dispatch_owner)\n";
echo "fixtures repaired.\n";