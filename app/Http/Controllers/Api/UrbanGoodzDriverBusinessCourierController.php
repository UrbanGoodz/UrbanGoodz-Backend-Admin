<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzBusinessClientJob;
use App\Models\UrbanGoodzDriverEarning;
use App\Models\DeliveryMan;
use App\Services\UrbanGoodzDriverDispatchNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UrbanGoodzDriverBusinessCourierController extends Controller
{
    private function authDriver(Request $request): DeliveryMan
    {
        $driver = $request->user('delivery_man');
        if (!$driver) {
            abort(401, 'Unauthenticated driver');
        }
        return $driver;
    }

    private function jobDetailResponse($job): array
    {
        $pickupLoc = $job->pickupLocation;
        $dropoffLoc = $job->dropoffLocation;

        $response = [
            'job_id' => $job->id,
            'job_number' => $job->job_number,
            'business_client_id' => $job->business_client_id,
            'business_client_name' => $job->client?->company_name,
            'job_type' => $job->job_type,
            'status' => $job->status,
            'description' => $job->description,
            'reference_number' => $job->reference_number,
            'po_number' => $job->po_number,

            'pickup' => [
                'location_id' => $pickupLoc?->id,
                'name' => $pickupLoc?->name,
                'address' => $pickupLoc?->address,
                'city' => $pickupLoc?->city,
                'state' => $pickupLoc?->state,
                'postal_code' => $pickupLoc?->postal_code,
                'latitude' => $pickupLoc?->latitude,
                'longitude' => $pickupLoc?->longitude,
                'contact_name' => $pickupLoc?->contact_name ?? $job->pickup_contact_name,
                'contact_phone' => $pickupLoc?->contact_phone ?? $job->pickup_contact_phone,
                'pickup_instructions' => $pickupLoc?->pickup_instructions,
                'pickup_earliest' => $job->pickup_earliest?->toIso8601String(),
                'pickup_latest' => $job->pickup_latest?->toIso8601String(),
            ],

            'dropoff' => [
                'location_id' => $dropoffLoc?->id,
                'name' => $dropoffLoc?->name,
                'address' => $dropoffLoc?->address,
                'city' => $dropoffLoc?->city,
                'state' => $dropoffLoc?->state,
                'postal_code' => $dropoffLoc?->postal_code,
                'latitude' => $dropoffLoc?->latitude,
                'longitude' => $dropoffLoc?->longitude,
                'contact_name' => $dropoffLoc?->contact_name ?? $job->dropoff_contact_name,
                'contact_phone' => $dropoffLoc?->contact_phone ?? $job->dropoff_contact_phone,
                'delivery_instructions' => $dropoffLoc?->delivery_instructions,
                'delivery_deadline' => $job->delivery_deadline?->toIso8601String(),
            ],

            'requirements' => [
                'vehicle_type_needed' => $job->vehicle_type_needed,
                'needs_liftgate' => $job->needs_liftgate,
                'needs_dock' => $job->needs_dock,
                'special_handling' => $job->special_handling,
                'load_type' => $job->load_type,
                'weight' => $job->weight,
                'weight_unit' => $job->weight_unit,
                'temperature_requirement' => $job->temperature_requirement,
                'specimen_type' => $job->specimen_type,
                'urgency_level' => $job->urgency_level,
                'courier_certification_required' => $job->courier_certification_required,
                'chain_of_custody_required' => $job->chain_of_custody_required,
            ],

            'pricing' => [
                'rate_offered' => $job->rate_offered,
                'currency' => $job->currency,
            ],

            'driver' => [
                'assigned_delivery_man_id' => $job->assigned_delivery_man_id,
                'assigned_at' => $job->assigned_at?->toIso8601String(),
                'driver_accepted_at' => $job->driver_accepted_at?->toIso8601String(),
            ],

            'proof' => [
                'proof_of_pickup' => $job->proof_of_pickup
                    ? (filter_var($job->proof_of_pickup, FILTER_VALIDATE_URL) ? $job->proof_of_pickup : url('storage/' . $job->proof_of_pickup))
                    : null,
                'proof_of_delivery' => $job->proof_of_delivery
                    ? (filter_var($job->proof_of_delivery, FILTER_VALIDATE_URL) ? $job->proof_of_delivery : url('storage/' . $job->proof_of_delivery))
                    : null,
                'pickup_proof_submitted' => !is_null($job->proof_of_pickup),
                'delivery_proof_submitted' => !is_null($job->proof_of_delivery),
            ],

            'timeline' => [
                'assigned_at' => $job->assigned_at?->toIso8601String(),
                'driver_accepted_at' => $job->driver_accepted_at?->toIso8601String(),
                'picked_up_at' => $job->picked_up_at?->toIso8601String(),
                'delivered_at' => $job->delivered_at?->toIso8601String(),
                'created_at' => $job->created_at?->toIso8601String(),
                'updated_at' => $job->updated_at?->toIso8601String(),
            ],

            'driver_notes' => $job->driver_notes,

            'exception' => [
                'has_exception' => !is_null($job->exception_reason),
                'reason' => $job->exception_reason,
                'reported_at' => $job->exception_reported_at?->toIso8601String(),
            ],
        ];

        return $response;
    }

    public function assignedJobs(Request $request)
    {
        $driver = $this->authDriver($request);

        $jobs = UrbanGoodzBusinessClientJob::with(['client', 'pickupLocation', 'dropoffLocation'])
            ->assignedToDriver($driver->id)
            ->driverAccessible()
            ->latest()
            ->get()
            ->map(function ($job) {
                return $this->jobDetailResponse($job);
            });

        $counts = [
            'total' => $jobs->count(),
            'assigned' => $jobs->where('status', 'assigned')->count(),
            'active' => $jobs->whereIn('status', ['driver_en_route', 'picked_up', 'in_transit', 'delayed'])->count(),
            'completed' => $jobs->whereIn('status', ['delivered', 'completed'])->count(),
        ];

        return response()->json([
            'jobs' => $jobs,
            'counts' => $counts,
        ]);
    }

    public function jobDetail(Request $request, $jobId)
    {
        $driver = $this->authDriver($request);

        $job = UrbanGoodzBusinessClientJob::with(['client', 'pickupLocation', 'dropoffLocation', 'assignedDriver'])
            ->assignedToDriver($driver->id)
            ->whereKey($jobId)
            ->driverAccessible()
            ->findOrFail($jobId);

        return response()->json([
            'job' => $this->jobDetailResponse($job),
        ]);
    }

    public function acceptJob(Request $request, $jobId)
    {
        $driver = $this->authDriver($request);

        $job = UrbanGoodzBusinessClientJob::with(['client', 'pickupLocation', 'dropoffLocation'])
            ->assignedToDriver($driver->id)
            ->whereKey($jobId)
            ->where('status', 'assigned')
            ->firstOrFail();

        $job->driver_accepted_at = now();
        $job->save();

        return response()->json([
            'message' => 'Job accepted successfully',
            'job' => $this->jobDetailResponse($job),
        ]);
    }

    public function startJob(Request $request, $jobId)
    {
        $driver = $this->authDriver($request);

        $job = UrbanGoodzBusinessClientJob::with(['client', 'pickupLocation', 'dropoffLocation'])
            ->assignedToDriver($driver->id)
            ->whereKey($jobId)
            ->whereIn('status', ['assigned', 'driver_en_route'])
            ->firstOrFail();

        $job->status = 'driver_en_route';
        if (!$job->driver_accepted_at) {
            $job->driver_accepted_at = now();
        }
        $job->save();

        return response()->json([
            'message' => 'Job started',
            'job' => $this->jobDetailResponse($job),
        ]);
    }

    public function markPickup(Request $request, $jobId)
    {
        $driver = $this->authDriver($request);

        $job = UrbanGoodzBusinessClientJob::with(['client', 'pickupLocation', 'dropoffLocation'])
            ->assignedToDriver($driver->id)
            ->whereKey($jobId)
            ->whereIn('status', ['driver_en_route', 'picked_up'])
            ->firstOrFail();

        $job->status = 'picked_up';
        $job->picked_up_at = now();
        $job->save();

        return response()->json([
            'message' => 'Pickup completed',
            'job' => $this->jobDetailResponse($job),
        ]);
    }

    public function markDelivery(Request $request, $jobId)
    {
        $driver = $this->authDriver($request);

        $job = UrbanGoodzBusinessClientJob::with(['client', 'pickupLocation', 'dropoffLocation'])
            ->assignedToDriver($driver->id)
            ->whereKey($jobId)
            ->whereIn('status', ['picked_up', 'in_transit', 'delayed', 'delivered'])
            ->firstOrFail();

        $job->status = 'delivered';
        $job->delivered_at = now();
        $job->save();

        if (is_null($job->rate_offered) || $job->rate_offered <= 0) {
            return response()->json([
                'message' => 'Delivery completed — no earning recorded (rate not set)',
                'job' => $this->jobDetailResponse($job),
                'earning' => null,
                'earning_note' => 'Admin must set a rate_offered for this job before earnings are created',
            ]);
        }

        $existingEarning = UrbanGoodzDriverEarning::where('delivery_man_id', $driver->id)
            ->where('business_client_job_id', $job->id)
            ->where('earning_type', 'business_courier_delivery')
            ->first();

        if ($existingEarning) {
            return response()->json([
                'message' => 'Delivery completed',
                'job' => $this->jobDetailResponse($job),
                'earning' => $existingEarning,
                'earning_note' => 'Earning already exists for this delivery (idempotent)',
            ]);
        }

        DB::beginTransaction();
        try {
            $earning = UrbanGoodzDriverEarning::create([
                'delivery_man_id' => $driver->id,
                'business_client_job_id' => $job->id,
                'earning_type' => 'business_courier_delivery',
                'amount' => $job->rate_offered,
                'currency' => $job->currency ?? 'USD',
                'status' => 'pending',
                'description' => 'Business courier delivery — Job #' . $job->job_number,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Delivery completed',
                'job' => $this->jobDetailResponse($job),
                'earning' => $earning,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to record earning: ' . $e->getMessage()], 500);
        }
    }

    public function submitPickupProof(Request $request, $jobId)
    {
        $driver = $this->authDriver($request);

        $job = UrbanGoodzBusinessClientJob::with(['client', 'pickupLocation', 'dropoffLocation'])
            ->assignedToDriver($driver->id)
            ->whereKey($jobId)
            ->driverAccessible()
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'proof' => ['required_without:proof_url', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:10240'],
            'proof_url' => ['required_without:proof', 'url', 'starts_with:https://'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->hasFile('proof')) {
            $path = $request->file('proof')->store('urban-goodz/business-jobs/proofs/pickup', 'public');
            $job->proof_of_pickup = $path;
        } elseif ($request->proof_url) {
            $job->proof_of_pickup = $request->proof_url;
        }

        if ($request->notes) {
            $job->driver_notes = $request->notes;
        }

        $job->save();

        return response()->json([
            'message' => 'Pickup proof submitted',
            'proof_of_pickup' => $job->proof_of_pickup
                ? (filter_var($job->proof_of_pickup, FILTER_VALIDATE_URL) ? $job->proof_of_pickup : url('storage/' . $job->proof_of_pickup))
                : null,
        ]);
    }

    public function submitDeliveryProof(Request $request, $jobId)
    {
        $driver = $this->authDriver($request);

        $job = UrbanGoodzBusinessClientJob::with(['client', 'pickupLocation', 'dropoffLocation'])
            ->assignedToDriver($driver->id)
            ->whereKey($jobId)
            ->driverAccessible()
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'proof' => ['required_without:proof_url', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:10240'],
            'proof_url' => ['required_without:proof', 'url', 'starts_with:https://'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->hasFile('proof')) {
            $path = $request->file('proof')->store('urban-goodz/business-jobs/proofs/delivery', 'public');
            $job->proof_of_delivery = $path;
        } elseif ($request->proof_url) {
            $job->proof_of_delivery = $request->proof_url;
        }

        if ($request->notes) {
            $job->driver_notes = $request->notes;
        }

        $job->save();

        return response()->json([
            'message' => 'Delivery proof submitted',
            'proof_of_delivery' => $job->proof_of_delivery
                ? (filter_var($job->proof_of_delivery, FILTER_VALIDATE_URL) ? $job->proof_of_delivery : url('storage/' . $job->proof_of_delivery))
                : null,
        ]);
    }

    public function reportException(Request $request, $jobId)
    {
        $driver = $this->authDriver($request);

        $job = UrbanGoodzBusinessClientJob::with(['client', 'pickupLocation', 'dropoffLocation'])
            ->assignedToDriver($driver->id)
            ->whereKey($jobId)
            ->whereIn('status', ['assigned', 'driver_en_route', 'picked_up', 'in_transit', 'delayed'])
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'reason' => ['required', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $job->exception_reason = $request->reason;
        $job->exception_reported_at = now();
        $job->status = 'delayed';

        if ($request->notes) {
            $job->driver_notes = $request->notes;
        }

        $job->save();

        app(UrbanGoodzDriverDispatchNotificationService::class)
            ->notifyPackageException($job, $request->reason ?? null);

        return response()->json([
            'message' => 'Exception reported',
            'job' => $this->jobDetailResponse($job),
        ]);
    }
}
