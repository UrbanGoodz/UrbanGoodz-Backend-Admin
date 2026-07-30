<?php

namespace Tests\Unit;

use App\Services\UrbanGoodz\Routing\DTOs\DistanceResult;
use App\Services\UrbanGoodz\Routing\Providers\OpenRouteServiceProvider;
use App\Services\UrbanGoodz\Routing\Services\DistanceMatrixService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * OpenRouteService (openrouteservice.org) road-distance provider.
 *
 * Every test uses Http::fake(); nothing here touches the network.
 */
class UrbanGoodzOpenRouteServiceDistanceTest extends TestCase
{
    private const FAKE_KEY = 'test-ors-key-should-never-be-logged';

    // Chicago Loop -> Wrigleyville, roughly.
    private const O_LAT = '41.8781';
    private const O_LNG = '-87.6298';
    private const D_LAT = '41.9484';
    private const D_LNG = '-87.6553';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function service(array $overrides = []): DistanceMatrixService
    {
        return new DistanceMatrixService([
            'provider' => 'openrouteservice',
            'google_maps_key' => '',
            'cache_ttl_hours' => 24,
            'batch_size' => 25,
            'request_delay_ms' => 0,
            'openrouteservice' => array_merge([
                'enabled' => true,
                'api_key' => self::FAKE_KEY,
                'base_url' => 'https://api.openrouteservice.org',
                'profile' => 'driving-car',
                'timeout_seconds' => 3,
                'connect_timeout_seconds' => 2,
                'max_retries' => 0,
                'retry_base_delay_ms' => 0,
                'max_retry_after_seconds' => 0,
                'cache_ttl_hours' => 24,
                'max_locations' => 50,
            ], $overrides),
        ]);
    }

    private function fakeMatrixBody(float $meters = 12874.752, float $seconds = 1320.0): array
    {
        return [
            'distances' => [[0.0, $meters], [$meters, 0.0]],
            'durations' => [[0.0, $seconds], [$seconds, 0.0]],
        ];
    }

    // ---------------------------------------------------------------
    // Success
    // ---------------------------------------------------------------

    public function test_ors_success_returns_road_network_mode_with_road_distance_and_duration(): void
    {
        Http::fake([
            'api.openrouteservice.org/*' => Http::response($this->fakeMatrixBody(), 200),
        ]);

        $result = $this->service()->getDistance(self::O_LAT, self::O_LNG, self::D_LAT, self::D_LNG);

        $this->assertSame(DistanceResult::CALC_ROAD_NETWORK, $result->calculationMode);
        $this->assertTrue($result->isRoadNetwork());
        $this->assertFalse($result->isFallback);
        $this->assertSame(OpenRouteServiceProvider::PROVIDER_NAME, $result->provider);

        // 12874.752 m == exactly 8.00 miles, 1320 s == 22.0 minutes.
        $this->assertSame(8.0, $result->distanceMiles);
        $this->assertSame(22.0, $result->durationMinutes);

        // Road distance must exceed the straight line between the same points.
        $straightLine = $this->service()->haversine(
            (float) self::O_LAT, (float) self::O_LNG,
            (float) self::D_LAT, (float) self::D_LNG
        );
        $this->assertGreaterThan($straightLine, $result->distanceMiles);
    }

    public function test_ors_request_posts_matrix_endpoint_with_both_metrics_and_header_auth(): void
    {
        Http::fake([
            'api.openrouteservice.org/*' => Http::response($this->fakeMatrixBody(), 200),
        ]);

        $this->service()->getDistance(self::O_LAT, self::O_LNG, self::D_LAT, self::D_LNG);

        Http::assertSent(function (Request $request) {
            $body = $request->data();

            $this->assertSame('POST', $request->method());
            $this->assertSame(
                'https://api.openrouteservice.org/v2/matrix/driving-car',
                $request->url()
            );
            $this->assertSame(['distance', 'duration'], $body['metrics']);
            // ORS takes [lng, lat].
            $this->assertEqualsWithDelta(-87.6298, $body['locations'][0][0], 0.0001);
            $this->assertEqualsWithDelta(41.8781, $body['locations'][0][1], 0.0001);
            // Key travels in the header, never in the URL.
            $this->assertSame(self::FAKE_KEY, $request->header('Authorization')[0]);
            $this->assertStringNotContainsString(self::FAKE_KEY, $request->url());

            return true;
        });
    }

