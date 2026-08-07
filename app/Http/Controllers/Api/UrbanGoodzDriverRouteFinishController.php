<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzDedicatedRoute;
use App\Models\UrbanGoodzRoutePackage;
use App\Services\UrbanGoodz\DedicatedRouteOptimizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Lets a driver say where they want to finish, then sorts their stops.
 *
 * Drivers are paid per package, not per mile, so a driver choosing to end near
 * home costs the business nothing -- they absorb that mileage themselves. That
 * is why the finish point is the driver's to set here rather than the
 * dispatcher's.
 *
 * This deliberately calls DedicatedRouteOptimizationService, the same solver
 * the admin "Optimize" button uses. The driver app previously had its own
 * optimiser in UrbanGoodzDriverAIController::optimizeRoute, which took no
 * finish point, used a different distance model, and -- most importantly --
 * persisted nothing, so any order it produced was gone by the next request.
 */
class UrbanGoodzDriverRouteFinishController extends Controller
{
    /** Routes assigned to this driver that still need sorting. */
    public function index(Request $request): JsonResponse
    {
        $driver = $this->driver($request);

        if (!$driver) {
            return response()->json(['errors' => [['code' => 'auth', 'message' => 'Unauthorized']]], 401);
        }

        $routes = UrbanGoodzDedicatedRoute::where('assigned_driver_id', $driver->id)
            ->whereIn('status', ['active', 'assigned', 'pending'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (UrbanGoodzDedicatedRoute $r) => [
                'route_id' => $r->id,
                'route_name' => $r->route_name,
                'stops' => (int) $r->total_packages,
                'pickup' => $r->pickup_location,
                'optimization_status' => $r->optimization_status,
                'finish_set' => $r->return_to_origin || $r->end_lat !== null,
                'pay_per_package' => $r->driver_pay_per_package,
                'completion_bonus' => $r->route_completion_bonus,
            ]);

        return response()->json(['status' => 'success', 'total_size' => $routes->count(), 'routes' => $routes]);
    }

    /**
     * Set the finish point and sort the run.
     *
     *   mode=hub      finish back where the route started
     *   mode=address  finish at a coordinate the driver supplies
     *   mode=open     finish wherever the last stop falls
     */
    public function finish(Request $request, int $route, DedicatedRouteOptimizationService $optimizer): JsonResponse
    {
        $driver = $this->driver($request);

        if (!$driver) {
            return response()->json(['errors' => [['code' => 'auth', 'message' => 'Unauthorized']]], 401);
        }

        $validator = Validator::make($request->all(), [
            'mode' => 'required|in:hub,address,open',
            'end_lat' => 'required_if:mode,address|nullable|numeric|between:-90,90',
            'end_lng' => 'required_if:mode,address|nullable|numeric|between:-180,180',
            'end_label' => 'nullable|string|max:255',
        ], [
            'end_lat.required_if' => 'Choose a finish location on the map.',
            'end_lng.required_if' => 'Choose a finish location on the map.',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $model = UrbanGoodzDedicatedRoute::where('id', $route)
            ->where('assigned_driver_id', $driver->id)
            ->first();

        if (!$model) {
            // A route belonging to somebody else is not this driver's business.
            return response()->json(['status' => 'error', 'message' => 'Route not found.'], 404);
        }

        $mode = $request->input('mode');

        $model->update(match ($mode) {
            'hub' => [
                'return_to_origin' => true,
                'end_lat' => $model->pickup_lat,
                'end_lng' => $model->pickup_lng,
                'end_location' => $model->pickup_location,
            ],
            'address' => [
                'return_to_origin' => false,
                'end_lat' => $request->input('end_lat'),
                'end_lng' => $request->input('end_lng'),
                'end_location' => $request->input('end_label') ?: 'Driver chosen finish',
            ],
            'open' => [
                'return_to_origin' => false,
                'end_lat' => null,
                'end_lng' => null,
                'end_location' => null,
            ],
        });

        try {
            $result = $optimizer->optimize($model->fresh(), $mode === 'hub', 'driver', $driver->id);
        } catch (\Throwable $e) {
            // The optimiser's own messages are written for a human -- e.g.
            // "This route has no active stops to optimize" -- so pass it
            // through rather than replacing it with something vaguer.
            return response()->json([
                'status' => 'error',
                'code' => 'optimization_failed',
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'changed' => (bool) ($result['changed'] ?? false),
            'route' => $this->runSheet($model->fresh()),
        ]);
    }

    /** The sorted run, in the order the driver should actually drive it. */
    public function runSheet(UrbanGoodzDedicatedRoute $route): array
    {
        $stops = UrbanGoodzRoutePackage::where('dedicated_route_id', $route->id)
            ->orderBy('stop_order')
            ->get()
            ->map(fn (UrbanGoodzRoutePackage $p) => [
                'stop' => (int) $p->stop_order,
                'tracking_id' => $p->tracking_id,
                'name' => $p->dropoff_name,
                'address' => trim(implode(', ', array_filter([
                    $p->dropoff_address, $p->dropoff_city, $p->dropoff_state, $p->dropoff_zip,
                ]))),
                'lat' => $p->dropoff_lat,
                'lng' => $p->dropoff_lng,
                'status' => $p->status,
                'priority' => $p->priority,
            ]);

        $packages = $stops->count();
        $perPackage = (float) ($route->driver_pay_per_package ?? 0);

        return [
            'route_id' => $route->id,
            'route_name' => $route->route_name,
            'finish' => $route->return_to_origin
                ? 'hub'
                : ($route->end_lat !== null ? 'address' : 'open'),
            'finish_label' => $route->end_location,
            'optimization_status' => $route->optimization_status,
            'distance_miles' => $route->optimized_distance_miles,
            'duration_minutes' => $route->optimized_duration_minutes,
            'distance_saved_miles' => $route->original_distance_miles !== null && $route->optimized_distance_miles !== null
                ? round((float) $route->original_distance_miles - (float) $route->optimized_distance_miles, 2)
                : null,
            // Paid per package, so the driver's earnings do not move with the
            // route length. Surfaced here so the app can show it plainly.
            'pay' => [
                'model' => 'per_package',
                'per_package' => $perPackage,
                'packages' => $packages,
                'package_total' => round($perPackage * $packages, 2),
                'completion_bonus' => (float) ($route->route_completion_bonus ?? 0),
                'estimated_total' => round($perPackage * $packages + (float) ($route->route_completion_bonus ?? 0), 2),
            ],
            'stops' => $stops,
        ];
    }

    private function driver(Request $request)
    {
        return $request->user('delivery_men') ?? auth('delivery_men')->user();
    }
}
