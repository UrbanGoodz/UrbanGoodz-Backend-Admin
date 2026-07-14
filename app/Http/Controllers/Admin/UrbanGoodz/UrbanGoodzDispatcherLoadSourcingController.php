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
use App\Services\UrbanGoodz\LoadSource\LoadSourcingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UrbanGoodzDispatcherLoadSourcingController extends Controller
{
    public function dashboard(): JsonResponse
    {
        $companyId = request()->attributes->get('dispatch_company_id');

        $drivers = DeliveryMan::where('load_board_eligible', true)
            ->where('active', 1)
            ->get();

        $availableLoads = ExternalLoad::where('status', 'available')
            ->where('is_duplicate', false)
            ->count();

        $savedSearches = DispatcherSavedSearch::where('dispatch_company_id', $companyId)->get();

        $recentRecommendations = LoadRecommendation::whereIn('delivery_man_id', $drivers->pluck('id'))
            ->where('status', 'pending')
            ->with('externalLoad')
            ->orderByDesc('score')
            ->limit(20)
            ->get();

        return response()->json([
            'eligible_drivers' => $drivers->count(),
            'available_loads' => $availableLoads,
            'saved_searches' => $savedSearches->count(),
            'top_recommendations' => $recentRecommendations,
        ]);
    }

    public function searchAllSources(Request $request): JsonResponse
    {
        $service = new LoadSourcingService();

        $criteria = $request->only([
            'origin_state', 'destination_state', 'equipment_type',
            'min_rate', 'max_deadhead', 'pickup_date_from', 'pickup_date_to',
            'weight_max', 'max_results',
        ]);

        $result = $service->searchAllSources(
            $criteria,
            auth('business')->id(),
            'dispatcher'
        );

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

        $allowedSorts = ['gross_rate', 'rate_per_loaded_mile', 'distance_deadhead', 'estimated_driver_net', 'created_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        if ($request->has('origin_state')) {
            $query->where('origin_state', $request->origin_state);
        }
        if ($request->has('destination_state')) {
            $query->where('destination_state', $request->destination_state);
        }

        $loads = $query->limit(50)->get();

        return response()->json($loads);
    }

    public function saveSearch(Request $request): JsonResponse
    {
        $user = auth('business')->user();
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'criteria' => 'sometimes|array',
            'source_keys' => 'sometimes|array',
            'auto_alert' => 'sometimes|boolean',
            'alert_threshold_score' => 'sometimes|integer|min:0|max:100',
        ]);

        $search = DispatcherSavedSearch::create([
            'business_client_user_id' => $user->id,
            'dispatch_company_id' => $user->business_client_id,
            'name' => $validated['name'],
            'criteria' => $validated['criteria'] ?? null,
            'source_keys' => $validated['source_keys'] ?? null,
            'auto_alert' => $validated['auto_alert'] ?? false,
            'alert_threshold_score' => $validated['alert_threshold_score'] ?? 70,
        ]);

        return response()->json(['success' => true, 'search' => $search]);
    }

    public function savedSearches(): JsonResponse
    {
        $user = auth('business')->user();
        $searches = DispatcherSavedSearch::where('dispatch_company_id', $user->business_client_id)
            ->latest()
            ->get();

        return response()->json($searches);
    }

    public function deleteSavedSearch(int $id): JsonResponse
    {
        $user = auth('business')->user();
        $search = DispatcherSavedSearch::where('dispatch_company_id', $user->business_client_id)->findOrFail($id);
        $search->delete();

        return response()->json(['success' => true]);
    }

    public function runSavedSearch(int $id): JsonResponse
    {
        $user = auth('business')->user();
        $search = DispatcherSavedSearch::where('dispatch_company_id', $user->business_client_id)->findOrFail($id);

        $service = new LoadSourcingService();
        $result = $service->searchAllSources(
            $search->criteria ?? [],
            $user->id,
            'dispatcher'
        );

        $search->update([
            'last_run_result_count' => $result['count'],
            'last_run_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'loads_count' => $result['count'],
            'loads' => $result['loads'],
            'search_id' => $result['search_id'],
        ]);
    }

    public function bestForDriver(int $driverId): JsonResponse
    {
        $user = auth('business')->user();
        $driver = DeliveryMan::find($driverId);

        if (!$driver) {
            return response()->json(['error' => 'Driver not found'], 404);
        }

        $service = new LoadSourcingService();
        $result = $service->generateRecommendations($driverId, 20);

        return response()->json($result);
    }

    public function assignLoadToDriver(Request $request, int $externalLoadId): JsonResponse
    {
        $validated = $request->validate([
            'driver_id' => 'required|exists:delivery_men,id',
        ]);

        $load = ExternalLoad::findOrFail($externalLoadId);
        $user = auth('business')->user();

        $driver = DeliveryMan::find($validated['driver_id']);

        $service = new LoadSourcingService();
        $preferences = DriverLoadPreference::where('delivery_man_id', $driver->id)->first();
        $weights = $service->getWeights();

        if (!$service->isEligible($load, $driver, $preferences)) {
            return response()->json(['error' => 'Driver is not eligible for this load'], 422);
        }

        $scoreResult = $service->scoreLoad($load, $driver, $preferences, $weights);

        LoadRecommendation::create([
            'external_load_id' => $load->id,
            'delivery_man_id' => $driver->id,
            'generated_by' => $user->id,
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

        return response()->json([
            'success' => true,
            'score' => $scoreResult['total_score'],
            'estimated_net' => $scoreResult['estimated_driver_net'],
        ]);
    }

    public function openExternalLoad(int $externalLoadId): JsonResponse
    {
        $load = ExternalLoad::with('source')->findOrFail($externalLoadId);
        $user = auth('business')->user();

        $externalUrl = $load->source_url ?? $load->source->deep_link_template ?? null;

        if (!$externalUrl) {
            return response()->json(['error' => 'No external URL available for this load'], 404);
        }

        $service = new LoadSourcingService();
        $referral = $service->recordExternalHandoff(
            $load->id,
            $load->source_id,
            $user->id,
            'dispatcher',
            'open_source',
            $externalUrl
        );

        return response()->json([
            'success' => true,
            'external_url' => $externalUrl,
            'source_name' => $load->source->name,
            'referral_id' => $referral['referral']->id ?? null,
        ]);
    }

    public function confirmBooking(Request $request, int $referralId): JsonResponse
    {
        $validated = $request->validate([
            'booked' => 'required|boolean',
            'notes' => 'sometimes|nullable|string',
        ]);

        $service = new LoadSourcingService();
        $result = $service->recordBookingConfirmation($referralId, $validated['booked'], $validated['notes'] ?? null);

        return response()->json($result);
    }

    public function driverPreferences(Request $request, int $driverId): JsonResponse
    {
        if ($request->isMethod('GET')) {
            $prefs = DriverLoadPreference::where('delivery_man_id', $driverId)->first();
            return response()->json($prefs);
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

        return response()->json(['success' => true, 'preferences' => $prefs]);
    }
}
