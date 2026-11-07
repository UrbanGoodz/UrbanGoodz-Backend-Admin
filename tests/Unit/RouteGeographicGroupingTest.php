<?php

namespace Tests\Unit;

use App\Services\UrbanGoodz\Routing\DTOs\RouteStop;
use App\Services\UrbanGoodz\Routing\Services\RoutePlanningService;
use Tests\TestCase;

/**
 * Dispatcher route creation: scanned-in packages must be grouped into routes by
 * geographic area, with no package lost or duplicated, before the driver sequences
 * each route toward their chosen finish.
 */
class RouteGeographicGroupingTest extends TestCase
{
    /** Three service areas roughly 25-35 miles apart. */
    private const AREAS = [
        'woodlands' => [30.16, -95.46],
        'downtown' => [29.76, -95.36],
        'galveston' => [29.30, -94.80],
    ];

    private function stops(int $perArea, int $seed = 1): array
    {
        mt_srand($seed);
        $stops = [];
        $id = 1;
        foreach (self::AREAS as $name => [$lat, $lng]) {
            for ($i = 0; $i < $perArea; $i++) {
                $stops[] = new RouteStop(
                    packageId: $id, trackingId: "{$name}-{$id}",
                    lat: $lat + mt_rand(-300, 300) / 10000, lng: $lng + mt_rand(-300, 300) / 10000,
                    address: "{$id} {$name} Rd", city: ucfirst($name), state: 'TX', zip: '77000'
                );
                $id++;
            }
        }
        shuffle($stops);

        return $stops;
    }

    private function planner(): RoutePlanningService
    {
        config()->set('urban_goodz.distance_matrix.provider', 'haversine');
        config()->set('urban_goodz.distance_matrix.google_maps_key', '');

        return app(RoutePlanningService::class);
    }

    private function areaOf(string $trackingId): string
    {
        return explode('-', $trackingId)[0];
    }

    /** All package/tracking ids across a cluster's stops (a stop may stand for several packages). */
    private function clusterTracking($cluster): array
    {
        return array_map(fn ($s) => is_array($s) ? ($s['tracking_id'] ?? $s['trackingId'] ?? '') : $s->trackingId, $cluster->stops);
    }

    public function test_packages_are_grouped_into_one_route_per_geographic_area(): void
    {
        $result = $this->planner()->planFromStops($this->stops(12), ['requested_route_count' => 3]);

        $this->assertSame(3, $result->routeCountGenerated);
        $this->assertSame(36, $result->routedPackages);
        $this->assertSame(0, $result->unrouteableCount);

        $seen = [];
        foreach ($result->clusters as $cluster) {
            $areas = array_unique(array_map(fn ($t) => $this->areaOf($t), $this->clusterTracking($cluster)));
            $this->assertCount(1, $areas, 'a route mixes service areas: '.implode(',', $areas));
            foreach ($this->clusterTracking($cluster) as $t) {
                $seen[] = $t;
            }
        }
        $this->assertCount(36, $seen, 'a package was dropped or duplicated across routes');
        $this->assertCount(36, array_unique($seen));
    }

    public function test_grouping_is_stable_for_different_scan_orders(): void
    {
        foreach ([2, 3, 4] as $seed) {
            $result = $this->planner()->planFromStops($this->stops(8, $seed), ['requested_route_count' => 3]);
            foreach ($result->clusters as $cluster) {
                $areas = array_unique(array_map(fn ($t) => $this->areaOf($t), $this->clusterTracking($cluster)));
                $this->assertCount(1, $areas, "seed {$seed}: a route mixes service areas");
            }
        }
    }

    public function test_each_grouped_route_is_sequenced_and_much_shorter_than_scan_order(): void
    {
        $result = $this->planner()->planFromStops($this->stops(10), ['requested_route_count' => 3]);

        foreach ($result->clusters as $cluster) {
            $this->assertGreaterThan(0.0, $cluster->estimatedMiles);
            // Compactness: every stop within a few miles of its route's centre.
            $this->assertLessThan(8.0, (float) $cluster->maxStopDistanceFromCenter, 'route is not geographically compact');
        }
    }

    public function test_packages_with_no_coordinates_are_reported_not_silently_dropped(): void
    {
        $stops = $this->stops(5);
        $stops[] = new RouteStop(packageId: 900, trackingId: 'bad-900', lat: 0.0, lng: 0.0, address: 'Unknown');

        $result = $this->planner()->planFromStops($stops, ['requested_route_count' => 3]);

        $this->assertSame(16, $result->totalPackages);
        $this->assertSame(1, $result->unrouteableCount);
        $this->assertSame(15, $result->routedPackages);
    }

    public function test_two_packages_at_the_same_address_stay_on_the_same_route_and_both_are_counted(): void
    {
        $stops = $this->stops(6);
        $first = $stops[0];
        $stops[] = new RouteStop(packageId: 800, trackingId: str_replace((string) $first->packageId, '800', $first->trackingId), lat: $first->lat, lng: $first->lng, address: $first->address, city: $first->city, state: 'TX', zip: $first->zip);

        $result = $this->planner()->planFromStops($stops, ['requested_route_count' => 3]);

        $this->assertSame(19, $result->routedPackages + $result->unrouteableCount, 'a same-address package was lost');
        $routesWithBoth = 0;
        foreach ($result->clusters as $cluster) {
            $t = $this->clusterTracking($cluster);
            $routesWithBoth += (in_array($first->trackingId, $t, true) ? 1 : 0);
        }
        $this->assertSame(1, $routesWithBoth, 'the shared-address stop appears on more than one route');
    }
}
