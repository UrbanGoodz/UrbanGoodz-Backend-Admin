<?php

namespace Tests\Unit;

use App\Models\UrbanGoodzRoutePackage;
use App\Services\UrbanGoodz\DedicatedRouteOptimizationService;
use App\Services\UrbanGoodz\Routing\DTOs\DistanceResult;
use App\Services\UrbanGoodz\Routing\Services\DistanceMatrixService;
use DomainException;
use PHPUnit\Framework\TestCase;

class DedicatedRouteOptimizationEngineTest extends TestCase
{
    private DedicatedRouteOptimizationService $optimizer;

    protected function setUp(): void
    {
        parent::setUp();
        $distances = new class extends DistanceMatrixService {
            public function __construct() {}

            public function getDistance(string $originLat, string $originLng, string $destLat, string $destLng): DistanceResult
            {
                $miles = hypot((float) $destLat - (float) $originLat, (float) $destLng - (float) $originLng);
                return new DistanceResult(
                    distanceMiles: $miles,
                    durationMinutes: $miles * 2,
                    mode: DistanceResult::MODE_HAVERSINE,
                    provider: 'haversine_fallback/deterministic_test',
                    isFallback: true,
                );
            }
        };
        $this->optimizer = new DedicatedRouteOptimizationService($distances);
    }

    public function test_optimizes_inefficient_five_stop_route_and_preserves_every_stop_once(): void
    {
        $packages = collect([
            $this->package(1, 10, 1, 'A'),
            $this->package(5, 10, 5, 'E'),
            $this->package(2, 10, 2, 'B'),
            $this->package(4, 10, 4, 'D'),
            $this->package(3, 10, 3, 'C'),
        ]);

        $plan = $this->optimizer->plan($packages, ['lat' => 10, 'lng' => 0], ['lat' => 10, 'lng' => 6]);

        self::assertSame([1, 5, 2, 4, 3], $plan['original']->pluck('id')->all());
        self::assertSame([1, 2, 3, 4, 5], $plan['optimized']->pluck('id')->all());
        self::assertSame([1, 2, 3, 4, 5], $plan['optimized']->pluck('id')->sort()->values()->all());
        self::assertSame(14.0, $plan['original_metrics']['miles']);
        self::assertSame(6.0, $plan['optimized_metrics']['miles']);
        self::assertSame(78, $plan['original_metrics']['minutes']);
        self::assertSame(62, $plan['optimized_metrics']['minutes']);
        self::assertLessThan($plan['original_metrics']['miles'], $plan['optimized_metrics']['miles']);
        self::assertSame('constrained_nearest_neighbor+2opt', $plan['method']);
        self::assertSame('HAVERSINE_FALLBACK', $plan['optimized_metrics']['distance_mode']);
    }

    public function test_fixed_end_and_return_to_origin_are_both_measured(): void
    {
        $packages = collect([
            $this->package(3, 10, 3),
            $this->package(1, 10, 1),
            $this->package(2, 10, 2),
        ]);
        $fixedEnd = $this->optimizer->plan($packages, ['lat' => 10, 'lng' => 0], ['lat' => 10, 'lng' => 4]);
        $return = $this->optimizer->plan($packages, ['lat' => 10, 'lng' => 0], ['lat' => 10, 'lng' => 0]);

        self::assertSame(4.0, $fixedEnd['optimized_metrics']['miles']);
        self::assertSame(6.0, $return['optimized_metrics']['miles']);
        self::assertSame(3, $fixedEnd['optimized']->last()->id);
    }

    public function test_duplicate_addresses_remain_distinct_stops(): void
    {
        $packages = collect([
            $this->package(10, 10, 1, 'Same address'),
            $this->package(11, 10, 1, 'Same address'),
            $this->package(12, 10, 2, 'Other address'),
        ]);

        $plan = $this->optimizer->plan($packages, ['lat' => 10, 'lng' => 0]);

        self::assertCount(3, $plan['optimized']);
        self::assertSame([10, 11, 12], $plan['optimized']->pluck('id')->sort()->values()->all());
    }

    public function test_missing_coordinates_fail_without_fabricated_success(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('invalid coordinates');
        $this->optimizer->plan(collect([$this->package(1, 0, 0)]), ['lat' => 10, 'lng' => 0]);
    }

