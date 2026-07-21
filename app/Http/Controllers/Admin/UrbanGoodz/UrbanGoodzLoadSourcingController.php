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
use App\Models\LoadSourceError;
use App\Models\LoadSourceSearch;
use App\Models\LoadSourceSyncRun;
use App\Models\LoadSourcingSetting;
use App\Services\UrbanGoodz\LoadSource\LoadEmailIngestionService;
use App\Services\UrbanGoodz\LoadSource\LoadManualImportService;
use App\Services\UrbanGoodz\LoadSource\LoadSourcingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UrbanGoodzLoadSourcingController extends Controller
{
    public function index(Request $request)
    {
        $sources = LoadSource::withCount(['externalLoads', 'syncRuns', 'errors'])
            ->orderBy('name')
            ->get();

        $stats = [
            'total_sources' => $sources->count(),
            'active_sources' => $sources->where('enabled', true)->count(),
            'connected_sources' => $sources->where('api_status', 'connected')->count(),
            'total_external_loads' => ExternalLoad::count(),
            'available_loads' => ExternalLoad::where('status', 'available')->where('is_duplicate', false)->count(),
            'pending_review' => ExternalLoad::where('status', 'pending_review')->count(),
            'total_recommendations' => LoadRecommendation::count(),
        ];

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'sources' => $sources,
                'stats' => $stats,
            ]);
        }

        $externalLoads = ExternalLoad::with('source')
            ->latest()
            ->paginate(25);

        return view('admin-views.urban-goodz.load-sourcing.index', compact('sources', 'stats', 'externalLoads'));
    }

    public function showSource(int $id): JsonResponse
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

    public function sourceSearch(Request $request, int $sourceId): JsonResponse
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

    public function searchAll(Request $request): JsonResponse
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

    public function settings(): JsonResponse
    {
        $settings = LoadSourcingSetting::all();

        $defaults = [
            'platform_fee_percent' => ['value' => 12.0, 'type' => 'decimal', 'description' => 'Platform fee percentage'],
            'fuel_cost_per_mile' => ['value' => 0.75, 'type' => 'decimal', 'description' => 'Estimated fuel cost per mile'],
            'toll_estimation_per_mile' => ['value' => 0.05, 'type' => 'decimal', 'description' => 'Estimated tolls per mile'],
            'default_max_deadhead_miles' => ['value' => 100, 'type' => 'integer', 'description' => 'Default max deadhead distance'],
            'minimum_confidence_threshold' => ['value' => 30, 'type' => 'integer', 'description' => 'Minimum score threshold'],
            'auto_alert_threshold' => ['value' => 70, 'type' => 'integer', 'description' => 'Auto-alert score threshold'],
        ];

        $weights = [
            'profit' => ['value' => 25, 'type' => 'integer', 'description' => 'Estimated net profit weight'],
            'rate_per_mile' => ['value' => 15, 'type' => 'integer', 'description' => 'Rate per loaded mile weight'],
            'deadhead' => ['value' => 15, 'type' => 'integer', 'description' => 'Deadhead distance weight'],
            'equipment_match' => ['value' => 15, 'type' => 'integer', 'description' => 'Equipment match weight'],
            'schedule_feasibility' => ['value' => 10, 'type' => 'integer', 'description' => 'Schedule feasibility weight'],
            'broker_quality' => ['value' => 10, 'type' => 'integer', 'description' => 'Broker quality weight'],
            'return_load' => ['value' => 5, 'type' => 'integer', 'description' => 'Return load potential weight'],
            'driver_preference' => ['value' => 5, 'type' => 'integer', 'description' => 'Driver preference weight'],
        ];

        $existingSettings = [];
        foreach ($settings as $s) {
            $existingSettings[$s->setting_key] = $s->setting_value;
        }

        return response()->json([
            'settings' => $defaults,
            'weights' => $weights,
            'existing' => $existingSettings,
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => 'sometimes|array',
            'weights' => 'sometimes|array',
        ]);

        if (!empty($validated['settings'])) {
            foreach ($validated['settings'] as $key => $value) {
                $type = is_int($value) ? 'integer' : (is_float($value) ? 'decimal' : 'string');
                LoadSourcingSetting::set($key, $value, $type);
            }
        }

        if (!empty($validated['weights'])) {
            $totalWeight = array_sum($validated['weights']);
            if ($totalWeight !== 100) {
                return response()->json(['error' => 'Weight total must equal 100, got ' . $totalWeight], 422);
            }
            LoadSourcingSetting::set('scoring_weights', $validated['weights'], 'json');
        }

        return response()->json(['success' => true]);
    }

    public function syncHistory(): JsonResponse
    {
        $runs = LoadSourceSyncRun::with('source')
            ->latest()
            ->limit(50)
            ->get();

        return response()->json($runs);
    }

    public function externalLoads(Request $request): JsonResponse
    {
        $query = ExternalLoad::with('source')
            ->where('is_duplicate', false);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('compliance_status')) {
            $query->where('compliance_status', $request->compliance_status);
        }
        if ($request->has('source_id')) {
            $query->where('source_id', $request->source_id);
        }

        $loads = $query->latest()->paginate(50);

        return response()->json($loads);
    }

    public function approveLoad(int $id): JsonResponse
    {
        $load = ExternalLoad::findOrFail($id);

        if ($load->status !== 'pending_review') {
            return response()->json(['error' => 'Load must be in pending_review status'], 422);
        }

        $load->update([
            'status' => 'available',
            'approved_by' => auth('admin')->id(),
            'approved_by_type' => 'admin',
            'approved_at' => now(),
        ]);

        return response()->json(['success' => true, 'load' => $load]);
    }

    public function rejectLoad(Request $request, int $id): JsonResponse
    {
        $load = ExternalLoad::findOrFail($id);

        $load->update([
            'status' => 'cancelled',
        ]);

        return response()->json(['success' => true]);
    }

    public function recommendations(Request $request): JsonResponse
    {
        $query = LoadRecommendation::with(['externalLoad', 'driver']);

        if ($request->has('driver_id')) {
            $query->where('delivery_man_id', $request->driver_id);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('min_score')) {
            $query->where('score', '>=', $request->min_score);
        }

        $recommendations = $query->orderByDesc('score')->paginate(50);

        return response()->json($recommendations);
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

    public function emailIngestions(Request $request): JsonResponse
    {
        $query = LoadEmailIngestion::query();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $ingestions = $query->latest()->paginate(50);

        return response()->json($ingestions);
    }

    public function approveEmailIngestion(Request $request, int $id): JsonResponse
    {
        $service = new LoadEmailIngestionService();
        $result = $service->approve($id, auth('admin')->id(), $request->all());

        return response()->json($result);
    }

    public function rejectEmailIngestion(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate(['reason' => 'required|string']);
        $service = new LoadEmailIngestionService();
        $result = $service->reject($id, auth('admin')->id(), $validated['reason']);

        return response()->json($result);
    }

    public function imports(): JsonResponse
    {
        $imports = LoadImport::with('source')->latest()->paginate(50);
        return response()->json($imports);
    }

    public function importCsv(Request $request): JsonResponse
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

        return response()->json($result);
    }

    public function errors(): JsonResponse
    {
        $errors = LoadSourceError::with('source')->latest()->limit(100)->get();
        return response()->json($errors);
    }

    public function resolveError(int $id): JsonResponse
    {
        $error = LoadSourceError::findOrFail($id);
        $error->update(['resolved' => true, 'resolved_at' => now()]);
        return response()->json(['success' => true]);
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
