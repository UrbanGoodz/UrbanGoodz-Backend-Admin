<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AiDispatch;
use App\Models\DeliveryMan;
use App\Models\Module;
use App\Models\Order;
use App\Models\OrderAnywhereRequest;
use App\Models\Store;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Vendor;
use App\Models\Zone;
use App\Observers\OrderAnywhereDispatchTriggerObserver;
use App\Services\OrderAnywhereOrderConversionService;
use Illuminate\Database\Query\Expression;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class UrbanGoodzOrderAnywhereConversionDispatchTest extends TestCase
{
    use DatabaseTransactions;

    private Admin $admin;
    private User $customer;
    private Vendor $vendor;
    private Store $store;
    private DeliveryMan $driver;
    private Zone $zone;
    private Module $module;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('urban_goodz_payments.mode', 'sandbox');
        Config::set('urban_goodz_payments.provider', 'staged_test');
        Config::set('urban_goodz_payments.staged_test.enabled', true);

        $this->zone = Zone::firstOrCreate(
            ['name' => 'Conversion Test Zone'],
            [
                'coordinates' => new Expression("ST_GeomFromText('POLYGON((0 0, 0 100, 100 100, 100 0, 0 0))')"),
                'status' => 1,
            ]
        );

        $this->module = Module::firstOrCreate(
            ['module_name' => 'Food'],
            ['module_type' => 'food', 'status' => 1]
        );

        $this->admin = Admin::firstOrCreate(
            ['email' => 'admin-conv@urbangoodz.com'],
            [
                'f_name' => 'Admin', 'l_name' => 'Conv', 'phone' => '1234567892',
                'password' => bcrypt('password'), 'role_id' => 1,
            ]
        );

        $this->vendor = Vendor::firstOrCreate(
            ['email' => 'vendor-conv@urbangoodz.com'],
            [
                'f_name' => 'Vendor', 'l_name' => 'Conv', 'phone' => '2223334445',
                'password' => bcrypt('password'), 'auth_token' => 'vendor-conv-token', 'status' => 1,
            ]
        );

        $this->store = Store::firstOrCreate(
            ['vendor_id' => $this->vendor->id],
            [
                'name' => 'Conversion Store', 'phone' => '2223334446', 'logo' => 'store.png',
                'address' => '456 Vendor Lane', 'latitude' => 29.7604, 'longitude' => -95.3698,
                'module_id' => $this->module->id, 'zone_id' => $this->zone->id, 'status' => 1,
            ]
        );

        $this->customer = User::firstOrCreate(
            ['email' => 'customer-conv@urbangoodz.com'],
            [
                'f_name' => 'Customer', 'l_name' => 'Conv', 'phone' => '5556667779',
                'password' => bcrypt('password'), 'is_active' => 1, 'is_verified' => 1,
            ]
        );

        $this->driver = DeliveryMan::updateOrCreate(
            ['phone' => '8889990001'],
            [
                'f_name' => 'Driver', 'l_name' => 'Conv', 'email' => 'driver-conv@urbangoodz.com',
                'password' => bcrypt('password'), 'active' => 1, 'application_status' => 'approved',
                'zone_id' => $this->zone->id, 'available_for_order_anywhere' => true,
                'private_endpoint_lat' => 29.7604, 'private_endpoint_lng' => -95.3698,
                'current_orders' => 0,
                'auth_token' => 'driver-conv-token',
            ]
        );
        $this->driver->auth_token = 'driver-conv-token';
        $this->driver->save();
    }

    private function conversion(): OrderAnywhereOrderConversionService
    {
        return app(OrderAnywhereOrderConversionService::class);
    }

    private function makeRequest(array $overrides = []): OrderAnywhereRequest
    {
        return OrderAnywhereRequest::create(array_merge([
            'request_number' => 'OA-CONV-' . uniqid(),
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'status' => 'approved',
            'quote_amount' => 42.50,
            'final_amount' => 42.50,
            'delivery_fee' => 6.00,
            'payment_status' => 'authorized',
            'payment_method' => 'staged_test',
            'fulfillment_type' => 'participating_vendor',
            'store_vendor_name' => 'Conversion Store',
            'metadata' => ['delivery_address' => '789 Dropoff Lane'],
        ], $overrides));
    }

    // ── GAP 1: dispatch trigger ────────────────────────────────────────────

    public function test_request_created_without_order_does_not_dispatch(): void
    {
        $request = $this->makeRequest();

        $this->assertNull($request->order_id);
        $this->assertDatabaseCount('ai_dispatches', 0);
    }

    public function test_conversion_creates_real_order_and_dispatches(): void
    {
        $request = $this->makeRequest();

        $dispatch = $this->conversion()->handleApproved($request->fresh());

        $this->assertNotNull($dispatch);
        $request->refresh();

        // A real Order now backs the request.
        $this->assertNotNull($request->order_id);
        $order = Order::find($request->order_id);
        $this->assertNotNull($order);
        $this->assertEquals($this->customer->id, $order->user_id);
        $this->assertEquals(42.50, (float) $order->order_amount);
        $this->assertEquals('pending', $order->order_status);
        $this->assertEquals($this->store->id, $order->store_id);

        // Pickup coordinates were resolved from the store.
        $this->assertEquals(29.7604, (float) $request->pickup_latitude);
        $this->assertEquals(-95.3698, (float) $request->pickup_longitude);

        // Exactly one dispatch was created, pushed to the nearest driver.
        $this->assertDatabaseHas('ai_dispatches', [
            'id' => $dispatch->id,
            'order_id' => $order->id,
            'delivery_man_id' => $this->driver->id,
            'source_type' => 'order_anywhere',
            'status' => 'sent',
        ]);
        $this->assertNotNull($request->metadata['dispatch_triggered_at'] ?? null);
    }

    public function test_repeated_conversion_does_not_create_duplicate_dispatch(): void
    {
        $request = $this->makeRequest();

        $this->conversion()->handleApproved($request->fresh());
        $this->conversion()->handleApproved($request->fresh());

        $request->refresh();
        $this->assertDatabaseCount('ai_dispatches', 1);
        $this->assertEquals(1, Order::where('user_id', $this->customer->id)->count());
    }

    public function test_conversion_skips_when_driver_manually_assigned(): void
    {
        $request = $this->makeRequest(['assigned_delivery_man_id' => $this->driver->id]);

        $dispatch = $this->conversion()->handleApproved($request->fresh());

        $this->assertNull($dispatch);
        $this->assertDatabaseCount('ai_dispatches', 0);
    }

    public function test_conversion_skips_when_no_nearby_driver(): void
    {
        $this->driver->update([
            'available_for_order_anywhere' => false,
            'private_endpoint_lat' => null,
            'private_endpoint_lng' => null,
        ]);

        $request = $this->makeRequest();

        $dispatch = $this->conversion()->handleApproved($request->fresh());

        $this->assertNull($dispatch);
        $this->assertDatabaseCount('ai_dispatches', 0);
        // The request still converts into a real Order so a retry can dispatch later.
        $this->assertNotNull($request->fresh()->order_id);
    }

    // ── GAP 1: observer plan (pure, testable without commit timing) ────────

    public function test_observer_plan_created(): void
    {
        $plain = $this->makeRequest();
        $this->assertSame(
            ['dispatch' => false, 'convert' => false],
            OrderAnywhereDispatchTriggerObserver::planForCreated($plain)
        );

        $linked = $this->makeRequest(['order_id' => 999]);
        $this->assertSame(
            ['dispatch' => true, 'convert' => false],
            OrderAnywhereDispatchTriggerObserver::planForCreated($linked)
        );
    }

    public function test_observer_plan_updated(): void
    {
        $request = $this->makeRequest();
        $request->status = 'approved';
        $request->syncOriginal();

        $request->order_id = 42;
        $request->syncOriginal();
        $request->order_id = 43;
        $this->assertSame(
            ['dispatch' => true, 'convert' => false],
            OrderAnywhereDispatchTriggerObserver::planForUpdated($request)
        );

        $request->status = 'pending';
        $request->syncOriginal();
        $request->status = 'approved';
        $this->assertSame(
            ['dispatch' => false, 'convert' => true],
            OrderAnywhereDispatchTriggerObserver::planForUpdated($request)
        );
    }

    // ── GAP 3: single push regression ──────────────────────────────────────

    public function test_admin_trigger_nearest_sends_single_offer(): void
    {
        // Create a plain order + linked request the way the E2E flow does.
        $order = Order::create([
            'user_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'module_id' => $this->module->id,
            'order_amount' => 120.00,
            'delivery_charge' => 15.00,
            'order_status' => 'pending',
            'zone_id' => $this->zone->id,
            'delivery_address' => '789 Dropoff Lane',
            'payment_method' => 'staged_test',
            'distance' => 3.5,
            'order_type' => 'delivery',
        ]);
        OrderAnywhereRequest::create([
            'request_number' => 'OA-SINGLE-' . uniqid(),
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'status' => 'pending',
            'payment_status' => 'authorized',
            'fulfillment_type' => 'participating_vendor',
        ]);

        $response = $this->actingAs($this->admin, 'admin')->postJson(
            "/api/v1/order-anywhere/admin/orders/{$order->id}/dispatch/trigger-nearest",
            [
                'radius_miles' => 50,
                'pickup_lat' => 29.7604,
                'pickup_lng' => -95.3698,
            ]
        );

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Exactly one dispatch row for the order...
        $this->assertSame(1, AiDispatch::where('order_id', $order->id)->count());

        $dispatch = AiDispatch::where('order_id', $order->id)->first();
        $this->assertNotNull($dispatch);

        // ...and exactly one in-app notification row (dedupe_key ai_dispatch:{id}).
        $notifs = UserNotification::where('delivery_man_id', $this->driver->id)
            ->get()
            ->filter(fn($n) => data_get($n->data, 'dedupe_key') === "ai_dispatch:{$dispatch->id}");
        $this->assertSame(1, $notifs->count());
    }
}
