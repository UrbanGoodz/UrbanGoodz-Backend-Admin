<?php

namespace App\Services\UrbanGoodz\Routing\Services;

use App\Services\UrbanGoodz\Routing\DTOs\ClusteringConstraints;
use App\Services\UrbanGoodz\Routing\DTOs\DistanceResult;
use App\Services\UrbanGoodz\Routing\DTOs\RouteStop;
use Illuminate\Support\Facades\Log;

class RouteSequencingService
{
    private DistanceMatrixService $distanceMatrix;
    private int $max2OptIterations;

    public function __construct(?DistanceMatrixService $distanceMatrix = null)
    {
        $this->distanceMatrix = $distanceMatrix ?? new DistanceMatrixService();
        $cfg = config('urban_goodz.sequencing', []);
        $this->max2OptIterations = $cfg['max_2opt_iterations'] ?? 50;
    }

    public function sequenceRoute(
        array $stops,
        ClusteringConstraints $constraints,
        ?int $originIndex = null,
        ?array $startLocation = null,
        ?array $endLocation = null
    ): array {
        if (count($stops) <= 1) {
            return [
                'ordered_stops' => $stops,
                'total_miles' => 0,
                'estimated_duration_minutes' => 0,
                'algorithm' => 'single_stop',
                'distance_mode' => 'none',
            ];
        }

        $validStops = [];
        $invalidStops = [];
        foreach ($stops as $idx => $stop) {
            if ($stop->hasValidCoordinates()) {
                $validStops[] = $stop;
            } else {
                $invalidStops[] = $stop;
            }
        }

        if (empty($validStops)) {
            return $this->returnInvalidStopsResult($stops);
        }

        $lockedStops = array_values(array_filter($validStops, fn($s) => $s->isLocked && $constraints->preserveLockedStops));
        $unlockedStops = array_values(array_filter($validStops, fn($s) => !($s->isLocked && $constraints->preserveLockedStops)));

        $sortedLocked = $this->sortLockedStops($lockedStops);

        if (empty($unlockedStops)) {
            $ordered = array_merge($sortedLocked, $invalidStops);
            return $this->computeRouteMetrics($ordered, $constraints, 'HAVERSINE_FALLBACK', $startLocation, $endLocation);
        }

        $matrix = $this->distanceMatrix->buildPairwiseMatrix($unlockedStops, 0);
        $initialOrder = $this->nearestFeasibleNeighbor($unlockedStops, $matrix, $constraints);
        $optimizedOrder = $this->twoOptImprovement($initialOrder, $matrix, $constraints, $startLocation, $endLocation);

        $ordered = array_merge($sortedLocked, $optimizedOrder, $invalidStops);
        $distanceMode = $this->distanceMatrix->getOverallDistanceMode($matrix);

        return $this->computeRouteMetrics($ordered, $constraints, $distanceMode, $startLocation, $endLocation);
    }

    public function sequenceAllRoutes(array $clusteredStops, ClusteringConstraints $constraints): array
    {
        $results = [];

        foreach ($clusteredStops as $clusterIdx => $clusterStops) {
            $results[$clusterIdx] = $this->sequenceRoute($clusterStops, $constraints);
        }

        return $results;
    }

    private function sortLockedStops(array $lockedStops): array
    {
        if (empty($lockedStops)) return [];

        $withOrder = array_filter($lockedStops, fn($s) => $s->lockedStopOrder !== null);
        $withoutOrder = array_filter($lockedStops, fn($s) => $s->lockedStopOrder === null);

        usort($withOrder, fn($a, $b) => $a->lockedStopOrder <=> $b->lockedStopOrder);

        return array_merge($withOrder, $withoutOrder);
    }

    private function nearestFeasibleNeighbor(array $stops, array $matrix, ClusteringConstraints $constraints): array
    {
        $n = count($stops);
        if ($n <= 1) return $stops;

        $ordered = [];
        $visited = array_fill(0, $n, false);
        $currentTime = 0.0;

        $startIdx = $this->findOptimalStart($stops, $constraints);
        $ordered[] = $stops[$startIdx];
        $visited[$startIdx] = true;

        $currentIdx = $startIdx;

        while (count($ordered) < $n) {
            $bestIdx = -1;
            $bestScore = PHP_FLOAT_MAX;

            foreach ($stops as $idx => $stop) {
                if ($visited[$idx]) continue;

                if (!$this->isFeasible($stop, $constraints)) continue;

                $distResult = $matrix[$currentIdx][$idx] ?? null;
                if ($distResult === null) continue;

                $score = $this->computeNeighborScore($distResult, $stop, $currentTime, $constraints);

                if ($score < $bestScore) {
                    $bestScore = $score;
                    $bestIdx = $idx;
                }
            }

            if ($bestIdx === -1) break;

            $ordered[] = $stops[$bestIdx];
            $visited[$bestIdx] = true;
            $currentIdx = $bestIdx;

            $distResult = $matrix[$currentIdx][$bestIdx] ?? null;
            $currentTime += ($distResult?->durationMinutes ?? 0) + $constraints->serviceTimePerStopMinutes;

            if ($this->needsBreak($currentTime, $constraints)) {
                $currentTime += $constraints->breakDurationMinutes;
            }
        }

        return $ordered;
    }

