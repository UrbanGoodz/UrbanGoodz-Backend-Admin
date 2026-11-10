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

    // =========================================================================
    // Driver-only: OTR load board and medical courier jobs
    //
    // These routes are behind `dm.api` (driver token). They were behind the customer
    // Passport guard, which let any customer account accept loads and jobs, move any load
    // or job through its lifecycle, write chain-of-custody entries, and read full records
    // including platform margins; the customer's user id was even used as the driver id.
    // The acting driver is always the authenticated driver, never a value from the request.
    // =========================================================================

    private function actingDriver(): \App\Models\DeliveryMan
    {
        $driver = auth('delivery_men')->user();
        abort_unless($driver, 401, 'Driver authentication required.');

        return $driver;
    }

    private function forbidden(string $code, string $message)
    {
        return response()->json(['success' => false, 'errors' => [['code' => $code, 'message' => $message]], 'message' => $message], 403);
    }

    public function loadBoardLoads(Request $request, UrbanGoodzLoadBoardService $loadBoardService)
    {
        $this->actingDriver();

        $query = \App\Models\UrbanGoodzLoadBoardLoad::where('status', 'available')->whereNull('assigned_driver_id');
        foreach (['origin_state', 'destination_state', 'load_type', 'equipment_type'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }
        if ($request->filled('min_payout')) {
            $query->where('payout_amount', '>=', (float) $request->input('min_payout'));
        }
        if ($request->filled('max_distance_miles')) {
            $query->where('distance_miles', '<=', (float) $request->input('max_distance_miles'));
        }

        $loads = $query->latest()->paginate(min(100, max(1, (int) $request->input('per_page', 25))));

        return response()->json([
            'success' => true,
            'message' => 'Load board loads retrieved successfully',
            'data' => $loads->getCollection()->map(fn ($l) => $l->driverFacing(false))->values(),
            'meta' => [
                'current_page' => $loads->currentPage(),
                'last_page' => $loads->lastPage(),
                'total' => $loads->total(),
                'per_page' => $loads->perPage(),
            ],
        ]);
    }

    public function loadBoardLoad($record, UrbanGoodzLoadBoardService $loadBoardService)
    {
        $driver = $this->actingDriver();
        $load = $loadBoardService->getById((int) $record);

        $mine = $load && (int) $load->assigned_driver_id === (int) $driver->id;
        $open = $load && $load->status === 'available' && $load->assigned_driver_id === null;
        if (!$mine && !$open) {
            return response()->json(['success' => false, 'message' => 'Load not found'], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Load details retrieved successfully',
            'data' => $load->driverFacing($mine),
        ]);
    }

    public function acceptLoadBoardLoad(Request $request, $record, UrbanGoodzLoadBoardService $loadBoardService)
    {
        $driver = $this->actingDriver();

        $load = \App\Models\UrbanGoodzLoadBoardLoad::where('status', 'available')->whereNull('assigned_driver_id')->find((int) $record);
        if (!$load) {
            return response()->json(['success' => false, 'message' => 'Load not available'], 404);
        }
        if ($reason = $loadBoardService->ineligibilityReason($load, $driver, true)) {
            return $this->forbidden($reason, 'You are not eligible to take this load.');
        }

        $claimed = $loadBoardService->acceptLoad((int) $record, $driver->id, null, true);
        if (!$claimed) {
            return response()->json(['success' => false, 'message' => 'This load was just taken by another driver.'], 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'Load accepted successfully',
            'data' => $claimed->driverFacing(true),
        ]);
    }

    public function updateLoadBoardLoadStatus(Request $request, $record, UrbanGoodzLoadBoardService $loadBoardService)
    {
        $driver = $this->actingDriver();
        // A driver moves their own load forward only. Cancelling or reopening a load is a
        // dispatcher decision (or a release before pickup via the active-jobs endpoint).
        $validated = $request->validate([
            'status' => 'required|string|in:in_transit,picked_up,delivered',
            'notes' => 'nullable|string|max:1000',
        ]);

        $load = \App\Models\UrbanGoodzLoadBoardLoad::where('assigned_driver_id', $driver->id)->find((int) $record);
        if (!$load) {
            return response()->json(['success' => false, 'message' => 'Load not found'], 404);
        }

        $updated = $loadBoardService->updateStatus((int) $record, $validated['status'], $driver->id, 'driver', $validated['notes'] ?? null);
        if (!$updated) {
            return response()->json(['success' => false, 'message' => 'Invalid status transition'], 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'Load status updated successfully',
            'data' => $updated->driverFacing(true),
        ]);
    }

    // =========================================================================
    // Medical Courier
    // =========================================================================

    /** Only approved, active drivers with medical-courier training may work these jobs. */
    private function medicalDriver()
    {
        $driver = $this->actingDriver();
        $trained = $driver->active && $driver->application_status === 'approved' && $driver->has_medical_courier_training;

        return $trained ? $driver : null;
    }

    private function medicalListing(UrbanGoodzMedicalCourierJob $job): array
    {
        return [
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
        ];
    }

    public function medicalCourierJobs()
    {
        if (!$this->medicalDriver()) {
            return $this->forbidden('medical_training_required', 'Medical courier jobs require an approved driver with medical courier training.');
        }

        $jobs = UrbanGoodzMedicalCourierJob::where('status', 'pending')
            ->orderBy('priority', 'desc')
            ->limit(50)
            ->get()
            ->map(fn ($job) => $this->medicalListing($job));

        return response()->json([
            'success' => true,
            'message' => 'Medical courier jobs retrieved successfully',
            'data' => $jobs,
        ]);
    }

    public function medicalCourierJob($record)
    {
        $driver = $this->medicalDriver();
        if (!$driver) {
            return $this->forbidden('medical_training_required', 'Medical courier jobs require an approved driver with medical courier training.');
        }

        $job = UrbanGoodzMedicalCourierJob::find($record);
        $mine = $job && (int) $job->assigned_driver_id === (int) $driver->id;
        $open = $job && $job->status === 'pending' && $job->assigned_driver_id === null;
        if (!$mine && !$open) {
            return response()->json(['success' => false, 'message' => 'Job not found'], 404);
        }

        // Facility contacts and the full record only once the job is yours; admin notes never.
        $data = $mine ? $job->makeHidden(['admin_notes'])->toArray() : $this->medicalListing($job);

        return response()->json([
            'success' => true,
            'message' => 'Medical courier job details retrieved successfully',
            'data' => $data,
        ]);
    }

    public function acceptMedicalCourierJob(Request $request, $record)
    {
        $driver = $this->medicalDriver();
        if (!$driver) {
            return $this->forbidden('medical_training_required', 'Medical courier jobs require an approved driver with medical courier training.');
        }

        // The driver is the authenticated driver; a driver_id in the body is ignored.
        $job = app(UrbanGoodzMedicalCourierService::class)->assignDriver((int) $record, (int) $driver->id);
        if (!$job) {
            return response()->json([
                'success' => false,
                'message' => 'Job not found or already assigned',
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'Medical courier job accepted successfully',
            'data' => ['id' => $job->id, 'status' => $job->status, 'assigned_driver_id' => $job->assigned_driver_id],
        ]);
    }

    public function updateMedicalCourierJobStatus(Request $request, $record)
    {
        $driver = $this->medicalDriver();
        if (!$driver) {
            return $this->forbidden('medical_training_required', 'Medical courier jobs require an approved driver with medical courier training.');
        }

        $validated = $request->validate([
            // A driver advances their own job. Cancelling a specimen run is an admin action.
            'status' => 'required|string|in:picked_up,in_transit,delivered',
            'notes' => 'nullable|string|max:1000',
            // Proof of delivery: a specimen is not "delivered" (and the driver is not paid) until
            // someone is named as having received it. It goes into the custody trail below.
            'recipient_name' => 'required_if:status,delivered|nullable|string|min:2|max:120',
        ], [
            'recipient_name.required_if' => 'Enter the name of the person who received the delivery.',
        ]);

        $job = UrbanGoodzMedicalCourierJob::where('assigned_driver_id', $driver->id)->find((int) $record);
        if (!$job) {
            return response()->json(['success' => false, 'message' => 'Job not found'], 404);
        }

        $notes = $validated['notes'] ?? null;
        if ($validated['status'] === 'delivered') {
            $notes = trim('Received by: ' . $validated['recipient_name'] . '. ' . (string) $notes);
        }

        $updated = app(UrbanGoodzMedicalCourierService::class)->updateStatus((int) $record, $validated['status'], (int) $driver->id, $notes);
        if (!$updated) {
            return response()->json(['success' => false, 'message' => 'Invalid status transition'], 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'Medical courier status updated successfully',
            'data' => ['id' => $updated->id, 'status' => $updated->status],
        ]);
    }

    public function updateMedicalCourierCustody(Request $request, $record)
    {
        $driver = $this->medicalDriver();
        if (!$driver) {
            return $this->forbidden('medical_training_required', 'Medical courier jobs require an approved driver with medical courier training.');
        }

        $validated = $request->validate([
            'action' => ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/'],
            'notes' => 'nullable|string|max:1000',
        ]);

        // Chain of custody: only the assigned driver, only while the specimen is with them, and
        // always recorded under the authenticated driver's identity (never a name from the body).
        $job = UrbanGoodzMedicalCourierJob::where('assigned_driver_id', $driver->id)
            ->whereIn('status', ['assigned', 'picked_up', 'in_transit'])
            ->find((int) $record);
        if (!$job) {
            return response()->json(['success' => false, 'message' => 'Job not found'], 404);
        }

        $log = app(UrbanGoodzMedicalCourierService::class)->logCustody(
            (int) $record,
            $validated['action'] ?? 'custody_update',
            trim($driver->f_name.' '.$driver->l_name) ?: "Driver #{$driver->id}",
            'driver',
            (int) $driver->id,
            $validated['notes'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Medical courier custody signature captured',
            'data' => ['id' => $log->id, 'job_id' => $log->job_id, 'action' => $log->action, 'logged_at' => $log->logged_at],
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
