<?php

namespace App\Services\UrbanGoodz\Routing\DTOs;

class DistanceResult
{
    // Legacy transport-level mode values. Kept for backwards compatibility with
    // callers/persisted audits that already read `mode`.
    public const MODE_ROAD_MATRIX = 'ROAD_MATRIX';
    public const MODE_CACHED_ROAD = 'CACHED_ROAD_MATRIX';
    public const MODE_HAVERSINE = 'HAVERSINE_FALLBACK';
    public const MODE_ROAD_NETWORK = 'ROAD_NETWORK';

    /**
     * Canonical calculation modes. This is the field a UI is allowed to trust
     * when it tells a human "this is a road distance". Every DistanceResult
     * carries exactly one of these, and it is never derived at display time.
     */
    public const CALC_ROAD_NETWORK = 'ROAD_NETWORK';
    public const CALC_HAVERSINE_FALLBACK = 'HAVERSINE_FALLBACK';
    public const CALC_MANUAL_ORDER = 'MANUAL_ORDER';

    /**
     * Diagonal / identical-point legs. Not a real calculation, always 0 miles.
     * Deliberately NOT one of the three reportable modes so that a zero leg can
     * never be counted as road coverage.
     */
    public const CALC_SELF = 'SELF';

    public readonly string $calculationMode;

    public function __construct(
        public readonly float $distanceMiles,
        public readonly ?float $durationMinutes,
        public readonly string $mode,
        public readonly string $provider,
        public readonly bool $fromCache = false,
        public readonly ?string $trafficMode = null,
        public readonly ?string $computedAt = null,
        public readonly bool $isFallback = false,
        ?string $calculationMode = null,
    ) {
        $this->calculationMode = $calculationMode ?? self::deriveCalculationMode($mode);
    }

    /**
     * Map any legacy/transport mode string onto the canonical taxonomy.
     * Anything unrecognised is treated as an estimate, never as road distance.
     */
    public static function deriveCalculationMode(string $mode): string
    {
        $normalized = strtoupper($mode);

        if ($normalized === 'SELF') {
            return self::CALC_SELF;
        }
        if ($normalized === self::CALC_MANUAL_ORDER || str_contains($normalized, 'MANUAL')) {
            return self::CALC_MANUAL_ORDER;
        }
        // Checked BEFORE the ROAD test so mixed values such as
        // MIXED_ROAD_HAVERSINE are never promoted to a road claim.
        if (str_contains($normalized, 'HAVERSINE')) {
            return self::CALC_HAVERSINE_FALLBACK;
        }
        if (str_contains($normalized, 'ROAD')) {
            return self::CALC_ROAD_NETWORK;
        }

        return self::CALC_HAVERSINE_FALLBACK;
    }

    /** Human-facing label for any raw mode string (for views/serializers). */
    public static function labelForCalculationMode(?string $calculationMode): string
    {
        return match ($calculationMode) {
            self::CALC_ROAD_NETWORK => 'Road network',
            self::CALC_MANUAL_ORDER => 'Manual order',
            self::CALC_SELF => 'Same location',
            default => 'Straight-line estimate',
        };
    }

    public static function road(float $miles, float $minutes, string $provider, bool $fromCache = false): self
    {
        return new self(
            distanceMiles: $miles,
            durationMinutes: $minutes,
            mode: $fromCache ? self::MODE_CACHED_ROAD : self::MODE_ROAD_MATRIX,
            provider: $provider,
            fromCache: $fromCache,
            computedAt: now()->toIso8601String(),
            isFallback: false,
            calculationMode: self::CALC_ROAD_NETWORK,
        );
    }

    /**
     * Road distance + duration returned by a routing engine that follows the
     * actual road network (OpenRouteService). Distinct from the legacy Google
     * distance-matrix path only in `mode`/`provider`; the canonical
     * calculation mode is the same ROAD_NETWORK.
     */
    public static function roadNetwork(float $miles, float $minutes, string $provider, bool $fromCache = false): self
    {
        return new self(
            distanceMiles: $miles,
            durationMinutes: $minutes,
            mode: $fromCache ? self::MODE_CACHED_ROAD : self::MODE_ROAD_NETWORK,
            provider: $provider,
            fromCache: $fromCache,
            computedAt: now()->toIso8601String(),
            isFallback: false,
            calculationMode: self::CALC_ROAD_NETWORK,
        );
    }

    public static function haversine(float $miles, string $reason = 'no_provider'): self
    {
        $speedMph = (float) config('urban_goodz.planning.default_average_speed_mph', 30);
        $minutes = $speedMph > 0 ? ($miles / $speedMph) * 60 : 0;

        return new self(
            distanceMiles: $miles,
            durationMinutes: $minutes,
            mode: self::MODE_HAVERSINE,
            provider: "haversine_fallback/{$reason}",
            computedAt: now()->toIso8601String(),
            isFallback: true,
            calculationMode: self::CALC_HAVERSINE_FALLBACK,
        );
    }

    /** True only when this value came from a real road-network calculation. */
    public function isRoadNetwork(): bool
    {
        return $this->calculationMode === self::CALC_ROAD_NETWORK;
    }

    /** Human-facing label for the mode. */
    public function calculationModeLabel(): string
    {
        return self::labelForCalculationMode($this->calculationMode);
    }

    public function toArray(): array
    {
        return [
            'distance_miles' => $this->distanceMiles,
            'duration_minutes' => $this->durationMinutes,
            'calculation_mode' => $this->calculationMode,
            'calculation_mode_label' => $this->calculationModeLabel(),
            'is_road_network' => $this->isRoadNetwork(),
            'mode' => $this->mode,
            'provider' => $this->provider,
            'from_cache' => $this->fromCache,
            'traffic_mode' => $this->trafficMode,
            'computed_at' => $this->computedAt,
            'is_fallback' => $this->isFallback,
        ];
    }
}
