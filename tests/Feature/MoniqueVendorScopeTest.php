<?php

namespace Tests\Feature;

use App\Models\AiMoniqueNotification;
use App\Models\DeliveryMan;
use App\Models\Item;
use App\Models\Module;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Zone;
use App\Services\UrbanGoodz\Agent\AgentToolRegistry;
use App\Services\UrbanGoodz\Agent\ExecutionRouter;
use App\Services\UrbanGoodz\Agent\MoniqueProactiveAttentionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Vendor-side guarantees for Monique's proactive loop.
 *
 * Two things must hold for every vendor account:
 *  1. Tenant isolation - a vendor never observes or acts on another vendor's
 *     orders, and never dispatches a courier it does not control.
 *  2. The vendor's notifications must actually be resolvable. Before the
 *     registry carried the vendor role, every vendor "Let Monique Handle It"
 *     bounced off ExecutionRouter as unauthorized, leaving the card stuck.
 */
class MoniqueVendorScopeTest extends TestCase
{
    // The suite runs against a persistent MySQL test database with no global
    // rollback, so fixtures from earlier runs otherwise accumulate and make
    // "the oldest delayed order" ambiguous. Roll this class back instead.
    use DatabaseTransactions;

    private MoniqueProactiveAttentionService $attention;
    private ExecutionRouter $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->attention = app(MoniqueProactiveAttentionService::class);
        $this->router = app(ExecutionRouter::class);

