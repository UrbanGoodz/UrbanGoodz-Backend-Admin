<?php

namespace Tests\Unit;

use App\Services\UrbanGoodz\Routing\DTOs\ClusteringConstraints;
use App\Services\UrbanGoodz\Routing\DTOs\RouteStop;
use App\Services\UrbanGoodz\Routing\Services\RouteSequencingService;
use Tests\TestCase;

/**
 * Independent measurement of the route sequencer against brute-force optimal tours.
 *
 * "Route optimization works" is only true if the ordering is measurably better than
 * the input order and close to optimal, the stops are a valid permutation, locked stops
 * are honoured, and the miles it reports match an independent calculation. This test
 * uses the shipped straight-line (haversine) distance provider, so it certifies the
 * SEQUENCING, not road distances (those need OpenRouteService / Google configured).
 */
class RouteOptimizationQualityTest extends TestCase
{
    private function stop(int $id, float $lat, float $lng, array $over = []): RouteStop
    {
        return new RouteStop(...array_merge([
            'packageId' => $id, 'trackingId' => "T{$id}", 'lat' => $lat, 'lng' => $lng, 'address' => "{$id} Test St",
        ], $over));
    }

    private function miles(array $a, array $b): float
    {
        $r = 3958.8;
        $dLat = deg2rad($b[0] - $a[0]);
        $dLng = deg2rad($b[1] - $a[1]);
        $h = sin($dLat / 2) ** 2 + cos(deg2rad($a[0])) * cos(deg2rad($b[0])) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(min(1, sqrt($h)));
    }

    private function pathMiles(array $points): float
    {
        $sum = 0.0;
        for ($i = 0; $i < count($points) - 1; $i++) {
            $sum += $this->miles($points[$i], $points[$i + 1]);
        }

        return $sum;
    }

    /** Shortest open path through all points (brute force; n <= 8). */
    private function optimalOpenPath(array $points): float
    {
        $best = INF;
        $perm = function (array $items, array $chosen = []) use (&$perm, &$best, $points) {
            if (! $items) {
                $best = min($best, $this->pathMiles(array_map(fn ($i) => $points[$i], $chosen)));

                return;
            }
            foreach ($items as $k => $item) {
                $rest = $items;
                unset($rest[$k]);
                $perm($rest, array_merge($chosen, [$item]));
            }
        };
        $perm(array_keys($points));

        return $best;
    }

    private function stopSet(int $seed, int $n): array
    {
        mt_srand($seed);
        $stops = [];
        for ($i = 1; $i <= $n; $i++) {
            // ~25 x 25 mile box around Houston
            $stops[] = $this->stop($i, 29.76 + (mt_rand(-1800, 1800) / 10000), -95.36 + (mt_rand(-2000, 2000) / 10000));
        }

        return $stops;
    }

    private function sequencer(): RouteSequencingService
    {
        config()->set('urban_goodz.distance_matrix.provider', 'haversine');
        config()->set('urban_goodz.distance_matrix.google_maps_key', '');

        return new RouteSequencingService();
    }

    private function constraints(): ClusteringConstraints
    {
        return new ClusteringConstraints(preserveLockedStops: true, respectTimeWindows: false, serviceTimePerStopMinutes: 5);
    }

    /** Shortest path depot -> all stops (-> depot when $closed). Brute force, n <= 8. */
    private function optimalFromDepot(array $depot, array $points, bool $closed): float
    {
        $best = INF;
        $perm = function (array $items, array $chosen = []) use (&$perm, &$best, $points, $depot, $closed) {
            if (! $items) {
                $path = array_merge([$depot], array_map(fn ($i) => $points[$i], $chosen), $closed ? [$depot] : []);
                $best = min($best, $this->pathMiles($path));

                return;
            }
            foreach ($items as $k => $item) {
                $rest = $items;
                unset($rest[$k]);
                $perm($rest, array_merge($chosen, [$item]));
            }
        };
        $perm(array_keys($points));

        return $best;
    }

