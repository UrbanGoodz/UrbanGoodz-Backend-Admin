<?php

namespace App\Services\UrbanGoodz\Routing\DTOs;

class PlanningMetrics
{
    public function __construct(
        public readonly float $totalPlanningTimeMs,
        public readonly float $matrixFetchTimeMs,
        public readonly float $clusteringTimeMs,
        public readonly float $sequencingTimeMs,
        public readonly float $persistenceTimeMs,
        public readonly int $matrixRequestCount,
        public readonly int $cacheHitCount,
        public readonly int $cacheMissCount,
        public readonly float $memoryPeakMb,
        public readonly float $cacheHitRate,
    ) {}

    public static function empty(): self
    {
        return new self(0, 0, 0, 0, 0, 0, 0, 0, 0, 0);
    }

    public function toArray(): array
    {
        return [
            'total_planning_time_ms' => round($this->totalPlanningTimeMs, 2),
            'matrix_fetch_time_ms' => round($this->matrixFetchTimeMs, 2),
            'clustering_time_ms' => round($this->clusteringTimeMs, 2),
            'sequencing_time_ms' => round($this->sequencingTimeMs, 2),
            'persistence_time_ms' => round($this->persistenceTimeMs, 2),
            'matrix_request_count' => $this->matrixRequestCount,
            'cache_hit_count' => $this->cacheHitCount,
            'cache_miss_count' => $this->cacheMissCount,
            'memory_peak_mb' => round($this->memoryPeakMb, 2),
            'cache_hit_rate' => round($this->cacheHitRate, 2),
        ];
    }
}
