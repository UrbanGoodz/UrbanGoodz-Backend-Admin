<?php

namespace App\Services\UrbanGoodz\Routing\Services;

use App\Services\UrbanGoodz\Routing\DTOs\DistanceResult;
use App\Services\UrbanGoodz\Routing\Providers\OpenRouteServiceProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DistanceMatrixService
{
    public const PROVIDER_OPENROUTESERVICE = 'openrouteservice';

    private string $provider;
    private string $googleMapsKey;
    private int $cacheTtlHours;
    private int $batchSize;
    private int $requestDelayMs;
    private ?OpenRouteServiceProvider $ors;
    private int $orsMaxLocations;

    private int $requestCount = 0;
    private int $cacheHitCount = 0;
    private int $cacheMissCount = 0;
    private float $matrixFetchTimeMs = 0;

    public function __construct(?array $config = null, ?OpenRouteServiceProvider $ors = null)
    {
        $cfg = $config ?? config('urban_goodz.distance_matrix', []);
        $this->provider = $cfg['provider'] ?? 'haversine';
        $this->googleMapsKey = $cfg['google_maps_key'] ?? '';
        $this->cacheTtlHours = $cfg['cache_ttl_hours'] ?? 24;
        $this->batchSize = $cfg['batch_size'] ?? 25;
        $this->requestDelayMs = $cfg['request_delay_ms'] ?? 100;
        $orsConfig = $cfg['openrouteservice'] ?? config('urban_goodz.openrouteservice', []);
        $this->ors = $ors ?? new OpenRouteServiceProvider($orsConfig);
        $this->orsMaxLocations = max(2, (int) ($orsConfig['max_locations'] ?? 50));
    }

    /** True when OpenRouteService is the selected, usable provider. */
    public function usesOpenRouteService(): bool
    {
        return $this->isOpenRouteServiceSelected()
            && $this->ors !== null
            && $this->ors->isConfigured();
    }

    private function isOpenRouteServiceSelected(): bool
    {
        return $this->provider === self::PROVIDER_OPENROUTESERVICE;
    }

    public function getDistance(string $originLat, string $originLng, string $destLat, string $destLng): DistanceResult
    {
        $pairKey = $this->pairCacheKey($originLat, $originLng, $destLat, $destLng);

        $cached = Cache::get("urb_distance_{$pairKey}");
        if (is_array($cached)
            && is_numeric($cached['miles'] ?? null)
            && is_numeric($cached['minutes'] ?? null)) {
            $this->cacheHitCount++;
            $cachedProvider = $cached['provider'] ?? 'google_distance_matrix';

            // Only road results are ever written to this cache, so a hit is
            // always ROAD_NETWORK -- but report the provider that produced it.
            return $cachedProvider === OpenRouteServiceProvider::PROVIDER_NAME
                ? DistanceResult::roadNetwork(
                    miles: $cached['miles'],
                    minutes: $cached['minutes'],
                    provider: $cachedProvider,
                    fromCache: true
                )
                : DistanceResult::road(
                    miles: $cached['miles'],
                    minutes: $cached['minutes'],
                    provider: $cachedProvider,
                    fromCache: true
                );
        }

        $this->cacheMissCount++;

        if ($this->isOpenRouteServiceSelected()) {
            if (!$this->usesOpenRouteService()) {
                $miles = $this->haversine(
                    (float)$originLat, (float)$originLng,
                    (float)$destLat, (float)$destLng
                );

                return DistanceResult::haversine($miles, 'ors_not_configured');
            }

            $result = $this->fetchOpenRouteServicePair(
                (float)$originLat, (float)$originLng,
                (float)$destLat, (float)$destLng
            );

            if ($result !== null) {
                $this->cachePair($pairKey, $result);
                return $result;
            }

            $miles = $this->haversine(
                (float)$originLat, (float)$originLng,
                (float)$destLat, (float)$destLng
            );

            return DistanceResult::haversine(
                $miles,
                'ors_' . ($this->ors?->lastFailureReason() ?? 'unavailable')
            );
        }

        if ($this->provider !== 'google_maps' || empty($this->googleMapsKey)) {
            $miles = $this->haversine(
                (float)$originLat, (float)$originLng,
                (float)$destLat, (float)$destLng
            );
            return DistanceResult::haversine($miles, empty($this->googleMapsKey) ? 'no_api_key' : 'provider_haversine');
        }

        $result = $this->fetchGoogleDistanceMatrix(
            $originLat, $originLng, $destLat, $destLng
        );

        if ($result !== null) {
            $this->cachePair($pairKey, $result);
            return $result;
        }

        $miles = $this->haversine(
            (float)$originLat, (float)$originLng,
            (float)$destLat, (float)$destLng
        );
        return DistanceResult::haversine($miles, 'api_error_fallback');
    }

    private function cachePair(string $pairKey, DistanceResult $result): void
    {
        Cache::put("urb_distance_{$pairKey}", [
            'miles' => $result->distanceMiles,
            'minutes' => $result->durationMinutes ?? 0,
            'provider' => $result->provider,
        ], now()->addHours($this->cacheTtlHours));
    }

    private function fetchOpenRouteServicePair(float $oLat, float $oLng, float $dLat, float $dLng): ?DistanceResult
    {
        if ($this->ors === null) {
            return null;
        }

        $this->requestCount++;
        $pair = $this->ors->pairDistance($oLat, $oLng, $dLat, $dLng);

        if ($pair === null) {
            return null;
        }

        return DistanceResult::roadNetwork(
            $pair['miles'],
            $pair['minutes'],
            OpenRouteServiceProvider::PROVIDER_NAME
        );
    }

    public function buildFullMatrix(array $stops): array
    {
        $startMs = microtime(true);

        $n = count($stops);
        $matrix = [];

        if ($n === 0) return $matrix;

        for ($i = 0; $i < $n; $i++) {
            $matrix[$i] = [];
            for ($j = 0; $j < $n; $j++) {
                if ($i === $j) {
                    $matrix[$i][$j] = new DistanceResult(
                        distanceMiles: 0,
                        durationMinutes: 0,
                        mode: 'self',
                        provider: 'self',
                    );
                } else {
                    $matrix[$i][$j] = $this->getDistance(
                        (string)$stops[$i]->lat, (string)$stops[$i]->lng,
                        (string)$stops[$j]->lat, (string)$stops[$j]->lng
                    );
                }
            }
        }

        $this->matrixFetchTimeMs = (microtime(true) - $startMs) * 1000;

        return $matrix;
    }

    public function buildPairwiseMatrix(array $stops, int $originIndex = 0): array
    {
        $startMs = microtime(true);

        $n = count($stops);
        $matrix = [];

        if ($n === 0) return $matrix;

        for ($i = 0; $i < $n; $i++) {
            if ($i === $originIndex) {
                $matrix[$i] = array_fill(0, $n, new DistanceResult(0, 0, 'self', 'self'));
                continue;
            }
            $matrix[$i] = [];
            for ($j = 0; $j < $n; $j++) {
                if ($i === $j) {
                    $matrix[$i][$j] = new DistanceResult(0, 0, 'self', 'self');
                } else {
                    $matrix[$i][$j] = $this->getDistance(
                        (string)$stops[$i]->lat, (string)$stops[$i]->lng,
                        (string)$stops[$j]->lat, (string)$stops[$j]->lng
                    );
                }
            }
        }

        $this->matrixFetchTimeMs = (microtime(true) - $startMs) * 1000;

        return $matrix;
    }

    public function buildChunkedMatrix(array $stops, ?callable $onProgress = null): array
    {
        if ($this->usesOpenRouteService()) {
            $orsMatrix = $this->buildOpenRouteServiceMatrix($stops, $onProgress);
            if ($orsMatrix !== null) {
                return $orsMatrix;
            }
            // ORS failed outright; fall through to the per-pair path, which
            // degrades to Haversine and labels every cell accordingly.
        }

        $startMs = microtime(true);
        $n = count($stops);
        $matrix = array_fill(0, $n, array_fill(0, $n, null));

        for ($i = 0; $i < $n; $i++) {
            $matrix[$i][$i] = new DistanceResult(0, 0, 'self', 'self');
        }

        $pairs = [];
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $pairs[] = [$i, $j];
            }
        }

        $chunks = array_chunk($pairs, $this->batchSize * $this->batchSize);
        $totalChunks = count($chunks);

        foreach ($chunks as $chunkIdx => $chunk) {
            $origins = [];
            $destinations = [];
            $originIdxs = [];
            $destIdxs = [];

            foreach ($chunk as [$oi, $di]) {
                if (!isset($originIdxs[$oi])) {
                    $originIdxs[$oi] = true;
                    $origins[] = "{$stops[$oi]->lat},{$stops[$oi]->lng}";
                }
                if (!isset($destIdxs[$di])) {
                    $destIdxs[$di] = true;
                    $destinations[] = "{$stops[$di]->lat},{$stops[$di]->lng}";
                }
            }

            $result = $this->fetchBatchGoogleMatrix($origins, $destinations);

            if ($result !== null) {
                foreach ($chunk as [$oi, $di]) {
                    $originKey = array_search("{$stops[$oi]->lat},{$stops[$oi]->lng}", $origins);
                    $destKey = array_search("{$stops[$di]->lat},{$stops[$di]->lng}", $destinations);
                    $pairResult = $result[$originKey][$destKey] ?? null;

                    if ($pairResult) {
                        $dr = DistanceResult::road($pairResult['miles'], $pairResult['minutes'], 'google_distance_matrix');
                        $matrix[$oi][$di] = $dr;
                        $matrix[$di][$oi] = new DistanceResult(
                            $pairResult['miles'],
                            $pairResult['minutes'],
                            'ROAD_MATRIX_REVERSE',
                            'google_distance_matrix'
                        );

                        $pairKey = $this->pairCacheKey(
                            (string)$stops[$oi]->lat, (string)$stops[$oi]->lng,
                            (string)$stops[$di]->lat, (string)$stops[$di]->lng
                        );
                        Cache::put("urb_distance_{$pairKey}", $pairResult, now()->addHours($this->cacheTtlHours));
                    }
                }
            }

            if ($onProgress) {
                $onProgress($chunkIdx + 1, $totalChunks);
            }

            if ($chunkIdx < $totalChunks - 1 && $this->requestDelayMs > 0) {
                usleep($this->requestDelayMs * 1000);
            }
        }

        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                if ($matrix[$i][$j] === null) {
                    $miles = $this->haversine(
                        (float)$stops[$i]->lat, (float)$stops[$i]->lng,
                        (float)$stops[$j]->lat, (float)$stops[$j]->lng
                    );
                    $matrix[$i][$j] = DistanceResult::haversine($miles, 'chunked_batch_fallback');
                }
            }
        }

        $this->matrixFetchTimeMs = (microtime(true) - $startMs) * 1000;

        return $matrix;
    }

    /**
     * One ORS matrix call covers the whole stop set (N x N distances AND
     * durations), which is what the matrix endpoint is for. Returns null when
     * the call fails or the set is too large for a single request, so the
     * caller can degrade.
     *
     * @return array<int, array<int, DistanceResult>>|null
     */
    private function buildOpenRouteServiceMatrix(array $stops, ?callable $onProgress = null): ?array
    {
        $startMs = microtime(true);
        $n = count($stops);

        if ($n === 0) {
            return [];
        }
        if ($n < 2 || $n > $this->orsMaxLocations) {
            return null;
        }

        $coordinates = [];
        foreach ($stops as $stop) {
            $coordinates[] = [(float) $stop->lng, (float) $stop->lat];
        }

        $this->requestCount++;
        $this->cacheMissCount++;
        $payload = $this->ors?->matrix($coordinates);

        if ($payload === null) {
            return null;
        }

        $matrix = [];
        for ($i = 0; $i < $n; $i++) {
            $matrix[$i] = [];
            for ($j = 0; $j < $n; $j++) {
                if ($i === $j) {
                    $matrix[$i][$j] = new DistanceResult(0, 0, 'self', 'self');
                    continue;
                }

                $meters = $payload['distances'][$i][$j] ?? null;
                $seconds = $payload['durations'][$i][$j] ?? null;

                if (is_numeric($meters) && is_numeric($seconds)) {
                    $matrix[$i][$j] = DistanceResult::roadNetwork(
                        round(((float) $meters) / 1609.344, 2),
                        round(((float) $seconds) / 60, 1),
                        OpenRouteServiceProvider::PROVIDER_NAME,
                        (bool) ($payload['from_cache'] ?? false)
                    );
                } else {
                    // ORS returns null for unreachable cells. That single cell
                    // is an estimate and is labelled as one.
                    $matrix[$i][$j] = DistanceResult::haversine(
                        $this->haversine(
                            (float) $stops[$i]->lat, (float) $stops[$i]->lng,
                            (float) $stops[$j]->lat, (float) $stops[$j]->lng
                        ),
                        'ors_unreachable_cell'
                    );
                }
            }
        }

        if ($onProgress) {
            $onProgress(1, 1);
        }

        $this->matrixFetchTimeMs = (microtime(true) - $startMs) * 1000;

        return $matrix;
    }

    public function getOverallDistanceMode(array $matrix): string
    {
        // Keep the legacy return labels, but apply the canonical all-or-nothing
        // rule so a mixed matrix can never be advertised as road distance.
        return $this->getOverallCalculationMode($matrix) === DistanceResult::CALC_ROAD_NETWORK
            ? 'ROAD_MATRIX'
            : 'HAVERSINE_FALLBACK';
    }

    /**
     * Canonical calculation mode for a whole matrix. Returns ROAD_NETWORK only
     * when every non-self cell really came from the road network; a single
     * fallback cell downgrades the whole matrix to HAVERSINE_FALLBACK so no
     * aggregate can be presented as a road distance when it partly is not.
     */
    public function getOverallCalculationMode(array $matrix): string
    {
        $sawRoad = false;

        foreach ($matrix as $row) {
            foreach ($row as $cell) {
                if (!$cell instanceof DistanceResult) {
                    continue;
                }
                if ($cell->calculationMode === DistanceResult::CALC_SELF) {
                    continue;
                }
                if ($cell->calculationMode !== DistanceResult::CALC_ROAD_NETWORK) {
                    return DistanceResult::CALC_HAVERSINE_FALLBACK;
                }
                $sawRoad = true;
            }
        }

        return $sawRoad
            ? DistanceResult::CALC_ROAD_NETWORK
            : DistanceResult::CALC_HAVERSINE_FALLBACK;
    }

    public function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusMiles = 3958.8;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) ** 2;

        $c = 2 * asin(sqrt($a));
        return $earthRadiusMiles * $c;
    }

    public function getStats(): array
    {
        $total = $this->cacheHitCount + $this->cacheMissCount;
        return [
            'request_count' => $this->requestCount,
            'cache_hit_count' => $this->cacheHitCount,
            'cache_miss_count' => $this->cacheMissCount,
            'cache_hit_rate' => $total > 0 ? round($this->cacheHitCount / $total * 100, 2) : 0,
            'matrix_fetch_time_ms' => round($this->matrixFetchTimeMs, 2),
            'provider' => $this->provider,
            'openrouteservice' => $this->ors?->getStats(),
        ];
    }

    private function pairCacheKey(string $lat1, string $lng1, string $lat2, string $lng2): string
    {
        $profile = $this->isOpenRouteServiceSelected() && $this->ors !== null
            ? $this->ors->profile()
            : 'driving';

        return md5("{$this->provider}|{$profile}|{$lat1},{$lng1}_{$lat2},{$lng2}");
    }

    private function fetchGoogleDistanceMatrix(string $oLat, string $oLng, string $dLat, string $dLng): ?DistanceResult
    {
        try {
            $this->requestCount++;
            $response = Http::timeout(10)->get(
                "https://maps.googleapis.com/maps/api/distancematrix/json",
                [
                    'origins' => "{$oLat},{$oLng}",
                    'destinations' => "{$dLat},{$dLng}",
                    'key' => $this->googleMapsKey,
                    'units' => 'imperial',
                    'mode' => 'driving',
                ]
            );

            if ($response->failed()) return null;

            $data = $response->json();
            if (($data['status'] ?? '') !== 'OK') return null;

            $element = $data['rows'][0]['elements'][0] ?? null;
            if (!$element || ($element['status'] ?? '') !== 'OK') return null;

            $meters = $element['distance']['value'] ?? 0;
            $seconds = $element['duration']['value'] ?? 0;

            return DistanceResult::road(
                round($meters / 1609.344, 2),
                round($seconds / 60, 1),
                'google_distance_matrix'
            );
        } catch (\Exception $e) {
            Log::warning('DistanceMatrixService: Google API error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function fetchBatchGoogleMatrix(array $origins, array $destinations): ?array
    {
        try {
            $this->requestCount++;
            $response = Http::timeout(15)->get(
                "https://maps.googleapis.com/maps/api/distancematrix/json",
                [
                    'origins' => implode('|', $origins),
                    'destinations' => implode('|', $destinations),
                    'key' => $this->googleMapsKey,
                    'units' => 'imperial',
                    'mode' => 'driving',
                ]
            );

            if ($response->failed()) return null;

            $data = $response->json();
            if (($data['status'] ?? '') !== 'OK') return null;

            $result = [];
            foreach ($data['rows'] as $rowIdx => $row) {
                $result[$rowIdx] = [];
                foreach ($row['elements'] as $elIdx => $element) {
                    if (($element['status'] ?? '') === 'OK') {
                        $result[$rowIdx][$elIdx] = [
                            'miles' => round(($element['distance']['value'] ?? 0) / 1609.344, 2),
                            'minutes' => round(($element['duration']['value'] ?? 0) / 60, 1),
                        ];
                    } else {
                        $result[$rowIdx][$elIdx] = null;
                    }
                }
            }

            return $result;
        } catch (\Exception $e) {
            Log::warning('DistanceMatrixService: Batch API error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function resetStats(): void
    {
        $this->requestCount = 0;
        $this->cacheHitCount = 0;
        $this->cacheMissCount = 0;
        $this->matrixFetchTimeMs = 0;
    }
}
