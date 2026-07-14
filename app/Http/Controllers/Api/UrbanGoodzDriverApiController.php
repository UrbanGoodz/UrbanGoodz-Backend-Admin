<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzDedicatedRoute;
use App\Models\UrbanGoodzRouteAssignment;
use App\Models\UrbanGoodzRouteBatch;
use App\Models\UrbanGoodzRoutePackage;
use App\Models\UrbanGoodzPackageScan;
use App\Models\UrbanGoodzDriverEarning;
use App\Models\UrbanGoodzDriverPayoutRequest;
use App\Models\UrbanGoodzMedicalCustodyLog;
use App\Models\UrbanGoodzAgeVerification;
use App\Models\UrbanGoodzPaymentSplit;
use App\Models\DeliveryMan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UrbanGoodzDriverApiController extends Controller
{
    private function authDriver(Request $request)
    {
        // dm.api middleware logs the driver in via the 'delivery_men' guard.
        // The singular 'delivery_man' alias also resolves the same provider.
        $driver = $request->user('delivery_men') ?? auth('delivery_men')->user();
        if (!$driver) {
            abort(401, 'Unauthenticated driver');
        }
        return $driver;
    }

    public function assignedRoutes(Request $request)
    {
        $driver = $this->authDriver($request);

        $routes = UrbanGoodzDedicatedRoute::with([
            'client:company_name,id',
            'batches',
        ])->where('assigned_driver_id', $driver->id)
          ->whereIn('status', ['active', 'in_progress'])
          ->latest()
          ->get()
          ->map(function ($route) {
              return [
                  'id' => $route->id,
                  'route_name' => $route->route_name,
                  'route_type' => $route->route_type,
                  'pickup_location' => $route->pickup_location,
                  'pickup_lat' => $route->pickup_lat,
                  'pickup_lng' => $route->pickup_lng,
                  'client' => $route->client?->company_name,
                  'total_packages' => $route->total_packages,
                  'completed_packages' => $route->completed_packages,
                  'failed_packages' => $route->failed_packages,
                  'status' => $route->status,
                  'driver_pay_per_package' => $route->driver_pay_per_package,
                  'batch_count' => $route->batches->count(),
                  'scheduled_date' => $route->scheduled_date?->toDateString(),
                  'route_started_at' => $route->route_started_at?->toDateTimeString(),
              ];
          });

        return response()->json(['routes' => $routes]);
    }

    public function routeDetail(Request $request, $routeId)
    {
        $driver = $this->authDriver($request);

        $route = UrbanGoodzDedicatedRoute::with([
            'batches.packages.optimizationStop',
            'optimizationStops.package',
        ])->where('id', $routeId)
          ->where('assigned_driver_id', $driver->id)
          ->firstOrFail();

        $stops = $route->optimizationStops()
            ->with('package')
            ->orderBy('stop_order')
            ->get()
            ->map(function ($stop) {
                $pkg = $stop->package;
                return [
                    'stop_order' => $stop->stop_order,
                    'package_id' => $pkg?->id,
                    'tracking_id' => $pkg?->tracking_id,
                    'barcode' => $pkg?->barcode,
                    'dropoff_name' => $pkg?->dropoff_name,
                    'dropoff_address' => $pkg?->dropoff_address,
                    'dropoff_lat' => $pkg?->dropoff_lat,
                    'dropoff_lng' => $pkg?->dropoff_lng,
                    'delivery_window_start' => $pkg?->delivery_window_start?->toDateTimeString(),
                    'delivery_window_end' => $pkg?->delivery_window_end?->toDateTimeString(),
                    'priority' => $pkg?->priority,
                    'requires_signature' => $pkg?->requires_signature,
                    'requires_photo' => $pkg?->requires_photo,
                    'requires_custody' => $pkg?->requires_custody,
                    'package_type' => $pkg?->package_type,
                    'weight' => $pkg?->weight,
                    'status' => $pkg?->status,
                    'exception_reason' => $pkg?->exception_reason,
                    'temperature_requirement' => $pkg?->temperature_requirement,
                    'age_restricted' => $pkg?->age_restricted,
                    'requires_id_verification' => $pkg?->requires_id_verification,
                    'no_contactless_delivery' => $pkg?->no_contactless_delivery,
                    'delivery_completion_locked_until_verified' => $pkg?->delivery_completion_locked_until_verified,
                    'age_verification_status' => $pkg?->age_verification_status,
                ];
            });

        return response()->json([
            'route' => [
                'id' => $route->id,
                'route_name' => $route->route_name,
                'route_type' => $route->route_type,
                'pickup_location' => $route->pickup_location,
                'pickup_lat' => $route->pickup_lat,
                'pickup_lng' => $route->pickup_lng,
                'status' => $route->status,
                'total_packages' => $route->total_packages,
                'completed_packages' => $route->completed_packages,
                'failed_packages' => $route->failed_packages,
                'driver_pay_per_package' => $route->driver_pay_per_package,
                'instant_payout_allowed' => $route->instant_payout_allowed,
                'weekly_payout_allowed' => $route->weekly_payout_allowed,
                'vehicle_type_required' => $route->vehicle_type_required,
            ],
            'stops' => $stops,
        ]);
    }

    public function scanPickup(Request $request, $routeId)
    {
        $driver = $this->authDriver($request);

        $validator = Validator::make($request->all(), [
            'tracking_id' => ['required_without:barcode', 'string'],
            'barcode' => ['required_without:tracking_id', 'string'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'photo' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $package = UrbanGoodzRoutePackage::where('dedicated_route_id', $routeId)
            ->where(function ($q) use ($request) {
                if ($request->tracking_id) $q->where('tracking_id', $request->tracking_id);
                if ($request->barcode) $q->orWhere('barcode', $request->barcode);
            })
            ->first();

        if (!$package) {
            return response()->json(['error' => 'Package not found on this route'], 404);
        }

        if ($package->status !== 'pending') {
            return response()->json(['error' => 'Package already scanned or delivered'], 400);
        }

        DB::beginTransaction();
        try {
            $package->status = 'picked_up';
            $package->pickup_scanned_at = now();
            $package->pickup_scanned_by = $driver->id;
            $package->pickup_lat = $request->latitude;
            $package->pickup_lng = $request->longitude;
            $package->save();

            UrbanGoodzPackageScan::create([
                'package_id' => $package->id,
                'scan_type' => 'pickup',
                'scanned_by' => $driver->id,
                'scanner_type' => 'driver',
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'photo' => $request->photo,
                'notes' => $request->notes,
            ]);

            if ($package->requires_custody) {
                UrbanGoodzMedicalCustodyLog::create([
                    'package_id' => $package->id,
                    'custody_event' => 'pickup',
                    'from_user_id' => $package->client->id ?? null,
                    'from_user_type' => 'client',
                    'to_user_id' => $driver->id,
                    'to_user_type' => 'driver',
                    'seal_intact' => true,
                    'notes' => 'Driver pickup scan',
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Package picked up successfully',
                'package' => [
                    'id' => $package->id,
                    'tracking_id' => $package->tracking_id,
                    'status' => $package->status,
                    'pickup_scanned_at' => $package->pickup_scanned_at->toDateTimeString(),
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Pickup scan failed: ' . $e->getMessage()], 500);
        }
    }

    public function scanDropoff(Request $request, $routeId)
    {
        $driver = $this->authDriver($request);

        $validator = Validator::make($request->all(), [
            'tracking_id' => ['required_without:barcode', 'string'],
            'barcode' => ['required_without:tracking_id', 'string'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'photo' => ['nullable', 'string'],
            'signature' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $package = UrbanGoodzRoutePackage::where('dedicated_route_id', $routeId)
            ->where(function ($q) use ($request) {
                if ($request->tracking_id) $q->where('tracking_id', $request->tracking_id);
                if ($request->barcode) $q->orWhere('barcode', $request->barcode);
            })
            ->first();

        if (!$package) {
            return response()->json(['error' => 'Package not found on this route'], 404);
        }

        if (!in_array($package->status, ['picked_up', 'in_transit'])) {
            return response()->json(['error' => 'Package must be picked up before dropoff'], 400);
        }

        if ($package->isDeliveryLocked()) {
            return response()->json(['error' => 'Age verification required before delivery completion'], 409);
        }

        DB::beginTransaction();
        try {
            $package->status = 'delivered';
            $package->dropoff_scanned_at = now();
            $package->dropoff_scanned_by = $driver->id;
            $package->dropoff_lat = $request->latitude;
            $package->dropoff_lng = $request->longitude;
            $package->proof_photo = $request->photo ?? $package->proof_photo;
            $package->recipient_signature = $request->signature ?? $package->recipient_signature;
            $package->save();

            UrbanGoodzPackageScan::create([
                'package_id' => $package->id,
                'scan_type' => 'dropoff',
                'scanned_by' => $driver->id,
                'scanner_type' => 'driver',
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'photo' => $request->photo,
                'signature' => $request->signature,
                'notes' => $request->notes,
            ]);

            if ($package->requires_custody) {
                UrbanGoodzMedicalCustodyLog::create([
                    'package_id' => $package->id,
                    'custody_event' => 'dropoff',
                    'from_user_id' => $driver->id,
                    'from_user_type' => 'driver',
                    'to_user_id' => null,
                    'to_user_type' => 'recipient',
                    'seal_intact' => true,
                    'signature' => $request->signature,
                    'notes' => 'Driver dropoff scan',
                ]);
            }

            $route = UrbanGoodzDedicatedRoute::find($routeId);
            if ($route) {
                $route->increment('completed_packages');
            }

            $route = UrbanGoodzDedicatedRoute::find($routeId);
            if ($route && $route->driver_pay_per_package > 0) {
                $amount = $route->driver_pay_per_package;
                if ($package->priority === 'high' || $package->priority === 'urgent' || $package->priority === 'medical') {
                    $amount += $route->priority_package_bonus;
                }

                UrbanGoodzDriverEarning::create([
                    'delivery_man_id' => $driver->id,
                    'package_id' => $package->id,
                    'dedicated_route_id' => $routeId,
                    'earning_type' => 'per_package',
                    'amount' => $amount,
                    'status' => 'pending',
                    'description' => 'Package ' . $package->tracking_id . ' delivery pay',
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Package delivered successfully',
                'package' => [
                    'id' => $package->id,
                    'tracking_id' => $package->tracking_id,
                    'status' => $package->status,
                    'dropoff_scanned_at' => $package->dropoff_scanned_at->toDateTimeString(),
                    'earned' => $route?->driver_pay_per_package ?? 0,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Dropoff scan failed: ' . $e->getMessage()], 500);
        }
    }

    public function scanException(Request $request, $routeId)
    {
        $driver = $this->authDriver($request);

        $validator = Validator::make($request->all(), [
            'tracking_id' => ['required_without:barcode', 'string'],
            'barcode' => ['required_without:tracking_id', 'string'],
            'exception_reason' => ['required', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'photo' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $package = UrbanGoodzRoutePackage::where('dedicated_route_id', $routeId)
            ->where(function ($q) use ($request) {
                if ($request->tracking_id) $q->where('tracking_id', $request->tracking_id);
                if ($request->barcode) $q->orWhere('barcode', $request->barcode);
            })
            ->first();

        if (!$package) {
            return response()->json(['error' => 'Package not found on this route'], 404);
        }

        DB::beginTransaction();
        try {
            $package->status = 'failed';
            $package->exception_reason = $request->exception_reason;
            $package->notes = $request->notes ?? $package->notes;
            $package->save();

            UrbanGoodzPackageScan::create([
                'package_id' => $package->id,
                'scan_type' => 'exception',
                'scanned_by' => $driver->id,
                'scanner_type' => 'driver',
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'photo' => $request->photo,
                'exception_reason' => $request->exception_reason,
                'notes' => $request->notes,
            ]);

            $route = UrbanGoodzDedicatedRoute::find($routeId);
            if ($route) {
                $route->increment('failed_packages');

                if ($route->failed_delivery_partial_pay > 0) {
                    UrbanGoodzDriverEarning::create([
                        'delivery_man_id' => $driver->id,
                        'package_id' => $package->id,
                        'dedicated_route_id' => $routeId,
                        'earning_type' => 'partial_pay',
                        'amount' => $route->failed_delivery_partial_pay,
                        'status' => 'pending',
                        'description' => 'Partial pay for failed delivery: ' . $package->tracking_id,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Exception recorded',
                'package' => [
                    'id' => $package->id,
                    'tracking_id' => $package->tracking_id,
                    'status' => $package->status,
                    'exception_reason' => $package->exception_reason,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Exception scan failed: ' . $e->getMessage()], 500);
        }
    }

    public function routeStarted(Request $request, $routeId)
    {
        $driver = $this->authDriver($request);

        $route = UrbanGoodzDedicatedRoute::where('id', $routeId)
            ->where('assigned_driver_id', $driver->id)
            ->firstOrFail();

        $route->status = 'in_progress';
        $route->route_started_at = now();
        $route->save();

        UrbanGoodzRouteAssignment::where('dedicated_route_id', $routeId)
            ->where('delivery_man_id', $driver->id)
            ->update([
                'status' => 'started',
                'route_started_at' => now(),
            ]);

        return response()->json(['message' => 'Route started', 'started_at' => $route->route_started_at->toDateTimeString()]);
    }

    public function routeCompleted(Request $request, $routeId)
    {
        $driver = $this->authDriver($request);

        $route = UrbanGoodzDedicatedRoute::where('id', $routeId)
            ->where('assigned_driver_id', $driver->id)
            ->firstOrFail();

        $pendingPackages = UrbanGoodzRoutePackage::where('dedicated_route_id', $routeId)
            ->whereIn('status', ['pending', 'picked_up', 'in_transit'])
            ->count();

        $route->status = 'completed';
        $route->route_completed_at = now();
        $route->save();

        UrbanGoodzRouteAssignment::where('dedicated_route_id', $routeId)
            ->where('delivery_man_id', $driver->id)
            ->update([
                'status' => 'completed',
                'route_completed_at' => now(),
            ]);

        if ($route->route_completion_bonus > 0) {
            UrbanGoodzDriverEarning::create([
                'delivery_man_id' => $driver->id,
                'dedicated_route_id' => $routeId,
                'earning_type' => 'completion_bonus',
                'amount' => $route->route_completion_bonus,
                'status' => 'pending',
                'description' => 'Route completion bonus: ' . $route->route_name,
            ]);
        }

        UrbanGoodzRouteBatch::where('dedicated_route_id', $routeId)
            ->where('status', 'in_transit')
            ->update(['status' => 'completed', 'completed_at' => now()]);

        return response()->json([
            'message' => 'Route completed',
            'completed_at' => $route->route_completed_at->toDateTimeString(),
            'completed_packages' => $route->completed_packages,
            'failed_packages' => $route->failed_packages,
            'pending_packages_remaining' => $pendingPackages,
            'completion_bonus' => $route->route_completion_bonus,
        ]);
    }

    public function earnings(Request $request)
    {
        $driver = $this->authDriver($request);

        $earnings = UrbanGoodzDriverEarning::where('delivery_man_id', $driver->id)
            ->latest()
            ->paginate(50);

        $totals = [
            'pending' => UrbanGoodzDriverEarning::where('delivery_man_id', $driver->id)->where('status', 'pending')->sum('amount'),
            'approved' => UrbanGoodzDriverEarning::where('delivery_man_id', $driver->id)->where('status', 'approved')->sum('amount'),
            'paid' => UrbanGoodzDriverEarning::where('delivery_man_id', $driver->id)->where('status', 'paid')->sum('amount'),
            'total' => UrbanGoodzDriverEarning::where('delivery_man_id', $driver->id)->sum('amount'),
        ];

        $orderAnywhereSplits = UrbanGoodzPaymentSplit::where('recipient_type', 'driver')
            ->where('recipient_id', $driver->id)
            ->where('feature', 'order_anywhere')
            ->where('status', 'released')
            ->where('split_type', 'driver_earning')
            ->latest()
            ->get();

        $orderAnywhereTotal = $orderAnywhereSplits->sum('amount');
        $orderAnywhereReversals = UrbanGoodzPaymentSplit::where('recipient_type', 'driver')
            ->where('recipient_id', $driver->id)
            ->where('feature', 'order_anywhere')
            ->where('status', 'reversed')
            ->where('split_type', 'driver_refund_reversal')
            ->sum('amount');
        $orderAnywhereTotal = max($orderAnywhereTotal - $orderAnywhereReversals, 0);

        // Order Anywhere settlement creates an UrbanGoodzDriverEarning record,
        // so adding released splits again would double-count the same money.
        $allTimeTotal = $totals['total'];

        return response()->json([
            'earnings' => $earnings->items(),
            'totals' => $totals,
            'order_anywhere' => [
                'total' => $orderAnywhereTotal,
                'reversal_total' => $orderAnywhereReversals,
                'splits' => $orderAnywhereSplits->toArray(),
            ],
            'all_time_total' => $allTimeTotal,
            'current_page' => $earnings->currentPage(),
            'last_page' => $earnings->lastPage(),
        ]);
    }

    public function requestPayout(Request $request)
    {
        $driver = $this->authDriver($request);

        $validator = Validator::make($request->all(), [
            'payout_type' => ['required', Rule::in(['instant', 'weekly', 'held'])],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $pendingEarnings = UrbanGoodzDriverEarning::where('delivery_man_id', $driver->id)
            ->where('status', 'pending')
            ->sum('amount');

        $pendingPayouts = UrbanGoodzDriverPayoutRequest::where('delivery_man_id', $driver->id)
            ->whereIn('status', ['pending', 'approved', 'processing'])
            ->sum('requested_amount');

        $availableEarnings = max($pendingEarnings - $pendingPayouts, 0);

        if ($request->amount > $availableEarnings) {
            return response()->json([
                'error' => 'Requested amount exceeds pending earnings',
                'pending_earnings' => $pendingEarnings,
                'available_earnings' => $availableEarnings,
            ], 400);
        }

        $instantFee = 0;
        if ($request->payout_type === 'instant') {
            $instantFee = round($request->amount * 0.05, 2);
        }

        $payout = UrbanGoodzDriverPayoutRequest::create([
            'delivery_man_id' => $driver->id,
            'payout_type' => $request->payout_type,
            'requested_amount' => $request->amount,
            'instant_fee' => $instantFee,
            'net_amount' => $request->amount - $instantFee,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Payout requested',
            'payout' => $payout,
        ]);
    }

    public function payoutHistory(Request $request)
    {
        $driver = $this->authDriver($request);

        $payouts = UrbanGoodzDriverPayoutRequest::where('delivery_man_id', $driver->id)
            ->latest()
            ->paginate(25);

        return response()->json([
            'payouts' => $payouts->items(),
            'current_page' => $payouts->currentPage(),
            'last_page' => $payouts->lastPage(),
        ]);
    }

    public function submitAgeVerification(Request $request, $routeId)
    {
        $driver = $this->authDriver($request);

        $validator = Validator::make($request->all(), [
            'tracking_id' => ['required_without:barcode', 'string'],
            'barcode' => ['required_without:tracking_id', 'string'],
            'id_type_checked' => ['required', 'string', 'max:100'],
            'recipient_name_verified' => ['required', 'string', 'max:255'],
            'recipient_dob_verified' => ['required', 'date'],
            'recipient_age_confirmed' => ['required', 'boolean', 'accepted'],
            'signature_captured' => ['nullable', 'boolean'],
            'proof_photo_captured' => ['nullable', 'boolean'],
            'driver_notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $package = UrbanGoodzRoutePackage::where('dedicated_route_id', $routeId)
            ->where(function ($q) use ($request) {
                if ($request->tracking_id) $q->where('tracking_id', $request->tracking_id);
                if ($request->barcode) $q->orWhere('barcode', $request->barcode);
            })
            ->first();

        if (!$package) {
            return response()->json(['error' => 'Package not found on this route'], 404);
        }

        if (!$package->isAgeRestricted()) {
            return response()->json(['error' => 'Package is not age-restricted'], 400);
        }

        DB::beginTransaction();
        try {
            $verification = UrbanGoodzAgeVerification::create([
                'package_id' => $package->id,
                'route_id' => $routeId,
                'order_id' => $package->order_id,
                'driver_id' => $driver->id,
                'verification_status' => 'verified',
                'id_type_checked' => $request->id_type_checked,
                'recipient_name_verified' => $request->recipient_name_verified,
                'recipient_dob_verified' => $request->recipient_dob_verified,
                'recipient_age_confirmed' => $request->recipient_age_confirmed,
                'signature_captured' => $request->signature_captured ?? false,
                'proof_photo_captured' => $request->proof_photo_captured ?? false,
                'driver_notes' => $request->driver_notes,
                'verification_attempted_at' => now(),
            ]);

            $package->age_verification_status = 'verified';
            $package->age_verified_at = now();
            $package->age_verified_by_driver_id = $driver->id;
            $package->age_verification_driver_notes = $request->driver_notes;
            $package->save();

            DB::commit();

            return response()->json([
                'message' => 'Age verification passed',
                'verification' => $verification,
                'delivery_unlocked' => !$package->isDeliveryLocked(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Age verification failed: ' . $e->getMessage()], 500);
        }
    }

    public function submitAgeRefusal(Request $request, $routeId)
    {
        $driver = $this->authDriver($request);

        $validator = Validator::make($request->all(), [
            'tracking_id' => ['required_without:barcode', 'string'],
            'barcode' => ['required_without:tracking_id', 'string'],
            'refusal_reason' => ['required', 'string', Rule::in(UrbanGoodzRoutePackage::AGE_VERIFICATION_REFUSAL_REASONS)],
            'driver_notes' => ['nullable', 'string'],
            'signature_captured' => ['nullable', 'boolean'],
            'proof_photo_captured' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $package = UrbanGoodzRoutePackage::where('dedicated_route_id', $routeId)
            ->where(function ($q) use ($request) {
                if ($request->tracking_id) $q->where('tracking_id', $request->tracking_id);
                if ($request->barcode) $q->orWhere('barcode', $request->barcode);
            })
            ->first();

        if (!$package) {
            return response()->json(['error' => 'Package not found on this route'], 404);
        }

        if (!$package->isAgeRestricted()) {
            return response()->json(['error' => 'Package is not age-restricted'], 400);
        }

        $needsAdminReview = $package->admin_review_required_on_failure;

        DB::beginTransaction();
        try {
            $verification = UrbanGoodzAgeVerification::create([
                'package_id' => $package->id,
                'route_id' => $routeId,
                'order_id' => $package->order_id,
                'driver_id' => $driver->id,
                'verification_status' => 'refused',
                'refusal_reason' => $request->refusal_reason,
                'driver_notes' => $request->driver_notes,
                'signature_captured' => $request->signature_captured ?? false,
                'proof_photo_captured' => $request->proof_photo_captured ?? false,
                'verification_attempted_at' => now(),
                'admin_review_required' => $needsAdminReview,
                'admin_review_status' => $needsAdminReview ? 'pending' : null,
            ]);

            $package->age_verification_status = 'refused';
            $package->age_verification_refusal_reason = $request->refusal_reason;
            $package->age_verification_driver_notes = $request->driver_notes;

            if ($needsAdminReview) {
                $package->status = 'admin_review';
            }
            $package->save();

            DB::commit();

            return response()->json([
                'message' => 'Age verification refused',
                'verification' => $verification,
                'admin_review_required' => $needsAdminReview,
                'delivery_locked' => $package->isDeliveryLocked(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Age refusal failed: ' . $e->getMessage()], 500);
        }
    }

    public function checkAgeStatus(Request $request, $routeId)
    {
        $driver = $this->authDriver($request);

        $validator = Validator::make($request->all(), [
            'tracking_id' => ['required_without:barcode', 'string'],
            'barcode' => ['required_without:tracking_id', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $package = UrbanGoodzRoutePackage::where('dedicated_route_id', $routeId)
            ->where(function ($q) use ($request) {
                if ($request->tracking_id) $q->where('tracking_id', $request->tracking_id);
                if ($request->barcode) $q->orWhere('barcode', $request->barcode);
            })
            ->first();

        if (!$package) {
            return response()->json(['error' => 'Package not found on this route'], 404);
        }

        return response()->json([
            'package_id' => $package->id,
            'tracking_id' => $package->tracking_id,
            'age_restricted' => $package->isAgeRestricted(),
            'age_verification_status' => $package->age_verification_status,
            'is_delivery_locked' => $package->isDeliveryLocked(),
            'requires_id_verification' => $package->requires_id_verification,
            'no_contactless_delivery' => $package->no_contactless_delivery,
        ]);
    }
}
