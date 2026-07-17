<?php

namespace App\Services\UrbanGoodz;

use App\Models\UrbanGoodzLoadBoardLoad;
use App\Models\UrbanGoodzLoadBoardAuditLog;
use App\Models\UrbanGoodzLoadBoardBid;
use App\Models\UrbanGoodzDriverEarning;
use App\Models\UrbanGoodzPaymentLedger;
use App\Models\UrbanGoodzBusinessClient;
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

    private const VALID_TRANSITIONS = [
        'available'    => ['offered', 'assigned', 'cancelled', 'exception'],
        'sourced'      => ['under_review', 'draft', 'cancelled'],
        'draft'        => ['under_review', 'cancelled'],
        'under_review' => ['recommended', 'available', 'cancelled'],
        'recommended'  => ['offered', 'assigned', 'available', 'cancelled'],
        'offered'      => ['assigned', 'cancelled', 'available'],
        'assigned'     => ['in_transit', 'cancelled', 'exception'],
        'in_transit'   => ['picked_up', 'cancelled', 'exception'],
        'picked_up'    => ['delivered', 'cancelled', 'exception'],
        'delivered'    => ['completed'],
        'completed'    => [],
        'cancelled'    => [],
        'exception'    => ['assigned', 'in_transit', 'cancelled'],
    ];

    public function listAvailable(array $filters = [], int $page = 1): array
    {
        $query = UrbanGoodzLoadBoardLoad::query()->latest();

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        } else {
            $query->whereNotIn('status', ['cancelled', 'completed']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('load_number', 'like', "%{$search}%")
                    ->orWhere('origin_city', 'like', "%{$search}%")
                    ->orWhere('origin_state', 'like', "%{$search}%")
                    ->orWhere('destination_city', 'like', "%{$search}%")
                    ->orWhere('destination_state', 'like', "%{$search}%")
                    ->orWhere('commodity_description', 'like', "%{$search}%");
            });
        }

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
        if (!empty($filters['provider'])) {
            $query->where('provider', $filters['provider']);
        }
        if (!empty($filters['business_client_id'])) {
            $query->where('business_client_id', $filters['business_client_id']);
        }
        if (!empty($filters['assigned_driver_id'])) {
            $query->where('assigned_driver_id', $filters['assigned_driver_id']);
        }

        $loads = $query->with(['assignedDriver', 'businessClient', 'dispatcherUser'])
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
        return UrbanGoodzLoadBoardLoad::with([
            'assignedDriver', 'approvedBy', 'reviewedBy', 'businessClient',
            'dispatchCompany', 'dispatcherUser', 'commissions', 'auditLogs.actor',
        ])->find($id);
    }

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::VALID_TRANSITIONS[$from] ?? []);
    }

    public function acceptLoad(int $loadId, int $driverId, ?int $adminId = null): ?UrbanGoodzLoadBoardLoad
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

            $oldStatus = $load->status;
            $load->update([
                'status' => 'assigned',
                'assigned_driver_id' => $driverId,
                'assigned_by' => $adminId,
                'assigned_at' => now(),
            ]);

            $driver->increment('current_orders');
            $load->logEvent('status_change', $oldStatus, 'assigned', null, 'admin', $adminId, "Driver {$driver->f_name} {$driver->l_name} assigned");

            DB::commit();
            return $load->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Load board accept failed', ['load_id' => $loadId, 'driver_id' => $driverId, 'error' => $e->getMessage()]);
            return null;
        }
    }

    public function reassignLoad(int $loadId, int $newDriverId, ?int $adminId = null, ?string $reason = null): ?UrbanGoodzLoadBoardLoad
    {
        $load = UrbanGoodzLoadBoardLoad::where('status', 'assigned')->find($loadId);
        if (!$load) {
            return null;
        }

        $newDriver = DeliveryMan::where('id', $newDriverId)
            ->where('active', 1)
            ->where('application_status', 'approved')
            ->first();
        if (!$newDriver) {
            return null;
        }

        try {
            DB::beginTransaction();

            $oldDriverId = $load->assigned_driver_id;
            if ($oldDriverId) {
                $oldDriver = DeliveryMan::find($oldDriverId);
                if ($oldDriver) {
                    $oldDriver->decrement('current_orders');
                }
            }

            $load->update([
                'assigned_driver_id' => $newDriverId,
                'assigned_by' => $adminId,
                'assigned_at' => now(),
            ]);

            $newDriver->increment('current_orders');
            $load->logEvent('reassign', (string) $oldDriverId, (string) $newDriverId, [
                'reason' => $reason,
            ], 'admin', $adminId, "Reassigned from driver #{$oldDriverId} to #{$newDriverId}");

            DB::commit();
            return $load->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Load board reassign failed', ['load_id' => $loadId, 'error' => $e->getMessage()]);
            return null;
        }
    }

    public function updateStatus(int $loadId, string $status, ?int $actorId = null, ?string $actorType = 'admin', ?string $notes = null): ?UrbanGoodzLoadBoardLoad
    {
        $load = UrbanGoodzLoadBoardLoad::find($loadId);
        if (!$load) {
            return null;
        }

        if (!$this->canTransition($load->status, $status)) {
            return null;
        }

        try {
            DB::beginTransaction();

            $oldStatus = $load->status;
            $update = ['status' => $status];

            if ($status === 'reviewed' || $status === 'recommended') {
                $update['reviewed_by'] = $actorId;
                $update['reviewed_at'] = now();
            }

            if ($status === 'in_transit') {
                $update['picked_up_at'] = now();
            }

            if ($status === 'picked_up') {
                $update['picked_up_at'] = $load->picked_up_at ?? now();
            }

            if ($status === 'delivered') {
                $update['delivered_at'] = now();
            }

            if ($status === 'completed') {
                $update['delivered_at'] = $load->delivered_at ?? now();
                $finalDriverId = $load->assigned_driver_id;
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
                            'load_id' => $load->id,
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

            if ($status === 'cancelled') {
                $update['cancelled_at'] = now();
                if ($load->assigned_driver_id) {
                    $driver = DeliveryMan::find($load->assigned_driver_id);
                    if ($driver) {
                        $driver->decrement('current_orders');
                    }
                    $update['assigned_driver_id'] = null;
                    $update['assigned_at'] = null;
                }
            }

            $load->update($update);
            $load->logEvent('status_change', $oldStatus, $status, null, $actorType, $actorId, $notes);

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

        if (empty($data['load_number'])) {
            $data['load_number'] = 'UG-' . strtoupper(uniqid());
        }

        $load = UrbanGoodzLoadBoardLoad::create($data);
        $load->logEvent('created', null, $data['status'], null, 'admin', null, 'Load created');

        return $load;
    }

    public function updateLoad(int $id, array $data, ?int $actorId = null): ?UrbanGoodzLoadBoardLoad
    {
        $load = UrbanGoodzLoadBoardLoad::find($id);
        if (!$load) {
            return null;
        }

        $load->update($data);
        $load->logEvent('updated', null, json_encode($data), null, 'admin', $actorId, 'Load updated');

        return $load->fresh();
    }

    public function deleteLoad(int $id): bool
    {
        $load = UrbanGoodzLoadBoardLoad::find($id);
        if (!$load) {
            return false;
        }

        if (in_array($load->status, ['assigned', 'in_transit', 'picked_up', 'delivered'])) {
            return false;
        }

        $load->logEvent('deleted', $load->status, null);
        $load->delete();
        return true;
    }

    public function reviewLoad(int $id, string $decision, ?int $reviewerId = null, ?string $notes = null): ?UrbanGoodzLoadBoardLoad
    {
        $load = UrbanGoodzLoadBoardLoad::find($id);
        if (!$load) {
            return null;
        }

        if (!in_array($load->status, ['under_review', 'sourced', 'draft'])) {
            return null;
        }

        $newStatus = match ($decision) {
            'approve' => 'recommended',
            'reject' => 'cancelled',
            'send_to_board' => 'available',
            default => null,
        };

        if (!$newStatus) {
            return null;
        }

        return $this->updateStatus($id, $newStatus, $reviewerId, 'admin', $notes);
    }

    public function getStats(): array
    {
        $thirtyDaysAgo = now()->subDays(30);

        return [
            'total_available' => UrbanGoodzLoadBoardLoad::where('status', 'available')->count(),
            'total_sourced' => UrbanGoodzLoadBoardLoad::where('status', 'sourced')->count(),
            'total_draft' => UrbanGoodzLoadBoardLoad::where('status', 'draft')->count(),
            'total_under_review' => UrbanGoodzLoadBoardLoad::where('status', 'under_review')->count(),
            'total_recommended' => UrbanGoodzLoadBoardLoad::where('status', 'recommended')->count(),
            'total_offered' => UrbanGoodzLoadBoardLoad::where('status', 'offered')->count(),
            'total_assigned' => UrbanGoodzLoadBoardLoad::where('status', 'assigned')->count(),
            'total_in_transit' => UrbanGoodzLoadBoardLoad::where('status', 'in_transit')->count(),
            'total_picked_up' => UrbanGoodzLoadBoardLoad::where('status', 'picked_up')->count(),
            'total_delivered' => UrbanGoodzLoadBoardLoad::where('status', 'delivered')
                ->where('delivered_at', '>=', $thirtyDaysAgo)->count(),
            'total_completed' => UrbanGoodzLoadBoardLoad::where('status', 'completed')
                ->where('updated_at', '>=', $thirtyDaysAgo)->count(),
            'total_cancelled' => UrbanGoodzLoadBoardLoad::where('status', 'cancelled')
                ->where('cancelled_at', '>=', $thirtyDaysAgo)->count(),
            'total_exception' => UrbanGoodzLoadBoardLoad::where('status', 'exception')->count(),
            'total_payout' => UrbanGoodzLoadBoardLoad::whereIn('status', ['delivered', 'completed'])
                ->where('delivered_at', '>=', $thirtyDaysAgo)
                ->sum('payout_amount'),
            'total_customer_charges' => UrbanGoodzLoadBoardLoad::whereIn('status', ['delivered', 'completed'])
                ->where('delivered_at', '>=', $thirtyDaysAgo)
                ->sum('customer_price'),
            'total_platform_margin' => UrbanGoodzLoadBoardLoad::whereIn('status', ['delivered', 'completed'])
                ->where('delivered_at', '>=', $thirtyDaysAgo)
                ->sum('platform_margin'),
            'avg_payout' => UrbanGoodzLoadBoardLoad::whereIn('status', ['delivered', 'completed'])
                ->where('delivered_at', '>=', $thirtyDaysAgo)
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
            'by_status' => UrbanGoodzLoadBoardLoad::selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
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
                if (in_array($existing->status, ['available', 'sourced'])) {
                    $existing->update($extLoad);
                    $count++;
                }
            } else {
                $extLoad['provider'] = $provider;
                $extLoad['status'] = 'sourced';
                UrbanGoodzLoadBoardLoad::create($extLoad);
                $count++;
            }
        }

        return $count;
    }

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

    public function purgeStaleLoads(int $days = 7): int
    {
        return UrbanGoodzLoadBoardLoad::where('status', 'sourced')
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

    public function placeBid(int $loadId, int $driverId, float $bidAmount, ?string $message = null): ?UrbanGoodzLoadBoardBid
    {
        $load = UrbanGoodzLoadBoardLoad::where('status', 'available')->find($loadId);
        if (!$load) {
            return null;
        }

        $driver = DeliveryMan::where('id', $driverId)
            ->where('active', 1)
            ->where('application_status', 'approved')
            ->where('load_board_eligible', true)
            ->first();
        if (!$driver) {
            return null;
        }

        $existingBid = UrbanGoodzLoadBoardBid::where('load_id', $loadId)
            ->where('driver_id', $driverId)
            ->where('status', 'pending')
            ->first();
        if ($existingBid) {
            return null;
        }

        $bid = UrbanGoodzLoadBoardBid::create([
            'load_id' => $loadId,
            'driver_id' => $driverId,
            'bid_amount' => $bidAmount,
            'bid_message' => $message,
            'status' => 'pending',
        ]);

        $load->logEvent('bid_placed', null, (string) $bidAmount, [
            'bid_id' => $bid->id,
            'driver_id' => $driverId,
        ], 'driver', $driverId, "Bid placed: \${$bidAmount}");

        return $bid;
    }

    public function acceptBid(int $bidId, int $actorId): ?UrbanGoodzLoadBoardLoad
    {
        $bid = UrbanGoodzLoadBoardBid::with('load')->pending()->find($bidId);
        if (!$bid) {
            return null;
        }

        $load = $bid->load;
        if (!$load || $load->status !== 'available') {
            return null;
        }

        try {
            DB::beginTransaction();

            $bid->update([
                'status' => 'accepted',
                'responded_at' => now(),
                'responded_by' => $actorId,
            ]);

            UrbanGoodzLoadBoardBid::where('load_id', $load->id)
                ->where('id', '!=', $bidId)
                ->where('status', 'pending')
                ->update([
                    'status' => 'rejected',
                    'responded_at' => now(),
                    'responded_by' => $actorId,
                    'notes' => 'Rejected: another bid accepted',
                ]);

            $result = $this->acceptLoad($load->id, $bid->driver_id, $actorId);
            if (!$result) {
                DB::rollBack();
                return null;
            }

            $load->logEvent('bid_accepted', null, (string) $bid->bid_amount, [
                'bid_id' => $bid->id,
                'driver_id' => $bid->driver_id,
            ], 'admin', $actorId, "Bid accepted from driver #{$bid->driver_id}");

            DB::commit();
            return $load->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Load board bid accept failed', ['bid_id' => $bidId, 'error' => $e->getMessage()]);
            return null;
        }
    }

    public function rejectBid(int $bidId, int $actorId): bool
    {
        $bid = UrbanGoodzLoadBoardBid::pending()->find($bidId);
        if (!$bid) {
            return false;
        }

        $bid->update([
            'status' => 'rejected',
            'responded_at' => now(),
            'responded_by' => $actorId,
        ]);

        if ($bid->load) {
            $bid->load->logEvent('bid_rejected', null, (string) $bid->bid_amount, [
                'bid_id' => $bid->id,
                'driver_id' => $bid->driver_id,
            ], 'admin', $actorId, "Bid rejected from driver #{$bid->driver_id}");
        }

        return true;
    }

    public function withdrawBid(int $bidId, int $driverId): bool
    {
        $bid = UrbanGoodzLoadBoardBid::pending()->find($bidId);
        if (!$bid || $bid->driver_id !== $driverId) {
            return false;
        }

        $bid->update(['status' => 'withdrawn']);

        if ($bid->load) {
            $bid->load->logEvent('bid_withdrawn', null, (string) $bid->bid_amount, [
                'bid_id' => $bid->id,
                'driver_id' => $driverId,
            ], 'driver', $driverId, 'Bid withdrawn');
        }

        return true;
    }

    public function getBidsForLoad(int $loadId): \Illuminate\Support\Collection
    {
        return UrbanGoodzLoadBoardBid::with(['driver'])
            ->where('load_id', $loadId)
            ->latest()
            ->get();
    }

    public function getBidsForDriver(int $driverId): \Illuminate\Support\Collection
    {
        return UrbanGoodzLoadBoardBid::with(['load'])
            ->where('driver_id', $driverId)
            ->latest()
            ->get();
    }

    public function getEligibleDriversForLoad(UrbanGoodzLoadBoardLoad $load): \Illuminate\Database\Eloquent\Collection
    {
        $query = DeliveryMan::where('active', 1)
            ->where('application_status', 'approved')
            ->where('load_board_eligible', true);

        if ($load->equipment_type) {
            $query->where(function ($q) use ($load) {
                $q->where('vehicle_type', $load->equipment_type)
                  ->orWhere('preferred_vehicle_types', 'like', '%' . $load->equipment_type . '%');
            });
        }

        if ($load->is_hazmat) {
            $query->where('has_hazmat_endorsement', true);
        }

        return $query->get();
    }

    public function completeWithEarnings(int $loadId, ?int $actorId = null, ?string $actorType = 'admin'): ?UrbanGoodzLoadBoardLoad
    {
        $load = UrbanGoodzLoadBoardLoad::find($loadId);
        if (!$load || $load->status !== 'delivered') {
            return null;
        }

        try {
            DB::beginTransaction();

            $result = $this->updateStatus($loadId, 'completed', $actorId, $actorType, 'Load completed with earnings');
            if (!$result) {
                DB::rollBack();
                return null;
            }

            $load = $result;

            if (
                $load->assigned_driver_id
                && $load->effective_driver_payout > 0
                && !UrbanGoodzDriverEarning::where('load_id', $load->id)->exists()
            ) {
                UrbanGoodzDriverEarning::create([
                    'delivery_man_id' => $load->assigned_driver_id,
                    'load_id' => $load->id,
                    'earning_type' => 'load_board_delivery',
                    'amount' => $load->effective_driver_payout,
                    'status' => 'pending',
                    'description' => 'Load board delivery: ' . $load->load_number,
                ]);
            }

            if ($load->customer_price > 0 && $load->business_client_id) {
                $idempotencyKey = "load_board_income_{$load->id}";
                $existingLedger = UrbanGoodzPaymentLedger::where('idempotency_key', $idempotencyKey)->first();
                if (!$existingLedger) {
                    UrbanGoodzPaymentLedger::create([
                        'ledger_number' => UrbanGoodzPaymentLedger::nextLedgerNumber(),
                        'feature' => 'load_board',
                        'payable_type' => UrbanGoodzBusinessClient::class,
                        'payable_id' => $load->business_client_id,
                        'event_type' => 'load_completed',
                        'direction' => 'inbound',
                        'amount' => $load->customer_price,
                        'currency' => 'USD',
                        'payment_method' => 'platform',
                        'payment_status' => 'pending',
                        'idempotency_key' => $idempotencyKey,
                        'customer_id' => $load->business_client_id,
                        'delivery_man_id' => $load->assigned_driver_id,
                        'created_by_admin_id' => $actorId,
                        'metadata' => [
                            'load_id' => $load->id,
                            'load_number' => $load->load_number,
                            'driver_payout' => $load->effective_driver_payout,
                            'platform_margin' => $load->effective_margin,
                        ],
                    ]);
                }
            }

            DB::commit();
            return $load->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Load board complete with earnings failed', ['load_id' => $loadId, 'error' => $e->getMessage()]);
            return null;
        }
    }
}