    public function test_zero_stops_fail_and_one_stop_returns_real_metrics(): void
    {
        try {
            $this->optimizer->plan(collect(), ['lat' => 10, 'lng' => 0]);
            self::fail('Zero-stop plan should fail.');
        } catch (DomainException $exception) {
            self::assertStringContainsString('no active stops', $exception->getMessage());
        }

        $plan = $this->optimizer->plan(
            collect([$this->package(1, 10, 2)]),
            ['lat' => 10, 'lng' => 0],
            ['lat' => 10, 'lng' => 3]
        );
        self::assertSame('single_stop', $plan['method']);
        self::assertSame(3.0, $plan['optimized_metrics']['miles']);
        self::assertGreaterThan(0, $plan['optimized_metrics']['minutes']);
    }

    public function test_priority_and_time_window_constraints_are_preserved(): void
    {
        $normal = $this->package(1, 10, 1);
        $normal->priority = 'normal';
        $urgent = $this->package(2, 10, 5);
        $urgent->priority = 'urgent';
        $high = $this->package(3, 10, 4);
        $high->priority = 'high';

        $plan = $this->optimizer->plan(collect([$normal, $urgent, $high]), ['lat' => 10, 'lng' => 0]);

        self::assertSame([2, 3, 1], $plan['optimized']->pluck('id')->all());
    }

    public function test_repeated_optimization_is_deterministic(): void
    {
        $packages = collect([
            $this->package(5, 10, 5),
            $this->package(1, 10, 1),
            $this->package(3, 10, 3),
            $this->package(2, 10, 2),
            $this->package(4, 10, 4),
        ]);

        $first = $this->optimizer->plan($packages, ['lat' => 10, 'lng' => 0], ['lat' => 10, 'lng' => 6]);
        $second = $this->optimizer->plan($packages, ['lat' => 10, 'lng' => 0], ['lat' => 10, 'lng' => 6]);

        self::assertSame($first['optimized']->pluck('id')->all(), $second['optimized']->pluck('id')->all());
        self::assertSame($first['optimized_metrics'], $second['optimized_metrics']);
        self::assertStringContainsString('haversine_fallback', $first['optimized_metrics']['provider']);
    }

    public function test_locked_stop_remains_at_its_required_position(): void
    {
        $far = $this->package(3, 10, 3);
        $locked = $this->package(1, 10, 1);
        $locked->stop_locked = true;
        $locked->locked_stop_order = 2;
        $near = $this->package(2, 10, 2);

        $plan = $this->optimizer->plan(
            collect([$far, $locked, $near]),
            ['lat' => 10, 'lng' => 0]
        );

        self::assertSame(1, $plan['optimized']->values()->get(1)->id);
        self::assertSame([1, 2, 3], $plan['optimized']->pluck('id')->sort()->values()->all());
    }

    public function test_duplicate_locked_positions_fail_instead_of_dropping_a_stop(): void
    {
        $first = $this->package(1, 10, 1);
        $first->stop_locked = true;
        $first->locked_stop_order = 1;
        $second = $this->package(2, 10, 2);
        $second->stop_locked = true;
        $second->locked_stop_order = 1;

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('both require position 1');

        $this->optimizer->plan(collect([$first, $second]), ['lat' => 10, 'lng' => 0]);
    }

    public function test_return_stops_are_sequenced_after_delivery_stops(): void
    {
        $return = $this->package(1, 10, 1);
        $return->status = 'returning_to_business';
        $return->return_required = true;
        $delivery = $this->package(2, 10, 4);

        $plan = $this->optimizer->plan(
            collect([$return, $delivery]),
            ['lat' => 10, 'lng' => 0]
        );

        self::assertSame([2, 1], $plan['optimized']->pluck('id')->all());
    }

    private function package(int $id, float $lat, float $lng, string $address = 'Address'): UrbanGoodzRoutePackage
    {
        $package = new UrbanGoodzRoutePackage();
        $package->setAttribute('id', $id);
        $package->setAttribute('tracking_id', "PKG-{$id}");
        $package->setAttribute('dropoff_lat', $lat);
        $package->setAttribute('dropoff_lng', $lng);
        $package->setAttribute('dropoff_address', $address);
        $package->setAttribute('priority', 'normal');
        return $package;
    }
}