    public function test_repeated_lookup_is_served_from_cache_without_a_second_request(): void
    {
        Http::fake([
            'api.openrouteservice.org/*' => Http::response($this->fakeMatrixBody(), 200),
        ]);

        $service = $this->service();
        $first = $service->getDistance(self::O_LAT, self::O_LNG, self::D_LAT, self::D_LNG);
        $second = $service->getDistance(self::O_LAT, self::O_LNG, self::D_LAT, self::D_LNG);

        Http::assertSentCount(1);
        $this->assertSame($first->distanceMiles, $second->distanceMiles);
        $this->assertSame($first->durationMinutes, $second->durationMinutes);
        $this->assertSame(DistanceResult::CALC_ROAD_NETWORK, $second->calculationMode);
        $this->assertTrue($second->fromCache);
    }

    public function test_provider_cache_is_keyed_on_coordinates_and_profile(): void
    {
        Http::fake([
            'api.openrouteservice.org/*' => Http::response($this->fakeMatrixBody(), 200),
        ]);

        $provider = new OpenRouteServiceProvider([
            'enabled' => true,
            'api_key' => self::FAKE_KEY,
            'max_retries' => 0,
            'cache_ttl_hours' => 24,
        ]);

        $provider->matrix([[-87.6298, 41.8781], [-87.6553, 41.9484]], 'driving-car');
        $provider->matrix([[-87.6298, 41.8781], [-87.6553, 41.9484]], 'driving-car');
        Http::assertSentCount(1);

        // Different profile -> different cache entry -> new request.
        $provider->matrix([[-87.6298, 41.8781], [-87.6553, 41.9484]], 'cycling-regular');
        Http::assertSentCount(2);

        // Different coordinates -> different cache entry -> new request.
        $provider->matrix([[-87.6298, 41.8781], [-87.9000, 41.9484]], 'driving-car');
        Http::assertSentCount(3);
    }

    // ---------------------------------------------------------------
    // Failure paths -- all must degrade to HAVERSINE_FALLBACK
    // ---------------------------------------------------------------

    public function test_ors_server_error_falls_back_to_haversine(): void
    {
        Http::fake([
            'api.openrouteservice.org/*' => Http::response('upstream exploded', 500),
        ]);

        $result = $this->service()->getDistance(self::O_LAT, self::O_LNG, self::D_LAT, self::D_LNG);

        $this->assertSame(DistanceResult::CALC_HAVERSINE_FALLBACK, $result->calculationMode);
        $this->assertFalse($result->isRoadNetwork());
        $this->assertTrue($result->isFallback);
        $this->assertStringContainsString('haversine_fallback', $result->provider);
        $this->assertGreaterThan(0, $result->distanceMiles);
    }

    public function test_ors_server_error_is_retried_a_bounded_number_of_times(): void
    {
        Http::fake([
            'api.openrouteservice.org/*' => Http::response('upstream exploded', 500),
        ]);

        $result = $this->service(['max_retries' => 2])
            ->getDistance(self::O_LAT, self::O_LNG, self::D_LAT, self::D_LNG);

        // 1 initial attempt + 2 retries, then stop. Not unbounded.
        Http::assertSentCount(3);
        $this->assertSame(DistanceResult::CALC_HAVERSINE_FALLBACK, $result->calculationMode);
    }