    /**
     * @return array{mean: float, max: float, optimal: int, naiveMean: float, mileErr: float, ms: float}
     */
    private function measure(bool $withDepot, bool $closed, int $n = 7, int $runs = 25): array
    {
        $service = $this->sequencer();
        $gaps = $naiveGaps = $mileErrors = $timeMs = [];
        $optimalCount = 0;

        for ($seed = 1; $seed <= $runs; $seed++) {
            $stops = $this->stopSet($seed, $n);
            $points = array_map(fn ($s) => [$s->lat, $s->lng], $stops);
            $depot = [29.76 + (($seed % 7) - 3) * 0.04, -95.36 + (($seed % 5) - 2) * 0.05];
            $start = $withDepot ? ['lat' => $depot[0], 'lng' => $depot[1]] : null;
            $end = $withDepot && $closed ? $start : null;

            $t = microtime(true);
            $result = $service->sequenceRoute($stops, $this->constraints(), null, $start, $end);
            $timeMs[] = (microtime(true) - $t) * 1000;

            $ordered = $result['ordered_stops'];
            $this->assertCount($n, $ordered, "seed {$seed}: stops were dropped or duplicated");
            $this->assertEqualsCanonicalizing(range(1, $n), array_map(fn ($s) => $s->packageId, $ordered), "seed {$seed}: not a permutation of the input");

            $orderedPoints = array_map(fn ($s) => [$s->lat, $s->lng], $ordered);
            $withEnds = fn (array $pts) => $withDepot ? array_merge([$depot], $pts, $closed ? [$depot] : []) : $pts;
            $mine = $this->pathMiles($withEnds($orderedPoints));
            $naive = $this->pathMiles($withEnds($points));
            $optimal = $withDepot ? $this->optimalFromDepot($depot, $points, $closed) : $this->optimalOpenPath($points);

            $gaps[] = ($mine - $optimal) / $optimal * 100;
            $naiveGaps[] = ($naive - $optimal) / $optimal * 100;
            $mileErrors[] = abs($result['total_miles'] - $mine) / max($mine, 0.01) * 100;
            $optimalCount += $mine <= $optimal + 1e-6 ? 1 : 0;
        }

        return [
            'mean' => array_sum($gaps) / $runs, 'max' => max($gaps), 'optimal' => $optimalCount,
            'naiveMean' => array_sum($naiveGaps) / $runs, 'mileErr' => max($mileErrors), 'ms' => array_sum($timeMs) / $runs,
        ];
    }

    private function report(string $label, array $m): void
    {
        if (getenv('UG_OPT_REPORT')) {
            fwrite(STDERR, sprintf(
                "\nOPT %-22s optimal=%2d/25 meanGap=%6.2f%% maxGap=%6.2f%% | inputOrderMeanGap=%6.2f%% | maxMileErr=%.3f%% | meanMs=%.1f\n",
                $label, $m['optimal'], $m['mean'], $m['max'], $m['naiveMean'], $m['mileErr'], $m['ms']
            ));
        }
    }

    public function test_route_from_a_depot_is_close_to_optimal_and_far_better_than_input_order(): void
    {
        $open = $this->measure(true, false);
        $this->report('depot -> stops', $open);
        $closed = $this->measure(true, true);
        $this->report('depot -> stops -> depot', $closed);

        foreach ([['depot start', $open], ['depot round trip', $closed]] as [$label, $m]) {
            $this->assertLessThan($m['naiveMean'] / 2, $m['mean'], "{$label}: not meaningfully better than input order");
            // Measured after the fixes: mean 0.0-0.1%, worst 1.9%. (Before: mean 25-38%, worst 82%.)
            $this->assertLessThanOrEqual(2.0, $m['mean'], "{$label}: mean gap to the optimal tour exceeds 2%");
            $this->assertLessThanOrEqual(8.0, $m['max'], "{$label}: a tour is more than 8% longer than optimal");
            $this->assertLessThanOrEqual(1.5, $m['mileErr'], "{$label}: reported total_miles disagrees with an independent calculation");
        }
    }

    public function test_route_without_a_depot_is_better_than_input_order(): void
    {
        $m = $this->measure(false, false);
        $this->report('no depot (free start)', $m);

        $this->assertLessThan($m['naiveMean'] / 4, $m['mean'], 'no better than input order');
        // Measured: mean 0.4%, worst 5.8% (before: mean 35%, worst 65%).
        $this->assertLessThanOrEqual(3.0, $m['mean'], 'free-start mean gap to optimal exceeds 3%');
        $this->assertLessThanOrEqual(12.0, $m['max']);
        $this->assertLessThanOrEqual(1.5, $m['mileErr']);
    }

