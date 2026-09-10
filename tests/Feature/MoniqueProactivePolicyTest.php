<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AiMoniqueNotification;
use App\Models\DeliveryMan;
use App\Models\Module;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Zone;
use App\Services\UrbanGoodz\Agent\MoniqueProactiveAttentionService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class MoniqueProactivePolicyTest extends TestCase
{
    private MoniqueProactiveAttentionService $attention;

    protected function setUp(): void
    {
        parent::setUp();
        $this->attention = app(MoniqueProactiveAttentionService::class);
    }

    private function makeAdmin(int $seed): Admin
    {
        return Admin::firstOrCreate(
            ['email' => 'monique-policy-e2e-' . $seed . '@urbangoodz.com'],
            [
                'f_name' => 'Monique',
                'l_name' => 'Policy ' . $seed,
                'phone' => '5556' . str_pad((string) $seed, 4, '0', STR_PAD_LEFT),
                'password' => bcrypt('password'),
                'role_id' => 1,
            ]
        );
    }

    private function sharedZone(): Zone
    {
        return Zone::firstOrCreate(
            ['name' => 'Test Zone E2E'],
            [
                'coordinates' => new \Illuminate\Database\Query\Expression("ST_GeomFromText('POLYGON((0 0, 0 100, 100 100, 100 0, 0 0))')"),
                'status' => 1,
            ]
        );
    }

    private function sharedStore(): Store
    {
        $module = Module::firstOrCreate(
            ['module_name' => 'Food'],
            ['module_type' => 'food', 'status' => 1]
        );

        $vendor = Vendor::firstOrCreate(
            ['email' => 'vendor-e2e@urbangoodz.com'],
            [
                'f_name' => 'Vendor',
                'l_name' => 'E2E',
                'phone' => '2223334444',
                'password' => bcrypt('password'),
                'auth_token' => 'vendor-test-token-e2e',
                'status' => 1,
            ]
        );

        return Store::firstOrCreate(
            ['vendor_id' => $vendor->id],
            [
                'name' => 'E2E Store',
                'phone' => '2223334445',
                'logo' => 'store.png',
                'address' => '456 Vendor Lane',
                'latitude' => 29.7604,
                'longitude' => -95.3698,
                'module_id' => $module->id,
                'zone_id' => $this->sharedZone()->id,
                'status' => 1,
            ]
        );
    }

    private function makeCourier(int $seed): DeliveryMan
    {
        return DeliveryMan::firstOrCreate(
            ['phone' => '7776' . str_pad((string) $seed, 4, '0', STR_PAD_LEFT)],
            [
                'f_name' => 'Courier',
                'l_name' => 'Policy ' . $seed,
                'email' => 'courier-policy-e2e-' . $seed . '@urbangoodz.com',
                'password' => bcrypt('password'),
                'active' => 1,
                'application_status' => 'approved',
                'is_delivery' => 1,
                'current_orders' => 0,
                'zone_id' => $this->sharedZone()->id,
            ]
        );
    }

    private function delayedOrderFor(int $adminId): Order
    {
        $customer = User::firstOrCreate(
            ['email' => 'customer-e2e@urbangoodz.com'],
            ['f_name' => 'Customer', 'l_name' => 'E2E', 'phone' => '5556667777', 'password' => bcrypt('password'), 'is_active' => 1, 'is_verified' => 1]
        );

        $store = $this->sharedStore();

        return Order::create([
            'user_id' => $customer->id,
            'store_id' => $store->id,
            'module_id' => $store->module_id,
            'order_amount' => 42.00,
            'delivery_charge' => 5.00,
            'order_status' => 'pending',
            'zone_id' => $store->zone_id,
            'delivery_address' => '789 Dropoff Lane',
            'payment_method' => 'staged_test',
            'distance' => 2.0,
            'order_type' => 'delivery',
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
        ]);
    }

    private function resetPolicy(): void
    {
        Config::set('urban_goodz_ai.monique_proactive.categories.delayed_orders.enabled', true);
        Config::set('urban_goodz_ai.monique_proactive.categories.delayed_orders.action', 'ask');
        Config::set('urban_goodz_ai.monique_proactive.categories.delayed_orders.max_auto_assign_per_cycle', 1);
    }

    public function test_ask_mode_flags_the_order_and_waits_for_owner(): void
    {
        $this->resetPolicy();
        $admin = $this->makeAdmin(10);
        $order = $this->delayedOrderFor($admin->id);

        $this->attention->observeAndAct('admin', $admin->id);

        $notif = AiMoniqueNotification::forAccount('admin', $admin->id)
            ->where('category', 'delayed_orders')
            ->first();

        $this->assertNotNull($notif);
        $this->assertSame(AiMoniqueNotification::STATUS_PENDING, $notif->status);

        $labels = array_column((array) ($notif->actions ?? []), 'label');
        $this->assertContains('Let Monique Handle It', $labels);

        $this->assertNull($order->fresh()->delivery_man_id, 'ask mode must not assign anything');
    }

    public function test_auto_run_mode_assigns_and_records_a_verified_resolution(): void
    {
        $this->resetPolicy();
        Config::set('urban_goodz_ai.monique_proactive.categories.delayed_orders.action', 'auto_run');

        $admin = $this->makeAdmin(11);
        $this->makeCourier(11);
        $this->delayedOrderFor($admin->id);

        $oldestBefore = $this->oldestUnassignedDelayed();

        $result = $this->attention->observeAndAct('admin', $admin->id);

        $this->assertSame(1, $result['auto_resolved_count']);
        $notif = AiMoniqueNotification::forAccount('admin', $admin->id)
            ->where('category', 'delayed_orders')
            ->first();

        $this->assertNotNull($notif);
        $this->assertTrue((bool) $notif->auto_resolved);
        $this->assertSame(AiMoniqueNotification::STATUS_RESOLVED, $notif->status);

        $this->assertOldestWasAssigned($oldestBefore);
        $this->assertStringNotContainsString('could not', strtolower((string) $notif->resolution_summary));
    }

    public function test_disabled_category_produces_no_notification(): void
    {
        $this->resetPolicy();
        Config::set('urban_goodz_ai.monique_proactive.categories.delayed_orders.enabled', false);

        $admin = $this->makeAdmin(12);
        $this->delayedOrderFor($admin->id);

        $this->attention->observeAndAct('admin', $admin->id);

        $count = AiMoniqueNotification::forAccount('admin', $admin->id)
            ->where('category', 'delayed_orders')
            ->count();

        $this->assertSame(0, $count);
    }

    public function test_let_monique_handle_it_resolves_and_assigns_after_owner_approval(): void
    {
        $this->resetPolicy();
        $admin = $this->makeAdmin(13);
        $this->makeCourier(13);
        $this->delayedOrderFor($admin->id);

        $this->attention->observeAndAct('admin', $admin->id);

        $notif = AiMoniqueNotification::forAccount('admin', $admin->id)
            ->where('category', 'delayed_orders')
            ->first();

        $this->assertNotNull($notif);

        $oldestBefore = $this->oldestUnassignedDelayed();

        $response = $this->attention->handleNotificationAction($notif->id, 'let_monique_handle_it', [
            'admin_id' => $admin->id,
            'actor_role' => 'admin',
        ]);

        $this->assertTrue($response['success']);
        $this->assertTrue($response['verified']);
        $this->assertSame(AiMoniqueNotification::STATUS_RESOLVED, $notif->fresh()->status);

        $this->assertOldestWasAssigned($oldestBefore);
    }

    private function oldestUnassignedDelayed(): ?Order
    {
        return Order::withoutGlobalScopes()
            ->where('order_status', 'pending')
            ->whereNull('delivery_man_id')
            ->where('created_at', '<=', now()->subMinutes(30))
            ->orderBy('created_at')
            ->first();
    }

    /**
     * Asserts the courier write landed on the order that was oldest at the
     * moment Monique acted, assigned to the least-loaded eligible courier.
     */
    private function assertOldestWasAssigned(?Order $oldestBefore): void
    {
        $this->assertNotNull($oldestBefore, 'there should be an unassigned delayed order');

        $expectedCourier = DeliveryMan::query()
            ->withoutGlobalScopes()
            ->where('is_delivery', 1)
            ->where('active', 1)
            ->where('application_status', 'approved')
            ->where(function ($q) {
                $q->whereNull('current_orders')
                    ->orWhere('current_orders', '<', (int) (config('dm_maximum_orders') ?: 1));
            })
            ->orderBy('current_orders')
            ->orderBy('id')
            ->first();

        $this->assertNotNull($expectedCourier, 'there should be an eligible courier');
        $this->assertSame($expectedCourier->id, $oldestBefore->fresh()->delivery_man_id);
    }

    public function test_unknown_category_returns_an_honest_failure(): void
    {
        $admin = $this->makeAdmin(14);

        $notif = AiMoniqueNotification::create([
            'account_type' => 'admin',
            'account_id' => $admin->id,
            'category' => 'something_imaginary',
            'priority' => AiMoniqueNotification::PRIORITY_MEDIUM,
            'title' => 'Imaginary task',
            'message' => 'There is no automated action for this.',
            'is_actionable' => true,
            'can_auto_resolve' => false,
            'auto_resolved' => false,
            'status' => AiMoniqueNotification::STATUS_PENDING,
            'delivered_channels' => ['in_app'],
        ]);

        $response = $this->attention->handleNotificationAction($notif->id, 'let_monique_handle_it', [
            'admin_id' => $admin->id,
            'actor_role' => 'admin',
        ]);

        $this->assertFalse($response['success']);
        $this->assertFalse($response['verified']);
        $this->assertStringContainsString('no automated action', strtolower($response['message']));
        $this->assertSame(AiMoniqueNotification::STATUS_PENDING, $notif->fresh()->status);
    }

    public function test_missing_optional_tables_never_crash_the_observer(): void
    {
        $admin = $this->makeAdmin(15);

        // No exception must be thrown even when the e2e DB has no failed_jobs
        // table (proven by the smoke run that crashed before the guards).
        $result = $this->attention->observeAndAct('admin', $admin->id);

        $this->assertArrayHasKey('observations_total', $result);
        $this->assertArrayHasKey('notifications_created', $result);
    }
}