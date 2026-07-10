<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzSourcedBusiness;
use App\Models\UrbanGoodzSourcedProduct;
use App\Models\UrbanGoodzSourcedImage;
use App\Models\UrbanGoodzDemandSignal;
use App\Services\UrbanGoodzIngestionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class UrbanGoodzDiscoveryController extends Controller
{
    protected UrbanGoodzIngestionService $ingestion;

    public function __construct(UrbanGoodzIngestionService $ingestion)
    {
        $this->ingestion = $ingestion;
    }

    /**
     * Capture customer search query / demand signals.
     */
    public function searchCapture(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'search_query' => 'required|string|max:500',
            'user_id' => 'nullable|integer',
            'module_id' => 'nullable|integer',
            'module_name' => 'nullable|string',
            'city' => 'nullable|string',
            'state' => 'nullable|string',
            'zone_id' => 'nullable|integer',
            'request_type' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = $request->input('search_query');
        $city = $request->input('city', 'Houston');
        $state = $request->input('state', 'TX');

        Log::info('Urban Goodz discovery search captured', [
            'query' => $query,
            'city' => $city,
            'ip' => $request->ip()
        ]);

        // Check if there is an exact or similar match in active stores
        $storeMatch = \App\Models\Store::where('name', 'LIKE', "%{$query}%")->first();
        $sourcedMatch = UrbanGoodzSourcedBusiness::where('name', 'LIKE', "%{$query}%")->where('admin_review_status', 'approved')->first();

        // Update or create demand signal
        $signal = $this->ingestion->updateDemandSignals([
            'customer_id' => $request->input('user_id'),
            'query_text' => $query,
            'requested_item' => $query,
            'source' => 'search',
            'matched_entity_id' => $sourcedMatch ? $sourcedMatch->id : ($storeMatch ? $storeMatch->id : null),
            'city' => $city,
            'state' => $state,
            'zone_id' => $request->input('zone_id'),
        ]);

        // If no match found at all, create a pending/reactive sourced business candidate
        if (!$storeMatch && !$sourcedMatch) {
            // Check compliance flags (exclude liquor, CBD, age-restricted from auto-candidate creation without review)
            $restrictedKeywords = ['liquor', 'wine', 'beer', 'cbd', 'thc', 'weed', 'dispensary', 'pharmacy', 'medicine', 'drug'];
            $isRestricted = false;
            foreach ($restrictedKeywords as $word) {
                if (str_contains(strtolower($query), $word)) {
                    $isRestricted = true;
                    break;
                }
            }

            if (!$isRestricted) {
                $candidate = [
                    'name' => ucwords($query),
                    'slug' => Str::slug($query) . '-' . Str::random(4),
                    'display_name' => ucwords($query),
                    'city' => $city,
                    'state' => $state,
                    'is_launch_market' => strtolower($city) === 'houston',
                    'onboarding_status' => 'public_sourced',
                    'source_status' => 'customer_requested',
                    'admin_review_status' => 'pending',
                    'data_confidence_score' => 40,
                    'demand_score' => 1,
                    'fulfillment_modes' => ['order_anywhere'],
                ];

                $classification = $this->ingestion->classifyBusiness($candidate);
                $candidate = array_merge($candidate, $classification);

                $business = UrbanGoodzSourcedBusiness::create($candidate);

                // Attach product
                UrbanGoodzSourcedProduct::create([
                    'sourced_business_id' => $business->id,
                    'module_id' => $business->module_id,
                    'name' => ucwords($query),
                    'price_type' => 'quote_required',
                    'requires_quote' => true,
                    'requires_admin_review' => true,
                    'item_type' => 'custom_request',
                ]);

                // Update signal match
                $signal->update(['matched_entity_id' => $business->id]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Search request captured and demand registered.',
            'data' => $signal,
        ]);
    }

    /**
     * List sourced business candidates for the review queue.
     */
    public function entities(Request $request)
    {
        $entities = UrbanGoodzSourcedBusiness::with(['products', 'images'])
            ->where('admin_review_status', 'approved')
            ->when($request->input('city'), fn($q, $city) => $q->where('city', $city))
            ->when($request->input('onboarding_status'), fn($q, $status) => $q->where('onboarding_status', $status))
            ->when($request->input('module_id'), fn($q, $mid) => $q->where('module_id', $mid))
            ->when($request->input('is_black_owned'), fn($q, $bo) => $q->where('is_black_owned', $bo))
            ->latest()
            ->paginate($request->input('per_page', 25));

        return response()->json([
            'success' => true,
            'data' => $entities,
        ]);
    }

    /**
     * Retrieve single business candidate.
     */
    public function entity($id)
    {
        $entity = UrbanGoodzSourcedBusiness::with(['products', 'images'])->where('admin_review_status', 'approved')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $entity,
        ]);
    }

    /**
     * Admin action on sourced candidate (approve, reject, edit, merge).
     */
    public function entityAction(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|string|in:approve,reject,edit,merge',
            'name' => 'nullable|string|max:255',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
            'is_black_owned' => 'nullable|boolean',
            'is_woman_owned' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $entity = UrbanGoodzSourcedBusiness::findOrFail($id);
        $action = $request->input('action');

        if ($action === 'approve') {
            // Converts from public listing to active/claimed vendor
            $store = $this->ingestion->publishApprovedListings($entity->id);
            return response()->json([
                'success' => true,
                'message' => 'Business listing approved and published live.',
                'data' => $store,
            ]);
        }

        if ($action === 'reject') {
            $entity->update([
                'onboarding_status' => 'rejected',
                'admin_review_status' => 'rejected',
            ]);
            return response()->json([
                'success' => true,
                'message' => 'Business listing rejected.',
                'data' => $entity,
            ]);
        }

        if ($action === 'edit') {
            $entity->update($request->only(['name', 'phone', 'address', 'is_black_owned', 'is_woman_owned']));
            return response()->json([
                'success' => true,
                'message' => 'Business details updated.',
                'data' => $entity->fresh(),
            ]);
        }

        if ($action === 'merge') {
            // Merge duplicate into existing store
            $targetStoreId = $request->input('target_store_id');
            if (!$targetStoreId) {
                return response()->json(['success' => false, 'message' => 'target_store_id is required for merge.'], 422);
            }

            $entity->update([
                'onboarding_status' => 'archived',
                'admin_review_status' => 'merged',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Business duplicate merged into target store successfully.',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid action',
        ], 400);
    }

    /**
     * Get demand opportunities and search highlights.
     */
    public function opportunities(Request $request)
    {
        $signals = UrbanGoodzDemandSignal::with(['matchedEntity'])
            ->selectRaw('query_text, requested_vendor, city, count(*) as demand_count, max(opportunity_score) as max_opportunity_score')
            ->groupBy('query_text', 'requested_vendor', 'city')
            ->orderBy('demand_count', 'desc')
            ->paginate($request->input('per_page', 25));

        return response()->json([
            'success' => true,
            'data' => $signals,
        ]);
    }

    /**
     * Accept opportunity / invite vendor onboarding.
     */
    public function acceptOpportunity($id)
    {
        $signal = UrbanGoodzDemandSignal::findOrFail($id);

        if ($signal->matched_entity_id) {
            $entity = UrbanGoodzSourcedBusiness::find($signal->matched_entity_id);
            if ($entity) {
                $entity->update([
                    'onboarding_status' => 'invited',
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Onboarding invitation sent to sourced vendor.',
        ]);
    }
}