    public function test_locked_stops_keep_their_position_ahead_of_optimized_stops(): void
    {
        $service = $this->sequencer();
        $stops = $this->stopSet(99, 6);
        $locked = $this->stop(100, 29.5, -95.9, ['isLocked' => true, 'lockedStopOrder' => 1]);
        $locked2 = $this->stop(101, 30.1, -94.9, ['isLocked' => true, 'lockedStopOrder' => 2]);

        $result = $service->sequenceRoute(array_merge($stops, [$locked2, $locked]), $this->constraints());
        $ids = array_map(fn ($s) => $s->packageId, $result['ordered_stops']);

        $this->assertSame([100, 101], array_slice($ids, 0, 2), 'locked stops must come first, in their locked order');
        $this->assertEqualsCanonicalizing([1, 2, 3, 4, 5, 6, 100, 101], $ids);
    }

    public function test_stops_without_coordinates_are_kept_and_reported_not_dropped(): void
    {
        $service = $this->sequencer();
        $stops = $this->stopSet(7, 4);
        $stops[] = $this->stop(50, 0.0, 0.0);   // invalid coordinates

        $result = $service->sequenceRoute($stops, $this->constraints());

        $this->assertCount(5, $result['ordered_stops'], 'a stop with invalid coordinates must not silently vanish');
        $this->assertContains(50, array_map(fn ($s) => $s->packageId, $result['ordered_stops']));
    }

    public function test_a_single_stop_and_an_empty_route_are_handled(): void
    {
        $service = $this->sequencer();

        $one = $service->sequenceRoute([$this->stop(1, 29.7, -95.3)], $this->constraints());
        $this->assertCount(1, $one['ordered_stops']);
        $this->assertSame(0, (int) $one['total_miles']);

        $none = $service->sequenceRoute([], $this->constraints());
        $this->assertSame([], $none['ordered_stops']);
    }

    public function test_the_result_reports_which_distance_mode_was_used(): void
    {
        $result = $this->sequencer()->sequenceRoute($this->stopSet(3, 5), $this->constraints());

        // Straight-line distances must be labelled as such, never presented as road routing.
        $this->assertStringContainsStringIgnoringCase('haversine', (string) ($result['distance_mode'] ?? ''));
    }

    public function test_a_realistic_route_of_sixty_stops_is_planned_quickly_and_much_shorter_than_input_order(): void
    {
        $service = $this->sequencer();
        $stops = $this->stopSet(2026, 60);
        $depot = ['lat' => 29.76, 'lng' => -95.36];

        $t = microtime(true);
        $result = $service->sequenceRoute($stops, $this->constraints(), null, $depot, $depot);
        $seconds = microtime(true) - $t;

        $ordered = $result['ordered_stops'];
        $this->assertCount(60, $ordered);
        $this->assertEqualsCanonicalizing(range(1, 60), array_map(fn ($s) => $s->packageId, $ordered));

        $loop = fn (array $list) => $this->pathMiles(array_merge(
            [[$depot['lat'], $depot['lng']]], array_map(fn ($s) => [$s->lat, $s->lng], $list), [[$depot['lat'], $depot['lng']]]
        ));
        $mine = $loop($ordered);
        $naive = $loop($stops);

        if (getenv('UG_OPT_REPORT')) {
            fwrite(STDERR, sprintf("\nOPT 60 stops: %.1f mi vs input order %.1f mi (%.0f%% shorter) in %.2fs\n", $mine, $naive, (1 - $mine / $naive) * 100, $seconds));
        }

        $this->assertLessThan($naive * 0.5, $mine, 'a 60-stop route should be far shorter than visiting stops in input order');
        $this->assertLessThan(10.0, $seconds, '60-stop planning is too slow');
    }

    public function test_timing_report_for_large_routes(): void
    {
        if (! getenv('UG_OPT_REPORT')) {
            $this->markTestSkipped('Report only (UG_OPT_REPORT=1).');
        }
        $service = $this->sequencer();
        foreach ([80, 81, 200] as $n) {
            $stops = $this->stopSet(7, $n);
            $t = microtime(true);
            $result = $service->sequenceRoute($stops, $this->constraints(), null, ['lat' => 29.76, 'lng' => -95.36], ['lat' => 29.76, 'lng' => -95.36]);
            fwrite(STDERR, sprintf("\nOPT %d stops planned in %.1fs (%d returned)\n", $n, microtime(true) - $t, count($result['ordered_stops'])));
        }
        $this->assertTrue(true);
    }

    // ------------------------------------------------------------------------------
    // The solver the driver "finish" flow uses (DedicatedRouteOptimizationService).
    // ------------------------------------------------------------------------------