    private function twoOptImprovement(
        array $stops,
        array $matrix,
        ClusteringConstraints $constraints,
        ?array $startLocation = null,
        ?array $endLocation = null
    ): array {
        $n = count($stops);
        if ($n <= 3) return $stops;

        $bestOrder = $stops;
        $bestDistance = $this->calculateTotalDistance($bestOrder, $matrix, $startLocation, $endLocation);

        for ($iter = 0; $iter < $this->max2OptIterations; $iter++) {
            $improved = false;

            for ($i = 1; $i < $n - 1; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $newOrder = $this->twoOptSwap($bestOrder, $i, $j);
                    $newDistance = $this->calculateTotalDistance($newOrder, $matrix, $startLocation, $endLocation);

                    if ($newDistance < $bestDistance - 0.001) {
                        $bestOrder = $newOrder;
                        $bestDistance = $newDistance;
                        $improved = true;
                    }
                }
            }

            if (!$improved) break;
        }

        return $bestOrder;
    }

    private function twoOptSwap(array $order, int $i, int $j): array
    {
        $newOrder = $order;
        $segment = array_slice($newOrder, $i, $j - $i + 1);
        array_splice($newOrder, $i, $j - $i + 1, array_reverse($segment));
        return $newOrder;
    }

    private function calculateTotalDistance(
        array $stops,
        array $matrix,
        ?array $startLocation = null,
        ?array $endLocation = null
    ): float {
        $total = 0.0;
        $n = count($stops);
        if ($n === 0) return 0.0;

        // 1. Start location to first stop
        if ($startLocation) {
            $res = $this->distanceMatrix->getDistance(
                (string)$startLocation['lat'], (string)$startLocation['lng'],
                (string)$stops[0]->lat, (string)$stops[0]->lng
            );
            $total += $res->distanceMiles;
        }

        // 2. Between stops
        for ($i = 0; $i < $n - 1; $i++) {
            $fromIdx = $this->findStopIndex($stops[$i], $matrix);
            $toIdx = $this->findStopIndex($stops[$i + 1], $matrix);

            if ($fromIdx !== null && $toIdx !== null) {
                $distResult = $matrix[$fromIdx][$toIdx] ?? null;
                $total += $distResult?->distanceMiles ?? PHP_FLOAT_MAX;
            } else {
                $total += $this->distanceMatrix->haversine(
                    $stops[$i]->lat, $stops[$i]->lng,
                    $stops[$i + 1]->lat, $stops[$i + 1]->lng
                );
            }
        }

        // 3. Last stop to end location
        if ($endLocation) {
            $res = $this->distanceMatrix->getDistance(
                (string)$stops[$n - 1]->lat, (string)$stops[$n - 1]->lng,
                (string)$endLocation['lat'], (string)$endLocation['lng']
            );
            $total += $res->distanceMiles;
        }

        return $total;
    }

    private function findStopIndex(RouteStop $stop, array $matrix): ?int
    {
        $i = 0;
        foreach ($matrix as $key => $row) {
            if ($i === $key) {
                $checkStop = $row[$key] ?? null;
                if ($checkStop && $checkStop->mode === 'self') {
                    return $key;
                }
            }
            $i++;
        }
        return array_key_first($matrix);
    }

    private function findOptimalStart(array $stops, ClusteringConstraints $constraints): int
    {
        foreach ($stops as $idx => $stop) {
            if ($stop->isUrgent()) return $idx;
        }

        foreach ($stops as $idx => $stop) {
            if ($stop->deliveryWindowStart !== null) return $idx;
        }

        return 0;
    }

    private function isFeasible(RouteStop $stop, ClusteringConstraints $constraints): bool
    {
        if ($stop->isLocked && $constraints->preserveLockedStops) {
            return false;
        }
        return true;
    }

    private function computeNeighborScore(DistanceResult $dist, RouteStop $stop, float $currentTime, ClusteringConstraints $constraints): float
    {
        $score = $dist->distanceMiles;

        if ($stop->isUrgent()) {
            $score *= 0.5;
        }

        if ($stop->deliveryWindowStart !== null && $constraints->respectTimeWindows) {
            $windowStart = strtotime($stop->deliveryWindowStart);
            $eta = $currentTime + ($dist->durationMinutes ?? 0);

            if ($windowStart !== false) {
                $windowStartMinutes = $windowStart / 60;
                if ($eta < $windowStartMinutes) {
                    $score += ($windowStartMinutes - $eta) * 0.1;
                } elseif ($eta > $windowStartMinutes + 60) {
                    $score += 50;
                }
            }
        }

        if ($stop->requiresCustody) {
            $score += 2;
        }

        return $score;
    }

    private function needsBreak(float $currentTimeMinutes, ClusteringConstraints $constraints): bool
    {
        if ($constraints->breakAfterHours <= 0) return false;
        $breakIntervalMinutes = $constraints->breakAfterHours * 60;
        return fmod($currentTimeMinutes, $breakIntervalMinutes) < $constraints->serviceTimePerStopMinutes;
    }

    public function checkTimeWindowFeasibility(array $orderedStops, ?array $startLocation = null, ?int $startTimeStamp = null): bool
    {
        if (empty($orderedStops)) return true;

        $currentTime = $startTimeStamp ?? time();
        $currentLat = $startLocation ? $startLocation['lat'] : null;
        $currentLng = $startLocation ? $startLocation['lng'] : null;

        foreach ($orderedStops as $stop) {
            if ($currentLat !== null && $currentLng !== null) {
                $res = $this->distanceMatrix->getDistance(
                    (string)$currentLat, (string)$currentLng,
                    (string)$stop->lat, (string)$stop->lng
                );
                $travelMinutes = $res->durationMinutes ?? (($res->distanceMiles / 30) * 60);
                $currentTime += $travelMinutes * 60;
            }

            if ($stop->deliveryWindowEnd !== null) {
                $windowEnd = strtotime($stop->deliveryWindowEnd);
                if ($windowEnd !== false && $currentTime > $windowEnd) {
                    return false; // Violated SLA delivery window
                }
            }

            $currentLat = $stop->lat;
            $currentLng = $stop->lng;

            // Wait if before start window
            if ($stop->deliveryWindowStart !== null) {
                $windowStart = strtotime($stop->deliveryWindowStart);
                if ($windowStart !== false && $currentTime < $windowStart) {
                    $currentTime = $windowStart;
                }
            }

            $currentTime += 5 * 60; // 5 mins service time
        }

        return true;
    }

    private function computeRouteMetrics(
        array $orderedStops,
        ClusteringConstraints $constraints,
        string $distanceMode = 'HAVERSINE_FALLBACK',
        ?array $startLocation = null,
        ?array $endLocation = null
    ): array {
        $totalMiles = 0.0;
        $totalDuration = 0.0;
        $n = count($orderedStops);

        if ($n > 0) {
            // 1. Start location to first stop
            if ($startLocation) {
                $res = $this->distanceMatrix->getDistance(
                    (string)$startLocation['lat'], (string)$startLocation['lng'],
                    (string)$orderedStops[0]->lat, (string)$orderedStops[0]->lng
                );
                $totalMiles += $res->distanceMiles;
                $totalDuration += $res->durationMinutes ?? (($res->distanceMiles / 30) * 60);
            }

            // 2. Between consecutive stops
            for ($i = 0; $i < $n - 1; $i++) {
                $res = $this->distanceMatrix->getDistance(
                    (string)$orderedStops[$i]->lat, (string)$orderedStops[$i]->lng,
                    (string)$orderedStops[$i + 1]->lat, (string)$orderedStops[$i + 1]->lng
                );
                $totalMiles += $res->distanceMiles;
                $totalDuration += $res->durationMinutes ?? (($res->distanceMiles / 30) * 60);
            }

            // 3. Last stop to end location
            if ($endLocation) {
                $res = $this->distanceMatrix->getDistance(
                    (string)$orderedStops[$n - 1]->lat, (string)$orderedStops[$n - 1]->lng,
                    (string)$endLocation['lat'], (string)$endLocation['lng']
                );
                $totalMiles += $res->distanceMiles;
                $totalDuration += $res->durationMinutes ?? (($res->distanceMiles / 30) * 60);
            }
        }

        $totalDuration += $n * $constraints->serviceTimePerStopMinutes;
        $breaks = (int)($totalDuration / ($constraints->breakAfterHours * 60));
        $totalDuration += $breaks * $constraints->breakDurationMinutes;

        $totalWeight = array_sum(array_map(fn($s) => $s->weightLbs ?? 0, $orderedStops));
        $totalPackages = array_sum(array_map(fn($s) => $s->packageCount, $orderedStops));

        return [
            'ordered_stops' => $orderedStops,
            'total_miles' => round($totalMiles, 2),
            'estimated_duration_minutes' => round($totalDuration, 1),
            'estimated_duration_hours' => round($totalDuration / 60, 1),
            'total_weight_lbs' => $totalWeight,
            'total_packages' => $totalPackages,
            'unique_stop_count' => $n,
            'algorithm' => $n <= 3 ? 'nearest_feasible_neighbor' : 'nearest_feasible_neighbor+2opt',
            'distance_mode' => $distanceMode,
        ];
    }

    private function returnInvalidStopsResult(array $stops): array
    {
        return [
            'ordered_stops' => $stops,
            'total_miles' => 0,
            'estimated_duration_minutes' => count($stops) * 10,
            'estimated_duration_hours' => round(count($stops) * 10 / 60, 1),
            'total_weight_lbs' => array_sum(array_map(fn($s) => $s->weightLbs ?? 0, $stops)),
            'total_packages' => array_sum(array_map(fn($s) => $s->packageCount, $stops)),
            'unique_stop_count' => count($stops),
            'algorithm' => 'invalid_coordinates',
            'distance_mode' => 'none',
            'warnings' => ['All stops have invalid coordinates - distance metrics are unavailable'],
        ];
    }
}
