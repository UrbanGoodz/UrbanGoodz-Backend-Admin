<?php

namespace App\Services\UrbanGoodz\Routing\DTOs;

class ClusteringConstraints
{
    public function __construct(
        public readonly ?int $requestedRouteCount = null,
        public readonly ?int $targetPackagesPerRoute = null,
        public readonly ?int $maximumPackagesPerRoute = null,
        public readonly ?int $maximumStopsPerRoute = null,
        public readonly ?float $preferredClusterRadiusMiles = null,
        public readonly ?float $maximumClusterRadiusMiles = null,
        public readonly ?float $maximumRouteMiles = null,
        public readonly ?int $maximumRouteDurationMinutes = null,
        public readonly ?float $maxWeightLbs = null,
        public readonly ?float $maxVolumeCubicFt = null,
        public readonly bool $respectTimeWindows = true,
        public readonly bool $preserveLockedStops = true,
        public readonly bool $preservePriorityStops = true,
        public readonly bool $returnToOrigin = false,
        public readonly int $serviceTimePerStopMinutes = 10,
        public readonly float $averageSpeedMph = 30.0,
        public readonly int $driverShiftLimitHours = 10,
        public readonly int $breakAfterHours = 5,
        public readonly int $breakDurationMinutes = 30,
        public readonly ?string $vehicleType = null,
        public readonly ?string $businessRules = null,
    ) {}

    public static function fromConfigAndRequest(array $config, array $request = []): self
    {
        $planning = $config['planning'] ?? [];
        return new self(
            requestedRouteCount: $request['requested_route_count'] ?? null,
            targetPackagesPerRoute: $request['target_packages_per_route'] ?? null,
            maximumPackagesPerRoute: $request['maximum_packages_per_route'] ?? null,
            maximumStopsPerRoute: $request['maximum_stops_per_route'] ?? null,
            preferredClusterRadiusMiles: $request['preferred_cluster_radius_miles'] ?? null,
            maximumClusterRadiusMiles: $request['maximum_cluster_radius_miles'] ?? null,
            maximumRouteMiles: $request['maximum_route_miles'] ?? null,
            maximumRouteDurationMinutes: $request['maximum_route_duration_minutes'] ?? null,
            maxWeightLbs: $request['max_weight_lbs'] ?? null,
            maxVolumeCubicFt: $request['max_volume_cubic_ft'] ?? null,
            respectTimeWindows: $request['respect_time_windows'] ?? true,
            preserveLockedStops: $request['preserve_locked_stops'] ?? true,
            preservePriorityStops: $request['preserve_priority_stops'] ?? true,
            returnToOrigin: $request['return_to_origin'] ?? false,
            serviceTimePerStopMinutes: $request['service_time_per_stop_minutes'] ?? ($planning['default_service_time_minutes'] ?? 10),
            averageSpeedMph: $request['average_speed_mph'] ?? ($planning['default_average_speed_mph'] ?? 30),
            driverShiftLimitHours: $request['driver_shift_limit_hours'] ?? ($planning['driver_shift_limit_hours'] ?? 10),
            breakAfterHours: $planning['break_after_hours'] ?? 5,
            breakDurationMinutes: $planning['break_duration_minutes'] ?? 30,
            vehicleType: $request['vehicle_type'] ?? null,
            businessRules: $request['business_rules'] ?? null,
        );
    }

    public function toPlanningLimitMinutes(): float
    {
        $shiftMinutes = $this->driverShiftLimitHours * 60.0;
        $breaks = (int)($this->driverShiftLimitHours / $this->breakAfterHours);
        return $shiftMinutes - ($breaks * $this->breakDurationMinutes);
    }
}
