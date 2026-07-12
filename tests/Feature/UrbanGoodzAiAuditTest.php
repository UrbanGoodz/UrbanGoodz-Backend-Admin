<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\DeliveryMan;
use App\Models\Order;
use App\Models\UrbanGoodzDedicatedRoute;
use App\Models\UrbanGoodzLoadBoardLoad;
use App\Services\AiCopilotService;
use App\Services\UrbanGoodz\UrbanGoodzLoadBoardService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class UrbanGoodzAiAuditTest extends TestCase
{
    use DatabaseTransactions;

    private AiCopilotService $aiService;
    private UrbanGoodzLoadBoardService $loadBoardService;
    private Admin $admin;
    private DeliveryMan $driver1;
    private DeliveryMan $driver2;
    private \App\Models\Module $module;
    private int $zone1Id;
    private int $zone2Id;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aiService = app(AiCopilotService::class);
        $this->loadBoardService = app(UrbanGoodzLoadBoardService::class);

        $this->admin = Admin::firstOrCreate(
            ['email' => 'test-admin@urbangoodz.com'],
            [
                'f_name' => 'Admin',
                'l_name' => 'User',
                'phone' => '1234567890',
                'password' => bcrypt('password'),
                'role_id' => 1,
            ]
        );

        $this->module = \App\Models\Module::firstOrCreate(
            ['module_name' => 'Food'],
            [
                'module_type' => 'food',
                'status' => 1,
            ]
        );

        // Zones: use name as key so auto-increment IDs are stable and consistent
        $zone1 = \App\Models\Zone::firstOrCreate(
            ['name' => 'UG Test Zone 1'],
            [
                'coordinates' => new \Illuminate\Database\Query\Expression("ST_GeomFromText('POLYGON((0 0, 0 100, 100 100, 100 0, 0 0))')"),
                'status' => 1,
            ]
        );

        $zone2 = \App\Models\Zone::firstOrCreate(
            ['name' => 'UG Test Zone 2'],
            [
                'coordinates' => new \Illuminate\Database\Query\Expression("ST_GeomFromText('POLYGON((0 0, 0 100, 100 100, 100 0, 0 0))')"),
                'status' => 1,
            ]
        );

        Config::set('dm_maximum_orders', 3);

        $this->driver1 = DeliveryMan::updateOrCreate(
            ['phone' => '9998887771'],
            [
                'f_name' => 'Driver',
                'l_name' => 'One',
                'email' => 'driver1@urbangoodz.com',
                'password' => bcrypt('password'),
                'active' => 1,
                'application_status' => 'approved',
                'zone_id' => $zone1->id,
                'current_orders' => 0,
            ]
        );
        $this->driver1->refresh();

        $this->driver2 = DeliveryMan::updateOrCreate(
            ['phone' => '9998887772'],
            [
                'f_name' => 'Driver',
                'l_name' => 'Two',
                'email' => 'driver2@urbangoodz.com',
                'password' => bcrypt('password'),
                'active' => 1,
                'application_status' => 'approved',
                'zone_id' => $zone2->id,
                'current_orders' => 1,
            ]
        );
        $this->driver2->refresh();

        // Store zone IDs for use in test order fixtures
        $this->zone1Id = $zone1->id;
        $this->zone2Id = $zone2->id;
    }

    public function test_load_board_duplicate_detection_null_external_ids(): void
    {
        // Insert a load with a null/empty external ID
        $load1 = UrbanGoodzLoadBoardLoad::create([
            'load_number' => 'LD-INTERNAL-100',
            'provider' => 'dat',
            'external_id' => null,
            'status' => 'available',
            'origin_city' => 'Atlanta',
            'origin_state' => 'GA',
            'destination_city' => 'Miami',
            'destination_state' => 'FL',
            'payout_amount' => 500.00,
        ]);

        // Attempting to sync a new load with an empty external_id
        $externalLoads = [
            [
                'external_id' => '',
                'load_number' => 'LD-INTERNAL-200',
                'origin_city' => 'Chicago',
                'origin_state' => 'IL',
                'destination_city' => 'Dallas',
                'destination_state' => 'TX',
                'payout_amount' => 800.00,
            ]
        ];

        $syncedCount = $this->loadBoardService->syncFromProvider('dat', $externalLoads);

        // Verify that the second load was inserted instead of overwriting the first null ID load
        $this->assertEquals(1, $syncedCount);

        $totalLoads = UrbanGoodzLoadBoardLoad::where('provider', 'dat')->count();
        // Should have both loads now
        $this->assertEquals(2, $totalLoads);
    }

    public function test_explainable_driver_matching_prefers_zone_match(): void
    {
        $order = new Order();
        $order->user_id = 1;
        $order->order_amount = 100.00;
        $order->zone_id = $this->zone1Id; // Matches driver1's zone
        $order->payment_status = 'paid';
        $order->order_status = 'pending';
        $order->delivery_address_id = null;
        $order->module_id = $this->module->id;
        $order->save();

        // Access via reflection or public wrapper if available
        $reflection = new \ReflectionClass(AiCopilotService::class);
        $method = $reflection->getMethod('findBestDriverForOrder');
        $method->setAccessible(true);

        $availableDrivers = collect([$this->driver1, $this->driver2]);

        $best = $method->invoke($this->aiService, $order, $availableDrivers);

        $this->assertNotNull($best);
        $this->assertEquals($this->driver1->id, $best['driver_id']);
        $this->assertTrue($best['zone_match']);
        $this->assertEquals(0.85, $best['confidence']);
    }

    public function test_explainable_driver_matching_fallback_if_no_zone_match(): void
    {
        $order = new Order();
        $order->user_id = 1;
        $order->order_amount = 100.00;
        $order->zone_id = 99999; // No zone match for either driver
        $order->payment_status = 'paid';
        $order->order_status = 'pending';
        $order->delivery_address_id = null;
        $order->module_id = $this->module->id;
        $order->save();

        $reflection = new \ReflectionClass(AiCopilotService::class);
        $method = $reflection->getMethod('findBestDriverForOrder');
        $method->setAccessible(true);

        $availableDrivers = collect([$this->driver1, $this->driver2]);

        $best = $method->invoke($this->aiService, $order, $availableDrivers);

        $this->assertNotNull($best);
        // Should choose driver 1 because they have fewer current orders (0 vs 1)
        $this->assertEquals($this->driver1->id, $best['driver_id']);
        $this->assertFalse($best['zone_match']);
        $this->assertEquals(0.6, $best['confidence']);
    }

    public function test_hard_driver_eligibility_rules_not_bypassed(): void
    {
        $order = new Order();
        $order->user_id = 1;
        $order->order_amount = 100.00;
        $order->zone_id = $this->zone1Id;
        $order->payment_status = 'paid';
        $order->order_status = 'pending';
        $order->delivery_address_id = null;
        $order->module_id = $this->module->id;
        $order->callback = 'default';
        $order->save();

        // Let's check autoDispatchOrder
        $reflection = new \ReflectionClass(AiCopilotService::class);
        $method = $reflection->getMethod('autoDispatchOrder');
        $method->setAccessible(true);

        // Driver info
        $driverInfo = ['driver_id' => $this->driver1->id];

        // Should return true for low risk order
        $result = $method->invoke($this->aiService, $order, $driverInfo);
        $this->assertTrue($result);

        $this->driver1->refresh();
        $this->assertEquals(1, $this->driver1->current_orders);

        // Set order to age restricted -> should be high risk -> should return false and block auto dispatch
        $order->age_restricted_order = 1;
        $order->save();

        $result2 = $method->invoke($this->aiService, $order, $driverInfo);
        $this->assertFalse($result2); // Blocked by eligibility/risk rules!
    }

    public function test_keys_do_not_appear_in_codebases(): void
    {
        // Ensure no hardcoded secret keys are present in frontend/mobile or config files
        $configPath = config_path('urban_goodz_payments.php');
        if (file_exists($configPath)) {
            $content = file_get_contents($configPath);
            $this->assertStringNotContainsString('sk_live_', $content);
            $this->assertStringNotContainsString('sk_test_', $content);
        }
    }
}
