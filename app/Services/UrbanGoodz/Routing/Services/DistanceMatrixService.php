<?php

namespace App\Services\UrbanGoodz\Routing\Services;

use App\Services\UrbanGoodz\Routing\DTOs\DistanceResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DistanceMatrixService
{
    private string $provider;
    private string $googleMapsKey;
    private int $cacheTtlHours;
    private int $batchSize;
    private int $requestDelayMs;

    private int $requestCount = 0;
    private int $cacheHitCount = 0;
    private int $cacheMissCount = 0;
    private float $matrixFetchTimeMs = 0;

    public function __construct(?array $config = null)
    {
        $cfg = $config ?? config('urban_goodz.distance_matrix', []);
        $this->provider = $cfg['provider'] ?? 'auto';
        $this->googleMapsKey = $cfg['google_maps_key'] ?? '';

        // The platform already owns working Google Maps credentials, but they
        // live in business_settings (map_api_key_server) where the admin panel
        // manages them — not in the env var this service was reading. The
        // result was every route silently optimising on straight-line distance
        // while a paid key sat unused. Fall back to the stored key so road
        // distances work without duplicating the secret into .env.
        if ($this->googleMapsKey === '') {
            $this->googleMapsKey = (string) $this->storedGoogleKey();
        }

        // 'auto' resolves to road distances when a key is available and to
        // Haversine when it is not, so the mode always reflects reality rather
        // than an aspirational setting.
        // The road-distance gate below tests for the literal 'google_maps',
        // so anything else — including 'google' — silently means Haversine.
        if ($this->provider === 'auto') {
            $this->provider = $this->googleMapsKey !== '' ? 'google_maps' : 'haversine';
        }

        // Accept the shorter spelling as an alias rather than failing closed.
        if ($this->provider === 'google') {
            $this->provider = 'google_maps';
        }
        $this->cacheTtlHours = $cfg['cache_ttl_hours'] ?? 24;
        $this->batchSize = $cfg['batch_size'] ?? 25;
        $this->requestDelayMs = $cfg['request_delay_ms'] ?? 100;
    }

    public function getDistance(string $originLat, string $originLng, string $destLat, string $destLng): DistanceResult
    {
        $pairKey = $this->pairCacheKey($originLat, $originLng, $destLat, $destLng);

        $cached = Cache::get("urb_distance_{$pairKey}");
        if ($cached !== null) {
            $this->cacheHitCount++;
            return DistanceResult::road(
                miles: $cached['miles'],
                minutes: $cached['minutes'],
                provider: 'google_distance_matrix',
                fromCache: true
            );
        }

        $this->cacheMissCount++;

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
            Cache::put("urb_distance_{$pairKey}", [
                'miles' => $result->distanceMiles,
                'minutes' => $result->durationMinutes ?? 0,
            ], now()->addHours($this->cacheTtlHours));
            return $result;
        }

        $miles = $this->haversine(
            (float)$originLat, (float)$originLng,
            (float)$destLat, (float)$destLng
        );
        return DistanceResult::haversine($miles, 'api_error_fallback');
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

    public function getOverallDistanceMode(array $matrix): string
    {
        $modes = [];
        foreach ($matrix as $row) {
            foreach ($row as $cell) {
                if ($cell instanceof DistanceResult && $cell->mode !== 'self') {
                    $modes[$cell->mode] = true;
                }
            }
        }

        if (isset($modes[DistanceResult::MODE_ROAD_MATRIX]) || isset($modes[DistanceResult::MODE_CACHED_ROAD])) {
            return 'ROAD_MATRIX';
        }
        return 'HAVERSINE_FALLBACK';
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
        ];
    }

    /**
     * The Google Maps server key the admin panel manages, cached briefly so a
     * matrix build does not re-query it per pair.
     */
    private function storedGoogleKey(): string
    {
        try {
            return (string) Cache::remember('ug_distance_matrix_google_key', 300, function () {
                return \App\Models\BusinessSetting::where('key', 'map_api_key_server')->value('value')
                    ?: \App\Models\BusinessSetting::where('key', 'map_api_key')->value('value')
                    ?: '';
            });
        } catch (\Throwable $e) {
            // Never let a settings lookup break route planning.
            return '';
        }
    }

    private function pairCacheKey(string $lat1, string $lng1, string $lat2, string $lng2): string
    {
        return md5("{$lat1},{$lng1}_{$lat2},{$lng2}");
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
