<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\DeliveryMan;
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

    /**
     * Aggregate statistics for the sourcing overview.
     *
     * Every column referenced here must exist on `external_loads`. Note that
     * `rate_per_mile` and `assigned_driver_id` belong to
     * `urban_goodz_load_board_loads`, NOT to `external_loads` — the sourcing
     * table stores `rate_per_loaded_mile` and has no driver-assignment column
     * at all (assignment only happens once a load is published to the board).
     */
    private function loadOverviewStats(): array
    {
        $statusCounts = ExternalLoad::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $byStatus = [];
        foreach (ExternalLoad::STATUSES as $status) {
            $byStatus[$status] = (int) ($statusCounts[$status] ?? 0);
        }

        return [
            'total_loads'       => (int) ExternalLoad::count(),
            'by_status'         => $byStatus,
            'available'         => (int) ExternalLoad::available()->count(),
            'sourced'           => $byStatus['sourced'],
            'pending_review'    => $byStatus['pending_review'],
            'approved'          => $byStatus['approved'],
            'bid_submitted'     => $byStatus['bid_submitted'],
            'booked'            => $byStatus['booked'],
            'expired'           => $byStatus['expired'],
            'cancelled'         => $byStatus['cancelled'],
            'duplicates'        => (int) ExternalLoad::where('is_duplicate', true)->count(),
            'total_payout'      => (float) ExternalLoad::whereNotNull('gross_rate')->sum('gross_rate'),
            'avg_gross_rate'    => (float) ExternalLoad::where('gross_rate', '>', 0)->avg('gross_rate'),
            // `rate_per_loaded_mile` is the sourcing table's rate column.
            'avg_rate_per_mile' => (float) ExternalLoad::where('rate_per_loaded_mile', '>', 0)
                                        ->avg('rate_per_loaded_mile'),
            'assigned_count'    => (int) ExternalLoad::available()
                                        ->whereExists($this->boardAssignmentExistsQuery())->count(),
            'unassigned_count'  => (int) ExternalLoad::available()
                                        ->whereNotExists($this->boardAssignmentExistsQuery())->count(),
            'loads_by_origin_state' => ExternalLoad::whereNotNull('origin_state')->notDuplicate()
                                        ->selectRaw('origin_state, count(*) as count')
                                        ->groupBy('origin_state')->orderByDesc('count')->limit(12)->get(),
            'loads_by_equipment_type' => ExternalLoad::whereNotNull('equipment_type')->notDuplicate()
                                        ->selectRaw('equipment_type, count(*) as count')
                                        ->groupBy('equipment_type')->orderByDesc('count')->limit(12)->get(),
        ];
    }

    /**
     * `external_loads` has no driver-assignment column. A sourced load counts as
     * assigned only once it has been published to the load board AND a driver was
     * assigned there, which we correlate through the shared fingerprint.
     */
    private function boardAssignmentExistsQuery(): \Closure
    {
        return function ($query) {
            $query->select(DB::raw(1))
                ->from('urban_goodz_load_board_loads')
                ->whereColumn('urban_goodz_load_board_loads.fingerprint', 'external_loads.fingerprint')
                ->whereNotNull('urban_goodz_load_board_loads.assigned_driver_id')
                ->whereNull('urban_goodz_load_board_loads.deleted_at');
        };
    }

    /**
     * Per-source health, credential posture (never the secret values) and the
     * moment each source is next due for a sync.
     */
    private function sourceHealthReport(int $refreshMinutes)
    {
        $sources = LoadSource::withCount(['externalLoads', 'syncRuns', 'errors', 'searches'])
            ->with(['credentials' => fn($q) => $q->select(
                'id', 'source_id', 'credential_key', 'status', 'expires_at', 'last_validated_at'
            )])
            ->orderBy('name')
            ->get();

        return $sources->map(function (LoadSource $source) use ($refreshMinutes) {
            $metadata = is_array($source->metadata) ? $source->metadata : [];
            $interval = (int) ($metadata['refresh_interval_minutes'] ?? $refreshMinutes);
            $interval = $interval > 0 ? $interval : $refreshMinutes;
            $nextDue = $source->last_sync_at ? $source->last_sync_at->copy()->addMinutes($interval) : null;

            $credentials = $source->credentials->map(fn($c) => [
                // Deliberately no `encrypted_value` / decrypted value: status only.
                'key'               => $c->credential_key,
                'status'            => $c->status,
                'expires_at'        => $c->expires_at,
                'last_validated_at' => $c->last_validated_at,
                'is_expired'        => $c->expires_at ? $c->expires_at->isPast() : false,
            ]);

            return [
                'source'              => $source,
                'enabled'             => (bool) $source->enabled,
                'api_status'          => $source->api_status,
                'partnership_status'  => $source->partnership_status,
                'credentials'         => $credentials,
                'credential_count'    => $credentials->count(),
                'has_credentials'     => $credentials->isNotEmpty(),
                'credentials_healthy' => $credentials->isNotEmpty()
                                        && $credentials->every(fn($c) => $c['status'] === 'active' && !$c['is_expired']),
                'last_sync_at'        => $source->last_sync_at,
                'last_success_at'     => $source->last_success_at,
                'last_error_at'       => $source->last_error_at,
                'last_error_message'  => $source->last_error_message,
                'next_sync_due_at'    => $nextDue,
                'refresh_minutes'     => $interval,
                'is_overdue'          => $nextDue ? $nextDue->isPast() : (bool) $source->enabled,
                'loads_sourced'       => (int) ($source->external_loads_count ?? 0),
                'sync_count'          => (int) ($source->sync_runs_count ?? 0),
                'search_count'        => (int) ($source->searches_count ?? 0),
                'error_count'         => (int) ($source->errors_count ?? 0),
            ];
        });
    }

    // ────────────────────────────────────────────────────────
    // 9 BLADE PAGES
    // ────────────────────────────────────────────────────────

    public function overview(Request $request)
    {
        $filters = [
            'status'         => $request->query('status'),
            'source_id'      => $request->query('source_id'),
            'equipment_type' => $request->query('equipment_type'),
            'q'              => $request->query('q'),
        ];

        $overviewError = null;
        $refreshMinutes = 30;
        $settings = [];

        try {
            $settings = LoadSourcingSetting::getAll();
            $refreshMinutes = (int) ($settings['default_source_refresh_minutes'] ?? 30);
            $refreshMinutes = $refreshMinutes > 0 ? $refreshMinutes : 30;
        } catch (\Throwable $e) {
            // Settings are advisory; surface the problem instead of blanking the page.
            $overviewError = 'Sourcing settings could not be loaded: ' . $e->getMessage();
        }

        $loadsQuery = ExternalLoad::with('source')->notDuplicate();
        if (!empty($filters['status'])) {
            $loadsQuery->where('status', $filters['status']);
        }
        if (!empty($filters['source_id'])) {
            $loadsQuery->where('source_id', $filters['source_id']);
        }
        if (!empty($filters['equipment_type'])) {
            $loadsQuery->where('equipment_type', $filters['equipment_type']);
        }
        if (!empty($filters['q'])) {
            $term = '%' . $filters['q'] . '%';
            $loadsQuery->where(function ($q) use ($term) {
                $q->where('broker_name', 'like', $term)
                  ->orWhere('origin_city', 'like', $term)
                  ->orWhere('destination_city', 'like', $term)
                  ->orWhere('commodity', 'like', $term)
                  ->orWhere('external_id', 'like', $term);
            });
        }

        $sourceHealth = $this->sourceHealthReport($refreshMinutes);
        $nextScheduledSync = $sourceHealth
            ->where('enabled', true)
            ->pluck('next_sync_due_at')
            ->filter()
            ->sort()
            ->first();

        return view('admin-views.urban-goodz.load-sourcing.overview', [
            'nav'               => $this->subNav('overview'),
            'stats'             => $this->loadOverviewStats(),
            'filters'           => $filters,
            'statuses'          => ExternalLoad::STATUSES,
            'sources'           => LoadSource::orderBy('name')->get(),
            'sourceHealth'      => $sourceHealth,
            'sourceSummary'     => [
                'total'      => $sourceHealth->count(),
                'enabled'    => $sourceHealth->where('enabled', true)->count(),
                'disabled'   => $sourceHealth->where('enabled', false)->count(),
                'connected'  => $sourceHealth->where('api_status', 'connected')->count(),
                'errored'    => $sourceHealth->where('api_status', 'error')->count(),
                'no_credentials' => $sourceHealth->where('has_credentials', false)->count(),
            ],
            'lastSyncAt'        => $sourceHealth->pluck('last_sync_at')->filter()->sortDesc()->first(),
            'nextScheduledSync' => $nextScheduledSync,
            'refreshMinutes'    => $refreshMinutes,
            'settings'          => $settings,
            'recentSearches'    => LoadSourceSearch::with('source')->latest()->limit(10)->get(),
            'recentSyncRuns'    => LoadSourceSyncRun::with('source')->latest()->limit(10)->get(),
            'syncFailures'      => LoadSourceError::with('source')->where('resolved', false)
                                        ->latest()->limit(10)->get(),
            'recentLoads'       => $loadsQuery->latest()->paginate(10, ['*'], 'loads_page')->withQueryString(),
            'recentImports'     => LoadImport::with('source')->latest()->limit(10)->get(),
            'duplicates'        => ExternalLoad::with(['source', 'deduplicatedTo'])
                                        ->where('is_duplicate', true)->latest()->limit(10)->get(),
            'recommendations'   => LoadRecommendation::with(['externalLoad', 'driver'])
                                        ->orderByDesc('score')->limit(10)->get(),
            'matchingDrivers'   => DriverLoadPreference::with('driver')->latest()->limit(10)->get(),
            'auditTrail'        => $this->overviewAuditTrail(),
            'overviewError'     => $overviewError,
        ]);
    }

    /**
     * A unified, read-only activity trail assembled from the real sourcing
     * records (searches, sync runs, imports, errors and admin approvals).
     */
    private function overviewAuditTrail(int $limit = 15): \Illuminate\Support\Collection
    {
        $events = collect();

        foreach (LoadSourceSearch::with('source')->latest()->limit($limit)->get() as $s) {
            $events->push([
                'at' => $s->created_at, 'type' => 'search',
                'actor' => trim(($s->searched_by_type ?? 'system') . ' #' . ($s->searched_by ?? '-')),
                'summary' => 'Search on ' . ($s->source->name ?? 'all sources')
                             . ' returned ' . (int) $s->result_count . ' load(s)',
                'ok' => (bool) $s->completed,
            ]);
        }
        foreach (LoadSourceSyncRun::with('source')->latest()->limit($limit)->get() as $r) {
            $events->push([
                'at' => $r->created_at, 'type' => 'sync',
                'actor' => 'scheduler',
                'summary' => 'Sync ' . $r->status . ' for ' . ($r->source->name ?? 'unknown source')
                             . ' (' . (int) $r->loads_new . ' new, ' . (int) $r->loads_duplicate . ' dup)',
                'ok' => $r->status === 'completed',
            ]);
        }
        foreach (LoadImport::with('source')->latest()->limit($limit)->get() as $i) {
            $events->push([
                'at' => $i->created_at, 'type' => 'import',
                'actor' => trim(($i->imported_by_type ?? 'system') . ' #' . ($i->imported_by ?? '-')),
                'summary' => ucfirst((string) $i->import_method) . ' import: '
                             . (int) $i->successful_rows . '/' . (int) $i->total_rows . ' rows',
                'ok' => $i->status === 'completed',
            ]);
        }
        foreach (LoadSourceError::with('source')->latest()->limit($limit)->get() as $e) {
            $events->push([
                'at' => $e->created_at, 'type' => 'error',
                'actor' => ($e->source->name ?? 'unknown source'),
                'summary' => '[' . $e->error_code . '] ' . \Illuminate\Support\Str::limit((string) $e->error_message, 120),
                'ok' => false,
            ]);
        }
        foreach (ExternalLoad::whereNotNull('approved_at')->latest('approved_at')->limit($limit)->get() as $l) {
            $events->push([
                'at' => $l->approved_at, 'type' => 'approval',
                'actor' => trim(($l->approved_by_type ?? 'admin') . ' #' . ($l->approved_by ?? '-')),
                'summary' => 'Load #' . $l->id . ' approved (' . $l->status . ')',
                'ok' => true,
            ]);
        }

        return $events->filter(fn($e) => $e['at'] !== null)
            ->sortByDesc('at')
            ->values()
            ->take($limit);
    }

    public function sources()
    {
        $sources = LoadSource::withCount(['externalLoads', 'syncRuns', 'errors'])
            ->with(['credentials' => fn($q) => $q->select(
                'id', 'source_id', 'credential_key', 'status', 'expires_at', 'last_validated_at'
            )])
            ->orderBy('name')
            ->get();

        // The sources view reads these as attributes off each source, so derive
        // them here. NOTE: `load_source_sync_runs` has no `completed_at` column —
        // the run's `updated_at` is when it reached its terminal state.
        $sources->each(function (LoadSource $src) {
            $lastRun = $src->syncRuns()->latest()->first();
            $lastSuccess = $src->syncRuns()->where('status', 'completed')->latest()->first();

            $src->setAttribute('credential_status', $src->credentials->isEmpty()
                ? 'missing'
                : ($src->credentials->every(fn($c) => $c->status === 'active' && !$c->isExpired()) ? 'active' : 'expired'));
            $src->setAttribute('credential_count', $src->credentials->count());
            $src->setAttribute('last_successful_sync_at', $lastSuccess?->updated_at);
            $src->setAttribute('last_run_at', $lastRun?->created_at);
            $src->setAttribute('records_imported_count', (int) ($src->external_loads_count ?? 0));
        });

        $sourceStats = $sources->map(fn(LoadSource $src) => [
            'source'            => $src,
            'credential_count'  => $src->credential_count,
            'has_credentials'   => $src->credential_count > 0,
            'all_active'        => $src->credential_status === 'active',
            'last_sync_at'      => $src->last_sync_at,
            'last_success_at'   => $src->last_successful_sync_at,
            'records_imported'  => $src->records_imported_count,
            'error_count'       => (int) ($src->errors_count ?? 0),
        ]);

        return view('admin-views.urban-goodz.load-sourcing.sources', [
            'nav' => $this->subNav('sources'),
            'sources' => $sources,
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
            // The view iterates `$searchResults`; `results` is kept for API parity.
            'searchResults' => $results,
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
            'savedSearches' => $searches,
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
            'externalLoads' => $loads,
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
            // Driver filter dropdown: only drivers that actually have recommendations.
            // DeliveryMan has no `name` attribute, so compose one for the <option> label.
            'drivers' => DeliveryMan::whereIn(
                    'id', LoadRecommendation::distinct()->pluck('delivery_man_id')->filter()
                )->orderBy('f_name')->get()
                ->each(fn($d) => $d->setAttribute(
                    'name', trim(($d->f_name ?? '') . ' ' . ($d->l_name ?? '')) ?: ('Driver #' . $d->id)
                )),
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
            'syncRuns' => $runs,
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

        // NOTE: `errors` is reserved by Blade for the ViewErrorBag and would be
        // silently shadowed, so the view reads `errors_list`.
        return view('admin-views.urban-goodz.load-sourcing.errors', [
            'nav' => $this->subNav('errors'),
            'errors_list' => $errors,
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
            'settings' => $existing,
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

        $boardLoad = $this->createBoardLoadFrom($load);

        $load->update(['status' => 'booked']);

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

            $this->createBoardLoadFrom($load);

            $load->update(['status' => 'booked']);
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

    /**
     * Project an `external_loads` row onto the `urban_goodz_load_board_loads`
     * schema. The two tables use different names for the same concepts
     * (rate_per_loaded_mile→rate_per_mile, origin_latitude→origin_lat,
     * distance_loaded→distance_miles, weight→weight_lbs,
     * commodity→commodity_description, pickup_start→origin_ready_at,
     * delivery_end→destination_due_at), and several real board columns are not
     * mass-assignable, so they are set explicitly below. Without that,
     * `fingerprint` was silently dropped and the duplicate guard never matched.
     */
    private function createBoardLoadFrom(ExternalLoad $load): UrbanGoodzLoadBoardLoad
    {
        $board = new UrbanGoodzLoadBoardLoad();

        $board->fill([
            'external_id'           => $load->external_id,
            'provider'              => $load->source->source_key ?? 'sourced',
            'load_number'           => 'UGS-' . $load->id,
            'status'                => 'available',
            'origin_name'           => $load->origin_address,
            'origin_city'           => $load->origin_city,
            'origin_state'          => $load->origin_state,
            'origin_zip'            => $load->origin_zip,
            'origin_lat'            => $load->origin_latitude,
            'origin_lng'            => $load->origin_longitude,
            'origin_ready_at'       => $load->pickup_start,
            'destination_name'      => $load->destination_address,
            'destination_city'      => $load->destination_city,
            'destination_state'     => $load->destination_state,
            'destination_zip'       => $load->destination_zip,
            'destination_lat'       => $load->destination_latitude,
            'destination_lng'       => $load->destination_longitude,
            'destination_due_at'    => $load->delivery_end ?? $load->delivery_start,
            'distance_miles'        => $load->distance_loaded,
            'payout_amount'         => $load->gross_rate,
            'rate_per_mile'         => $load->rate_per_loaded_mile,
            'driver_payout_amount'  => $load->estimated_driver_net,
            'processing_fee'        => $load->estimated_platform_fee,
            'equipment_type'        => $load->equipment_type,
            'weight_lbs'            => $load->weight,
            'commodity_description' => $load->commodity,
        ]);

        // Real board columns that are deliberately absent from its $fillable.
        $board->source_id  = $load->source_id;
        $board->fingerprint = $load->fingerprint;
        $board->source_url = $load->source_url;
        $board->expires_at = $load->expires_at;
        // The board model does not cast this column, so encode it here.
        $board->raw_source_payload = is_array($load->raw_source_payload)
            ? json_encode($load->raw_source_payload)
            : $load->raw_source_payload;

        $board->save();

        return $board;
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
