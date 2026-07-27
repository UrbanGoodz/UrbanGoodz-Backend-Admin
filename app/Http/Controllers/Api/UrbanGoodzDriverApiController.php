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
use App\Models\UrbanGoodzLoadBoardLoad;
use App\Models\UrbanGoodzLoadBoardBid;
use App\Models\UrbanGoodzPaymentLedger;
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

    private function assignedRouteOrFail($routeId, DeliveryMan $driver): UrbanGoodzDedicatedRoute
    {
        return UrbanGoodzDedicatedRoute::where('id', $routeId)
            ->where('assigned_driver_id', $driver->id)
            ->firstOrFail();
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
                    'sequence_number' => $stop->stop_order,
                    'stop_type' => 'dropoff',
                    'package_id' => $pkg?->id,
                    'tracking_id' => $pkg?->tracking_id,
                    'barcode' => $pkg?->barcode,
                    'dropoff_name' => $pkg?->dropoff_name,
                    'contact_phone' => $pkg?->dropoff_phone,
                    'dropoff_address' => $pkg?->dropoff_address,
                    'dropoff_lat' => $pkg?->dropoff_lat,
                    'dropoff_lng' => $pkg?->dropoff_lng,
                    'delivery_window_start' => $pkg?->delivery_window_start?->toDateTimeString(),
                    'delivery_window_end' => $pkg?->delivery_window_end?->toDateTimeString(),
                    'priority' => $pkg?->priority,
                    'requires_signature' => $pkg?->requires_signature,
                    'requires_photo' => $pkg?->requires_photo,
                    'requires_custody' => $pkg?->requires_custody,
                    'proof_requirements' => array_values(array_filter([
                        $pkg?->requires_signature ? 'signature' : null,
                        $pkg?->requires_photo ? 'photo' : null,
                        $pkg?->requires_custody ? 'chain_of_custody' : null,
                        $pkg?->requires_id_verification ? 'id_verification' : null,
                    ])),
                    'contact_instructions' => $pkg?->notes,
                    'exception_requirements' => [
                        'report_reason' => true,
                        'photo_supported' => true,
                    ],
                    'return_required' => (bool) $pkg?->return_required,
                    'return_location' => $pkg?->return_location,
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
                'end_location' => $route->end_location,
                'end_lat' => $route->end_lat,
                'end_lng' => $route->end_lng,
                'return_to_origin' => (bool) $route->return_to_origin,
                'optimization_status' => $route->optimization_status,
                'optimization_version' => $route->optimization_version,
                'optimization_method' => $route->optimization_method,
                'optimization_provider' => $route->optimization_provider,
                'estimated_miles' => $route->optimized_distance_miles ?? $route->estimated_miles,
                'estimated_duration_minutes' => $route->optimized_duration_minutes ?? $route->estimated_duration,
            ],
            'stops' => $stops,
        ]);
    }

    public function resequenceRoute(Request $request, $routeId)
    {
        $driver = $this->authDriver($request);

        $validator = Validator::make($request->all(), [
            'endpoint_type' => 'required|in:company_endpoint,return_to_pickup,no_preference,private_endpoint',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        $endpointType = $request->input('endpoint_type');

        $route = UrbanGoodzDedicatedRoute::where('id', $routeId)
            ->where('assigned_driver_id', $driver->id)
            ->firstOrFail();

        $startLocation = [
            'lat' => (float)$route->pickup_lat,
            'lng' => (float)$route->pickup_lng,
        ];

        $endLocation = null;
        $privateAddress = null;
        $privateLat = null;
        $privateLng = null;

        if ($endpointType === 'company_endpoint') {
            $endLocation = [
                'lat' => (float)($route->end_lat ?? $route->pickup_lat),
                'lng' => (float)($route->end_lng ?? $route->pickup_lng),
            ];
        } elseif ($endpointType === 'return_to_pickup') {
            $endLocation = [
                'lat' => (float)$route->pickup_lat,
                'lng' => (float)$route->pickup_lng,
            ];
        } elseif ($endpointType === 'private_endpoint') {
            if (!$driver->hasApprovedPrivateEndpoint()) {
                return response()->json(['message' => 'Selected private endpoint is not approved.'], 400);
            }
            $privateAddress = $driver->private_endpoint_address;
            $privateLat = (float)$driver->private_endpoint_lat;
            $privateLng = (float)$driver->private_endpoint_lng;
            $endLocation = [
                'lat' => $privateLat,
                'lng' => $privateLng,
            ];
        }

        $stops = $route->optimizationStops()
            ->with('package')
            ->orderBy('stop_order')
            ->get();

        if ($stops->isEmpty()) {
            return response()->json(['message' => 'No stops found on this route.'], 400);
        }

        $routeStops = [];
        foreach ($stops as $stop) {
            $pkg = $stop->package;
            if ($pkg) {
                $routeStops[] = \App\Services\UrbanGoodz\Routing\DTOs\RouteStop::fromPackageModel($pkg);
            }
        }

        $sequencingService = new \App\Services\UrbanGoodz\Routing\Services\RouteSequencingService();
        
        $constraints = new \App\Services\UrbanGoodz\Routing\DTOs\ClusteringConstraints(
            preserveLockedStops: true,
            respectTimeWindows: true,
            serviceTimePerStopMinutes: 5,
        );

        $sequenced = $sequencingService->sequenceRoute($routeStops, $constraints, null, $startLocation, $endLocation);

        $startTime = strtotime($route->scheduled_date ? $route->scheduled_date->toDateString() . ' 08:00:00' : now()->toDateString() . ' 08:00:00');
        $isFeasible = $sequencingService->checkTimeWindowFeasibility($sequenced['ordered_stops'], $startLocation, $startTime);
        if (!$isFeasible) {
            return response()->json(['message' => 'Resequencing failed: The optimized stops violate delivery time windows.'], 400);
        }

        $originalMiles = (float)$route->estimated_miles;
        $newMiles = (float)$sequenced['total_miles'];
        $varianceMiles = $newMiles - $originalMiles;
        $variancePercent = $originalMiles > 0 ? ($varianceMiles / $originalMiles) * 100 : 0;

        $isExcessive = ($variancePercent > 20.0 || $varianceMiles > 15.0);

        $nextVersion = \App\Models\UrbanGoodzRouteExecutionVersion::where('dedicated_route_id', $route->id)->max('version') + 1;
        $nextVersion = max(1, $nextVersion);

        $stopOrderSequence = [];
        $seqOrder = 1;
        foreach ($sequenced['ordered_stops'] as $sStop) {
            $stopOrderSequence[] = [
                'package_id' => $sStop->packageId,
                'stop_order' => $seqOrder++,
            ];
        }

        $executionVersion = \App\Models\UrbanGoodzRouteExecutionVersion::create([
            'dedicated_route_id' => $route->id,
            'driver_id' => $driver->id,
            'version' => $nextVersion,
            'endpoint_type' => $endpointType,
            'private_endpoint_address' => $privateAddress,
            'private_endpoint_lat' => $privateLat,
            'private_endpoint_lng' => $privateLng,
            'miles' => $newMiles,
            'duration_minutes' => (int)$sequenced['estimated_duration_minutes'],
            'stop_order_sequence' => $stopOrderSequence,
            'status' => $isExcessive ? 'pending_approval' : 'active',
        ]);

        if ($isExcessive) {
            $route->update(['status' => 'admin_review']);
            return response()->json([
                'message' => 'Resequencing requires dispatcher approval due to excessive variance.',
                'execution_version' => $executionVersion->version,
                'miles' => $newMiles,
                'duration_minutes' => $executionVersion->duration_minutes,
                'requires_approval' => true,
            ]);
        }

        DB::transaction(function () use ($route, $stopOrderSequence) {
            // First pass: set stop_orders to negative temporary values to prevent unique key collisions
            foreach ($stopOrderSequence as $mapping) {
                \App\Models\UrbanGoodzRouteOptimizationStop::where('dedicated_route_id', $route->id)
                    ->where('package_id', $mapping['package_id'])
                    ->update(['stop_order' => -$mapping['stop_order']]);
            }

            // Second pass: set to final correct stop_orders
            foreach ($stopOrderSequence as $mapping) {
                \App\Models\UrbanGoodzRouteOptimizationStop::where('dedicated_route_id', $route->id)
                    ->where('package_id', $mapping['package_id'])
                    ->update(['stop_order' => $mapping['stop_order']]);

                \App\Models\UrbanGoodzRoutePackage::where('id', $mapping['package_id'])
                    ->update(['stop_order' => $mapping['stop_order']]);

                $routePkg = \App\Models\UrbanGoodzRoutePackage::find($mapping['package_id']);
                if ($routePkg) {
                    \App\Models\UrbanGoodzBatchPackage::where('tracking_id', $routePkg->tracking_id)
                        ->update(['stop_order' => $mapping['stop_order']]);
                }
            }
        });

        return response()->json([
            'message' => 'Route resequenced successfully.',
            'execution_version' => $executionVersion->version,
            'miles' => $newMiles,
            'duration_minutes' => $executionVersion->duration_minutes,
            'requires_approval' => false,
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

        $this->assignedRouteOrFail($routeId, $driver);

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

        $route = $this->assignedRouteOrFail($routeId, $driver);

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

        $currentStop = $package->optimizationStop;
        if ($currentStop) {
            $unfinishedPriorStop = \App\Models\UrbanGoodzRouteOptimizationStop::query()
                ->where('dedicated_route_id', $routeId)
                ->where('stop_order', '<', $currentStop->stop_order)
                ->whereHas('package', function ($query) {
                    $query->whereNotIn('status', [
                        'delivered', 'failed', 'unable_to_deliver',
                        'returned_to_pickup', 'returned_to_hub', 'returned_to_business', 'completed',
                    ]);
                })
                ->orderBy('stop_order')
                ->first();
            if ($unfinishedPriorStop) {
                return response()->json([
                    'error' => 'Complete or record an exception for the preceding stop before this delivery.',
                    'expected_stop_order' => $unfinishedPriorStop->stop_order,
                    'attempted_stop_order' => $currentStop->stop_order,
                ], 409);
            }
        }

        DB::beginTransaction();
        try {
            $package->status = 'delivered';
            $package->dropoff_scanned_at = now();
            $package->dropoff_scanned_by = $driver->id;
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

            $route->increment('completed_packages');

            if ($route->driver_pay_per_package > 0) {
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

        $this->assignedRouteOrFail($routeId, $driver);

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

        if (!in_array($route->status, ['active', 'in_progress'])) {
            return response()->json(['error' => 'Route is not in an active state'], 400);
        }

        $pendingPackages = UrbanGoodzRoutePackage::where('dedicated_route_id', $routeId)
            ->whereIn('status', ['pending', 'picked_up', 'in_transit'])
            ->count();

        $completedCount = UrbanGoodzRoutePackage::where('dedicated_route_id', $routeId)->delivered()->count();
        $failedCount = UrbanGoodzRoutePackage::where('dedicated_route_id', $routeId)->failed()->count();
        $returningCount = UrbanGoodzRoutePackage::where('dedicated_route_id', $routeId)
            ->whereIn('status', ['return_required', 'returning_to_pickup', 'returning_to_hub', 'returning_to_business'])
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
            'completed_packages' => $completedCount,
            'failed_packages' => $failedCount,
            'returning_packages' => $returningCount,
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

    public function loadBoardAvailable(Request $request)
    {
        $driver = $this->authDriver($request);

        $query = UrbanGoodzLoadBoardLoad::where('status', 'available')
            ->with(['businessClient:company_name,id']);

        if ($request->filled('origin_state')) {
            $query->where('origin_state', $request->origin_state);
        }
        if ($request->filled('destination_state')) {
            $query->where('destination_state', $request->destination_state);
        }
        if ($request->filled('equipment_type')) {
            $query->where('equipment_type', $request->equipment_type);
        }

        $loads = $query->latest()->paginate(25);

        return response()->json([
            'loads' => $loads->items(),
            'current_page' => $loads->currentPage(),
            'last_page' => $loads->lastPage(),
            'total' => $loads->total(),
        ]);
    }

    public function loadBoardDetail(Request $request, $loadId)
    {
        $driver = $this->authDriver($request);

        $load = UrbanGoodzLoadBoardLoad::with(['businessClient:company_name,id'])
            ->find($loadId);

        if (!$load) {
            return response()->json(['error' => 'Load not found'], 404);
        }

        $existingBid = UrbanGoodzLoadBoardBid::where('load_id', $loadId)
            ->where('driver_id', $driver->id)
            ->first();

        return response()->json([
            'load' => [
                'id' => $load->id,
                'load_number' => $load->load_number,
                'origin_city' => $load->origin_city,
                'origin_state' => $load->origin_state,
                'destination_city' => $load->destination_city,
                'destination_state' => $load->destination_state,
                'distance_miles' => $load->distance_miles,
                'payout_amount' => $load->payout_amount,
                'driver_payout_amount' => $load->effective_driver_payout,
                'load_type' => $load->load_type,
                'equipment_type' => $load->equipment_type,
                'weight_lbs' => $load->weight_lbs,
                'pieces' => $load->pieces,
                'commodity_description' => $load->commodity_description,
                'is_hazmat' => $load->is_hazmat,
                'is_temperature_controlled' => $load->is_temperature_controlled,
                'requires_liftgate' => $load->requires_liftgate,
                'is_expedited' => $load->is_expedited,
                'origin_ready_at' => $load->origin_ready_at?->toDateTimeString(),
                'destination_due_at' => $load->destination_due_at?->toDateTimeString(),
                'bid_count' => $load->bids()->count(),
                'my_bid' => $existingBid ? [
                    'id' => $existingBid->id,
                    'bid_amount' => $existingBid->bid_amount,
                    'status' => $existingBid->status,
                ] : null,
            ],
        ]);
    }

    public function loadBoardPlaceBid(Request $request, $loadId)
    {
        $driver = $this->authDriver($request);

        $validator = Validator::make($request->all(), [
            'bid_amount' => ['required', 'numeric', 'min:0.01'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $load = UrbanGoodzLoadBoardLoad::where('status', 'available')->find($loadId);
        if (!$load) {
            return response()->json(['error' => 'Load not available for bidding'], 404);
        }

        $existingBid = UrbanGoodzLoadBoardBid::where('load_id', $loadId)
            ->where('driver_id', $driver->id)
            ->where('status', 'pending')
            ->first();
        if ($existingBid) {
            return response()->json(['error' => 'You already have a pending bid on this load'], 400);
        }

        $bid = UrbanGoodzLoadBoardBid::create([
            'load_id' => $loadId,
            'driver_id' => $driver->id,
            'bid_amount' => $request->bid_amount,
            'bid_message' => $request->message,
            'status' => 'pending',
        ]);

        $load->logEvent('bid_placed', null, (string) $request->bid_amount, [
            'bid_id' => $bid->id,
            'driver_id' => $driver->id,
        ], 'driver', $driver->id, "Bid placed: \${$request->bid_amount}");

        return response()->json([
            'message' => 'Bid placed successfully',
            'bid' => $bid,
        ], 201);
    }

    public function loadBoardMyBids(Request $request)
    {
        $driver = $this->authDriver($request);

        $bids = UrbanGoodzLoadBoardBid::with(['load'])
            ->where('driver_id', $driver->id)
            ->latest()
            ->paginate(25);

        return response()->json([
            'bids' => $bids->items(),
            'current_page' => $bids->currentPage(),
            'last_page' => $bids->lastPage(),
        ]);
    }

    public function loadBoardWithdrawBid(Request $request, $bidId)
    {
        $driver = $this->authDriver($request);

        $bid = UrbanGoodzLoadBoardBid::where('id', $bidId)
            ->where('driver_id', $driver->id)
            ->pending()
            ->first();

        if (!$bid) {
            return response()->json(['error' => 'Bid not found or not withdrawable'], 404);
        }

        $bid->update(['status' => 'withdrawn']);

        if ($bid->load) {
            $bid->load->logEvent('bid_withdrawn', null, (string) $bid->bid_amount, [
                'bid_id' => $bid->id,
                'driver_id' => $driver->id,
            ], 'driver', $driver->id, 'Bid withdrawn');
        }

        return response()->json(['message' => 'Bid withdrawn']);
    }

    public function activeJobs(Request $request)
    {
        $driver = $this->authDriver($request);

        $assignedRoutes = UrbanGoodzDedicatedRoute::with(['client:company_name,id', 'batches'])
            ->where('assigned_driver_id', $driver->id)
            ->whereIn('status', ['active', 'in_progress'])
            ->latest()
            ->get()
            ->map(function ($route) {
                return [
                    'type' => 'dedicated_route',
                    'id' => $route->id,
                    'name' => $route->route_name,
                    'status' => $route->status,
                    'total_packages' => $route->total_packages,
                    'completed_packages' => $route->completed_packages,
                    'client' => $route->client?->company_name,
                    'scheduled_date' => $route->scheduled_date?->toDateString(),
                ];
            });

        $assignedLoads = UrbanGoodzLoadBoardLoad::with(['businessClient:company_name,id'])
            ->where('assigned_driver_id', $driver->id)
            ->whereIn('status', ['assigned', 'in_transit', 'picked_up'])
            ->latest()
            ->get()
            ->map(function ($load) {
                return [
                    'type' => 'load_board',
                    'id' => $load->id,
                    'name' => $load->load_number,
                    'status' => $load->status,
                    'origin' => $load->origin_full,
                    'destination' => $load->destination_full,
                    'payout' => $load->effective_driver_payout,
                    'client' => $load->businessClient?->company_name,
                ];
            });

        return response()->json([
            'routes' => $assignedRoutes,
            'loads' => $assignedLoads,
        ]);
    }

    public function loadSourcingRecommendations(Request $request)
    {
        $driver = $this->authDriver($request);
        if (!$driver) return response()->json(['error' => 'Unauthorized'], 401);

        $service = new \App\Services\UrbanGoodz\LoadSource\LoadSourcingService();
        $limit = min((int) $request->get('limit', 20), 50);
        $result = $service->generateRecommendations($driver->id, $limit);

        $recommendations = array_map(function ($rec) {
            $load = $rec->externalLoad;
            return [
                'id' => $rec->id,
                'score' => $rec->score,
                'confidence' => $rec->confidence_level,
                'estimated_driver_net' => $rec->estimated_driver_net,
                'net_per_total_mile' => $rec->net_per_total_mile,
                'deadhead_miles' => $rec->deadhead_miles,
                'equipment_match' => $rec->equipment_match,
                'certification_match' => $rec->certification_match,
                'schedule_feasible' => $rec->schedule_feasible,
                'broker_risk' => $rec->broker_risk,
                'reasons_recommended' => $rec->reasons_recommended,
                'reasons_penalized' => $rec->reasons_penalized,
                'status' => $rec->status,
                'expires_at' => $rec->expires_at?->toIso8601String(),
                'load' => $load ? [
                    'id' => $load->id,
                    'source' => $load->source?->name,
                    'origin_city' => $load->origin_city,
                    'origin_state' => $load->origin_state,
                    'destination_city' => $load->destination_city,
                    'destination_state' => $load->destination_state,
                    'equipment_type' => $load->equipment_type,
                    'weight' => $load->weight,
                    'gross_rate' => $load->gross_rate,
                    'distance_loaded' => $load->distance_loaded,
                    'pickup_start' => $load->pickup_start?->toIso8601String(),
                    'pickup_end' => $load->pickup_end?->toIso8601String(),
                    'commodity' => $load->commodity,
                    'broker_name' => $load->broker_name,
                    'source_url' => $load->source_url,
                ] : null,
            ];
        }, $result['recommendations'] ?? []);

        return response()->json([
            'success' => true,
            'recommendations' => $recommendations,
            'count' => count($recommendations),
        ]);
    }

    public function loadSourcingDetail(Request $request, int $recommendationId)
    {
        $driver = $this->authDriver($request);
        if (!$driver) return response()->json(['error' => 'Unauthorized'], 401);

        $rec = \App\Models\LoadRecommendation::with(['externalLoad.source'])
            ->where('delivery_man_id', $driver->id)
            ->where('id', $recommendationId)
            ->first();

        if (!$rec) return response()->json(['error' => 'Recommendation not found'], 404);

        if ($rec->status === 'pending') {
            $rec->update(['status' => 'viewed', 'viewed_at' => now()]);
        }

        $load = $rec->externalLoad;
        $sourceUrl = $load?->source_url ?? $load?->source?->deep_link_template;

        return response()->json([
            'success' => true,
            'recommendation' => [
                'id' => $rec->id,
                'score' => $rec->score,
                'confidence' => $rec->confidence_level,
                'estimated_driver_net' => $rec->estimated_driver_net,
                'net_per_total_mile' => $rec->net_per_total_mile,
                'deadhead_miles' => $rec->deadhead_miles,
                'equipment_match' => $rec->equipment_match,
                'certification_match' => $rec->certification_match,
                'schedule_feasible' => $rec->schedule_feasible,
                'broker_risk' => $rec->broker_risk,
                'reasons_recommended' => $rec->reasons_recommended,
                'reasons_penalized' => $rec->reasons_penalized,
                'status' => $rec->status,
                'expires_at' => $rec->expires_at?->toIso8601String(),
            ],
            'load' => $load ? [
                'id' => $load->id,
                'source' => $load->source?->name,
                'source_key' => $load->source?->source_key,
                'compliance_status' => $load->compliance_status,
                'origin_address' => $load->origin_address,
                'origin_city' => $load->origin_city,
                'origin_state' => $load->origin_state,
                'origin_zip' => $load->origin_zip,
                'destination_address' => $load->destination_address,
                'destination_city' => $load->destination_city,
                'destination_state' => $load->destination_state,
                'destination_zip' => $load->destination_zip,
                'equipment_type' => $load->equipment_type,
                'trailer_type' => $load->trailer_type,
                'weight' => $load->weight,
                'commodity' => $load->commodity,
                'gross_rate' => $load->gross_rate,
                'rate_per_loaded_mile' => $load->rate_per_loaded_mile,
                'distance_loaded' => $load->distance_loaded,
                'distance_deadhead' => $load->distance_deadhead,
                'estimated_fuel_cost' => $load->estimated_fuel_cost,
                'estimated_tolls' => $load->estimated_tolls,
                'estimated_platform_fee' => $load->estimated_platform_fee,
                'estimated_driver_net' => $load->estimated_driver_net,
                'pickup_start' => $load->pickup_start?->toIso8601String(),
                'pickup_end' => $load->pickup_end?->toIso8601String(),
                'delivery_start' => $load->delivery_start?->toIso8601String(),
                'delivery_end' => $load->delivery_end?->toIso8601String(),
                'broker_name' => $load->broker_name,
                'broker_reference' => $load->broker_reference,
                'broker_rating' => $load->broker_rating,
                'source_url' => $sourceUrl,
                'has_external_link' => !empty($sourceUrl),
                'supports_bidding' => $load->source?->supports_bidding ?? false,
            ] : null,
        ]);
    }

    public function loadSourcingSave(Request $request, int $recommendationId)
    {
        $driver = $this->authDriver($request);
        if (!$driver) return response()->json(['error' => 'Unauthorized'], 401);

        $rec = \App\Models\LoadRecommendation::where('delivery_man_id', $driver->id)
            ->where('id', $recommendationId)
            ->first();

        if (!$rec) return response()->json(['error' => 'Recommendation not found'], 404);

        $rec->update(['status' => 'saved', 'saved_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function loadSourcingHide(Request $request, int $recommendationId)
    {
        $driver = $this->authDriver($request);
        if (!$driver) return response()->json(['error' => 'Unauthorized'], 401);

        $rec = \App\Models\LoadRecommendation::where('delivery_man_id', $driver->id)
            ->where('id', $recommendationId)
            ->first();

        if (!$rec) return response()->json(['error' => 'Recommendation not found'], 404);

        $rec->update(['status' => 'hidden', 'hidden_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function loadSourcingExpressInterest(Request $request, int $recommendationId)
    {
        $driver = $this->authDriver($request);
        if (!$driver) return response()->json(['error' => 'Unauthorized'], 401);

        $rec = \App\Models\LoadRecommendation::where('delivery_man_id', $driver->id)
            ->where('id', $recommendationId)
            ->first();

        if (!$rec) return response()->json(['error' => 'Recommendation not found'], 404);

        $rec->update(['status' => 'interested']);

        return response()->json(['success' => true]);
    }

    public function loadSourcingHandoff(Request $request, int $recommendationId)
    {
        $driver = $this->authDriver($request);
        if (!$driver) return response()->json(['error' => 'Unauthorized'], 401);

        $rec = \App\Models\LoadRecommendation::with('externalLoad.source')
            ->where('delivery_man_id', $driver->id)
            ->where('id', $recommendationId)
            ->first();

        if (!$rec) return response()->json(['error' => 'Recommendation not found'], 404);

        $load = $rec->externalLoad;
        $sourceUrl = $load->source_url ?? $load->source?->deep_link_template;

        if (!$sourceUrl) {
            return response()->json(['error' => 'No external URL available'], 404);
        }

        $service = new \App\Services\UrbanGoodz\LoadSource\LoadSourcingService();
        $result = $service->recordExternalHandoff(
            $load->id,
            $load->source_id,
            $driver->id,
            'driver',
            'open_source',
            $sourceUrl
        );

        return response()->json([
            'success' => true,
            'external_url' => $sourceUrl,
            'source_name' => $load->source?->name,
            'referral_id' => $result['referral']->id ?? null,
            'message' => 'Please complete booking on the source platform, then confirm back here.',
        ]);
    }

    public function loadSourcingConfirmBooking(Request $request, int $referralId)
    {
        $driver = $this->authDriver($request);
        if (!$driver) return response()->json(['error' => 'Unauthorized'], 401);

        $validated = $request->validate([
            'booked' => 'required|boolean',
            'rate_confirmation_url' => 'sometimes|nullable|string',
            'notes' => 'sometimes|nullable|string',
        ]);

        $referral = \App\Models\LoadPartnerReferral::where('referred_by', $driver->id)
            ->where('referred_by_type', 'driver')
            ->where('id', $referralId)
            ->first();

        if (!$referral) return response()->json(['error' => 'Referral not found'], 404);

        $service = new \App\Services\UrbanGoodz\LoadSource\LoadSourcingService();
        $result = $service->recordBookingConfirmation(
            $referralId,
            $validated['booked'],
            $validated['notes'] ?? null
        );

        return response()->json($result);
    }

    public function loadSourcingUpdatePreferences(Request $request)
    {
        $driver = $this->authDriver($request);
        if (!$driver) return response()->json(['error' => 'Unauthorized'], 401);

        $validated = $request->validate([
            'min_rate_per_mile' => 'sometimes|nullable|numeric',
            'max_deadhead_miles' => 'sometimes|nullable|numeric',
            'max_total_distance' => 'sometimes|nullable|numeric',
            'preferred_origins' => 'sometimes|nullable|array',
            'preferred_destinations' => 'sometimes|nullable|array',
            'excluded_origins' => 'sometimes|nullable|array',
            'excluded_destinations' => 'sometimes|nullable|array',
            'preferred_equipment' => 'sometimes|nullable|array',
            'excluded_commodities' => 'sometimes|nullable|array',
            'prefer_home_routes' => 'sometimes|boolean',
            'prefer_high_value' => 'sometimes|boolean',
            'prefer_short_haul' => 'sometimes|boolean',
            'prefer_long_haul' => 'sometimes|boolean',
            'open_to_hazmat' => 'sometimes|boolean',
            'open_to_temperature_controlled' => 'sometimes|boolean',
            'max_hours_per_day' => 'sometimes|nullable|integer',
        ]);

        $prefs = \App\Models\DriverLoadPreference::updateOrCreate(
            ['delivery_man_id' => $driver->id],
            $validated
        );

        return response()->json(['success' => true, 'preferences' => $prefs]);
    }

    public function loadSourcingAvailableExternal(Request $request)
    {
        $driver = $this->authDriver($request);
        if (!$driver) return response()->json(['error' => 'Unauthorized'], 401);

        $query = \App\Models\ExternalLoad::where('status', 'available')
            ->where('is_duplicate', false)
            ->with('source');

        if ($request->has('equipment_type')) {
            $query->where('equipment_type', $request->equipment_type);
        }
        if ($request->has('origin_state')) {
            $query->where('origin_state', $request->origin_state);
        }
        if ($request->has('destination_state')) {
            $query->where('destination_state', $request->destination_state);
        }

        $loads = $query->orderByDesc('gross_rate')->limit(50)->get();

        $result = $loads->map(function ($load) {
            $sourceUrl = $load->source_url ?? $load->source?->deep_link_template;
            return [
                'id' => $load->id,
                'source' => $load->source?->name,
                'source_key' => $load->source?->source_key,
                'compliance_status' => $load->compliance_status,
                'origin_city' => $load->origin_city,
                'origin_state' => $load->origin_state,
                'destination_city' => $load->destination_city,
                'destination_state' => $load->destination_state,
                'equipment_type' => $load->equipment_type,
                'weight' => $load->weight,
                'gross_rate' => $load->gross_rate,
                'distance_loaded' => $load->distance_loaded,
                'distance_deadhead' => $load->distance_deadhead,
                'estimated_driver_net' => $load->estimated_driver_net,
                'pickup_start' => $load->pickup_start?->toIso8601String(),
                'pickup_end' => $load->pickup_end?->toIso8601String(),
                'commodity' => $load->commodity,
                'broker_name' => $load->broker_name,
                'source_url' => $sourceUrl,
                'has_external_link' => !empty($sourceUrl),
            ];
        });

        return response()->json([
            'success' => true,
            'loads' => $result,
            'count' => $result->count(),
        ]);
    }

    public function loadSourcingShareExternal(Request $request)
    {
        $driver = $this->authDriver($request);
        if (!$driver) return response()->json(['error' => 'Unauthorized'], 401);

        $validated = $request->validate([
            'source_url' => 'required|url',
            'origin_city' => 'sometimes|nullable|string',
            'origin_state' => 'sometimes|nullable|string|max:2',
            'destination_city' => 'sometimes|nullable|string',
            'destination_state' => 'sometimes|nullable|string|max:2',
            'equipment_type' => 'sometimes|nullable|string',
            'weight' => 'sometimes|nullable|numeric',
            'gross_rate' => 'sometimes|nullable|numeric',
            'commodity' => 'sometimes|nullable|string',
            'broker_name' => 'sometimes|nullable|string',
            'broker_contact' => 'sometimes|nullable|string',
            'notes' => 'sometimes|nullable|string',
        ]);

        $service = new \App\Services\UrbanGoodz\LoadSource\LoadManualImportService();
        $result = $service->shareToUrbanGoodz($validated, $driver->id, 'driver');

        return response()->json($result);
    }
}
