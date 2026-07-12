<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzLoadBoardLoad;
use App\Models\UrbanGoodzMedicalCourierJob;
use App\Models\UrbanGoodzMedicalCourierCustodyLog;
use App\Services\UrbanGoodz\UrbanGoodzLoadBoardService;
use App\Services\UrbanGoodz\UrbanGoodzMedicalCourierService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UrbanGoodzOpportunityController extends Controller
{
    // =========================================================================
    // Earn Money
    // =========================================================================

    public function earnMoneyOpportunities()
    {
        return response()->json([
            'success' => true,
            'message' => 'Earning opportunities retrieved successfully',
            'data' => [
                [
                    'id' => 1,
                    'title' => 'Deliver Food Package',
                    'description' => 'Delivery job from local kitchen to customer house.',
                    'earnings' => 15.50,
                    'status' => 'available',
                ],
                [
                    'id' => 2,
                    'title' => 'Retail Merch Setup',
                    'description' => 'Assemble display stand at storefront.',
                    'earnings' => 45.00,
                    'status' => 'available',
                ]
            ],
        ]);
    }

    public function earnMoneyOpportunity($record)
    {
        return response()->json([
            'success' => true,
            'message' => 'Opportunity details retrieved successfully',
            'data' => [
                'id' => (int)$record,
                'title' => 'Opportunity ' . $record,
                'description' => 'Sample description for opportunity ' . $record,
                'earnings' => 25.00,
                'status' => 'available',
            ],
        ]);
    }

    public function acceptEarnMoneyOpportunity(Request $request, $record)
    {
        Log::info('Earn money opportunity accepted', ['record' => $record, 'ip' => $request->ip()]);
        return response()->json([
            'success' => true,
            'message' => 'Opportunity accepted successfully',
            'data' => [
                'id' => (int)$record,
                'status' => 'accepted',
            ],
        ]);
    }

    // =========================================================================
    // Logistics Jobs
    // =========================================================================

    public function logisticsJobs()
    {
        return response()->json([
            'success' => true,
            'message' => 'Logistics jobs retrieved successfully',
            'data' => [
                [
                    'id' => 10,
                    'title' => 'Warehouse Package Route',
                    'weight' => '120 lbs',
                    'payout' => 85.00,
                    'status' => 'available',
                ]
            ],
        ]);
    }

    public function logisticsJob($record)
    {
        return response()->json([
            'success' => true,
            'message' => 'Logistics job details retrieved successfully',
            'data' => [
                'id' => (int)$record,
                'title' => 'Logistics Job ' . $record,
                'status' => 'available',
            ],
        ]);
    }

    public function acceptLogisticsJob(Request $request, $record)
    {
        Log::info('Logistics job accepted', ['record' => $record]);
        return response()->json([
            'success' => true,
            'message' => 'Logistics job accepted successfully',
            'data' => [
                'id' => (int)$record,
                'status' => 'accepted',
            ],
        ]);
    }

    public function updateLogisticsJobStatus(Request $request, $record)
    {
        $status = $request->input('status', 'in_transit');
        Log::info('Logistics job status updated', ['record' => $record, 'status' => $status]);
        return response()->json([
            'success' => true,
            'message' => 'Logistics job status updated successfully',
            'data' => [
                'id' => (int)$record,
                'status' => $status,
            ],
        ]);
    }

    // =========================================================================
    // Load Board
    // =========================================================================

    public function loadBoardLoads(Request $request, UrbanGoodzLoadBoardService $loadBoardService)
    {
        $filters = $request->only([
            'origin_state', 'destination_state', 'load_type', 'equipment_type',
            'min_payout', 'max_distance_miles',
        ]);

        $result = $loadBoardService->listAvailable($filters);

        return response()->json([
            'success' => true,
            'message' => 'Load board loads retrieved successfully',
            'data' => $result['loads'],
            'meta' => $result['meta'],
        ]);
    }

    public function loadBoardLoad($record, UrbanGoodzLoadBoardService $loadBoardService)
    {
        $load = $loadBoardService->getById((int) $record);

        if (!$load) {
            return response()->json([
                'success' => false,
                'message' => 'Load not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Load details retrieved successfully',
            'data' => $load,
        ]);
    }

    public function acceptLoadBoardLoad(Request $request, $record, UrbanGoodzLoadBoardService $loadBoardService)
    {
        $driverId = $request->user()?->id;
        if (!$driverId) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        $load = $loadBoardService->acceptLoad((int) $record, $driverId);

        if (!$load) {
            return response()->json([
                'success' => false,
                'message' => 'Load not available or driver not eligible',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Load accepted successfully',
            'data' => $load,
        ]);
    }

    public function updateLoadBoardLoadStatus(Request $request, $record, UrbanGoodzLoadBoardService $loadBoardService)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:in_transit,picked_up,delivered,cancelled',
        ]);

        $driverId = $request->user()?->id;
        $load = $loadBoardService->updateStatus((int) $record, $validated['status'], $driverId);

        if (!$load) {
            return response()->json([
                'success' => false,
                'message' => 'Load not found or invalid status transition',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Load status updated successfully',
            'data' => $load,
        ]);
    }

    // =========================================================================
    // Medical Courier
    // =========================================================================

    public function medicalCourierJobs()
    {
        $jobs = UrbanGoodzMedicalCourierJob::where('status', 'pending')
            ->orderBy('priority', 'desc')
            ->limit(50)
            ->get()
            ->map(fn($job) => [
                'id' => $job->id,
                'job_number' => $job->job_number,
                'specimen_type' => $job->specimen_type,
                'pickup_location' => $job->pickup_facility_name ?? $job->pickup_location,
                'delivery_location' => $job->delivery_facility_name ?? $job->delivery_location,
                'distance_miles' => $job->distance_miles,
                'payout_amount' => $job->payout_amount,
                'priority' => $job->priority,
                'requires_refrigeration' => $job->requires_refrigeration,
                'is_biological_hazard' => $job->is_biological_hazard,
                'pickup_window_start' => $job->pickup_window_start,
                'pickup_window_end' => $job->pickup_window_end,
                'status' => $job->status,
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Medical courier jobs retrieved successfully',
            'data' => $jobs,
        ]);
    }

    public function medicalCourierJob($record)
    {
        $job = UrbanGoodzMedicalCourierJob::with('assignedDriver')->find($record);

        if (!$job) {
            return response()->json([
                'success' => false,
                'message' => 'Job not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Medical courier job details retrieved successfully',
            'data' => $job,
        ]);
    }

    public function acceptMedicalCourierJob(Request $request, $record)
    {
        $service = app(UrbanGoodzMedicalCourierService::class);
        $driverId = $request->input('driver_id');

        if (!$driverId) {
            return response()->json([
                'success' => false,
                'message' => 'driver_id is required',
            ], 422);
        }

        $job = $service->assignDriver((int) $record, (int) $driverId);

        if (!$job) {
            return response()->json([
                'success' => false,
                'message' => 'Job not found, already assigned, or driver lacks medical courier training',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Medical courier job accepted successfully',
            'data' => [
                'id' => $job->id,
                'status' => $job->status,
                'assigned_driver_id' => $job->assigned_driver_id,
            ],
        ]);
    }

    public function updateMedicalCourierJobStatus(Request $request, $record)
    {
        $service = app(UrbanGoodzMedicalCourierService::class);
        $status = $request->input('status');
        $notes = $request->input('notes');

        if (!$status) {
            return response()->json([
                'success' => false,
                'message' => 'status is required',
            ], 422);
        }

        $job = $service->updateStatus((int) $record, $status, null, $notes);

        if (!$job) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid status transition or job not found',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Medical courier status updated successfully',
            'data' => [
                'id' => $job->id,
                'status' => $job->status,
            ],
        ]);
    }

    public function updateMedicalCourierCustody(Request $request, $record)
    {
        $service = app(UrbanGoodzMedicalCourierService::class);
        $action = $request->input('action', 'custody_update');
        $handlerName = $request->input('handler_name', 'Driver');
        $notes = $request->input('notes');

        $job = UrbanGoodzMedicalCourierJob::find($record);
        if (!$job) {
            return response()->json([
                'success' => false,
                'message' => 'Job not found',
            ], 404);
        }

        $log = $service->logCustody(
            (int) $record,
            $action,
            $handlerName,
            'driver',
            $request->input('driver_id'),
            $notes
        );

        return response()->json([
            'success' => true,
            'message' => 'Medical courier custody signature captured',
            'data' => [
                'id' => $log->id,
                'job_id' => $log->job_id,
                'action' => $log->action,
                'logged_at' => $log->logged_at,
            ],
        ]);
    }

    // =========================================================================
    // Book Anything
    // =========================================================================

    public function bookAnythingRecords()
    {
        return response()->json([
            'success' => true,
            'message' => 'Booking records retrieved successfully',
            'data' => [
                [
                    'id' => 40,
                    'service_name' => 'Custom Fashion Consultation',
                    'scheduled_time' => '2026-07-05 14:00:00',
                    'status' => 'confirmed',
                ]
            ],
        ]);
    }

    public function bookAnythingRecord($record)
    {
        return response()->json([
            'success' => true,
            'message' => 'Booking record details retrieved successfully',
            'data' => [
                'id' => (int)$record,
                'service_name' => 'Bespoke Alterations',
                'status' => 'confirmed',
            ],
        ]);
    }

    public function submitBookAnythingRequest(Request $request)
    {
        $payload = $request->all();
        Log::info('Book anything request submitted', ['payload' => $payload]);
        return response()->json([
            'success' => true,
            'message' => 'Booking request submitted successfully',
            'data' => [
                'id' => rand(100, 999),
                'status' => 'pending',
            ],
        ]);
    }

    // =========================================================================
    // Events
    // =========================================================================

    public function events()
    {
        return response()->json([
            'success' => true,
            'message' => 'Events list retrieved successfully',
            'data' => [
                [
                    'id' => 50,
                    'title' => 'Houston Local Creator Expo',
                    'location' => 'Expo Hall B',
                    'event_date' => '2026-08-15',
                    'status' => 'active',
                ]
            ],
        ]);
    }

    public function event($record)
    {
        return response()->json([
            'success' => true,
            'message' => 'Event details retrieved successfully',
            'data' => [
                'id' => (int)$record,
                'title' => 'Event ' . $record,
                'status' => 'active',
            ],
        ]);
    }

    public function eventInterest(Request $request, $record)
    {
        Log::info('Event interest expressed', ['record' => $record]);
        return response()->json([
            'success' => true,
            'message' => 'Interest recorded successfully',
            'data' => [
                'id' => (int)$record,
                'interested' => true,
            ],
        ]);
    }

    public function eventVendorOpportunity(Request $request, $record)
    {
        $payload = $request->all();
        Log::info('Event vendor opportunity request', ['record' => $record, 'payload' => $payload]);
        return response()->json([
            'success' => true,
            'message' => 'Vendor application submitted successfully',
            'data' => [
                'id' => (int)$record,
                'status' => 'submitted',
            ],
        ]);
    }

    public function eventCreatorOpportunity(Request $request, $record)
    {
        $payload = $request->all();
        Log::info('Event creator opportunity request', ['record' => $record, 'payload' => $payload]);
        return response()->json([
            'success' => true,
            'message' => 'Creator application submitted successfully',
            'data' => [
                'id' => (int)$record,
                'status' => 'submitted',
            ],
        ]);
    }

    public function eventLogisticsSupport(Request $request, $record)
    {
        $payload = $request->all();
        Log::info('Event logistics support request', ['record' => $record, 'payload' => $payload]);
        return response()->json([
            'success' => true,
            'message' => 'Logistics support application submitted successfully',
            'data' => [
                'id' => (int)$record,
                'status' => 'submitted',
            ],
        ]);
    }
}
