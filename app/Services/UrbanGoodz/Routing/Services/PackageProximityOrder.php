<?php

namespace App\Services\UrbanGoodz\Routing\Services;

/**
 * Orders scanned packages by proximity: start at the package nearest the depot, then repeatedly hop to the
 * nearest not-yet-listed package. Neighbours in the real world end up neighbours in the list, which is what a
 * vendor needs to see before choosing how many packages go on each route. Packages with no coordinates go last
 * (they cannot be placed), in their original order.
 */
class PackageProximityOrder
{
    /**
     * @param iterable<object> $packages objects with dropoff_lat / dropoff_lng
     * @return array<int, array{package: object, rank: int, miles_from_depot: ?float, miles_from_previous: ?float}>
     */
    public function order(iterable $packages, float $originLat, float $originLng): array
    {
        $placed = [];
        $unplaced = [];
        foreach ($packages as $p) {
            if ($p->dropoff_lat !== null && $p->dropoff_lng !== null && (float) $p->dropoff_lat != 0.0) {
                $placed[] = $p;
            } else {
                $unplaced[] = $p;
            }
        }

        $out = [];
        $curLat = $originLat;
        $curLng = $originLng;
        $rank = 0;
        while ($placed) {
            $bestKey = null;
            $bestDist = INF;
            foreach ($placed as $k => $p) {
                $d = $this->miles($curLat, $curLng, (float) $p->dropoff_lat, (float) $p->dropoff_lng);
                if ($d < $bestDist) {
                    $bestDist = $d;
                    $bestKey = $k;
                }
            }
            $p = $placed[$bestKey];
            unset($placed[$bestKey]);
            $out[] = [
                'package' => $p,
                'rank' => ++$rank,
                'miles_from_depot' => round($this->miles($originLat, $originLng, (float) $p->dropoff_lat, (float) $p->dropoff_lng), 2),
                'miles_from_previous' => round($bestDist, 2),
            ];
            $curLat = (float) $p->dropoff_lat;
            $curLng = (float) $p->dropoff_lng;
        }
        foreach ($unplaced as $p) {
            $out[] = ['package' => $p, 'rank' => ++$rank, 'miles_from_depot' => null, 'miles_from_previous' => null];
        }

        return $out;
    }

    public function miles(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 3958.8;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
