<?php

namespace Tests\Feature;

use App\Contracts\LoadSource\LoadSourceAdapter;
use App\Services\UrbanGoodz\LoadBoard\TrukTekAdapter;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * TrukTek is the only load source Urban Goodz can query with no credentials,
 * so it is the one that actually runs in production today.
 *
 * The fixtures here mirror what the live endpoint returned on 2026-09-09,
 * including the two traps that shaped the adapter: the endpoint ignores the
 * lane parameters it documents, and it uses ratePay=1 as a "no published
 * rate" placeholder.
 */
class TrukTekLoadSourceTest extends TestCase
{
    private function config(): array
    {
        return [
            'enabled' => true,
            'base_url' => 'https://www.truktek.com',
            'timeout' => 30,
        ];
    }

    /**
     * A board page whose rows deliberately span several states and equipment
     * types, the way the real unfiltered endpoint responds.
     */
    private function boardFixture(): array
    {
        return [
            'total' => 10000,
            'loads' => [
                [
                    'loadId' => 'load-tx-van',
                    'octy' => 'Houston', 'ost' => 'TX',
                    'dcty' => 'Dallas', 'dst' => 'TX',
                    'equip' => 'Van',
                    'ratePay' => 800,
                    'pickupDate' => '2026-09-14T04:00:00.000Z',
                    'deliveryDate' => '2026-09-15T04:00:00.000Z',
                    'shipperNm' => 'Test Broker LLC',
                    'weight' => 30000,
                    'length' => 48,
                    'coordinates' => [[-95.3698, 29.7604], [-96.797, 32.7767]],
                    'loadDist' => 240.0,
                    'loadHours' => 4.0,
                    'o2oDist' => 25.0,
                    'grossRpm' => 0,
                ],
                [
                    'loadId' => 'load-ga-van',
                    'octy' => 'Atlanta', 'ost' => 'GA',
                    'dcty' => 'Macon', 'dst' => 'GA',
                    'equip' => 'Van',
                    'ratePay' => 400,
                    'pickupDate' => '2026-09-14T04:00:00.000Z',
                    'deliveryDate' => '2026-09-14T04:00:00.000Z',
                    'shipperNm' => 'Second Broker LLC',
                    'weight' => 12000,
                    'length' => 48,
                    'coordinates' => [[-84.388, 33.749], [-83.6324, 32.8407]],
                    'loadDist' => 80.0,
                    'loadHours' => 1.5,
                    'o2oDist' => 500.0,
                    'grossRpm' => 0,
                ],
                [
                    // ratePay = 1 is the "no published rate" placeholder.
                    'loadId' => 'load-tx-unpriced',
                    'octy' => 'Austin', 'ost' => 'TX',
                    'dcty' => 'Waco', 'dst' => 'TX',
                    'equip' => 'Van',
                    'ratePay' => 1,
                    'pickupDate' => '2026-09-14T04:00:00.000Z',
                    'deliveryDate' => '2026-09-14T04:00:00.000Z',
                    'shipperNm' => 'Unpriced Broker LLC',
                    'weight' => 9000,
                    'length' => 48,
                    'coordinates' => [[-97.7431, 30.2672], [-97.1467, 31.5493]],
                    'loadDist' => 100.0,
                    'loadHours' => 1.8,
                    'o2oDist' => 10.0,
                    'grossRpm' => 0,
                ],
            ],
        ];
    }

    private function fakeBoard(): void
    {
        Http::fake([
            'www.truktek.com/api/loads*' => Http::response($this->boardFixture(), 200),
        ]);
    }

    public function test_adapter_is_configured_without_any_credentials(): void
    {
        $adapter = new TrukTekAdapter($this->config());

        $this->assertTrue(
            $adapter->isConfigured(),
            'TrukTek public board needs no API key; that is the whole reason it is usable today.'
        );
        $this->assertSame('truktek', $adapter->getProviderSlug());
    }

    public function test_adapter_reports_unconfigured_when_disabled(): void
    {
        $adapter = new TrukTekAdapter(['enabled' => false, 'base_url' => 'https://www.truktek.com']);

        $this->assertFalse($adapter->isConfigured());
    }

