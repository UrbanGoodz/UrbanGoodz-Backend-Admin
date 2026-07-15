<?php

namespace App\Http\Controllers\Api\V1\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderAnywhereRequest;
use App\Models\UrbanGoodzServiceRequest;
use App\Models\UrbanGoodzRentalBooking;
use App\Models\UrbanGoodzCreatorContent;
use App\Models\UrbanGoodzEvent;
use App\Models\UrbanGoodzLoadBoardLoad;
use App\Models\DeliveryMan;
use App\Models\User;
use App\Models\Vendor;
use App\Models\UrbanGoodzBusinessClient;
use App\Services\UrbanGoodz\UrbanGoodzAIExecutionService;
use App\Services\UrbanGoodz\UrbanGoodzAIConciergeService;
use App\Services\UrbanGoodz\UrbanGoodzAIService;
use App\Services\UrbanGoodz\FashionFitAIService;
use App\Services\UrbanGoodz\VendorAIService;
use App\Services\UrbanGoodz\CreatorSpaceAIService;
use App\Services\UrbanGoodz\BusinessClientAIService;
use App\Services\UrbanGoodz\LoadBoardNLPService;
use App\Services\UrbanGoodz\PackageScanAIService;
use App\Services\UrbanGoodz\ETAPredictionService;
use App\Services\UrbanGoodz\DynamicPricingService;
use App\Services\UrbanGoodz\FraudDetectionService;
use App\Services\UrbanGoodz\SupportAIService;
use App\Services\UrbanGoodz\NotificationAIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CrossAppAIController extends Controller
{
    public function __construct(
        private UrbanGoodzAIExecutionService $executionService,
        private UrbanGoodzAIConciergeService $conciergeService,
        private UrbanGoodzAIService $aiService,
        private FashionFitAIService $fashionFitAI,
        private VendorAIService $vendorAI,
        private CreatorSpaceAIService $creatorAI,
        private BusinessClientAIService $businessAI,
        private LoadBoardNLPService $loadBoardNLP,
        private PackageScanAIService $packageScanAI,
        private ETAPredictionService $etaService,
        private DynamicPricingService $dynamicPricing,
        private FraudDetectionService $fraudService,
        private SupportAIService $supportAI,
        private NotificationAIService $notificationAI
    ) {}

    // ─── CUSTOMER APP ENDPOINTS ────────────────────────────────────────────

    /**
     * Customer AI Concierge - main entry point for customer app
     */
    public function customerQuery(Request $request): JsonResponse
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'max:2000'],
            'context' => ['nullable', 'array'],
        ]);

        $customerId = $request->user()?->id ?? Auth::guard('api')->id();

        $result = $this->executionService->executeIntent(
            $data['query'],
            $customerId,
            $data['context'] ?? []
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Customer AI Conversation History
     */
    public function customerHistory(Request $request): JsonResponse
    {
        $customerId = $request->user()?->id ?? Auth::guard('api')->id();

        $conversations = \App\Models\UrbanGoodzAIConversation::where('customer_id', $customerId)
            ->with('detectedIntent')
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $conversations,
        ]);
    }

    /**
     * Customer Fashion Fit - measurements, providers, quotes
     */
    public function fashionFitMeasurements(Request $request): JsonResponse
    {
        $customerId = $request->user()?->id ?? Auth::guard('api')->id();
        $data = $request->validate([
            'photo' => ['required', 'string'],
            'garment_type' => ['nullable', 'string'],
            'style_notes' => ['nullable', 'string'],
        ]);

        $result = $this->fashionFitAI->extractMeasurementsFromPhoto($data['photo'], [
            'garment_type' => $data['garment_type'] ?? null,
            'style_notes' => $data['style_notes'] ?? null,
        ]);

        if (!($result['success'] ?? false)) {
            return response()->json($result, 422);
        }

        // Get matched providers
        $providers = \App\Models\Vendor::where('type', 'fashion_fit_provider')
            ->where('is_active', true)
            ->limit(10)
            ->get()
            ->map(fn($v) => [
                'id' => $v->id,
                'name' => $v->name,
                'phone' => $v->phone,
                'email' => $v->email,
            ])->toArray();

        return response()->json([
            'success' => true,
            'measurements' => $result['measurements'] ?? [],
            'confidence' => $result['confidence'] ?? 0,
            'providers' => $providers,
        ]);
    }

    /**
     * Customer Order Anywhere - submit natural language request
     */
    public function orderAnywhere(Request $request): JsonResponse
    {
        $customerId = $request->user()?->id ?? Auth::guard('api')->id();
        $data = $request->validate([
            'query' => ['required', 'string', 'max:2000'],
            'context' => ['nullable', 'array'],
        ]);

        $nlpService = app(\App\Services\UrbanGoodz\OrderAnywhereNLPService::class);
        $parsed = $nlpService->parseFromText($data['query'], array_merge($data['context'] ?? [], [
            'customer_id' => $customerId,
        ]));

        if (!empty($parsed['missing'])) {
            return response()->json([
                'success' => true,
                'store_found' => false,
                'parsed' => $parsed['parsed'],
                'missing' => $parsed['missing'],
                'follow_up_prompts' => $parsed['follow_up_prompts'],
                'suggestions' => $parsed['suggestions'] ?? [],
            ]);
        }

        // Create order request
        $requestModel = \App\Models\OrderAnywhereRequest::create([
            'customer_id' => $customerId,
            'request_number' => 'OAW-' . strtoupper(uniqid()),
            'store_name' => $parsed['parsed']['store_vendor_name'] ?? null,
            'item_details' => $parsed['parsed']['item_details'] ?? null,
            'delivery_address' => $parsed['parsed']['delivery_address'] ?? null,
            'budget_estimate' => $parsed['parsed']['budget_estimate'] ?? null,
            'status' => 'requested',
        ]);

        return response()->json([
            'success' => true,
            'request_id' => $requestModel->id,
            'request_number' => $requestModel->request_number,
            'status' => $requestModel->status,
        ], 201);
    }

    /**
     * Customer Smart Reorder
     */
    public function smartReorder(Request $request): JsonResponse
    {
        $customerId = $request->user()?->id ?? Auth::guard('api')->id();
        $data = $request->validate([
            'reference' => ['required', 'string'], // "last friday", "order #123", "my usual"
        ]);

        // Find the referenced order
        $order = \App\Models\Order::where('user_id', $customerId)
            ->latest()
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'No previous orders found',
            ], 404);
        }

        // Check availability and build new cart
        $items = $order->details->map(function ($detail) {
            $item = $detail->item;
            if (!$item || !$item->active) {
                return ['name' => $detail->item->name ?? 'Unavailable', 'available' => false];
            }
            return [
                'item_id' => $item->id,
                'name' => $item->name,
                'quantity' => $detail->quantity,
                'price' => $item->price,
                'available' => true,
                'price_changed' => $detail->price != $item->price,
            ];
        })->toArray();

        $unavailable = array_filter($items, fn($i) => !$i['available']);
        $priceChanged = array_filter($items, fn($i) => $i['price_changed'] ?? false);

        return response()->json([
            'success' => true,
            'original_order' => [
                'id' => $order->id,
                'number' => $order->order_number,
                'date' => $order->created_at->format('M d, Y'),
                'total' => $order->order_amount,
            ],
            'items' => $items,
            'unavailable' => array_values($unavailable),
            'price_changes' => array_values($priceChanged),
            'cart_ready' => empty($unavailable),
        ]);
    }

    /**
     * Customer Delivery ETA
     */
    public function deliveryETA(Request $request): JsonResponse
    {
        $customerId = $request->user()?->id ?? Auth::guard('api')->id();
        $data = $request->validate([
            'order_id' => ['required', 'integer'],
        ]);

        $order = Order::where('id', $data['order_id'])
            ->where('user_id', $customerId)
            ->with(['details.item', 'store', 'deliveryMan'])
            ->firstOrFail();

        $eta = $this->etaService->predictOrderETA($data['order_id']);

        return response()->json([
            'success' => true,
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->order_status,
                'store' => $order->store->name ?? null,
            ],
            'eta' => $eta,
        ]);
    }

    // ─── VENDOR APP ENDPOINTS ──────────────────────────────────────────────

    /**
     * Vendor Daily Brief
     */
    public function vendorDailyBrief(Request $request): JsonResponse
    {
        $vendorId = $request->user('vendor')?->id ?? Auth::guard('vendor')->id();

        $brief = $this->vendorAI->generateVendorDailyBrief($vendorId);

        return response()->json([
            'success' => true,
            'brief' => $brief,
        ]);
    }

    /**
     * Vendor Order Summary
     */
    public function vendorOrderSummary(Request $request): JsonResponse
    {
        $vendorId = $request->user('vendor')?->id ?? Auth::guard('vendor')->id();
        $data = $request->validate([
            'order_id' => ['required', 'integer'],
        ]);

        // Verify order belongs to vendor
        $order = \App\Models\Order::where('id', $data['order_id'])
            ->whereIn('store_id', \App\Models\Store::where('vendor_id', $vendorId)->pluck('id'))
            ->firstOrFail();

        $summary = $this->vendorAI->summarizeOrder($order->id);

        return response()->json([
            'success' => true,
            'summary' => $summary,
        ]);
    }

    /**
     * Vendor Alerts
     */
    public function vendorAlerts(Request $request): JsonResponse
    {
        $vendorId = $request->user('vendor')?->id ?? Auth::guard('vendor')->id();

        $alerts = $this->vendorAI->generateVendorAlerts($vendorId);

        return response()->json([
            'success' => true,
            'alerts' => $alerts,
        ]);
    }

    /**
     * Vendor Performance Analysis
     */
    public function vendorPerformance(Request $request): JsonResponse
    {
        $vendorId = $request->user('vendor')?->id ?? Auth::guard('vendor')->id();

        $performance = $this->vendorAI->analyzeVendorPerformance($vendorId);

        return response()->json([
            'success' => true,
            'performance' => $performance,
        ]);
    }

    /**
     * Vendor Dynamic Pricing
     */
    public function vendorPricing(Request $request): JsonResponse
    {
        $vendorId = $request->user('vendor')?->id ?? Auth::guard('vendor')->id();

        $result = $this->vendorAI->optimizeMenuPricing($vendorId);

        return response()->json([
            'success' => true,
            'pricing' => $result,
        ]);
    }

    /**
     * Vendor Promotion Suggestions
     */
    public function vendorPromotions(Request $request): JsonResponse
    {
        $vendorId = $request->user('vendor')?->id ?? Auth::guard('vendor')->id();

        $promotions = $this->vendorAI->suggestVendorPromotions($vendorId);

        return response()->json([
            'success' => true,
            'promotions' => $promotions,
        ]);
    }

    /**
     * Vendor Prep Time Estimate
     */
    public function vendorPrepTime(Request $request): JsonResponse
    {
        $vendorId = $request->user('vendor')?->id ?? Auth::guard('vendor')->id();
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.name' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'store_type' => ['nullable', 'string'],
        ]);

        $estimate = $this->vendorAI->estimatePrepTime($data['items'], $data['store_type'] ?? 'restaurant');

        return response()->json([
            'success' => true,
            'estimate' => $estimate,
        ]);
    }

    // ─── DRIVER APP ENDPOINTS ──────────────────────────────────────────────

    /**
     * Driver Daily Summary
     */
    public function driverDailySummary(Request $request): JsonResponse
    {
        $driverId = $request->user('dm')?->id ?? Auth::guard('dm')->id();

        $summary = $this->executionService->executeIntent(
            "Give me my daily summary",
            $driverId
        );

        return response()->json([
            'success' => true,
            'summary' => $summary,
        ]);
    }

    /**
     * Driver Route Optimization
     */
    public function driverRouteOptimization(Request $request): JsonResponse
    {
        $driverId = $request->user('dm')?->id ?? Auth::guard('dm')->id();
        $data = $request->validate([
            'route_id' => ['required', 'integer'],
            'preference' => ['nullable', 'string', 'in:distance,time,earnings'],
        ]);

        $route = \App\Models\UrbanGoodzRouteBatch::where('id', $data['route_id'])
            ->where('delivery_man_id', $driverId)
            ->with('packages')
            ->firstOrFail();

        $result = $this->executionService->executeIntent(
            "Optimize my route {$data['route_id']} for " . ($data['preference'] ?? 'time'),
            $driverId
        );

        return response()->json([
            'success' => true,
            'optimization' => $result,
        ]);
    }

    /**
     * Driver Package Verification
     */
    public function driverVerifyPackage(Request $request): JsonResponse
    {
        $driverId = $request->user('dm')?->id ?? Auth::guard('dm')->id();
        $data = $request->validate([
            'package_id' => ['required', 'integer'],
            'photo' => ['required', 'string'],
            'gps_lat' => ['required', 'numeric'],
            'gps_lng' => ['required', 'numeric'],
        ]);

        $package = \App\Models\UrbanGoodzRoutePackage::where('id', $data['package_id'])
            ->whereHas('routeBatch', fn($q) => $q->where('delivery_man_id', $driverId))
            ->firstOrFail();

        $orderData = [
            'id' => $package->order_id ?? $package->id,
            'items_summary' => $package->description,
            'package_description' => $package->package_type,
            'weight' => $package->weight,
            'pickup_address' => $package->pickup_address,
            'special_instructions' => $package->special_instructions,
        ];

        $verification = $this->packageScanAI->verifyPickup($data['photo'], $orderData);

        $verification['package_id'] = $package->id;
        $verification['gps'] = ['lat' => $data['gps_lat'], 'lng' => $data['gps_lng']];

        return response()->json([
            'success' => true,
            'verification' => $verification,
        ]);
    }

    /**
     * Driver Delivery Verification
     */
    public function driverVerifyDelivery(Request $request): JsonResponse
    {
        $driverId = $request->user('dm')?->id ?? Auth::guard('dm')->id();
        $data = $request->validate([
            'package_id' => ['required', 'integer'],
            'photo' => ['required', 'string'],
            'gps_lat' => ['required', 'numeric'],
            'gps_lng' => ['required', 'numeric'],
            'recipient_name' => ['nullable', 'string'],
            'dropoff_instructions' => ['nullable', 'string'],
        ]);

        $package = \App\Models\UrbanGoodzRoutePackage::where('id', $data['package_id'])
            ->whereHas('routeBatch', fn($q) => $q->where('delivery_man_id', $driverId))
            ->firstOrFail();

        $context = [
            'order_id' => $package->order_id ?? $package->id,
            'delivery_address' => $package->delivery_address,
            'recipient_name' => $data['recipient_name'] ?? 'Customer',
            'dropoff_instructions' => $data['dropoff_instructions'] ?? $package->delivery_instructions,
            'package_description' => $package->description,
            'weather' => $this->getWeather($data['gps_lat'], $data['gps_lng']),
        ];

        $verification = $this->packageScanAI->verifyDelivery($data['photo'], $context);
        $verification['package_id'] = $package->id;
        $verification['gps'] = ['lat' => $data['gps_lat'], 'lng' => $data['gps_lng']];

        // Generate delivery proof if verified
        if (($verification['delivery_verified'] ?? false)) {
            $proof = $this->packageScanAI->generateDeliveryProof([
                'order_id' => $package->order_id ?? $package->id,
                'delivery_man_id' => $driverId,
                'photos' => [$data['photo']],
                'latitude' => $data['gps_lat'],
                'longitude' => $data['gps_lng'],
                'gps_accuracy' => $request->input('gps_accuracy'),
                'customer_signature' => $request->input('signature'),
                'signature_timestamp' => $request->input('signature_timestamp'),
                'pickup_address' => $package->pickup_address,
                'delivery_address' => $package->delivery_address,
                'instructions_followed' => true,
                'condition_assessment' => $verification,
                'verification_result' => $verification,
            ]);
            $verification['delivery_proof'] = $proof;
        }

        return response()->json([
            'success' => true,
            'verification' => $verification,
        ]);
    }

    /**
     * Driver Load Recommendations
     */
    public function driverLoadRecommendations(Request $request): JsonResponse
    {
        $driverId = $request->user('dm')?->id ?? Auth::guard('dm')->id();

        $driver = DeliveryMan::findOrFail($driverId);
        $loads = \App\Models\UrbanGoodzLoadBoardLoad::where('status', 'available')
            ->where(function ($q) use ($driver) {
                $q->where('equipment_type', $driver->vehicle_type ?? 'cargo_van')
                  ->orWhere('equipment_type', 'any');
            })
            ->where('weight_lbs', '<=', $driver->max_capacity_lbs ?? 10000)
            ->orderByDesc('payout_amount')
            ->limit(10)
            ->get();

        $loadsData = $loads->map(fn($l) => [
            'id' => $l->id,
            'load_number' => $l->load_number,
            'origin' => $l->origin_full,
            'destination' => $l->destination_full,
            'payout' => $l->payout_amount,
            'rate_per_mile' => $l->rate_per_mile,
            'equipment' => $l->equipment_type,
            'weight' => $l->weight_lbs,
            'distance' => $l->distance_miles,
        ])->toArray();

        $matchResult = $this->loadBoardNLP->matchLoadToDriver(
            $loadsData[0] ?? [],
            $driver->toArray()
        );

        return response()->json([
            'success' => true,
            'loads' => $loadsData,
            'ai_match' => $matchResult,
        ]);
    }

    /**
     * Driver Earnings Comparison
     */
    public function driverEarningsComparison(Request $request): JsonResponse
    {
        $driverId = $request->user('dm')?->id ?? Auth::guard('dm')->id();
        $data = $request->validate([
            'period' => ['nullable', 'string', 'in:week,month,year'],
        ]);

        $period = $data['period'] ?? 'week';

        $earnings = \App\Models\UrbanGoodzDriverEarning::where('dm_id', $driverId)
            ->when($period === 'week', fn($q) => $q->where('created_at', '>=', now()->subWeek()))
            ->when($period === 'month', fn($q) => $q->where('created_at', '>=', now()->subMonth()))
            ->when($period === 'year', fn($q) => $q->where('created_at', '>=', now()->subYear()))
            ->get();

        $totalEarnings = $earnings->sum('amount');
        $totalTips = $earnings->sum('tips');
        $totalOrders = $earnings->count();
        $avgPerOrder = $totalOrders > 0 ? $totalEarnings / $totalOrders : 0;
        $activeHours = $this->calculateActiveHours($driverId, $period);
        $earningsPerHour = $activeHours > 0 ? $totalEarnings / $activeHours : 0;

        $platformAvg = \App\Models\UrbanGoodzDriverEarning::where('created_at', '>=', now()->subDays($period === 'week' ? 7 : ($period === 'month' ? 30 : 365)))
            ->avg('amount') ?? 0;

        return response()->json([
            'success' => true,
            'period' => $period,
            'total_earnings' => $totalEarnings,
            'total_tips' => $totalTips,
            'total_orders' => $totalOrders,
            'avg_per_order' => round($avgPerOrder, 2),
            'active_hours' => round($activeHours, 1),
            'earnings_per_hour' => round($earningsPerHour, 2),
            'platform_avg_per_order' => round($platformAvg, 2),
            'vs_platform' => $avgPerOrder > $platformAvg ? 'above' : 'below',
            'percentile' => $this->calculatePercentile($driverId, $period),
        ]);
    }

    // ─── BUSINESS PORTAL ENDPOINTS ──────────────────────────────────────────

    /**
     * Business Portal Manifest Import
     */
    public function businessImportManifest(Request $request): JsonResponse
    {
        $businessId = $request->user('business')?->id ?? Auth::guard('business')->id();

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,xlsx,xls,pdf,eml,msg', 'max:10240'],
            'source_type' => ['required', 'string', 'in:csv,excel,pdf,email'],
            'auto_create_packages' => ['nullable', 'boolean'],
            'duplicate_check' => ['nullable', 'boolean'],
        ]);

        $file = $request->file('file');
        $content = $this->extractFileContent($file, $data['source_type']);

        $result = $this->businessAI->parseManifest($content, [
            'client_id' => $businessId,
            'source_type' => $data['source_type'],
            'auto_create' => $data['auto_create_packages'] ?? false,
            'duplicate_check' => $data['duplicate_check'] ?? true,
        ]);

        if (($result['success'] ?? false) && ($result['auto_created'] ?? false)) {
            $created = $this->createPackagesFromParsed($businessId, $result['packages']);
            $result['created_packages'] = $created;
        }

        return response()->json($result);
    }

    /**
     * Business Portal Package Pool Grouping
     */
    public function businessPackagePool(Request $request): JsonResponse
    {
        $businessId = $request->user('business')?->id ?? Auth::guard('business')->id();

        $data = $request->validate([
            'status' => ['nullable', 'string'],
            'region' => ['nullable', 'string'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $query = \App\Models\UrbanGoodzRoutePackage::where('business_client_id', $businessId)
            ->whereIn('status', ['pending', 'queued', 'awaiting_assignment']);

        if ($data['status'] ?? false) {
            $query->where('status', $data['status']);
        }
        if ($data['region'] ?? false) {
            $query->whereJsonContains('delivery_zone', $data['region']);
        }
        if ($data['date_from'] ?? false) {
            $query->whereDate('pickup_window_start', '>=', $data['date_from']);
        }
        if ($data['date_to'] ?? false) {
            $query->whereDate('pickup_window_start', '<=', $data['date_to']);
        }

        $packages = $query->with(['routeBatch', 'pickupLocation', 'deliveryLocation'])->get();

        $result = $this->businessAI->groupPackagesForRoutes($packages->toArray(), [
            'client_id' => $businessId,
            'max_route_distance' => $request->input('max_route_distance', 100),
            'max_stops_per_route' => $request->input('max_stops_per_route', 25),
            'vehicle_types' => $request->input('vehicle_types', ['sprinter', 'box_truck', 'cargo_van']),
        ]);

        return response()->json([
            'success' => true,
            'total_packages' => $packages->count(),
            'groups' => $result['groups'] ?? [],
            'unassigned' => $result['unassigned'] ?? [],
            'warnings' => $result['warnings'] ?? [],
        ]);
    }

    /**
     * Business Route Creation
     */
    public function businessCreateRoute(Request $request): JsonResponse
    {
        $businessId = $request->user('business')?->id ?? Auth::guard('business')->id();

        $data = $request->validate([
            'package_ids' => ['required', 'array', 'min:1'],
            'package_ids.*' => ['integer'],
            'vehicle_type' => ['required', 'string', 'in:sprinter,box_truck,cargo_van,step_deck,flatbed'],
            'driver_id' => ['nullable', 'integer'],
            'route_name' => ['nullable', 'string', 'max:100'],
            'dedicated' => ['nullable', 'boolean'],
            'recurring' => ['nullable', 'boolean'],
            'recurrence_pattern' => ['nullable', 'string', 'in:daily,weekdays,weekly,custom'],
        ]);

        $packages = \App\Models\UrbanGoodzRoutePackage::whereIn('id', $data['package_ids'])
            ->where('business_client_id', $businessId)
            ->whereIn('status', ['pending', 'queued', 'awaiting_assignment'])
            ->get();

        if ($packages->count() !== count($data['package_ids'])) {
            return response()->json([
                'success' => false,
                'message' => 'Some packages not found or not available for routing',
            ], 422);
        }

        $result = $this->businessAI->optimizeRoute($packages->toArray(), [
            'vehicle_type' => $data['vehicle_type'],
            'driver_id' => $data['driver_id'],
            'dedicated' => $data['dedicated'] ?? false,
            'recurring' => $data['recurring'] ?? false,
        ]);

        if (!($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Route optimization failed',
                'details' => $result,
            ], 422);
        }

        $routeBatch = \App\Models\UrbanGoodzRouteBatch::create([
            'business_client_id' => $businessId,
            'batch_number' => 'RB-' . now()->format('YmdHis') . '-' . random_int(1000, 9999),
            'route_name' => $data['route_name'] ?? 'Route ' . now()->format('M j, g:i A'),
            'vehicle_type' => $data['vehicle_type'],
            'delivery_man_id' => $data['driver_id'],
            'status' => $data['driver_id'] ? 'assigned' : 'open',
            'dedicated' => $data['dedicated'] ?? false,
            'recurring' => $data['recurring'] ?? false,
            'recurrence_pattern' => $data['recurrence_pattern'],
            'total_distance_miles' => $result['distance_miles'] ?? 0,
            'estimated_duration_minutes' => $result['estimated_time_minutes'] ?? 0,
            'package_count' => $packages->count(),
            'optimized_stops' => $result['optimized_stops'] ?? [],
            'ai_confidence' => $result['confidence'] ?? 0,
            'ai_explanation' => $result['explanation'] ?? null,
            'created_by' => Auth::guard('business')->id(),
        ]);

        $packages->each(function ($pkg) use ($routeBatch, $result) {
            $stop = collect($result['optimized_stops'] ?? [])->firstWhere('package_id', $pkg->id);
            $pkg->update([
                'route_batch_id' => $routeBatch->id,
                'status' => 'assigned',
                'sequence_number' => $stop['sequence'] ?? null,
                'assigned_at' => now(),
            ]);
        });

        return response()->json([
            'success' => true,
            'route' => $routeBatch->fresh(['packages', 'deliveryMan']),
            'optimization' => $result,
        ]);
    }

    // ─── DISPATCHER ENDPOINTS ──────────────────────────────────────────────

    /**
     * Dispatcher Load Ranking
     */
    public function dispatcherLoadRanking(Request $request): JsonResponse
    {
        $data = $request->validate([
            'filters' => ['nullable', 'array'],
            'driver_id' => ['nullable', 'integer'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = \App\Models\UrbanGoodzLoadBoardLoad::where('status', 'available');

        if (!empty($data['filters'])) {
            $f = $data['filters'];
            if (!empty($f['origin_state'])) $query->where('origin_state', $f['origin_state']);
            if (!empty($f['destination_state'])) $query->where('destination_state', $f['destination_state']);
            if (!empty($f['equipment_type'])) $query->where('equipment_type', $f['equipment_type']);
            if (!empty($f['min_rate'])) $query->where('payout_amount', '>=', $f['min_rate']);
            if (!empty($f['max_rate'])) $query->where('payout_amount', '<=', $f['max_rate']);
            if (!empty($f['max_weight'])) $query->where('weight_lbs', '<=', $f['max_weight']);
        }

        $loads = $query->orderByDesc('created_at')
            ->limit($data['limit'] ?? 50)
            ->get();

        $driverId = $data['driver_id'] ?? null;
        $driver = $driverId ? \App\Models\DeliveryMan::find($driverId) : null;

        $ranked = [];
        foreach ($loads as $load) {
            $match = null;
            if ($driver) {
                $matchResult = $this->loadBoardNLP->matchLoadToDriver(
                    $load->toArray(),
                    [$driver->toArray()]
                );
                $match = $matchResult['rankings'][0] ?? null;
            }

            $rateAnalysis = $this->loadBoardNLP->estimateFairRate($load->toArray());

            $ranked[] = [
                'load' => [
                    'id' => $load->id,
                    'load_number' => $load->load_number,
                    'origin' => $load->origin_full,
                    'destination' => $load->destination_full,
                    'equipment' => $load->equipment_type,
                    'weight' => $load->weight_lbs,
                    'payout' => $load->payout_amount,
                    'rate_per_mile' => $load->rate_per_mile,
                    'distance' => $load->distance_miles,
                ],
                'driver_match' => $match,
                'fair_rate' => $rateAnalysis,
                'margin_estimate' => $match && $rateAnalysis['estimated_rate'] ?? null
                    ? round((($load->payout_amount - $rateAnalysis['estimated_rate']) / $load->payout_amount) * 100, 1)
                    : null,
            ];
        }

        usort($ranked, fn($a, $b) => ($b['driver_match']['score'] ?? 0) <=> ($a['driver_match']['score'] ?? 0));

        return response()->json([
            'success' => true,
            'ranked_loads' => $ranked,
            'total_evaluated' => count($ranked),
        ]);
    }

    /**
     * Dispatcher Driver Match
     */
    public function dispatcherDriverMatch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'load_id' => ['required', 'integer'],
            'driver_ids' => ['nullable', 'array'],
            'driver_ids.*' => ['integer'],
        ]);

        $load = \App\Models\UrbanGoodzLoadBoardLoad::findOrFail($data['load_id']);

        $drivers = \App\Models\DeliveryMan::where('active', 1)
            ->where('application_status', 'approved');

        if (!empty($data['driver_ids'])) {
            $drivers->whereIn('id', $data['driver_ids']);
        }

        $drivers = $drivers->get();

        if ($drivers->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No available drivers found',
            ], 404);
        }

        $matchResult = $this->loadBoardNLP->matchLoadToDriver(
            $load->toArray(),
            $drivers->toArray()
        );

        return response()->json([
            'success' => true,
            'load_id' => $load->id,
            'load_number' => $load->load_number,
            'recommendations' => $matchResult['rankings'] ?? [],
            'recommended_driver_id' => $matchResult['recommended_driver_id'] ?? null,
            'notes' => $matchResult['notes'] ?? null,
        ]);
    }

    /**
     * Dispatcher Rate Estimate
     */
    public function dispatcherRateEstimate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'origin_city' => ['required', 'string'],
            'origin_state' => ['required', 'string', 'size:2'],
            'destination_city' => ['required', 'string'],
            'destination_state' => ['required', 'string', 'size:2'],
            'equipment_type' => ['required', 'string'],
            'weight_lbs' => ['nullable', 'numeric'],
            'load_type' => ['nullable', 'string'],
            'is_hazmat' => ['nullable', 'boolean'],
            'is_expedited' => ['nullable', 'boolean'],
        ]);

        $result = $this->loadBoardNLP->estimateFairRate($data);

        return response()->json([
            'success' => true,
            'estimate' => $result,
        ]);
    }

    /**
     * Dispatcher Duplicate Check
     */
    public function dispatcherDuplicateCheck(Request $request): JsonResponse
    {
        $data = $request->validate([
            'load_data' => ['required', 'array'],
        ]);

        $result = $this->loadBoardNLP->detectDuplicates($data['load_data']);

        return response()->json([
            'success' => true,
            'duplicates' => $result,
        ]);
    }

    /**
     * Dispatcher Ops Summary
     */
    public function dispatcherOpsSummary(Request $request): JsonResponse
    {
        $summary = $this->aiService->generateOpsSummary([]);

        return response()->json([
            'success' => true,
            'summary' => $summary,
        ]);
    }

    /**
     * Dispatcher Parse Load from Text/Email
     */
    public function dispatcherParseLoad(Request $request): JsonResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string'],
            'source' => ['nullable', 'string', 'in:manual,email,api'],
        ]);

        $result = $this->loadBoardNLP->parseLoadFromText($data['text']);

        return response()->json([
            'success' => true,
            'parsed' => $result,
        ]);
    }

    /**
     * Dispatcher Parse Email
     */
    public function dispatcherParseEmail(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email_body' => ['required', 'string'],
            'from_address' => ['nullable', 'string', 'email'],
            'subject' => ['nullable', 'string'],
        ]);

        $result = $this->loadBoardNLP->parseLoadFromEmail($data['email_body'], $data['from_address'] ?? '');

        return response()->json([
            'success' => true,
            'parsed' => $result,
        ]);
    }

    /**
     * Dispatcher Parse Batch
     */
    public function dispatcherParseBatch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string'],
        ]);

        $result = $this->loadBoardNLP->parseBatchLoads($data['text']);

        return response()->json([
            'success' => true,
            'parsed' => $result,
        ]);
    }

    /**
     * Dispatcher Source Status
     */
    public function dispatcherSourceStatus(Request $request): JsonResponse
    {
        $sources = [
            'internal' => ['name' => 'Internal', 'adapter' => 'InternalLoadAdapter', 'status' => 'active'],
            'manual' => ['name' => 'Manual Entry', 'adapter' => null, 'status' => 'active'],
            'email' => ['name' => 'Email Ingestion', 'adapter' => 'EmailLoadAdapter', 'status' => 'active'],
            'dat' => ['name' => 'DAT', 'adapter' => 'DatAdapter', 'status' => 'configured'],
            'truckstop' => ['name' => 'Truckstop', 'adapter' => 'TruckstopAdapter', 'status' => 'configured'],
            'trulos' => ['name' => 'Trulos', 'adapter' => 'TrulosAdapter', 'status' => 'pending'],
            'tb_load' => ['name' => 'TB Load', 'adapter' => 'TbLoadAdapter', 'status' => 'pending'],
            'direct_freight' => ['name' => 'Direct Freight', 'adapter' => 'DirectFreightAdapter', 'status' => 'pending'],
            'trucker_path' => ['name' => 'Trucker Path', 'adapter' => 'TruckerPathAdapter', 'status' => 'pending'],
            'truck_smarter' => ['name' => 'TruckSmarter', 'adapter' => 'TruckSmarterAdapter', 'status' => 'pending'],
        ];

        foreach ($sources as $key => &$source) {
            if ($source['adapter']) {
                $source['last_sync'] = $this->loadBoardService->getLastSync($key);
                $source['sync_status'] = $source['last_sync'] ? 'ok' : 'never';
                $source['rate_limit'] = $this->loadBoardService->getRateLimit($key);
            }
        }

        return response()->json([
            'success' => true,
            'sources' => $sources,
        ]);
    }

    /**
     * Dispatcher Sync Source
     */
    public function dispatcherSyncSource(Request $request): JsonResponse
    {
        $data = $request->validate([
            'source' => ['required', 'string'],
            'full_sync' => ['nullable', 'boolean'],
        ]);

        if (!in_array($data['source'], ['dat', 'truckstop', 'email'])) {
            return response()->json([
                'success' => false,
                'message' => 'Source not yet implemented for auto-sync',
            ], 422);
        }

        $result = $this->loadBoardService->syncFromProvider($data['source'], $data['full_sync'] ?? false);

        return response()->json([
            'success' => true,
            'source' => $data['source'],
            'result' => $result,
        ]);
    }

    // ─── HELPERS ────────────────────────────────────────────────────────────

    private function extractFileContent($file, string $type): string
    {
        switch ($type) {
            case 'csv':
                return $file->get();
            case 'excel':
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getPathname());
                return json_encode($spreadsheet->getActiveSheet()->toArray());
            case 'pdf':
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($file->getPathname());
                return $pdf->getText();
            case 'email':
                return $file->get();
            default:
                return $file->get();
        }
    }

    private function createPackagesFromParsed($businessId, array $parsed): array
    {
        $created = [];
        foreach ($parsed as $pkg) {
            $package = \App\Models\UrbanGoodzRoutePackage::create([
                'business_client_id' => $businessId,
                'tracking_number' => $pkg['tracking_number'] ?? 'TRK-' . uniqid(),
                'pickup_address' => $pkg['pickup_address'] ?? '',
                'delivery_address' => $pkg['delivery_address'] ?? '',
                'pickup_lat' => $pkg['pickup_lat'] ?? null,
                'pickup_lng' => $pkg['pickup_lng'] ?? null,
                'delivery_lat' => $pkg['delivery_lat'] ?? null,
                'delivery_lng' => $pkg['delivery_lng'] ?? null,
                'pickup_window_start' => $pkg['pickup_window_start'] ?? now(),
                'pickup_window_end' => $pkg['pickup_window_end'] ?? now()->addHours(2),
                'delivery_window_start' => $pkg['delivery_window_start'] ?? now()->addHours(4),
                'delivery_window_end' => $pkg['delivery_window_end'] ?? now()->addHours(6),
                'weight' => $pkg['weight'] ?? 0,
                'dimensions' => $pkg['dimensions'] ?? null,
                'package_type' => $pkg['package_type'] ?? 'parcel',
                'requires_signature' => $pkg['requires_signature'] ?? false,
                'requires_age_verification' => $pkg['requires_age_verification'] ?? false,
                'requires_refrigeration' => $pkg['requires_refrigeration'] ?? false,
                'is_hazardous' => $pkg['is_hazardous'] ?? false,
                'service_time_minutes' => $pkg['service_time_minutes'] ?? 15,
                'priority' => $pkg['priority'] ?? 'normal',
                'description' => $pkg['description'] ?? '',
                'special_instructions' => $pkg['special_instructions'] ?? '',
                'status' => 'pending',
                'metadata' => $pkg,
            ]);
            $created[] = $package;
        }
        return $created;
    }

    private function getWeather(float $lat, float $lng): string
    {
        return 'Clear, 72°F'; // Stub
    }

    private function calculateActiveHours(int $driverId, string $period): float
    {
        $start = match($period) {
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            default => now()->subYear(),
        };

        $earnings = \App\Models\UrbanGoodzDriverEarning::where('dm_id', $driverId)
            ->where('created_at', '>=', $start)
            ->get();

        if ($earnings->isEmpty()) return 0;

        $first = $earnings->min('created_at');
        $last = $earnings->max('created_at');
        return max(0, $first->diffInHours($last));
    }

    private function calculatePercentile(int $driverId, string $period): int
    {
        $start = match($period) {
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            default => now()->subYear(),
        };

        $driverAvg = \App\Models\UrbanGoodzDriverEarning::where('dm_id', $driverId)
            ->where('created_at', '>=', $start)
            ->avg('amount') ?? 0;

        $allAvgs = \App\Models\UrbanGoodzDriverEarning::where('created_at', '>=', $start)
            ->groupBy('dm_id')
            ->selectRaw('dm_id, AVG(amount) as avg_earning')
            ->pluck('avg_earning')
            ->toArray();

        if (empty($allAvgs)) return 50;

        $below = count(array_filter($allAvgs, fn($a) => $a < $driverAvg));
        return round(($below / count($allAvgs)) * 100);
    }

    private function getRiskLevel(int $score): string
    {
        if ($score >= 70) return 'critical';
        if ($score >= 40) return 'high';
        if ($score >= 20) return 'medium';
        return 'low';
    }

    private function getRecommendation(string $level, array $flags): string
    {
        return match ($level) {
            'critical' => 'IMMEDIATE ACTION REQUIRED: Block transaction, freeze account, escalate to security team immediately.',
            'high' => 'HIGH RISK: Review manually within 1 hour. Consider blocking transaction and flagging account for review.',
            'medium' => 'MODERATE RISK: Review within 4 hours. Monitor account activity closely.',
            'low' => 'LOW RISK: Log for pattern analysis. No immediate action required.',
        };
    }
}