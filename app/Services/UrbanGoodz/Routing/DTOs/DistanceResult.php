<?php

namespace App\Services\UrbanGoodz\Routing\DTOs;

class DistanceResult
{
    public const MODE_ROAD_MATRIX = 'ROAD_MATRIX';
    public const MODE_CACHED_ROAD = 'CACHED_ROAD_MATRIX';
    public const MODE_HAVERSINE = 'HAVERSINE_FALLBACK';

    public function __construct(
        public readonly float $distanceMiles,
        public readonly ?float $durationMinutes,
        public readonly string $mode,
        public readonly string $provider,
        public readonly bool $fromCache = false,
        public readonly ?string $trafficMode = null,
        public readonly ?string $computedAt = null,
        public readonly bool $isFallback = false,
    ) {}

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
        );
    }

    public function toArray(): array
    {
        return [
            'distance_miles' => $this->distanceMiles,
            'duration_minutes' => $this->durationMinutes,
            'mode' => $this->mode,
            'provider' => $this->provider,
            'from_cache' => $this->fromCache,
            'traffic_mode' => $this->trafficMode,
            'computed_at' => $this->computedAt,
            'is_fallback' => $this->isFallback,
        ];
    }
}