    public function test_malformed_response_body_falls_back_and_does_not_crash(): void
    {
        $bodies = [
            'not json at all',
            json_encode(['unexpected' => 'shape']),
            json_encode(['distances' => 'nope', 'durations' => 'nope']),
            json_encode(['distances' => [[0.0, 100.0]], 'durations' => [[0.0, 60.0]]]), // wrong size
            json_encode(['distances' => [[0.0, 100.0], [100.0]], 'durations' => [[0.0, 60.0], [60.0, 0.0]]]), // ragged row
            json_encode(['distances' => [[0.0, null], [null, 0.0]], 'durations' => [[0.0, null], [null, 0.0]]]), // null cell
        ];

        foreach ($bodies as $index => $body) {
            Cache::flush();
            Http::fake([
                'api.openrouteservice.org/*' => Http::response($body, 200, ['Content-Type' => 'application/json']),
            ]);

            $result = $this->service()->getDistance(self::O_LAT, self::O_LNG, self::D_LAT, self::D_LNG);

            $this->assertSame(
                DistanceResult::CALC_HAVERSINE_FALLBACK,
                $result->calculationMode,
                "Malformed body #{$index} must degrade to HAVERSINE_FALLBACK"
            );
            $this->assertTrue($result->isFallback, "Malformed body #{$index} must be flagged as fallback");
            $this->assertGreaterThan(0, $result->distanceMiles);
        }
    }

    public function test_http_429_rate_limit_is_handled_and_falls_back(): void
    {
        Http::fake([
            'api.openrouteservice.org/*' => Http::response(
                json_encode(['error' => 'rate limit exceeded']),
                429,
                ['Retry-After' => '1']
            ),
        ]);

        $result = $this->service(['max_retries' => 1])
            ->getDistance(self::O_LAT, self::O_LNG, self::D_LAT, self::D_LNG);

        // Retried once (respecting Retry-After, capped at max_retry_after_seconds
        // = 0 in this config so the test does not actually sleep), then gave up.
        Http::assertSentCount(2);
        $this->assertSame(DistanceResult::CALC_HAVERSINE_FALLBACK, $result->calculationMode);
        $this->assertTrue($result->isFallback);
        $this->assertStringContainsString('rate_limited', $result->provider);
    }

    public function test_rate_limit_recovers_when_the_retry_succeeds(): void
    {
        Http::fake([
            'api.openrouteservice.org/*' => Http::sequence()
                ->push(json_encode(['error' => 'rate limit exceeded']), 429, ['Retry-After' => '0'])
                ->push(json_encode($this->fakeMatrixBody()), 200),
        ]);

        $result = $this->service(['max_retries' => 2])
            ->getDistance(self::O_LAT, self::O_LNG, self::D_LAT, self::D_LNG);

        Http::assertSentCount(2);
        $this->assertSame(DistanceResult::CALC_ROAD_NETWORK, $result->calculationMode);
        $this->assertSame(8.0, $result->distanceMiles);
    }

    public function test_timeout_is_handled_and_falls_back(): void
    {
        Http::fake(function () {
            throw new ConnectionException('cURL error 28: Operation timed out after 3000 milliseconds');
        });

        $result = $this->service(['max_retries' => 1])
            ->getDistance(self::O_LAT, self::O_LNG, self::D_LAT, self::D_LNG);

        $this->assertSame(DistanceResult::CALC_HAVERSINE_FALLBACK, $result->calculationMode);
        $this->assertTrue($result->isFallback);
        $this->assertStringContainsString('transport_error', $result->provider);
    }

    public function test_client_error_is_not_retried(): void
    {
        Http::fake([
            'api.openrouteservice.org/*' => Http::response(json_encode(['error' => 'bad key']), 403),
        ]);

        $result = $this->service(['max_retries' => 3])
            ->getDistance(self::O_LAT, self::O_LNG, self::D_LAT, self::D_LNG);

        Http::assertSentCount(1);
        $this->assertSame(DistanceResult::CALC_HAVERSINE_FALLBACK, $result->calculationMode);
        $this->assertStringContainsString('authentication_failed_403', $result->provider);
    }

