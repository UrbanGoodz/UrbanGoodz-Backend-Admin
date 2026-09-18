<?php

namespace Tests\Feature;

use App\Models\DeliveryMan;
use App\Models\Module;
use App\Models\Order;
use App\Models\Store;
use App\Models\Vendor;
use App\Models\Zone;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Cross-vendor security coverage for the vendor-owned driver network:
 *
 * 1. VendorDriverManagementController@index never leaks another vendor's
 *    drivers.
 * 2. VendorDriverManagementController@assignOrder / Vendor\OrderController
 *    @assignDriver cannot be used to assign a driver that belongs to a
 *    different vendor.
 * 3. Neither entry point can be used to hijack another vendor's order by
 *    passing its order_id alongside a driver the caller genuinely owns
 *    (the cross-vendor order hijack fixed in
 *    UrbanGoodzDriverNetworkService::assignToBusinessOrder()).
 */
class UrbanGoodzVendorDriverNetworkSecurityTest extends TestCase
{
    use DatabaseTransactions;

    private Zone $zone;
    private Module $module;

    private Vendor $vendorA;
    private Vendor $vendorB;
    private Store $storeA;
    private Store $storeB;
    private DeliveryMan $driverA;
    private DeliveryMan $driverB;
    private Order $orderA;
    private Order $orderB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->zone = Zone::firstOrCreate(
            ['name' => 'Security Test Zone'],
            [
                'coordinates' => new \Illuminate\Database\Query\Expression("ST_GeomFromText('POLYGON((0 0, 0 100, 100 100, 100 0, 0 0))')"),
                'status' => 1,
            ]
        );

        $this->module = Module::firstOrCreate(
            ['module_name' => 'Food'],
            ['module_type' => 'food', 'status' => 1]
        );

        $suffix = Str::random(8);

        $this->vendorA = Vendor::create([
            'f_name' => 'Vendor', 'l_name' => 'A',
            'phone' => '1' . random_int(1000000000, 1999999999),
            'email' => "vendor-a-{$suffix}@example.test",
            'password' => bcrypt('password'),
            'auth_token' => "vendor-a-token-{$suffix}",
            'status' => 1,
        ]);

        $this->vendorB = Vendor::create([
            'f_name' => 'Vendor', 'l_name' => 'B',
            'phone' => '1' . random_int(1000000000, 1999999999),
            'email' => "vendor-b-{$suffix}@example.test",
            'password' => bcrypt('password'),
            'auth_token' => "vendor-b-token-{$suffix}",
            'status' => 1,
        ]);

