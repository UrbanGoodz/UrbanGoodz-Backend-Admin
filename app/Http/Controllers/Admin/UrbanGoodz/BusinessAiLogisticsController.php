<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\AiCopilotRecommendation;
use App\Models\AiDispatch;
use App\Models\DeliveryMan;
use App\Models\UrbanGoodzBusinessClient;
use App\Models\UrbanGoodzBusinessClientDocument;
use App\Models\UrbanGoodzBusinessClientUser;
use App\Models\UrbanGoodzClientInvoice;
use App\Models\UrbanGoodzDedicatedRoute;
use App\Models\UrbanGoodzLoadBoardLoad;
use App\Models\UrbanGoodzManifest;
use App\Models\UrbanGoodzRoutePackage;
use App\Models\UrbanGoodzRouteBatch;
use App\Services\AiCopilotService;
use App\Services\UrbanGoodz\BusinessClientAIService;
use App\CentralLogics\Helpers;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BusinessAiLogisticsController extends Controller
{
    private AiCopilotService $copilotService;
    private BusinessClientAIService $businessAI;

    public function __construct(AiCopilotService $copilotService, BusinessClientAIService $businessAI)
    {
        $this->copilotService = $copilotService;
        $this->businessAI = $businessAI;
    }

    // ─── AUTH / TENANT HELPERS ──────────────────────────────────────────

    protected function getClientId(): int
    {
        return auth('business')->user()->business_client_id;
    }

    protected function getClient(): UrbanGoodzBusinessClient
    {
        return UrbanGoodzBusinessClient::findOrFail($this->getClientId());
    }

    protected function checkPermission(string $permission): bool
    {
        $user = auth('business')->user();
        if (!$user) return false;
        if ($user->role === 'owner_admin') return true;
        $perms = $user->permissions ?? [];
        return in_array($permission, $perms);
    }

    protected function requirePermission(string $permission)
    {
        if (!$this->checkPermission($permission)) {
            abort(403, translate('messages.access_denied'));
        }
    }

    protected function logAction(string $actionType, string $description, array $metadata = []): void
    {
        try {
            DB::table('ai_action_logs')->insert([
                'action_type' => $actionType,
                'description' => $description,
                'admin_id' => null,
                'business_client_id' => $this->getClientId(),
                'business_user_id' => auth('business')->id(),
                'metadata' => json_encode($metadata),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::warning('AI action log failed: ' . $e->getMessage());
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // COMMAND CENTER
    // ═══════════════════════════════════════════════════════════════════

    public function commandCenter()
    {
        $this->requirePermission('ai_logistics');
        $clientId = $this->getClientId();

        $data = [
            'client' => $this->getClient(),

            // KPIs
            'total_loads' => UrbanGoodzLoadBoardLoad::where('business_client_id', $clientId)->count(),
            'active_loads' => UrbanGoodzLoadBoardLoad::where('business_client_id', $clientId)
                ->whereIn('status', ['open', 'assigned', 'in_transit'])->count(),
            'pool_packages' => UrbanGoodzRoutePackage::where('business_client_id', $clientId)
                ->whereIn('status', ['pending', 'queued', 'awaiting_assignment'])->count(),
            'active_routes' => UrbanGoodzDedicatedRoute::where('business_client_id', $clientId)
                ->whereIn('status', ['active', 'in_progress'])->count(),
            'available_drivers' => DeliveryMan::where('business_client_id', $clientId)
                ->where('active', 1)->where('application_status', 'approved')->count(),
            'pending_dispatches' => AiDispatch::forClient($clientId)->pending()->count(),
            'pending_exceptions' => UrbanGoodzRoutePackage::where('business_client_id', $clientId)
                ->where('status', 'exception')->count(),
            'unpaid_invoices' => UrbanGoodzClientInvoice::where('business_client_id', $clientId)
                ->where('status', 'unpaid')->count(),

            // Recent AI recommendations
            'recent_recommendations' => AiCopilotRecommendation::where('business_client_id', $clientId)
                ->where('status', 'pending')
                ->latest()
                ->take(5)
                ->get(),

            // Recent dispatches
            'recent_dispatches' => AiDispatch::forClient($clientId)
                ->with(['driver', 'load'])
                ->latest()
                ->take(5)
                ->get(),

            // Expiring documents
            'expiring_docs' => UrbanGoodzBusinessClientDocument::where('business_client_id', $clientId)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now()->addDays(30))
                ->count(),
        ];

        return view('business.ai-logistics.command-center', $data);
    }

    // ═══════════════════════════════════════════════════════════════════
    // LOAD BOARD
    // ═══════════════════════════════════════════════════════════════════

    public function loadBoard(Request $request)
    {
        $this->requirePermission('ai_logistics');
        $clientId = $this->getClientId();

        $query = UrbanGoodzLoadBoardLoad::where('business_client_id', $clientId);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                  ->orWhere('origin_city', 'like', "%{$search}%")
                  ->orWhere('destination_city', 'like', "%{$search}%");
            });
        }

        $loads = $query->with('deliveryMan')->latest()->paginate(15);

        return view('business.ai-logistics.load-board.index', compact('loads'));
    }

    public function loadBoardCreate()
    {
        $this->requirePermission('ai_logistics');
        $clientId = $this->getClientId();

        $locations = \App\Models\UrbanGoodzBusinessClientLocation::where('business_client_id', $clientId)
            ->where('is_active', 1)->get();
        $drivers = DeliveryMan::where('business_client_id', $clientId)
            ->where('active', 1)->where('application_status', 'approved')->get();

        return view('business.ai-logistics.load-board.create', compact('locations', 'drivers'));
    }

    public function loadBoardStore(Request $request)
    {
        $this->requirePermission('ai_logistics');
        $clientId = $this->getClientId();

        $data = $request->validate([
            'origin_address' => 'required|string|max:255',
            'origin_city' => 'required|string|max:100',
            'origin_state' => 'required|string|max:2',
            'destination_address' => 'required|string|max:255',
            'destination_city' => 'required|string|max:100',
            'destination_state' => 'required|string|max:2',
            'pickup_date' => 'required|date',
            'delivery_date' => 'required|date|after_or_equal:pickup_date',
            'vehicle_type' => 'required|string',
            'weight_lbs' => 'nullable|numeric|min:0',
            'distance_miles' => 'nullable|numeric|min:0',
            'rate' => 'nullable|numeric|min:0',
            'description' => 'nullable|string|max:1000',
            'special_instructions' => 'nullable|string|max:1000',
        ]);

        $load = UrbanGoodzLoadBoardLoad::create(array_merge($data, [
            'business_client_id' => $clientId,
            'reference_number' => 'LB-' . strtoupper(substr(md5(uniqid()), 0, 8)),
            'status' => 'open',
            'posted_by' => auth('business')->id(),
            'posted_at' => now(),
        ]));

        $this->logAction('load_board_create', "Created load board posting {$load->reference_number}", [
            'load_id' => $load->id,
        ]);

        Toastr::success(translate('Load posted successfully'));
        return redirect()->route('business.ai-logistics.load-board.index');
    }

    public function loadBoardShow($id)
    {
        $this->requirePermission('ai_logistics');
        $clientId = $this->getClientId();

        $load = UrbanGoodzLoadBoardLoad::where('business_client_id', $clientId)
            ->with(['deliveryMan', 'bids'])
            ->findOrFail($id);

        $availableDrivers = DeliveryMan::where('business_client_id', $clientId)
            ->where('active', 1)->where('application_status', 'approved')->get();

        $dispatches = AiDispatch::where('load_id', $id)
            ->forClient($clientId)
            ->with('driver')
            ->latest()
            ->get();

        return view('business.ai-logistics.load-board.show', compact('load', 'availableDrivers', 'dispatches'));
    }

    // ═══════════════════════════════════════════════════════════════════
    // LOAD SOURCING
    // ═══════════════════════════════════════════════════════════════════

    public function loadSourcing(Request $request)
    {
        $this->requirePermission('ai_logistics');
        $clientId = $this->getClientId();

        $sources = DB::table('load_sourcing_sources')
            ->where('business_client_id', $clientId)
            ->orWhereNull('business_client_id')
            ->get();

        $externalLoads = DB::table('external_loads')
            ->where('business_client_id', $clientId)
            ->latest()
            ->paginate(15);

        $availableCount = DB::table('external_loads')
            ->where('business_client_id', $clientId)
            ->where('status', 'available')
            ->count();

        $fleetMatchCount = DB::table('external_loads')
            ->where('business_client_id', $clientId)
            ->where('fleet_match', true)
            ->count();

        $savedSearchCount = \App\Models\DispatcherSavedSearch::where('dispatch_company_id', $clientId)->count();

        $activeDispatchCount = \App\Models\AiDispatch::forClient($clientId)
            ->whereIn('status', ['pending', 'accepted', 'in_progress'])
            ->count();

        return view('business.ai-logistics.load-sourcing.index', compact(
            'sources', 'externalLoads', 'availableCount',
            'fleetMatchCount', 'savedSearchCount', 'activeDispatchCount'
        ));
    }

    public function loadSourcingSearch(Request $request)
    {
        $this->requirePermission('ai_logistics');
        $clientId = $this->getClientId();

        $criteria = $request->validate([
            'origin_city' => 'nullable|string',
            'origin_state' => 'nullable|string',
            'destination_city' => 'nullable|string',
            'destination_state' => 'nullable|string',
            'vehicle_type' => 'nullable|string',
            'min_rate' => 'nullable|numeric',
            'max_distance' => 'nullable|numeric',
        ]);

        $results = $this->businessAI->searchExternalLoads($criteria, [
            'client_id' => $clientId,
        ]);

        $this->logAction('load_sourcing_search', 'Searched external load sources', $criteria);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'results' => $results]);
        }

        $searchResults = collect($results['loads'] ?? []);

        return view('business.ai-logistics.load-sourcing.search', compact('searchResults'));
    }

    // ═══════════════════════════════════════════════════════════════════
    // DYNAMIC PRICING
    // ═══════════════════════════════════════════════════════════════════

    public function dynamicPricing(Request $request)
    {
        $this->requirePermission('ai_logistics');
        $clientId = $this->getClientId();

        $recentLoads = UrbanGoodzLoadBoardLoad::where('business_client_id', $clientId)
            ->whereNotNull('rate')
            ->latest()
            ->take(50)
            ->get();

        $laneAnalysis = [];
        $grouped = $recentLoads->groupBy(function ($load) {
            return ($load->origin_state ?? '?') . ' → ' . ($load->destination_state ?? '?');
        });
        foreach ($grouped as $lane => $loads) {
            $rates = $loads->pluck('rate')->filter();
            $laneAnalysis[] = [
                'lane' => $lane,
                'count' => $loads->count(),
                'avg_rate' => $rates->avg(),
                'min_rate' => $rates->min(),
                'max_rate' => $rates->max(),
                'avg_distance' => $loads->pluck('distance_miles')->filter()->avg(),
            ];
        }

        return view('business.ai-logistics.pricing.index', compact('laneAnalysis', 'recentLoads'));
    }

    public function dynamicPricingCalculate(Request $request)
    {
        $this->requirePermission('ai_logistics');
        $clientId = $this->getClientId();

        $data = $request->validate([
            'origin_state' => 'required|string|max:2',
            'destination_state' => 'required|string|max:2',
            'distance_miles' => 'required|numeric|min:1',
            'weight_lbs' => 'nullable|numeric|min:0',
            'vehicle_type' => 'required|string',
        ]);

        $historicalLoads = UrbanGoodzLoadBoardLoad::where('business_client_id', $clientId)
            ->where('origin_state', $data['origin_state'])
            ->where('destination_state', $data['destination_state'])
            ->whereNotNull('rate')
            ->latest()
            ->take(20)
            ->get();

        $avgRate = $historicalLoads->pluck('rate')->avg() ?: 0;
        $avgRatePerMile = $data['distance_miles'] > 0 && $historicalLoads->count() > 0
            ? $historicalLoads->avg(function ($l) { return $l->distance_miles > 0 ? $l->rate / $l->distance_miles : 0; })
            : 2.50;

        $recommended = round($avgRatePerMile * $data['distance_miles'], 2);
        $lowBound = round($recommended * 0.85, 2);
        $highBound = round($recommended * 1.15, 2);

        $this->logAction('dynamic_pricing_calculate', "Pricing calculated for {$data['origin_state']}→{$data['destination_state']}", [
            'recommended' => $recommended,
            'distance' => $data['distance_miles'],
        ]);

        return response()->json([
            'success' => true,
            'recommended_rate' => $recommended,
            'low_bound' => $lowBound,
            'high_bound' => $highBound,
            'rate_per_mile' => round($avgRatePerMile, 2),
            'based_on_count' => $historicalLoads->count(),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // DRIVER MATCHING & DISPATCH
    // ═══════════════════════════════════════════════════════════════════

    public function driverMatching(Request $request)
    {
        $this->requirePermission('ai_logistics');
        $clientId = $this->getClientId();

        $drivers = DeliveryMan::where('business_client_id', $clientId)
            ->where('active', 1)
            ->where('application_status', 'approved')
            ->get()
            ->map(function ($d) {
                $activeDispatches = AiDispatch::where('delivery_man_id', $d->id)
                    ->whereIn('status', ['pending', 'accepted'])->count();
                return [
                    'id' => $d->id,
                    'name' => $d->f_name . ' ' . $d->l_name,
                    'phone' => $d->phone,
                    'vehicle_type' => $d->vehicle_type ?? 'unknown',
                    'zone' => $d->zone_id,
                    'active_dispatches' => $activeDispatches,
                    'is_available' => $activeDispatches === 0,
                    'image' => $d->image,
                ];
            });

        $openLoads = UrbanGoodzLoadBoardLoad::where('business_client_id', $clientId)
            ->where('status', 'open')
            ->latest()
            ->get();

        return view('business.ai-logistics.driver-matching.index', compact('drivers', 'openLoads'));
    }

    public function driverMatchRoute(Request $request)
    {
        $this->requirePermission('ai_logistics');
        $clientId = $this->getClientId();

        $data = $request->validate([
            'load_id' => 'required|integer',
            'auto_dispatch' => 'nullable|boolean',
        ]);

        $load = UrbanGoodzLoadBoardLoad::where('business_client_id', $clientId)
            ->findOrFail($data['load_id']);

        $drivers = DeliveryMan::where('business_client_id', $clientId)
            ->where('active', 1)
            ->where('application_status', 'approved')
            ->get();

        $matchResult = $this->businessAI->matchDriverToRoute(
            ['load' => $load->toArray()],
            $drivers->map(fn($d) => [
                'id' => $d->id,
                'name' => $d->f_name . ' ' . $d->l_name,
                'vehicle_type' => $d->vehicle_type,
                'current_lat' => $d->current_lat,
                'current_lng' => $d->current_lng,
            ])->toArray()
        );

        $this->logAction('driver_match', "AI driver matching for load {$load->reference_number}", [
            'load_id' => $load->id,
            'result' => $matchResult,
        ]);

        return response()->json([
            'success' => true,
            'load_id' => $load->id,
            'rankings' => $matchResult['rankings'] ?? [],
            'recommended_driver_id' => $matchResult['recommended_driver_id'] ?? null,
        ]);
    }

    public function driverDispatchForm()
    {
        $this->requirePermission('ai_logistics');
        $clientId = $this->getClientId();

        $drivers = DeliveryMan::where('business_client_id', $clientId)
            ->where('active', 1)
            ->where('application_status', 'approved')
            ->get();

        $loads = UrbanGoodzLoadBoardLoad::where('business_client_id', $clientId)
            ->where('status', 'open')
            ->latest()
            ->get();

        return view('business.ai-logistics.dispatch.create', compact('drivers', 'loads'));
    }

    public function driverDispatch(Request $request)
    {
        $this->requirePermission('ai_logistics');
        $clientId = $this->getClientId();

        $data = $request->validate([
            'driver_id' => 'required|integer',
            'load_id' => 'required|integer',
            'dispatch_type' => 'nullable|string|in:ai_match,manual,copilot_rec',
            'ai_confidence' => 'nullable|numeric',
            'ai_reasoning' => 'nullable|string',
        ]);

        $driver = DeliveryMan::where('id', $data['driver_id'])
            ->where('business_client_id', $clientId)
            ->firstOrFail();

        $load = UrbanGoodzLoadBoardLoad::where('id', $data['load_id'])
            ->where('business_client_id', $clientId)
            ->firstOrFail();

        // Create dispatch record using new canonical AiDispatch model
        $payload = [
            'business_client_id' => $clientId,
            'delivery_man_id' => $driver->id,
            'load_id' => $load->id,
            'source_type' => 'business',
            'source_id' => auth('business')->id(),
            'created_by_type' => 'business_user',
            'created_by_id' => auth('business')->id(),
            'status' => AiDispatch::STATUS_APPROVED,
            'recommended_by_ai' => in_array($data['dispatch_type'] ?? '', ['ai_match', 'copilot_rec']),
            'ai_match_score' => $data['ai_confidence'] ?? null,
            'ai_reasoning_summary' => $data['ai_reasoning'] ?? null,
            'metadata' => ['dispatch_type' => $data['dispatch_type'] ?? 'manual'],
        ];

        $dispatch = app(\App\Services\UrbanGoodzAiDispatchService::class)->createAndSend($payload);

        $this->logAction('driver_dispatch', "Dispatched driver {$driver->f_name} {$driver->l_name} to load {$load->reference_number}", [
            'dispatch_id' => $dispatch->id,
            'driver_id' => $driver->id,
            'load_id' => $load->id,
        ]);

        Toastr::success(translate('Driver dispatched successfully'));
        return redirect()->route('business.ai-logistics.load-board.show', $load->id);
    }

    // ═══════════════════════════════════════════════════════════════════
    // ROUTE PLANNING & OPTIMIZATION
    // ═══════════════════════════════════════════════════════════════════

    public function routeRecommendations()
    {
        $this->requirePermission('ai_logistics');
        $clientId = $this->getClientId();

        $activeRoutes = UrbanGoodzDedicatedRoute::where('business_client_id', $clientId)
            ->with('packages')
            ->latest()
            ->paginate(15);

        $unassignedPackages = UrbanGoodzRoutePackage::where('business_client_id', $clientId)
            ->whereNull('dedicated_route_id')
            ->whereIn('status', ['pending', 'queued', 'awaiting_assignment'])
            ->get();

        return view('business.ai-logistics.route-planning.index', compact('activeRoutes', 'unassignedPackages'));
    }

    public function routeOptimize(Request $request)
    {
        $this->requirePermission('ai_logistics');
        $clientId = $this->getClientId();

        $data = $request->validate([
            'package_ids' => 'required|array|min:1',
            'package_ids.*' => 'integer',
            'vehicle_type' => 'required|string',
        ]);

        $packages = UrbanGoodzRoutePackage::whereIn('id', $data['package_ids'])
            ->where('business_client_id', $clientId)
            ->get();

        if ($packages->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No valid packages found'], 422);
        }

        $result = $this->businessAI->optimizeRoute($packages->toArray(), [
            'vehicle_type' => $data['vehicle_type'],
        ]);

        $this->logAction('route_optimize', "Optimized route for {$packages->count()} packages", [
            'package_count' => $packages->count(),
            'result' => $result,
        ]);

        return response()->json([
            'success' => true,
            'optimization' => $result,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // PACKAGE CLUSTERING
    // ═══════════════════════════════════════════════════════════════════

    public function packageClustering()
    {
        $this->requirePermission('ai_logistics');
        $clientId = $this->getClientId();

        $poolPackages = UrbanGoodzRoutePackage::where('business_client_id', $clientId)
            ->whereNull('dedicated_route_id')
            ->whereIn('status', ['pending', 'queued', 'awaiting_assignment'])
            ->with(['pickupLocation', 'deliveryLocation'])
            ->get();

        return view('business.ai-logistics.package-clustering.index', compact('poolPackages'));
    }

    public function packageCluster(Request $request)
    {
        $this->requirePermission('ai_logistics');
        $clientId = $this->getClientId();

        $data = $request->validate([
            'package_ids' => 'nullable|array',
            'max_stops_per_route' => 'nullable|integer|min:1|max:50',
            'max_route_distance' => 'nullable|numeric|min:1',
        ]);

        $query = UrbanGoodzRoutePackage::where('business_client_id', $clientId)
            ->whereNull('dedicated_route_id')
            ->whereIn('status', ['pending', 'queued', 'awaiting_assignment']);

        if (!empty($data['package_ids'])) {
            $query->whereIn('id', $data['package_ids']);
        }

        $packages = $query->get();

        $result = $this->businessAI->groupPackagesForRoutes($packages->toArray(), [
            'client_id' => $clientId,
            'max_stops_per_route' => $data['max_stops_per_route'] ?? 25,
            'max_route_distance' => $data['max_route_distance'] ?? 100,
        ]);

        $this->logAction('package_cluster', "Clustered {$packages->count()} packages", [
            'package_count' => $packages->count(),
            'groups_count' => count($result['groups'] ?? []),
        ]);

        return response()->json([
            'success' => true,
            'total_packages' => $packages->count(),
            'groups' => $result['groups'] ?? [],
            'unassigned' => $result['unassigned'] ?? [],
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // EXCEPTION MANAGEMENT
    // ═══════════════════════════════════════════════════════════════════

    public function exceptionManagement(Request $request)
    {
        $this->requirePermission('ai_logistics');
        $clientId = $this->getClientId();

        $exceptions = UrbanGoodzRoutePackage::where('business_client_id', $clientId)
            ->where('status', 'exception')
            ->with(['routeBatch', 'routeBatch.deliveryMan'])
            ->latest()
            ->paginate(15);

        $driverExceptions = DB::table('driver_exception_reports')
            ->where('business_client_id', $clientId)
            ->where('resolved', false)
            ->latest()
            ->get();

        return view('business.ai-logistics.exceptions.index', compact('exceptions', 'driverExceptions'));
    }

    public function exceptionResolve(Request $request, $id)
    {
        $this->requirePermission('ai_logistics');
        $clientId = $this->getClientId();

        $data = $request->validate([
            'resolution' => 'required|string|in:redeliver,return,refund,cancel,reassign',
            'notes' => 'nullable|string|max:500',
            'assign_driver_id' => 'nullable|integer',
        ]);

        $package = UrbanGoodzRoutePackage::where('business_client_id', $clientId)
            ->where('status', 'exception')
            ->findOrFail($id);

        $newStatus = match ($data['resolution']) {
            'redeliver' => 'pending',
            'return' => 'return_initiated',
            'refund' => 'refunded',
            'cancel' => 'cancelled',
            'reassign' => 'awaiting_assignment',
            default => 'pending',
        };

        $package->update([
            'status' => $newStatus,
            'exception_notes' => $data['notes'],
        ]);

        // If reassigning, dispatch to new driver
        if ($data['resolution'] === 'reassign' && $data['assign_driver_id']) {
            $driver = DeliveryMan::where('id', $data['assign_driver_id'])
                ->where('business_client_id', $clientId)
                ->first();
            if ($driver && $driver->fcm_token) {
                Helpers::send_push_notif_to_device($driver->fcm_token, [
                    'title' => translate('Exception Reassignment'),
                    'description' => translate('Package reassigned to you for re-delivery'),
                    'order_id' => '',
                    'type' => 'exception_reassign',
                    'package_id' => $package->id,
                ]);
            }
        }

        $this->logAction('exception_resolve', "Resolved exception for package #{$package->id}: {$data['resolution']}", [
            'package_id' => $package->id,
            'resolution' => $data['resolution'],
        ]);

        Toastr::success(translate('Exception resolved'));
        return redirect()->route('business.ai-logistics.exceptions.index');
    }

    // ═══════════════════════════════════════════════════════════════════
    // RETURN MANAGEMENT
    // ═══════════════════════════════════════════════════════════════════

    public function returnManagement(Request $request)
    {
        $this->requirePermission('ai_logistics');
        $clientId = $this->getClientId();

        $returns = UrbanGoodzRoutePackage::where('business_client_id', $clientId)
            ->whereIn('status', ['return_initiated', 'return_in_progress', 'returned'])
            ->with(['routeBatch', 'routeBatch.deliveryMan'])
            ->latest()
            ->paginate(15);

        return view('business.ai-logistics.returns.index', compact('returns'));
    }

    // ═══════════════════════════════════════════════════════════════════
    // COST & MARGIN ANALYSIS
    // ═══════════════════════════════════════════════════════════════════

    public function costAnalysis(Request $request)
    {
        $this->requirePermission('ai_logistics');
        $clientId = $this->getClientId();

        $dateFrom = $request->input('date_from', now()->subDays(30)->toDateString());
        $dateTo = $request->input('date_to', now()->toDateString());

        $invoices = UrbanGoodzClientInvoice::where('business_client_id', $clientId)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->get();

        $loads = UrbanGoodzLoadBoardLoad::where('business_client_id', $clientId)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->whereNotNull('rate')
            ->get();

        $totalRevenue = $loads->sum('rate');
        $totalCost = $invoices->sum('total_amount');
        $margin = $totalRevenue > 0 ? round(($totalRevenue - $totalCost) / $totalRevenue * 100, 1) : 0;

        $costByMonth = $invoices->groupBy(function ($inv) {
            return $inv->created_at->format('Y-m');
        })->map(fn($group) => $group->sum('total_amount'));

        return view('business.ai-logistics.cost-analysis.index', compact(
            'invoices', 'loads', 'totalRevenue', 'totalCost', 'margin',
            'costByMonth', 'dateFrom', 'dateTo'
        ));
    }

    // ═══════════════════════════════════════════════════════════════════
    // DOCUMENT / COMPLIANCE ALERTS
    // ═══════════════════════════════════════════════════════════════════

    public function documentAlerts()
    {
        $this->requirePermission('ai_logistics');
        $clientId = $this->getClientId();

        $expiringDocs = UrbanGoodzBusinessClientDocument::where('business_client_id', $clientId)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(60))
            ->orderBy('expires_at')
            ->get();

        $expiredDocs = $expiringDocs->filter(fn($d) => $d->expires_at->isPast());
        $warningDocs = $expiringDocs->filter(fn($d) => !$d->expires_at->isPast());

        return view('business.ai-logistics.document-alerts.index', compact('expiredDocs', 'warningDocs'));
    }

    // ═══════════════════════════════════════════════════════════════════
    // INVOICE INSIGHTS
    // ═══════════════════════════════════════════════════════════════════

    public function invoiceInsights(Request $request)
    {
        $this->requirePermission('ai_logistics');
        $clientId = $this->getClientId();

        $invoices = UrbanGoodzClientInvoice::where('business_client_id', $clientId)
            ->latest()
            ->paginate(15);

        $unpaidTotal = UrbanGoodzClientInvoice::where('business_client_id', $clientId)
            ->where('status', 'unpaid')
            ->sum('total_amount');

        $paidTotal = UrbanGoodzClientInvoice::where('business_client_id', $clientId)
            ->where('status', 'paid')
            ->sum('total_amount');

        $overdueCount = UrbanGoodzClientInvoice::where('business_client_id', $clientId)
            ->where('status', 'unpaid')
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->count();

        return view('business.ai-logistics.invoice-insights.index', compact(
            'invoices', 'unpaidTotal', 'paidTotal', 'overdueCount'
        ));
    }

    // ═══════════════════════════════════════════════════════════════════
    // DEMAND FORECAST
    // ═══════════════════════════════════════════════════════════════════

    public function demandForecast()
    {
        $this->requirePermission('ai_logistics');
        $clientId = $this->getClientId();

        // Historical data for forecasting
        $monthlyLoads = UrbanGoodzLoadBoardLoad::where('business_client_id', $clientId)
            ->where('created_at', '>=', now()->subMonths(12))
            ->get()
            ->groupBy(function ($load) {
                return $load->created_at->format('Y-m');
            })
            ->map(fn($group) => $group->count());

        $monthlyPackages = UrbanGoodzRoutePackage::where('business_client_id', $clientId)
            ->where('created_at', '>=', now()->subMonths(12))
            ->get()
            ->groupBy(function ($pkg) {
                return $pkg->created_at->format('Y-m');
            })
            ->map(fn($group) => $group->count());

        $weeklyTrend = UrbanGoodzLoadBoardLoad::where('business_client_id', $clientId)
            ->where('created_at', '>=', now()->subWeeks(8))
            ->get()
            ->groupBy(function ($load) {
                return $load->created_at->format('W');
            })
            ->map(fn($group) => $group->count());

        return view('business.ai-logistics.demand-forecast.index', compact(
            'monthlyLoads', 'monthlyPackages', 'weeklyTrend'
        ));
    }

    // ═══════════════════════════════════════════════════════════════════
    // AI COPILOT RECOMMENDATIONS
    // ═══════════════════════════════════════════════════════════════════

    public function copilotRecommendations(Request $request)
    {
        $this->requirePermission('ai_logistics');
        $clientId = $this->getClientId();

        $query = AiCopilotRecommendation::where('business_client_id', $clientId);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', 'pending');
        }

        if ($request->filled('type')) {
            $query->where('recommendation_type', $request->type);
        }

        $recommendations = $query->latest()->paginate(15);

        return view('business.ai-logistics.recommendations.index', compact('recommendations'));
    }

    public function copilotAccept(Request $request, $id)
    {
        $this->requirePermission('ai_logistics');
        $clientId = $this->getClientId();

        $rec = AiCopilotRecommendation::where('business_client_id', $clientId)->findOrFail($id);
        $adminId = auth('business')->id() ?? 1;

        $this->copilotService->accept($rec->id, $adminId, $request->admin_notes);

        $this->logAction('copilot_accept', "Accepted AI recommendation #{$rec->id}: {$rec->recommendation_type}", [
            'recommendation_id' => $rec->id,
            'type' => $rec->recommendation_type,
        ]);

        Toastr::success(translate('Recommendation accepted'));
        return redirect()->route('business.ai-logistics.recommendations.index');
    }

    public function copilotDismiss(Request $request, $id)
    {
        $this->requirePermission('ai_logistics');
        $clientId = $this->getClientId();

        $rec = AiCopilotRecommendation::where('business_client_id', $clientId)->findOrFail($id);
        $adminId = auth('business')->id() ?? 1;

        $this->copilotService->dismiss($rec->id, $adminId, $request->admin_notes);

        $this->logAction('copilot_dismiss', "Dismissed AI recommendation #{$rec->id}", [
            'recommendation_id' => $rec->id,
        ]);

        Toastr::info(translate('Recommendation dismissed'));
        return redirect()->route('business.ai-logistics.recommendations.index');
    }

    public function copilotSnooze(Request $request, $id)
    {
        $this->requirePermission('ai_logistics');
        $clientId = $this->getClientId();

        $rec = AiCopilotRecommendation::where('business_client_id', $clientId)->findOrFail($id);
        $adminId = auth('business')->id() ?? 1;

        $hours = $request->input('snooze_hours', 24);
        $this->copilotService->snooze($rec->id, $adminId, $hours);

        $this->logAction('copilot_snooze', "Snoozed AI recommendation #{$rec->id} for {$hours}h", [
            'recommendation_id' => $rec->id,
            'hours' => $hours,
        ]);

        Toastr::info(translate('Recommendation snoozed'));
        return redirect()->route('business.ai-logistics.recommendations.index');
    }

    // ═══════════════════════════════════════════════════════════════════
    // AUDIT LOG
    // ═══════════════════════════════════════════════════════════════════

    public function auditLog(Request $request)
    {
        $this->requirePermission('ai_logistics');
        $clientId = $this->getClientId();

        $query = DB::table('ai_action_logs')
            ->where('business_client_id', $clientId);

        if ($request->filled('action_type')) {
            $query->where('action_type', $request->action_type);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->latest()->paginate(25);

        $actionTypes = DB::table('ai_action_logs')
            ->where('business_client_id', $clientId)
            ->distinct()
            ->pluck('action_type');

        return view('business.ai-logistics.audit-log.index', compact('logs', 'actionTypes'));
    }

    // ═══════════════════════════════════════════════════════════════════
    // DISPATCH MANAGEMENT
    // ═══════════════════════════════════════════════════════════════════

    public function dispatches(Request $request)
    {
        $this->requirePermission('ai_logistics');
        $clientId = $this->getClientId();

        $query = AiDispatch::with(['deliveryMan', 'load', 'route'])
            ->where('business_client_id', $clientId);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('driver_id')) {
            $query->where('delivery_man_id', $request->driver_id);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('id', $s)
                  ->orWhereHas('load', fn($l) => $l->where('reference_number', 'like', "%{$s}%"));
            });
        }

        $dispatches = $query->latest()->paginate(20);
        $statuses = AiDispatch::$canonicalStatuses;
        $drivers = DeliveryMan::where('business_client_id', $clientId)
            ->where('active', 1)->where('application_status', 'approved')
            ->get(['id', 'f_name', 'l_name']);

        return view('business.ai-logistics.dispatches.index', compact('dispatches', 'statuses', 'drivers'));
    }

    public function dispatchShow($id)
    {
        $this->requirePermission('ai_logistics');
        $dispatch = AiDispatch::where('business_client_id', $this->getClientId())
            ->with(['deliveryMan', 'load', 'route', 'aiRecommendation'])->findOrFail($id);
        return view('business.ai-logistics.dispatches.show', compact('dispatch'));
    }

    public function dispatchCancel($id, Request $request)
    {
        $this->requirePermission('ai_logistics');
        $dispatch = AiDispatch::where('business_client_id', $this->getClientId())->findOrFail($id);
        $dispatch->cancelDispatch($request->input('reason', 'Cancelled by business'));
        $this->logAction('dispatch_cancel', "Cancelled dispatch #{$id}", ['dispatch_id' => $id]);
        Toastr::success(translate('Dispatch cancelled'));
        return redirect()->route('business.ai-logistics.dispatches.index');
    }

    public function dispatchResend($id)
    {
        $this->requirePermission('ai_logistics');
        $dispatch = AiDispatch::where('business_client_id', $this->getClientId())->findOrFail($id);
        $dispatch->resendToDriver();
        app(\App\Services\UrbanGoodzAiDispatchService::class)->pushToDriver($dispatch);
        $this->logAction('dispatch_resend', "Resent dispatch #{$id}", ['dispatch_id' => $id]);
        Toastr::success(translate('Dispatch resent to driver'));
        return redirect()->route('business.ai-logistics.dispatches.show', $id);
    }
}