    public function test_provider_unavailable_is_bounded_then_falls_back(): void
    {
        Http::fake([
            'api.openrouteservice.org/*' => Http::response('maintenance', 503),
        ]);

        $result = $this->service(['max_retries' => 1])
            ->getDistance(self::O_LAT, self::O_LNG, self::D_LAT, self::D_LNG);

        Http::assertSentCount(2);
        $this->assertSame(DistanceResult::CALC_HAVERSINE_FALLBACK, $result->calculationMode);
        $this->assertStringContainsString('provider_unavailable_503', $result->provider);
    }

    public function test_missing_api_key_never_issues_a_request_and_reports_fallback(): void
    {
        Http::fake();

        $result = $this->service(['api_key' => ''])
            ->getDistance(self::O_LAT, self::O_LNG, self::D_LAT, self::D_LNG);

        Http::assertNothingSent();
        $this->assertSame(DistanceResult::CALC_HAVERSINE_FALLBACK, $result->calculationMode);
        $this->assertStringContainsString('ors_not_configured', $result->provider);
    }

    // ---------------------------------------------------------------
    // Logging hygiene
    // ---------------------------------------------------------------

    public function test_failure_logging_never_contains_the_api_key_or_a_query_string(): void
    {
        $captured = [];
        Log::listen(function ($message) use (&$captured) {
            $captured[] = $message->message . ' ' . json_encode($message->context);
        });

        Http::fake([
            'api.openrouteservice.org/*' => Http::response('boom', 500),
        ]);

        $this->service(['max_retries' => 1])
            ->getDistance(self::O_LAT, self::O_LNG, self::D_LAT, self::D_LNG);

        $this->assertNotEmpty($captured, 'expected the provider to log the failure');

        foreach ($captured as $line) {
            $this->assertStringNotContainsString(self::FAKE_KEY, $line);
            $this->assertStringNotContainsString('api_key', $line);
            $this->assertStringNotContainsString('?', $line);
        }
    }

    public function test_transport_exception_text_is_redacted_before_logging(): void
    {
        $captured = [];
        Log::listen(function ($message) use (&$captured) {
            $captured[] = $message->message . ' ' . json_encode($message->context);
        });

        Http::fake(function () {
            throw new ConnectionException(
                'failed to reach https://api.openrouteservice.org/v2/matrix/driving-car?api_key=' . self::FAKE_KEY
            );
        });

        $this->service(['max_retries' => 0])
            ->getDistance(self::O_LAT, self::O_LNG, self::D_LAT, self::D_LNG);

        $this->assertNotEmpty($captured);
        foreach ($captured as $line) {
            $this->assertStringNotContainsString(self::FAKE_KEY, $line);
        }
    }

    // ---------------------------------------------------------------
    // Haversine math and mode integrity
    // ---------------------------------------------------------------

    public function test_haversine_math_is_still_correct(): void
    {
        $service = $this->service();

        // One degree of longitude at the equator on a 3958.8 mi radius sphere.
        $this->assertEqualsWithDelta(69.0933, $service->haversine(0.0, 0.0, 0.0, 1.0), 0.001);

        // Identical points.
        $this->assertSame(0.0, $service->haversine(41.8781, -87.6298, 41.8781, -87.6298));

        // Symmetry.
        $this->assertEqualsWithDelta(
            $service->haversine(41.8781, -87.6298, 41.9484, -87.6553),
            $service->haversine(41.9484, -87.6553, 41.8781, -87.6298),
            0.0000001
        );

        // Known reference: Chicago Loop -> Wrigleyville is about 4.9 straight-line miles.
        $this->assertEqualsWithDelta(
            4.9,
            $service->haversine(41.8781, -87.6298, 41.9484, -87.6553),
            0.2
        );
    }

