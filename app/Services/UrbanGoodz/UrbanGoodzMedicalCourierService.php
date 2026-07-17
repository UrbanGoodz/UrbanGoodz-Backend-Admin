<?php

namespace App\Services\UrbanGoodz;

use App\Models\UrbanGoodzMedicalCourierJob;
use App\Models\UrbanGoodzMedicalCourierCustodyLog;
use App\Models\DeliveryMan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UrbanGoodzMedicalCourierService
{
    private const PER_PAGE = 25;

    private const VALID_TRANSITIONS = [
        'pending' => ['assigned', 'cancelled'],
        'assigned' => ['picked_up', 'cancelled'],
        'picked_up' => ['in_transit', 'cancelled'],
        'in_transit' => ['delivered', 'cancelled'],
        'delivered' => [],
        'cancelled' => [],
    ];

    public function listJobs(array $filters = [], int $page = 1): array
    {
        $query = UrbanGoodzMedicalCourierJob::with('assignedDriver')->latest();

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['specimen_type'])) {
            $query->where('specimen_type', $filters['specimen_type']);
        }
        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }
        if (!empty($filters['assigned_driver_id'])) {
            $query->where('assigned_driver_id', $filters['assigned_driver_id']);
        }
        if (!empty($filters['requires_refrigeration'])) {
            $query->where('requires_refrigeration', true);
        }
        if (!empty($filters['is_biological_hazard'])) {
            $query->where('is_biological_hazard', true);
        }
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('job_number', 'like', "%{$search}%")
                  ->orWhere('pickup_location', 'like', "%{$search}%")
                  ->orWhere('delivery_location', 'like', "%{$search}%")
                  ->orWhere('specimen_type', 'like', "%{$search}%");
            });
        }

        $jobs = $query->paginate($filters['per_page'] ?? self::PER_PAGE);

        return [
            'jobs' => $jobs->items(),
            'meta' => [
                'current_page' => $jobs->currentPage(),
                'last_page' => $jobs->lastPage(),
                'total' => $jobs->total(),
                'per_page' => $jobs->perPage(),
            ],
        ];
    }

    public function getById(int $id): ?UrbanGoodzMedicalCourierJob
    {
        return UrbanGoodzMedicalCourierJob::with(['assignedDriver', 'custodyLogs'])->find($id);
    }

    public function createJob(array $data): UrbanGoodzMedicalCourierJob
    {
        $data['job_number'] = $data['job_number'] ?? self::generateJobNumber();
        $data['status'] = $data['status'] ?? 'pending';
        $data['priority'] = $data['priority'] ?? 'normal';

        return UrbanGoodzMedicalCourierJob::create($data);
    }

    public function updateJob(int $id, array $data): ?UrbanGoodzMedicalCourierJob
    {
        $job = UrbanGoodzMedicalCourierJob::find($id);
        if (!$job) return null;

        $job->update($data);
        return $job->fresh();
    }

    public function deleteJob(int $id): bool
    {
        $job = UrbanGoodzMedicalCourierJob::find($id);
        if (!$job) return false;

        if (in_array($job->status, ['assigned', 'picked_up', 'in_transit'])) {
            return false;
        }

        $job->delete();
        return true;
    }

    public function assignDriver(int $jobId, int $driverId): ?UrbanGoodzMedicalCourierJob
    {
        $job = UrbanGoodzMedicalCourierJob::where('status', 'pending')->find($jobId);
        if (!$job) return null;

        $driver = DeliveryMan::where('id', $driverId)
            ->where('active', 1)
            ->where('application_status', 'approved')
            ->where('has_medical_courier_training', true)
            ->first();
        if (!$driver) return null;

        try {
            DB::beginTransaction();

            $job->update([
                'status' => 'assigned',
                'assigned_driver_id' => $driverId,
                'assigned_at' => now(),
            ]);

            $this->logCustody($jobId, 'assigned', $driver->name ?? 'System', 'admin', null, "Job assigned to driver #{$driverId}");

            DB::commit();
            return $job->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Medical courier assign failed', ['job_id' => $jobId, 'driver_id' => $driverId, 'error' => $e->getMessage()]);
            return null;
        }
    }

    public function updateStatus(int $jobId, string $status, ?int $driverId = null, ?string $notes = null): ?UrbanGoodzMedicalCourierJob
    {
        $job = UrbanGoodzMedicalCourierJob::find($jobId);
        if (!$job) return null;

        $allowed = self::VALID_TRANSITIONS[$job->status] ?? [];
        if (!in_array($status, $allowed)) {
            return null;
        }

        try {
            DB::beginTransaction();

            $update = ['status' => $status];

            if ($status === 'picked_up') {
                $update['picked_up_at'] = now();
            }
            if ($status === 'delivered') {
                $update['delivered_at'] = now();
                
                $finalDriverId = $driverId ?? $job->assigned_driver_id;
                if ($finalDriverId) {
                    try {
                        $driver = DeliveryMan::find($finalDriverId);
                        $metadata = is_array($job->metadata) ? $job->metadata : [];
                        $driverPricingService = resolve(\App\Services\UrbanGoodz\UrbanGoodzDriverPricingService::class);
                        $payoutResult = $driverPricingService->calculatePayout('medical_courier', [
                            'zone_id' => $metadata['zone_id'] ?? null,
                            'mileage' => $job->distance_miles ?? 0.00,
                            'revenue' => $job->payout_amount ?? 0.00,
                            'base_amount' => $job->payout_amount ?? 0.00,
                            'vehicle_id' => $driver?->vehicle_id,
                            'is_urgent' => (bool) ($job->priority === 'urgent'),
                        ]);

                        $driverPricingService->recordEarning([
                            'delivery_man_id' => $finalDriverId,
                            'earning_type' => 'medical_courier',
                            'amount' => $payoutResult['payout'],
                            'status' => 'approved', // Credits wallet immediately
                            'description' => "Payout for completing medical courier job #{$job->id}",
                        ]);
                    } catch (\Exception $e) {
                        Log::error("Failed to calculate/record medical courier payout: " . $e->getMessage());
                    }
                }
            }

            $job->update($update);

            $handlerName = 'System';
            if ($driverId) {
                $driver = DeliveryMan::find($driverId);
                $handlerName = $driver->name ?? "Driver #{$driverId}";
            }
            $this->logCustody($jobId, $status, $handlerName, $driverId ? 'driver' : 'admin', $driverId, $notes);

            DB::commit();
            return $job->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Medical courier status update failed', ['job_id' => $jobId, 'status' => $status, 'error' => $e->getMessage()]);
            return null;
        }
    }

    public function logCustody(int $jobId, string $action, string $handlerName, string $handlerRole = 'driver', ?int $handlerId = null, ?string $notes = null): UrbanGoodzMedicalCourierCustodyLog
    {
        return UrbanGoodzMedicalCourierCustodyLog::create([
            'job_id' => $jobId,
            'action' => $action,
            'handler_name' => $handlerName,
            'handler_role' => $handlerRole,
            'handler_id' => $handlerId,
            'notes' => $notes,
            'logged_at' => now(),
        ]);
    }

    public function getStats(): array
    {
        $base = UrbanGoodzMedicalCourierJob::query();
        return [
            'total_pending' => UrbanGoodzMedicalCourierJob::where('status', 'pending')->count(),
            'total_assigned' => UrbanGoodzMedicalCourierJob::where('status', 'assigned')->count(),
            'total_in_transit' => UrbanGoodzMedicalCourierJob::whereIn('status', ['picked_up', 'in_transit'])->count(),
            'total_delivered_30d' => UrbanGoodzMedicalCourierJob::where('status', 'delivered')
                ->where('delivered_at', '>=', now()->subDays(30))->count(),
            'total_payout_30d' => UrbanGoodzMedicalCourierJob::where('status', 'delivered')
                ->where('delivered_at', '>=', now()->subDays(30))
                ->sum('payout_amount'),
            'by_specimen_type' => UrbanGoodzMedicalCourierJob::selectRaw('specimen_type, COUNT(*) as count')
                ->where('status', '!=', 'cancelled')
                ->groupBy('specimen_type')
                ->pluck('count', 'specimen_type')
                ->toArray(),
            'by_priority' => UrbanGoodzMedicalCourierJob::selectRaw('priority, COUNT(*) as count')
                ->where('status', '!=', 'cancelled')
                ->groupBy('priority')
                ->pluck('count', 'priority')
                ->toArray(),
        ];
    }

    private function generateJobNumber(): string
    {
        $prefix = 'MC';
        $date = now()->format('ymd');
        $last = UrbanGoodzMedicalCourierJob::where('job_number', 'like', "{$prefix}{$date}%")
            ->orderByDesc('job_number')
            ->value('job_number');

        if ($last) {
            $seq = (int) substr($last, -4) + 1;
        } else {
            $seq = 1;
        }

        return $prefix . $date . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