        $this->storeA = Store::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Store A', 'phone' => '2000000001',
            'logo' => 'store.png', 'address' => '1 A St',
            'latitude' => 29.7, 'longitude' => -95.3,
            'module_id' => $this->module->id, 'zone_id' => $this->zone->id,
            'status' => 1,
        ]);

        $this->storeB = Store::create([
            'vendor_id' => $this->vendorB->id,
            'name' => 'Store B', 'phone' => '2000000002',
            'logo' => 'store.png', 'address' => '1 B St',
            'latitude' => 29.8, 'longitude' => -95.4,
            'module_id' => $this->module->id, 'zone_id' => $this->zone->id,
            'status' => 1,
        ]);

        $this->driverA = DeliveryMan::create([
            'f_name' => 'Driver', 'l_name' => 'A',
            'phone' => '3' . random_int(1000000000, 1999999999),
            'email' => "driver-a-{$suffix}@example.test",
            'password' => bcrypt('password'),
            'zone_id' => $this->zone->id,
            'vendor_id' => $this->vendorA->id,
            'ownership_type' => 'vendor_owned',
            'admin_approval_status' => 'approved',
            'application_status' => 'approved',
            'network_dispatch_status' => 'available',
            'active' => 1,
            'current_orders' => 0,
        ]);

        $this->driverB = DeliveryMan::create([
            'f_name' => 'Driver', 'l_name' => 'B',
            'phone' => '4' . random_int(1000000000, 1999999999),
            'email' => "driver-b-{$suffix}@example.test",
            'password' => bcrypt('password'),
            'zone_id' => $this->zone->id,
            'vendor_id' => $this->vendorB->id,
            'ownership_type' => 'vendor_owned',
            'admin_approval_status' => 'approved',
            'application_status' => 'approved',
            'network_dispatch_status' => 'available',
            'active' => 1,
            'current_orders' => 0,
        ]);

        $this->orderA = Order::factory()->create([
            'store_id' => $this->storeA->id,
            'zone_id' => $this->zone->id,
            'order_status' => 'pending',
            'delivery_man_id' => null,
            'order_amount' => 50,
        ]);

        $this->orderB = Order::factory()->create([
            'store_id' => $this->storeB->id,
            'zone_id' => $this->zone->id,
            'order_status' => 'pending',
            'delivery_man_id' => null,
            'order_amount' => 75,
        ]);
    }

    private function vendorAHeaders(): array
    {
        return [
            'vendorType' => 'owner',
            'Authorization' => 'Bearer ' . $this->vendorA->auth_token,
        ];
    }

    // ---- VendorDriverManagementController@index ------------------------

    public function test_vendor_index_never_returns_another_vendors_drivers(): void
    {
        $response = $this->withHeaders($this->vendorAHeaders())
            ->getJson('/api/v1/urban-goodz/cross-app/ai/vendor/drivers');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($this->driverA->id, $ids);
        $this->assertNotContains($this->driverB->id, $ids);
    }

    // ---- VendorDriverManagementController@assignOrder -------------------

    public function test_api_vendor_cannot_assign_another_vendors_driver(): void
    {
        $response = $this->withHeaders($this->vendorAHeaders())
            ->postJson("/api/v1/urban-goodz/cross-app/ai/vendor/drivers/{$this->driverB->id}/assign", [
                'order_id' => $this->orderA->id,
            ]);

        $response->assertStatus(404);
        $this->assertNull($this->orderA->fresh()->delivery_man_id);
    }

    public function test_api_vendor_cannot_assign_own_driver_to_another_vendors_order(): void
    {
        $response = $this->withHeaders($this->vendorAHeaders())
            ->postJson("/api/v1/urban-goodz/cross-app/ai/vendor/drivers/{$this->driverA->id}/assign", [
                'order_id' => $this->orderB->id,
            ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
        $this->assertNull($this->orderB->fresh()->delivery_man_id);
        $this->assertSame('available', $this->driverA->fresh()->network_dispatch_status);
    }

    // ---- Vendor\OrderController@assignDriver (Blade route) --------------

    public function test_blade_vendor_cannot_assign_another_vendors_driver(): void
    {
        $this->actingAs($this->vendorA, 'vendor')
            ->post(route('vendor.order.assign-driver', ['id' => $this->orderA->id]), [
                'driver_id' => $this->driverB->id,
            ])
            ->assertRedirect();

        $this->assertNull($this->orderA->fresh()->delivery_man_id);
    }

    public function test_blade_vendor_cannot_assign_own_driver_to_another_vendors_order(): void
    {
        $this->actingAs($this->vendorA, 'vendor')
            ->post(route('vendor.order.assign-driver', ['id' => $this->orderB->id]), [
                'driver_id' => $this->driverA->id,
            ])
            ->assertRedirect();

        $this->assertNull($this->orderB->fresh()->delivery_man_id);
        $this->assertSame('available', $this->driverA->fresh()->network_dispatch_status);
    }

    // ---- Order/driver state rules on assign & release -------------------

    private function assignViaApi(int $driverId, int $orderId)
    {
        return $this->withHeaders($this->vendorAHeaders())
            ->postJson("/api/v1/urban-goodz/cross-app/ai/vendor/drivers/{$driverId}/assign", ['order_id' => $orderId]);
    }

    private function releaseViaApi(int $driverId, int $orderId)
    {
        return $this->withHeaders($this->vendorAHeaders())
            ->postJson("/api/v1/urban-goodz/cross-app/ai/vendor/drivers/{$driverId}/release", ['order_id' => $orderId]);
    }

    private function secondDriverForVendorA(): DeliveryMan
    {
        return DeliveryMan::create([
            'f_name' => 'Driver', 'l_name' => 'A2',
            'phone' => '5' . random_int(1000000000, 1999999999),
            'email' => 'driver-a2-' . Str::random(8) . '@example.test',
            'password' => bcrypt('password'),
            'zone_id' => $this->zone->id,
            'vendor_id' => $this->vendorA->id,
            'ownership_type' => 'vendor_owned',
            'admin_approval_status' => 'approved',
            'application_status' => 'approved',
            'network_dispatch_status' => 'available',
            'active' => 1,
            'current_orders' => 0,
        ]);
    }

    public function test_assign_moves_order_to_accepted_and_books_the_driver(): void
    {
        $this->assignViaApi($this->driverA->id, $this->orderA->id)->assertStatus(200);

        $order = $this->orderA->fresh();
        $this->assertSame($this->driverA->id, (int) $order->delivery_man_id);
        $this->assertSame('accepted', $order->order_status);
        $this->assertNotNull($order->accepted);

        $driver = $this->driverA->fresh();
        $this->assertSame('on_business_job', $driver->network_dispatch_status);
        $this->assertSame(1, (int) $driver->current_orders);
    }

    public function test_assign_refuses_a_finished_order_and_leaves_it_finished(): void
    {
        $this->orderA->update(['order_status' => 'delivered']);

        $this->assignViaApi($this->driverA->id, $this->orderA->id)->assertStatus(422);

        $this->assertSame('delivered', $this->orderA->fresh()->order_status);
        $this->assertNull($this->orderA->fresh()->delivery_man_id);
        $this->assertSame('available', $this->driverA->fresh()->network_dispatch_status);
    }

    public function test_assign_refuses_an_order_that_already_has_a_driver(): void
    {
        $this->assignViaApi($this->driverA->id, $this->orderA->id)->assertStatus(200);
        $other = $this->secondDriverForVendorA();

        $this->assignViaApi($other->id, $this->orderA->id)->assertStatus(422);

        $this->assertSame($this->driverA->id, (int) $this->orderA->fresh()->delivery_man_id);
        $this->assertSame('available', $other->fresh()->network_dispatch_status);
        $this->assertSame(0, (int) $other->fresh()->current_orders);
    }

    public function test_assign_refuses_a_take_away_order(): void
    {
        $this->orderA->update(['order_type' => 'take_away']);

        $this->assignViaApi($this->driverA->id, $this->orderA->id)->assertStatus(422);

        $this->assertNull($this->orderA->fresh()->delivery_man_id);
    }

    public function test_driver_finishing_their_only_job_becomes_assignable_again(): void
    {
        $this->assignViaApi($this->driverA->id, $this->orderA->id)->assertStatus(200);

        // What the driver app does when the order is delivered
        // (DeliverymanController::update_order_status).
        $driver = $this->driverA->fresh();
        $driver->current_orders = $driver->current_orders > 1 ? $driver->current_orders - 1 : 0;
        $driver->save();

        $this->assertSame('available', $this->driverA->fresh()->network_dispatch_status);

        $next = Order::factory()->create([
            'store_id' => $this->storeA->id,
            'zone_id' => $this->zone->id,
            'order_status' => 'pending',
            'delivery_man_id' => null,
            'order_amount' => 20,
        ]);
        $this->assignViaApi($this->driverA->id, $next->id)->assertStatus(200);
    }

    public function test_release_refuses_a_driver_who_is_not_on_that_order(): void
    {
        $this->assignViaApi($this->driverA->id, $this->orderA->id)->assertStatus(200);
        $unrelated = Order::factory()->create([
            'store_id' => $this->storeA->id,
            'zone_id' => $this->zone->id,
            'order_status' => 'pending',
            'delivery_man_id' => null,
            'order_amount' => 20,
        ]);

        $this->releaseViaApi($this->driverA->id, $unrelated->id)->assertStatus(422);

        $driver = $this->driverA->fresh();
        $this->assertSame('on_business_job', $driver->network_dispatch_status);
        $this->assertSame(1, (int) $driver->current_orders);
    }

    public function test_release_of_an_open_order_unassigns_it_and_frees_the_driver(): void
    {
        $this->assignViaApi($this->driverA->id, $this->orderA->id)->assertStatus(200);

        $this->releaseViaApi($this->driverA->id, $this->orderA->id)->assertStatus(200);

        $this->assertNull($this->orderA->fresh()->delivery_man_id);
        $driver = $this->driverA->fresh();
        $this->assertSame('available', $driver->network_dispatch_status);
        $this->assertSame(0, (int) $driver->current_orders);
    }

    public function test_assignable_orders_lists_only_this_vendors_open_unassigned_delivery_orders(): void
    {
        $delivered = Order::factory()->create([
            'store_id' => $this->storeA->id, 'zone_id' => $this->zone->id,
            'order_status' => 'delivered', 'delivery_man_id' => null, 'order_amount' => 10,
        ]);
        $takeAway = Order::factory()->create([
            'store_id' => $this->storeA->id, 'zone_id' => $this->zone->id,
            'order_status' => 'pending', 'order_type' => 'take_away', 'delivery_man_id' => null, 'order_amount' => 10,
        ]);

        $response = $this->withHeaders($this->vendorAHeaders())
            ->getJson('/api/v1/urban-goodz/cross-app/ai/vendor/drivers/assignable-orders');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($this->orderA->id, $ids);
        $this->assertNotContains($this->orderB->id, $ids);
        $this->assertNotContains($delivered->id, $ids);
        $this->assertNotContains($takeAway->id, $ids);
    }
}
