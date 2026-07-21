<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\DeliveryMan;
use App\Models\Order;
use App\Models\OrderAnywhereRequest;
use App\Models\Store;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Zone;
use App\Models\AiDispatch;
use App\Models\UrbanGoodzBusinessClient;
use App\Models\UrbanGoodzBusinessClientUser;
use App\Models\UrbanGoodzPaymentLedger;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Laravel\Passport\Passport;
use Tests\TestCase;

class UrbanGoodzOrderAnywhereAiDispatchTest extends TestCase
{
    use DatabaseTransactions;

    private Admin $admin;
    private User $customer;
    private User $anotherCustomer;
    private Vendor $vendor;
    private Store $store;
    private DeliveryMan $driver;
    private Zone $zone;
    private Order $order;
    private OrderAnywhereRequest $orderRequest;
    private UrbanGoodzBusinessClientUser $businessUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Enforce sandbox mode by default
        Config::set('urban_goodz_payments.mode', 'sandbox');
        Config::set('urban_goodz_payments.provider', 'staged_test');
        Config::set('urban_goodz_payments.staged_test.enabled', true);

        // Create testing zone
        $this->zone = Zone::firstOrCreate(
            ['name' => 'Test Zone E2E'],
            [
                'coordinates' => new \Illuminate\Database\Query\Expression("ST_GeomFromText('POLYGON((0 0, 0 100, 100 100, 100 0, 0 0))')"),
                'status' => 1,
            ]
        );

        $module = \App\Models\Module::firstOrCreate(
            ['module_name' => 'Food'],
            [
                'module_type' => 'food',
                'status' => 1,
            ]
        );

        // Create Admin
        $this->admin = Admin::firstOrCreate(
            ['email' => 'admin-e2e@urbangoodz.com'],
            [
                'f_name' => 'Admin',
                'l_name' => 'User',
                'phone' => '1234567891',
                'password' => bcrypt('password'),
                'role_id' => 1,
            ]
        );

        // Create Vendor
        $this->vendor = Vendor::firstOrCreate(
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

        // Create Store
        $this->store = Store::firstOrCreate(
            ['vendor_id' => $this->vendor->id],
            [
                'name' => 'E2E Store',
                'phone' => '2223334445',
                'logo' => 'store.png',
                'address' => '456 Vendor Lane',
                'latitude' => 29.7604,
                'longitude' => -95.3698,
                'module_id' => $module->id,
                'zone_id' => $this->zone->id,
                'status' => 1,
            ]
        );

        // Create Customer
        $this->customer = User::firstOrCreate(
            ['email' => 'customer-e2e@urbangoodz.com'],
            [
                'f_name' => 'Customer',
                'l_name' => 'E2E',
                'phone' => '5556667777',
                'password' => bcrypt('password'),
                'is_active' => 1,
                'is_verified' => 1,
            ]
        );

        // Create Another Customer
        $this->anotherCustomer = User::firstOrCreate(
            ['email' => 'customer2-e2e@urbangoodz.com'],
            [
                'f_name' => 'Another',
                'l_name' => 'Customer',
                'phone' => '5556667778',
                'password' => bcrypt('password'),
                'is_active' => 1,
                'is_verified' => 1,
            ]
        );

        // Create Driver
        $this->driver = DeliveryMan::firstOrCreate(
            ['phone' => '8889990000'],
            [
                'f_name' => 'Driver',
                'l_name' => 'E2E',
                'email' => 'driver-e2e@urbangoodz.com',
                'password' => bcrypt('password'),
                'active' => 1,
                'application_status' => 'approved',
                'zone_id' => $this->zone->id,
                'available_for_order_anywhere' => true,
                'private_endpoint_lat' => 29.7604,
                'private_endpoint_lng' => -95.3698,
                'auth_token' => 'driver-test-token-e2e',
            ]
        );
        $this->driver->auth_token = 'driver-test-token-e2e';
        $this->driver->save();

        // Create Business Client
        $businessClient = UrbanGoodzBusinessClient::firstOrCreate(
            ['company_name' => 'E2E Biz Client'],
            [
                'status' => 'approved',
                'primary_contact_name' => 'John Biz',
                'primary_contact_email' => 'biz-contact@urbangoodz.com',
                'primary_contact_phone' => '1112223333',
            ]
        );

        // Create Business Client User
        $this->businessUser = UrbanGoodzBusinessClientUser::firstOrCreate(
            ['email' => 'business-e2e@urbangoodz.com'],
            [
                'business_client_id' => $businessClient->id,
                'first_name' => 'Biz',
                'last_name' => 'User',
                'phone' => '1113335555',
                'password' => bcrypt('password'),
                'is_active' => true,
                'status' => 'active',
            ]
        );

        // Create Order
        $this->order = new Order();
        $this->order->user_id = $this->customer->id;
        $this->order->store_id = $this->store->id;
        $this->order->module_id = $module->id;
        $this->order->order_amount = 120.00;
        $this->order->delivery_charge = 15.00;
        $this->order->order_status = 'pending';
        $this->order->zone_id = $this->zone->id;
        $this->order->delivery_address = '789 Dropoff Lane';
        $this->order->payment_method = 'staged_test';
        $this->order->distance = 3.5;
        $this->order->save();

        // Create Order Anywhere Request
        $this->orderRequest = OrderAnywhereRequest::create([
            'request_number' => 'OA-E2E-TEST-1',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'status' => 'shopping',
            'quote_amount' => 120.00,
            'final_amount' => 120.00,
            'payment_status' => 'authorized',
            'order_id' => $this->order->id,
            'fulfillment_type' => 'participating_vendor',
        ]);
    }

