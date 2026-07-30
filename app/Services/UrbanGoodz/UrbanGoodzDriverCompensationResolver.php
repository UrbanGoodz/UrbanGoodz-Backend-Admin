<?php

namespace App\Services\UrbanGoodz;

use App\Exceptions\DriverCompensationConfigurationException;
use App\Models\UrbanGoodzDriverPricingPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * Selects the driver compensation policy for one assignment.
 *
 * Resolution order, most specific first:
 *
 *    1. Assignment-specific approved rate   (subject_type + subject_id)
 *    2. Contract-specific rate              (contract_id)
 *    3. Dedicated-route rate                (route_id, route_scope=dedicated)
 *    4. Recurring-route rate                (route_id, route_scope=recurring)
 *    5. Business-specific rate              (business_client_id)
 *    6. Service-type rate                   (service_type)
 *    7. Vehicle-type rate                   (vehicle_type_id)
 *    8. Load-type rate                      (load_type)
 *    9. Medical-courier type rate           (medical_type)
 *   10. Zone or market rate                 (zone_id | market)
 *   11. Module default                      (module_id)
 *   12. Global driver fallback              (all dimensions null)
 *   13. Safe failure with Admin alert
 *
 * As with commission, specificity is derived from which dimensions a policy
 * populates rather than stored, so precedence cannot drift from the data. Ties
 * inside a tier break on `priority`, then the later `effective_from`, then id.
 */
class UrbanGoodzDriverCompensationResolver
{
    public const TIER_ASSIGNMENT = 130;
    public const TIER_CONTRACT = 120;
    public const TIER_ROUTE_DEDICATED = 110;
    public const TIER_ROUTE_RECURRING = 100;
    public const TIER_BUSINESS = 90;
    public const TIER_SERVICE = 80;
    public const TIER_VEHICLE = 70;
    public const TIER_LOAD = 60;
    public const TIER_MEDICAL = 50;
    public const TIER_MARKET = 40;
    public const TIER_MODULE = 30;
    public const TIER_GLOBAL = 20;

    private const POLICY_TYPE_ALIASES = [
        'business_multi_stop' => 'business_routes',
    ];

    /**
     * The most specific policy in force, or null when nothing is configured.
     */
    public function resolve(DriverCompensationContext $context): ?UrbanGoodzDriverPricingPolicy
    {
        $candidates = UrbanGoodzDriverPricingPolicy::query()
            ->where('is_active', true)
            ->whereIn('policy_type', $this->policyTypeCandidates($context->policyType))
            ->where(function (Builder $q) use ($context) {
                $q->whereNull('effective_from')->orWhere('effective_from', '<=', $context->at);
            })
            ->where(function (Builder $q) use ($context) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>', $context->at);
            })
            ->where(fn (Builder $q) => $this->wildcard($q, 'contract_id', $context->contractId))
            ->where(fn (Builder $q) => $this->wildcard($q, 'route_id', $context->routeId))
            ->where(fn (Builder $q) => $this->wildcard($q, 'route_scope', $context->routeScope))
            ->where(fn (Builder $q) => $this->wildcard($q, 'business_client_id', $context->businessClientId))
            ->where(fn (Builder $q) => $this->wildcard($q, 'service_type', $context->serviceType))
            ->where(fn (Builder $q) => $this->wildcard($q, 'vehicle_type_id', $context->vehicleTypeId))
            ->where(fn (Builder $q) => $this->wildcard($q, 'load_type', $context->loadType))
            ->where(fn (Builder $q) => $this->wildcard($q, 'medical_type', $context->medicalType))
            ->where(fn (Builder $q) => $this->wildcard($q, 'zone_id', $context->zoneId))
            ->where(fn (Builder $q) => $this->wildcard($q, 'market', $context->market))
            ->where(fn (Builder $q) => $this->wildcard($q, 'module_id', $context->moduleId))
            ->where(fn (Builder $q) => $this->subjectMatch($q, $context))
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        return $candidates
            ->sortByDesc(fn (UrbanGoodzDriverPricingPolicy $p) => [
                $this->specificity($p),
                (int) ($p->priority ?? 0),
                $p->effective_from?->getTimestamp() ?? 0,
                $p->id,
            ])
            ->first();
    }

    /**
     * Resolve or halt. Used once policies are configured; until then callers
     * use {@see resolve()} and handle a null themselves so that settlement is
     * not broken by the absence of configuration.
     */
    public function resolveOrFail(DriverCompensationContext $context): UrbanGoodzDriverPricingPolicy
    {
        $policy = $this->resolve($context);

        if ($policy !== null) {
            return $policy;
        }

        $exception = DriverCompensationConfigurationException::missing($context);

        Log::error('[UrbanGoodz][driver-compensation] ' . $exception->getMessage(), [
            'policy_type' => $context->policyType,
            'zone_id' => $context->zoneId,
            'business_client_id' => $context->businessClientId,
            'route_id' => $context->routeId,
            'service_type' => $context->serviceType,
            'subject_type' => $context->subjectType,
            'subject_id' => $context->subjectId,
        ]);

        throw $exception;
    }

    public function specificity(UrbanGoodzDriverPricingPolicy $policy): int
    {
        return match (true) {
            $policy->subject_type !== null && $policy->subject_id !== null => self::TIER_ASSIGNMENT,
            $policy->contract_id !== null => self::TIER_CONTRACT,
            $policy->route_id !== null && $policy->route_scope === 'dedicated' => self::TIER_ROUTE_DEDICATED,
            $policy->route_id !== null => self::TIER_ROUTE_RECURRING,
            $policy->business_client_id !== null => self::TIER_BUSINESS,
            $policy->service_type !== null => self::TIER_SERVICE,
            $policy->vehicle_type_id !== null => self::TIER_VEHICLE,
            $policy->load_type !== null => self::TIER_LOAD,
            $policy->medical_type !== null => self::TIER_MEDICAL,
            $policy->zone_id !== null || $policy->market !== null => self::TIER_MARKET,
            $policy->module_id !== null => self::TIER_MODULE,
            default => self::TIER_GLOBAL,
        };
    }

    private function wildcard(Builder $query, string $column, int|string|null $value): Builder
    {
        if ($value === null) {
            return $query->whereNull($column);
        }

        return $query->where(fn (Builder $q) => $q->whereNull($column)->orWhere($column, $value));
    }

    private function subjectMatch(Builder $query, DriverCompensationContext $context): Builder
    {
        if ($context->subjectType === null || $context->subjectId === null) {
            return $query->whereNull('subject_type')->whereNull('subject_id');
        }

        return $query->where(function (Builder $q) use ($context) {
            $q->where(fn (Builder $i) => $i->whereNull('subject_type')->whereNull('subject_id'))
                ->orWhere(fn (Builder $i) => $i
                    ->where('subject_type', $context->subjectType)
                    ->where('subject_id', $context->subjectId));
        });
    }

    /**
     * @return array<int, string>
     */
    private function policyTypeCandidates(string $type): array
    {
        $normalized = self::POLICY_TYPE_ALIASES[$type] ?? $type;
        $candidates = [$normalized];

        if ($normalized === 'business_routes') {
            $candidates[] = 'business_multi_stop';
        }

        return array_values(array_unique($candidates));
    }
}
