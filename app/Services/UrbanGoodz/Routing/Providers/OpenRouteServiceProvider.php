<?php

namespace App\Services\UrbanGoodz\Routing\Providers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * OpenRouteService (openrouteservice.org) matrix provider.
 *
 * NOTE: this is the road-routing API. It has nothing to do with the
 * OPENROUTER_* configuration in this repo, which is an LLM gateway
 * (openrouter.ai). The key for this provider is ORS_API_KEY.
 *
 * The key is sent in the Authorization header, never in a query string, so no
 * URL logged by this class can ever leak it.
 */
class OpenRouteServiceProvider
{
    public const PROVIDER_NAME = 'openrouteservice';

    private const METERS_PER_MILE = 1609.344;
    private const CACHE_PREFIX = 'urb_ors_matrix_';

    private string $apiKey;
    private string $baseUrl;
    private string $profile;
    private float $timeoutSeconds;
    private float $connectTimeoutSeconds;
    private int $maxRetries;
    private int $retryBaseDelayMs;
    private int $maxRetryAfterSeconds;
    private int $cacheTtlHours;
    private bool $enabled;

    private int $requestCount = 0;
    private int $retryCount = 0;
    private int $rateLimitCount = 0;
    private int $cacheHitCount = 0;
    private ?string $lastFailureReason = null;

    public function __construct(?array $config = null)
    {
        $cfg = $config ?? config('urban_goodz.openrouteservice', []);

        $this->enabled = (bool) ($cfg['enabled'] ?? false);
        $this->apiKey = (string) ($cfg['api_key'] ?? '');
        $this->baseUrl = rtrim((string) ($cfg['base_url'] ?? 'https://api.openrouteservice.org'), '/');
        $this->profile = (string) ($cfg['profile'] ?? 'driving-car');
        $this->timeoutSeconds = (float) ($cfg['timeout_seconds'] ?? 8);
        $this->connectTimeoutSeconds = (float) ($cfg['connect_timeout_seconds'] ?? 4);
        $this->maxRetries = max(0, (int) ($cfg['max_retries'] ?? 2));
        $this->retryBaseDelayMs = max(0, (int) ($cfg['retry_base_delay_ms'] ?? 250));
        $this->maxRetryAfterSeconds = max(0, (int) ($cfg['max_retry_after_seconds'] ?? 5));
        $this->cacheTtlHours = max(0, (int) ($cfg['cache_ttl_hours'] ?? 24));
    }

    public function isConfigured(): bool
    {
        return $this->enabled && $this->apiKey !== '';
    }

    public function profile(): string
    {
        return $this->profile;
    }

    /**
     * Road distance + duration for a single origin/destination pair.
     *
     * @return array{miles: float, minutes: float}|null null on any failure
     */
    public function pairDistance(float $originLat, float $originLng, float $destLat, float $destLng): ?array
    {
        $matrix = $this->matrix([
            [$originLng, $originLat],
            [$destLng, $destLat],
        ]);

        if ($matrix === null) {
            return null;
        }

        $meters = $matrix['distances'][0][1] ?? null;
        $seconds = $matrix['durations'][0][1] ?? null;

        if (!is_numeric($meters) || !is_numeric($seconds)) {
            $this->lastFailureReason = 'malformed_pair_cell';
            return null;
        }

        return [
            'miles' => round(((float) $meters) / self::METERS_PER_MILE, 2),
            'minutes' => round(((float) $seconds) / 60, 1),
        ];
    }