    public function test_customer_trigger_nearest_driver_success(): void
    {
        Passport::actingAs($this->customer);

        $response = $this->postJson("/api/v1/order-anywhere/orders/{$this->order->id}/dispatch/trigger-nearest", [
            'radius_miles' => 10,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'dispatch' => ['id', 'status', 'uuid'],
                'driver' => ['id', 'name', 'distance_miles'],
            ]);

        $dispatchId = $response->json('dispatch.id');
        $this->assertDatabaseHas('ai_dispatches', [
            'id' => $dispatchId,
            'order_id' => $this->order->id,
            'delivery_man_id' => $this->driver->id,
            'status' => 'sent',
        ]);
    }

    public function test_customer_cannot_access_unowned_dispatch_status(): void
    {
        Passport::actingAs($this->anotherCustomer);

        $response = $this->getJson("/api/v1/order-anywhere/orders/{$this->order->id}/dispatch/status");
        $response->assertStatus(404); // Should fail to find order belonging to another customer
    }

    public function test_customer_dispatch_status_retrieval(): void
    {
        Passport::actingAs($this->customer);

        // Trigger dispatch first
        $this->postJson("/api/v1/order-anywhere/orders/{$this->order->id}/dispatch/trigger-nearest");

        $response = $this->getJson("/api/v1/order-anywhere/orders/{$this->order->id}/dispatch/status");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'order_id',
                'order_status',
                'ai_dispatches',
                'nearby_drivers',
            ]);
    }

    public function test_admin_pending_orders_listing(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->getJson('/api/v1/order-anywhere/admin/pending-orders');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'pagination',
            ]);
    }

    public function test_admin_trigger_nearest_driver(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->postJson("/api/v1/order-anywhere/admin/orders/{$this->order->id}/dispatch/trigger-nearest", [
            'radius_miles' => 20,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => ['dispatch_id', 'delivery_man_id'],
            ]);
    }

    public function test_admin_assign_driver(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->postJson("/api/v1/order-anywhere/admin/orders/{$this->order->id}/dispatch/assign", [
            'driver_id' => $this->driver->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_admin_cancel_dispatch(): void
    {
        $this->actingAs($this->admin, 'admin');

        // First create a dispatch
        $dispatch = AiDispatch::create([
            'order_id' => $this->order->id,
            'delivery_man_id' => $this->driver->id,
            'source_type' => 'order_anywhere',
            'status' => 'sent',
            'uuid' => \Illuminate\Support\Str::uuid(),
            'offer_expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson("/api/v1/order-anywhere/admin/dispatches/{$dispatch->id}/cancel", [
            'reason' => 'Customer requested cancellation',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Dispatch cancelled',
            ]);

        $this->assertDatabaseHas('ai_dispatches', [
            'id' => $dispatch->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_vendor_view_orders_and_dispatches(): void
    {
        $response = $this->withHeaders([
            'vendorType' => 'owner',
            'Authorization' => 'Bearer vendor-test-token-e2e',
        ])->getJson('/api/v1/order-anywhere/vendor/orders');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'pagination',
            ]);
    }

    public function test_business_view_dispatches(): void
    {
        $this->actingAs($this->businessUser, 'business');

        $response = $this->getJson('/business/order-anywhere-dispatch/dispatches');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }

    public function test_driver_accept_and_deliver_flow(): void
    {
        // 1. Create AI Dispatch
        $dispatch = AiDispatch::create([
            'order_id' => $this->order->id,
            'delivery_man_id' => $this->driver->id,
            'source_type' => 'order_anywhere',
            'status' => 'sent',
            'uuid' => \Illuminate\Support\Str::uuid(),
            'offer_expires_at' => now()->addMinutes(10),
        ]);

        // 2. Driver Accepts
        $responseAccept = $this->withHeaders([
            'Authorization' => 'Bearer driver-test-token-e2e',
        ])->postJson("/api/v1/urban-goodz/driver/ai-dispatches/{$dispatch->id}/accept");

        $responseAccept->assertStatus(200)
            ->assertJson([
                'message' => 'Dispatch accepted successfully.',
            ]);

        $this->assertDatabaseHas('ai_dispatches', [
            'id' => $dispatch->id,
            'status' => 'accepted',
        ]);

        $this->order->refresh();
        $this->assertEquals($this->driver->id, $this->order->delivery_man_id);

        // 3. Driver Picked Up / Progress Status
        $dispatch->status = 'picked_up';
        $dispatch->save();

        // 4. Driver Delivers
        $responseDeliver = $this->withHeaders([
            'Authorization' => 'Bearer driver-test-token-e2e',
        ])->postJson("/api/v1/urban-goodz/driver/ai-dispatches/{$dispatch->id}/deliver");

        $responseDeliver->assertStatus(200)
            ->assertJson([
                'message' => 'Delivery confirmed.',
            ]);

        $dispatch->refresh();
        $this->assertEquals('delivered', $dispatch->status);

        $this->order->refresh();
        $this->assertEquals('delivered', $this->order->order_status);
    }
}
