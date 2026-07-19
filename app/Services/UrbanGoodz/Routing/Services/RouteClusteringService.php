<?php

namespace App\Services\UrbanGoodz\Routing\Services;

use App\Services\UrbanGoodz\Routing\DTOs\ClusteringConstraints;
use App\Services\UrbanGoodz\Routing\DTOs\DistanceResult;
use App\Services\UrbanGoodz\Routing\DTOs\RouteStop;
use Illuminate\Support\Facades\Log;

class RouteClusteringService
{
    private DistanceMatrixService $distanceMatrix;
    private int $maxIterations;
    private float $rebalanceThreshold;

    public function __construct(?DistanceMatrixService $distanceMatrix = null)
    {
        $this->distanceMatrix = $distanceMatrix ?? new DistanceMatrixService();
        $cfg = config('urban_goodz.clustering', []);
        $this->maxIterations = $cfg['max_iterations'] ?? 100;
        $this->rebalanceThreshold = $cfg['rebalance_threshold'] ?? 0.3;
    }

    public function cluster(array $stops, ClusteringConstraints $constraints): array
    {
        $startTime = microtime(true);

        $sameAddressGroups = $this->groupSameAddresses($stops);
        $consolidated = $this->consolidateSameAddressGroups($stops, $sameAddressGroups);
        $locked = array_filter($consolidated, fn($s) => $s->isLocked && $constraints->preserveLockedStops);
        $priority = array_filter($consolidated, fn($s) => $s->isUrgent() && $constraints->preservePriorityStops);
        $regular = array_values(array_filter($consolidated, fn($s) => !in_array($s, $locked) && !in_array($s, $priority)));

        $routeCount = $this->determineRouteCount($regular, $constraints);
        $lockedClusters = $this->distributeLockedToNearest($locked, $routeCount);
        $priorityClusters = $this->distributePrioritySmartly($priority, $routeCount, $lockedClusters);

        $initialCenters = $this->seedingKMeansPlusPlus($regular, $routeCount);
        $clusters = $this->kMeansIterate($regular, $initialCenters, $routeCount, $constraints);

        $clusters = $this->mergeLockedPriority($clusters, $lockedClusters, $priorityClusters);
        $clusters = $this->enforceCapacity($clusters, $constraints);
        $clusters = $this->enforceRadius($clusters, $constraints);
        $clusters = $this->enforceTimeWindows($clusters, $constraints);
        $clusters = $this->rebalanceWorkload($clusters, $constraints);

        $elapsedMs = (microtime(true) - $startTime) * 1000;
        Log::info('RouteClusteringService: clustering complete', [
            'routes' => count($clusters),
            'elapsed_ms' => round($elapsedMs, 2),
        ]);

        return [
            'clusters' => $clusters,
            'same_address_groups' => $this->summarizeSameAddressGroups($sameAddressGroups),
            'clustering_time_ms' => round($elapsedMs, 2),
        ];
    }

    private function groupSameAddresses(array $stops): array
    {
        $groups = [];
        $groupId = 0;

        $normalizedStops = [];
        foreach ($stops as $stop) {
            $key = $stop->addressKey();
            if (!isset($groups[$key])) {
                $groups[$key] = ['id' => $groupId++, 'stops' => []];
            }
            $groups[$key]['stops'][] = $stop;
        }

        return array_filter($groups, fn($g) => count($g['stops']) > 1);
    }

    private function consolidateSameAddressGroups(array $stops, array $groups): array
    {
        $consolidated = [];
        $groupedPackageIds = [];

        foreach ($groups as $key => $group) {
            $representative = $group['stops'][0];
            $totalPackages = array_sum(array_map(fn($s) => $s->packageCount, $group['stops']));

            foreach ($group['stops'] as $s) {
                $groupedPackageIds[$s->packageId] = true;
            }

            $consolidated[] = new RouteStop(
                packageId: $representative->packageId,
                trackingId: $representative->trackingId,
                lat: $representative->lat,
                lng: $representative->lng,
                address: $representative->address,
                city: $representative->city,
                state: $representative->state,
                zip: $representative->zip,
                recipientName: $representative->recipientName,
                recipientPhone: $representative->recipientPhone,
                priority: $representative->priority,
                deliveryWindowStart: $representative->deliveryWindowStart,
                deliveryWindowEnd: $representative->deliveryWindowEnd,
                isLocked: $representative->isLocked,
                isAgeRestricted: $representative->isAgeRestricted,
                requiresCustody: $representative->requiresCustody,
                requiresSignature: $representative->requiresSignature,
                requiresPhoto: $representative->requiresPhoto,
                weightLbs: $representative->weightLbs,
                volumeCubicFt: $representative->volumeCubicFt,
                packageType: $representative->packageType,
                lockedRouteId: $representative->lockedRouteId,
                lockedStopOrder: $representative->lockedStopOrder,
                sameAddressGroupId: $group['id'],
                packageCount: $totalPackages,
                manifestId: $representative->manifestId,
            );
        }

        foreach ($stops as $stop) {
            if (!isset($groupedPackageIds[$stop->packageId])) {
                $consolidated[] = $stop;
            }
        }

        return $consolidated;
    }