    private function packageSet(int $seed, int $n): \Illuminate\Support\Collection
    {
        return collect($this->stopSet($seed, $n))->map(function (RouteStop $s) {
            $p = new \App\Models\UrbanGoodzRoutePackage([
                'tracking_id' => $s->trackingId, 'dropoff_lat' => $s->lat, 'dropoff_lng' => $s->lng,
                'dropoff_address' => $s->address, 'dropoff_city' => 'Houston', 'dropoff_state' => 'TX', 'dropoff_zip' => '77001',
                'stop_locked' => false,
            ]);
            $p->id = $s->packageId;

            return $p;
        });
    }

    /** @return array{mean: float, max: float, optimal: int, naiveMean: float} */
    private function measureDriverFinish(string $finish, int $n = 7, int $runs = 25): array
    {
        config()->set('urban_goodz.distance_matrix.provider', 'haversine');
        config()->set('urban_goodz.distance_matrix.google_maps_key', '');
        $service = new \App\Services\UrbanGoodz\DedicatedRouteOptimizationService();
        $gaps = $naiveGaps = [];
        $optimalCount = 0;

        for ($seed = 1; $seed <= $runs; $seed++) {
            $packages = $this->packageSet($seed, $n);
            $points = $packages->map(fn ($p) => [(float) $p->dropoff_lat, (float) $p->dropoff_lng])->all();
            $depot = [29.76 + (($seed % 7) - 3) * 0.04, -95.36 + (($seed % 5) - 2) * 0.05];
            // hub = back to the depot; address = a driver-chosen finish (their home); open = wherever the last stop falls
            $home = [29.62 + ($seed % 3) * 0.03, -95.62 + ($seed % 4) * 0.02];
            $end = match ($finish) {
                'hub' => ['lat' => $depot[0], 'lng' => $depot[1]],
                'address' => ['lat' => $home[0], 'lng' => $home[1]],
                default => null,
            };
            $endPoint = match ($finish) { 'hub' => $depot, 'address' => $home, default => null };

            $plan = $service->plan($packages, ['lat' => $depot[0], 'lng' => $depot[1]], $end);

            $orderedIds = $plan['optimized']->pluck('id')->all();
            $this->assertEqualsCanonicalizing(range(1, $n), $orderedIds, "seed {$seed}: not a permutation");

            $byId = $packages->keyBy('id');
            $path = fn (array $ids) => $this->pathMiles(array_merge(
                [$depot],
                array_map(fn ($id) => [(float) $byId[$id]->dropoff_lat, (float) $byId[$id]->dropoff_lng], $ids),
                $endPoint ? [$endPoint] : []
            ));
            $mine = $path($orderedIds);
            $naive = $path($packages->pluck('id')->all());

            $best = INF;
            $perm = function (array $items, array $chosen = []) use (&$perm, &$best, $path) {
                if (! $items) {
                    $best = min($best, $path($chosen));

                    return;
                }
                foreach ($items as $k => $item) {
                    $rest = $items;
                    unset($rest[$k]);
                    $perm($rest, array_merge($chosen, [$item]));
                }
            };
            $perm(range(1, $n));

            $gaps[] = ($mine - $best) / $best * 100;
            $naiveGaps[] = ($naive - $best) / $best * 100;
            $optimalCount += $mine <= $best + 1e-6 ? 1 : 0;
        }

        return ['mean' => array_sum($gaps) / $runs, 'max' => max($gaps), 'optimal' => $optimalCount, 'naiveMean' => array_sum($naiveGaps) / $runs];
    }

    public function test_driver_finish_solver_is_near_optimal_for_every_finish_choice(): void
    {
        foreach (['hub', 'address', 'open'] as $finish) {
            $m = $this->measureDriverFinish($finish);
            if (getenv('UG_OPT_REPORT')) {
                fwrite(STDERR, sprintf(
                    "\nOPT driver finish=%-8s optimal=%2d/25 meanGap=%6.2f%% maxGap=%6.2f%% | inputOrderMeanGap=%6.2f%%\n",
                    $finish, $m['optimal'], $m['mean'], $m['max'], $m['naiveMean']
                ));
            }
            $this->assertLessThan($m['naiveMean'] / 4, $m['mean'], "finish={$finish}: not meaningfully better than input order");
            $this->assertLessThanOrEqual(3.0, $m['mean'], "finish={$finish}: mean gap to optimal exceeds 3%");
            $this->assertLessThanOrEqual(15.0, $m["max"], "finish={$finish}: a route is more than 15% longer than optimal");
        }
    }
}
