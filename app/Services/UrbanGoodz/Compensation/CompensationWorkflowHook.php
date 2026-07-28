<?php

namespace App\Services\UrbanGoodz\Compensation;

use App\Models\UrbanGoodzCompensationResult;
use App\Models\UrbanGoodzDedicatedRoute;
use App\Models\UrbanGoodzDriverEarning;
use App\Models\UrbanGoodzLoadBoardLoad;
use App\Models\UrbanGoodzMedicalCourierJob;
use App\Models\UrbanGoodzPaymentLedger;
use App\Models\Order;
use App\Models\OrderAnywhereRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Bridges workflow lifecycle events to the compensation engine.
 *
 * Three phases:
 *   1. AT ASSIGNMENT — estimate only (never finalized).
 *   2. AT ACCEPTANCE — preserve the estimate; permit only audited recalculation.
 *   3. AT TERMINAL STATE — compute the final, seal it, create idempotent ledger
 *      instructions, and prevent retroactive mutation.
 */
final class CompensationWorkflowHook
{
    private CompensationEngine $engine;

    public function __construct(?CompensationEngine $engine = null)
    {
        $this->engine = $engine ?? new CompensationEngine();
    }

    // ---------------------------------------------------------------
    // Work-type mapping
    // ---------------------------------------------------------------

    public static function workTypeFor(string $domain): string
    {
        return match ($domain) {
            'order', 'order_anywhere', 'shopping', 'courier_parcel',
            'marketplace_delivery', 'delivery' => 'delivery',
            'dedicated_route', 'scheduled_route', 'recurring_route',
            'business_courier', 'business_multi_stop', 'package_route',
            'route' => 'route',
            'logistics_load', 'full_truckload', 'partial_ltl',
            'local_logistics', 'otr_long_haul' => 'logistics',
            'medical_courier', 'stat_medical', 'scheduled_medical_route',
            'medical' => 'medical',
            default => 'delivery',
        };
    }

    public static function serviceScopeFor(string $domain): ?string
    {
        return match ($domain) {
            'order', 'marketplace_delivery', 'delivery' => 'marketplace_delivery',
            'order_anywhere' => 'order_anywhere',
            'shopping' => 'shopping_job',
            'courier_parcel' => 'courier_parcel',
            'dedicated_route' => 'dedicated_route',
            'scheduled_route' => 'scheduled_route',
            'recurring_route' => 'recurring_route',
            'business_courier', 'business_multi_stop' => 'business_multi_stop',
            'package_route' => 'package_route',
            'logistics_load' => 'local_logistics',
            'full_truckload' => 'full_truckload',
            'partial_ltl' => 'partial_ltl',
            'otr_long_haul' => 'otr_long_haul',
            'medical_courier' => 'medical_courier',
            'stat_medical' => 'stat_medical',
            'scheduled_medical_route' => 'scheduled_medical_route',
            'medical' => 'medical_courier',
            default => null,
        };
    }

    // ---------------------------------------------------------------
    // Phase 1 — At Assignment (estimate)
    // ---------------------------------------------------------------