    private function summarizeSameAddressGroups(array $groups): array
    {
        return array_map(function ($group) {
            return [
                'group_id' => $group['id'],
                'address' => $group['stops'][0]->address,
                'package_count' => count($group['stops']),
                'package_ids' => array_map(fn($s) => $s->packageId, $group['stops']),
            ];
        }, array_values($groups));
    }

    private function determineRouteCount(array $stops, ClusteringConstraints $constraints): int
    {
        $count = count($stops);
        if ($count === 0) return 0;

        if ($constraints->requestedRouteCount !== null) {
            return max(1, $constraints->requestedRouteCount);
        }

        if ($constraints->targetPackagesPerRoute !== null) {
            return (int)ceil($count / $constraints->targetPackagesPerRoute);
        }

        $maxPerRoute = $constraints->maximumPackagesPerRoute ?? 20;
        return max(1, (int)ceil($count / $maxPerRoute));
    }

    private function seedingKMeansPlusPlus(array $stops, int $k): array
    {
        if ($k >= count($stops)) {
            return array_map(fn($s) => [$s->lat, $s->lng], $stops);
        }

        $centers = [];
        $validStops = array_values(array_filter($stops, fn($s) => $s->hasValidCoordinates()));

        if (empty($validStops)) {
            $validStops = $stops;
        }

        $centers[] = [$validStops[0]->lat, $validStops[0]->lng];

        for ($i = 1; $i < $k; $i++) {
            $distances = [];
            foreach ($validStops as $stop) {
                $minDist = PHP_INT_MAX;
                foreach ($centers as $center) {
                    $d = $this->distanceMatrix->haversine(
                        $stop->lat, $stop->lng, $center[0], $center[1]
                    );
                    $minDist = min($minDist, $d);
                }
                $distances[] = $minDist * $minDist;
            }

            $totalDist = array_sum($distances);
            if ($totalDist == 0) break;

            $rand = mt_rand(0, (int)($totalDist * 1000)) / 1000;
            $cumulative = 0;
            foreach ($distances as $idx => $d) {
                $cumulative += $d;
                if ($cumulative >= $rand) {
                    $centers[] = [$validStops[$idx]->lat, $validStops[$idx]->lng];
                    break;
                }
            }
        }

        return $centers;
    }

    private function kMeansIterate(array $stops, array $initialCenters, int $k, ClusteringConstraints $constraints): array
    {
        $centers = $initialCenters;
        $assignment = array_fill(0, count($stops), 0);
        $validStops = array_values(array_filter($stops, fn($s) => $s->hasValidCoordinates()));

        for ($iter = 0; $iter < $this->maxIterations; $iter++) {
            $changed = false;

            foreach ($validStops as $idx => $stop) {
                $bestCluster = 0;
                $bestDist = PHP_INT_MAX;

                for ($c = 0; $c < $k; $c++) {
                    $dist = $this->distanceMatrix->haversine(
                        $stop->lat, $stop->lng,
                        $centers[$c][0], $centers[$c][1]
                    );

                    if ($constraints->maximumClusterRadiusMiles !== null && $dist > $constraints->maximumClusterRadiusMiles) {
                        continue;
                    }

                    if ($dist < $bestDist) {
                        $bestDist = $dist;
                        $bestCluster = $c;
                    }
                }

                if ($assignment[$idx] !== $bestCluster) {
                    $assignment[$idx] = $bestCluster;
                    $changed = true;
                }
            }

            if (!$changed) break;

            for ($c = 0; $c < $k; $c++) {
                $members = [];
                foreach ($validStops as $idx => $stop) {
                    if ($assignment[$idx] === $c) {
                        $members[] = $stop;
                    }
                }

                if (empty($members)) continue;

                $centers[$c] = [
                    array_sum(array_map(fn($m) => $m->lat, $members)) / count($members),
                    array_sum(array_map(fn($m) => $m->lng, $members)) / count($members),
                ];
            }
        }

        $clusters = [];
        for ($c = 0; $c < $k; $c++) {
            $members = [];
            foreach ($validStops as $idx => $stop) {
                if ($assignment[$idx] === $c) {
                    $members[] = $stop;
                }
            }
            if (!empty($members)) {
                $clusters[] = $members;
            }
        }

        return $clusters;
    }