    public function test_haversine_fallback_duration_uses_the_configured_average_speed(): void
    {
        config(['urban_goodz.planning.default_average_speed_mph' => 30]);

        Http::fake([
            'api.openrouteservice.org/*' => Http::response('boom', 500),
        ]);

        $result = $this->service()->getDistance(self::O_LAT, self::O_LNG, self::D_LAT, self::D_LNG);

        $this->assertEqualsWithDelta(
            ($result->distanceMiles / 30) * 60,
            $result->durationMinutes,
            0.0001
        );
    }

    /**
     * The load-bearing guarantee: no failure path may ever produce a value that
     * a caller could present as a road distance.
     */
    public function test_a_fallback_result_can_never_be_reported_as_a_road_distance(): void
    {
        $failureScenarios = [
            '500' => fn () => Http::fake(['api.openrouteservice.org/*' => Http::response('boom', 500)]),
            '429' => fn () => Http::fake(['api.openrouteservice.org/*' => Http::response('slow down', 429)]),
            '403' => fn () => Http::fake(['api.openrouteservice.org/*' => Http::response('bad key', 403)]),
            'malformed' => fn () => Http::fake(['api.openrouteservice.org/*' => Http::response('{"nope":1}', 200)]),
            'timeout' => fn () => Http::fake(function () {
                throw new ConnectionException('timed out');
            }),
        ];

        foreach ($failureScenarios as $name => $arrange) {
            Cache::flush();
            $arrange();

            $result = $this->service()->getDistance(self::O_LAT, self::O_LNG, self::D_LAT, self::D_LNG);

            $this->assertSame(
                DistanceResult::CALC_HAVERSINE_FALLBACK,
                $result->calculationMode,
                "[{$name}] fallback leaked a non-fallback calculation mode"
            );
            $this->assertFalse($result->isRoadNetwork(), "[{$name}] isRoadNetwork() must be false");
            $this->assertTrue($result->isFallback, "[{$name}] isFallback must be true");
            $this->assertNotSame(
                DistanceResult::MODE_ROAD_NETWORK,
                $result->mode,
                "[{$name}] legacy mode must not claim ROAD_NETWORK"
            );
            $this->assertSame('Straight-line estimate', $result->calculationModeLabel());

            $serialized = $result->toArray();
            $this->assertSame(DistanceResult::CALC_HAVERSINE_FALLBACK, $serialized['calculation_mode']);
            $this->assertFalse($serialized['is_road_network']);
            $this->assertTrue($serialized['is_fallback']);
        }
    }

    public function test_matrix_with_one_estimated_cell_is_not_reported_as_road_network(): void
    {
        $service = $this->service();

        $matrix = [
            [
                new DistanceResult(0, 0, 'self', 'self'),
                DistanceResult::roadNetwork(5.0, 12.0, OpenRouteServiceProvider::PROVIDER_NAME),
            ],
            [
                DistanceResult::haversine(4.2, 'ors_unreachable_cell'),
                new DistanceResult(0, 0, 'self', 'self'),
            ],
        ];

        $this->assertSame(
            DistanceResult::CALC_HAVERSINE_FALLBACK,
            $service->getOverallCalculationMode($matrix)
        );
        $this->assertSame('HAVERSINE_FALLBACK', $service->getOverallDistanceMode($matrix));
    }

    public function test_all_road_matrix_is_reported_as_road_network(): void
    {
        $service = $this->service();

        $matrix = [
            [
                new DistanceResult(0, 0, 'self', 'self'),
                DistanceResult::roadNetwork(5.0, 12.0, OpenRouteServiceProvider::PROVIDER_NAME),
            ],
            [
                DistanceResult::roadNetwork(5.1, 12.4, OpenRouteServiceProvider::PROVIDER_NAME),
                new DistanceResult(0, 0, 'self', 'self'),
            ],
        ];

        $this->assertSame(
            DistanceResult::CALC_ROAD_NETWORK,
            $service->getOverallCalculationMode($matrix)
        );
    }

