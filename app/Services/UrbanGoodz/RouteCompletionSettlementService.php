<?php

namespace App\Services\UrbanGoodz;

use App\Models\UrbanGoodzDedicatedRoute;
use App\Models\UrbanGoodzRouteOperationalMetric;
use Illuminate\Support\Facades\Log;

class RouteCompletionSettlementService
{
    private const FINANCIAL_SERVICE =
        'App\\Services\\UrbanGoodz\\FinancialControl\\FinancialControlService';

    public function captureAndSettle(UrbanGoodzDedicatedRoute $route): array
    {
        $route->loadMissing('packages', 'optimizationStops');
        $returnCount = $route->packages->whereIn('status', [
            'return_required', 'returning_to_pickup', 'returning_to_hub',
            'returning_to_business', 'returned_to_pickup', 'returned_to_hub',
            'returned_to_business',
        ])->count();
        $exceptionCount = $route->packages()
            ->whereHas('scans', fn ($query) => $query->where('scan_type', 'exception'))
            ->count();
        $milesMilli = (int) round(
            (float) ($route->optimized_distance_miles ?? $route->estimated_miles ?? 0) * 1000
        );
        $completionVersion = max(1, (int) $route->optimization_version);
        $distanceMode = $route->optimization_distance_mode ?: 'HAVERSINE_FALLBACK';

        $metric = UrbanGoodzRouteOperationalMetric::updateOrCreate([
            'dedicated_route_id' => $route->id,
            'completion_version' => $completionVersion,
        ], [
            'driver_id' => $route->assigned_driver_id,
            'business_client_id' => $route->business_client_id,
            'miles_milli' => $milesMilli,
            'package_count' => $route->packages->count(),
            'stop_count' => $route->optimizationStops->count() ?: $route->packages->count(),
            'return_count' => $returnCount,
            'exception_count' => $exceptionCount,
            'duration_minutes' => (int) (
                $route->optimized_duration_minutes ?? $route->estimated_duration ?? 0
            ),
            'distance_mode' => $distanceMode,
            'provider' => $route->optimization_provider,
            'verified_at' => now(),
        ]);

        if (!class_exists(self::FINANCIAL_SERVICE)) {
            return [
                'status' => 'metrics_captured_financial_lane_pending',
                'metric_id' => $metric->id,
            ];
        }

        $context = [
            'merchandise_subtotal_cents' => 0,
            'delivery_charge_cents' => 0,
            'business_id' => (int) $route->business_client_id,
            'provider_id' => (int) $route->business_client_id,
            'driver_id' => (int) $route->assigned_driver_id,
            'service_type' => $route->source_module ?: $route->route_type,
            'zone_id' => null,
            'miles_milli' => $metric->miles_milli,
            'package_count' => $metric->package_count,
            'stop_count' => $metric->stop_count,
            'route_count' => 1,
            'hours_minutes' => $metric->duration_minutes,
            'return_count' => $metric->return_count,
            'exception_count' => $metric->exception_count,
            'currency' => 'USD',
        ];
        $completionTimestamp = $route->route_completed_at?->timestamp ?? $route->updated_at->timestamp;
        $idempotencyKey = "dedicated-route:{$route->id}:completion:{$completionTimestamp}";

        try {
            $snapshot = app(self::FINANCIAL_SERVICE)->settle(
                'dedicated_route',
                (string) $route->id,
                $context,
                $idempotencyKey
            );

            return [
                'status' => 'settled',
                'metric_id' => $metric->id,
                'settlement_snapshot_id' => $snapshot->id,
            ];
        } catch (\Throwable $exception) {
            Log::error('Dedicated route financial settlement failed', [
                'route_id' => $route->id,
                'metric_id' => $metric->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return [
                'status' => 'metrics_captured_settlement_retry_required',
                'metric_id' => $metric->id,
            ];
        }
    }
}
