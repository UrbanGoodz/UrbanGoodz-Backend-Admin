<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryMan;
use App\Models\OrderAnywhereRequest;
use App\Models\UrbanGoodzLoadBoardLoad;
use App\Models\UrbanGoodzDedicatedRoute;
use App\Models\UrbanGoodzBusinessClientJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UrbanGoodzDriverActiveJobsController extends Controller
{
    private function authDriver(Request $request): DeliveryMan
    {
        $driver = $request->user('delivery_men');
        if (!$driver) {
            abort(401, 'Unauthenticated driver');
        }
        return $driver;
    }

    private function normalizeActiveJob($job, string $source): array
    {
        // pickupLocation/dropoffLocation are relations on some job sources
        // (e.g. business courier). Order Anywhere / dedicated route / load
        // board carry flat coordinates instead, so guard the relation access.
        $pickup = method_exists($job, 'pickupLocation') ? $job->pickupLocation : null;
        $dropoff = method_exists($job, 'dropoffLocation') ? $job->dropoffLocation : null;

        return [
            'id' => $job->id,
            'source' => $source,
            'title' => $job->job_number ?? ($job->route_name ?? ('Job #' . $job->id)),
            'status' => $job->status,
            'driver_task_status' => $job->driver_task_status ?? null,
            'pickup_address' => $pickup?->address ?? ($job->pickup_location ?? null),
            'pickup_lat' => $pickup?->latitude ?? $job->pickup_lat ?? null,
            'pickup_lng' => $pickup?->longitude ?? $job->pickup_lng ?? null,
            'dropoff_address' => $dropoff?->address ?? ($job->dropoff_address ?? $job->end_location ?? null),
            'dropoff_lat' => $dropoff?->latitude ?? $job->dropoff_lat ?? null,
            'dropoff_lng' => $dropoff?->longitude ?? $job->dropoff_lng ?? null,
            'earnings' => $job->rate_offered ?? $job->driver_pay_per_package ?? 0,
            'currency' => $job->currency ?? 'USD',
            'distance' => $job->distance_miles ?? null,
            'estimated_duration' => $job->estimated_duration ?? null,
            'scheduled_date' => $job->scheduled_date?->toDateString() ?? null,
            'scheduled_time' => $job->pickup_earliest?->format('H:i') ?? null,
            'vehicle_type' => $job->vehicle_type_needed ?? $job->vehicle_type_required ?? null,
            'is_urgent' => ($job->urgency_level ?? '') === 'immediate',
            'requires_signature' => $job->requires_signature ?? false,
            'requires_photo' => $job->requires_photo ?? false,
            'created_at' => $job->created_at?->toIso8601String(),
            'assigned_at' => $job->assigned_at?->toIso8601String(),
            'started_at' => $job->driver_started_at?->toIso8601String(),
        ];
    }

    public function index(Request $request)
    {
        $driver = $this->authDriver($request);
        $jobs = collect();

        // Order Anywhere jobs assigned to this driver
        $orderAnywhere = OrderAnywhereRequest::where('assigned_delivery_man_id', $driver->id)
            ->whereIn('status', ['approved', 'shopping', 'picked_up', 'out_for_delivery'])
            ->get()
            ->map(fn($j) => $this->normalizeActiveJob($j, 'order_anywhere'));
        $jobs = $jobs->concat($orderAnywhere);

        // Business courier jobs assigned to this driver
        $businessJobs = UrbanGoodzBusinessClientJob::where('assigned_delivery_man_id', $driver->id)
            ->whereIn('status', ['accepted', 'in_progress', 'picked_up'])
            ->with(['pickupLocation', 'dropoffLocation'])
            ->get()
            ->map(fn($j) => $this->normalizeActiveJob($j, 'business_courier'));
        $jobs = $jobs->concat($businessJobs);

        // Dedicated routes assigned to this driver (flat coords, no location relations)
        $routes = UrbanGoodzDedicatedRoute::where('assigned_driver_id', $driver->id)
            ->whereIn('status', ['active', 'in_progress'])
            ->get()
            ->map(fn($r) => $this->normalizeActiveJob($r, 'dedicated_route'));
        $jobs = $jobs->concat($routes);

        // Load board loads accepted by this driver (flat coords, no location relations)
        $loads = UrbanGoodzLoadBoardLoad::where('assigned_driver_id', $driver->id)
            ->whereIn('status', ['accepted', 'in_transit'])
            ->get()
            ->map(fn($l) => $this->normalizeActiveJob($l, 'load_board'));
        $jobs = $jobs->concat($loads);

        $jobs = $jobs->sortByDesc('created_at')->values();

        return response()->json([
            'jobs' => $jobs,
            'counts' => [
                'total' => $jobs->count(),
                'order_anywhere' => $jobs->where('source', 'order_anywhere')->count(),
                'business_courier' => $jobs->where('source', 'business_courier')->count(),
                'dedicated_route' => $jobs->where('source', 'dedicated_route')->count(),
                'load_board' => $jobs->where('source', 'load_board')->count(),
            ],
        ]);
    }

    public function detail(Request $request, int $jobId)
    {
        $driver = $this->authDriver($request);

        // Try each source type
        $job = OrderAnywhereRequest::where('id', $jobId)
            ->where('assigned_delivery_man_id', $driver->id)
            ->first();

        if ($job) {
            return response()->json(['job' => $this->normalizeActiveJob($job, 'order_anywhere')]);
        }

        $job = UrbanGoodzBusinessClientJob::where('id', $jobId)
            ->where('assigned_delivery_man_id', $driver->id)
            ->with(['pickupLocation', 'dropoffLocation'])
            ->first();

        if ($job) {
            return response()->json(['job' => $this->normalizeActiveJob($job, 'business_courier')]);
        }

        $job = UrbanGoodzDedicatedRoute::where('id', $jobId)
            ->where('assigned_driver_id', $driver->id)
            ->first();

        if ($job) {
            return response()->json(['job' => $this->normalizeActiveJob($job, 'dedicated_route')]);
        }

        $job = UrbanGoodzLoadBoardLoad::where('id', $jobId)
            ->where('assigned_driver_id', $driver->id)
            ->first();

        if ($job) {
            return response()->json(['job' => $this->normalizeActiveJob($job, 'load_board')]);
        }

        abort(404, 'Job not found or not assigned to this driver');
    }

    public function acceptJob(Request $request, int $jobId)
    {
        $driver = $this->authDriver($request);

        $job = UrbanGoodzLoadBoardLoad::where('id', $jobId)
            ->whereNull('assigned_driver_id')
            ->where('status', 'available')
            ->first();

        if (!$job) {
            abort(404, 'Load not available');
        }

        $job->update([
            'assigned_driver_id' => $driver->id,
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        return response()->json(['job' => $this->normalizeActiveJob($job->fresh(), 'load_board')]);
    }

    public function startJob(Request $request, int $jobId)
    {
        $driver = $this->authDriver($request);

        $job = $this->findDriverJob($driver, $jobId);
        if (!$job) {
            abort(404, 'Job not found');
        }

        $updateFields = ['driver_task_status' => 'in_progress', 'driver_started_at' => now()];
        if ($job instanceof UrbanGoodzLoadBoardLoad) {
            $updateFields['status'] = 'in_transit';
        } elseif ($job instanceof OrderAnywhereRequest) {
            $updateFields['status'] = 'shopping';
        } elseif ($job instanceof UrbanGoodzBusinessClientJob) {
            $updateFields['status'] = 'in_progress';
        } elseif ($job instanceof UrbanGoodzDedicatedRoute) {
            $updateFields['status'] = 'in_progress';
            $updateFields['route_started_at'] = now();
        }

        $job->update($updateFields);

        return response()->json(['job' => $this->normalizeActiveJob($job->fresh(), $this->guessSource($job))]);
    }

    public function completeJob(Request $request, int $jobId)
    {
        $driver = $this->authDriver($request);

        $job = $this->findDriverJob($driver, $jobId);
        if (!$job) {
            abort(404, 'Job not found');
        }

        $updateFields = ['driver_task_status' => 'completed', 'driver_completed_at' => now()];
        if ($job instanceof UrbanGoodzLoadBoardLoad) {
            $updateFields['status'] = 'delivered';
        } elseif ($job instanceof OrderAnywhereRequest) {
            $updateFields['status'] = 'completed';
        } elseif ($job instanceof UrbanGoodzBusinessClientJob) {
            $updateFields['status'] = 'delivered';
            $updateFields['delivered_at'] = now();
        } elseif ($job instanceof UrbanGoodzDedicatedRoute) {
            $updateFields['status'] = 'completed';
            $updateFields['route_completed_at'] = now();
        }

        $job->update($updateFields);

        // Record earning if applicable
        $earnings = $job->rate_offered ?? $job->driver_pay_per_package ?? 0;
        if ($earnings > 0 && method_exists($job, 'driverEarnings')) {
            \App\Models\UrbanGoodzDriverEarning::create([
                'delivery_man_id' => $driver->id,
                'amount' => $earnings,
                'source_type' => get_class($job),
                'source_id' => $job->id,
                'status' => 'completed',
                'earned_at' => now(),
            ]);
        }

        return response()->json(['job' => $this->normalizeActiveJob($job->fresh(), $this->guessSource($job))]);
    }

    public function cancelJob(Request $request, int $jobId)
    {
        $driver = $this->authDriver($request);

        $job = $this->findDriverJob($driver, $jobId);
        if (!$job) {
            abort(404, 'Job not found');
        }

        $updateFields = ['driver_task_status' => 'cancelled'];
        if ($job instanceof UrbanGoodzLoadBoardLoad) {
            $updateFields['status'] = 'available';
            $updateFields['assigned_driver_id'] = null;
            $updateFields['accepted_at'] = null;
        } elseif ($job instanceof OrderAnywhereRequest) {
            $updateFields['status'] = 'pending_review';
            $updateFields['assigned_delivery_man_id'] = null;
        } elseif ($job instanceof UrbanGoodzBusinessClientJob) {
            $updateFields['status'] = 'submitted';
            $updateFields['assigned_delivery_man_id'] = null;
            $updateFields['driver_accepted_at'] = null;
        } elseif ($job instanceof UrbanGoodzDedicatedRoute) {
            $updateFields['status'] = 'pending';
            $updateFields['assigned_driver_id'] = null;
        }

        $job->update($updateFields);

        return response()->json(['message' => 'Job cancelled successfully']);
    }

    public function updateStatus(Request $request, int $jobId)
    {
        $driver = $this->authDriver($request);

        // Extended 2026-08-09 to cover the full driver delivery-lifecycle MVP
        // (assigned/accepted/arrived_pickup/in_transit/failed_delivery added).
        // Legacy values (en_route, in_progress) are kept accepted so any
        // existing caller isn't broken by this change.
        $validator = Validator::make($request->all(), [
            'driver_task_status' => 'required|string|in:assigned,accepted,en_route,arrived_pickup,picked_up,in_progress,in_transit,delivered,failed_delivery',
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $job = $this->findDriverJob($driver, $jobId);
        if (!$job) {
            abort(404, 'Job not found');
        }

        $updateFields = ['driver_task_status' => $request->driver_task_status];
        if ($request->driver_task_status === 'failed_delivery'
            && $request->filled('reason')
            && $job->isFillable('driver_notes')) {
            $updateFields['driver_notes'] = $request->reason;
        }

        $job->update($updateFields);

        return response()->json(['job' => $this->normalizeActiveJob($job->fresh(), $this->guessSource($job))]);
    }

    // --- Load Board ---

    public function loadBoardAvailable(Request $request)
    {
        $driver = $this->authDriver($request);

        $loads = UrbanGoodzLoadBoardLoad::where('status', 'available')
            ->whereNull('assigned_driver_id')
            ->latest()
            ->paginate($request->input('per_page', 20));

        return response()->json(['loads' => $loads]);
    }

    public function loadBoardBid(Request $request, int $loadId)
    {
        $driver = $this->authDriver($request);

        $validator = Validator::make($request->all(), [
            'bid_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $load = UrbanGoodzLoadBoardLoad::where('id', $loadId)
            ->where('status', 'available')
            ->first();

        if (!$load) {
            abort(404, 'Load not available');
        }

        $bids = $load->bids ?? [];
        $bids[] = [
            'driver_id' => $driver->id,
            'driver_name' => $driver->first_name . ' ' . $driver->last_name,
            'bid_amount' => $request->bid_amount,
            'notes' => $request->notes ?? null,
            'bid_at' => now()->toIso8601String(),
        ];

        $load->update(['bids' => $bids]);

        return response()->json(['message' => 'Bid submitted successfully', 'load' => $load]);
    }

    // --- Opportunities ---

    public function opportunities(Request $request)
    {
        $driver = $this->authDriver($request);

        $type = $request->input('type');

        $query = \App\Models\UrbanGoodzEarnMoneyOpportunity::where('status', 'active');
        if ($type) {
            $query->where('type', $type);
        }

        $opportunities = $query->latest()->paginate($request->input('per_page', 20));

        return response()->json(['opportunities' => $opportunities]);
    }

    public function claimOpportunity(Request $request, int $opportunityId)
    {
        $driver = $this->authDriver($request);

        $opportunity = \App\Models\UrbanGoodzEarnMoneyOpportunity::where('id', $opportunityId)
            ->where('status', 'active')
            ->first();

        if (!$opportunity) {
            abort(404, 'Opportunity not available');
        }

        \App\Models\UrbanGoodzEarnMoneyApplication::create([
            'opportunity_id' => $opportunityId,
            'delivery_man_id' => $driver->id,
            'status' => 'submitted',
            'applied_at' => now(),
        ]);

        return response()->json(['message' => 'Opportunity claimed successfully']);
    }

    // --- Vehicles ---

    public function vehicles(Request $request)
    {
        $driver = $this->authDriver($request);

        $vehicles = \App\Models\DeliveryManVehicle::where('delivery_man_id', $driver->id)
            ->get()
            ->map(fn($v) => [
                'id' => $v->id,
                'make' => $v->make,
                'model' => $v->model,
                'year' => $v->year,
                'color' => $v->color,
                'plate_number' => $v->plate_number,
                'vehicle_type' => $v->vehicle_type,
                'is_insured' => $v->is_insured ?? false,
                'is_registered' => $v->is_registered ?? false,
                'insurance_expiry' => $v->insurance_expiry?->toDateString(),
                'registration_expiry' => $v->registration_expiry?->toDateString(),
                'certifications' => $v->certifications ?? [],
                'is_active' => $v->is_active ?? true,
                'created_at' => $v->created_at?->toIso8601String(),
            ]);

        return response()->json(['vehicles' => $vehicles]);
    }

    // --- Certifications ---

    public function certifications(Request $request)
    {
        $driver = $this->authDriver($request);

        $certs = \App\Models\DriverCertification::where('delivery_man_id', $driver->id)
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'issuing_authority' => $c->issuing_authority,
                'issue_date' => $c->issue_date?->toDateString(),
                'expiry_date' => $c->expiry_date?->toDateString(),
                'status' => $c->status,
                'document_url' => $c->document_url,
                'is_required' => $c->is_required ?? false,
                'renewal_status' => $c->renewal_status ?? null,
            ]);

        return response()->json(['certifications' => $certs]);
    }

    public function uploadCertDocument(Request $request, int $certId)
    {
        $driver = $this->authDriver($request);

        $validator = Validator::make($request->all(), [
            'document' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $cert = \App\Models\DriverCertification::where('id', $certId)
            ->where('delivery_man_id', $driver->id)
            ->first();

        if (!$cert) {
            abort(404, 'Certification not found');
        }

        $file = $request->file('document');
        $path = $file->store('driver_certifications/' . $driver->id, 'public');

        $cert->update([
            'document_url' => $path,
            'status' => 'pending',
        ]);

        return response()->json(['message' => 'Document uploaded successfully', 'certification' => $cert]);
    }

    public function renewCertification(Request $request, int $certId)
    {
        $driver = $this->authDriver($request);

        $cert = \App\Models\DriverCertification::where('id', $certId)
            ->where('delivery_man_id', $driver->id)
            ->first();

        if (!$cert) {
            abort(404, 'Certification not found');
        }

        $cert->update([
            'renewal_status' => 'pending',
            'status' => 'pending',
        ]);

        return response()->json(['message' => 'Renewal request submitted', 'certification' => $cert]);
    }

    // --- Helpers ---

    private function findDriverJob(DeliveryMan $driver, int $jobId)
    {
        $job = OrderAnywhereRequest::where('id', $jobId)
            ->where('assigned_delivery_man_id', $driver->id)->first();
        if ($job) return $job;

        $job = UrbanGoodzBusinessClientJob::where('id', $jobId)
            ->where('assigned_delivery_man_id', $driver->id)->first();
        if ($job) return $job;

        $job = UrbanGoodzDedicatedRoute::where('id', $jobId)
            ->where('assigned_driver_id', $driver->id)->first();
        if ($job) return $job;

        $job = UrbanGoodzLoadBoardLoad::where('id', $jobId)
            ->where('assigned_driver_id', $driver->id)->first();
        if ($job) return $job;

        return null;
    }

    private function guessSource($job): string
    {
        if ($job instanceof OrderAnywhereRequest) return 'order_anywhere';
        if ($job instanceof UrbanGoodzBusinessClientJob) return 'business_courier';
        if ($job instanceof UrbanGoodzDedicatedRoute) return 'dedicated_route';
        if ($job instanceof UrbanGoodzLoadBoardLoad) return 'load_board';
        return 'unknown';
    }
}