    private function distributeLockedToNearest(array $locked, int $routeCount): array
    {
        $clusters = array_fill(0, $routeCount, []);

        foreach ($locked as $stop) {
            if ($stop->lockedRouteId !== null) {
                $idx = ($stop->lockedRouteId - 1) % $routeCount;
                $clusters[$idx][] = $stop;
            } else {
                $clusters[0][] = $stop;
            }
        }

        return $clusters;
    }

    private function distributePrioritySmartly(array $priority, int $routeCount, array $lockedClusters): array
    {
        $clusters = array_fill(0, $routeCount, []);

        foreach ($priority as $stop) {
            $bestCluster = 0;
            $bestScore = -PHP_INT_MAX;

            for ($c = 0; $c < $routeCount; $c++) {
                $score = 0;

                if (!empty($lockedClusters[$c])) {
                    $avgLat = array_sum(array_map(fn($l) => $l->lat, $lockedClusters[$c])) / count($lockedClusters[$c]);
                    $avgLng = array_sum(array_map(fn($l) => $l->lng, $lockedClusters[$c])) / count($lockedClusters[$c]);
                    $proximity = $this->distanceMatrix->haversine($stop->lat, $stop->lng, $avgLat, $avgLng);
                    $score += 1000 - $proximity;
                }

                $clusterSize = count($clusters[$c]);
                $score -= $clusterSize * 10;

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestCluster = $c;
                }
            }

            $clusters[$bestCluster][] = $stop;
        }

