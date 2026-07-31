<?php

namespace App\Services\ServiceBookings;

use App\Models\UrbanGoodzServiceProvider;
use Illuminate\Database\Eloquent\Builder;

/**
 * Distance-aware provider discovery.
 *
 * A provider is reachable from a point when any of its active service areas
 * covers that point. Radius areas cover anything inside `radius_miles`;
 * city/postal areas are matched by their own identifiers rather than by
 * distance, so they are filtered separately by the caller.
 */
class ServiceProviderDiscoveryService
{
    public const EARTH_RADIUS_MILES = 3958.7613;

    /** Great-circle distance between two coordinates, in miles. */
    public static function distanceMiles(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return self::EARTH_RADIUS_MILES * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Whether a radius-type service area covers the supplied point.
     */
    public static function areaCoversPoint(
        ?float $areaLat,
        ?float $areaLon,
        ?int $radiusMiles,
        float $lat,
        float $lon
    ): bool {
        if ($areaLat === null || $areaLon === null || $radiusMiles === null || $radiusMiles <= 0) {
            return false;
        }

        return self::distanceMiles($areaLat, $areaLon, $lat, $lon) <= $radiusMiles;
    }

    /**
     * Restrict a provider query to providers whose active service areas reach
     * the given point, using a database-side bounding box first so the query
     * stays index-friendly, then an exact haversine check.
     *
     * `$limitMiles` caps the search independently of each area's own radius.
     */
    public function scopeWithinReach(Builder $query, float $lat, float $lon, ?int $limitMiles = null): Builder
    {
        $maxMiles = $limitMiles ?? 500;
        $latDelta = $maxMiles / 69.0;
        // Guard against a division by zero at the poles.
        $cos = max(cos(deg2rad($lat)), 0.01);
        $lonDelta = $maxMiles / (69.0 * $cos);

        return $query->whereHas('areas', function ($areaQuery) use ($lat, $lon, $latDelta, $lonDelta) {
            $areaQuery->where('is_active', true)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->whereBetween('latitude', [$lat - $latDelta, $lat + $latDelta])
                ->whereBetween('longitude', [$lon - $lonDelta, $lon + $lonDelta]);
        });
    }

    /**
     * Exact post-filter for a loaded provider collection, attaching the
     * distance to the nearest covering area so the Shopper can display it.
     *
     * Only areas that actually cover the point are considered, so a provider
     * is never shown with a distance it does not serve.
     */
    public function attachDistances(
        iterable $providers,
        float $lat,
        float $lon,
        ?int $limitMiles = null
    ): array {
        $matched = [];

        foreach ($providers as $provider) {
            $best = null;
            foreach ($provider->areas ?? [] as $area) {
                if ($area->latitude === null || $area->longitude === null) {
                    continue;
                }
                $distance = self::distanceMiles(
                    (float) $area->latitude,
                    (float) $area->longitude,
                    $lat,
                    $lon
                );
                $radius = (int) ($area->radius_miles ?? 0);
                $covers = $radius > 0 && $distance <= $radius;
                if (!$covers) {
                    continue;
                }
                if ($limitMiles !== null && $distance > $limitMiles) {
                    continue;
                }
                if ($best === null || $distance < $best) {
                    $best = $distance;
                }
            }

            if ($best !== null) {
                $provider->setAttribute('distance_miles', round($best, 2));
                $matched[] = $provider;
            }
        }

        usort($matched, fn ($a, $b) => $a->distance_miles <=> $b->distance_miles);

        return $matched;
    }

    /** @return array<int, string> */
    public static function categories(): array
    {
        return (array) config('service_bookings.categories', []);
    }

    public static function isSupportedCategory(string $category): bool
    {
        return in_array($category, self::categories(), true);
    }

    public function baseQuery(): Builder
    {
        return UrbanGoodzServiceProvider::query()
            ->where('approval_status', 'approved')
            ->where('is_verified', true)
            ->where('is_active', true);
    }
}