    /**
     * Full N x N matrix.
     *
     * @param  array<int, array{0: float, 1: float}>  $coordinates  [lng, lat] pairs, ORS order.
     * @return array{distances: array, durations: array}|null null on any failure
     */
    public function matrix(array $coordinates, ?string $profile = null): ?array
    {
        $this->lastFailureReason = null;

        if (!$this->isConfigured()) {
            $this->lastFailureReason = 'not_configured';
            return null;
        }

        $coordinates = array_values($coordinates);
        if (count($coordinates) < 2) {
            $this->lastFailureReason = 'insufficient_coordinates';
            return null;
        }

        $profile = $profile ?: $this->profile;
        $cacheKey = $this->cacheKey($coordinates, $profile);

        if ($this->cacheTtlHours > 0) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && isset($cached['distances'], $cached['durations'])) {
                $this->cacheHitCount++;
                $cached['from_cache'] = true;
                return $cached;
            }
        }

        $payload = $this->request($profile, $coordinates);
        if ($payload === null) {
            return null;
        }

        if ($this->cacheTtlHours > 0) {
            Cache::put($cacheKey, $payload, now()->addHours($this->cacheTtlHours));
        }

        return $payload;
    }

    /**
     * Perform the POST with bounded retries, backoff and 429 handling.
     *
     * @return array{distances: array, durations: array, from_cache: bool}|null
     */
    private function request(string $profile, array $coordinates): ?array
    {
        $endpoint = "{$this->baseUrl}/v2/matrix/{$profile}";
        $safeEndpoint = $this->sanitizeUrl($endpoint);
        $attempts = $this->maxRetries + 1;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $this->requestCount++;

                $response = Http::withHeaders([
                    'Authorization' => $this->apiKey,
                    'Content-Type' => 'application/json; charset=utf-8',
                    'Accept' => 'application/json',
                ])
                    ->connectTimeout($this->connectTimeoutSeconds)
                    ->timeout($this->timeoutSeconds)
                    ->post($endpoint, [
                        'locations' => $coordinates,
                        'metrics' => ['distance', 'duration'],
                        'units' => 'm',
                        'resolve_locations' => false,
                    ]);

                $status = $response->status();

                if ($status === 429) {
                    $this->rateLimitCount++;
                    $this->lastFailureReason = 'rate_limited';
                    $waitSeconds = $this->retryAfterSeconds($response->header('Retry-After'));

                    Log::warning('OpenRouteService: rate limited', [
                        'endpoint' => $safeEndpoint,
                        'status' => 429,
                        'attempt' => $attempt,
                        'retry_after_seconds' => $waitSeconds,
                    ]);

                    if ($attempt >= $attempts) {
                        return null;
                    }

                    $this->retryCount++;
                    $this->sleepMs($waitSeconds !== null
                        ? (int) ($waitSeconds * 1000)
                        : $this->backoffMs($attempt));
                    continue;
                }

                if ($status >= 500 || $status === 408) {
                    $this->lastFailureReason = in_array($status, [502, 503, 504], true)
                        ? "provider_unavailable_{$status}"
                        : "server_error_{$status}";
                    Log::warning('OpenRouteService: server error', [
                        'endpoint' => $safeEndpoint,
                        'status' => $status,
                        'attempt' => $attempt,
                    ]);

                    if ($attempt >= $attempts) {
                        return null;
                    }

                    $this->retryCount++;
                    $this->sleepMs($this->backoffMs($attempt));
                    continue;
                }

                if ($status >= 400) {
                    // 4xx other than 429 are not retryable (bad key, bad coords).
                    $this->lastFailureReason = in_array($status, [401, 403], true)
                        ? "authentication_failed_{$status}"
                        : "client_error_{$status}";
                    Log::warning('OpenRouteService: request rejected', [
                        'endpoint' => $safeEndpoint,
                        'status' => $status,
                    ]);
                    return null;
                }

                return $this->parse($response->json(), count($coordinates), $safeEndpoint);
            } catch (Throwable $e) {
                // Connection/timeout errors surface here.
                $this->lastFailureReason = 'transport_error';
                Log::warning('OpenRouteService: transport failure', [
                    'endpoint' => $safeEndpoint,
                    'attempt' => $attempt,
                    'error' => $this->sanitizeMessage($e->getMessage()),
                ]);

                if ($attempt >= $attempts) {
                    return null;
                }

                $this->retryCount++;
                $this->sleepMs($this->backoffMs($attempt));
            }
        }

        return null;
    }

    /**
     * Validate the response body. Anything unexpected returns null so the
     * caller falls back rather than throwing.
     */
    private function parse(mixed $data, int $expectedSize, string $safeEndpoint): ?array
    {
        if (!is_array($data)) {
            $this->lastFailureReason = 'non_array_body';
            Log::warning('OpenRouteService: unusable response body', [
                'endpoint' => $safeEndpoint,
                'reason' => 'non_array_body',
            ]);
            return null;
        }

        $distances = $data['distances'] ?? null;
        $durations = $data['durations'] ?? null;

        if (!is_array($distances) || !is_array($durations)) {
            $this->lastFailureReason = 'missing_metrics';
            Log::warning('OpenRouteService: unusable response body', [
                'endpoint' => $safeEndpoint,
                'reason' => 'missing_metrics',
            ]);
            return null;
        }

        if (count($distances) !== $expectedSize || count($durations) !== $expectedSize) {
            $this->lastFailureReason = 'matrix_size_mismatch';
            Log::warning('OpenRouteService: matrix size mismatch', [
                'endpoint' => $safeEndpoint,
                'expected' => $expectedSize,
                'distance_rows' => count($distances),
                'duration_rows' => count($durations),
            ]);
            return null;
        }

        foreach ([$distances, $durations] as $grid) {
            foreach ($grid as $row) {
                if (!is_array($row) || count($row) !== $expectedSize) {
                    $this->lastFailureReason = 'malformed_matrix_row';
                    Log::warning('OpenRouteService: malformed matrix row', [
                        'endpoint' => $safeEndpoint,
                    ]);
                    return null;
                }
            }
        }

        return [
            'distances' => $distances,
            'durations' => $durations,
            'from_cache' => false,
        ];
    }

    private function retryAfterSeconds(?string $header): ?float
    {
        if ($header === null || trim($header) === '') {
            return null;
        }

        $header = trim($header);

        if (is_numeric($header)) {
            $seconds = (float) $header;
        } else {
            $timestamp = strtotime($header);
            if ($timestamp === false) {
                return null;
            }
            $seconds = $timestamp - time();
        }

        if ($seconds <= 0) {
            return 0.0;
        }

        return min($seconds, (float) $this->maxRetryAfterSeconds);
    }

    private function backoffMs(int $attempt): int
    {
        // 250, 500, 1000, ... capped so a retry storm can't stall a request.
        return (int) min($this->retryBaseDelayMs * (2 ** ($attempt - 1)), $this->maxRetryAfterSeconds * 1000);
    }

    private function sleepMs(int $ms): void
    {
        if ($ms > 0) {
            usleep($ms * 1000);
        }
    }

    private function cacheKey(array $coordinates, string $profile): string
    {
        $normalized = array_map(
            fn ($c) => round((float) ($c[0] ?? 0), 6) . ',' . round((float) ($c[1] ?? 0), 6),
            $coordinates
        );

        return self::CACHE_PREFIX . md5($profile . '|' . implode(';', $normalized));
    }

    /** Strip any query string so a key can never ride along in a log line. */
    private function sanitizeUrl(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false) {
            return '[unparseable-url]';
        }

        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $path = $parts['path'] ?? '';

        return $host === '' ? $path : "{$scheme}://{$host}{$path}";
    }

    /** Redact the key from any third-party exception text before logging. */
    private function sanitizeMessage(string $message): string
    {
        if ($this->apiKey !== '') {
            $message = str_replace($this->apiKey, '[REDACTED]', $message);
        }

        // Also drop query strings that a Guzzle message may have embedded.
        return (string) preg_replace('/\?[^\s"\']*/', '?[REDACTED_QUERY]', $message);
    }

    public function lastFailureReason(): ?string
    {
        return $this->lastFailureReason;
    }

    public function getStats(): array
    {
        return [
            'request_count' => $this->requestCount,
            'retry_count' => $this->retryCount,
            'rate_limit_count' => $this->rateLimitCount,
            'cache_hit_count' => $this->cacheHitCount,
            'last_failure_reason' => $this->lastFailureReason,
        ];
    }

    public function resetStats(): void
    {
        $this->requestCount = 0;
        $this->retryCount = 0;
        $this->rateLimitCount = 0;
        $this->cacheHitCount = 0;
        $this->lastFailureReason = null;
    }
}