    public function test_mixed_and_unknown_legacy_mode_strings_never_promote_to_road_network(): void
    {
        $this->assertSame(
            DistanceResult::CALC_HAVERSINE_FALLBACK,
            DistanceResult::deriveCalculationMode('MIXED_ROAD_HAVERSINE')
        );
        $this->assertSame(
            DistanceResult::CALC_HAVERSINE_FALLBACK,
            DistanceResult::deriveCalculationMode('none')
        );
        $this->assertSame(
            DistanceResult::CALC_HAVERSINE_FALLBACK,
            DistanceResult::deriveCalculationMode('something_new_nobody_mapped')
        );
        $this->assertSame(
            DistanceResult::CALC_ROAD_NETWORK,
            DistanceResult::deriveCalculationMode('ROAD_MATRIX')
        );
        $this->assertSame(
            DistanceResult::CALC_MANUAL_ORDER,
            DistanceResult::deriveCalculationMode('MANUAL_ORDER')
        );
    }

    public function test_full_ors_matrix_labels_every_reachable_cell_road_network(): void
    {
        Http::fake([
            'api.openrouteservice.org/*' => Http::response([
                'distances' => [
                    [0.0, 1609.344, 3218.688],
                    [1609.344, 0.0, 1609.344],
                    [3218.688, 1609.344, 0.0],
                ],
                'durations' => [
                    [0.0, 120.0, 240.0],
                    [120.0, 0.0, 120.0],
                    [240.0, 120.0, 0.0],
                ],
            ], 200),
        ]);

        $stops = [
            (object) ['lat' => 41.8781, 'lng' => -87.6298],
            (object) ['lat' => 41.8881, 'lng' => -87.6398],
            (object) ['lat' => 41.8981, 'lng' => -87.6498],
        ];

        $service = $this->service();
        $matrix = $service->buildChunkedMatrix($stops);

        Http::assertSentCount(1);
        $this->assertSame(1.0, $matrix[0][1]->distanceMiles);
        $this->assertSame(2.0, $matrix[0][1]->durationMinutes);
        $this->assertSame(DistanceResult::CALC_ROAD_NETWORK, $matrix[0][1]->calculationMode);
        $this->assertSame(DistanceResult::CALC_SELF, $matrix[0][0]->calculationMode);
        $this->assertSame(DistanceResult::CALC_ROAD_NETWORK, $service->getOverallCalculationMode($matrix));
    }

    public function test_full_matrix_with_an_unreachable_cell_downgrades_the_overall_mode(): void
    {
        Http::fake([
            'api.openrouteservice.org/*' => Http::response([
                'distances' => [
                    [0.0, 1609.344, null],
                    [1609.344, 0.0, 1609.344],
                    [null, 1609.344, 0.0],
                ],
                'durations' => [
                    [0.0, 120.0, null],
                    [120.0, 0.0, 120.0],
                    [null, 120.0, 0.0],
                ],
            ], 200),
        ]);

        $stops = [
            (object) ['lat' => 41.8781, 'lng' => -87.6298],
            (object) ['lat' => 41.8881, 'lng' => -87.6398],
            (object) ['lat' => 41.8981, 'lng' => -87.6498],
        ];

        $service = $this->service();
        $matrix = $service->buildChunkedMatrix($stops);

        $this->assertSame(DistanceResult::CALC_HAVERSINE_FALLBACK, $matrix[0][2]->calculationMode);
        $this->assertTrue($matrix[0][2]->isFallback);
        $this->assertSame(DistanceResult::CALC_HAVERSINE_FALLBACK, $service->getOverallCalculationMode($matrix));
    }

    public function test_non_ors_provider_configuration_is_untouched(): void
    {
        Http::fake();

        $service = new DistanceMatrixService([
            'provider' => 'haversine',
            'google_maps_key' => '',
        ]);

        $result = $service->getDistance(self::O_LAT, self::O_LNG, self::D_LAT, self::D_LNG);

        Http::assertNothingSent();
        $this->assertSame(DistanceResult::MODE_HAVERSINE, $result->mode);
        $this->assertSame(DistanceResult::CALC_HAVERSINE_FALLBACK, $result->calculationMode);
    }
}
