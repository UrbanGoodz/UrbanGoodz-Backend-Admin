<?php

namespace App\Services\UrbanGoodz;

use App\Models\UrbanGoodzLoadBoardLoad;
use App\Models\DeliveryMan;
use App\Models\Order;
use App\Services\UrbanGoodz\LoadBoard\DatAdapter;
use App\Services\UrbanGoodz\LoadBoard\TruckstopAdapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UrbanGoodzLoadBoardService
{
    private const PER_PAGE = 25;

    public function listAvailable(array $filters = [], int $page = 1): array
    {
        $query = UrbanGoodzLoadBoardLoad::available()->latest();

        if (!empty($filters['origin_state'])) {
            $query->where('origin_state', $filters['origin_state']);
        }
        if (!empty($filters['destination_state'])) {
            $query->where('destination_state', $filters['destination_state']);
        }
        if (!empty($filters['load_type'])) {
            $query->where('load_type', $filters['load_type']);
        }
        if (!empty($filters['equipment_type'])) {
            $query->where('equipment_type', $filters['equipment_type']);
        }
        if (!empty($filters['min_payout'])) {
            $query->where('payout_amount', '>=', $filters['min_payout']);
        }
        if (!empty($filters['max_distance_miles'])) {
            $query->where('distance_miles', '<=', $filters['max_distance_miles']);
        }
        if (!empty($filters['is_hazmat'])) {
            $query->where('is_hazmat', true);
        }
        if (!empty($filters['requires_liftgate'])) {
            $query->where('requires_liftgate', true);
        }
        if (!empty($filters['is_expedited'])) {
            $query->where('is_expedited', true);
        }

        $loads = $query
            ->paginate($filters['per_page'] ?? self::PER_PAGE)
            ->withQueryString();

        return [
            'loads' => $loads,
            'meta' => [
                'current_page' => $loads->currentPage(),
                'last_page' => $loads->lastPage(),
                'total' => $loads->total(),
                'per_page' => $loads->perPage(),
            ],
        ];
    }

    public function getById(int $id): ?UrbanGoodzLoadBoardLoad
    {
        return UrbanGoodzLoadBoardLoad::find($id);
    }

    public function acceptLoad(int $loadId, int $driverId): ?UrbanGoodzLoadBoardLoad
    {
        $load = UrbanGoodzLoadBoardLoad::where('status', 'available')->find($loadId);
        if (!$load) {
            return null;
        }

        $driver = DeliveryMan::where('id', $driverId)
            ->where('active', 1)
            ->where('application_status', 'approved')
            ->first();
        if (!$driver) {
            return null;
        }

        try {
            DB::beginTransaction();

            $load->update([
                'status' => 'assigned',
                'assigned_driver_id' => $driverId,
                'assigned_at' => now(),
            ]);

            $driver->increment('current_orders');

            DB::commit();
            return $load->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Load board accept failed', ['load_id' => $loadId, 'driver_id' => $driverId, 'error' => $e->getMessage()]);
            return null;
        }
    }

    public function updateStatus(int $loadId, string $status, ?int $driverId = null): ?UrbanGoodzLoadBoardLoad
    {
        $load = UrbanGoodzLoadBoardLoad::find($loadId);
        if (!$load) {
            return null;
        }

        $validTransitions = [
            'assigned' => ['in_transit', 'cancelled'],
            'in_transit' => ['picked_up', 'cancelled'],
            'picked_up' => ['delivered', 'cancelled'],
        ];

        $allowed = $validTransitions[$load->status] ?? [];
        if (!in_array($status, $allowed)) {
            return null;
        }

        try {
            DB::beginTransaction();

            $update = ['status' => $status];

            if ($status === 'in_transit' || $status === 'picked_up') {
                $update['picked_up_at'] = now();
            }
            if ($status === 'delivered') {
                $update['delivered_at'] = now();
                $finalDriverId = $driverId ?? $load->assigned_driver_id;
                if ($finalDriverId) {
                    $driver = DeliveryMan::find($finalDriverId);
                    if ($driver) {
                        $driver->decrement('current_orders');
                    }
                    // Trigger payout calculation and log earnings for logistics load completion
                    try {
                        $driverPricingService = resolve(\App\Services\UrbanGoodz\UrbanGoodzDriverPricingService::class);
                        $metadata = is_array($load->metadata) ? $load->metadata : [];
                        $payoutResult = $driverPricingService->calculatePayout('logistics_loads', [
                            'zone_id' => $metadata['zone_id'] ?? null,
                            'mileage' => $load->distance_miles ?? 0.00,
                            'duration' => $load->estimated_duration_minutes ?? 0.00,
                            'revenue' => $load->payout_amount ?? 0.00,
                            'base_amount' => $load->payout_amount ?? 0.00,
                            'vehicle_id' => $driver?->vehicle_id,
                        ]);

                        $driverPricingService->recordEarning([
                            'delivery_man_id' => $finalDriverId,
                            'earning_type' => 'logistics_loads',
                            'amount' => $payoutResult['payout'],
                            'status' => 'approved', // Credits wallet immediately
                            'description' => "Payout for completing load board load #{$load->id}",
                        ]);
                    } catch (\Exception $e) {
                        Log::error("Failed to calculate/record load board payout: " . $e->getMessage());
                    }
                }
            }
            if ($status === 'cancelled' && $load->assigned_driver_id) {
                $driver = DeliveryMan::find($load->assigned_driver_id);
                if ($driver) {
                    $driver->decrement('current_orders');
                }
                $update['assigned_driver_id'] = null;
                $update['assigned_at'] = null;
            }

            $load->update($update);

            DB::commit();
            return $load->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Load board status update failed', ['load_id' => $loadId, 'status' => $status, 'error' => $e->getMessage()]);
            return null;
        }
    }

    public function createLoad(array $data): UrbanGoodzLoadBoardLoad
    {
        $data['provider'] = $data['provider'] ?? 'internal';
        $data['status'] = $data['status'] ?? 'available';
        $metadata = isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : [];

        // Calculate and enforce pricing rules at creation if driver payout is not pre-set
        try {
            $driverPricingService = resolve(\App\Services\UrbanGoodz\UrbanGoodzDriverPricingService::class);
            $payoutResult = $driverPricingService->calculatePayout('logistics_loads', [
                'zone_id' => $data['zone_id'] ?? $metadata['zone_id'] ?? null,
                'mileage' => $data['distance_miles'] ?? $data['mileage'] ?? 0.00,
                'duration' => $data['estimated_duration_minutes'] ?? $data['duration'] ?? 0.00,
                'revenue' => $data['payout_amount'] ?? $data['rate'] ?? 0.00,
                'base_amount' => $data['payout_amount'] ?? $data['rate'] ?? 0.00,
            ]);
            $metadata['driver_payout_amount'] = $metadata['driver_payout_amount'] ?? $payoutResult['payout'];
            $metadata['driver_pricing_policy_id'] = $metadata['driver_pricing_policy_id'] ?? $payoutResult['policy_id'];
            $metadata['driver_pricing_model'] = $metadata['driver_pricing_model'] ?? $payoutResult['payout_model'];
        } catch (\Exception $e) {
            Log::error("Failed to apply pricing policy at load creation: " . $e->getMessage());
        }

        $data['metadata'] = $metadata;

        return UrbanGoodzLoadBoardLoad::create($data);
    }

    public function updateLoad(int $id, array $data): ?UrbanGoodzLoadBoardLoad
    {
        $load = UrbanGoodzLoadBoardLoad::find($id);
        if (!$load) {
            return null;
        }

        $load->update($data);
        return $load->fresh();
    }

    public function deleteLoad(int $id): bool
    {
        $load = UrbanGoodzLoadBoardLoad::find($id);
        if (!$load) {
            return false;
        }

        if (in_array($load->status, ['assigned', 'in_transit'])) {
            return false;
        }

        $load->delete();
        return true;
    }

    public function getStats(): array
    {
        return [
            'total_available' => UrbanGoodzLoadBoardLoad::available()->count(),
            'total_assigned' => UrbanGoodzLoadBoardLoad::where('status', 'assigned')->count(),
            'total_in_transit' => UrbanGoodzLoadBoardLoad::where('status', 'in_transit')->count(),
            'total_delivered' => UrbanGoodzLoadBoardLoad::where('status', 'delivered')
                ->where('delivered_at', '>=', now()->subDays(30))->count(),
            'total_payout' => UrbanGoodzLoadBoardLoad::where('status', 'delivered')
                ->where('delivered_at', '>=', now()->subDays(30))
                ->sum('payout_amount'),
            'avg_payout' => UrbanGoodzLoadBoardLoad::where('status', 'delivered')
                ->where('delivered_at', '>=', now()->subDays(30))
                ->avg('payout_amount'),
            'by_load_type' => UrbanGoodzLoadBoardLoad::selectRaw('load_type, COUNT(*) as count')
                ->where('status', 'available')
                ->groupBy('load_type')
                ->pluck('count', 'load_type')
                ->toArray(),
            'by_state' => UrbanGoodzLoadBoardLoad::selectRaw('origin_state, COUNT(*) as count')
                ->where('status', 'available')
                ->groupBy('origin_state')
                ->orderByDesc('count')
                ->limit(10)
                ->pluck('count', 'origin_state')
                ->toArray(),
        ];
    }

    public function syncFromProvider(string $provider, array $externalLoads): int
    {
        $count = 0;

        foreach ($externalLoads as $extLoad) {
            $existing = null;
            if (!empty($extLoad['external_id'])) {
                $existing = UrbanGoodzLoadBoardLoad::where('provider', $provider)
                    ->where('external_id', $extLoad['external_id'])
                    ->first();
            }

            if ($existing) {
                if ($existing->status === 'available') {
                    $existing->update($extLoad);
                    $count++;
                }
            } else {
                $extLoad['provider'] = $provider;
                $extLoad['status'] = 'available';
                UrbanGoodzLoadBoardLoad::create($extLoad);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Sync from all enabled external providers.
     * Returns summary per provider.
     */
    public function syncAllProviders(array $filters = [], int $maxPerProvider = 250): array
    {
        $results = [];
        $providers = config('urban_goodz_load_board.providers', []);

        foreach ($providers as $slug => $config) {
            if (empty($config['enabled'])) {
                continue;
            }

            $adapter = match ($slug) {
                'dat' => new DatAdapter($config),
                'truckstop' => new TruckstopAdapter($config),
                default => null,
            };

            if (!$adapter || !$adapter->isConfigured()) {
                $results[$slug] = ['status' => 'skipped', 'reason' => 'not_configured'];
                continue;
            }

            try {
                $mergedFilters = array_merge($config['default_filters'] ?? [], $filters);
                $loads = $adapter->fetchLoads($mergedFilters, $maxPerProvider);
                $synced = $this->syncFromProvider($slug, $loads);

                $results[$slug] = [
                    'status' => 'ok',
                    'fetched' => count($loads),
                    'synced' => $synced,
                ];
            } catch (\Exception $e) {
                $results[$slug] = [
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
                Log::error("Load board syncAllProviders failed for {$slug}", ['error' => $e->getMessage()]);
            }
        }

        return $results;
    }

    /**
     * Purge old available loads from external providers that haven't been refreshed.
     */
    public function purgeStaleLoads(int $days = 7): int
    {
        return UrbanGoodzLoadBoardLoad::where('status', 'available')
            ->where('provider', '!=', 'internal')
            ->where('updated_at', '<', now()->subDays($days))
            ->delete();
    }

    public function getLastSync(string $key): ?array
    {
        return Cache::get("load_board_last_sync:{$key}");
    }

    public function getRateLimit(string $key): int
    {
        return (int) Cache::get("load_board_rate_limit:{$key}", 0);
    }
}