    public function test_only_the_equipment_parameter_is_sent_upstream(): void
    {
        $this->fakeBoard();

        (new TrukTekAdapter($this->config()))->fetchLoads([
            'equipment_type' => 'reefer',
            'origin_state' => 'TX',
            'origin_city' => 'Houston',
            'min_rate_per_mile' => 2.0,
        ]);

        // The endpoint accepts but silently ignores the lane parameters, so
        // sending them would imply a filter we never actually got.
        Http::assertSent(function ($request) {
            $query = [];
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);

            $this->assertSame('Reefer', $query['freight'] ?? null);
            $this->assertArrayNotHasKey('ost', $query);
            $this->assertArrayNotHasKey('octy', $query);
            $this->assertArrayNotHasKey('gross_rpm', $query);

            return true;
        });
    }

    public function test_lane_filtering_happens_locally(): void
    {
        $this->fakeBoard();

        $loads = (new TrukTekAdapter($this->config()))->fetchLoads(['origin_state' => 'TX']);

        $this->assertCount(2, $loads, 'Only the two TX-origin rows should survive.');
        foreach ($loads as $load) {
            $this->assertSame('TX', $load['ost']);
        }
    }

    public function test_origin_city_filter_is_case_insensitive(): void
    {
        $this->fakeBoard();

        $loads = (new TrukTekAdapter($this->config()))->fetchLoads(['origin_city' => 'houston']);

        $this->assertCount(1, $loads);
        $this->assertSame('load-tx-van', $loads[0]['loadId']);
    }

    public function test_deadhead_filter_uses_origin_distance(): void
    {
        $this->fakeBoard();

        $loads = (new TrukTekAdapter($this->config()))->fetchLoads(['max_deadhead_miles' => 100]);

        $ids = array_column($loads, 'loadId');
        $this->assertContains('load-tx-van', $ids);
        $this->assertNotContains('load-ga-van', $ids, 'The 500-mile deadhead row must be dropped.');
    }

    public function test_placeholder_rate_is_reported_as_null_not_one_dollar(): void
    {
        $adapter = new TrukTekAdapter($this->config());
        $raw = $this->boardFixture()['loads'][2];

        $normalized = $adapter->normalize($raw);

        $this->assertNull(
            $normalized['payout_amount'],
            'ratePay=1 is a placeholder; booking it as a $1 payout would poison every margin calculation.'
        );
        $this->assertNull($normalized['rate_per_mile']);
        $this->assertTrue($normalized['metadata']['truktek_rate_is_placeholder']);
    }

    public function test_rate_per_mile_is_recomputed_not_taken_from_the_api(): void
    {
        $adapter = new TrukTekAdapter($this->config());
        $raw = $this->boardFixture()['loads'][0];

        $normalized = $adapter->normalize($raw);

        // The API reports grossRpm 0 for anonymous callers; 800/240 = 3.3333.
        $this->assertSame(0.0, (float) $raw['grossRpm']);
        $this->assertSame(3.3333, $normalized['rate_per_mile']);
        $this->assertSame(800.0, $normalized['payout_amount']);
    }

    public function test_min_rate_per_mile_filter_uses_the_recomputed_value(): void
    {
        $this->fakeBoard();

        // GA row is 400/80 = $5.00/mi, TX row is 800/240 = $3.33/mi.
        $loads = (new TrukTekAdapter($this->config()))->fetchLoads(['min_rate_per_mile' => 4.0]);

        $this->assertCount(1, $loads);
        $this->assertSame('load-ga-van', $loads[0]['loadId']);
    }

    public function test_normalize_maps_route_polyline_endpoints_to_coordinates(): void
    {
        $adapter = new TrukTekAdapter($this->config());

        $normalized = $adapter->normalize($this->boardFixture()['loads'][0]);

        // coordinates arrive as [lng, lat] pairs.
        $this->assertSame(29.7604, $normalized['origin_lat']);
        $this->assertSame(-95.3698, $normalized['origin_lng']);
        $this->assertSame(32.7767, $normalized['destination_lat']);
        $this->assertSame(-96.797, $normalized['destination_lng']);
        $this->assertSame('Houston', $normalized['origin_city']);
        $this->assertSame('TX', $normalized['origin_state']);
        $this->assertSame(240, (int) $normalized['estimated_duration_minutes']);
        $this->assertSame('van', $normalized['equipment_type']);
        $this->assertSame('Test Broker LLC', $normalized['shipper_name']);
    }

    public function test_source_adapter_is_registered_and_returns_normalized_loads(): void
    {
        $this->fakeBoard();

        $adapter = app(LoadSourceAdapter::class, ['source' => 'truktek']);

        $this->assertSame('truktek', $adapter->sourceKey());
        $this->assertTrue($adapter->isConfigured());
        $this->assertFalse($adapter->supportsBooking(), 'The public board is read-only.');

        $result = $adapter->search(['origin_state' => 'TX', 'max_results' => 10]);

        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['count']);
        $this->assertSame('truktek', $result['source']);
        $this->assertSame('Houston', $result['loads'][0]['origin_city']);
    }

    public function test_source_adapter_fails_closed_when_disabled(): void
    {
        $adapter = new \App\Services\UrbanGoodz\LoadSource\TrukTekLoadSourceAdapter([
            'enabled' => false,
            'base_url' => 'https://www.truktek.com',
        ]);

        $result = $adapter->search([]);

        $this->assertFalse($result['success']);
        $this->assertSame('adapter_not_configured', $result['status']);
        $this->assertSame([], $result['loads']);
    }

    public function test_get_load_reports_clearly_when_a_load_rolled_off_the_board(): void
    {
        $this->fakeBoard();

        $adapter = app(LoadSourceAdapter::class, ['source' => 'truktek']);

        $found = $adapter->getLoad('load-ga-van');
        $this->assertTrue($found['success']);
        $this->assertSame('load-ga-van', $found['load']['external_id']);

        $missing = $adapter->getLoad('load-that-is-gone');
        $this->assertFalse($missing['success']);
        $this->assertStringContainsString('no longer on the public board', $missing['error']);
    }

    public function test_upstream_failure_does_not_throw(): void
    {
        Http::fake([
            'www.truktek.com/api/loads*' => Http::response('gateway timeout', 504),
        ]);

        $result = app(LoadSourceAdapter::class, ['source' => 'truktek'])->search([]);

        // AbstractLoadBoardProvider::get() logs and returns null on failure, so
        // the sourcing layer reports an empty board rather than crashing the
        // dispatcher dashboard.
        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['count']);
    }
}
