<?php

namespace App\Services\UrbanGoodz;

use App\Models\UrbanGoodzDedicatedRoute;
use App\Models\UrbanGoodzDriverEarning;
use App\Models\UrbanGoodzFinancialSettlementSnapshot;
use App\Models\UrbanGoodzRouteOperationalMetric;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
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
            ->whereHas('scans', fn ($query) => $query->whereIn('scan_type', [
                'exception', 'failed_delivery', 'canceled',
            ]))
            ->count();
        $milesMilli = (int) round(
            (float) ($route->optimized_distance_miles ?? $route->estimated_miles ?? 0) * 1000
        );
        $completionVersion = max(1, (int) $route->optimization_version);
        $distanceMode = $route->optimization_distance_mode ?: 'HAVERSINE_FALLBACK';
        $isEligibleRoadMileage = $distanceMode === 'ROAD_NETWORK'
            && (int) $route->optimization_version > 0
            && (int) $route->assigned_driver_id > 0;
        $eligibleMilesMilli = $isEligibleRoadMileage ? $milesMilli : 0;
        $mileageEligibility = $isEligibleRoadMileage
            ? 'eligible_accepted_road_sequence'
            : 'ineligible_non_road_or_unaccepted';
        $stopCount = $route->optimizationStops
            ->map(fn ($stop) => (int) ($stop->group_stop_order ?: $stop->stop_order))
            ->unique()
            ->count();

        $metric = UrbanGoodzRouteOperationalMetric::updateOrCreate([
            'dedicated_route_id' => $route->id,
            'completion_version' => $completionVersion,
        ], [
            'driver_id' => $route->assigned_driver_id,
            'business_client_id' => $route->business_client_id,
            'miles_milli' => $milesMilli,
            'eligible_miles_milli' => $eligibleMilesMilli,
            'mileage_eligibility' => $mileageEligibility,
            'accepted_optimization_version' => (int) $route->optimization_version,
            'package_count' => $route->packages->count(),
            'stop_count' => $stopCount ?: $route->packages
                ->map(fn ($package) => $package->deliveryGroupKey())
                ->unique()
                ->count(),
            'return_count' => $returnCount,
            'exception_count' => $exceptionCount,
            'duration_minutes' => (int) (
                $route->optimized_duration_minutes ?? $route->estimated_duration ?? 0
            ),
            'distance_mode' => $distanceMode,
            'provider' => $route->optimization_provider,
            'verified_at' => now(),
        ]);

        if (!class_exists(self::FINANCIAL_SERVICE)
            && !(function_exists('app') && app()->bound(self::FINANCIAL_SERVICE))) {
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
            'miles_milli' => $metric->eligible_miles_milli,
            'measured_miles_milli' => $metric->miles_milli,
            'mileage_eligibility' => $metric->mileage_eligibility,
            'accepted_optimization_version' => $metric->accepted_optimization_version,
            'package_count' => $metric->package_count,
            'stop_count' => $metric->stop_count,
            'route_count' => 1,
            'hours_minutes' => $metric->duration_minutes,
            'return_count' => $metric->return_count,
            'exception_count' => $metric->exception_count,
            'guaranteed_driver_compensation_cents' => $this->guaranteedCompensationCents($route),
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
            $payout = $snapshot instanceof UrbanGoodzFinancialSettlementSnapshot
                ? $this->materializePayoutEarning($route, $snapshot)
                : ['status' => 'not_materialized_non_persistent_test_double'];

            return [
                'status' => 'settled',
                'metric_id' => $metric->id,
                'settlement_snapshot_id' => $snapshot->id,
                'payout' => $payout,
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

    private function guaranteedCompensationCents(UrbanGoodzDedicatedRoute $route): int
    {
        $delivered = $route->packages->whereIn('status', [
            'delivered', 'payout_eligible', 'completed',
        ]);
        $failed = $route->packages->whereIn('status', [
            'failed', 'unable_to_deliver',
        ]);
        $returned = $route->packages->whereIn('status', [
            'return_required', 'returning_to_pickup', 'returning_to_hub',
            'returning_to_business', 'returned_to_pickup', 'returned_to_hub',
            'returned_to_business',
        ]);
        $priorityDelivered = $delivered->whereIn('priority', [
            'high', 'urgent', 'medical',
        ])->count();

        $componentCents =
            ($delivered->count() * $this->moneyToCents($route->driver_pay_per_package))
            + ($priorityDelivered * $this->moneyToCents($route->priority_package_bonus))
            + ($failed->count() * $this->moneyToCents($route->failed_delivery_partial_pay))
            + ($returned->count() * $this->moneyToCents($route->return_to_sender_pay))
            + $this->moneyToCents($route->pickup_bonus)
            + $this->moneyToCents($route->route_completion_bonus);

        return max(
            $componentCents,
            $this->moneyToCents($route->route_offer_amount),
            $this->existingRouteEarningsCents($route)
        );
    }

    private function materializePayoutEarning(
        UrbanGoodzDedicatedRoute $route,
        UrbanGoodzFinancialSettlementSnapshot $snapshot
    ): array {
        return DB::transaction(function () use ($route, $snapshot) {
            $snapshot = UrbanGoodzFinancialSettlementSnapshot::query()
                ->lockForUpdate()
                ->findOrFail($snapshot->id);

            $existing = UrbanGoodzDriverEarning::query()
                ->where('financial_settlement_snapshot_id', $snapshot->id)
                ->first();
            if ($existing) {
                return [
                    'status' => 'already_materialized',
                    'earning_id' => $existing->id,
                    'legacy_earnings_cents' => $this->existingRouteEarningsCents($route),
                    'materialized_cents' => $this->earningCents($existing),
                ];
            }

            $legacyCents = $this->existingRouteEarningsCents($route, true);
            $materializedCents = max(0, (int) $snapshot->driver_net_cents - $legacyCents);
            if ($materializedCents === 0) {
                return [
                    'status' => 'covered_by_existing_earnings',
                    'earning_id' => null,
                    'legacy_earnings_cents' => $legacyCents,
                    'materialized_cents' => 0,
                ];
            }

            try {
                $earning = UrbanGoodzDriverEarning::create([
                    'delivery_man_id' => $route->assigned_driver_id,
                    'dedicated_route_id' => $route->id,
                    'earning_type' => 'dedicated_routes',
                    'amount' => number_format($materializedCents / 100, 2, '.', ''),
                    'currency' => $snapshot->currency,
                    'status' => 'pending',
                    'description' => 'Financial settlement payout for route '.$route->route_name,
                    'gross_cents' => $materializedCents,
                    'admin_fee_cents' => 0,
                    'net_cents' => $materializedCents,
                    'calculation_inputs' => [
                        'route_id' => $route->id,
                        'legacy_earnings_cents' => $legacyCents,
                        'settlement_driver_net_cents' => (int) $snapshot->driver_net_cents,
                    ],
                    'policy_snapshot' => $snapshot->rule_snapshot,
                    'financial_settlement_snapshot_id' => $snapshot->id,
                    'idempotency_key' => 'financial-settlement:'.$snapshot->id,
                ]);
            } catch (QueryException $exception) {
                $earning = UrbanGoodzDriverEarning::query()
                    ->where('financial_settlement_snapshot_id', $snapshot->id)
                    ->first();
                if (! $earning) {
                    throw $exception;
                }
            }

            return [
                'status' => 'materialized',
                'earning_id' => $earning->id,
                'legacy_earnings_cents' => $legacyCents,
                'materialized_cents' => $this->earningCents($earning),
            ];
        });
    }

    private function existingRouteEarningsCents(
        UrbanGoodzDedicatedRoute $route,
        bool $lockForUpdate = false
    ): int {
        $query = UrbanGoodzDriverEarning::query()
            ->where('delivery_man_id', $route->assigned_driver_id)
            ->where('dedicated_route_id', $route->id)
            ->whereNull('financial_settlement_snapshot_id');
        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->get()->sum(fn (UrbanGoodzDriverEarning $earning) =>
            $this->earningCents($earning)
        );
    }

    private function earningCents(UrbanGoodzDriverEarning $earning): int
    {
        return $earning->net_cents !== null
            ? (int) $earning->net_cents
            : $this->moneyToCents($earning->amount);
    }

    private function moneyToCents(mixed $amount): int
    {
        return max(0, (int) round(((float) ($amount ?? 0)) * 100));
    }
}