        Config::set('urban_goodz_ai.monique_proactive.enabled', true);
        Config::set('urban_goodz_ai.monique_proactive.vendor.waiting_order_minutes', 15);
        Config::set('urban_goodz_ai.monique_proactive.vendor.out_of_stock_min_items', 1);
        Config::set('urban_goodz_ai.monique_proactive.vendor.out_of_stock_enabled', true);
        Config::set('urban_goodz_ai.monique_proactive.delayed_order_minutes', 30);
    }

    private function zone(): Zone
    {
        return Zone::firstOrCreate(
            ['name' => 'Test Zone E2E'],
            [
                'coordinates' => new \Illuminate\Database\Query\Expression("ST_GeomFromText('POLYGON((0 0, 0 100, 100 100, 100 0, 0 0))')"),
                'status' => 1,
            ]
        );
    }

    /**
     * Build an isolated vendor that owns exactly one store.
     *
     * @return array{0: Vendor, 1: Store}
     */
    private function vendorWithStore(string $tag): array
    {
        $module = Module::firstOrCreate(
            ['module_name' => 'Food'],
            ['module_type' => 'food', 'status' => 1]
        );

        $vendor = Vendor::firstOrCreate(
            ['email' => "vendor-scope-{$tag}@urbangoodz.com"],
            [
                'f_name' => 'Vendor',
                'l_name' => strtoupper($tag),
                'phone' => '2229' . substr(md5($tag), 0, 6),
                'password' => bcrypt('password'),
                'auth_token' => "vendor-scope-token-{$tag}",
                'status' => 1,
            ]
        );

        $store = Store::firstOrCreate(
            ['vendor_id' => $vendor->id],
            [
                'name' => "Scope Store {$tag}",
                'phone' => '2229' . substr(md5($tag . 's'), 0, 6),
                'logo' => 'store.png',
                'address' => "1 {$tag} Way",
                'latitude' => 29.7604,
                'longitude' => -95.3698,
                'module_id' => $module->id,
                'zone_id' => $this->zone()->id,
                'status' => 1,
            ]
        );

        return [$vendor, $store];
    }

    private function pendingOrderFor(Store $store, int $ageMinutes = 120): Order
    {
        $customer = User::firstOrCreate(
            ['email' => 'customer-scope-e2e@urbangoodz.com'],
            [
                'f_name' => 'Customer',
                'l_name' => 'Scope',
                'phone' => '5551239999',
                'password' => bcrypt('password'),
                'is_active' => 1,
                'is_verified' => 1,
            ]
        );

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
            'created_at' => now()->subMinutes($ageMinutes),
            'updated_at' => now()->subMinutes($ageMinutes),
        ]);
    }

    private function courier(string $tag, ?int $vendorId, bool $marketplace = false): DeliveryMan
    {
        return DeliveryMan::firstOrCreate(
            ['phone' => '7779' . substr(md5($tag), 0, 6)],
            [
                'f_name' => 'Courier',
                'l_name' => strtoupper($tag),
                'email' => "courier-scope-{$tag}@urbangoodz.com",
                'password' => bcrypt('password'),
                'active' => 1,
                'application_status' => 'approved',
                'is_delivery' => 1,
                'current_orders' => 0,
                'zone_id' => $this->zone()->id,
                'vendor_id' => $vendorId,
                'available_for_marketplace' => $marketplace,
            ]
        );
    }

    public function test_registry_authorizes_vendor_to_assign_its_own_courier(): void
    {
        $registry = app(AgentToolRegistry::class);

        $this->assertTrue(
            $registry->isAuthorized('assign_order_courier', 'vendor'),
            'A vendor must be able to dispatch its own backlog, or its notifications are undismissable.'
        );
        $this->assertTrue($registry->isAuthorized('assign_order_courier', 'admin'));

        // Platform-wide inventory stays admin-only: it reports across every store.
        $this->assertFalse($registry->isAuthorized('get_out_of_stock_inventory', 'vendor'));
    }

    public function test_vendor_cannot_dispatch_another_vendors_order(): void
    {
        [$vendorA] = $this->vendorWithStore('a1');
        [, $storeB] = $this->vendorWithStore('b1');

        $foreignOrder = $this->pendingOrderFor($storeB);
        $ownCourier = $this->courier('a1', $vendorA->id);

        $result = $this->router->execute('assign_order_courier', [
            'order_id' => $foreignOrder->id,
            'driver_id' => $ownCourier->id,
        ], [
            'actor_role' => 'vendor',
            'vendor_id' => $vendorA->id,
            'admin_id' => $vendorA->id,
            'confirmed' => true,
        ]);

        $this->assertFalse($result['success'], 'Vendor A must not dispatch another vendor order.');
        $this->assertSame('forbidden_order', $result['error_code'] ?? null);

        $foreignOrder->refresh();
        $this->assertNull($foreignOrder->delivery_man_id, 'The foreign order must be left untouched.');
        $this->assertSame('pending', $foreignOrder->order_status);
    }

    public function test_vendor_cannot_dispatch_a_courier_it_does_not_control(): void
    {
        [$vendorA, $storeA] = $this->vendorWithStore('a2');
        [$vendorB] = $this->vendorWithStore('b2');

        $ownOrder = $this->pendingOrderFor($storeA);
        $foreignCourier = $this->courier('b2', $vendorB->id, false);

        $result = $this->router->execute('assign_order_courier', [
            'order_id' => $ownOrder->id,
            'driver_id' => $foreignCourier->id,
        ], [
            'actor_role' => 'vendor',
            'vendor_id' => $vendorA->id,
            'admin_id' => $vendorA->id,
            'confirmed' => true,
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('forbidden_driver', $result['error_code'] ?? null);

        $ownOrder->refresh();
        $this->assertNull($ownOrder->delivery_man_id);
    }

    public function test_vendor_may_dispatch_a_marketplace_courier_to_its_own_order(): void
    {
        [$vendorA, $storeA] = $this->vendorWithStore('a3');

        $ownOrder = $this->pendingOrderFor($storeA);
        $sharedCourier = $this->courier('shared3', null, true);

        $result = $this->router->execute('assign_order_courier', [
            'order_id' => $ownOrder->id,
            'driver_id' => $sharedCourier->id,
        ], [
            'actor_role' => 'vendor',
            'vendor_id' => $vendorA->id,
            'admin_id' => $vendorA->id,
            'confirmed' => true,
        ]);

        $this->assertTrue($result['success'], $result['message'] ?? 'marketplace dispatch should succeed');
        $this->assertTrue($result['verified'] ?? false, 'The assignment must be verified against the database.');

        $ownOrder->refresh();
        $this->assertSame($sharedCourier->id, (int) $ownOrder->delivery_man_id);
    }

    public function test_admin_dispatch_is_not_narrowed_by_the_vendor_guard(): void
    {
        [, $storeA] = $this->vendorWithStore('a4');
        $order = $this->pendingOrderFor($storeA);
        $courier = $this->courier('admin4', null, false);

        $result = $this->router->execute('assign_order_courier', [
            'order_id' => $order->id,
            'driver_id' => $courier->id,
        ], [
            'actor_role' => 'admin',
            'admin_id' => 1,
            'confirmed' => true,
        ]);

        $this->assertTrue($result['success'], 'Admin reach must stay platform-wide.');

        $order->refresh();
        $this->assertSame($courier->id, (int) $order->delivery_man_id);
    }

    public function test_vendor_handle_it_assigns_only_from_its_own_backlog(): void
    {
        [$vendorA, $storeA] = $this->vendorWithStore('a5');
        [, $storeB] = $this->vendorWithStore('b5');

        // Leftovers from a previous run of this persistent database would make
        // "the oldest delayed order" ambiguous, so start both stores empty.
        // The surrounding DatabaseTransactions rollback undoes this.
        Order::withoutGlobalScopes()
            ->whereIn('store_id', [$storeA->id, $storeB->id])
            ->delete();

        // The other vendor's order is OLDER, so an unscoped "oldest delayed
        // order" lookup would pick it and act across the tenant boundary.
        $foreignOlder = $this->pendingOrderFor($storeB, 300);
        $ownOrder = $this->pendingOrderFor($storeA, 120);

        $courier = $this->courier('a5', $vendorA->id);
        $courier->forceFill(['current_orders' => 0, 'active' => 1])->save();

        $notif = AiMoniqueNotification::create([
            'account_type' => 'vendor',
            'account_id' => $vendorA->id,
            'category' => 'delayed_orders',
            'priority' => AiMoniqueNotification::PRIORITY_HIGH,
            'title' => 'Orders waiting',
            'message' => 'Orders are waiting for acceptance.',
            'status' => AiMoniqueNotification::STATUS_PENDING,
        ]);

        $result = $this->attention->handleNotificationAction($notif->id, 'let_monique_handle_it');

        $this->assertTrue($result['success'], $result['resolution_summary'] ?? 'vendor dispatch should succeed');

        $ownOrder->refresh();
        $foreignOlder->refresh();

        $this->assertNotNull($ownOrder->delivery_man_id, 'Monique should have dispatched the vendor own order.');
        $this->assertNull($foreignOlder->delivery_man_id, 'The other vendor older order must never be touched.');
    }

    public function test_vendor_out_of_stock_resolves_instead_of_returning_unauthorized(): void
    {
        [$vendorA, $storeA] = $this->vendorWithStore('a6');

        Item::firstOrCreate(
            ['name' => 'Scope Sold Out Item', 'store_id' => $storeA->id],
            [
                'module_id' => $storeA->module_id,
                'price' => 9.99,
                'status' => 1,
                'stock' => 0,
                'veg' => 0,
            ]
        );

        $notif = AiMoniqueNotification::create([
            'account_type' => 'vendor',
            'account_id' => $vendorA->id,
            'category' => 'out_of_stock',
            'priority' => AiMoniqueNotification::PRIORITY_MEDIUM,
            'title' => 'Products out of stock',
            'message' => 'Some products are out of stock.',
            'status' => AiMoniqueNotification::STATUS_PENDING,
        ]);

        $result = $this->attention->handleNotificationAction($notif->id, 'let_monique_handle_it');

        $this->assertTrue($result['success'], $result['resolution_summary'] ?? 'vendor inventory action should resolve');
        $this->assertStringNotContainsStringIgnoringCase(
            'not authorized',
            $result['resolution_summary'] ?? '',
            'The vendor branch must not fall through to an admin-only tool.'
        );

        $notif->refresh();
        $this->assertSame(AiMoniqueNotification::STATUS_RESOLVED, $notif->status);
    }
}
