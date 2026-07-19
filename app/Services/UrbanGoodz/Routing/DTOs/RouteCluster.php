<?php

namespace App\Services\UrbanGoodz\Routing\DTOs;

class RouteCluster
{
    public function __construct(
        public readonly int $clusterIndex,
        public readonly string $label,
        public readonly array $stops,
        public readonly float $estimatedMiles,
        public readonly int $estimatedDurationMinutes,
        public readonly int $packageCount,
        public readonly int $uniqueStopCount,
        public readonly float $totalWeightLbs,
        public readonly ?float $clusterCenterLat,
        public readonly ?float $clusterCenterLng,
        public readonly ?float $maxStopDistanceFromCenter,
        public readonly float $averageStopDistance,
        public readonly float $workloadScore,
        public readonly string $distanceMode,
        public readonly bool $hasTimeWindows,
        public readonly bool $hasAgeRestricted,
        public readonly bool $hasMedical,
        public readonly array $constraintViolations = [],
        public readonly array $warnings = [],
    ) {}

    public function toSummaryArray(): array
    {
        return [
            'cluster_index' => $this->clusterIndex,
            'label' => $this->label,
            'package_count' => $this->packageCount,
            'unique_stop_count' => $this->uniqueStopCount,
            'estimated_miles' => $this->estimatedMiles,
            'estimated_duration_minutes' => $this->estimatedDurationMinutes,
            'estimated_duration_hours' => round($this->estimatedDurationMinutes / 60, 1),
            'total_weight_lbs' => $this->totalWeightLbs,
            'cluster_center_lat' => $this->clusterCenterLat,
            'cluster_center_lng' => $this->clusterCenterLng,
            'max_stop_distance_from_center' => $this->maxStopDistanceFromCenter,
            'average_stop_distance' => $this->averageStopDistance,
            'workload_score' => $this->workloadScore,
            'distance_mode' => $this->distanceMode,
            'has_time_windows' => $this->hasTimeWindows,
            'has_age_restricted' => $this->hasAgeRestricted,
            'has_medical' => $this->hasMedical,
            'constraint_violations' => $this->constraintViolations,
            'warnings' => $this->warnings,
        ];
    }
}
