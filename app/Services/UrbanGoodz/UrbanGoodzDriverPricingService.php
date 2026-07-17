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
     * Falls back to global policy (zone_id = null) if zone override not found.
     */
    public function resolvePolicy(string $type, ?int $zoneId = null): ?UrbanGoodzDriverPricingPolicy
    {
        $typeCandidates = $this->policyTypeCandidates($type);

        // Try zone override first
        if ($zoneId) {
            foreach ($typeCandidates as $candidate) {
                $policy = UrbanGoodzDriverPricingPolicy::active()
                    ->forTypeAndZone($candidate, $zoneId)
                    ->first();
                if ($policy) {
                    return $policy;
                }
            }
        }

        // Fallback to global policy
        foreach ($typeCandidates as $candidate) {
            $policy = UrbanGoodzDriverPricingPolicy::active()
                ->forTypeAndZone($candidate, null)
                ->first();
            if ($policy) {
                return $policy;
            }
        }

        return null;
    }

    /**
     * Calculate authoritative driver payout based on policy and parameters.
     */
    public function calculatePayout(string $type, array $params): array
    {
        $type = $this->normalizePolicyType($type);
        $zoneId = $params['zone_id'] ?? null;
        $policy = $this->resolvePolicy($type, $zoneId);

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
