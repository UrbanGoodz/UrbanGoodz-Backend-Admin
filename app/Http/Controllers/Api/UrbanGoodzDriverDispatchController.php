<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiDispatch;
use App\Services\UrbanGoodzAiDispatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UrbanGoodzDriverDispatchController extends Controller
{
    public function __construct(private UrbanGoodzAiDispatchService $dispatchService) {}

    private function authDriver(Request $request)
    {
        $driver = $request->user('delivery_men') ?? auth('delivery_men')->user();
        if (!$driver) {
            abort(401, 'Unauthenticated driver');
        }
        return $driver;
    }

    public function index(Request $request): JsonResponse
    {
        $driver = $this->authDriver($request);

        $statuses = $request->input('status');

        // Expire stale offers before listing
        $this->dispatchService->expireStaleOffers(30);

        $query = AiDispatch::forDriver($driver->id)
            ->with(['load' => function ($q) {
                $q->select('id', 'reference_number', 'origin_city', 'origin_state', 'destination_city', 'destination_state', 'distance_miles', 'rate', 'vehicle_type', 'pickup_date', 'delivery_date', 'status');
            }, 'route' => function ($q) {
                $q->select('id', 'route_name', 'pickup_location', 'dropoff_location', 'total_packages', 'status');
            }, 'businessClient' => function ($q) {
                $q->select('id', 'company_name');
            }]);

        if ($statuses) {
            $filterList = array_map('trim', explode(',', $statuses));
            $query->whereIn('status', $filterList);
        } else {
            $query->orderByRaw("FIELD(status, 'sent', 'pending_driver', 'viewed', 'accepted', 'en_route_to_pickup', 'arrived_at_pickup', 'picked_up', 'in_transit', 'arrived_at_delivery', 'delivered', 'exception', 'declined', 'expired', 'cancelled', 'settled')");
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $dispatches = $query->paginate($perPage);

        $dispatches->getCollection()->transform(function ($d) {
            $d->is_expired = $d->isExpiredOffer();
            $d->can_accept = $d->canAccept();
            $d->can_decline = $d->canDecline();
            return $d;
        });

        return response()->json([
            'dispatches' => $dispatches->items(),
            'pagination' => [
                'current_page' => $dispatches->currentPage(),
                'last_page' => $dispatches->lastPage(),
                'per_page' => $dispatches->perPage(),
                'total' => $dispatches->total(),
            ],
            'counts' => [
                'pending' => AiDispatch::forDriver($driver->id)->whereIn('status', ['sent', 'pending_driver', 'viewed'])->count(),
                'active' => AiDispatch::forDriver($driver->id)->active()->count(),
                'completed' => AiDispatch::forDriver($driver->id)->whereIn('status', ['delivered', 'settled', 'closed'])->count(),
            ],
        ]);
    }

    public function show($id, Request $request): JsonResponse
    {
        $driver = $this->authDriver($request);

        $dispatch = AiDispatch::forDriver($driver->id)
            ->with(['load', 'route', 'businessClient', 'aiRecommendation'])
            ->findOrFail($id);

        $dispatch->markViewed();

        $dispatch->is_expired = $dispatch->isExpiredOffer();
        $dispatch->can_accept = $dispatch->canAccept();
        $dispatch->can_decline = $dispatch->canDecline();

        return response()->json(['dispatch' => $dispatch]);
    }

    public function accept($id, Request $request): JsonResponse
    {
        $driver = $this->authDriver($request);

        $dispatch = AiDispatch::forDriver($driver->id)->findOrFail($id);

        if (!$dispatch->canAccept()) {
            return response()->json([
                'error' => 'This dispatch cannot be accepted.',
                'reason' => $dispatch->isExpiredOffer() ? 'offer_expired' : 'invalid_status',
                'current_status' => $dispatch->status,
            ], 422);
        }

        if ($this->dispatchService->hasActiveDispatch($driver->id, $dispatch->id)) {
            return response()->json([
                'error' => 'You already have an active dispatch. Complete or cancel it first.',
            ], 422);
        }

        try {
            $dispatch->acceptDispatch();
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        try {
            app(\App\Services\OrderAnywhereDispatchIntegrationService::class)
                ->onDispatchAccepted($dispatch);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Integration onDispatchAccepted failed', [
                'dispatch_id' => $dispatch->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message' => 'Dispatch accepted successfully.',
            'dispatch' => $dispatch->fresh()->load(['load', 'route']),
        ]);
    }

    public function decline($id, Request $request): JsonResponse
    {
        $driver = $this->authDriver($request);

        $dispatch = AiDispatch::forDriver($driver->id)->findOrFail($id);

        if (!$dispatch->canDecline()) {
            return response()->json([
                'error' => 'This dispatch cannot be declined.',
                'current_status' => $dispatch->status,
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'reason_code' => ['nullable', 'string', Rule::in([
                'availability_conflict', 'equipment_mismatch', 'distance',
                'payout', 'schedule', 'safety_concern', 'other',
            ])],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $dispatch->declineDispatch($request->reason_code, $request->reason);

        return response()->json([
            'message' => 'Dispatch declined.',
            'dispatch' => $dispatch->fresh(),
        ]);
    }

    public function routeGuidance($id, Request $request): JsonResponse
    {
        $driver = $this->authDriver($request);

        $dispatch = AiDispatch::forDriver($driver->id)
            ->with(['load', 'route'])
            ->findOrFail($id);

        $guidance = [];

        if ($dispatch->load) {
            $load = $dispatch->load;
            $guidance = [
                'source_type' => 'load_board',
                'origin' => [
                    'address' => $load->origin_address,
                    'city' => $load->origin_city,
                    'state' => $load->origin_state,
                    'lat' => $load->origin_lat,
                    'lng' => $load->origin_lng,
                ],
                'destination' => [
                    'address' => $load->destination_address,
                    'city' => $load->destination_city,
                    'state' => $load->destination_state,
                    'lat' => $load->destination_lat,
                    'lng' => $load->destination_lng,
                ],
                'distance_miles' => $load->distance_miles,
                'pickup_window' => $load->pickup_date?->toDateTimeString(),
                'delivery_deadline' => $load->delivery_date?->toDateTimeString(),
                'vehicle_type' => $load->vehicle_type,
                'weight_lbs' => $load->weight_lbs,
                'special_instructions' => $load->special_instructions,
            ];
        } elseif ($dispatch->route) {
            $route = $dispatch->route;
            $guidance = [
                'source_type' => 'dedicated_route',
                'route_name' => $route->route_name,
                'pickup_location' => $route->pickup_location,
                'dropoff_location' => $route->dropoff_location,
                'total_stops' => $route->total_packages,
                'total_distance_miles' => $route->estimated_miles,
            ];
        }

        $guidance['routing_service_available'] = false;
        $guidance['routing_note'] = 'Real-time routing service not configured. Use coordinates directly.';

        return response()->json(['guidance' => $guidance]);
    }

    public function reportException($id, Request $request): JsonResponse
    {
        $driver = $this->authDriver($request);

        $dispatch = AiDispatch::forDriver($driver->id)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'exception_type' => ['required', 'string', Rule::in([
                'load_not_ready', 'facility_closed', 'incorrect_address',
                'customer_unavailable', 'refused_delivery', 'damaged_package',
                'missing_item', 'vehicle_issue', 'traffic_delay',
                'safety_issue', 'documentation_issue', 'return_required', 'other',
            ])],
            'description' => ['required', 'string', 'max:2000'],
            'location' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $dispatch->reportException($request->exception_type, $request->description);

        return response()->json([
            'message' => 'Exception reported.',
            'dispatch' => $dispatch->fresh(),
        ]);
    }

    public function markDelivered($id, Request $request): JsonResponse
    {
        $driver = $this->authDriver($request);

        $dispatch = AiDispatch::forDriver($driver->id)->findOrFail($id);

        if (!in_array($dispatch->status, [
            AiDispatch::STATUS_PICKED_UP,
            AiDispatch::STATUS_IN_TRANSIT,
            AiDispatch::STATUS_ARRIVED_AT_DELIVERY,
        ])) {
            return response()->json([
                'error' => 'Delivery can only be completed after pickup.',
                'current_status' => $dispatch->status,
            ], 422);
        }

        $dispatch->deliver();

        try {
            app(\App\Services\OrderAnywhereDispatchIntegrationService::class)
                ->onDispatchDelivered($dispatch);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Integration onDispatchDelivered failed', [
                'dispatch_id' => $dispatch->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message' => 'Delivery confirmed.',
            'dispatch' => $dispatch->fresh(),
        ]);
    }

    public function performanceSummary(Request $request): JsonResponse
    {
        $driver = $this->authDriver($request);
        $summary = $this->dispatchService->getDriverPerformanceSummary($driver->id);
        return response()->json(['performance' => $summary]);
    }
}
