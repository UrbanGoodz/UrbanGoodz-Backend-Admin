<?php

namespace Tests\Feature;

use App\Models\DeliveryMan;
use App\Models\UrbanGoodzBusinessClient;
use App\Models\UrbanGoodzDedicatedRoute;
use App\Models\UrbanGoodzPackageScan;
use App\Models\UrbanGoodzRouteOperationalMetric;
use App\Models\UrbanGoodzRouteOptimizationStop;
use App\Models\UrbanGoodzRoutePackage;
use App\Services\UrbanGoodz\DedicatedRouteOptimizationService;
use App\Services\UrbanGoodz\PackageScanWorkflowService;
use App\Services\UrbanGoodz\RouteCompletionSettlementService;
use App\Services\UrbanGoodz\Routing\DTOs\DistanceResult;
use App\Services\UrbanGoodz\Routing\Services\DistanceMatrixService;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ConnectedDriverRouteScanWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    private UrbanGoodzBusinessClient $business;
    private DeliveryMan $driver;
    private UrbanGoodzDedicatedRoute $route;
    private UrbanGoodzRoutePackage $firstAtAddress;
    private UrbanGoodzRoutePackage $secondAtAddress;
    private UrbanGoodzRoutePackage $otherAddress;
    private DedicatedRouteOptimizationService $optimizer;
    private PackageScanWorkflowService $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        $distanceMatrix = new class extends DistanceMatrixService {
            public function __construct() {}

            public function getDistance(
                string $originLat,
                string $originLng,
                string $destLat,
                string $destLng
            ): DistanceResult {
                $miles = max(0.25, hypot(
                    (float) $destLat - (float) $originLat,
                    (float) $destLng - (float) $originLng
                ) * 69);
                return DistanceResult::road(
                    round($miles, 3),
                    round($miles * 2, 3),
                    'openrouteservice/matrix'
                );
            }
        };
        $this->optimizer = new DedicatedRouteOptimizationService($distanceMatrix);
        $this->workflow = new PackageScanWorkflowService($this->optimizer);

        $suffix = str_replace('.', '', uniqid('', true));
        $this->business = UrbanGoodzBusinessClient::create([
            'company_name' => "Connected Route {$suffix}",
            'email' => "connected-{$suffix}@urbangoodz.test",
            'status' => 'approved',
        ]);
        $this->driver = $this->driver("assigned-{$suffix}@urbangoodz.test", "71{$suffix}");
        $this->route = UrbanGoodzDedicatedRoute::create([
            'business_client_id' => $this->business->id,
            'route_name' => "Connected Driver Route {$suffix}",
            'route_type' => 'package_routes',
            'source_module' => 'package_routes',
            'pickup_location' => 'Dispatch Hub',
            'pickup_lat' => 29.7500000,
            'pickup_lng' => -95.3600000,
            'end_location' => 'Dispatch Hub',
            'end_lat' => 29.7500000,
            'end_lng' => -95.3600000,
            'return_to_origin' => true,
            'scheduled_date' => now()->toDateString(),
            'status' => 'active',
            'assigned_driver_id' => $this->driver->id,
            'total_packages' => 3,
            'max_packages_per_batch' => 10,
            'capacity_packages' => 10,
            'capacity_weight_lbs' => 100,
        ]);
        $this->firstAtAddress = $this->package(
            "GROUP-A1-{$suffix}",
            '100 Grouped Address',
            29.7600000,
            -95.3700000,
            'medical'
        );
        $this->secondAtAddress = $this->package(
            "GROUP-A2-{$suffix}",
            '100   Grouped Address',
            29.7600000,
            -95.3700000,
            'normal'
        );
        $this->otherAddress = $this->package(
            "GROUP-B-{$suffix}",
            '200 Other Address',
            29.7800000,
            -95.3900000,
            'normal'
        );
    }

    public function test_group_scan_is_atomic_idempotent_and_rejects_wrong_group_or_driver(): void
    {
        $load = $this->workflow->processGroup(
            $this->route,
            $this->driver,
            $this->groupEvent('load', 'load-group-1', [
                $this->firstAtAddress,
                $this->secondAtAddress,
            ])
        );

        $this->assertFalse($load['duplicate']);
        $this->assertSame(2, $load['package_count']);
        $this->assertSame(
            $this->firstAtAddress->deliveryGroupKey(),
            $this->secondAtAddress->deliveryGroupKey()
        );
        $this->assertSame('loaded', $this->firstAtAddress->fresh()->status);
        $this->assertSame('loaded', $this->secondAtAddress->fresh()->status);
        $this->assertSame(2, UrbanGoodzPackageScan::where('dedicated_route_id', $this->route->id)->count());

        $duplicate = $this->workflow->processGroup(
            $this->route,
            $this->driver,
            $this->groupEvent('load', 'load-group-1', [
                $this->firstAtAddress,
                $this->secondAtAddress,
            ])
        );
        $this->assertTrue($duplicate['duplicate']);
        $this->assertSame(2, UrbanGoodzPackageScan::where('dedicated_route_id', $this->route->id)->count());

        try {
            $this->workflow->processGroup($this->route, $this->driver, [
                'action' => 'load',
                'group_idempotency_key' => 'same-package-two-identifiers',
                'input_method' => 'manual',
                'packages' => [
                    [
                        'identifier' => $this->firstAtAddress->barcode,
                        'identifier_type' => 'barcode',
                    ],
                    [
                        'identifier' => $this->firstAtAddress->tracking_id,
                        'identifier_type' => 'tracking_id',
                    ],
                ],
            ]);
            $this->fail('One package must not be scanned twice through alternate identifiers.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('only once', $exception->getMessage());
            $this->assertSame(
                2,
                UrbanGoodzPackageScan::where('dedicated_route_id', $this->route->id)->count()
            );
        }

        UrbanGoodzRoutePackage::whereKey($this->firstAtAddress->id)
            ->update(['status' => 'picked_up']);
        UrbanGoodzRoutePackage::whereKey($this->secondAtAddress->id)
            ->update(['status' => 'pending']);
        $scanCount = UrbanGoodzPackageScan::where('dedicated_route_id', $this->route->id)->count();
        try {
            $this->workflow->processGroup(
                $this->route,
                $this->driver,
                $this->groupEvent('delivery', 'atomic-delivery-group', [
                    $this->firstAtAddress,
                    $this->secondAtAddress,
                ])
            );
            $this->fail('One invalid package transition must roll back the entire group scan.');
        } catch (DomainException) {
            $this->assertSame('picked_up', $this->firstAtAddress->fresh()->status);
            $this->assertSame('pending', $this->secondAtAddress->fresh()->status);
            $this->assertSame(
                $scanCount,
                UrbanGoodzPackageScan::where('dedicated_route_id', $this->route->id)->count()
            );
        }

        try {
            $this->workflow->processGroup(
                $this->route,
                $this->driver,
                $this->groupEvent('pickup', 'mixed-address-group', [
                    $this->firstAtAddress,
                    $this->otherAddress,
                ])
            );
            $this->fail('Mixed delivery addresses must not be accepted as one stop.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('same delivery address', $exception->getMessage());
        }

        $unassigned = $this->driver(
            'unassigned-' . uniqid() . '@urbangoodz.test',
            '72' . str_replace('.', '', uniqid('', true))
        );
        $this->expectException(ModelNotFoundException::class);
        $this->workflow->process(
            $this->route,
            $unassigned,
            $this->event('pickup', $this->firstAtAddress, 'wrong-driver')
        );
    }

    public function test_connected_grouped_route_reoptimizes_failures_and_pays_only_accepted_road_miles(): void
    {
        $this->workflow->processGroup(
            $this->route,
            $this->driver,
            $this->groupEvent('pickup', 'pickup-address-a', [
                $this->firstAtAddress,
                $this->secondAtAddress,
            ])
        );
        $this->workflow->process(
            $this->route,
            $this->driver,
            $this->event('pickup', $this->otherAddress, 'pickup-address-b')
        );

        $plan = $this->optimizer->optimize($this->route->fresh(), true);
        $this->assertSame('ROAD_NETWORK', $plan['distance_mode']);
        $this->assertStringContainsString('openrouteservice', $plan['provider']);
        $this->assertCount(2, $plan['stop_groups']);
        $this->assertSame(
            2,
            UrbanGoodzRouteOptimizationStop::where('dedicated_route_id', $this->route->id)
                ->pluck('group_stop_order')
                ->unique()
                ->count()
        );

        $this->actingAs($this->driver, 'delivery_men');
        $detail = $this->getJson(
            "/api/v1/urban-goodz/driver/routes/{$this->route->id}?token={$this->driver->auth_token}"
        );
        $detail->assertOk()->assertJsonCount(2, 'stops');
        $detail->assertJsonPath('stops.0.package_count', 2);

        $version = (int) $this->route->fresh()->optimization_version;
        $failed = $this->workflow->process(
            $this->route,
            $this->driver,
            array_merge(
                $this->event('fail', $this->otherAddress, 'fail-address-b'),
                ['exception_reason' => 'recipient unavailable']
            )
        );
        $this->assertSame('reoptimized', $failed['reoptimization']['status']);
        $this->assertGreaterThan($version, $failed['reoptimization']['optimization_version']);
        $this->assertSame(2, $failed['reoptimization']['remaining_packages']);
        $this->assertCount(1, $failed['reoptimization']['stop_groups']);

        $returned = $this->workflow->process(
            $this->route->fresh(),
            $this->driver,
            array_merge(
                $this->event('return', $this->otherAddress, 'return-address-b'),
                ['return_destination' => 'business']
            )
        );
        $this->assertSame('reoptimized', $returned['reoptimization']['status']);
        $this->assertSame('returned_to_business', $this->otherAddress->fresh()->status);
        $this->assertCount(1, $returned['reoptimization']['stop_groups']);

        $this->workflow->processGroup(
            $this->route->fresh(),
            $this->driver,
            $this->groupEvent('delivery', 'deliver-address-a', [
                $this->firstAtAddress,
                $this->secondAtAddress,
            ])
        );

        $this->route->refresh()->update([
            'status' => 'completed',
            'route_completed_at' => now(),
        ]);
        $financial = new class {
            public array $context = [];

            public function settle(
                string $sourceType,
                string|int $sourceId,
                array $context,
                string $idempotencyKey
            ): object {
                $this->context = $context;
                return (object) ['id' => 7001];
            }
        };
        app()->instance(
            'App\\Services\\UrbanGoodz\\FinancialControl\\FinancialControlService',
            $financial
        );

        $settled = (new RouteCompletionSettlementService())->captureAndSettle($this->route->fresh());
        $metric = UrbanGoodzRouteOperationalMetric::findOrFail($settled['metric_id']);

        $this->assertSame('settled', $settled['status']);
        $this->assertSame(7001, $settled['settlement_snapshot_id']);
        $this->assertSame('eligible_accepted_road_sequence', $metric->mileage_eligibility);
        $this->assertGreaterThan(0, $metric->eligible_miles_milli);
        $this->assertSame($metric->eligible_miles_milli, $financial->context['miles_milli']);
        $this->assertSame(1, $metric->stop_count);
        $this->assertSame(1, $metric->return_count);
        $this->assertSame(1, $metric->exception_count);
        $this->assertSame(3, $metric->package_count);
    }

    public function test_cancel_reoptimizes_only_the_remaining_address_stops(): void
    {
        $plan = $this->optimizer->optimize($this->route->fresh(), true);
        $this->assertCount(2, $plan['stop_groups']);
        $version = $plan['optimization_version'];

        $canceled = $this->workflow->process(
            $this->route->fresh(),
            $this->driver,
            array_merge(
                $this->event('cancel', $this->otherAddress, 'cancel-address-b'),
                ['exception_reason' => 'business canceled package']
            )
        );

        $this->assertSame('canceled', $this->otherAddress->fresh()->status);
        $this->assertSame('reoptimized', $canceled['reoptimization']['status']);
        $this->assertGreaterThan($version, $canceled['reoptimization']['optimization_version']);
        $this->assertSame(2, $canceled['reoptimization']['remaining_packages']);
        $this->assertCount(1, $canceled['reoptimization']['stop_groups']);
    }

    public function test_haversine_fallback_miles_are_recorded_but_never_sent_as_driver_pay_miles(): void
    {
        $this->route->update([
            'status' => 'completed',
            'route_completed_at' => now(),
            'optimization_version' => 1,
            'optimization_distance_mode' => 'HAVERSINE_FALLBACK',
            'optimization_provider' => 'haversine_fallback',
            'optimized_distance_miles' => 12.345,
        ]);
        $financial = new class {
            public array $context = [];

            public function settle(
                string $sourceType,
                string|int $sourceId,
                array $context,
                string $idempotencyKey
            ): object {
                $this->context = $context;
                return (object) ['id' => 7002];
            }
        };
        app()->instance(
            'App\\Services\\UrbanGoodz\\FinancialControl\\FinancialControlService',
            $financial
        );

        $settled = (new RouteCompletionSettlementService())->captureAndSettle($this->route->fresh());
        $metric = UrbanGoodzRouteOperationalMetric::findOrFail($settled['metric_id']);

        $this->assertSame(12350, $metric->miles_milli);
        $this->assertSame(0, $metric->eligible_miles_milli);
        $this->assertSame('ineligible_non_road_or_unaccepted', $metric->mileage_eligibility);
        $this->assertSame(0, $financial->context['miles_milli']);
        $this->assertSame(12350, $financial->context['measured_miles_milli']);
    }

    public function test_wrong_route_or_business_package_is_never_scanned(): void
    {
        $otherBusiness = UrbanGoodzBusinessClient::create([
            'company_name' => 'Other Connected Business',
            'email' => 'other-' . uniqid() . '@urbangoodz.test',
            'status' => 'approved',
        ]);
        $foreignRoute = UrbanGoodzDedicatedRoute::create([
            'business_client_id' => $otherBusiness->id,
            'route_name' => 'Foreign Route',
            'route_type' => 'package_routes',
            'source_module' => 'package_routes',
            'pickup_location' => 'Foreign Hub',
            'pickup_lat' => 30.0000000,
            'pickup_lng' => -95.0000000,
            'scheduled_date' => now()->toDateString(),
            'status' => 'active',
            'assigned_driver_id' => $this->driver->id,
            'total_packages' => 1,
        ]);
        $foreign = UrbanGoodzRoutePackage::create([
            'dedicated_route_id' => $foreignRoute->id,
            'business_client_id' => $otherBusiness->id,
            'tracking_id' => 'FOREIGN-' . uniqid(),
            'barcode' => 'FOREIGN-BAR-' . uniqid(),
            'dropoff_address' => '999 Foreign Address',
            'dropoff_lat' => 30.0100000,
            'dropoff_lng' => -95.0100000,
            'status' => 'pending',
        ]);

        $this->expectException(ModelNotFoundException::class);
        $this->workflow->process(
            $this->route,
            $this->driver,
            $this->event('pickup', $foreign, 'foreign-package')
        );
    }

    private function driver(string $email, string $phone): DeliveryMan
    {
        return DeliveryMan::create([
            'f_name' => 'Connected',
            'l_name' => 'Driver',
            'phone' => substr($phone, 0, 20),
            'email' => $email,
            'password' => bcrypt('password'),
            'auth_token' => hash('sha256', $email),
        ]);
    }

    private function package(
        string $tracking,
        string $address,
        float $latitude,
        float $longitude,
        string $priority
    ): UrbanGoodzRoutePackage {
        return UrbanGoodzRoutePackage::create([
            'dedicated_route_id' => $this->route->id,
            'business_client_id' => $this->business->id,
            'tracking_id' => $tracking,
            'barcode' => "BAR-{$tracking}",
            'qr_code' => "QR-{$tracking}",
            'dropoff_name' => 'Connected Recipient',
            'dropoff_address' => $address,
            'dropoff_lat' => $latitude,
            'dropoff_lng' => $longitude,
            'delivery_window_start' => now()->startOfDay()->addHours(8),
            'delivery_window_end' => now()->startOfDay()->addHours(17),
            'priority' => $priority,
            'weight' => 5,
            'status' => 'pending',
            'requires_photo' => false,
            'requires_signature' => false,
            'delivery_completion_locked_until_verified' => false,
        ]);
    }

    private function event(
        string $action,
        UrbanGoodzRoutePackage $package,
        string $key
    ): array {
        return [
            'action' => $action,
            'identifier' => $package->barcode,
            'identifier_type' => 'barcode',
            'input_method' => 'barcode',
            'idempotency_key' => $key,
        ];
    }

    private function groupEvent(string $action, string $key, array $packages): array
    {
        return [
            'action' => $action,
            'group_idempotency_key' => $key,
            'input_method' => 'barcode',
            'packages' => array_map(fn ($package) => [
                'identifier' => $package->barcode,
                'identifier_type' => 'barcode',
            ], $packages),
        ];
    }
}
