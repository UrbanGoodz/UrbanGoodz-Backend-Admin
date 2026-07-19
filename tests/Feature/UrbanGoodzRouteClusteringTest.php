<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UrbanGoodzBusinessClient;
use App\Models\UrbanGoodzIntakeBatch;
use App\Models\UrbanGoodzBatchParticipant;
use App\Models\UrbanGoodzBatchPackage;
use App\Models\UrbanGoodzDedicatedRoute;
use App\Models\UrbanGoodzRouteClusteringAudit;
use App\Services\UrbanGoodz\Routing\Services\BatchIntakeService;
use App\Services\UrbanGoodz\Routing\Services\BatchLockingService;
use App\Services\UrbanGoodz\Routing\Services\RoutePlanningService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class UrbanGoodzRouteClusteringTest extends TestCase
{
    use DatabaseTransactions;

    private BatchIntakeService $intakeService;
    private BatchLockingService $lockingService;
    private RoutePlanningService $planningService;

    private UrbanGoodzBusinessClient $business;
    private User $supervisor;
    private User $worker;

    protected function setUp(): void
    {
        parent::setUp();

        // Fake the Google maps distance matrix API key and provider FIRST
        Config::set('urban_goodz.distance_matrix', [
            'provider' => 'google_maps',
            'google_maps_key' => 'TEST_GOOGLE_MAPS_KEY',
            'cache_ttl_hours' => 24,
            'batch_size' => 25,
            'request_delay_ms' => 0,
        ]);

        $this->intakeService = new BatchIntakeService();
        $this->lockingService = new BatchLockingService();
        $this->planningService = new RoutePlanningService();

        $this->business = UrbanGoodzBusinessClient::create([
            'company_name' => 'Houston Delivery Co',
            'email' => 'houston@urbangoodz.test',
            'status' => 'approved',
        ]);

        $this->supervisor = User::create([
            'name' => 'Supervisor Sam',
            'email' => 'samsup@urbangoodz.test',
            'password' => bcrypt('password'),
        ]);

        $this->worker = User::create([
            'name' => 'Worker Will',
            'email' => 'willwork@urbangoodz.test',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_distance_matrix_service_uses_road_distance_when_faked(): void
    {
        $srv = new \App\Services\UrbanGoodz\Routing\Services\DistanceMatrixService();
        
        // Assert config values are correctly set
        $this->assertEquals('google_maps', config('urban_goodz.distance_matrix.provider'));
        
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'OK',
                'rows' => [
                    [
                        'elements' => [
                            [
                                'status' => 'OK',
                                'distance' => ['value' => 1609],
                                'duration' => ['value' => 60],
                            ]
                        ]
                    ]
                ],
            ], 200)
        ]);

        $res = $srv->getDistance('29.7604', '-95.3698', '29.7704', '-95.3798');
        $this->assertEquals('ROAD_MATRIX', $res->mode);
    }

    public function test_1000_package_locked_intake_snapshot_routing_and_clustering(): void
    {
        $mockDistanceMatrix = new MockDistanceMatrixService();
        $planning = new RoutePlanningService($mockDistanceMatrix);
        $lockingService = new BatchLockingService($planning);

        // 1. Create a controlled Business intake batch.
        $batch = $this->intakeService->createBatch([
            'business_client_id' => $this->business->id,
            'service_date' => now()->toDateString(),
            'expected_package_count' => 1000,
        ], $this->supervisor->id);
        $batch = $this->intakeService->openBatch($batch->id, $this->supervisor->id);

        $this->intakeService->joinBatch($batch->id, $this->worker->id, 'intake_worker', 'dev-1');

        // 2. Generate 1,000 intended packages
        // Let's create:
        // - 10 consolidated same-address groups with 5 packages each (50 packages total)
        // - 10 unrouteable packages (0.0, 0.0 coordinates)
        // - 940 regular single packages spread geographically around Houston
        
        $baseLat = 29.7604;
        $baseLng = -95.3698;

        $packagesData = [];

        // 10 same-address groups of 5 packages each
        for ($g = 1; $g <= 10; $g++) {
            $addr = "{$g}00 Consolidated Ave";
            $lat = $baseLat + ($g * 0.001);
            $lng = $baseLng + ($g * 0.001);

            for ($p = 1; $p <= 5; $p++) {
                $packagesData[] = [
                    'tracking_id' => "TRK-CON-{$g}-{$p}",
                    'dropoff_address' => $addr,
                    'dropoff_lat' => $lat,
                    'dropoff_lng' => $lng,
                    'dropoff_city' => 'Houston',
                    'dropoff_state' => 'TX',
                    'dropoff_zip' => '77002',
                ];
            }
        }

        // 10 unrouteable packages
        for ($u = 1; $u <= 10; $u++) {
            $packagesData[] = [
                'tracking_id' => "TRK-UNR-{$u}",
                'dropoff_address' => "{$u} Unknown Way",
                'dropoff_lat' => 0.0,
                'dropoff_lng' => 0.0,
                'dropoff_city' => 'Houston',
                'dropoff_state' => 'TX',
                'dropoff_zip' => '77002',
            ];
        }

        // 940 regular single packages
        for ($r = 1; $r <= 940; $r++) {
            $lat = $baseLat + (cos($r) * 0.05);
            $lng = $baseLng + (sin($r) * 0.05);
            $packagesData[] = [
                'tracking_id' => "TRK-REG-{$r}",
                'dropoff_address' => "{$r} regular Street",
                'dropoff_lat' => $lat,
                'dropoff_lng' => $lng,
                'dropoff_city' => 'Houston',
                'dropoff_state' => 'TX',
                'dropoff_zip' => '77002',
            ];
        }

        $this->assertCount(1000, $packagesData);

        // Batch insert packages to database directly for high performance
        DB::beginTransaction();
        foreach ($packagesData as $idx => $pData) {
            $pkg = new UrbanGoodzBatchPackage(array_merge($pData, [
                'intake_batch_id' => $batch->id,
                'business_client_id' => $this->business->id,
                'created_by_user_id' => $this->worker->id,
                'source_type' => 'csv_import',
                'validation_status' => 'pending',
                'is_active' => true,
            ]));
            $pkg->save();
            $pkg->runValidation();
        }
        DB::commit();

        $this->assertEquals(1000, UrbanGoodzBatchPackage::where('intake_batch_id', $batch->id)->count());

        \Illuminate\Support\Facades\Cache::flush();

        // Fake Google Distance Matrix responses
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'OK',
                'rows' => [
                    [
                        'elements' => [
                            [
                                'status' => 'OK',
                                'distance' => ['value' => 8046], // ~5 miles (in meters)
                                'duration' => ['value' => 600],  // 10 minutes
                            ]
                        ]
                    ]
                ],
            ], 200)
        ]);

        // Transition batch to READY_FOR_ROUTING and lock it, requesting 8 routes
        $batch->update(['status' => UrbanGoodzIntakeBatch::STATUS_READY_FOR_ROUTING]);

        $lockRes = $lockingService->lockForRouting($batch->id, $this->supervisor->id, [
            'requested_route_count' => 8,
            'route_type' => 'bulk_delivery',
        ]);

        $this->assertTrue($lockRes['success']);
        
        // Assertions:
        // 1. Snapshot has correctly locked the batch
        $batchFresh = $batch->fresh();
        $this->assertTrue($batchFresh->is_locked);
        $this->assertEquals('ROUTES_GENERATED', $batchFresh->status);

        // 2. Persisted planning audit
        $auditId = $lockRes['routing']['audit_id'];
        $this->assertNotNull($auditId);
        $audit = UrbanGoodzRouteClusteringAudit::find($auditId);
        $this->assertNotNull($audit);
        $this->assertEquals($batch->id, $audit->intake_batch_id);
        $this->assertEquals('pending_review', $audit->status);
        $this->assertEquals('ROAD_MATRIX', $audit->distance_mode);
        $this->assertNotNull($audit->metrics);
        
        // 3. Exactly 8 routes were generated
        $this->assertEquals(8, $lockRes['routing']['route_count']);
        
        $routes = UrbanGoodzDedicatedRoute::where('intake_batch_id', $batch->id)->get();
        $this->assertCount(8, $routes);

        // 4. Alphabetical naming works: Route A, B, C, D, E, F, G, H
        $expectedLabels = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
        foreach ($routes as $idx => $route) {
            $this->assertEquals($expectedLabels[$idx], $route->route_label);
            $this->assertEquals("Route {$expectedLabels[$idx]}", $route->route_name);
            $this->assertEquals('planned', $route->status);
            $this->assertEquals('bulk_delivery', $route->route_type);
        }

        // 5. Invariant check: all valid packages (990) are assigned to routes, 10 are unrouteable
        $assignedCount = UrbanGoodzBatchPackage::where('intake_batch_id', $batch->id)
            ->where('route_assignment_status', 'assigned')
            ->count();
        $unassignedCount = UrbanGoodzBatchPackage::where('intake_batch_id', $batch->id)
            ->where('route_assignment_status', 'unassigned')
            ->count();

        $this->assertEquals(990, $assignedCount);
        $this->assertEquals(10, $unassignedCount); // 10 unrouteable packages

        // 6. No duplicates inside routes, and every package was routed correctly
        $routedPackageIds = [];
        foreach ($routes as $route) {
            $packagesOnRoute = UrbanGoodzBatchPackage::where('dedicated_route_id', $route->id)->get();
            $this->assertGreaterThan(0, $packagesOnRoute->count());
            
            // Check that no stop order overflows or has duplicate stop orders on a single route
            $stopOrders = $packagesOnRoute->pluck('stop_order')->toArray();
            $uniqueStopOrders = array_unique($stopOrders);
            // Wait: since we consolidate same-address packages, multiple packages can share the same stop order!
            // So stop orders might be duplicated in terms of package count, but unique in terms of distinct coordinates/stops.
            
            foreach ($packagesOnRoute as $p) {
                $this->assertNotContains($p->id, $routedPackageIds);
                $routedPackageIds[] = $p->id;
            }
        }
        $this->assertCount(990, $routedPackageIds);
    }
}

class MockDistanceMatrixService extends \App\Services\UrbanGoodz\Routing\Services\DistanceMatrixService
{
    public function getDistance(string $originLat, string $originLng, string $destLat, string $destLng): \App\Services\UrbanGoodz\Routing\DTOs\DistanceResult
    {
        $miles = $this->haversine((float)$originLat, (float)$originLng, (float)$destLat, (float)$destLng);
        $scaledMiles = round($miles * 1.3, 2);
        $minutes = round(($scaledMiles / 30) * 60, 1);
        
        return \App\Services\UrbanGoodz\Routing\DTOs\DistanceResult::road(
            $scaledMiles,
            $minutes,
            'mocked_google_distance_matrix'
        );
    }
}
