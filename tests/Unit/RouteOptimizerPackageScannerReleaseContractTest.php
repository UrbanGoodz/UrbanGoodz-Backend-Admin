<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class RouteOptimizerPackageScannerReleaseContractTest extends TestCase
{
    public function test_every_required_route_source_is_supported(): void
    {
        $source = $this->source('app/Models/UrbanGoodzDedicatedRoute.php');

        foreach ([
            'package_routes', 'business_courier', 'scheduled_route', 'recurring_route',
            'marketplace_delivery', 'retail', 'grocery', 'pharmacy',
            'home_based_business', 'medical_courier', 'stat', 'logistics',
            'load_board', 'order_anywhere', 'rental_pickup_return',
        ] as $routeType) {
            self::assertStringContainsString("'{$routeType}'", $source);
        }
    }

    public function test_optimizer_persists_constraints_history_manual_order_and_restore(): void
    {
        $source = $this->source('app/Services/UrbanGoodz/DedicatedRouteOptimizationService.php');

        foreach ([
            "'fixed_start' => true",
            "'fixed_end'",
            "'return_to_origin'",
            "'locked_stop_count'",
            "'time_windows'",
            "'capacity_packages'",
            "'capacity_weight_lbs'",
            "'pickup_before_dropoff' => true",
            "'medical_priority'",
            "'returns'",
            "'action' => 'manual_order'",
            "'action' => 'restore_original'",
            "'action' => \$nextVersion === 1 ? 'optimize' : 'reoptimize'",
            "'distance_mode' => \$optimizedMetrics['distance_mode']",
            "'optimization_method' => 'manual_order'",
            "'result_distance_miles' => \$metrics['miles']",
        ] as $contract) {
            self::assertStringContainsString($contract, $source);
        }
    }

    public function test_driver_receives_authoritative_sequence_and_cannot_silently_resequence_it(): void
    {
        $source = $this->source('app/Http/Controllers/Api/UrbanGoodzDriverApiController.php');

        self::assertStringContainsString("->orderBy('stop_order')", $source);
        self::assertStringContainsString("'sequence_number' => \$stop->stop_order", $source);
        self::assertStringContainsString('persisted Business/dispatcher sequence is authoritative', $source);
        self::assertStringContainsString("'optimization_distance_mode'", $source);
        self::assertStringContainsString("'optimization_constraints'", $source);
    }

    public function test_scanner_supports_all_inputs_lifecycle_proof_and_offline_idempotency(): void
    {
        $controller = $this->source('app/Http/Controllers/Api/UrbanGoodzDriverPackageScanController.php');
        $workflow = $this->source('app/Services/UrbanGoodz/PackageScanWorkflowService.php');
        $routes = $this->source('routes/api/v1/urban_goodz.php');

        foreach ([
            'barcode,qr_code,tracking_id,manual',
            'load,pickup,delivery,proof,exception,return,redelivery',
            "'idempotency_key' => ['required'",
            "'events' => ['required', 'array'",
            "\$event['was_offline'] = true",
        ] as $contract) {
            self::assertStringContainsString($contract, $controller);
        }
        foreach ([
            "'driver_loading'", "'pickup'", "'dropoff'", "'proof_uploaded'",
            "'exception'", "'return_scan'", "'redelivery'",
            'Photo proof is required',
            'Recipient signature is required',
            'assertPrecedingStopsComplete',
            "'custody_event' => 'dropoff'",
            "'duplicate' => \$duplicate",
            "'optimization_status' => 'reoptimization_required'",
        ] as $contract) {
            self::assertStringContainsString($contract, $workflow);
        }
        self::assertStringContainsString("routes/{routeId}/package-events", $routes);
        self::assertStringContainsString("package-events/sync", $routes);
    }

    public function test_scanner_enforces_driver_and_business_isolation_and_records_admin_history(): void
    {
        $controller = $this->source('app/Http/Controllers/Api/UrbanGoodzDriverPackageScanController.php');
        $workflow = $this->source('app/Services/UrbanGoodz/PackageScanWorkflowService.php');
        $business = $this->source('app/Http/Controllers/Admin/UrbanGoodz/BusinessPortalController.php');
        $admin = $this->source('app/Http/Controllers/Admin/UrbanGoodz/UrbanGoodzDedicatedRouteController.php');

        self::assertStringContainsString("->where('assigned_driver_id', \$driverId)", $controller);
        self::assertStringContainsString("->where('business_client_id', \$route->business_client_id)", $workflow);
        self::assertStringContainsString("UrbanGoodzDedicatedRoute::where('business_client_id', \$clientId)", $business);
        self::assertStringContainsString("UrbanGoodzRoutePackage::where('business_client_id', \$clientId)", $business);
        self::assertStringContainsString("'scan_type' => 'route_assignment'", $business);
        self::assertStringContainsString("'packages.scans'", $admin);
        self::assertStringContainsString("'optimizationHistory'", $admin);
        self::assertStringContainsString("'operationalMetrics'", $admin);
    }

    public function test_route_completion_rejects_unfinished_work_and_connects_verified_metrics_to_compensation(): void
    {
        $driver = $this->source('app/Http/Controllers/Api/UrbanGoodzDriverApiController.php');
        $settlement = $this->source('app/Services/UrbanGoodz/RouteCompletionSettlementService.php');

        self::assertStringContainsString('Every package must be delivered, excepted, or returned', $driver);
        self::assertStringContainsString("'compensation_settlement' => \$settlement", $driver);
        self::assertStringContainsString("'miles_milli' => \$milesMilli", $settlement);
        self::assertStringContainsString("'distance_mode' => \$distanceMode", $settlement);
        self::assertStringContainsString("'package_count' => \$metric->package_count", $settlement);
        self::assertStringContainsString("'stop_count' => \$metric->stop_count", $settlement);
        self::assertStringContainsString("'return_count' => \$metric->return_count", $settlement);
        self::assertStringContainsString("'exception_count' => \$metric->exception_count", $settlement);
        self::assertStringContainsString('dedicated-route:', $settlement);
    }

    public function test_release_schema_provides_auditable_optimization_scanning_and_compensation_records(): void
    {
        $migration = $this->source(
            'database/migrations/2026_07_30_090000_add_route_optimizer_and_package_scan_release_controls.php'
        );

        foreach ([
            'urban_goodz_route_optimization_histories',
            'urban_goodz_route_operational_metrics',
            'ug_route_opt_history_version_uniq',
            'ug_route_metrics_completion_uniq',
            'ug_scan_idempotency_uniq',
            'optimization_distance_mode',
            'optimization_constraints',
            'stop_locked',
            'locked_stop_order',
            'redelivery_attempts',
            'was_offline',
        ] as $contract) {
            self::assertStringContainsString($contract, $migration);
        }
    }

    private function source(string $path): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $path);
        self::assertNotFalse($contents, "Unable to read {$path}");
        return $contents;
    }
}