    public function atAssignment(string $domain, array $contextData): ?UrbanGoodzCompensationResult
    {
        $contextData['work_type'] = $contextData['work_type'] ?? self::workTypeFor($domain);
        $contextData['service_scope'] = $contextData['service_scope'] ?? self::serviceScopeFor($domain);

        $ctx = CompensationContext::fromArray($contextData);

        try {
            $calc = $this->engine->calculate($ctx);
        } catch (\Throwable $e) {
            Log::info('Compensation estimate skipped — no matching rule', [
                'domain' => $domain,
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        return $this->engine->record($calc, $ctx, false);
    }

    // ---------------------------------------------------------------
    // Phase 2 — At Acceptance
    // ---------------------------------------------------------------

    public function atAcceptance(UrbanGoodzCompensationResult $estimate, ?int $acceptedBy = null): UrbanGoodzCompensationResult
    {
        if ($estimate->is_final) {
            return $estimate;
        }

        $estimate->update([
            'breakdown' => array_merge((array) $estimate->breakdown, [
                'accepted_at' => now()->toIso8601String(),
                'accepted_by' => $acceptedBy,
            ]),
        ]);

        return $estimate->fresh();
    }

    /**
     * Audited recalculation — allowed at acceptance but creates a new record
     * rather than overwriting the existing estimate.
     */
    public function recalcAtAcceptance(string $domain, array $contextData, ?int $actorId = null): ?UrbanGoodzCompensationResult
    {
        $contextData['work_type'] = $contextData['work_type'] ?? self::workTypeFor($domain);
        $contextData['service_scope'] = $contextData['service_scope'] ?? self::serviceScopeFor($domain);

        $ctx = CompensationContext::fromArray($contextData);

        try {
            $calc = $this->engine->calculate($ctx);
        } catch (\Throwable $e) {
            Log::warning('Compensation recalculation at acceptance failed', [
                'domain' => $domain,
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        return $this->engine->record($calc, $ctx, false);
    }

    // ---------------------------------------------------------------
    // Phase 3 — At Terminal State (final)
    // ---------------------------------------------------------------

    public function atTerminalState(
        string $domain,
        string $subjectType,
        int $subjectId,
        array $contextData,
        ?int $driverId = null,
    ): ?UrbanGoodzCompensationResult {
        $contextData['work_type'] = $contextData['work_type'] ?? self::workTypeFor($domain);
        $contextData['service_scope'] = $contextData['service_scope'] ?? self::serviceScopeFor($domain);
        $contextData['subject_type'] = $subjectType;
        $contextData['subject_id'] = $subjectId;
        $contextData['driver_id'] = $driverId;
        $contextData['occurred_at'] = $contextData['occurred_at'] ?? now()->toIso8601String();

        $ctx = CompensationContext::fromArray($contextData);

        // Idempotent: if a finalized result already exists for this subject, return it.
        $existing = UrbanGoodzCompensationResult::query()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('is_final', true)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        try {
            $calc = $this->engine->calculate($ctx);
        } catch (\Throwable $e) {
            Log::warning('Compensation at terminal state failed — no matching rule', [
                'domain' => $domain,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        return DB::transaction(function () use ($calc, $ctx, $domain, $subjectType, $subjectId, $driverId) {
            $result = $this->engine->record($calc, $ctx, true);

            $this->createLedgerInstruction($result, $domain, $subjectType, $subjectId, $driverId);

            $this->createDriverEarning($result, $domain, $subjectType, $subjectId, $driverId);

            return $result;
        });
    }

    // ---------------------------------------------------------------
    // Workflow-specific convenience methods
    // ---------------------------------------------------------------

    public function onOrderDelivered(Order $order): ?UrbanGoodzCompensationResult
    {
        return $this->atTerminalState(
            $order->order_type ?? 'order',
            'order',
            $order->id,
            $this->orderContext($order),
            $order->delivery_man_id,
        );
    }

    public function onOrderCancelled(Order $order): ?UrbanGoodzCompensationResult
    {
        $ctx = $this->orderContext($order);
        $ctx['is_cancelled'] = true;

        return $this->atTerminalState(
            $order->order_type ?? 'order',
            'order',
            $order->id,
            $ctx,
            $order->delivery_man_id,
        );
    }

    public function onOrderFailedDelivery(Order $order): ?UrbanGoodzCompensationResult
    {
        $ctx = $this->orderContext($order);
        $ctx['is_failed_delivery'] = true;

        return $this->atTerminalState(
            $order->order_type ?? 'order',
            'order',
            $order->id,
            $ctx,
            $order->delivery_man_id,
        );
    }

    public function onOrderReturn(Order $order): ?UrbanGoodzCompensationResult
    {
        $ctx = $this->orderContext($order);
        $ctx['is_return_trip'] = true;

        return $this->atTerminalState(
            $order->order_type ?? 'order',
            'order',
            $order->id,
            $ctx,
            $order->delivery_man_id,
        );
    }

    public function onOrderRedelivery(Order $order): ?UrbanGoodzCompensationResult
    {
        $ctx = $this->orderContext($order);
        $ctx['is_redelivery'] = true;

        return $this->atTerminalState(
            $order->order_type ?? 'order',
            'order',
            $order->id,
            $ctx,
            $order->delivery_man_id,
        );
    }

    public function onOrderAnywhereDelivered(OrderAnywhereRequest $request, ?Order $order = null): ?UrbanGoodzCompensationResult
    {
        $driverId = $request->assigned_driver_id ?? $order?->delivery_man_id;

        return $this->atTerminalState(
            'order_anywhere',
            'order_anywhere',
            $request->id,
            $this->orderAnywhereContext($request, $order),
            $driverId,
        );
    }

    public function onOrderAnywhereCancelled(OrderAnywhereRequest $request): ?UrbanGoodzCompensationResult
    {
        $ctx = $this->orderAnywhereContext($request);
        $ctx['is_cancelled'] = true;

        return $this->atTerminalState(
            'order_anywhere',
            'order_anywhere',
            $request->id,
            $ctx,
            $request->assigned_driver_id,
        );
    }

    public function onLoadBoardCompleted(UrbanGoodzLoadBoardLoad $load): ?UrbanGoodzCompensationResult
    {
        return $this->atTerminalState(
            'logistics_load',
            'load',
            $load->id,
            $this->loadContext($load),
            $load->assigned_driver_id,
        );
    }

    public function onLoadBoardCancelled(UrbanGoodzLoadBoardLoad $load): ?UrbanGoodzCompensationResult
    {
        $ctx = $this->loadContext($load);
        $ctx['is_cancelled'] = true;

        return $this->atTerminalState(
            'logistics_load',
            'load',
            $load->id,
            $ctx,
            $load->assigned_driver_id,
        );
    }

    public function onDedicatedRouteCompleted(UrbanGoodzDedicatedRoute $route): ?UrbanGoodzCompensationResult
    {
        return $this->atTerminalState(
            $this->routeDomain($route),
            'route',
            $route->id,
            $this->routeContext($route),
            $route->assigned_driver_id,
        );
    }

    public function onDedicatedRouteCancelled(UrbanGoodzDedicatedRoute $route): ?UrbanGoodzCompensationResult
    {
        $ctx = $this->routeContext($route);
        $ctx['is_cancelled'] = true;

        return $this->atTerminalState(
            $this->routeDomain($route),
            'route',
            $route->id,
            $ctx,
            $route->assigned_driver_id,
        );
    }

    public function onDedicatedRouteException(UrbanGoodzDedicatedRoute $route): ?UrbanGoodzCompensationResult
    {
        return $this->atTerminalState(
            $this->routeDomain($route),
            'route',
            $route->id,
            array_merge($this->routeContext($route), ['is_failed_delivery' => true]),
            $route->assigned_driver_id,
        );
    }

    public function onMedicalCourierDelivered(UrbanGoodzMedicalCourierJob $job): ?UrbanGoodzCompensationResult
    {
        return $this->atTerminalState(
            'medical_courier',
            'medical_job',
            $job->id,
            $this->medicalContext($job),
            $job->assigned_driver_id,
        );
    }

    public function onMedicalCourierCancelled(UrbanGoodzMedicalCourierJob $job): ?UrbanGoodzCompensationResult
    {
        $ctx = $this->medicalContext($job);
        $ctx['is_cancelled'] = true;

        return $this->atTerminalState(
            'medical_courier',
            'medical_job',
            $job->id,
            $ctx,
            $job->assigned_driver_id,
        );
    }

    public function onMedicalHandoffFailed(UrbanGoodzMedicalCourierJob $job): ?UrbanGoodzCompensationResult
    {
        $ctx = $this->medicalContext($job);
        $ctx['is_failed_handoff'] = true;

        return $this->atTerminalState(
            'medical_courier',
            'medical_job',
            $job->id,
            $ctx,
            $job->assigned_driver_id,
        );
    }

    // ---------------------------------------------------------------
    // Context builders
    // ---------------------------------------------------------------

    private function orderContext(Order $order): array
    {
        return [
            'miles' => (float) ($order->distance ?? 0),
            'stops' => 1,
            'packages' => max(1, $order->item_count ?? 1),
            'deliveredPackages' => in_array($order->order_status, ['delivered', 'completed']) ? max(1, $order->item_count ?? 1) : 0,
            'customerChargeCents' => (int) round(((float) ($order->order_amount ?? 0)) * 100),
            'tipsCents' => (int) round(((float) ($order->tip ?? 0)) * 100),
            'tollsCents' => 0,
            'isPeak' => (bool) ($order->is_peak ?? false),
            'vehicleType' => $order->vehicle_type ?? null,
            'market' => $order->market ?? null,
            'zoneId' => $order->zone_id ?? null,
        ];
    }

    private function orderAnywhereContext(OrderAnywhereRequest $request, ?Order $order = null): array
    {
        return [
            'miles' => (float) ($request->distance_miles ?? $order?->distance ?? 0),
            'stops' => 1,
            'packages' => 1,
            'deliveredPackages' => 1,
            'customerChargeCents' => (int) round(((float) ($request->total_amount ?? $order?->order_amount ?? 0)) * 100),
            'tipsCents' => (int) round(((float) ($request->tip ?? $order?->tip ?? 0)) * 100),
            'vehicleType' => $request->vehicle_type ?? null,
            'market' => $request->market ?? null,
            'zoneId' => $request->zone_id ?? null,
        ];
    }

    private function loadContext(UrbanGoodzLoadBoardLoad $load): array
    {
        $metadata = is_array($load->metadata) ? $load->metadata : [];

        return [
            'miles' => (float) ($load->distance_miles ?? 0),
            'loadedMiles' => (float) ($load->distance_miles ?? 0),
            'deadheadMiles' => (float) ($metadata['deadhead_miles'] ?? 0),
            'packages' => (int) ($load->package_count ?? 1),
            'deliveredPackages' => (int) ($load->package_count ?? 1),
            'minutes' => (int) ($load->estimated_duration_minutes ?? 0),
            'detentionMinutes' => (int) ($metadata['detention_minutes'] ?? 0),
            'waitMinutes' => (int) ($metadata['wait_minutes'] ?? 0),
            'layoverNights' => (int) ($metadata['layover_nights'] ?? 0),
            'customerChargeCents' => (int) round(((float) ($load->payout_amount ?? 0)) * 100),
            'linehaulCents' => (int) round(((float) ($metadata['linehaul_cents'] ?? $load->payout_amount ?? 0)) * 100),
            'vehicleType' => $metadata['vehicle_type'] ?? null,
            'market' => $load->market ?? null,
            'zoneId' => $metadata['zone_id'] ?? null,
            'isPeak' => (bool) ($metadata['is_peak'] ?? false),
            'isAfterHours' => (bool) ($metadata['is_after_hours'] ?? false),
            'isWeekend' => (bool) ($metadata['is_weekend'] ?? false),
            'isOvernight' => (bool) ($metadata['is_overnight'] ?? false),
            'tollsCents' => (int) round(((float) ($metadata['tolls'] ?? 0)) * 100),
            'reimbursementsCents' => (int) round(((float) ($metadata['reimbursements'] ?? 0)) * 100),
        ];
    }

    private function routeDomain(UrbanGoodzDedicatedRoute $route): string
    {
        if (!empty($route->recurring_rule)) {
            return 'recurring_route';
        }
        if ($route->scheduled_date !== null && empty($route->recurring_rule)) {
            return 'scheduled_route';
        }
        return 'dedicated_route';
    }

    private function routeContext(UrbanGoodzDedicatedRoute $route): array
    {
        $totalPackages = $route->total_packages ?? $route->packages()->count();
        $completedPackages = $route->completed_packages ?? $route->packages()->where('status', 'completed')->count();
        $failedPackages = $route->failed_packages ?? 0;
        $routeRevenue = (float) ($route->route_offer_amount ?? 0);
        $driverPay = (float) ($route->driver_pay_per_package ?? 0) * $totalPackages;

        return [
            'miles' => (float) ($route->estimated_miles ?? 0),
            'stops' => $totalPackages,
            'packages' => $totalPackages,
            'deliveredPackages' => $completedPackages,
            'customerChargeCents' => (int) round($routeRevenue * 100),
            'linehaulCents' => (int) round($driverPay * 100),
            'vehicleType' => $route->vehicle_type ?? null,
            'market' => $route->market ?? null,
            'zoneId' => $route->zone_id ?? $route->client?->zone_id ?? null,
            'routeCompleted' => $route->status === 'completed',
        ];
    }

    private function medicalContext(UrbanGoodzMedicalCourierJob $job): array
    {
        $metadata = is_array($job->metadata) ? $job->metadata : [];

        return [
            'miles' => (float) ($job->distance_miles ?? 0),
            'packages' => 1,
            'deliveredPackages' => in_array($job->status, ['delivered', 'completed']) ? 1 : 0,
            'customerChargeCents' => (int) round(((float) ($job->payout_amount ?? 0)) * 100),
            'linehaulCents' => (int) round(((float) ($job->payout_amount ?? 0)) * 100),
            'isStat' => (bool) ($job->priority === 'urgent'),
            'requiresChainOfCustody' => (bool) ($metadata['chain_of_custody'] ?? true),
            'requiresTemperatureControl' => (bool) ($metadata['temperature_control'] ?? false),
            'isAfterHours' => (bool) ($metadata['is_after_hours'] ?? false),
            'isReturnSpecimen' => (bool) ($metadata['return_specimen'] ?? false),
            'vehicleType' => $metadata['vehicle_type'] ?? null,
            'market' => $job->market ?? null,
            'zoneId' => $metadata['zone_id'] ?? null,
            'waitMinutes' => (int) ($metadata['wait_minutes'] ?? 0),
        ];
    }

    // ---------------------------------------------------------------
    // Ledger + earning creation
    // ---------------------------------------------------------------

    private function createLedgerInstruction(
        UrbanGoodzCompensationResult $result,
        string $domain,
        string $subjectType,
        int $subjectId,
        ?int $driverId,
    ): void {
        $idempotencyKey = "compensation:{$subjectType}:{$subjectId}:{$result->rule_version}";

        // Prevent duplicate ledger entries for the same finalization.
        $exists = UrbanGoodzPaymentLedger::where('idempotency_key', $idempotencyKey)->exists();
        if ($exists) {
            return;
        }

        UrbanGoodzPaymentLedger::create([
            'ledger_number' => UrbanGoodzPaymentLedger::nextLedgerNumber(),
            'feature' => "compensation_{$domain}",
            'payable_type' => $this->payableModel($subjectType),
            'payable_id' => $subjectId,
            'event_type' => 'compensation_finalized',
            'direction' => 'outbound',
            'amount' => Money::toDecimal($result->driver_cents),
            'currency' => 'USD',
            'payment_status' => 'completed',
            'idempotency_key' => $idempotencyKey,
            'delivery_man_id' => $driverId,
            'metadata' => [
                'compensation_result_id' => $result->id,
                'rule_id' => $result->rule_id,
                'rule_key' => $result->rule_key,
                'rule_version' => $result->rule_version,
                'earned_cents' => $result->splits['driver_cents'] ?? $result->driver_cents,
                'pass_through_cents' => $result->splits['driver_pass_through_cents'] ?? 0,
                'is_deficit' => $result->splits['is_deficit'] ?? false,
            ],
        ]);
    }

    private function createDriverEarning(
        UrbanGoodzCompensationResult $result,
        string $domain,
        string $subjectType,
        int $subjectId,
        ?int $driverId,
    ): void {
        if ($driverId === null || $result->driver_cents <= 0) {
            return;
        }

        $fkColumn = $this->subjectForeignKey($subjectType);
        $fkValue = $this->resolveForeignKey($subjectType, $subjectId);

        $data = [
            'delivery_man_id' => $driverId,
            'earning_type' => $this->earningType($domain),
            'amount' => Money::toDecimal($result->driver_cents),
            'status' => 'approved',
            'description' => "Compensation via rule {$result->rule_key} v{$result->rule_version} for {$subjectType} #{$subjectId}",
        ];

        if ($fkColumn !== null && $fkValue !== null) {
            $data[$fkColumn] = $fkValue;
        }

        UrbanGoodzDriverEarning::create($data);
    }

    private function resolveForeignKey(string $subjectType, int $subjectId): ?int
    {
        $model = match ($subjectType) {
            'order' => Order::find($subjectId),
            'order_anywhere' => OrderAnywhereRequest::find($subjectId),
            'load' => UrbanGoodzLoadBoardLoad::find($subjectId),
            'route' => UrbanGoodzDedicatedRoute::find($subjectId),
            'medical_job' => UrbanGoodzMedicalCourierJob::find($subjectId),
            default => null,
        };

        return $model?->id;
    }

    private function payableModel(string $subjectType): string
    {
        return match ($subjectType) {
            'order' => Order::class,
            'order_anywhere' => OrderAnywhereRequest::class,
            'load' => UrbanGoodzLoadBoardLoad::class,
            'route' => UrbanGoodzDedicatedRoute::class,
            'medical_job' => UrbanGoodzMedicalCourierJob::class,
            default => Order::class,
        };
    }

    private function earningType(string $domain): string
    {
        return match ($domain) {
            'order', 'marketplace_delivery', 'delivery' => 'marketplace_delivery',
            'order_anywhere' => 'order_anywhere',
            'shopping' => 'marketplace_delivery',
            'courier_parcel' => 'courier_parcel',
            'dedicated_route' => 'dedicated_routes',
            'scheduled_route' => 'dedicated_routes',
            'recurring_route' => 'dedicated_routes',
            'business_courier', 'business_multi_stop' => 'business_courier_delivery',
            'package_route' => 'dedicated_routes',
            'logistics_load', 'full_truckload', 'partial_ltl',
            'local_logistics', 'otr_long_haul' => 'logistics_loads',
            'medical_courier', 'stat_medical', 'scheduled_medical_route' => 'medical_courier',
            default => 'marketplace_delivery',
        };
    }

    private function subjectForeignKey(string $subjectType): ?string
    {
        return match ($subjectType) {
            'order' => 'order_id',
            'load' => 'load_id',
            'route' => 'dedicated_route_id',
            'medical_job' => null,
            default => null,
        };
    }
}
