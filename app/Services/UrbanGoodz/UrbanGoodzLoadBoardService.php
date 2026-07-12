<?php

namespace App\Services\UrbanGoodz;

use App\Models\UrbanGoodzLoadBoardLoad;
use App\Models\DeliveryMan;
use App\Models\Order;
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

        $loads = $query->paginate($filters['per_page'] ?? self::PER_PAGE);

        return [
            'loads' => $loads->items(),
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
                if ($driverId || $load->assigned_driver_id) {
                    $driver = DeliveryMan::find($driverId ?? $load->assigned_driver_id);
                    if ($driver) {
                        $driver->decrement('current_orders');
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
            $existing = UrbanGoodzLoadBoardLoad::where('provider', $provider)
                ->where('external_id', $extLoad['external_id'] ?? null)
                ->first();

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
}
