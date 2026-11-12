<?php

namespace App\Services\UrbanGoodz\Routing\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Turns a typed address into coordinates, using OpenRouteService's Pelias
 * geocoder.
 *
 * NOTE: this is openrouteservice.org, sharing ORS_API_KEY / ORS_ENABLED with
 * the routing provider. It has nothing to do with the OPENROUTER_*
 * configuration in this repo, which is an LLM gateway (openrouter.ai). As with
 * the routing provider the key travels in the Authorization header, never in a
 * query string, so no URL logged by this class can leak it.
 *
 * Every lookup is bounded to a circle around an anchor - in practice the
 * route's pickup. That bound is the whole safety model, and it is not
 * decorative: asked for "asdkjhqwe zzzz nowhere 99999" the unbounded API
 * cheerfully returns a venue in Belgrade, Serbia, at confidence 1.0. Pelias
 * confidence scores how well the text matched something, not whether that
 * something is anywhere a van could drive, so it cannot be used as the guard.
 * Bounded to the operating region, the same query returns nothing at all.
 *
 * A miss returns null rather than a best guess.
 */
class AddressGeocoder
{
    private const CACHE_PREFIX = 'urb_geocode_';

    private const EARTH_RADIUS_KM = 6371.0088;

    /** How far from the anchor a finish point may plausibly be. */
    private const DEFAULT_RADIUS_KM = 150.0;

    private string $apiKey;
    private string $baseUrl;
    private float $timeoutSeconds;
    private float $connectTimeoutSeconds;
    private float $radiusKm;
    private int $cacheTtlHours;
    private bool $enabled;
    private ?string $lastFailureReason = null;

    public function __construct(?array $config = null)
    {
        $cfg = $config ?? config('urban_goodz.openrouteservice', []);

        $this->enabled = (bool) ($cfg['enabled'] ?? false);
        $this->apiKey = (string) ($cfg['api_key'] ?? '');
        $this->baseUrl = rtrim((string) ($cfg['base_url'] ?? 'https://api.openrouteservice.org'), '/');
        $this->timeoutSeconds = (float) ($cfg['timeout_seconds'] ?? 8);
        $this->connectTimeoutSeconds = (float) ($cfg['connect_timeout_seconds'] ?? 4);
        $this->radiusKm = (float) ($cfg['geocode_radius_km'] ?? self::DEFAULT_RADIUS_KM);
        $this->cacheTtlHours = max(0, (int) ($cfg['cache_ttl_hours'] ?? 24));
    }

    public function isConfigured(): bool
    {
        return $this->enabled && $this->apiKey !== '';
    }

    public function lastFailureReason(): ?string
    {
        return $this->lastFailureReason;
    }

    public function radiusKm(): float
    {
        return $this->radiusKm;
    }

    /**
     * Resolve free-text address near an anchor point.
     *
     * The anchor is required. Without somewhere to bound the search there is
     * no way to tell a legitimate match from a same-named street on another
     * continent, and guessing is the failure this class exists to avoid.
     *
     * @param array{lat: float, lng: float} $anchor usually the route's pickup
     * @return array{lat: float, lng: float, label: string, confidence: float}|null
     */
    public function geocode(string $address, ?array $anchor = null): ?array
    {
        $this->lastFailureReason = null;
        $address = trim($address);

        if ($address === '') {
            $this->lastFailureReason = 'empty_address';
            return null;
        }

        if (!$this->isConfigured()) {
            $this->lastFailureReason = 'not_configured';
            return null;
        }

        if ($anchor === null || !isset($anchor['lat'], $anchor['lng'])) {
            $this->lastFailureReason = 'no_anchor';
            return null;
        }

        $anchorLat = (float) $anchor['lat'];
        $anchorLng = (float) $anchor['lng'];
        $cacheKey = $this->cacheKey($address, $anchorLat, $anchorLng);

        if ($this->cacheTtlHours > 0) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        try {
            $response = Http::withHeaders(['Authorization' => $this->apiKey])
                ->timeout($this->timeoutSeconds)
                ->connectTimeout($this->connectTimeoutSeconds)
                ->get($this->baseUrl . '/geocode/search', [
                    'text' => $address,
                    'size' => 1,
                    // Rank within the circle...
                    'focus.point.lat' => $anchorLat,
                    'focus.point.lon' => $anchorLng,
                    // ...and refuse to leave it.
                    'boundary.circle.lat' => $anchorLat,
                    'boundary.circle.lon' => $anchorLng,
                    'boundary.circle.radius' => $this->radiusKm,
                ]);
        } catch (Throwable $e) {
            // The address is the driver's own typing and is frequently their
            // home, so it is never written to the log beside the failure.
            Log::warning('Address geocoding request failed', ['error' => $e->getMessage()]);
            $this->lastFailureReason = 'transport_error';
            return null;
        }

        if (!$response->successful()) {
            Log::warning('Address geocoding returned an error', ['status' => $response->status()]);
            $this->lastFailureReason = 'http_' . $response->status();
            return null;
        }

        $feature = $response->json('features.0');

        if (!is_array($feature)) {
            $this->lastFailureReason = 'no_match';
            return null;
        }

        $coordinates = $feature['geometry']['coordinates'] ?? null;

        // Pelias returns [lon, lat] - the reverse of how the rest of this
        // codebase orders a pair.
        if (!is_array($coordinates) || count($coordinates) < 2) {
            $this->lastFailureReason = 'no_coordinates';
            return null;
        }

        $lat = (float) $coordinates[1];
        $lng = (float) $coordinates[0];

        // Defence in depth: the bound above is applied by the provider, so a
        // provider change or a silently ignored parameter would otherwise let
        // a far-away match through unnoticed.
        if ($this->haversineKm($anchorLat, $anchorLng, $lat, $lng) > $this->radiusKm) {
            $this->lastFailureReason = 'outside_service_area';
            return null;
        }

        $result = [
            'lat' => $lat,
            'lng' => $lng,
            'label' => (string) ($feature['properties']['label'] ?? $address),
            // Reported for display only. See the class comment: this is not a
            // validity signal and nothing here branches on it.
            'confidence' => (float) ($feature['properties']['confidence'] ?? 0.0),
        ];

        if ($this->cacheTtlHours > 0) {
            Cache::put($cacheKey, $result, now()->addHours($this->cacheTtlHours));
        }

        return $result;
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function cacheKey(string $address, float $anchorLat, float $anchorLng): string
    {
        // The address is hashed rather than stored: cache keys surface in logs
        // and dashboards, and these are drivers' home addresses.
        return self::CACHE_PREFIX . sha1(
            mb_strtolower($address) . '|' . round($anchorLat, 2) . ',' . round($anchorLng, 2)
        );
    }
}
