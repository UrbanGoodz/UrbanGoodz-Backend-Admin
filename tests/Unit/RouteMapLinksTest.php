<?php

namespace Tests\Unit;

use App\Services\UrbanGoodz\Routing\Services\RouteMapLinks;
use PHPUnit\Framework\TestCase;

class RouteMapLinksTest extends TestCase
{
    private function stops(int $n, array $doneOrders = [], array $noCoordsOrders = []): array
    {
        $out = [];
        for ($i = 1; $i <= $n; $i++) {
            $out[] = [
                'stop_order' => $i,
                'lat' => in_array($i, $noCoordsOrders, true) ? null : 29.70 + $i / 1000,
                'lng' => in_array($i, $noCoordsOrders, true) ? null : -95.30 - $i / 1000,
                'pending' => ! in_array($i, $doneOrders, true),
            ];
        }

        return $out;
    }

    public function test_each_stop_gets_a_single_navigate_link_and_trips_cover_only_pending_stops_in_order(): void
    {
        $r = (new RouteMapLinks)->build($this->stops(20, doneOrders: [1, 2, 3]));

        $this->assertStringContainsString('destination=29.701,-95.301', $r['stops'][0]['navigate_url']);
        // 17 pending stops in chunks of 9 -> 2 trips (9 + 8)
        $this->assertCount(2, $r['trips']);
        $this->assertSame(range(4, 12), $r['trips'][0]['stop_orders']);
        $this->assertSame(range(13, 20), $r['trips'][1]['stop_orders']);
        $this->assertSame($r['trips'][0], $r['next_trip']);
    }

    public function test_a_trip_url_has_one_destination_and_up_to_eight_waypoints_and_no_origin(): void
    {
        $r = (new RouteMapLinks)->build($this->stops(9));
        $url = $r['next_trip']['url'];

        $this->assertStringContainsString('destination=29.709,-95.309', $url);
        $this->assertSame(8, substr_count($url, '%7C') + 1, 'eight waypoints');
        $this->assertStringNotContainsString('origin=', $url, 'maps starts from the driver\'s own location');
    }

    public function test_stops_without_coordinates_get_no_link_and_are_left_out_of_trips(): void
    {
        $r = (new RouteMapLinks)->build($this->stops(3, noCoordsOrders: [2]));

        $this->assertNull($r['stops'][1]['navigate_url']);
        $this->assertSame([1, 3], $r['trips'][0]['stop_orders']);
    }

    public function test_nothing_pending_means_no_next_trip(): void
    {
        $r = (new RouteMapLinks)->build($this->stops(2, doneOrders: [1, 2]));

        $this->assertNull($r['next_trip']);
        $this->assertSame([], $r['trips']);
    }
}
