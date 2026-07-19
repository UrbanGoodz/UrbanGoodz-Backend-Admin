<?php

namespace App\Services\UrbanGoodz\Routing\DTOs;

class PlanningResult
{
    public function __construct(
        public readonly int $totalPackages,
        public readonly int $routedPackages,
        public readonly int $unrouteableCount,
        public readonly int $routeCountRequested,
        public readonly int $routeCountGenerated,
        public readonly int $uniqueStopCount,
        public readonly array $clusters,
        public readonly array $unrouteable,
        public readonly array $sameAddressGroups,
        public readonly PlanningMetrics $metrics,
        public readonly ClusteringConstraints $constraints,
        public readonly string $algorithmVersion,
        public readonly string $overallDistanceMode,
        public readonly array $overallViolations = [],
        public readonly array $warnings = [],
        public readonly ?int $auditId = null,
    ) {}

    public function toArray(): array
    {
        return [
            'total_packages' => $this->totalPackages,
            'routed_packages' => $this->routedPackages,
            'unrouteable_count' => $this->unrouteableCount,
            'route_count_requested' => $this->routeCountRequested,
            'route_count_generated' => $this->routeCountGenerated,
            'unique_stop_count' => $this->uniqueStopCount,
            'clusters' => array_map(fn($c) => $c->toSummaryArray(), $this->clusters),
            'unrouteable' => $this->unrouteable,
            'same_address_groups' => $this->sameAddressGroups,
            'metrics' => $this->metrics->toArray(),
            'algorithm_version' => $this->algorithmVersion,
            'overall_distance_mode' => $this->overallDistanceMode,
            'overall_violations' => $this->overallViolations,
            'warnings' => $this->warnings,
            'audit_id' => $this->auditId,
        ];
    }
}
