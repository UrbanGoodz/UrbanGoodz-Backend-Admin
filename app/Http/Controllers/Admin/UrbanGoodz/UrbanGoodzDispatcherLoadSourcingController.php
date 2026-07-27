<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\DeliveryMan;
use App\Models\DispatcherSavedSearch;
use App\Models\DriverLoadPreference;
use App\Models\ExternalLoad;
use App\Models\LoadRecommendation;
use App\Models\LoadSource;
use App\Models\LoadPartnerReferral;
use App\Models\UrbanGoodzActivityLog;
use App\Services\UrbanGoodz\LoadSource\LoadSourcingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Dispatcher Load Sourcing.
 *
 * Two clearly separated surfaces:
 *  - WEB (routes/admin.php, `dispatcher-sourcing` prefix): every GET renders a Blade
 *    page inside the admin layout; every mutation redirects back with a flash message.
 *  - API (routes/api/v1/admin_dispatcher_sourcing.php, `api/v1/admin` prefix): the same
 *    data as structured JSON with explicit status codes.
 *
 * Both surfaces run behind the `admin` middleware, so an unauthenticated caller is
 * redirected to the admin login and a non-admin never reaches the controller.
 */
class UrbanGoodzDispatcherLoadSourcingController extends Controller
{
    // ═══════════════════════════════════════════════════════════════════
    // SHARED HELPERS
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Resolve the acting principal across both guards.
     *
     * The dispatcher-sourcing screens are reachable from the admin panel (admin guard)
     * and from the business/dispatcher portal (business guard). Previously every saved
     * search method assumed `auth('business')->user()` was non-null, which produced a
     * hard 500 whenever an admin opened the page.
     *
     * @return array{id:?int,type:string,company_id:?int}
     */
    private function actor(): array
    {
        if (auth('business')->check()) {
            $user = auth('business')->user();

            return [
                'id' => $user->id,
                'type' => 'business',
                'company_id' => $user->business_client_id,
            ];
        }

        if (auth('admin')->check()) {
            return [
                'id' => auth('admin')->id(),
                'type' => 'admin',
                // Set by the DispatchTerritoryScope middleware when the admin is
                // scoped to a single dispatch company; null means platform-wide.
                'company_id' => request()->attributes->get('dispatch_company_id'),
            ];
        }

        return ['id' => null, 'type' => 'guest', 'company_id' => null];
    }

    /**
     * Saved searches visible to the current actor.
     *
     * A business/dispatcher user only ever sees their own company's searches. A
     * platform admin with no company scope sees every search; a scoped admin sees
     * only that company's.
     */
    private function savedSearchQuery(): Builder
    {
        $actor = $this->actor();
        $query = DispatcherSavedSearch::query();

        if ($actor['company_id'] !== null) {
            return $query->where('dispatch_company_id', $actor['company_id']);
        }

        if ($actor['type'] === 'admin') {
            return $query;
        }

        // Unknown principal: return an intentionally empty set rather than leaking rows.
        return $query->whereRaw('1 = 0');
    }

    /** Drivers that may be offered sourced loads. */
    private function eligibleDriverQuery(): Builder
    {
        return DeliveryMan::where('load_board_eligible', true)->where('active', 1);
    }

    /** Reuse the existing Urban Goodz activity log rather than inventing a new one. */
    private function audit(string $event, string $description, ?string $loggableType = null, ?int $loggableId = null, array $metadata = []): void
    {
        try {
            $actor = $this->actor();

            UrbanGoodzActivityLog::create([
                'loggable_type' => $loggableType,
                'loggable_id' => $loggableId,
                'event' => $event,
                'description' => $description,
                'causer_type' => $actor['type'] === 'admin' ? \App\Models\Admin::class : null,
                'causer_id' => $actor['id'],
                'old_values' => [],
                'new_values' => [],
                'metadata' => array_merge($metadata, [
                    'actor_type' => $actor['type'],
                    'dispatch_company_id' => $actor['company_id'],
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]),
            ]);
        } catch (\Throwable $e) {
            // Auditing must never break the request it is describing.
            Log::warning('Dispatcher sourcing audit log failed: ' . $e->getMessage());
        }
    }

