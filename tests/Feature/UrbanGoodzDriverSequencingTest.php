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

    /**
     * The route sequencer resolves every leg through the Google Distance
     * Matrix provider configured in setUp(). Faking a single-pair response
     * makes every leg cost the same, so total mileage is predictable:
     * legs = start -> stop1 -> stop2 -> stop3 [-> end location].
     */
    private function fakeDistanceMatrix(int $meters, int $seconds): void
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'OK',
                'rows' => [
                    [
                        'elements' => [
                            [
                                'status' => 'OK',
                                'distance' => ['value' => $meters],
                                'duration' => ['value' => $seconds],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);
    }

    private function currentStopOrder(): array
    {
        return UrbanGoodzRouteOptimizationStop::query()
            ->where('dedicated_route_id', $this->route->id)
            ->orderBy('stop_order')
            ->pluck('package_id', 'stop_order')
            ->all();
    }

    private function resequence(string $endpointType)
    {
        $this->actingAs($this->driver, 'delivery_men');

        return $this->postJson(
            "/api/v1/urban-goodz/driver/routes/{$this->route->id}/sequence?token={$this->authToken}",
            ['endpoint_type' => $endpointType]
        );
    }

    /**
     * Regression: the variance gate used to ratchet. It read
     * $route->estimated_miles, an accessor that returns the ACTIVE EXECUTION
     * VERSION's miles once one exists, so the second resequence was measured
     * against the driver's own previous result instead of the dispatcher's
     * baseline. A driver could therefore walk the route arbitrarily far from
     * the dispatch plan in a series of sub-20% steps and never trip
     * admin_review - which is precisely what the gate exists to prevent.
     * The baseline is now read with getRawOriginal('estimated_miles').
     */
    public function test_variance_is_measured_against_the_dispatcher_baseline_not_the_last_accepted_version(): void
    {
        // One closure stub whose distance we change between the two calls:
        // Http::fake() APPENDS stubs rather than replacing them, so calling the
        // fixture helper twice would serve the first distance to both requests.
        $legMeters = 6169;
        Http::fake(function () use (&$legMeters) {
            return Http::response([
                'status' => 'OK',
                'rows' => [['elements' => [[
                    'status' => 'OK',
                    'distance' => ['value' => $legMeters],
                    'duration' => ['value' => 600],
                ]]]],
            ], 200);
        });

        // Baseline is 10.0 miles over three legs. 6169m per leg = 11.50 miles,
        // +15% - under the gate, so this one is accepted and becomes active.
        $this->resequence('no_preference')->assertStatus(200)
            ->assertJsonFragment(['requires_approval' => false]);

        $this->assertDatabaseHas('urban_goodz_route_execution_versions', [
            'dedicated_route_id' => $this->route->id,
            'status' => 'active',
        ]);
        // The accessor now reports the accepted version, which is exactly the
        // value the gate must NOT use as its baseline.
        $this->assertEqualsWithDelta(11.5, (float) $this->route->fresh()->estimated_miles, 0.1);

        // 7081m per leg = 13.20 miles. That is only +14.8% against the accepted
        // 11.50, which the ratcheting version waved through, but +32% against
        // the dispatcher's 10.0 baseline - so it must need approval.
        $legMeters = 7081;
        // DistanceMatrixService caches per coordinate pair (urb_distance_*),
        // and the stops have not moved, so without this the second pass reuses
        // the first pass's distances and never sees the new legs.
        \Illuminate\Support\Facades\Cache::flush();
        $before = $this->currentStopOrder();

        $response = $this->resequence('no_preference');

        $response->assertStatus(200);
        $response->assertJsonFragment(['requires_approval' => true]);
        $this->assertSame($before, $this->currentStopOrder());
        $this->assertEquals('admin_review', $this->route->fresh()->status);
    }

    public function test_driver_resequence_no_preference_is_applied(): void
    {
        $this->fakeDistanceMatrix(1609, 120); // 1 mile / 2 mins per leg

        $response = $this->resequence('no_preference');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'execution_version',
            'miles',
            'duration_minutes',
            'requires_approval',
        ]);
        $response->assertJsonFragment(['requires_approval' => false]);

        $this->assertDatabaseHas('urban_goodz_route_execution_versions', [
            'dedicated_route_id' => $this->route->id,
            'driver_id' => $this->driver->id,
            'endpoint_type' => 'no_preference',
            'status' => 'active',
        ]);

        // The route keeps its dispatcher-planned baseline; the new mileage
        // lives on the execution version, not on the route row.
        $route = $this->route->fresh();
        $this->assertEquals(10.0, (float)$route->getRawOriginal('estimated_miles'));
        $this->assertSame('planned', $route->status);
    }

    public function test_driver_resequence_company_endpoint_is_applied(): void
    {
        $this->fakeDistanceMatrix(1609, 120);

        $response = $this->resequence('company_endpoint');

        $response->assertStatus(200);
        $response->assertJsonFragment(['requires_approval' => false]);
        $this->assertDatabaseHas('urban_goodz_route_execution_versions', [
            'dedicated_route_id' => $this->route->id,
            'endpoint_type' => 'company_endpoint',
            'status' => 'active',
        ]);
    }

    public function test_driver_resequence_private_endpoint_requires_an_approved_endpoint(): void
    {
        $this->driver->update(['private_endpoint_status' => 'pending']);

        $before = $this->currentStopOrder();

        $response = $this->resequence('private_endpoint');

        $response->assertStatus(400);
        $response->assertJsonFragment(['message' => 'Selected private endpoint is not approved.']);

        // The rejection must not echo the endpoint back, and nothing may be
        // committed.
        $response->assertJsonMissing(['private_endpoint_address' => $this->driver->private_endpoint_address]);
        $this->assertStringNotContainsString('123 Home Rd', $response->getContent());
        $this->assertSame($before, $this->currentStopOrder());
        $this->assertSame(
            0,
            UrbanGoodzRouteExecutionVersion::where('dedicated_route_id', $this->route->id)->count()
        );
    }

    public function test_driver_resequence_approved_private_endpoint_is_applied(): void
    {
        $this->fakeDistanceMatrix(1609, 120);

        $response = $this->resequence('private_endpoint');

        $response->assertStatus(200);
        $response->assertJsonFragment(['requires_approval' => false]);
        $this->assertDatabaseHas('urban_goodz_route_execution_versions', [
            'dedicated_route_id' => $this->route->id,
            'endpoint_type' => 'private_endpoint',
            'status' => 'active',
            'private_endpoint_address' => '123 Home Rd, Houston, TX 77001',
        ]);
    }

    public function test_driver_resequence_preserves_locked_stops(): void
    {
        $this->fakeDistanceMatrix(1609, 120);

        $response = $this->resequence('no_preference');

        $response->assertStatus(200);

        // Bob's package (pkg2) is locked, so the sequencer must keep it ahead
        // of every re-orderable stop instead of optimising it away.
        $bobStop = UrbanGoodzRouteOptimizationStop::where('dedicated_route_id', $this->route->id)
            ->where('package_id', $this->pkg2->id)
            ->first();

        $this->assertEquals(1, $bobStop->stop_order);
        $this->assertEquals(1, $this->pkg2->fresh()->stop_order);

        // And the locked stop is the only thing at position 1.
        $this->assertSame(
            [1 => $this->pkg2->id],
            UrbanGoodzRouteOptimizationStop::where('dedicated_route_id', $this->route->id)
                ->where('stop_order', 1)
                ->pluck('package_id', 'stop_order')
                ->all()
        );
    }

    public function test_driver_resequence_is_rejected_when_time_windows_are_violated(): void
    {
        // Give Charlie (pkg3) a window that has already closed before the
        // 08:00 route start, so no ordering can satisfy it.
        $this->pkg3->update([
            'delivery_window_start' => now()->toDateString() . ' 07:00:00',
            'delivery_window_end' => now()->toDateString() . ' 07:30:00',
        ]);

        $this->fakeDistanceMatrix(8046, 600); // 5 miles / 10 mins per leg

        $before = $this->currentStopOrder();

        $response = $this->resequence('no_preference');

        $response->assertStatus(400);
        $response->assertJsonFragment(['message' => 'Resequencing failed: The optimized stops violate delivery time windows.']);

        // Nothing is committed and no execution version is recorded.
        $this->assertSame($before, $this->currentStopOrder());
        $this->assertSame(
            0,
            UrbanGoodzRouteExecutionVersion::where('dedicated_route_id', $this->route->id)->count()
        );
        $this->assertSame('planned', $this->route->fresh()->status);
    }

    public function test_driver_resequence_with_excessive_variance_is_staged_for_approval(): void
    {
        // Baseline is 10.0 miles. 10 miles per leg over the three legs of a
        // no_preference route (start -> s1 -> s2 -> s3) is 30 miles: both
        // >20% and >15 miles of variance, so a dispatcher must confirm it.
        $this->fakeDistanceMatrix(16093, 1200);

        $before = $this->currentStopOrder();

        $response = $this->resequence('no_preference');

        $response->assertStatus(200);
        $response->assertJsonFragment(['requires_approval' => true]);
        $response->assertJsonFragment([
            'message' => 'Resequencing requires dispatcher approval due to excessive variance.',
        ]);

        // The reorder is staged, not applied: the persisted sequence is intact.
        $this->assertSame($before, $this->currentStopOrder());

        $route = $this->route->fresh();
        $this->assertEquals('admin_review', $route->status);

        $this->assertDatabaseHas('urban_goodz_route_execution_versions', [
            'dedicated_route_id' => $this->route->id,
            'status' => 'pending_approval',
        ]);
        $this->assertDatabaseMissing('urban_goodz_route_execution_versions', [
            'dedicated_route_id' => $this->route->id,
            'status' => 'active',
        ]);

        // Base payout is unchanged by a driver-proposed reorder.
        $this->assertEquals(150.00, (float)$route->route_offer_amount);
    }

    public function test_private_endpoint_is_not_exposed_by_resequence_endpoint(): void
    {
        $this->fakeDistanceMatrix(1609, 120);

        $response = $this->resequence('private_endpoint');

        $response->assertStatus(200);

        // The response body never carries the driver's home address or coords.
        $body = $response->getContent();
        $this->assertStringNotContainsString('123 Home Rd', $body);
        $this->assertStringNotContainsString('29.75', $body);
        $response->assertJsonMissing(['private_endpoint_address' => $this->driver->private_endpoint_address]);

        $route = $this->route->fresh();

        // 1. As the assigned driver, reading end_location returns the real
        //    private location.
        $this->assertEquals('123 Home Rd, Houston, TX 77001', $route->end_location);
        $this->assertEquals(29.75, (float)$route->end_lat);
        $this->assertEquals(-95.36, (float)$route->end_lng);

        // 2. To anyone who is not the assigned driver it is masked.
        auth('delivery_men')->logout();

        $maskedRoute = UrbanGoodzDedicatedRoute::find($this->route->id);
        $this->assertEquals('Driver Private Location', $maskedRoute->end_location);
        $this->assertEquals(0.0, (float)$maskedRoute->end_lat);
        $this->assertEquals(0.0, (float)$maskedRoute->end_lng);
    }
}