        return $clusters;
    }

    private function mergeLockedPriority(array $clusters, array $lockedClusters, array $priorityClusters): array
    {
        $routeCount = count($clusters);

        for ($c = 0; $c < $routeCount; $c++) {
            $locked = $lockedClusters[$c] ?? [];
            $priority = $priorityClusters[$c] ?? [];
            $regular = $clusters[$c] ?? [];

            $clusters[$c] = array_merge($locked, $priority, $regular);
        }

        return $clusters;
    }

    private function enforceCapacity(array $clusters, ClusteringConstraints $constraints): array
    {
        $maxPackages = $constraints->maximumPackagesPerRoute ?? PHP_INT_MAX;
        $maxWeight = $constraints->maxWeightLbs ?? PHP_INT_MAX;
        $maxVolume = $constraints->maxVolumeCubicFt ?? PHP_INT_MAX;

        $excess = [];
        foreach ($clusters as $c => $stops) {
            $totalPackages = array_sum(array_map(fn($s) => $s->packageCount, $stops));
            $totalWeight = array_sum(array_filter(array_map(fn($s) => $s->weightLbs ?? 0, $stops)));
            $totalVolume = array_sum(array_filter(array_map(fn($s) => $s->volumeCubicFt ?? 0, $stops)));

            if ($totalPackages > $maxPackages || $totalWeight > $maxWeight || $totalVolume > $maxVolume) {
                $sorted = $stops;
                usort($sorted, fn($a, $b) => $b->priority <=> $a->priority);

                $keep = [];
                $cumulative = 0;
                $cumWeight = 0;
                $cumVolume = 0;

                foreach ($sorted as $stop) {
                    $stopPkgs = $stop->packageCount;
                    $stopWeight = $stop->weightLbs ?? 0;
                    $stopVol = $stop->volumeCubicFt ?? 0;

                    if ($cumulative + $stopPkgs <= $maxPackages
                        && $cumWeight + $stopWeight <= $maxWeight
                        && $cumVolume + $stopVol <= $maxVolume) {
                        $keep[] = $stop;
                        $cumulative += $stopPkgs;
                        $cumWeight += $stopWeight;
                        $cumVolume += $stopVol;
                    }
                }

                $clusters[$c] = $keep;
                $excess = array_merge($excess, array_diff_key($stops, array_flip(array_map(fn($s) => $s->packageId, $keep))));
            }
        }

        if (!empty($excess)) {
            $minCluster = 0;
            $minSize = PHP_INT_MAX;
            foreach ($clusters as $c => $stops) {
                $size = array_sum(array_map(fn($s) => $s->packageCount, $stops));
                if ($size < $minSize) {
                    $minSize = $size;
                    $minCluster = $c;
                }
            }
            $clusters[$minCluster] = array_merge($clusters[$minCluster], $excess);
        }

        return $clusters;
    }

    private function enforceRadius(array $clusters, ClusteringConstraints $constraints): array
    {
        $maxRadius = $constraints->maximumClusterRadiusMiles ?? PHP_FLOAT_MAX;

        foreach ($clusters as $c => $stops) {
            if (count($stops) <= 1) continue;

            $validStops = array_values(array_filter($stops, fn($s) => $s->hasValidCoordinates()));
            if (empty($validStops)) continue;

            $centerLat = array_sum(array_map(fn($s) => $s->lat, $validStops)) / count($validStops);
            $centerLng = array_sum(array_map(fn($s) => $s->lng, $validStops)) / count($validStops);

            $outliers = [];
            $inliers = [];

            foreach ($stops as $stop) {
                if (!$stop->hasValidCoordinates()) {
                    $inliers[] = $stop;
                    continue;
                }

                $dist = $this->distanceMatrix->haversine($stop->lat, $stop->lng, $centerLat, $centerLng);
                if ($dist > $maxRadius) {
                    $outliers[] = $stop;
                } else {
                    $inliers[] = $stop;
                }
            }

            $clusters[$c] = $inliers;

            foreach ($outliers as $outlier) {
                $bestCluster = $c;
                $bestDist = PHP_FLOAT_MAX;

                foreach ($clusters as $oc => $otherStops) {
                    if ($oc === $c) continue;
                    if (empty($otherStops)) {
                        $clusters[$oc][] = $outlier;
                        continue 2;
                    }

                    $validOther = array_values(array_filter($otherStops, fn($s) => $s->hasValidCoordinates()));
                    if (empty($validOther)) continue;

                    $oLat = array_sum(array_map(fn($s) => $s->lat, $validOther)) / count($validOther);
                    $oLng = array_sum(array_map(fn($s) => $s->lng, $validOther)) / count($validOther);
                    $dist = $this->distanceMatrix->haversine($outlier->lat, $outlier->lng, $oLat, $oLng);

                    if ($dist < $bestDist) {
                        $bestDist = $dist;
                        $bestCluster = $oc;
                    }
                }

                $clusters[$bestCluster][] = $outlier;
            }
        }

        return $clusters;
    }

    private function enforceTimeWindows(array $clusters, ClusteringConstraints $constraints): array
    {
        if (!$constraints->respectTimeWindows) return $clusters;

        foreach ($clusters as $c => $stops) {
            $withWindows = array_values(array_filter($stops, fn($s) => $s->deliveryWindowStart !== null));
            $withoutWindows = array_values(array_filter($stops, fn($s) => $s->deliveryWindowStart === null));

            if (empty($withWindows)) continue;

            usort($withWindows, fn($a, $b) => $a->deliveryWindowStart <=> $b->deliveryWindowStart);

            $clusters[$c] = array_merge($withWindows, $withoutWindows);
        }

        return $clusters;
    }

    private function rebalanceWorkload(array $clusters, ClusteringConstraints $constraints): array
    {
        if (count($clusters) <= 1) return $clusters;

        $sizes = array_map(fn($stops) => array_sum(array_map(fn($s) => $s->packageCount, $stops)), $clusters);
        $avgSize = array_sum($sizes) / count($sizes);

        if ($avgSize == 0) return $clusters;

        $maxDeviation = max(array_map(fn($s) => abs($s - $avgSize) / $avgSize, $sizes));

        if ($maxDeviation <= $this->rebalanceThreshold) return $clusters;

        $overloaded = [];
        $underloaded = [];

        foreach ($sizes as $c => $size) {
            if ($size > $avgSize * (1 + $this->rebalanceThreshold)) {
                $overloaded[] = $c;
            } elseif ($size < $avgSize * (1 - $this->rebalanceThreshold)) {
                $underloaded[] = $c;
            }
        }

        foreach ($overloaded as $oc) {
            if (empty($underloaded)) break;

            $transferCount = (int)(($sizes[$oc] - $avgSize) / 2);
            if ($transferCount <= 0) continue;

            $stops = $clusters[$oc];
            $nonLocked = array_values(array_filter($stops, fn($s) => !$s->isLocked));

            $toTransfer = array_slice($nonLocked, -$transferCount);

            if (!empty($toTransfer)) {
                $clusters[$oc] = array_diff_key($stops, array_flip(array_map(fn($s) => $s->packageId, $toTransfer)));

                $uc = $underloaded[0];
                $clusters[$uc] = array_merge($clusters[$uc], $toTransfer);

                if (count($clusters[$uc]) >= $avgSize) {
                    array_shift($underloaded);
                }
            }
        }

        return $clusters;
    }
}