    /** The payload shared by the Blade dashboard and the JSON dashboard endpoint. */
    private function dashboardPayload(): array
    {
        $eligibleDrivers = $this->eligibleDriverQuery()->get();

        $availableLoads = ExternalLoad::where('status', 'available')
            ->where('is_duplicate', false)
            ->count();

        $savedSearchCount = $this->savedSearchQuery()->count();

        $assignmentCount = LoadRecommendation::where('generated_by_type', 'dispatcher')
            ->where('status', 'pending')
            ->count();

        $topRecommendations = LoadRecommendation::whereIn('delivery_man_id', $eligibleDrivers->pluck('id'))
            ->where('status', 'pending')
            ->with('externalLoad.source')
            ->orderByDesc('score')
            ->limit(20)
            ->get();

        return [
            'eligibleDrivers' => $eligibleDrivers,
            'availableLoads' => $availableLoads,
            'savedSearchCount' => $savedSearchCount,
            'assignmentCount' => $assignmentCount,
            'activeLoadCount' => $availableLoads,
            'topRecommendations' => $topRecommendations,
        ];
    }

    // ═══════════════════════════════════════════════════════════════════
    // WEB (BLADE) PAGES  — every one of these returns HTML
    // ═══════════════════════════════════════════════════════════════════

    public function dashboardBlade(): View
    {
        return view('admin-views.urban-goodz.dispatcher-sourcing.dashboard', $this->dashboardPayload());
    }

    public function searchBlade(Request $request): View
    {
        $searchResults = null;
        $eligibleDrivers = $this->eligibleDriverQuery()->get();
        $searchError = null;

        if ($request->isMethod('POST')) {
            $service = new LoadSourcingService();
            $criteria = $request->only([
                'origin_state', 'destination_state', 'equipment_type',
                'min_rate', 'max_deadhead', 'pickup_date_from', 'pickup_date_to',
                'weight_max', 'max_results',
            ]);

            try {
                $service->searchAllSources($criteria, $this->actor()['id'], 'dispatcher');
                $searchResults = ExternalLoad::with('source')
                    ->where('is_duplicate', false)
                    ->latest()
                    ->limit(100)
                    ->get();

                $this->audit('dispatcher_sourcing.search', 'Ran a dispatcher load search', null, null, ['criteria' => $criteria]);
            } catch (\Exception $e) {
                Log::warning('Dispatcher search error: ' . $e->getMessage());
                $searchError = translate('The load search could not be completed. Please try again.');
            }
        }

        return view('admin-views.urban-goodz.dispatcher-sourcing.search', compact(
            'searchResults', 'eligibleDrivers', 'searchError'
        ));
    }

    public function savedSearchesBlade(Request $request): View|RedirectResponse
    {
        if ($request->isMethod('POST')) {
            return $this->saveSearchWeb($request);
        }

        $savedSearches = $this->savedSearchQuery()->latest()->get();

        return view('admin-views.urban-goodz.dispatcher-sourcing.saved-searches', compact('savedSearches'));
    }

    public function assignmentsBlade(Request $request): View
    {
        $query = LoadRecommendation::where('generated_by_type', 'dispatcher')
            ->with('externalLoad.source', 'deliveryMan');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('externalLoad', function ($q) use ($search) {
                $q->where('origin_city', 'like', "%{$search}%")
                    ->orWhere('destination_city', 'like', "%{$search}%")
                    ->orWhere('external_id', 'like', "%{$search}%");
            });
        }

        $assignments = $query->latest()->paginate(25)->withQueryString();

