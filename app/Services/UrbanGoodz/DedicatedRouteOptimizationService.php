<?php

namespace App\Services\UrbanGoodz;

use App\Models\UrbanGoodzBatchPackage;
use App\Models\UrbanGoodzBusinessPortalAuditLog;
use App\Models\UrbanGoodzDedicatedRoute;
use App\Models\UrbanGoodzRouteOptimizationHistory;
use App\Models\UrbanGoodzRouteOptimizationStop;
use App\Models\UrbanGoodzRoutePackage;
use App\Services\UrbanGoodz\Routing\DTOs\DistanceResult;
use App\Services\UrbanGoodz\Routing\Services\DistanceMatrixService;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DedicatedRouteOptimizationService
{
    private const ACTIVE_PACKAGE_STATUSES = [
        'pending', 'pending_review', 'ready_for_route', 'assigned',
        'loaded', 'picked_up', 'in_transit', 'out_for_delivery',
        'return_required', 'returning_to_pickup', 'returning_to_hub',
        'returning_to_business', 'redelivery_pending',
    ];

    public function __construct(private ?DistanceMatrixService $distances = null)
    {
        $this->distances ??= new DistanceMatrixService();
    }

    public function optimize(
        UrbanGoodzDedicatedRoute $route,
        bool $returnToOrigin = false,
        ?string $actorType = null,
        ?int $actorId = null
    ): array {
        try {
            $this->assertRouteCanBeOptimized($route);

            $packages = $route->packages()
                ->whereIn('status', self::ACTIVE_PACKAGE_STATUSES)
                ->orderByRaw('CASE WHEN stop_order > 0 THEN stop_order ELSE 2147483647 END')
                ->orderBy('id')
                ->get();

            if ($packages->isEmpty()) {
                throw new DomainException('This route has no active stops to optimize.');
            }
            $this->assertCapacity($route, $packages);

            $start = $this->validatedPoint($route->pickup_lat, $route->pickup_lng, 'starting location');
            $end = $returnToOrigin
                ? $start
                : $this->optionalEndPoint($route);

            foreach ($packages as $package) {
                $this->validatedPoint(
                    $package->dropoff_lat,
                    $package->dropoff_lng,
                    "stop {$package->tracking_id}"
                );
            }

            $plan = $this->plan($packages, $start, $end);
            $original = $plan['original'];
            $optimized = $plan['optimized'];
            $originalMetrics = $plan['original_metrics'];
            $optimizedMetrics = $plan['optimized_metrics'];
            $changed = $plan['changed'];
            $status = $plan['status'];
            $method = $plan['method'];
            $constraints = $this->constraintsFor($route, $packages, $returnToOrigin);
            $nextVersion = ((int) $route->optimization_version) + 1;
            $savedOriginalSequence = array_values(array_map(
                'intval',
                $route->optimization_original_sequence ?: $original->pluck('id')->all()
            ));

            DB::transaction(function () use (
                $route, $original, $optimized, $originalMetrics, $optimizedMetrics,
                $returnToOrigin, $actorType, $actorId, $status, $method,
                $savedOriginalSequence, $constraints, $nextVersion
            ): void {
                UrbanGoodzDedicatedRoute::whereKey($route->id)->lockForUpdate()->firstOrFail();
                $persistedPackageIds = UrbanGoodzRoutePackage::where('dedicated_route_id', $route->id)
                    ->whereIn('status', self::ACTIVE_PACKAGE_STATUSES)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->sort()
                    ->values()
                    ->all();
                if ($persistedPackageIds !== $original->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all()) {
                    throw new DomainException('Route stops changed while optimization was running. Please retry.');
                }

                UrbanGoodzRouteOptimizationStop::where('dedicated_route_id', $route->id)->delete();

                $previous = [
                    'lat' => (float) $route->pickup_lat,
                    'lng' => (float) $route->pickup_lng,
                ];

                foreach ($optimized as $index => $package) {
                    $order = $index + 1;
                    $leg = $this->distances->getDistance(
                        (string) $previous['lat'],
                        (string) $previous['lng'],
                        (string) $package->dropoff_lat,
                        (string) $package->dropoff_lng
                    );

                    UrbanGoodzRouteOptimizationStop::create([
                        'dedicated_route_id' => $route->id,
                        'package_id' => $package->id,
                        'stop_order' => $order,
                        'original_stop_order' => (($savedIndex = array_search((int) $package->id, $savedOriginalSequence, true)) !== false)
                            ? $savedIndex + 1
                            : $original->search(fn ($item) => $item->id === $package->id) + 1,
                        'estimated_distance_from_prev' => round($leg->distanceMiles, 2),
                        'estimated_duration_from_prev' => $leg->durationMinutes === null
                            ? null
                            : (int) round($leg->durationMinutes),
                        'status' => 'pending',
                    ]);

                    $package->update(['stop_order' => $order]);
                    if (Schema::hasTable('urban_goodz_batch_packages')) {
                        UrbanGoodzBatchPackage::where('dedicated_route_id', $route->id)
                            ->where('tracking_id', $package->tracking_id)
                            ->update(['stop_order' => $order]);
                    }

                    $previous = [
                        'lat' => (float) $package->dropoff_lat,
                        'lng' => (float) $package->dropoff_lng,
                    ];
                }

                $route->update([
                    'return_to_origin' => $returnToOrigin,
                    'optimization_status' => $status,
                    'optimized_at' => now(),
                    'original_distance_miles' => $originalMetrics['miles'],
                    'optimized_distance_miles' => $optimizedMetrics['miles'],
                    'original_duration_minutes' => $originalMetrics['minutes'],
                    'optimized_duration_minutes' => $optimizedMetrics['minutes'],
                    'estimated_miles' => $optimizedMetrics['miles'],
                    'estimated_duration' => $optimizedMetrics['minutes'],
                    'optimization_method' => $method,
                    'optimization_provider' => $optimizedMetrics['provider'],
                    'optimization_distance_mode' => $optimizedMetrics['distance_mode'],
                    'optimization_constraints' => $constraints,
                    'optimization_error' => null,
                    'optimization_original_sequence' => $savedOriginalSequence,
                    'optimization_manual_override' => false,
                    'optimized_by_type' => $actorType,
                    'optimized_by_id' => $actorId,
                    'optimization_version' => $nextVersion,
                ]);

                UrbanGoodzRouteOptimizationHistory::create([
                    'dedicated_route_id' => $route->id,
                    'version' => $nextVersion,
                    'action' => $nextVersion === 1 ? 'optimize' : 'reoptimize',
                    'status' => $status,
                    'method' => $method,
                    'provider' => $optimizedMetrics['provider'],
                    'distance_mode' => $optimizedMetrics['distance_mode'],
                    'original_sequence' => $original->pluck('id')->values()->all(),
                    'result_sequence' => $optimized->pluck('id')->values()->all(),
                    'constraints' => $constraints,
                    'package_count' => $packages->count(),
                    'stop_count' => $optimized->count(),
                    'original_distance_miles' => $originalMetrics['miles'],
                    'result_distance_miles' => $optimizedMetrics['miles'],
                    'original_duration_minutes' => $originalMetrics['minutes'],
                    'result_duration_minutes' => $optimizedMetrics['minutes'],
                    'actor_type' => $actorType,
                    'actor_id' => $actorId,
                ]);

                $this->audit($route, 'route_optimized', $actorType, $actorId, [
                    'original_sequence' => $original->pluck('id')->values()->all(),
                    'optimized_sequence' => $optimized->pluck('id')->values()->all(),
                    'original_miles' => $originalMetrics['miles'],
                    'optimized_miles' => $optimizedMetrics['miles'],
                    'method' => $method,
                    'provider' => $optimizedMetrics['provider'],
                ]);
            });

            return [
                'status' => $status,
                'changed' => $changed,
                'original_sequence' => $original->pluck('id')->values()->all(),
                'optimized_sequence' => $optimized->pluck('id')->values()->all(),
                'original_distance_miles' => $originalMetrics['miles'],
                'optimized_distance_miles' => $optimizedMetrics['miles'],
                'original_duration_minutes' => $originalMetrics['minutes'],
                'optimized_duration_minutes' => $optimizedMetrics['minutes'],
                'method' => $method,
                'provider' => $optimizedMetrics['provider'],
                'distance_mode' => $optimizedMetrics['distance_mode'],
                'constraints' => $constraints,
                'optimization_version' => $nextVersion,
            ];
        } catch (Throwable $exception) {
            if (Schema::hasColumn('urban_goodz_dedicated_routes', 'optimization_status')) {
                $route->forceFill([
                    'optimization_status' => 'failed',
                    'optimization_error' => mb_substr($exception->getMessage(), 0, 2000),
                ])->save();
            }

            throw $exception;
        }
    }

    public function plan(Collection $packages, array $start, ?array $end = null): array
    {
        if ($packages->isEmpty()) {
            throw new DomainException('This route has no active stops to optimize.');
        }

        $start = $this->validatedPoint($start['lat'] ?? null, $start['lng'] ?? null, 'starting location');
        if ($end !== null) {
            $end = $this->validatedPoint($end['lat'] ?? null, $end['lng'] ?? null, 'ending location');
        }
        foreach ($packages as $package) {
            $this->validatedPoint($package->dropoff_lat, $package->dropoff_lng, "stop {$package->tracking_id}");
        }
        $this->assertLockedStopsAreValid($packages);

        $original = $packages->values();
        $originalMetrics = $this->metrics($original, $start, $end);
        $optimized = $packages->count() === 1 ? $original : $this->improve($packages, $start, $end);
        $optimizedMetrics = $this->metrics($optimized, $start, $end);

        if (!$this->samePackageSet($original, $optimized)) {
            throw new DomainException('Optimization did not preserve every route stop exactly once.');
        }

        $changed = $original->pluck('id')->all() !== $optimized->pluck('id')->all();
        if ($optimizedMetrics['miles'] > $originalMetrics['miles'] + 0.01 && !$this->hasOrderingConstraints($packages)) {
            $optimized = $original;
            $optimizedMetrics = $originalMetrics;
            $changed = false;
        }

        $fallback = $originalMetrics['fallback'] || $optimizedMetrics['fallback'];
        return [
            'original' => $original,
            'optimized' => $optimized,
            'original_metrics' => $originalMetrics,
            'optimized_metrics' => $optimizedMetrics,
            'changed' => $changed,
            'status' => $changed ? ($fallback ? 'optimized_with_fallback' : 'optimized') : 'no_improvement',
            'method' => $packages->count() === 1 ? 'single_stop' : 'constrained_nearest_neighbor+2opt',
        ];
    }

    public function applyManualOrder(
        UrbanGoodzDedicatedRoute $route,
        array $packageIds,
        ?string $actorType = null,
        ?int $actorId = null
    ): void {
        $current = $route->optimizationStops()->orderBy('stop_order')->pluck('package_id')->map(fn ($id) => (int) $id)->all();
        $requested = array_values(array_map('intval', $packageIds));
        if (count($current) !== count($requested) || array_diff($current, $requested) || array_diff($requested, $current)) {
            throw new DomainException('Manual order must contain every persisted stop exactly once.');
        }

        $packageById = $route->packages()->whereIn('id', $requested)->get()->keyBy('id');
        $orderedPackages = collect($requested)->map(function ($packageId) use ($packageById) {
            $package = $packageById->get($packageId);
            if (!$package) {
                throw new DomainException("Package {$packageId} is no longer available on this route.");
            }
            return $package;
        });
        foreach ($requested as $index => $packageId) {
            $package = $packageById->get($packageId);
            $currentPosition = array_search($packageId, $current, true);
            $lockedPosition = $package?->locked_stop_order
                ?: ($currentPosition === false ? null : $currentPosition + 1);
            if ($package?->stop_locked && (int) $lockedPosition !== $index + 1) {
                throw new DomainException(
                    "Locked stop {$package->tracking_id} must remain at position {$lockedPosition}."
                );
            }
        }
        $start = $this->validatedPoint($route->pickup_lat, $route->pickup_lng, 'starting location');
        $end = $route->return_to_origin ? $start : $this->optionalEndPoint($route);
        $metrics = $this->metrics($orderedPackages, $start, $end);
        $previousDistance = $route->optimized_distance_miles;
        $previousDuration = $route->optimized_duration_minutes;

        DB::transaction(function () use (
            $route, $current, $requested, $orderedPackages, $start, $metrics,
            $previousDistance, $previousDuration, $actorType, $actorId
        ): void {
            UrbanGoodzDedicatedRoute::whereKey($route->id)->lockForUpdate()->firstOrFail();
            $persisted = UrbanGoodzRouteOptimizationStop::where('dedicated_route_id', $route->id)
                ->orderBy('stop_order')
                ->pluck('package_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            if ($persisted !== $current) {
                throw new DomainException('Route stops changed while manual ordering was being saved. Please retry.');
            }
            $nextVersion = ((int) $route->optimization_version) + 1;
            foreach ($requested as $index => $packageId) {
                UrbanGoodzRouteOptimizationStop::where('dedicated_route_id', $route->id)
                    ->where('package_id', $packageId)
                    ->update(['stop_order' => -($index + 1)]);
            }
            $previous = $start;
            foreach ($orderedPackages as $index => $package) {
                $order = $index + 1;
                $leg = $this->leg($previous, $package);
                UrbanGoodzRouteOptimizationStop::where('dedicated_route_id', $route->id)
                    ->where('package_id', $package->id)
                    ->update([
                        'stop_order' => $order,
                        'estimated_distance_from_prev' => round($leg->distanceMiles, 2),
                        'estimated_duration_from_prev' => $leg->durationMinutes === null
                            ? null
                            : (int) round($leg->durationMinutes),
                    ]);
                UrbanGoodzRoutePackage::where('dedicated_route_id', $route->id)
                    ->where('id', $package->id)
                    ->update(['stop_order' => $order]);
                if (Schema::hasTable('urban_goodz_batch_packages')) {
                    UrbanGoodzBatchPackage::where('dedicated_route_id', $route->id)
                        ->where('tracking_id', $package->tracking_id)
                        ->update(['stop_order' => $order]);
                }
                $previous = [
                    'lat' => (float) $package->dropoff_lat,
                    'lng' => (float) $package->dropoff_lng,
                ];
            }

            $route->update([
                'optimization_status' => 'manual_override',
                'optimization_manual_override' => true,
                'optimized_distance_miles' => $metrics['miles'],
                'optimized_duration_minutes' => $metrics['minutes'],
                'estimated_miles' => $metrics['miles'],
                'estimated_duration' => $metrics['minutes'],
                'optimization_method' => 'manual_order',
                'optimization_provider' => $metrics['provider'],
                'optimization_distance_mode' => $metrics['distance_mode'],
                'optimization_error' => null,
                'optimized_by_type' => $actorType,
                'optimized_by_id' => $actorId,
                'optimized_at' => now(),
                'optimization_version' => $nextVersion,
            ]);
            UrbanGoodzRouteOptimizationHistory::create([
                'dedicated_route_id' => $route->id,
                'version' => $nextVersion,
                'action' => 'manual_order',
                'status' => 'manual_override',
                'method' => 'manual_order',
                'provider' => $metrics['provider'],
                'distance_mode' => $metrics['distance_mode'],
                'original_sequence' => $current,
                'result_sequence' => $requested,
                'constraints' => $route->optimization_constraints,
                'package_count' => count($requested),
                'stop_count' => count($requested),
                'original_distance_miles' => $previousDistance,
                'result_distance_miles' => $metrics['miles'],
                'original_duration_minutes' => $previousDuration,
                'result_duration_minutes' => $metrics['minutes'],
                'actor_type' => $actorType,
                'actor_id' => $actorId,
            ]);
            $this->audit($route, 'route_manual_reorder', $actorType, $actorId, [
                'before' => $current,
                'after' => $requested,
            ]);
        });
    }

    public function restoreOriginalOrder(
        UrbanGoodzDedicatedRoute $route,
        ?string $actorType = null,
        ?int $actorId = null
    ): void {
        $original = array_map('intval', $route->optimization_original_sequence ?? []);
        if ($original === []) {
            throw new DomainException('No original route sequence has been saved.');
        }
        $this->applyManualOrder($route, $original, $actorType, $actorId);
        $route->update(['optimization_status' => 'original_restored']);
        $route->optimizationHistory()->where('version', $route->fresh()->optimization_version)->update([
            'action' => 'restore_original',
            'status' => 'original_restored',
        ]);
    }

    private function improve(Collection $packages, array $start, ?array $end): Collection
    {
        $original = $packages->values();
        $lockedByIndex = [];
        foreach ($original as $index => $package) {
            if ($package->stop_locked) {
                $lockedPosition = $package->locked_stop_order ?: ($index + 1);
                $lockedByIndex[$lockedPosition - 1] = $package;
            }
        }
        $remaining = $packages
            ->reject(fn ($package) => $package->stop_locked)
            ->keyBy('id');
        $ordered = collect();
        $current = $start;

        for ($position = 0; $position < $packages->count(); $position++) {
            if (isset($lockedByIndex[$position])) {
                $next = $lockedByIndex[$position];
                $ordered->push($next);
                $current = ['lat' => (float) $next->dropoff_lat, 'lng' => (float) $next->dropoff_lng];
                continue;
            }
            if ($remaining->isEmpty()) {
                break;
            }
            $next = $remaining->sort(function ($a, $b) use ($current) {
                $constraint = $this->constraintKey($a) <=> $this->constraintKey($b);
                if ($constraint !== 0) {
                    return $constraint;
                }
                $distanceA = $this->leg($current, $a)->distanceMiles;
                $distanceB = $this->leg($current, $b)->distanceMiles;
                return ($distanceA <=> $distanceB) ?: ($a->id <=> $b->id);
            })->first();

            $ordered->push($next);
            $remaining->forget($next->id);
            $current = ['lat' => (float) $next->dropoff_lat, 'lng' => (float) $next->dropoff_lng];
        }

        $best = $ordered->values();
        $bestMiles = $this->metrics($best, $start, $end)['miles'];
        $count = $best->count();

        for ($iteration = 0; $iteration < 50; $iteration++) {
            $improved = false;
            for ($i = 0; $i < $count - 1; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $candidate = $best->take($i)
                        ->concat($best->slice($i, $j - $i + 1)->reverse())
                        ->concat($best->slice($j + 1))
                        ->values();
                    if (!$this->constraintsPreserved($candidate, $original, $start)) {
                        continue;
                    }
                    $miles = $this->metrics($candidate, $start, $end)['miles'];
                    if ($miles < $bestMiles - 0.001) {
                        $best = $candidate;
                        $bestMiles = $miles;
                        $improved = true;
                    }
                }
            }
            if (!$improved) {
                break;
            }
        }

        return $best;
    }

    private function metrics(Collection $packages, array $start, ?array $end): array
    {
        $miles = 0.0;
        $minutes = 0.0;
        $durationAvailable = true;
        $fallback = false;
        $providers = [];
        $current = $start;
        $currentTimestamp = null;
        $timeWindowViolations = [];

        foreach ($packages as $package) {
            $leg = $this->leg($current, $package);
            $miles += $leg->distanceMiles;
            if ($leg->durationMinutes === null) {
                $durationAvailable = false;
            } else {
                $minutes += $leg->durationMinutes;
            }
            $fallback = $fallback || $leg->isFallback;
            $providers[$leg->provider] = true;
            if ($currentTimestamp === null) {
                $currentTimestamp = $package->delivery_window_start?->timestamp ?? time();
            }
            $currentTimestamp += (int) round((float) ($leg->durationMinutes ?? 0) * 60);
            if ($package->delivery_window_start && $currentTimestamp < $package->delivery_window_start->timestamp) {
                $currentTimestamp = $package->delivery_window_start->timestamp;
            }
            if ($package->delivery_window_end && $currentTimestamp > $package->delivery_window_end->timestamp) {
                $timeWindowViolations[] = (int) $package->id;
            }
            $current = ['lat' => (float) $package->dropoff_lat, 'lng' => (float) $package->dropoff_lng];
        }

        if ($end !== null && $packages->isNotEmpty()) {
            $leg = $this->distances->getDistance(
                (string) $current['lat'], (string) $current['lng'],
                (string) $end['lat'], (string) $end['lng']
            );
            $miles += $leg->distanceMiles;
            if ($leg->durationMinutes === null) {
                $durationAvailable = false;
            } else {
                $minutes += $leg->durationMinutes;
            }
            $fallback = $fallback || $leg->isFallback;
            $providers[$leg->provider] = true;
        }

        if ($durationAvailable) {
            $serviceMinutes = function_exists('app') && app()->bound('config')
                ? (int) config('urban_goodz.planning.default_service_time_minutes', 10)
                : 10;
            $minutes += $packages->count() * $serviceMinutes;
        }

        return [
            'miles' => round($miles, 2),
            'minutes' => $durationAvailable ? (int) round($minutes) : null,
            'fallback' => $fallback,
            'provider' => implode(',', array_keys($providers)) ?: 'none',
            'distance_mode' => $fallback ? 'HAVERSINE_FALLBACK' : 'ROAD_NETWORK',
            'time_window_violations' => $timeWindowViolations,
        ];
    }

    private function leg(array $origin, UrbanGoodzRoutePackage $package): DistanceResult
    {
        return $this->distances->getDistance(
            (string) $origin['lat'], (string) $origin['lng'],
            (string) $package->dropoff_lat, (string) $package->dropoff_lng
        );
    }

    private function constraintKey(UrbanGoodzRoutePackage $package): array
    {
        $isReturn = $package->return_required
            || str_starts_with((string) $package->status, 'returning_to_');
        $priority = match ($package->priority) {
            'urgent', 'medical' => 0,
            'high' => 1,
            default => 2,
        };
        $window = $package->delivery_window_end?->timestamp ?? PHP_INT_MAX;
        return [$isReturn ? 1 : 0, $priority, $window];
    }

    private function constraintsPreserved(Collection $packages, Collection $original, array $start): bool
    {
        $positions = $packages->pluck('id')->flip();
        foreach ($original->values() as $index => $package) {
            if ($package->stop_locked) {
                $expected = ($package->locked_stop_order ?: ($index + 1)) - 1;
                if ((int) $positions->get($package->id, -1) !== $expected) {
                    return false;
                }
            }
        }
        $keys = $packages->map(fn ($package) => $this->constraintKey($package))->all();
        for ($i = 1; $i < count($keys); $i++) {
            if ($keys[$i - 1] > $keys[$i]) {
                return false;
            }
        }
        return $this->metrics($packages, $start, null)['time_window_violations'] === [];
    }

    private function hasOrderingConstraints(Collection $packages): bool
    {
        return $packages->contains(fn ($package) =>
            $package->stop_locked
            || in_array($package->priority, ['high', 'urgent', 'medical'], true)
            || $package->delivery_window_end !== null
            || $package->return_required
            || str_starts_with((string) $package->status, 'returning_to_')
        );
    }

    private function samePackageSet(Collection $original, Collection $optimized): bool
    {
        return $original->pluck('id')->sort()->values()->all()
            === $optimized->pluck('id')->sort()->values()->all()
            && $optimized->pluck('id')->unique()->count() === $optimized->count();
    }

    private function assertRouteCanBeOptimized(UrbanGoodzDedicatedRoute $route): void
    {
        if (in_array($route->status, ['completed', 'canceled'], true)) {
            throw new DomainException("A {$route->status} route cannot be optimized.");
        }
    }

    private function assertLockedStopsAreValid(Collection $packages): void
    {
        $occupied = [];
        foreach ($packages->values() as $index => $package) {
            if (!$package->stop_locked) {
                continue;
            }
            $position = (int) ($package->locked_stop_order ?: ($index + 1));
            if ($position < 1 || $position > $packages->count()) {
                throw new DomainException(
                    "Locked stop {$package->tracking_id} has an invalid position {$position}."
                );
            }
            if (isset($occupied[$position])) {
                throw new DomainException(
                    "Locked stops {$occupied[$position]} and {$package->tracking_id} both require position {$position}."
                );
            }
            $occupied[$position] = $package->tracking_id;
        }
    }

    private function assertCapacity(UrbanGoodzDedicatedRoute $route, Collection $packages): void
    {
        $packageCapacity = (int) ($route->capacity_packages ?: $route->max_packages_per_batch);
        if ($packageCapacity > 0 && $packages->count() > $packageCapacity) {
            throw new DomainException(
                "Route has {$packages->count()} packages but capacity is {$packageCapacity}."
            );
        }

        $weight = (float) $packages->sum(fn ($package) => (float) ($package->weight ?? 0));
        if ($route->capacity_weight_lbs !== null && $weight > (float) $route->capacity_weight_lbs) {
            throw new DomainException(
                "Route weight {$weight} lbs exceeds capacity {$route->capacity_weight_lbs} lbs."
            );
        }
    }

    private function constraintsFor(
        UrbanGoodzDedicatedRoute $route,
        Collection $packages,
        bool $returnToOrigin
    ): array {
        return [
            'fixed_start' => true,
            'fixed_end' => $returnToOrigin || $route->end_lat !== null,
            'return_to_origin' => $returnToOrigin,
            'locked_stop_count' => $packages->where('stop_locked', true)->count(),
            'time_windows' => $packages->contains(
                fn ($package) => $package->delivery_window_start || $package->delivery_window_end
            ),
            'capacity_packages' => $route->capacity_packages ?: $route->max_packages_per_batch,
            'capacity_weight_lbs' => $route->capacity_weight_lbs,
            'pickup_before_dropoff' => true,
            'medical_priority' => $packages->contains(fn ($package) => $package->priority === 'medical'),
            'returns' => $packages->contains(fn ($package) => $package->return_required),
            'source_module' => $route->source_module ?: $route->route_type,
        ];
    }

    private function optionalEndPoint(UrbanGoodzDedicatedRoute $route): ?array
    {
        if ($route->end_location === null && $route->end_lat === null && $route->end_lng === null) {
            return null;
        }
        return $this->validatedPoint($route->end_lat, $route->end_lng, 'ending location');
    }

    private function validatedPoint($lat, $lng, string $label): array
    {
        if (!is_numeric($lat) || !is_numeric($lng)) {
            throw new DomainException("The {$label} is missing valid coordinates.");
        }
        $lat = (float) $lat;
        $lng = (float) $lng;
        if ($lat <= -90 || $lat >= 90 || $lng <= -180 || $lng >= 180 || ($lat === 0.0 && $lng === 0.0)) {
            throw new DomainException("The {$label} has invalid coordinates.");
        }
        return compact('lat', 'lng');
    }

    private function audit(
        UrbanGoodzDedicatedRoute $route,
        string $action,
        ?string $actorType,
        ?int $actorId,
        array $details
    ): void {
        if (!Schema::hasTable('urban_goodz_business_portal_audit_logs')) {
            return;
        }
        UrbanGoodzBusinessPortalAuditLog::create([
            'admin_id' => $actorType === 'admin' ? $actorId : null,
            'business_client_user_id' => $actorType === 'business' ? $actorId : null,
            'business_client_id' => $route->business_client_id,
            'action' => $action,
            'mode' => 'deterministic',
            'target_type' => UrbanGoodzDedicatedRoute::class,
            'target_id' => $route->id,
            'details' => $details,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
