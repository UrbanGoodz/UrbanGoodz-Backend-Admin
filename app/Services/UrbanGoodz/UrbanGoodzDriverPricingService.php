<?php

namespace App\Services\UrbanGoodz;

use App\Models\UrbanGoodzDriverPricingPolicy;
use App\Models\UrbanGoodzDriverEarning;
use App\Models\DeliveryManWallet;
use App\Models\UrbanGoodzActivityLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UrbanGoodzDriverPricingService
{
    private const POLICY_TYPE_ALIASES = [
        'business_multi_stop' => 'business_routes',
    ];

    public function __construct(
        protected DynamicPricingService $dynamicPricingService
    ) {}

    private function normalizePolicyType(string $type): string
    {
        return self::POLICY_TYPE_ALIASES[$type] ?? $type;
    }

    private function policyTypeCandidates(string $type): array
    {
        $normalized = $this->normalizePolicyType($type);
        $candidates = [$normalized];

        if ($normalized === 'business_routes') {
            $candidates[] = 'business_multi_stop';
        }

        return array_values(array_unique($candidates));
    }

    /**
     * Resolve the active pricing policy for a specific type and zone.
     *
     * Delegates to {@see UrbanGoodzDriverCompensationResolver}, which applies
     * the full assignment/contract/route/business/service/vehicle/load/medical/
     * market/module/global hierarchy. This signature previously understood only
     * zone and global, so callers passing just a type and zone keep working
     * unchanged while gaining every narrower tier.
     */
    public function resolvePolicy(
        string $type,
        ?int $zoneId = null,
        ?DriverCompensationContext $context = null
    ): ?UrbanGoodzDriverPricingPolicy {
        return $this->resolvePolicyFor($context ?? new DriverCompensationContext(
            policyType: $type,
            zoneId: $zoneId,
        ));
    }

    /**
     * Resolve using the full context — business, route, contract, service type,
     * vehicle, load type and a specific assignment.
     */
    public function resolvePolicyFor(DriverCompensationContext $context): ?UrbanGoodzDriverPricingPolicy
    {
        return app(UrbanGoodzDriverCompensationResolver::class)->resolve($context);
    }

    /**
     * Map a calculatePayout() parameter bag onto the resolution context.
     *
     * Callers that only supply a zone keep the previous behaviour; callers that
     * know the business, route or contract now get the narrower rate.
     *
     * @param array<string, mixed> $params
     */
    private function contextFromParams(string $type, array $params): DriverCompensationContext
    {
        return new DriverCompensationContext(
            policyType: $type,
            zoneId: isset($params['zone_id']) ? (int) $params['zone_id'] : null,
            market: $params['market'] ?? null,
            moduleId: isset($params['module_id']) ? (int) $params['module_id'] : null,
            businessClientId: isset($params['business_client_id']) ? (int) $params['business_client_id'] : null,
            contractId: isset($params['contract_id']) ? (int) $params['contract_id'] : null,
            routeId: isset($params['route_id']) ? (int) $params['route_id'] : null,
            routeScope: $params['route_scope'] ?? null,
            serviceType: $params['service_type'] ?? null,
            vehicleTypeId: isset($params['vehicle_type_id']) ? (int) $params['vehicle_type_id'] : null,
            loadType: $params['load_type'] ?? null,
            medicalType: $params['medical_type'] ?? null,
            subjectType: $params['subject_type'] ?? null,
            subjectId: isset($params['subject_id']) ? (int) $params['subject_id'] : null,
            at: $params['at'] ?? null,
        );
    }

    /**
     * Calculate authoritative driver payout based on policy and parameters.
     */
    public function calculatePayout(string $type, array $params): array
    {
        $type = $this->normalizePolicyType($type);
        $zoneId = $params['zone_id'] ?? null;
        // Build the full context so every configured tier — business, contract,
        // route, service, vehicle, load, medical, market, module — can win, not
        // just zone and global. Routed through resolvePolicy() so that callers
        // and tests which override that seam keep working.
        $policy = $this->resolvePolicy($type, $zoneId, $this->contextFromParams($type, $params));

        if (!$policy) {
            Log::warning("No active driver pricing policy found for type: {$type}, zone: {$zoneId}");
            $fallbackAmount = (float) ($params['base_amount'] ?? 0.00);
            return [
                'payout' => $fallbackAmount,
                'payout_model' => 'fallback',
                'policy_id' => null,
                'details' => ['message' => 'No policy resolved, used fallback amount.'],
            ];
        }

        // Base payout calculation depending on payout model
        $basePayout = 0.00;
        $details = [];
        $model = $policy->payout_model;

        switch ($model) {
            case 'fixed_payout':
                $basePayout = (float) $policy->fixed_amount;
                $details['base_calculation'] = "Fixed Payout: {$basePayout}";
                break;

            case 'base_mileage':
                $mileage = (float) ($params['mileage'] ?? 0.00);
                $basePayout = (float) $policy->base_fare + ($mileage * (float) $policy->rate_per_mile);
                $details['base_calculation'] = "Base Fare: {$policy->base_fare} + ({$mileage} miles * {$policy->rate_per_mile}/mi) = {$basePayout}";
                break;

            case 'base_mileage_time':
                $mileage = (float) ($params['mileage'] ?? 0.00);
                $duration = (float) ($params['duration'] ?? 0.00); // minutes
                $basePayout = (float) $policy->base_fare + ($mileage * (float) $policy->rate_per_mile) + ($duration * (float) $policy->rate_per_minute);
                $details['base_calculation'] = "Base Fare: {$policy->base_fare} + ({$mileage} miles * {$policy->rate_per_mile}/mi) + ({$duration} mins * {$policy->rate_per_minute}/min) = {$basePayout}";
                break;

            case 'per_stop':
                $stops = (int) ($params['stops'] ?? 1);
                $basePayout = $stops * (float) $policy->rate_per_stop;
                $details['base_calculation'] = "Stops: {$stops} * {$policy->rate_per_stop}/stop = {$basePayout}";
                break;

            case 'per_package':
                $packages = (int) ($params['packages'] ?? 1);
                $basePayout = $packages * (float) $policy->rate_per_package;
                $details['base_calculation'] = "Packages: {$packages} * {$policy->rate_per_package}/package = {$basePayout}";
                break;

            case 'percentage_of_revenue':
                $revenue = (float) ($params['revenue'] ?? 0.00);
                $basePayout = round($revenue * ((float) $policy->revenue_percentage / 100), 2);
                $details['base_calculation'] = "Revenue: {$revenue} * {$policy->revenue_percentage}% = {$basePayout}";
                break;

            case 'dynamic_ai':
                if ($policy->dynamic_pricing_enabled) {
                    $aiParams = [
                        'base_price' => (float) ($params['base_amount'] ?? $policy->base_fare ?: 5.00),
                        'demand_level' => $params['demand_level'] ?? 'medium',
                        'mileage' => $params['mileage'] ?? 0.00,
                        'duration' => $params['duration'] ?? 0.00,
                        'stops' => $params['stops'] ?? 1,
                        'packages' => $params['packages'] ?? 1,
                        'revenue' => $params['revenue'] ?? 0.00,
                    ];
                    $aiResult = $this->dynamicPricingService->calculateDynamicPrice($aiParams);
                    $basePayout = (float) $aiResult['final_price'];
                    $details['base_calculation'] = "Dynamic AI calculation (multiplier: {$aiResult['dynamic_multiplier']}x). Explanation: {$aiResult['explanation']}";
                } else {
                    // Fallback to base fare
                    $basePayout = (float) $policy->base_fare;
                    $details['base_calculation'] = "Dynamic AI disabled, fell back to Base Fare: {$basePayout}";
                }
                break;

            case 'manual_quote':
                $basePayout = (float) ($params['manual_quote_amount'] ?? $policy->fixed_amount);
                $details['base_calculation'] = "Manual Quote: {$basePayout}";
                break;

            default:
                $basePayout = (float) ($params['base_amount'] ?? 0.00);
                $details['base_calculation'] = "Fallback Default Amount: {$basePayout}";
                break;
        }

        $payout = $basePayout;

        // Apply vehicle multiplier
        $vehicleId = $params['vehicle_id'] ?? null;
        if ($vehicleId && !empty($policy->vehicle_multipliers)) {
            $multipliers = $policy->vehicle_multipliers;
            $multiplier = (float) ($multipliers[$vehicleId] ?? 1.0);
            if ($multiplier !== 1.0) {
                $payout *= $multiplier;
                $details['vehicle_multiplier'] = "Vehicle multiplier applied: {$multiplier}x (for vehicle category ID: {$vehicleId})";
            }
        }

        // Apply urgency premium
        $isUrgent = $params['is_urgent'] ?? false;
        if ($isUrgent) {
            $premium = (float) $policy->urgency_premium;
            if ($premium > 0) {
                $payout += $premium;
                $details['urgency_premium'] = "Urgency premium added: +{$premium}";
            }
        }

        // Apply additional rates
        $deadheadMiles = (float) ($params['deadhead_miles'] ?? 0.00);
        if ($deadheadMiles > 0 && $policy->deadhead_pay_rate > 0) {
            $deadheadPay = $deadheadMiles * (float) $policy->deadhead_pay_rate;
            $payout += $deadheadPay;
            $details['deadhead_pay'] = "Deadhead pay: {$deadheadMiles} miles * {$policy->deadhead_pay_rate}/mi = +{$deadheadPay}";
        }

        $waitingMinutes = (float) ($params['waiting_minutes'] ?? 0.00);
        if ($waitingMinutes > 0 && $policy->waiting_pay_rate > 0) {
            $waitingPay = $waitingMinutes * (float) $policy->waiting_pay_rate;
            $payout += $waitingPay;
            $details['waiting_pay'] = "Waiting pay: {$waitingMinutes} mins * {$policy->waiting_pay_rate}/min = +{$waitingPay}";
        }

        $isReturned = $params['is_returned'] ?? false;
        if ($isReturned && $policy->return_pay_rate > 0) {
            $payout += (float) $policy->return_pay_rate;
            $details['return_pay'] = "Return pay: +{$policy->return_pay_rate}";
        }

        $isException = $params['is_exception'] ?? false;
        if ($isException && $policy->exception_pay_rate > 0) {
            $payout += (float) $policy->exception_pay_rate;
            $details['exception_pay'] = "Exception pay: +{$policy->exception_pay_rate}";
        }

        // Enforce Min/Max payout limits
        if ($policy->minimum_payout !== null && $payout < (float) $policy->minimum_payout) {
            $payout = (float) $policy->minimum_payout;
            $details['minimum_payout_applied'] = "Capped to Policy Minimum: {$policy->minimum_payout}";
        }
        if ($policy->maximum_payout !== null && $payout > (float) $policy->maximum_payout) {
            $payout = (float) $policy->maximum_payout;
            $details['maximum_payout_applied'] = "Capped to Policy Maximum: {$policy->maximum_payout}";
        }

        // Enforce Minimum Margin Constraint
        $revenue = (float) ($params['revenue'] ?? $params['base_amount'] ?? 0.00);
        if ($revenue > 0 && $policy->minimum_margin !== null) {
            $maxAllowedPayout = round($revenue * (1 - ((float) $policy->minimum_margin / 100)), 2);
            if ($payout > $maxAllowedPayout) {
                $payout = max(0.00, $maxAllowedPayout);
                $details['minimum_margin_applied'] = "Payout adjusted to enforce minimum platform margin of {$policy->minimum_margin}% (max payout allowed: {$maxAllowedPayout})";
            }
        }

        $payout = round($payout, 2);

        return [
            'payout' => $payout,
            'payout_model' => $model,
            'policy_id' => $policy->id,
            'details' => $details,
            'sandbox_mode' => $policy->sandbox_pricing_enabled,
        ];
    }

    /**
     * Record driver earnings to the database and credit the driver's wallet.
     */
    public function recordEarning(array $data): UrbanGoodzDriverEarning
    {
        return DB::transaction(function () use ($data) {
            $driverId = $data['delivery_man_id'];
            $amount = (float) $data['amount'];
            $type = $data['earning_type'] ?? 'business_courier_delivery';
            $currency = $data['currency'] ?? 'USD';

            // A replayed completion must not pay twice.
            $idempotencyKey = $data['idempotency_key'] ?? null;

            if ($idempotencyKey !== null) {
                $existing = UrbanGoodzDriverEarning::where('idempotency_key', $idempotencyKey)->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            $policy = $data['policy'] ?? null;
            $grossCents = isset($data['gross_cents'])
                ? (int) $data['gross_cents']
                : (int) round($amount * 100, 0, PHP_ROUND_HALF_UP);
            $adminFeeCents = isset($data['admin_fee_cents']) ? (int) $data['admin_fee_cents'] : null;
            $netCents = $data['net_cents']
                ?? ($adminFeeCents === null ? $grossCents : $grossCents - $adminFeeCents);

            // Create Driver Earning record
            $earning = UrbanGoodzDriverEarning::create([
                'delivery_man_id' => $driverId,
                'dedicated_route_id' => $data['dedicated_route_id'] ?? null,
                'package_id' => $data['package_id'] ?? null,
                'business_client_job_id' => $data['business_client_job_id'] ?? null,
                'order_id' => $data['order_id'] ?? null,
                'load_id' => $data['load_id'] ?? null,
                'earning_type' => $type,
                'amount' => $amount,
                'currency' => $currency,
                'status' => $data['status'] ?? 'pending',
                'description' => $data['description'] ?? 'Driver compensation payment',
                'approved_by' => $data['approved_by'] ?? null,
                'approved_at' => isset($data['approved_by']) ? now() : null,

                // Compensation snapshot: which policy produced this figure, by
                // which method, from which verified operational inputs. Without
                // it a later rate change makes the earning unexplainable and a
                // driver disputing their pay cannot be shown the arithmetic.
                'pricing_policy_id' => $policy?->id ?? ($data['pricing_policy_id'] ?? null),
                'pricing_policy_version' => $policy?->version ?? ($data['pricing_policy_version'] ?? null),
                'payout_model' => $policy?->payout_model ?? ($data['payout_model'] ?? null),
                'gross_cents' => $grossCents,
                'admin_fee_cents' => $adminFeeCents,
                'net_cents' => $netCents,
                'calculation_inputs' => $data['calculation_inputs'] ?? null,
                'policy_snapshot' => $policy?->attributesToArray() ?? ($data['policy_snapshot'] ?? null),
                'settlement_snapshot_id' => $data['settlement_snapshot_id'] ?? null,
                'idempotency_key' => $idempotencyKey,
            ]);

            // If the status is paid or approved, credit the wallet immediately (unless bypassed)
            if (in_array($earning->status, ['approved', 'paid']) && !($data['bypass_wallet'] ?? false)) {
                $wallet = DeliveryManWallet::firstOrCreate(['delivery_man_id' => $driverId]);
                $wallet->increment('total_earning', $amount);
            }

            return $earning;
        });
    }

    /**
     * Log policy change audit events in UrbanGoodzActivityLog.
     */
    public function logPolicyActivity(UrbanGoodzDriverPricingPolicy $policy, string $event, ?string $description = null, ?array $oldValues = null): void
    {
        UrbanGoodzActivityLog::create([
            'loggable_type' => UrbanGoodzDriverPricingPolicy::class,
            'loggable_id' => $policy->id,
            'event' => $event,
            'description' => $description ?? "Driver pricing policy updated",
            'causer_type' => auth('admin')->check() ? 'App\Models\Admin' : null,
            'causer_id' => auth('admin')->id(),
            'old_values' => $oldValues,
            'new_values' => $policy->only(array_keys($oldValues ?? $policy->toArray())),
        ]);
    }
}
