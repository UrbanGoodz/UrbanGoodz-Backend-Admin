<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\DeliveryMan;
use App\Models\UrbanGoodzBusinessClient;
use App\Models\UrbanGoodzIntakeBatch;
use App\Models\UrbanGoodzBatchPackage;
use App\Models\UrbanGoodzDedicatedRoute;
use App\Models\UrbanGoodzRoutePackage;
use App\Models\UrbanGoodzRouteOptimizationStop;
use App\Models\UrbanGoodzRouteExecutionVersion;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class UrbanGoodzDriverSequencingTest extends TestCase
{
    use DatabaseTransactions;

    private UrbanGoodzBusinessClient $business;
    private DeliveryMan $driver;
    private UrbanGoodzIntakeBatch $batch;
    private UrbanGoodzDedicatedRoute $route;
    private UrbanGoodzRoutePackage $pkg1;
    private UrbanGoodzRoutePackage $pkg2;
    private UrbanGoodzRoutePackage $pkg3;
    private string $authToken = 'TEST_DRIVER_TOKEN';

    protected function setUp(): void
    {
        parent::setUp();

        // Configure fake distance matrix
        Config::set('urban_goodz.distance_matrix', [
            'provider' => 'google_maps',
            'google_maps_key' => 'TEST_GOOGLE_MAPS_KEY',
            'cache_ttl_hours' => 24,
            'batch_size' => 25,
            'request_delay_ms' => 0,
        ]);

        $this->business = UrbanGoodzBusinessClient::create([
            'company_name' => 'Houston Delivery Co',
            'email' => 'houston@urbangoodz.test',
            'status' => 'approved',
        ]);

        $this->driver = DeliveryMan::create([
            'f_name' => 'Driver',
            'l_name' => 'Dan',
            'phone' => '1234567890',
            'email' => 'driverdan@urbangoodz.test',
            'password' => bcrypt('password'),
            'private_endpoint_address' => '123 Home Rd, Houston, TX 77001',
            'private_endpoint_lat' => 29.7500000,
            'private_endpoint_lng' => -95.3600000,
            'private_endpoint_status' => 'approved',
            'auth_token' => $this->authToken,
        ]);

        $this->batch = UrbanGoodzIntakeBatch::create([
            'business_client_id' => $this->business->id,
            'batch_name' => 'Test Batch',
            'service_date' => now()->toDateString(),
            'status' => 'ready',
        ]);

        $this->route = UrbanGoodzDedicatedRoute::create([
            'business_client_id' => $this->business->id,
            'intake_batch_id' => $this->batch->id,
            'route_name' => 'Route A',
            'route_label' => 'A',
            'total_packages' => 3,
            'estimated_miles' => 10.0,
            'estimated_duration' => 30,
            'scheduled_date' => now()->toDateString(),
            'route_type' => 'bulk_delivery',
            'status' => 'planned',
            'assigned_driver_id' => $this->driver->id,
            'pickup_lat' => 29.7600000,
            'pickup_lng' => -95.3600000,
            'pickup_location' => 'Pickup Hub',
            'end_lat' => 29.7600000,
            'end_lng' => -95.3600000,
            'end_location' => 'Pickup Hub',
            'route_offer_amount' => 150.00,
        ]);

        $this->pkg1 = UrbanGoodzRoutePackage::create([
            'dedicated_route_id' => $this->route->id,
            'business_client_id' => $this->business->id,
            'tracking_id' => 'TRK-D-1',
            'barcode' => 'BAR-D-1',
            'dropoff_name' => 'Alice',
            'dropoff_address' => '100 Main St',
            'dropoff_lat' => 29.7700000,
            'dropoff_lng' => -95.3700000,
            'status' => 'pending',
            'stop_order' => 1,
            'delivery_completion_locked_until_verified' => false,
            'age_restricted' => false,
            'delivery_window_start' => now()->toDateString() . ' 08:00:00',
            'delivery_window_end' => now()->toDateString() . ' 17:00:00',
        ]);

        $this->pkg2 = UrbanGoodzRoutePackage::create([
            'dedicated_route_id' => $this->route->id,
            'business_client_id' => $this->business->id,
            'tracking_id' => 'TRK-D-2',
            'barcode' => 'BAR-D-2',
            'dropoff_name' => 'Bob',
            'dropoff_address' => '200 Main St',
            'dropoff_lat' => 29.7800000,
            'dropoff_lng' => -95.3800000,
            'status' => 'pending',
            'stop_order' => 2,
            'delivery_completion_locked_until_verified' => true, // locked!
            'age_restricted' => false,
            'delivery_window_start' => now()->toDateString() . ' 08:00:00',
            'delivery_window_end' => now()->toDateString() . ' 17:00:00',
        ]);

        $this->pkg3 = UrbanGoodzRoutePackage::create([
            'dedicated_route_id' => $this->route->id,
            'business_client_id' => $this->business->id,
            'tracking_id' => 'TRK-D-3',
            'barcode' => 'BAR-D-3',
            'dropoff_name' => 'Charlie',
            'dropoff_address' => '300 Main St',
            'dropoff_lat' => 29.7900000,
            'dropoff_lng' => -95.3900000,
            'status' => 'pending',
            'stop_order' => 3,
            'delivery_completion_locked_until_verified' => false,
            'age_restricted' => false,
            'delivery_window_start' => now()->toDateString() . ' 08:00:00',
            'delivery_window_end' => now()->toDateString() . ' 17:00:00',
        ]);

        UrbanGoodzRouteOptimizationStop::create([
            'dedicated_route_id' => $this->route->id,
            'package_id' => $this->pkg1->id,
            'stop_order' => 1,
            'estimated_distance_from_prev' => 1.0,
            'estimated_duration_from_prev' => 5,
        ]);

        UrbanGoodzRouteOptimizationStop::create([
            'dedicated_route_id' => $this->route->id,
            'package_id' => $this->pkg2->id,
            'stop_order' => 2,
            'estimated_distance_from_prev' => 1.0,
            'estimated_duration_from_prev' => 5,
        ]);

        UrbanGoodzRouteOptimizationStop::create([
            'dedicated_route_id' => $this->route->id,
            'package_id' => $this->pkg3->id,
            'stop_order' => 3,
            'estimated_distance_from_prev' => 1.0,
            'estimated_duration_from_prev' => 5,
        ]);
    }

    public function test_driver_resequence_no_preference_success(): void
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'OK',
                'rows' => [
                    [
                        'elements' => [
                            [
                                'status' => 'OK',
                                'distance' => ['value' => 1609], // 1 mile
                                'duration' => ['value' => 120], // 2 mins
                            ]
                        ]
                    ]
                ],
            ], 200)
        ]);

        $this->actingAs($this->driver, 'delivery_men');

        $response = $this->postJson("/api/v1/urban-goodz/driver/routes/{$this->route->id}/sequence?token={$this->authToken}", [
            'endpoint_type' => 'no_preference',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'execution_version',
            'miles',
            'duration_minutes',
            'requires_approval',
        ]);

        $this->assertDatabaseHas('urban_goodz_route_execution_versions', [
            'dedicated_route_id' => $this->route->id,
            'driver_id' => $this->driver->id,
            'endpoint_type' => 'no_preference',
            'status' => 'active',
        ]);

        // Original planned metrics remain untouched
        $route = $this->route->fresh();
        $this->assertEquals(10.0, (float)$route->getRawOriginal('estimated_miles'));
    }

    public function test_driver_resequence_company_endpoint_success(): void
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'OK',
                'rows' => [
                    [
                        'elements' => [
                            [
                                'status' => 'OK',
                                'distance' => ['value' => 1609],
                                'duration' => ['value' => 120],
                            ]
                        ]
                    ]
                ],
            ], 200)
        ]);

        $this->actingAs($this->driver, 'delivery_men');

        $response = $this->postJson("/api/v1/urban-goodz/driver/routes/{$this->route->id}/sequence?token={$this->authToken}", [
            'endpoint_type' => 'company_endpoint',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('urban_goodz_route_execution_versions', [
            'dedicated_route_id' => $this->route->id,
            'endpoint_type' => 'company_endpoint',
            'status' => 'active',
        ]);
    }

    public function test_driver_resequence_private_endpoint_fails_if_unapproved(): void
    {
        $this->driver->update(['private_endpoint_status' => 'pending']);
        $this->actingAs($this->driver, 'delivery_men');

        $response = $this->postJson("/api/v1/urban-goodz/driver/routes/{$this->route->id}/sequence?token={$this->authToken}", [
            'endpoint_type' => 'private_endpoint',
        ]);

        $response->assertStatus(400);
        $response->assertJsonFragment(['message' => 'Selected private endpoint is not approved.']);
    }

    public function test_driver_resequence_private_endpoint_success_when_approved(): void
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'OK',
                'rows' => [
                    [
                        'elements' => [
                            [
                                'status' => 'OK',
                                'distance' => ['value' => 1609],
                                'duration' => ['value' => 120],
                            ]
                        ]
                    ]
                ],
            ], 200)
        ]);

        $this->actingAs($this->driver, 'delivery_men');

        $response = $this->postJson("/api/v1/urban-goodz/driver/routes/{$this->route->id}/sequence?token={$this->authToken}", [
            'endpoint_type' => 'private_endpoint',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('urban_goodz_route_execution_versions', [
            'dedicated_route_id' => $this->route->id,
            'endpoint_type' => 'private_endpoint',
            'status' => 'active',
        ]);
    }

    public function test_driver_resequence_preserves_locked_stops(): void
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'OK',
                'rows' => [
                    [
                        'elements' => [
                            [
                                'status' => 'OK',
                                'distance' => ['value' => 1609],
                                'duration' => ['value' => 120],
                            ]
                        ]
                    ]
                ],
            ], 200)
        ]);

        $this->actingAs($this->driver, 'delivery_men');

        $response = $this->postJson("/api/v1/urban-goodz/driver/routes/{$this->route->id}/sequence?token={$this->authToken}", [
            'endpoint_type' => 'no_preference',
        ]);

        $response->assertStatus(200);

        // Bob's package (pkg2) was locked and must be at stop order 1 in the active sequence
        $bobStop = UrbanGoodzRouteOptimizationStop::where('dedicated_route_id', $this->route->id)
            ->where('package_id', $this->pkg2->id)
            ->first();

        $this->assertEquals(1, $bobStop->stop_order);
    }

    public function test_driver_resequence_violates_time_window_fails(): void
    {
        // Give Charlie (pkg3) an extremely tight window that ends before route start time (e.g. 08:00 AM)
        $this->pkg3->update([
            'delivery_window_start' => now()->toDateString() . ' 07:00:00',
            'delivery_window_end' => now()->toDateString() . ' 07:30:00',
        ]);

        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'OK',
                'rows' => [
                    [
                        'elements' => [
                            [
                                'status' => 'OK',
                                'distance' => ['value' => 8046], // 5 miles
                                'duration' => ['value' => 600], // 10 mins
                            ]
                        ]
                    ]
                ],
            ], 200)
        ]);

        $this->actingAs($this->driver, 'delivery_men');

        $response = $this->postJson("/api/v1/urban-goodz/driver/routes/{$this->route->id}/sequence?token={$this->authToken}", [
            'endpoint_type' => 'no_preference',
        ]);

        $response->assertStatus(400);
        $response->assertJsonFragment(['message' => 'Resequencing failed: The optimized stops violate delivery time windows.']);
    }

    public function test_driver_resequence_excessive_variance_requires_approval(): void
    {
        // Original miles is 10.0. Make the faked Google Matrix return a massive distance (e.g. 30 miles), which is > 20% and > 15 miles variance.
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'OK',
                'rows' => [
                    [
                        'elements' => [
                            [
                                'status' => 'OK',
                                'distance' => ['value' => 16093], // 10 miles per leg -> 30 miles total
                                'duration' => ['value' => 1200],
                            ]
                        ]
                    ]
                ],
            ], 200)
        ]);

        $this->actingAs($this->driver, 'delivery_men');

        $response = $this->postJson("/api/v1/urban-goodz/driver/routes/{$this->route->id}/sequence?token={$this->authToken}", [
            'endpoint_type' => 'no_preference',
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['requires_approval' => true]);

        // Verifies route status is now admin_review
        $route = $this->route->fresh();
        $this->assertEquals('admin_review', $route->status);

        // Verifies execution version is pending_approval
        $this->assertDatabaseHas('urban_goodz_route_execution_versions', [
            'dedicated_route_id' => $this->route->id,
            'status' => 'pending_approval',
        ]);

        // Verifies base payout is unchanged
        $this->assertEquals(150.00, (float)$route->route_offer_amount);
    }

    public function test_private_endpoint_is_protected(): void
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'OK',
                'rows' => [
                    [
                        'elements' => [
                            [
                                'status' => 'OK',
                                'distance' => ['value' => 1609],
                                'duration' => ['value' => 120],
                            ]
                        ]
                    ]
                ],
            ], 200)
        ]);

        $this->actingAs($this->driver, 'delivery_men');

        // Apply a private endpoint sequence run
        $this->postJson("/api/v1/urban-goodz/driver/routes/{$this->route->id}/sequence?token={$this->authToken}", [
            'endpoint_type' => 'private_endpoint',
        ]);

        $route = $this->route->fresh();

        // 1. As the assigned driver, reading end_location should return the actual private location
        $this->assertEquals('123 Home Rd, Houston, TX 77001', $route->end_location);

        // 2. Clear authentication and act as a guest or admin
        auth('delivery_men')->logout();

        // Fetch again, should mask it!
        $maskedRoute = UrbanGoodzDedicatedRoute::find($this->route->id);
        $this->assertEquals('Driver Private Location', $maskedRoute->end_location);
        $this->assertEquals(0.0, (float)$maskedRoute->end_lat);
        $this->assertEquals(0.0, (float)$maskedRoute->end_lng);
    }
}
