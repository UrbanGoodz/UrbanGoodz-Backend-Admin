<?php

namespace Tests\Unit;

use App\Services\UrbanGoodz\Routing\Services\PackageProximityOrder;
use PHPUnit\Framework\TestCase;

class PackageProximityOrderTest extends TestCase
{
    private function p(string $id, ?float $lat, ?float $lng): object
    {
        return (object) ['id' => $id, 'dropoff_lat' => $lat, 'dropoff_lng' => $lng];
    }

    public function test_nearest_to_depot_first_then_a_chain_and_unplaced_last(): void
    {
        // Depot downtown Houston. A and B are close together downtown; C and D are far north, close to each other.
        $pkgs = [
            $this->p('D', 30.20, -95.40), $this->p('B', 29.77, -95.36), $this->p('none', null, null),
            $this->p('C', 30.18, -95.41), $this->p('A', 29.76, -95.37),
        ];
        $out = (new PackageProximityOrder)->order($pkgs, 29.7589, -95.3677);
        $ids = array_map(fn ($r) => $r['package']->id, $out);

        $this->assertSame(['A', 'B', 'C', 'D', 'none'], $ids);
        $this->assertSame([1, 2, 3, 4, 5], array_column($out, 'rank'));
        $this->assertNull($out[4]['miles_from_depot']);
        $this->assertGreaterThan($out[1]['miles_from_depot'], $out[3]['miles_from_depot']);
    }

    public function test_an_empty_list_is_fine(): void
    {
        $this->assertSame([], (new PackageProximityOrder)->order([], 29.7, -95.3));
    }
}
