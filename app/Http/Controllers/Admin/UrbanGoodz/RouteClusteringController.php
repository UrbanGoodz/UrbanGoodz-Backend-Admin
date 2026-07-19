<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzDedicatedRoute;
use App\Models\UrbanGoodzManifest;
use App\Models\UrbanGoodzRoutePackage;
use App\Models\UrbanGoodzRouteClusteringAudit;
use App\Services\UrbanGoodz\Routing\Services\RoutePlanningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RouteClusteringController extends Controller
{
    private RoutePlanningService $planning;

    public function __construct(RoutePlanningService $planning)
    {
        $this->planning = $planning;
    }

    public function clusterFromManifest(Request $request, int $manifestId)
    {
        $manifest = UrbanGoodzManifest::with('packages')->findOrFail($manifestId);

        $packages = $manifest->packages()->whereIn('package_status', [
            'pending', 'pending_review', 'ready_for_route',
        ])->get();

        if ($packages->isEmpty()) {
            return response()->json(['error' => 'No routable packages found in manifest'], 422);
        }

        $params = $this->validateParams($request);
        $params['business_client_id'] = $manifest->business_client_id;
        $params['planning_uuid'] = (string)\Illuminate\Support\Str::uuid();

        $result = $this->planning->planFromManifest($manifestId, $params);

        $manifest->update(['status' => 'grouped']);

        return response()->json([
            'success' => true,
            'manifest_id' => $manifest->id,
            'planning_uuid' => $params['planning_uuid'],
            'audit_id' => $result->auditId,
            'total_packages' => $result->totalPackages,
            'routed_packages' => $result->routedPackages,
            'unrouteable_count' => $result->unrouteableCount,
            'route_count_requested' => $result->routeCountRequested,
            'route_count_generated' => $result->routeCountGenerated,
            'unique_stop_count' => $result->uniqueStopCount,
            'overall_distance_mode' => $result->overallDistanceMode,
            'algorithm_version' => $result->algorithmVersion,
            'metrics' => $result->metrics->toArray(),
            'clusters' => array_map(fn($c) => $c->toSummaryArray(), $result->clusters),
            'unrouteable' => $result->unrouteable,
            'same_address_groups' => $result->sameAddressGroups,
            'violations' => $result->overallViolations,
            'warnings' => $result->warnings,
        ]);
    }

    public function clusterFromPool(Request $request, int $clientId)
    {
        $packages = UrbanGoodzRoutePackage::where('business_client_id', $clientId)
            ->whereNull('dedicated_route_id')
            ->whereIn('package_status', ['pending', 'pending_review', 'ready_for_route'])
            ->get();

        if ($packages->isEmpty()) {
            return response()->json(['error' => 'No unrouted packages found for this client'], 422);
        }

        $params = $this->validateParams($request);
        $params['business_client_id'] = $clientId;

        $result = $this->planning->planFromPool($packages->toArray(), $params);

        return response()->json([
            'success' => true,
            'total_packages' => $result->totalPackages,
            'routed_packages' => $result->routedPackages,
            'unrouteable_count' => $result->unrouteableCount,
            'route_count_requested' => $result->routeCountRequested,
            'route_count_generated' => $result->routeCountGenerated,
            'overall_distance_mode' => $result->overallDistanceMode,
            'metrics' => $result->metrics->toArray(),
            'clusters' => array_map(fn($c) => $c->toSummaryArray(), $result->clusters),
            'unrouteable' => $result->unrouteable,
            'same_address_groups' => $result->sameAddressGroups,
            'violations' => $result->overallViolations,
            'warnings' => $result->warnings,
        ]);
    }

    public function createRoutesFromAudit(int $auditId, Request $request)
    {
        $audit = UrbanGoodzRouteClusteringAudit::findOrFail($auditId);

        if ($audit->status !== 'pending_review') {
            return response()->json([
                'error' => 'Audit must be in pending_review status to create routes',
                'current_status' => $audit->status,
            ], 422);
        }

        $plan = json_decode($audit->optimized_plan, true);
        $clusters = $plan['clusters'] ?? [];

        $routes = [];
        foreach ($clusters as $cluster) {
            $route = UrbanGoodzDedicatedRoute::create([
                'business_client_id' => $audit->business_client_id,
                'manifest_id' => $audit->manifest_id,
                'route_name' => "Route {$cluster['label']}",
                'route_label' => $cluster['label'],
                'total_packages' => $cluster['package_count'],
                'estimated_miles' => $cluster['estimated_miles'],
                'estimated_duration' => "{$cluster['estimated_duration_minutes']} min",
                'scheduled_date' => $request->input('scheduled_date', now()->toDateString()),
                'route_type' => $request->input('route_type', 'bulk_delivery'),
                'driver_pay_per_package' => $request->input('driver_pay_per_package', 5.00),
                'business_charge_per_package' => $request->input('business_charge_per_package', 8.00),
                'status' => 'planned',
                'created_by' => auth()->id(),
            ]);

            $routes[] = [
                'id' => $route->id,
                'label' => $cluster['label'],
                'package_count' => $cluster['package_count'],
                'estimated_miles' => $cluster['estimated_miles'],
            ];
        }

        $audit->update(['status' => 'applied']);

        if ($audit->manifest_id) {
            UrbanGoodzManifest::where('id', $audit->manifest_id)->update([
                'generated_routes_count' => count($routes),
                'status' => 'approved',
            ]);
        }

        return response()->json([
            'success' => true,
            'audit_id' => $audit->id,
            'routes_created' => count($routes),
            'routes' => $routes,
        ]);
    }

    public function recalculateRoute(int $routeId)
    {
        $route = UrbanGoodzDedicatedRoute::with('packages')->findOrFail($routeId);
        $packages = $route->packages()->get();

        if ($packages->isEmpty()) {
            return response()->json(['error' => 'No packages on route'], 422);
        }

        $result = $this->planning->planFromPool(
            $packages->toArray(),
            ['requested_route_count' => 1]
        );

        return response()->json([
            'success' => true,
            'route_id' => $routeId,
            'total_packages' => $result->totalPackages,
            'routed_packages' => $result->routedPackages,
            'unrouteable_count' => $result->unrouteableCount,
            'overall_distance_mode' => $result->overallDistanceMode,
            'clusters' => array_map(fn($c) => $c->toSummaryArray(), $result->clusters),
            'metrics' => $result->metrics->toArray(),
        ]);
    }

    public function unrouteable(int $clientId)
    {
        $unrouteable = UrbanGoodzRoutePackage::where('business_client_id', $clientId)
            ->whereNull('dedicated_route_id')
            ->where('package_status', 'admin_review')
            ->get();

        return response()->json([
            'unrouteable' => $unrouteable->map(fn($p) => [
                'id' => $p->id,
                'tracking_id' => $p->tracking_id,
                'dropoff_address' => $p->dropoff_address,
                'dropoff_city' => $p->dropoff_city,
                'dropoff_state' => $p->dropoff_state,
                'priority' => $p->priority,
                'notes' => $p->notes,
            ]),
            'count' => $unrouteable->count(),
        ]);
    }

    public function reassignPackage(Request $request, int $packageId)
    {
        $request->validate([
            'route_id' => 'required|exists:urban_goodz_dedicated_routes,id',
        ]);

        $package = UrbanGoodzRoutePackage::findOrFail($packageId);
        $newRoute = UrbanGoodzDedicatedRoute::findOrFail($request->input('route_id'));

        $oldRouteId = $package->dedicated_route_id;

        $package->update([
            'dedicated_route_id' => $newRoute->id,
            'stop_order' => $newRoute->packages()->max('stop_order') + 1,
            'package_status' => 'assigned',
        ]);

        $newRoute->increment('total_packages');

        if ($oldRouteId) {
            UrbanGoodzDedicatedRoute::where('id', $oldRouteId)->decrement('total_packages');
        }

        return response()->json([
            'success' => true,
            'package_id' => $packageId,
            'old_route_id' => $oldRouteId,
            'new_route_id' => $newRoute->id,
        ]);
    }

    public function auditHistory(int $clientId)
    {
        $audits = UrbanGoodzRouteClusteringAudit::where('business_client_id', $clientId)
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($audits);
    }

    public function auditDetail(int $auditId)
    {
        $audit = UrbanGoodzRouteClusteringAudit::findOrFail($auditId);

        return response()->json([
            'id' => $audit->id,
            'business_client_id' => $audit->business_client_id,
            'manifest_id' => $audit->manifest_id,
            'planning_uuid' => $audit->planning_uuid,
            'clustering_params' => json_decode($audit->clustering_params, true),
            'original_plan' => json_decode($audit->original_plan, true),
            'optimized_plan' => json_decode($audit->optimized_plan, true),
            'unrouteable_packages' => json_decode($audit->unrouteable_packages, true),
            'algorithm' => $audit->algorithm,
            'distance_mode' => $audit->distance_mode,
            'metrics' => json_decode($audit->metrics ?? '{}', true),
            'status' => $audit->status,
            'admin_notes' => $audit->admin_notes,
            'created_at' => $audit->created_at,
        ]);
    }

    public function reviewAudit(int $auditId, Request $request)
    {
        $audit = UrbanGoodzRouteClusteringAudit::findOrFail($auditId);

        $audit->update([
            'status' => 'reviewed',
            'admin_notes' => $request->input('admin_notes', $audit->admin_notes),
        ]);

        return response()->json(['success' => true, 'status' => 'reviewed']);
    }

    private function validateParams(Request $request): array
    {
        return $request->validate([
            'requested_route_count' => 'nullable|integer|min:1|max:100',
            'target_packages_per_route' => 'nullable|integer|min:1|max:200',
            'maximum_packages_per_route' => 'nullable|integer|min:1|max:200',
            'maximum_stops_per_route' => 'nullable|integer|min:1|max:200',
            'preferred_cluster_radius_miles' => 'nullable|numeric|min:1|max:500',
            'maximum_cluster_radius_miles' => 'nullable|numeric|min:1|max:500',
            'maximum_route_miles' => 'nullable|numeric|min:1|max:2000',
            'maximum_route_duration_minutes' => 'nullable|integer|min:30|max:1440',
            'max_weight_lbs' => 'nullable|numeric|min:0',
            'max_volume_cubic_ft' => 'nullable|numeric|min:0',
            'respect_time_windows' => 'nullable|boolean',
            'preserve_locked_stops' => 'nullable|boolean',
            'preserve_priority_stops' => 'nullable|boolean',
            'return_to_origin' => 'nullable|boolean',
            'service_time_per_stop_minutes' => 'nullable|integer|min:1|max:60',
            'average_speed_mph' => 'nullable|numeric|min:1|max:100',
            'driver_shift_limit_hours' => 'nullable|integer|min:1|max:24',
            'vehicle_type' => 'nullable|string|max:50',
            'business_rules' => 'nullable|string|max:500',
        ]);
    }
}
