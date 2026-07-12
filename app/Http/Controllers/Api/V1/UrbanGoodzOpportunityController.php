<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzBookAnythingRequest;
use App\Models\UrbanGoodzEarnMoneyApplication;
use App\Models\UrbanGoodzEarnMoneyOpportunity;
use App\Models\UrbanGoodzEvent;
use App\Models\UrbanGoodzLoadBoardLoad;
use App\Models\UrbanGoodzLogisticsJob;
use App\Models\UrbanGoodzMedicalCourierJob;
use App\Models\UrbanGoodzMedicalCourierCustodyLog;
use App\Services\UrbanGoodz\UrbanGoodzLoadBoardService;
use App\Services\UrbanGoodz\UrbanGoodzMedicalCourierService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UrbanGoodzOpportunityController extends Controller
{
    // =========================================================================
    // Earn Money
    // =========================================================================

    public function earnMoneyOpportunities()
    {
        $opportunities = UrbanGoodzEarnMoneyOpportunity::where('status', 'available')
            ->latest()
            ->get()
            ->map(fn($opp) => [
                'id' => $opp->id,
                'title' => $opp->title,
                'description' => $opp->description,
                'type' => $opp->type,
                'earnings' => (float) $opp->reward_amount,
                'reward_type' => $opp->reward_type,
                'status' => $opp->status,
                'terms' => $opp->terms,
                'starts_at' => $opp->starts_at,
                'ends_at' => $opp->ends_at,
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Earning opportunities retrieved successfully',
            'data' => $opportunities,
        ]);
    }

    public function earnMoneyOpportunity($record)
    {
        $opportunity = UrbanGoodzEarnMoneyOpportunity::find($record);

        if (!$opportunity) {
            return response()->json([
                'success' => false,
                'message' => 'Opportunity not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Opportunity details retrieved successfully',
            'data' => [
                'id' => $opportunity->id,
                'title' => $opportunity->title,
                'description' => $opportunity->description,
                'type' => $opportunity->type,
                'earnings' => (float) $opportunity->reward_amount,
                'reward_type' => $opportunity->reward_type,
                'status' => $opportunity->status,
                'terms' => $opportunity->terms,
                'starts_at' => $opportunity->starts_at,
                'ends_at' => $opportunity->ends_at,
            ],
        ]);
    }

    public function acceptEarnMoneyOpportunity(Request $request, $record)
    {
        $opportunity = UrbanGoodzEarnMoneyOpportunity::find($record);

        if (!$opportunity) {
            return response()->json([
                'success' => false,
                'message' => 'Opportunity not found',
            ], 404);
        }

        $application = UrbanGoodzEarnMoneyApplication::create([
            'opportunity_id' => $opportunity->id,
            'applicant_name' => $request->input('applicant_name', $request->user()?->name),
            'applicant_email' => $request->input('applicant_email', $request->user()?->email),
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Opportunity accepted successfully',
            'data' => [
                'id' => $application->id,
                'opportunity_id' => $opportunity->id,
                'status' => $application->status,
            ],
        ]);
    }

    // =========================================================================
    // Logistics Jobs
    // =========================================================================

    public function logisticsJobs()
    {
        $jobs = UrbanGoodzLogisticsJob::where('status', 'available')
            ->latest()
            ->get()
            ->map(fn($job) => [
                'id' => $job->id,
                'job_number' => $job->job_number,
                'pickup_location' => $job->pickup_location,
                'delivery_location' => $job->delivery_location,
                'pickup_by' => $job->pickup_by,
                'deliver_by' => $job->deliver_by,
                'description' => $job->description,
                'weight_kg' => (float) $job->weight_kg,
                'payout' => (float) $job->offer_amount,
                'status' => $job->status,
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Logistics jobs retrieved successfully',
            'data' => $jobs,
        ]);
    }

    public function logisticsJob($record)
    {
        $job = UrbanGoodzLogisticsJob::find($record);

        if (!$job) {
            return response()->json([
                'success' => false,
                'message' => 'Logistics job not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Logistics job details retrieved successfully',
            'data' => [
                'id' => $job->id,
                'job_number' => $job->job_number,
                'pickup_location' => $job->pickup_location,
                'delivery_location' => $job->delivery_location,
                'pickup_by' => $job->pickup_by,
                'deliver_by' => $job->deliver_by,
                'description' => $job->description,
                'weight_kg' => (float) $job->weight_kg,
                'payout' => (float) $job->offer_amount,
                'status' => $job->status,
                'assigned_driver_id' => $job->assigned_driver_id,
            ],
        ]);
    }

    public function acceptLogisticsJob(Request $request, $record)
    {
        $job = UrbanGoodzLogisticsJob::find($record);

        if (!$job) {
            return response()->json([
                'success' => false,
                'message' => 'Logistics job not found',
            ], 404);
        }

        if ($job->status !== 'available') {
            return response()->json([
                'success' => false,
                'message' => 'Logistics job is no longer available',
            ], 422);
        }

        $driverId = $request->user()?->id;
        if (!$driverId) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        $job->update([
            'assigned_driver_id' => $driverId,
            'status' => 'assigned',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Logistics job accepted successfully',
            'data' => [
                'id' => $job->id,
                'job_number' => $job->job_number,
                'status' => $job->status,
                'assigned_driver_id' => $job->assigned_driver_id,
            ],
        ]);
    }

    public function updateLogisticsJobStatus(Request $request, $record)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:available,assigned,picked_up,in_transit,delivered,cancelled',
        ]);

        $job = UrbanGoodzLogisticsJob::find($record);

        if (!$job) {
            return response()->json([
                'success' => false,
                'message' => 'Logistics job not found',
            ], 404);
        }

        $job->update(['status' => $validated['status']]);

        return response()->json([
            'success' => true,
            'message' => 'Logistics job status updated successfully',
            'data' => [
                'id' => $job->id,
                'job_number' => $job->job_number,
                'status' => $job->status,
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
        $records = UrbanGoodzBookAnythingRequest::latest()
            ->get()
            ->map(fn($rec) => [
                'id' => $rec->id,
                'request_number' => $rec->request_number,
                'service_name' => $rec->service_name,
                'description' => $rec->description,
                'preferred_date' => $rec->preferred_date,
                'preferred_time' => $rec->preferred_time,
                'location' => $rec->location,
                'budget_amount' => $rec->budget_amount ? (float) $rec->budget_amount : null,
                'status' => $rec->status,
                'assigned_provider_id' => $rec->assigned_provider_id,
                'completed_at' => $rec->completed_at,
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Booking records retrieved successfully',
            'data' => $records,
        ]);
    }

    public function bookAnythingRecord($record)
    {
        $record = UrbanGoodzBookAnythingRequest::find($record);

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => 'Booking record not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Booking record details retrieved successfully',
            'data' => [
                'id' => $record->id,
                'request_number' => $record->request_number,
                'service_name' => $record->service_name,
                'description' => $record->description,
                'preferred_date' => $record->preferred_date,
                'preferred_time' => $record->preferred_time,
                'location' => $record->location,
                'budget_amount' => $record->budget_amount ? (float) $record->budget_amount : null,
                'status' => $record->status,
                'assigned_provider_id' => $record->assigned_provider_id,
                'admin_notes' => $record->admin_notes,
                'completed_at' => $record->completed_at,
            ],
        ]);
    }

    public function submitBookAnythingRequest(Request $request)
    {
        $validated = $request->validate([
            'service_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'preferred_date' => 'nullable|date',
            'preferred_time' => 'nullable|string|max:50',
            'location' => 'nullable|string|max:255',
            'budget_amount' => 'nullable|numeric|min:0',
        ]);

        $requestNumber = 'BA-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4));

        $booking = UrbanGoodzBookAnythingRequest::create([
            'request_number' => $requestNumber,
            'customer_id' => $request->user()?->id,
            'service_name' => $validated['service_name'],
            'description' => $validated['description'] ?? null,
            'preferred_date' => $validated['preferred_date'] ?? null,
            'preferred_time' => $validated['preferred_time'] ?? null,
            'location' => $validated['location'] ?? null,
            'budget_amount' => $validated['budget_amount'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Booking request submitted successfully',
            'data' => [
                'id' => $booking->id,
                'request_number' => $booking->request_number,
                'status' => $booking->status,
            ],
        ]);
    }

    // =========================================================================
    // Events
    // =========================================================================

    public function events()
    {
        $events = UrbanGoodzEvent::where('status', 'active')
            ->orderBy('starts_at')
            ->get()
            ->map(fn($event) => [
                'id' => $event->id,
                'title' => $event->title,
                'description' => $event->description,
                'location' => $event->location,
                'starts_at' => $event->starts_at,
                'ends_at' => $event->ends_at,
                'organizer_name' => $event->organizer_name,
                'ticket_price' => $event->ticket_price ? (float) $event->ticket_price : null,
                'capacity' => $event->capacity,
                'status' => $event->status,
                'image_url' => $event->image_url,
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Events list retrieved successfully',
            'data' => $events,
        ]);
    }

    public function event($record)
    {
        $event = UrbanGoodzEvent::find($record);

        if (!$event) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Event details retrieved successfully',
            'data' => [
                'id' => $event->id,
                'title' => $event->title,
                'description' => $event->description,
                'location' => $event->location,
                'starts_at' => $event->starts_at,
                'ends_at' => $event->ends_at,
                'organizer_name' => $event->organizer_name,
                'organizer_contact' => $event->organizer_contact,
                'ticket_price' => $event->ticket_price ? (float) $event->ticket_price : null,
                'capacity' => $event->capacity,
                'status' => $event->status,
                'image_url' => $event->image_url,
            ],
        ]);
    }

    public function eventInterest(Request $request, $record)
    {
        $event = UrbanGoodzEvent::find($record);

        if (!$event) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found',
            ], 404);
        }

        Log::info('Event interest expressed', [
            'event_id' => $event->id,
            'user_id' => $request->user()?->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Interest recorded successfully',
            'data' => [
                'id' => $event->id,
                'title' => $event->title,
                'interested' => true,
            ],
        ]);
    }

    public function eventVendorOpportunity(Request $request, $record)
    {
        $event = UrbanGoodzEvent::find($record);

        if (!$event) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found',
            ], 404);
        }

        $validated = $request->validate([
            'business_name' => 'required|string|max:255',
            'contact_email' => 'required|email|max:255',
            'booth_type' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        Log::info('Event vendor opportunity request', [
            'event_id' => $event->id,
            'user_id' => $request->user()?->id,
            'payload' => $validated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Vendor application submitted successfully',
            'data' => [
                'id' => $event->id,
                'status' => 'submitted',
            ],
        ]);
    }

    public function eventCreatorOpportunity(Request $request, $record)
    {
        $event = UrbanGoodzEvent::find($record);

        if (!$event) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found',
            ], 404);
        }

        $validated = $request->validate([
            'creator_name' => 'required|string|max:255',
            'contact_email' => 'required|email|max:255',
            'content_type' => 'nullable|string|max:100',
            'portfolio_url' => 'nullable|url|max:255',
            'notes' => 'nullable|string',
        ]);

        Log::info('Event creator opportunity request', [
            'event_id' => $event->id,
            'user_id' => $request->user()?->id,
            'payload' => $validated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Creator application submitted successfully',
            'data' => [
                'id' => $event->id,
                'status' => 'submitted',
            ],
        ]);
    }

    public function eventLogisticsSupport(Request $request, $record)
    {
        $event = UrbanGoodzEvent::find($record);

        if (!$event) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found',
            ], 404);
        }

        $validated = $request->validate([
            'provider_name' => 'required|string|max:255',
            'contact_email' => 'required|email|max:255',
            'service_type' => 'nullable|string|max:100',
            'capacity_details' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        Log::info('Event logistics support request', [
            'event_id' => $event->id,
            'user_id' => $request->user()?->id,
            'payload' => $validated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Logistics support application submitted successfully',
            'data' => [
                'id' => $event->id,
                'status' => 'submitted',
            ],
        ]);
    }
}
