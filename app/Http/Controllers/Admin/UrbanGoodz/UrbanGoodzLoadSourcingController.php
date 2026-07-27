<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\DispatcherSavedSearch;
use App\Models\DriverLoadPreference;
use App\Models\ExternalLoad;
use App\Models\LoadEmailIngestion;
use App\Models\LoadImport;
use App\Models\LoadPartnerReferral;
use App\Models\LoadRecommendation;
use App\Models\LoadSource;
use App\Models\LoadSourceCredential;
use App\Models\LoadSourceError;
use App\Models\LoadSourceSearch;
use App\Models\LoadSourceSyncRun;
use App\Models\LoadSourcingSetting;
use App\Models\UrbanGoodzLoadBoardLoad;
use App\Services\UrbanGoodz\LoadSource\LoadEmailIngestionService;
use App\Services\UrbanGoodz\LoadSource\LoadManualImportService;
use App\Services\UrbanGoodz\LoadSource\LoadSourcingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UrbanGoodzLoadSourcingController extends Controller
{
    private const SUB_NAV = [
        'overview'     => 'admin.urban-goodz.load-sourcing.overview',
        'sources'      => 'admin.urban-goodz.load-sourcing.sources',
        'search'       => 'admin.urban-goodz.load-sourcing.search',
        'saved'        => 'admin.urban-goodz.load-sourcing.saved-searches',
        'sourced'      => 'admin.urban-goodz.load-sourcing.sourced-loads',
        'recommendations' => 'admin.urban-goodz.load-sourcing.recommendations',
        'sync'         => 'admin.urban-goodz.load-sourcing.sync-runs',
        'errors'       => 'admin.urban-goodz.load-sourcing.errors',
        'settings'     => 'admin.urban-goodz.load-sourcing.settings',
    ];

    private function subNav(string $active): array
    {
        $items = [
            ['label' => 'Overview',     'route' => self::SUB_NAV['overview'],     'key' => 'overview'],
            ['label' => 'Sources',       'route' => self::SUB_NAV['sources'],      'key' => 'sources'],
            ['label' => 'Search Loads',  'route' => self::SUB_NAV['search'],       'key' => 'search'],
            ['label' => 'Saved Searches','route' => self::SUB_NAV['saved'],        'key' => 'saved'],
            ['label' => 'Sourced Loads', 'route' => self::SUB_NAV['sourced'],      'key' => 'sourced'],
            ['label' => 'Recommendations','route' => self::SUB_NAV['recommendations'],'key' => 'recommendations'],
            ['label' => 'Sync Runs',     'route' => self::SUB_NAV['sync'],         'key' => 'sync'],
            ['label' => 'Errors',        'route' => self::SUB_NAV['errors'],       'key' => 'errors'],
            ['label' => 'Settings',      'route' => self::SUB_NAV['settings'],     'key' => 'settings'],
        ];
        return array_map(fn($item) => $item + ['active' => $item['key'] === $active], $items);
    }

    private function loadOverviewStats(): array
    {
        $el = ExternalLoad::class;
        return [
            'total_loads'    => $el::count(),
            'available'      => $el::where('status', 'available')->where('is_duplicate', false)->count(),
            'assigned'       => $el::where('status', 'assigned')->count(),
            'in_transit'     => $el::where('status', 'in_transit')->count(),
            'delivered'      => $el::where('status', 'delivered')->count(),
            'cancelled'      => $el::where('status', 'cancelled')->count(),
            'pending_review' => $el::where('status', 'pending_review')->count(),
            'total_payout'   => (float) $el::whereNotNull('gross_rate')->sum('gross_rate'),
            'avg_rate_per_mile' => (float) $el::where('rate_per_loaded_mile', '>', 0)->avg('rate_per_loaded_mile'),
            'unassigned_count' => $el::where('status', 'available')->where('is_duplicate', false)->count(),
            'by_state'       => $el::whereNotNull('origin_state')->where('is_duplicate', false)
                                    ->selectRaw('origin_state, count(*) as cnt')
                                    ->groupBy('origin_state')->orderByDesc('cnt')->pluck('cnt', 'origin_state')->toArray(),
            'by_equipment'   => $el::whereNotNull('equipment_type')->where('is_duplicate', false)
                                    ->selectRaw('equipment_type, count(*) as cnt')
                                    ->groupBy('equipment_type')->orderByDesc('cnt')->pluck('cnt', 'equipment_type')->toArray(),
        ];
    }

    // ────────────────────────────────────────────────────────
    // 9 BLADE PAGES
    // ────────────────────────────────────────────────────────

    public function overview()
    {
        $stats = $this->loadOverviewStats();
        $recentLoads = ExternalLoad::with('source')->latest()->limit(10)->get();
        return view('admin-views.urban-goodz.load-sourcing.overview', [
            'nav' => $this->subNav('overview'),
            'stats' => $stats,
            'recentLoads' => $recentLoads,
        ]);
    }

    public function sources()
    {
        $sources = LoadSource::withCount(['externalLoads', 'syncRuns', 'errors'])
            ->with(['credentials' => fn($q) => $q->select('source_id', 'credential_key', 'status', 'expires_at', 'last_validated_at')])
            ->orderBy('name')
            ->get();

        $sourceStats = $sources->map(function ($src) {
            $lastRun = $src->syncRuns()->latest()->first();
            $lastSuccess = $src->syncRuns()->where('status', 'completed')->latest()->first();
            return [
                'source' => $src,
                'credential_count' => $src->credentials->count(),
                'has_credentials' => $src->credentials->count() > 0,
                'all_active' => $src->credentials->every('status', 'active'),
                'last_sync_at' => $lastRun?->created_at,
                'last_success_at' => $lastSuccess?->completed_at,
                'records_imported' => $src->external_loads_count ?? 0,
                'error_count' => $src->errors_count ?? 0,
            ];
        });

        return view('admin-views.urban-goodz.load-sourcing.sources', [
            'nav' => $this->subNav('sources'),
            'sourceStats' => $sourceStats,
        ]);
    }

    public function search(Request $request)
    {
        $results = null;
        $searchId = null;
        $searchError = null;

        if ($request->isMethod('POST')) {
            $service = new LoadSourcingService();
            $criteria = $request->only([
                'origin_city', 'origin_state', 'origin_radius',
                'destination_city', 'destination_state', 'destination_radius',
                'pickup_date_from', 'pickup_date_to',
                'equipment_type', 'vehicle_type',
                'min_rate', 'min_rate_per_mile', 'max_deadhead',
                'weight', 'load_category', 'full_or_partial',
                'medical_compliance', 'preferred_sources',
                'exclude_missing_rate', 'exclude_stale', 'exclude_duplicates',
            ]);

            try {
                $result = $service->searchAllSources($criteria, auth('admin')->id(), 'admin');
                $searchId = $result['search_id'] ?? null;
                $results = ExternalLoad::with('source')
                    ->where('is_duplicate', false)
                    ->latest()
                    ->limit(100)
                    ->get();
            } catch (\Exception $e) {
                $searchError = $e->getMessage();
            }
        }

        $sources = LoadSource::where('enabled', true)->get();

        return view('admin-views.urban-goodz.load-sourcing.search', [
            'nav' => $this->subNav('search'),
            'results' => $results,
            'searchId' => $searchId,
            'searchError' => $searchError,
            'sources' => $sources,
        ]);
    }

    public function savedSearches(Request $request)
    {
        if ($request->isMethod('POST')) {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'criteria' => 'required|array',
                'source_keys' => 'sometimes|array',
                'auto_alert' => 'sometimes|boolean',
                'alert_threshold_score' => 'sometimes|integer|min:0|max:100',
            ]);

            DispatcherSavedSearch::create([
                'business_client_user_id' => auth('admin')->id(),
                'name' => $validated['name'],
                'criteria' => $validated['criteria'],
                'source_keys' => $validated['source_keys'] ?? [],
                'auto_alert' => $validated['auto_alert'] ?? false,
                'alert_threshold_score' => $validated['alert_threshold_score'] ?? 70,
            ]);

            return redirect()->route(self::SUB_NAV['saved'])->with('success', 'Search saved.');
        }

        if ($request->isMethod('DELETE') && $request->id) {
            DispatcherSavedSearch::findOrFail($request->id)->delete();
            return redirect()->route(self::SUB_NAV['saved'])->with('success', 'Search deleted.');
        }

        $searches = DispatcherSavedSearch::latest()->paginate(50);
        $sources = LoadSource::where('enabled', true)->get();

        return view('admin-views.urban-goodz.load-sourcing.saved-searches', [
            'nav' => $this->subNav('saved'),
            'searches' => $searches,
            'sources' => $sources,
        ]);
    }

    public function runSavedSearch(int $id)
    {
        $search = DispatcherSavedSearch::findOrFail($id);
        $service = new LoadSourcingService();

        $result = $service->searchAllSources(
            $search->criteria,
            auth('admin')->id(),
            'admin'
        );

        $search->update([
            'last_run_at' => now(),
            'last_run_result_count' => $result['count'] ?? 0,
        ]);

        return redirect()->route(self::SUB_NAV['sourced'])
            ->with('success', "Search '{$search->name}' ran. Found {$result['count']} loads.");
    }

    public function sourcedLoads(Request $request)
    {
        $query = ExternalLoad::with('source')
            ->where('is_duplicate', false);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('source_id')) {
            $query->where('source_id', $request->source_id);
        }
        if ($request->filled('equipment_type')) {
            $query->where('equipment_type', $request->equipment_type);
        }
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }

        $loads = $query->latest()->paginate(50);
        $sources = LoadSource::all();

        return view('admin-views.urban-goodz.load-sourcing.sourced-loads', [
            'nav' => $this->subNav('sourced'),
            'loads' => $loads,
            'sources' => $sources,
        ]);
    }

    public function recommendations(Request $request)
    {
        $query = LoadRecommendation::with(['externalLoad.source', 'driver']);

        if ($request->filled('driver_id')) {
            $query->where('delivery_man_id', $request->driver_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('min_score')) {
            $query->where('score', '>=', $request->min_score);
        }

        $recommendations = $query->orderByDesc('score')->paginate(50);

        return view('admin-views.urban-goodz.load-sourcing.recommendations', [
            'nav' => $this->subNav('recommendations'),
            'recommendations' => $recommendations,
        ]);
    }

    public function syncRuns(Request $request)
    {
        $query = LoadSourceSyncRun::with('source');

        if ($request->filled('source_id')) {
            $query->where('source_id', $request->source_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $runs = $query->latest()->paginate(50);
        $sources = LoadSource::all();

        return view('admin-views.urban-goodz.load-sourcing.sync-runs', [
            'nav' => $this->subNav('sync'),
            'runs' => $runs,
            'sources' => $sources,
        ]);
    }

    public function errors(Request $request)
    {
        $query = LoadSourceError::with('source', 'syncRun');

        if ($request->filled('source_id')) {
            $query->where('source_id', $request->source_id);
        }
        if ($request->filled('resolved')) {
            $query->where('resolved', $request->boolean('resolved'));
        }

        $errors = $query->latest()->paginate(50);
        $sources = LoadSource::all();

        return view('admin-views.urban-goodz.load-sourcing.errors', [
            'nav' => $this->subNav('errors'),
            'errors' => $errors,
            'sources' => $sources,
        ]);
    }

    public function settings(Request $request)
    {
        if ($request->isMethod('POST')) {
            $validated = $request->validate([
                'settings' => 'sometimes|array',
                'weights' => 'sometimes|array',
            ]);

            if (!empty($validated['settings'])) {
                foreach ($validated['settings'] as $key => $value) {
                    if (is_bool($value)) {
                        $type = 'boolean';
                    } elseif (is_int($value)) {
                        $type = 'integer';
                    } elseif (is_float($value)) {
                        $type = 'decimal';
                    } else {
                        $type = 'string';
                    }
                    LoadSourcingSetting::set($key, $value, $type);
                }
            }

            if (!empty($validated['weights'])) {
                $totalWeight = array_sum($validated['weights']);
                if ($totalWeight !== 100) {
                    return back()->withErrors(['weights' => "Weight total must equal 100, got {$totalWeight}"])->withInput();
                }
                LoadSourcingSetting::set('scoring_weights', $validated['weights'], 'json');
            }

            return redirect()->route(self::SUB_NAV['settings'])->with('success', 'Settings updated.');
        }

        $existing = [];
        foreach (LoadSourcingSetting::all() as $s) {
            $existing[$s->setting_key] = $s->setting_value;
        }

        return view('admin-views.urban-goodz.load-sourcing.settings', [
            'nav' => $this->subNav('settings'),
            'existing' => $existing,
        ]);
    }

    // ────────────────────────────────────────────────────────
    // JSON API ENDPOINTS (for AJAX)
    // ────────────────────────────────────────────────────────

    public function showSourceApi(int $id): JsonResponse
    {
        $source = LoadSource::withCount(['externalLoads', 'syncRuns', 'errors'])
            ->with(['syncRuns' => fn($q) => $q->latest()->limit(20)])
            ->findOrFail($id);

        return response()->json($source);
    }

    public function updateSource(Request $request, int $id): JsonResponse
    {
        $source = LoadSource::findOrFail($id);

        $validated = $request->validate([
            'enabled' => 'sometimes|boolean',
            'supports_bidding' => 'sometimes|boolean',
            'supports_booking' => 'sometimes|boolean',
            'rate_limit_per_minute' => 'sometimes|integer|min:1',
            'description' => 'sometimes|string',
            'deep_link_template' => 'sometimes|nullable|string',
        ]);

        $source->update($validated);

        return response()->json(['success' => true, 'source' => $source]);
    }

    public function toggleSource(Request $request, int $id): JsonResponse
    {
        $source = LoadSource::findOrFail($id);
        $source->update(['enabled' => !$source->enabled]);

        return response()->json(['success' => true, 'enabled' => $source->enabled]);
    }

    public function storeCredential(Request $request, int $id): JsonResponse
    {
        $source = LoadSource::findOrFail($id);

        $validated = $request->validate([
            'credential_key' => 'required|string',
            'credential_value' => 'required|string',
        ]);

        $source->setCredential($validated['credential_key'], $validated['credential_value']);

        $hasAllRequired = $this->checkSourceCredentials($source);
        if ($hasAllRequired) {
            $source->update(['api_status' => 'configured']);
        }

        return response()->json(['success' => true, 'api_status' => $source->api_status]);
    }

    public function testConnection(int $id): JsonResponse
    {
        $source = LoadSource::findOrFail($id);

        try {
            $service = new LoadSourcingService();
            $result = $service->searchSource($source->source_key, ['max_results' => 1], auth('admin')->id());

            $source->update([
                'api_status' => 'connected',
                'last_sync_at' => now(),
            ]);

            return response()->json(['success' => true, 'message' => 'Connection successful']);
        } catch (\Exception $e) {
            $source->update(['api_status' => 'error', 'last_error_at' => now(), 'last_error_message' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function syncSource(int $id): JsonResponse
    {
        $source = LoadSource::findOrFail($id);
        $service = new LoadSourcingService();

        try {
            $result = $service->searchSource($source->source_key, [], auth('admin')->id());

            return response()->json([
                'success' => true,
                'loads_found' => $result['count'] ?? 0,
                'duration_ms' => $result['duration_ms'] ?? 0,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function sourceSearchApi(Request $request, int $sourceId): JsonResponse
    {
        $source = LoadSource::findOrFail($sourceId);
        $service = new LoadSourcingService();

        $criteria = $request->only([
            'origin_state', 'destination_state', 'equipment_type',
            'min_rate', 'max_deadhead', 'pickup_date_from', 'pickup_date_to',
            'weight_max', 'max_results',
        ]);

        $result = $service->searchSource($source->source_key, $criteria, auth('admin')->id());

        return response()->json($result);
    }

    public function searchAllApi(Request $request): JsonResponse
    {
        $service = new LoadSourcingService();

        $criteria = $request->only([
            'origin_state', 'destination_state', 'equipment_type',
            'min_rate', 'max_deadhead', 'pickup_date_from', 'pickup_date_to',
            'weight_max', 'max_results',
        ]);

        $result = $service->searchAllSources($criteria, auth('admin')->id(), 'admin');

        return response()->json([
            'success' => true,
            'loads_count' => $result['count'],
            'errors' => $result['errors'],
            'search_id' => $result['search_id'],
            'duration_ms' => $result['duration_ms'],
        ]);
    }

    public function approveLoad(int $id)
    {
        $load = ExternalLoad::findOrFail($id);

        if ($load->status !== 'pending_review') {
            return back()->withErrors(['status' => 'Load must be in pending_review status.']);
        }

        $load->update([
            'status' => 'available',
            'approved_by' => auth('admin')->id(),
            'approved_by_type' => 'admin',
            'approved_at' => now(),
        ]);

        return back()->with('success', "Load #{$load->id} approved.");
    }

    public function rejectLoad(Request $request, int $id)
    {
        $load = ExternalLoad::findOrFail($id);

        $load->update(['status' => 'cancelled']);

        return back()->with('success', "Load #{$load->id} rejected.");
    }

    public function publishToLoadBoard(int $id)
    {
        $load = ExternalLoad::findOrFail($id);

        if ($load->status !== 'available') {
            return back()->withErrors(['status' => 'Load must be approved (available) before publishing.']);
        }

        $existing = UrbanGoodzLoadBoardLoad::where('fingerprint', $load->fingerprint)->first();
        if ($existing) {
            return back()->with('info', 'Load already published to Load Board.');
        }

        $boardLoad = UrbanGoodzLoadBoardLoad::create([
            'external_id' => $load->external_id,
            'provider' => $load->source->source_key ?? 'sourced',
            'source_id' => $load->source_id,
            'fingerprint' => $load->fingerprint,
            'load_number' => 'UGS-' . $load->id,
            'status' => 'available',
            'origin_name' => $load->origin_address,
            'origin_city' => $load->origin_city,
            'origin_state' => $load->origin_state,
            'origin_zip' => $load->origin_zip,
            'origin_lat' => $load->origin_lat,
            'origin_lng' => $load->origin_lng,
            'destination_name' => $load->destination_address,
            'destination_city' => $load->destination_city,
            'destination_state' => $load->destination_state,
            'destination_zip' => $load->destination_zip,
            'destination_lat' => $load->destination_lat,
            'destination_lng' => $load->destination_lng,
            'payout_amount' => $load->gross_rate,
            'rate_per_mile' => $load->rate_per_loaded_mile,
            'equipment_type' => $load->equipment_type,
            'weight' => $load->weight,
            'commodity' => $load->commodity,
            'pickup_start' => $load->pickup_start,
            'pickup_end' => $load->pickup_end,
            'delivery_start' => $load->delivery_start,
            'delivery_end' => $load->delivery_end,
            'distance_miles' => $load->distance_miles,
            'source_url' => $load->source_url,
            'raw_source_payload' => $load->raw_source_payload,
            'expires_at' => $load->expires_at,
        ]);

        $load->update(['status' => 'published']);

        return back()->with('success', "Load #{$load->id} published to Load Board as #{$boardLoad->id}.");
    }

    public function bulkApprove(Request $request)
    {
        $validated = $request->validate(['ids' => 'required|array']);
        $count = ExternalLoad::whereIn('id', $validated['ids'])
            ->where('status', 'pending_review')
            ->update([
                'status' => 'available',
                'approved_by' => auth('admin')->id(),
                'approved_by_type' => 'admin',
                'approved_at' => now(),
            ]);

        return back()->with('success', "{$count} loads approved.");
    }

    public function bulkReject(Request $request)
    {
        $validated = $request->validate(['ids' => 'required|array']);
        $count = ExternalLoad::whereIn('id', $validated['ids'])
            ->where('status', 'pending_review')
            ->update(['status' => 'cancelled']);

        return back()->with('success', "{$count} loads rejected.");
    }

    public function bulkPublish(Request $request)
    {
        $validated = $request->validate(['ids' => 'required|array']);
        $loads = ExternalLoad::whereIn('id', $validated['ids'])
            ->where('status', 'available')
            ->get();

        $published = 0;
        foreach ($loads as $load) {
            $exists = UrbanGoodzLoadBoardLoad::where('fingerprint', $load->fingerprint)->exists();
            if ($exists) continue;

            UrbanGoodzLoadBoardLoad::create([
                'external_id' => $load->external_id,
                'provider' => $load->source->source_key ?? 'sourced',
                'source_id' => $load->source_id,
                'fingerprint' => $load->fingerprint,
                'load_number' => 'UGS-' . $load->id,
                'status' => 'available',
                'origin_city' => $load->origin_city,
                'origin_state' => $load->origin_state,
                'origin_zip' => $load->origin_zip,
                'origin_lat' => $load->origin_lat,
                'origin_lng' => $load->origin_lng,
                'destination_city' => $load->destination_city,
                'destination_state' => $load->destination_state,
                'destination_zip' => $load->destination_zip,
                'destination_lat' => $load->destination_lat,
                'destination_lng' => $load->destination_lng,
                'payout_amount' => $load->gross_rate,
                'rate_per_mile' => $load->rate_per_loaded_mile,
                'equipment_type' => $load->equipment_type,
                'weight' => $load->weight,
                'distance_miles' => $load->distance_miles,
                'pickup_start' => $load->pickup_start,
                'pickup_end' => $load->pickup_end,
                'source_url' => $load->source_url,
                'expires_at' => $load->expires_at,
            ]);

            $load->update(['status' => 'published']);
            $published++;
        }

        return back()->with('success', "{$published} loads published to Load Board.");
    }

    public function generateRecommendations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'driver_id' => 'required|exists:delivery_men,id',
            'limit' => 'sometimes|integer|min:1|max:100',
        ]);

        $service = new LoadSourcingService();
        $result = $service->generateRecommendations($validated['driver_id'], $validated['limit'] ?? 20);

        return response()->json($result);
    }

    public function emailIngestions(Request $request)
    {
        $query = LoadEmailIngestion::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $ingestions = $query->latest()->paginate(50);

        return response()->json($ingestions);
    }

    public function approveEmailIngestion(Request $request, int $id)
    {
        $service = new LoadEmailIngestionService();
        $result = $service->approve($id, auth('admin')->id(), $request->all());

        return back()->with('success', 'Email ingestion approved.');
    }

    public function rejectEmailIngestion(Request $request, int $id)
    {
        $validated = $request->validate(['reason' => 'required|string']);
        $service = new LoadEmailIngestionService();
        $result = $service->reject($id, auth('admin')->id(), $validated['reason']);

        return back()->with('success', 'Email ingestion rejected.');
    }

    public function imports()
    {
        $imports = LoadImport::with('source')->latest()->paginate(50);
        return response()->json($imports);
    }

    public function importCsv(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $service = new LoadManualImportService();
        $result = $service->importCsv(
            $validated['file'],
            auth('admin')->id(),
            'admin'
        );

        return back()->with('success', "Imported {$result['successful_rows']} rows.");
    }

    public function resolveError(int $id)
    {
        $error = LoadSourceError::findOrFail($id);
        $error->update(['resolved' => true, 'resolved_at' => now()]);
        return back()->with('success', 'Error marked as resolved.');
    }

    public function externalLoadsApi(Request $request): JsonResponse
    {
        $query = ExternalLoad::with('source')->where('is_duplicate', false);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('source_id')) {
            $query->where('source_id', $request->source_id);
        }

        return response()->json($query->latest()->paginate(50));
    }

    public function syncHistoryApi(): JsonResponse
    {
        $runs = LoadSourceSyncRun::with('source')->latest()->limit(50)->get();
        return response()->json($runs);
    }

    public function errorsApi(): JsonResponse
    {
        $errors = LoadSourceError::with('source')->latest()->limit(100)->get();
        return response()->json($errors);
    }

    public function recommendationsApi(Request $request): JsonResponse
    {
        $query = LoadRecommendation::with(['externalLoad', 'driver']);

        if ($request->has('driver_id')) {
            $query->where('delivery_man_id', $request->driver_id);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->orderByDesc('score')->paginate(50));
    }

    public function retrySyncRun(int $id)
    {
        $run = LoadSourceSyncRun::findOrFail($id);
        $source = $run->source;

        $service = new LoadSourcingService();

        try {
            $result = $service->searchSource($source->source_key, json_decode($run->search_criteria, true) ?? [], auth('admin')->id());
            return back()->with('success', "Retry initiated for {$source->name}.");
        } catch (\Exception $e) {
            return back()->withErrors(['retry' => $e->getMessage()]);
        }
    }

    private function checkSourceCredentials(LoadSource $source): bool
    {
        $requiredKeys = match($source->source_key) {
            'dat' => ['api_key'],
            'truckstop' => ['client_id', 'client_secret'],
            default => [],
        };

        if (empty($requiredKeys)) return true;

        foreach ($requiredKeys as $key) {
            if (!$source->getCredentialValue($key)) {
                return false;
            }
        }

        return true;
    }
}