        $statusCounts = LoadRecommendation::where('generated_by_type', 'dispatcher')
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        return view('admin-views.urban-goodz.dispatcher-sourcing.assignments', compact(
            'assignments', 'statusCounts'
        ));
    }

    public function driverMatchesBlade(?int $loadId = null): View
    {
        $load = $loadId ? ExternalLoad::with('source')->find($loadId) : null;
        $driverMatches = [];

        if ($load) {
            $eligibleDrivers = $this->eligibleDriverQuery()->get();

            $service = new LoadSourcingService();
            $weights = $service->getWeights();

            foreach ($eligibleDrivers as $driver) {
                $preferences = DriverLoadPreference::where('delivery_man_id', $driver->id)->first();
                if (!$service->isEligible($load, $driver, $preferences)) {
                    continue;
                }

                $scoreResult = $service->scoreLoad($load, $driver, $preferences, $weights);
                $activeDispatches = LoadRecommendation::where('delivery_man_id', $driver->id)
                    ->whereIn('status', ['pending', 'accepted'])
                    ->count();

                $driverMatches[] = [
                    'driver' => $driver,
                    'score' => $scoreResult['total_score'] ?? 0,
                    'reasons' => array_merge(
                        $scoreResult['reasons_recommended'] ?? [],
                        array_map(fn($r) => '-' . $r, $scoreResult['reasons_penalized'] ?? [])
                    ),
                    'active_dispatches' => $activeDispatches,
                ];
            }

            usort($driverMatches, fn($a, $b) => $b['score'] <=> $a['score']);
        }

        return view('admin-views.urban-goodz.dispatcher-sourcing.driver-matches', compact(
            'load', 'driverMatches'
        ));
    }

    // ═══════════════════════════════════════════════════════════════════
    // WEB MUTATIONS — browser form posts, so they redirect (never JSON)
    // ═══════════════════════════════════════════════════════════════════

    public function saveSearchWeb(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'criteria' => 'sometimes|array',
            'source_keys' => 'sometimes|array',
            'auto_alert' => 'sometimes|boolean',
            'alert_threshold_score' => 'sometimes|integer|min:0|max:100',
        ]);

        $search = $this->createSavedSearch($validated);

        $this->audit('dispatcher_sourcing.saved_search.created', "Saved search [{$search->name}] created", DispatcherSavedSearch::class, $search->id);

        return redirect()->route('admin.urban-goodz.dispatcher-sourcing.saved-searches')
            ->with('success', translate('Search saved successfully'));
    }

    public function runSavedSearchWeb(int $id): RedirectResponse
    {
        $search = $this->savedSearchQuery()->find($id);

        if (!$search) {
            return redirect()->route('admin.urban-goodz.dispatcher-sourcing.saved-searches')
                ->with('error', translate('Saved search not found'));
        }

        try {
            $result = $this->runSearch($search);
        } catch (\Throwable $e) {
            Log::warning('Dispatcher saved search run failed: ' . $e->getMessage());

            return redirect()->route('admin.urban-goodz.dispatcher-sourcing.saved-searches')
                ->with('error', translate('The saved search could not be run.'));
        }

        $this->audit('dispatcher_sourcing.saved_search.run', "Saved search [{$search->name}] executed", DispatcherSavedSearch::class, $search->id, ['loads_count' => $result['count'] ?? 0]);

        return redirect()->route('admin.urban-goodz.dispatcher-sourcing.saved-searches')
            ->with('success', translate('Search run complete') . ' — ' . ($result['count'] ?? 0) . ' ' . translate('loads found'));
    }

    public function deleteSavedSearchWeb(int $id): RedirectResponse
    {
        $search = $this->savedSearchQuery()->find($id);

        if (!$search) {
            return redirect()->route('admin.urban-goodz.dispatcher-sourcing.saved-searches')
                ->with('error', translate('Saved search not found'));
        }

        $name = $search->name;
        $search->delete();

        $this->audit('dispatcher_sourcing.saved_search.deleted', "Saved search [{$name}] deleted", DispatcherSavedSearch::class, $id);

        return redirect()->route('admin.urban-goodz.dispatcher-sourcing.saved-searches')
            ->with('success', translate('Saved search deleted'));
    }

    public function assignLoadToDriverWeb(Request $request, int $externalLoadId): RedirectResponse
    {
        $validated = $request->validate([
            'driver_id' => 'required|exists:delivery_men,id',
        ]);

        $load = ExternalLoad::find($externalLoadId);
        $driver = DeliveryMan::find($validated['driver_id']);

        if (!$load || !$driver) {
            return back()->with('error', translate('Load or driver not found'));
        }

        $outcome = $this->createAssignment($load, $driver);

        if (!$outcome['ok']) {
            return back()->with('error', $outcome['message']);
        }

        return back()->with('success', translate('Load assigned successfully') . ' — ' . translate('score') . ': ' . $outcome['score']);
    }

    /** Cancel a pending dispatcher assignment (recommendation). */
    public function cancelAssignmentWeb(int $recommendationId): RedirectResponse
    {
        $recommendation = LoadRecommendation::where('generated_by_type', 'dispatcher')->find($recommendationId);

        if (!$recommendation) {
            return back()->with('error', translate('Assignment not found'));
        }

        $from = $recommendation->status;
        $recommendation->update(['status' => 'cancelled']);

        $this->audit('dispatcher_sourcing.assignment.cancelled', "Assignment #{$recommendationId} cancelled", LoadRecommendation::class, $recommendationId, ['from' => $from, 'to' => 'cancelled']);

        return back()->with('success', translate('Assignment cancelled'));
    }

    /** Hand the dispatcher off to the broker's own site, recording the referral first. */
    public function openExternalLoadWeb(int $externalLoadId): RedirectResponse
    {
        $load = ExternalLoad::with('source')->find($externalLoadId);

        if (!$load) {
            return back()->with('error', translate('Load not found'));
        }

        $externalUrl = $this->resolveExternalUrl($load);

        if (!$externalUrl) {
            return back()->with('error', translate('No external URL available for this load'));
        }

        $this->recordHandoff($load, $externalUrl);

        return redirect()->away($externalUrl);
    }

    // ═══════════════════════════════════════════════════════════════════
    // JSON API  — mounted under api/v1/admin, never on a browser-facing GET
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Dashboard counters as JSON.
     *
     * This is the payload that used to be served from the browser-facing
     * `GET /admin/urban-goodz/dispatcher-sourcing` URL. It now lives only on the API.
     */
    public function apiDashboard(): JsonResponse
    {
        $payload = $this->dashboardPayload();

        return response()->json([
            'eligible_drivers' => $payload['eligibleDrivers']->count(),
            'available_loads' => $payload['availableLoads'],
            'saved_searches' => $payload['savedSearchCount'],
            'top_recommendations' => $payload['topRecommendations'],
        ]);
    }

    public function searchAllSources(Request $request): JsonResponse
    {
        $criteria = $request->only([
            'origin_state', 'destination_state', 'equipment_type',
            'min_rate', 'max_deadhead', 'pickup_date_from', 'pickup_date_to',
            'weight_max', 'max_results',
        ]);

        try {
            $result = (new LoadSourcingService())->searchAllSources($criteria, $this->actor()['id'], 'dispatcher');
        } catch (\Throwable $e) {
            Log::warning('Dispatcher API search error: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Load search failed'], 502);
        }

        $this->audit('dispatcher_sourcing.api.search', 'Ran a dispatcher load search via API', null, null, ['criteria' => $criteria]);

        return response()->json([
            'success' => true,
            'loads_count' => $result['count'],
            'loads' => $result['loads'],
            'errors' => $result['errors'],
            'search_id' => $result['search_id'],
        ]);
    }

    public function bestLoads(Request $request): JsonResponse
    {
        $query = ExternalLoad::where('status', 'available')
            ->where('is_duplicate', false);

        $sortBy = $request->get('sort', 'gross_rate');
        $sortDir = $request->get('direction', 'desc');

        // Whitelist guards against SQL injection via ?sort=, and every entry below is a
        // real column on `external_loads` (there is no `rate_per_mile` column).
        $allowedSorts = ['gross_rate', 'rate_per_loaded_mile', 'distance_deadhead', 'estimated_driver_net', 'created_at'];
        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        if ($request->filled('origin_state')) {
            $query->where('origin_state', $request->origin_state);
        }
        if ($request->filled('destination_state')) {
            $query->where('destination_state', $request->destination_state);
        }

        return response()->json([
            'success' => true,
            'count' => $query->count(),
            'loads' => $query->limit(50)->get(),
        ]);
    }

    public function saveSearch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'criteria' => 'sometimes|array',
            'source_keys' => 'sometimes|array',
            'auto_alert' => 'sometimes|boolean',
            'alert_threshold_score' => 'sometimes|integer|min:0|max:100',
        ]);

        $search = $this->createSavedSearch($validated);

        $this->audit('dispatcher_sourcing.saved_search.created', "Saved search [{$search->name}] created via API", DispatcherSavedSearch::class, $search->id);

        return response()->json(['success' => true, 'search' => $search], 201);
    }

    public function savedSearches(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'saved_searches' => $this->savedSearchQuery()->latest()->get(),
        ]);
    }

    public function deleteSavedSearch(int $id): JsonResponse
    {
        $search = $this->savedSearchQuery()->find($id);

        if (!$search) {
            return response()->json(['success' => false, 'message' => 'Saved search not found'], 404);
        }

        $name = $search->name;
        $search->delete();

        $this->audit('dispatcher_sourcing.saved_search.deleted', "Saved search [{$name}] deleted via API", DispatcherSavedSearch::class, $id);

        return response()->json(['success' => true]);
    }

    public function runSavedSearch(int $id): JsonResponse
    {
        $search = $this->savedSearchQuery()->find($id);

        if (!$search) {
            return response()->json(['success' => false, 'message' => 'Saved search not found'], 404);
        }

        try {
            $result = $this->runSearch($search);
        } catch (\Throwable $e) {
            Log::warning('Dispatcher API saved search run failed: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Saved search execution failed'], 502);
        }

        return response()->json([
            'success' => true,
            'loads_count' => $result['count'],
            'loads' => $result['loads'],
            'search_id' => $result['search_id'],
        ]);
    }

    public function bestForDriver(int $driverId): JsonResponse
    {
        if (!DeliveryMan::find($driverId)) {
            return response()->json(['success' => false, 'message' => 'Driver not found'], 404);
        }

        return response()->json([
            'success' => true,
            'recommendations' => (new LoadSourcingService())->generateRecommendations($driverId, 20),
        ]);
    }

    public function assignLoadToDriver(Request $request, int $externalLoadId): JsonResponse
    {
        $validated = $request->validate([
            'driver_id' => 'required|exists:delivery_men,id',
        ]);

        $load = ExternalLoad::find($externalLoadId);
        $driver = DeliveryMan::find($validated['driver_id']);

        if (!$load || !$driver) {
            return response()->json(['success' => false, 'message' => 'Load or driver not found'], 404);
        }

        $outcome = $this->createAssignment($load, $driver);

        if (!$outcome['ok']) {
            return response()->json(['success' => false, 'message' => $outcome['message']], 422);
        }

        return response()->json([
            'success' => true,
            'score' => $outcome['score'],
            'estimated_net' => $outcome['estimated_net'],
        ], 201);
    }

    public function openExternalLoad(int $externalLoadId): JsonResponse
    {
        $load = ExternalLoad::with('source')->find($externalLoadId);

        if (!$load) {
            return response()->json(['success' => false, 'message' => 'Load not found'], 404);
        }

        $externalUrl = $this->resolveExternalUrl($load);

        if (!$externalUrl) {
            return response()->json(['success' => false, 'message' => 'No external URL available for this load'], 404);
        }

        $referral = $this->recordHandoff($load, $externalUrl);

        return response()->json([
            'success' => true,
            'external_url' => $externalUrl,
            'source_name' => $load->source->name ?? null,
            'referral_id' => $referral['referral']->id ?? null,
        ]);
    }

    public function confirmBooking(Request $request, int $referralId): JsonResponse
    {
        $validated = $request->validate([
            'booked' => 'required|boolean',
            'notes' => 'sometimes|nullable|string',
        ]);

        $result = (new LoadSourcingService())->recordBookingConfirmation(
            $referralId,
            $validated['booked'],
            $validated['notes'] ?? null
        );

        $this->audit('dispatcher_sourcing.booking.confirmed', "Booking confirmation recorded for referral #{$referralId}", LoadPartnerReferral::class, $referralId, ['booked' => $validated['booked']]);

        return response()->json(['success' => true, 'result' => $result]);
    }

    public function driverPreferences(Request $request, int $driverId): JsonResponse
    {
        if (!DeliveryMan::find($driverId)) {
            return response()->json(['success' => false, 'message' => 'Driver not found'], 404);
        }

        if ($request->isMethod('GET')) {
            return response()->json([
                'success' => true,
                'preferences' => DriverLoadPreference::where('delivery_man_id', $driverId)->first(),
            ]);
        }

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

        $prefs = DriverLoadPreference::updateOrCreate(
            ['delivery_man_id' => $driverId],
            $validated
        );

        $this->audit('dispatcher_sourcing.driver_preferences.updated', "Load preferences updated for driver #{$driverId}", DriverLoadPreference::class, $prefs->id);

        return response()->json(['success' => true, 'preferences' => $prefs]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // SHARED DOMAIN LOGIC (used by both surfaces)
    // ═══════════════════════════════════════════════════════════════════

    private function createSavedSearch(array $validated): DispatcherSavedSearch
    {
        $actor = $this->actor();

        return DispatcherSavedSearch::create([
            'business_client_user_id' => $actor['type'] === 'business' ? $actor['id'] : null,
            'dispatch_company_id' => $actor['company_id'],
            'name' => $validated['name'],
            'criteria' => $validated['criteria'] ?? null,
            'source_keys' => $validated['source_keys'] ?? null,
            'auto_alert' => $validated['auto_alert'] ?? false,
            'alert_threshold_score' => $validated['alert_threshold_score'] ?? 70,
        ]);
    }

    private function runSearch(DispatcherSavedSearch $search): array
    {
        $result = (new LoadSourcingService())->searchAllSources(
            $search->criteria ?? [],
            $this->actor()['id'],
            'dispatcher'
        );

        $search->update([
            'last_run_result_count' => $result['count'] ?? 0,
            'last_run_at' => now(),
        ]);

        return $result;
    }

    /**
     * @return array{ok:bool,message:?string,score:?float,estimated_net:?float}
     */
    private function createAssignment(ExternalLoad $load, DeliveryMan $driver): array
    {
        $service = new LoadSourcingService();
        $preferences = DriverLoadPreference::where('delivery_man_id', $driver->id)->first();

        if (!$service->isEligible($load, $driver, $preferences)) {
            return ['ok' => false, 'message' => translate('Driver is not eligible for this load'), 'score' => null, 'estimated_net' => null];
        }

        $scoreResult = $service->scoreLoad($load, $driver, $preferences, $service->getWeights());

        $recommendation = LoadRecommendation::create([
            'external_load_id' => $load->id,
            'delivery_man_id' => $driver->id,
            'generated_by' => $this->actor()['id'],
            'generated_by_type' => 'dispatcher',
            'score' => $scoreResult['total_score'],
            'confidence_level' => $scoreResult['confidence_level'],
            'estimated_driver_net' => $scoreResult['estimated_driver_net'],
            'net_per_total_mile' => $scoreResult['net_per_total_mile'],
            'deadhead_miles' => $load->distance_deadhead,
            'equipment_match' => $scoreResult['equipment_match'],
            'certification_match' => $scoreResult['certification_match'],
            'schedule_feasible' => $scoreResult['schedule_feasible'],
            'broker_risk' => $scoreResult['broker_risk'],
            'reasons_recommended' => $scoreResult['reasons_recommended'],
            'reasons_penalized' => $scoreResult['reasons_penalized'],
            'status' => 'pending',
            'expires_at' => now()->addHours(48),
        ]);

        $this->audit(
            'dispatcher_sourcing.assignment.created',
            "Load #{$load->id} assigned to driver #{$driver->id}",
            LoadRecommendation::class,
            $recommendation->id,
            ['external_load_id' => $load->id, 'delivery_man_id' => $driver->id, 'score' => $scoreResult['total_score']]
        );

        return [
            'ok' => true,
            'message' => null,
            'score' => $scoreResult['total_score'],
            'estimated_net' => $scoreResult['estimated_driver_net'],
        ];
    }

    private function resolveExternalUrl(ExternalLoad $load): ?string
    {
        return $load->source_url ?? $load->source->deep_link_template ?? null;
    }

    private function recordHandoff(ExternalLoad $load, string $externalUrl): array
    {
        $referral = (new LoadSourcingService())->recordExternalHandoff(
            $load->id,
            $load->source_id,
            $this->actor()['id'],
            'dispatcher',
            'open_source',
            $externalUrl
        );

        $this->audit('dispatcher_sourcing.external_handoff', "External load #{$load->id} opened at source", ExternalLoad::class, $load->id, ['url' => $externalUrl]);

        return $referral;
    }
}
