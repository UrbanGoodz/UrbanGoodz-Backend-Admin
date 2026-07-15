<?php

namespace App\Services\UrbanGoodz;

use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Models\Vendor;
use App\Models\DeliveryMan;
use App\Models\UrbanGoodzAIIntent;
use App\Models\UrbanGoodzMedicalCourierJob;
use App\Models\UrbanGoodzLoadBoardLoad;
use App\Models\UrbanGoodzRentalAsset;
use App\Models\UrbanGoodzServiceProvider;
use App\Models\UrbanGoodzCommunityPost;
use App\Models\UrbanGoodzCommunityMarketplaceItem;
use App\Models\UrbanGoodzCreatorProfile;
use App\Models\UrbanGoodzCreatorCampaign;
use App\Models\UrbanGoodzEarnMoneyOpportunity;
use App\Models\UrbanGoodzEvent;
use App\Models\UrbanGoodzActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UrbanGoodzAIExecutionService
{
    private UrbanGoodzAIService $ai;
    private UrbanGoodzModuleRouter $router;
    private UrbanGoodzAIConciergeService $concierge;
    private AllowedActionRegistry $actionRegistry;
    private AIActionValidator $actionValidator;

    public function __construct(
        UrbanGoodzAIService $ai,
        UrbanGoodzModuleRouter $router,
        UrbanGoodzAIConciergeService $concierge,
        AllowedActionRegistry $actionRegistry,
        AIActionValidator $actionValidator
    ) {
        $this->ai = $ai;
        $this->router = $router;
        $this->concierge = $concierge;
        $this->actionRegistry = $actionRegistry;
        $this->actionValidator = $actionValidator;
    }

    // ─── MAIN ENTRY POINT ─────────────────────────────────────────────

    public function executeIntent(string $query, int $customerId = null): array
    {
        $startTime = microtime(true);

        try {
            $possibleIntents = UrbanGoodzAIIntent::where('is_active', true)
                ->get()
                ->map(fn($i) => ['slug' => $i->slug, 'description' => $i->description ?? $i->name])
                ->toArray();

            $classification = $this->ai->classifyIntent($query, $possibleIntents);
            $intentSlug = $classification['intent'] ?? 'unknown';
            $confidence = $classification['confidence'] ?? 0.0;
            $entities = $classification['entities'] ?? [];

            $customerContext = $customerId ? $this->buildCustomerContext($customerId) : [];
            $routeResult = $this->router->route($intentSlug, $entities, $customerContext, $customerId);

            if (!$routeResult['success']) {
                return $this->buildResult(false, [
                    'message' => $routeResult['fallback_message'] ?? $routeResult['message'] ?? 'I could not determine what you need. Could you rephrase?',
                    'intent' => $intentSlug,
                    'confidence' => $confidence,
                    'entities' => $entities,
                ], $this->getGenericNextSteps(), $this->getGenericUiHints());
            }

            $moduleKey = $routeResult['module'];
            $actionName = $routeResult['actions'][0]['action'] ?? null;
            $params = $routeResult['actions'][0]['params'] ?? [];

            if (!$actionName) {
                return $this->buildResult(false, [
                    'message' => 'No action specified for this intent.',
                    'intent' => $intentSlug,
                    'confidence' => $confidence,
                ], [], []);
            }

            $validationResult = $this->actionRegistry->validateUserCanExecute($intentSlug, $actionName, $customerId);
            
            if (!$validationResult['allowed']) {
                Log::warning('UrbanGoodzAIExecutionService: Action blocked by authorization', [
                    'intent' => $intentSlug,
                    'action' => $actionName,
                    'customer_id' => $customerId,
                    'reason' => $validationResult['reason'],
                ]);
                
                return $this->buildResult(false, [
                    'message' => 'You are not authorized to perform this action.',
                    'intent' => $intentSlug,
                    'confidence' => $confidence,
                    'blocked_reason' => $validationResult['reason'],
                ], [], []);
            }

            $executionResult = $this->executeWithSafeguards(
                $moduleKey,
                $actionName,
                $params,
                $validationResult,
                $query,
                $entities,
                $intentSlug,
                $confidence,
                $customerId,
                $startTime
            );

            return $executionResult;

        } catch (\Exception $e) {
            Log::error('UrbanGoodzAIExecutionService: executeIntent failed', [
                'query' => $query,
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->buildResult(false, [
                'message' => 'I encountered an unexpected error processing your request. Our team has been notified.',
                'error' => $e->getMessage(),
            ], [
                ['label' => 'Try again', 'action' => 'retry'],
                ['label' => 'Contact support', 'action' => 'contact_support'],
            ]);
        }
    }

    private function executeWithSafeguards(
        string $moduleKey,
        string $actionName,
        array $params,
        array $validationResult,
        string $query,
        array $entities,
        string $intentSlug,
        float $confidence,
        ?int $customerId,
        float $startTime
    ): array {
        $requiresConfirmation = $validationResult['requires_confirmation'] ?? false;
        $requiresHumanReview = $validationResult['requires_human_review'] ?? false;
        $idempotencyKey = $validationResult['idempotency_key'] ?? null;

        $awaitingConfirmation = $requiresConfirmation && ($params['confirmed'] ?? false) !== true;
        $awaitingReview = $requiresHumanReview && ($params['reviewed'] ?? false) !== true;

        if ($awaitingConfirmation) {
            return $this->buildResult(true, [
                'message' => 'This action requires your confirmation before proceeding.',
                'intent' => $intentSlug,
                'confidence' => $confidence,
                'entities' => $entities,
                'proposed_action' => [
                    'module' => $moduleKey,
                    'action' => $actionName,
                    'params' => $params,
                ],
                'awaiting_confirmation' => true,
                'idempotency_key' => $idempotencyKey,
            ], [
                ['label' => 'Confirm', 'action' => 'confirm_action', 'params' => ['idempotency_key' => $idempotencyKey]],
                ['label' => 'Cancel', 'action' => 'cancel_action'],
            ], ['show_confirmation_dialog' => true]);
        }

        if ($awaitingReview) {
            return $this->buildResult(true, [
                'message' => 'This action requires human review before proceeding. A team member will review and approve.',
                'intent' => $intentSlug,
                'confidence' => $confidence,
                'entities' => $entities,
                'proposed_action' => [
                    'module' => $moduleKey,
                    'action' => $actionName,
                    'params' => $params,
                ],
                'awaiting_review' => true,
                'idempotency_key' => $idempotencyKey,
            ], [
                ['label' => 'View Status', 'action' => 'view_review_status'],
            ], ['show_review_pending' => true]);
        }

        $elapsed = round((microtime(true) - $startTime) * 1000, 1);

        $executionResult = match ($moduleKey) {
            'orderAnywhere'      => $this->executeOrderAnywhere($params),
            'fashionFit'         => $this->executeFashionFit($params),
            'bookServices'       => $this->executeBookServices($params),
            'rentals'            => $this->executeRentals($params),
            'marketplaceSearch'  => $this->executeMarketplaceSearch($params),
            'medicalCourier'     => $this->executeMedicalCourier($params),
            'loadBoard'          => $this->executeLoadBoard($params),
            'delivery'           => $this->executeDelivery($params),
            'creatorCommerce'    => $this->executeCreatorCommerce($params),
            'community'          => $this->executeCommunity($params),
            'earnMoney'          => $this->executeEarnMoney($params),
            'events'             => $this->executeEvents($params),
            default              => $this->buildResult(false, [
                'message' => "Module '{$moduleKey}' is not yet available for execution.",
            ], [], []),
        };

        $this->logAction([
            'event' => 'intent_executed',
            'intent_slug' => $intentSlug,
            'module' => $moduleKey,
            'action' => $actionName,
            'customer_id' => $customerId,
            'confidence' => $confidence,
            'success' => $executionResult['success'] ?? false,
            'execution_time_ms' => $elapsed,
            'metadata' => [
                'query' => $query,
                'entities' => $entities,
                'params' => $params,
                'idempotency_key' => $idempotencyKey,
                'required_confirmation' => $requiresConfirmation,
                'required_human_review' => $requiresHumanReview,
            ],
        ]);

        $executionResult['intent'] = $intentSlug;
        $executionResult['confidence'] = $confidence;
        $executionResult['entities'] = $entities;
        $executionResult['execution_time_ms'] = $elapsed;
        $executionResult['idempotency_key'] = $idempotencyKey;

        return $executionResult;
    }

    // ─── ORDER ANYWHERE ───────────────────────────────────────────────

    public function executeOrderAnywhere(array $params): array
    {
        try {
            $nlpService = app(OrderAnywhereNLPService::class);
            $queryText = $params['store_name'] ?? $params['items'] ?? '';
            $parsed = $nlpService->parseFromText($queryText, $params);

            $storeName = $parsed['parsed']['store_vendor_name'] ?? $params['store_name'] ?? null;
            $storeFound = false;
            $matchedStores = [];
            $orderId = null;

            if ($storeName) {
                $matchedStores = Store::where('name', 'LIKE', "%{$storeName}%")
                    ->where('status', 1)
                    ->where('active', true)
                    ->limit(5)
                    ->get()
                    ->map(fn($s) => [
                        'id' => $s->id,
                        'name' => $s->name,
                        'address' => $s->address,
                        'rating' => $s->rating,
                        'delivery' => $s->delivery,
                        'minimum_order' => $s->minimum_order,
                    ])
                    ->toArray();

                $storeFound = count($matchedStores) === 1;
            }

            if ($storeFound) {
                $store = $matchedStores[0];
                $itemDetails = $parsed['parsed']['item_details'] ?? $params['items'] ?? 'Items from ' . $storeName;
                $deliveryAddress = $parsed['parsed']['customer_phone'] ?? $params['delivery_address'] ?? null;
                $budgetEstimate = $parsed['parsed']['budget_estimate'] ?? $params['budget_max'] ?? null;

                $requestNumber = 'OAW-' . strtoupper(uniqid());
                $orderId = DB::table('orders')->insertGetId([
                    'user_id' => $params['customer_id'] ?? null,
                    'order_number' => $requestNumber,
                    'store_id' => $store['id'],
                    'delivery_address' => $deliveryAddress,
                    'order_amount' => $budgetEstimate ?? 0,
                    'order_status' => 'requested',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $estimatedDelivery = now()->addHours(2)->format('g:i A');
                $estimatedTotal = $budgetEstimate ? '$' . number_format($budgetEstimate, 2) : 'Pending store confirmation';

                return $this->buildResult(true, [
                    'request_id' => $orderId,
                    'request_number' => $requestNumber,
                    'store' => $store,
                    'item_details' => $itemDetails,
                    'estimated_total' => $estimatedTotal,
                    'estimated_delivery_time' => $estimatedDelivery,
                    'store_found' => true,
                ], [
                    ['label' => 'Confirm order', 'action' => 'confirm_order', 'params' => ['request_id' => $orderId]],
                    ['label' => 'Add more items', 'action' => 'add_items', 'params' => ['request_id' => $orderId]],
                    ['label' => 'Change store', 'action' => 'change_store'],
                ], [
                    'show_store_details' => true,
                    'show_order_summary' => true,
                    'show_delivery_estimate' => true,
                ]);
            }

            if (!empty($parsed['follow_up_prompts'])) {
                return $this->buildResult(true, [
                    'store_found' => false,
                    'message' => 'I found the store but need a few more details to complete your order.',
                    'parsed_order' => $parsed['parsed'],
                    'matched_stores' => $matchedStores,
                    'missing' => $parsed['missing'],
                ], $parsed['follow_up_prompts'], [
                    'show_follow_up' => true,
                    'show_store_selection' => count($matchedStores) > 1,
                ]);
            }

            $suggestions = $nlpService->suggestSubstitutions(
                $parsed['parsed']['items'] ?? [],
                $storeName ?? ''
            );

            return $this->buildResult(true, [
                'store_found' => false,
                'message' => "I couldn't find an exact match for \"{$storeName}\". Here are some alternatives:",
                'matched_stores' => $matchedStores,
                'parsed_order' => $parsed['parsed'],
                'suggestions' => $suggestions,
            ], [
                ['label' => 'Select a store', 'action' => 'select_store', 'params' => ['stores' => $matchedStores]],
                ['label' => 'Search for a different store', 'action' => 'search_store'],
                ['label' => 'Enter store details manually', 'action' => 'manual_store_entry'],
            ], [
                'show_store_suggestions' => true,
                'show_alternative_stores' => true,
            ]);

        } catch (\Exception $e) {
            Log::error('UrbanGoodzAIExecutionService: executeOrderAnywhere failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);

            return $this->buildResult(false, [
                'message' => 'I had trouble processing your Order Anywhere request. Please try again or enter the details manually.',
            ], [
                ['label' => 'Try again', 'action' => 'retry'],
                ['label' => 'Enter manually', 'action' => 'manual_order'],
            ]);
        }
    }

    // ─── FASHION FIT ──────────────────────────────────────────────────

    public function executeFashionFit(array $params): array
    {
        try {
            $photoData = $params['photo'] ?? $params['photo_data'] ?? null;
            $measurements = null;
            $extractedMeasurements = null;

            if ($photoData) {
                $fitService = app(FashionFitAIService::class);
                $photoResult = $fitService->extractMeasurementsFromPhoto($photoData, [
                    'garment_type' => $params['garment_type'] ?? null,
                    'style_notes' => $params['description'] ?? null,
                ]);

                if ($photoResult['success'] ?? false) {
                    $extractedMeasurements = $photoResult['measurements'] ?? null;
                    $measurements = $extractedMeasurements;
                }
            }

            $providers = Vendor::where('type', 'fashion_fit_provider')
                ->where('is_active', true)
                ->limit(10)
                ->get()
                ->map(fn($v) => [
                    'id' => $v->id,
                    'name' => $v->name,
                    'phone' => $v->phone,
                    'email' => $v->email,
                ])
                ->toArray();

            if ($providers === []) {
                $providers = UrbanGoodzServiceProvider::where('service_category', 'LIKE', '%fashion%')
                    ->orWhere('service_category', 'LIKE', '%tailor%')
                    ->where('is_active', true)
                    ->limit(10)
                    ->get()
                    ->map(fn($p) => [
                        'id' => $p->id,
                        'name' => $p->business_name,
                        'category' => $p->service_category,
                        'rating' => $p->rating,
                    ])
                    ->toArray();
            }

            $measurementRequestId = null;
            if ($customerId = $params['customer_id']) {
                $measurementRequestId = DB::table('urban_goodz_measurement_requests')->insertGetId([
                    'customer_id' => $customerId,
                    'preferred_fit' => $params['service_type'] ?? 'regular',
                    'height' => $extractedMeasurements['height'] ?? null,
                    'chest_bust' => $extractedMeasurements['chest'] ?? null,
                    'waist' => $extractedMeasurements['waist'] ?? null,
                    'hips' => $extractedMeasurements['hips'] ?? null,
                    'inseam' => $extractedMeasurements['inseam'] ?? null,
                    'shoulder_width' => $extractedMeasurements['shoulders'] ?? null,
                    'source' => $photoData ? 'ai_photo' : 'manual',
                    'item_wanted' => $params['garment_type'] ?? null,
                    'request_type' => $params['service_type'] ?? null,
                    'budget' => $params['budget'] ?? null,
                    'measurement_status' => $extractedMeasurements ? 'completed' : 'not_started',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $nextSteps = [];
            if ($extractedMeasurements) {
                $nextSteps[] = ['label' => 'Review measurements', 'action' => 'review_measurements', 'params' => ['request_id' => $measurementRequestId]];
                $nextSteps[] = ['label' => 'Match to a stylist', 'action' => 'match_stylist'];
            } else {
                $nextSteps[] = ['label' => 'Upload a photo', 'action' => 'upload_photo'];
                $nextSteps[] = ['label' => 'Enter measurements manually', 'action' => 'manual_measurements'];
            }

            return $this->buildResult(true, [
                'profile_id' => $measurementRequestId,
                'measurements' => $extractedMeasurements,
                'photo_analyzed' => $photoData !== null,
                'matched_providers' => $providers,
                'request_status' => $extractedMeasurements ? 'measurements_ready' : 'awaiting_measurements',
            ], $nextSteps, [
                'show_measurements' => $extractedMeasurements !== null,
                'show_provider_list' => true,
                'show_photo_upload' => $extractedMeasurements === null,
            ]);

        } catch (\Exception $e) {
            Log::error('UrbanGoodzAIExecutionService: executeFashionFit failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);

            return $this->buildResult(false, [
                'message' => 'I had trouble processing your Fashion Fit request.',
            ], [
                ['label' => 'Try again', 'action' => 'retry'],
                ['label' => 'Contact support', 'action' => 'contact_support'],
            ]);
        }
    }

    // ─── BOOK SERVICES ────────────────────────────────────────────────

    public function executeBookServices(array $params): array
    {
        try {
            $serviceName = $params['service_name'] ?? $params['category'] ?? null;
            $location = $params['location'] ?? null;
            $matchedProviders = [];

            if ($serviceName) {
                $query = UrbanGoodzServiceProvider::where('is_active', true);

                $query->where(function ($q) use ($serviceName) {
                    $q->where('service_category', 'LIKE', "%{$serviceName}%")
                      ->orWhere('business_name', 'LIKE', "%{$serviceName}%")
                      ->orWhere('description', 'LIKE', "%{$serviceName}%");
                });

                if ($location) {
                    $query->where(function ($q) use ($location) {
                        $q->whereJsonContains('service_areas', $location)
                          ->orWhere('service_areas', 'LIKE', "%{$location}%");
                    });
                }

                $matchedProviders = $query->limit(10)
                    ->get()
                    ->map(fn($p) => [
                        'id' => $p->id,
                        'name' => $p->business_name,
                        'category' => $p->service_category,
                        'rating' => $p->rating,
                        'is_verified' => $p->is_verified,
                        'service_areas' => $p->service_areas,
                    ])
                    ->toArray();
            }

            $requestId = null;
            if ($customerId = $params['customer_id']) {
                $requestNumber = 'BA-' . strtoupper(uniqid());
                $requestId = DB::table('urban_goodz_book_anywhere_requests')->insertGetId([
                    'request_number' => $requestNumber,
                    'customer_id' => $customerId,
                    'service_name' => $serviceName,
                    'description' => $params['description'] ?? null,
                    'preferred_date' => $params['preferred_date'] ?? null,
                    'preferred_time' => $params['preferred_time'] ?? null,
                    'location' => $location,
                    'budget_amount' => $params['budget_amount'] ?? null,
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $estimatedPricing = null;
            if ($params['budget_amount']) {
                $estimatedPricing = [
                    'budget' => '$' . number_format($params['budget_amount'], 2),
                    'note' => 'Pricing will be confirmed by the service provider.',
                ];
            }

            return $this->buildResult(true, [
                'request_id' => $requestId,
                'request_number' => $requestId ? 'BA-' . strtoupper(uniqid()) : null,
                'matched_providers' => $matchedProviders,
                'estimated_pricing' => $estimatedPricing,
                'service_name' => $serviceName,
            ], [
                ['label' => 'Select provider', 'action' => 'select_provider'],
                ['label' => 'View provider details', 'action' => 'view_provider'],
                ['label' => 'Request quote', 'action' => 'request_quote'],
                ['label' => 'Change search criteria', 'action' => 'refine_search'],
            ], [
                'show_provider_list' => true,
                'show_budget' => isset($params['budget_amount']),
            ]);

        } catch (\Exception $e) {
            Log::error('UrbanGoodzAIExecutionService: executeBookServices failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);

            return $this->buildResult(false, [
                'message' => 'I had trouble finding service providers for your request.',
            ], [
                ['label' => 'Try again', 'action' => 'retry'],
                ['label' => 'Browse services', 'action' => 'browse_services'],
            ]);
        }
    }

    // ─── RENTALS ──────────────────────────────────────────────────────

    public function executeRentals(array $params): array
    {
        try {
            $query = UrbanGoodzRentalAsset::where('is_active', true)
                ->where('status', 'available');

            if (!empty($params['asset_type'])) {
                $query->where('asset_type', 'LIKE', "%{$params['asset_type']}%");
            }

            if (!empty($params['make'])) {
                $query->where('make', 'LIKE', "%{$params['make']}%");
            }

            if (!empty($params['model'])) {
                $query->where('model', 'LIKE', "%{$params['model']}%");
            }

            if (!empty($params['location'])) {
                $query->where(function ($q) use ($params) {
                    $q->where('pickup_location', 'LIKE', "%{$params['location']}%")
                      ->orWhere('return_location', 'LIKE', "%{$params['location']}%");
                });
            }

            if (!empty($params['max_daily_rate'])) {
                $query->where('daily_rate', '<=', $params['max_daily_rate']);
            }

            $assets = $query->limit(20)
                ->get()
                ->map(fn($a) => [
                    'id' => $a->id,
                    'title' => $a->title,
                    'description' => $a->description,
                    'asset_type' => $a->asset_type,
                    'make' => $a->make,
                    'model' => $a->model,
                    'year' => $a->year,
                    'daily_rate' => '$' . number_format($a->daily_rate, 2),
                    'hourly_rate' => $a->hourly_rate ? '$' . number_format($a->hourly_rate, 2) : null,
                    'deposit_amount' => '$' . number_format($a->deposit_amount, 2),
                    'pickup_location' => $a->pickup_location,
                    'return_location' => $a->return_location,
                ])
                ->toArray();

            $estimatedPricing = null;
            if (!empty($assets)) {
                $dailyRates = array_map(fn($a) => (float) str_replace('$', '', str_replace(',', '', $a['daily_rate'])), $assets);
                $estimatedPricing = [
                    'min_daily_rate' => '$' . number_format(min($dailyRates), 2),
                    'max_daily_rate' => '$' . number_format(max($dailyRates), 2),
                    'note' => 'Rates may vary based on rental duration and availability.',
                ];
            }

            return $this->buildResult(true, [
                'matched_assets' => $assets,
                'total_found' => count($assets),
                'estimated_pricing' => $estimatedPricing,
            ], [
                ['label' => 'Book rental', 'action' => 'book_rental'],
                ['label' => 'View asset details', 'action' => 'view_asset'],
                ['label' => 'Compare options', 'action' => 'compare_assets'],
                ['label' => 'Refine search', 'action' => 'refine_search'],
            ], [
                'show_asset_list' => true,
                'show_pricing' => true,
            ]);

        } catch (\Exception $e) {
            Log::error('UrbanGoodzAIExecutionService: executeRentals failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);

            return $this->buildResult(false, [
                'message' => 'I had trouble searching for rental options.',
            ], [
                ['label' => 'Try again', 'action' => 'retry'],
            ]);
        }
    }

    // ─── MARKETPLACE SEARCH ───────────────────────────────────────────

    public function executeMarketplaceSearch(array $params): array
    {
        try {
            $searchService = app(AIMarketplaceSearchService::class);
            $searchQuery = $params['search_query'] ?? $params['keyword'] ?? $params['query'] ?? '';
            $context = array_filter([
                'latitude' => $params['latitude'] ?? null,
                'longitude' => $params['longitude'] ?? null,
                'zone_id' => $params['zone_id'] ?? null,
            ], fn($v) => $v !== null);

            $results = $searchService->search($searchQuery, $context);

            DB::table('urban_goodz_discovery_searches')->insert([
                'query' => $searchQuery,
                'customer_ip' => request()->ip(),
                'source' => 'ai_execution',
                'result_count' => $results['total'] ?? 0,
                'was_fulfilled' => ($results['total'] ?? 0) > 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $this->buildResult(true, [
                'results' => $results['results'] ?? [],
                'total' => $results['total'] ?? 0,
                'query_interpretation' => $results['query_interpretation'] ?? $searchQuery,
                'filters_applied' => $results['filters_applied'] ?? [],
            ], [
                ['label' => 'View item details', 'action' => 'view_item'],
                ['label' => 'Add to cart', 'action' => 'add_to_cart'],
                ['label' => 'Filter results', 'action' => 'filter_results'],
                ['label' => 'Search again', 'action' => 'new_search'],
            ], [
                'show_results_list' => true,
                'show_ai_interpretation' => true,
            ]);

        } catch (\Exception $e) {
            Log::error('UrbanGoodzAIExecutionService: executeMarketplaceSearch failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);

            return $this->buildResult(false, [
                'message' => 'I had trouble searching the marketplace. Please try again.',
            ], [
                ['label' => 'Try again', 'action' => 'retry'],
                ['label' => 'Browse categories', 'action' => 'browse_categories'],
            ]);
        }
    }

    // ─── MEDICAL COURIER ──────────────────────────────────────────────

    public function executeMedicalCourier(array $params): array
    {
        try {
            $actionType = $this->determineActionType($params);

            if ($actionType === 'status' && !empty($params['job_id'])) {
                $job = UrbanGoodzMedicalCourierJob::find($params['job_id']);
                if ($job) {
                    return $this->buildResult(true, [
                        'job_id' => $job->id,
                        'job_number' => $job->job_number,
                        'status' => $job->status,
                        'status_label' => $job->status_label,
                        'priority' => $job->priority,
                        'priority_label' => $job->priority_label,
                        'pickup_location' => $job->pickup_location,
                        'delivery_location' => $job->delivery_location,
                        'assigned_driver' => $job->assignedDriver
                            ? ['id' => $job->assignedDriver->id, 'name' => $job->assignedDriver->name]
                            : null,
                    ], [], [
                        'show_job_status' => true,
                        'show_driver_info' => $job->assigned_driver_id !== null,
                    ]);
                }
            }

            $certifiedDrivers = DeliveryMan::where('active', 1)
                ->where('application_status', 'approved')
                ->limit(20)
                ->get()
                ->map(fn($d) => [
                    'id' => $d->id,
                    'name' => $d->name,
                    'phone' => $d->phone,
                ])
                ->toArray();

            $jobNumber = 'MC-' . strtoupper(uniqid());
            $jobId = DB::table('urban_goodz_medical_courier_jobs')->insertGetId([
                'job_number' => $jobNumber,
                'pickup_location' => $params['pickup_location'] ?? '',
                'pickup_facility_name' => $params['pickup_facility_name'] ?? null,
                'delivery_location' => $params['delivery_location'] ?? '',
                'delivery_facility_name' => $params['delivery_facility_name'] ?? null,
                'specimen_type' => $params['specimen_type'] ?? null,
                'requires_refrigeration' => $params['requires_refrigeration'] ?? false,
                'is_biological_hazard' => false,
                'priority' => $params['priority'] ?? 'normal',
                'status' => 'pending',
                'pickup_window_start' => $params['pickup_window_start'] ?? null,
                'pickup_window_end' => $params['pickup_window_end'] ?? null,
                'delivery_window_start' => $params['delivery_window_start'] ?? null,
                'delivery_window_end' => $params['delivery_window_end'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $this->buildResult(true, [
                'job_id' => $jobId,
                'job_number' => $jobNumber,
                'matched_drivers' => array_slice($certifiedDrivers, 0, 5),
                'status' => 'pending',
            ], [
                ['label' => 'Assign driver', 'action' => 'assign_driver'],
                ['label' => 'Update pickup window', 'action' => 'update_windows'],
                ['label' => 'Cancel job', 'action' => 'cancel_job'],
                ['label' => 'View job details', 'action' => 'view_job'],
            ], [
                'show_driver_list' => true,
                'show_job_details' => true,
            ]);

        } catch (\Exception $e) {
            Log::error('UrbanGoodzAIExecutionService: executeMedicalCourier failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);

            return $this->buildResult(false, [
                'message' => 'I had trouble processing the medical courier request.',
            ], [
                ['label' => 'Try again', 'action' => 'retry'],
                ['label' => 'Contact dispatch', 'action' => 'contact_dispatch'],
            ]);
        }
    }

    // ─── LOAD BOARD ───────────────────────────────────────────────────

    public function executeLoadBoard(array $params): array
    {
        try {
            $nlpService = app(LoadBoardNLPService::class);
            $actionType = $this->determineActionType($params);

            if ($actionType === 'status' && !empty($params['load_id'])) {
                $load = UrbanGoodzLoadBoardLoad::find($params['load_id']);
                if ($load) {
                    return $this->buildResult(true, [
                        'load_id' => $load->id,
                        'load_number' => $load->load_number,
                        'status' => $load->status,
                        'status_label' => $load->status_label,
                        'origin' => $load->origin_full,
                        'destination' => $load->destination_full,
                        'payout_amount' => '$' . number_format($load->payout_amount, 2),
                        'rate_per_mile' => $load->rate_per_mile ? '$' . number_format($load->rate_per_mile, 2) : null,
                        'equipment_type' => $load->equipment_type,
                        'weight_lbs' => $load->weight_lbs,
                        'distance_miles' => $load->distance_miles,
                    ], [], [
                        'show_load_details' => true,
                        'show_status_timeline' => true,
                    ]);
                }
            }

            $query = UrbanGoodzLoadBoardLoad::where('status', 'available');

            if (!empty($params['origin_state'])) {
                $query->where('origin_state', $params['origin_state']);
            }

            if (!empty($params['destination_state'])) {
                $query->where('destination_state', $params['destination_state']);
            }

            if (!empty($params['origin_city'])) {
                $query->where('origin_city', 'LIKE', "%{$params['origin_city']}%");
            }

            if (!empty($params['destination_city'])) {
                $query->where('destination_city', 'LIKE', "%{$params['destination_city']}%");
            }

            if (!empty($params['equipment_type'])) {
                $query->where('equipment_type', $params['equipment_type']);
            }

            if (!empty($params['load_type'])) {
                $query->where('load_type', $params['load_type']);
            }

            if (!empty($params['min_rate'])) {
                $query->where('payout_amount', '>=', $params['min_rate']);
            }

            if (!empty($params['max_rate'])) {
                $query->where('payout_amount', '<=', $params['max_rate']);
            }

            if (!empty($params['is_hazmat'])) {
                $query->where('is_hazmat', true);
            }

            if (!empty($params['is_expedited'])) {
                $query->where('is_expedited', true);
            }

            $loads = $query->orderByDesc('created_at')
                ->limit(20)
                ->get()
                ->map(fn($l) => [
                    'id' => $l->id,
                    'load_number' => $l->load_number,
                    'origin' => $l->origin_full,
                    'destination' => $l->destination_full,
                    'origin_state' => $l->origin_state,
                    'destination_state' => $l->destination_state,
                    'payout_amount' => '$' . number_format($l->payout_amount, 2),
                    'rate_per_mile' => $l->rate_per_mile ? '$' . number_format($l->rate_per_mile, 2) : null,
                    'equipment_type' => $l->equipment_type,
                    'load_type' => $l->load_type,
                    'weight_lbs' => $l->weight_lbs,
                    'distance_miles' => $l->distance_miles,
                    'is_hazmat' => $l->is_hazmat,
                    'is_expedited' => $l->is_expedited,
                    'is_team_load' => $l->is_team_load,
                    'posted_at' => $l->created_at->format('M d, Y g:i A'),
                ])
                ->toArray();

            $estimatedRate = null;
            if (!empty($loads)) {
                $rates = array_map(fn($l) => (float) str_replace('$', '', str_replace(',', '', $l['payout_amount'])), $loads);
                $estimatedRate = [
                    'min_rate' => '$' . number_format(min($rates), 2),
                    'max_rate' => '$' . number_format(max($rates), 2),
                ];
            }

            return $this->buildResult(true, [
                'matched_loads' => $loads,
                'total_found' => count($loads),
                'estimated_rate' => $estimatedRate,
            ], [
                ['label' => 'Bid on load', 'action' => 'bid_on_load'],
                ['label' => 'View load details', 'action' => 'view_load'],
                ['label' => 'Contact broker', 'action' => 'contact_broker'],
                ['label' => 'Filter loads', 'action' => 'filter_loads'],
            ], [
                'show_load_list' => true,
                'show_rate_comparison' => count($loads) > 1,
            ]);

        } catch (\Exception $e) {
            Log::error('UrbanGoodzAIExecutionService: executeLoadBoard failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);

            return $this->buildResult(false, [
                'message' => 'I had trouble searching the load board.',
            ], [
                ['label' => 'Try again', 'action' => 'retry'],
                ['label' => 'Browse all loads', 'action' => 'browse_loads'],
            ]);
        }
    }

    // ─── DELIVERY ─────────────────────────────────────────────────────

    public function executeDelivery(array $params): array
    {
        try {
            $orderId = $params['order_id'] ?? $params['request_id'] ?? null;
            $orderNumber = $params['order_number'] ?? null;

            $order = null;
            if ($orderId) {
                $order = Order::find($orderId);
            } elseif ($orderNumber) {
                $order = Order::where('order_number', $orderNumber)->first();
            }

            if (!$order) {
                return $this->buildResult(true, [
                    'delivery_status' => 'not_found',
                    'message' => 'I could not find a delivery matching that order number. Please check and try again.',
                ], [
                    ['label' => 'Try a different order number', 'action' => 'retry'],
                    ['label' => 'View all orders', 'action' => 'view_orders'],
                ], [
                    'show_search_form' => true,
                ]);
            }

            $driverInfo = null;
            if ($order->delivery_man_id) {
                $driver = DeliveryMan::find($order->delivery_man_id);
                if ($driver) {
                    $driverInfo = [
                        'id' => $driver->id,
                        'name' => $driver->name,
                        'phone' => $driver->phone,
                    ];
                }
            }

            $eta = null;
            if (in_array($order->order_status, ['confirmed', 'processing', 'picked_up', 'on_the_way'])) {
                $eta = 'Estimated arrival: 30-45 minutes';
            }

            return $this->buildResult(true, [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'delivery_status' => $order->order_status,
                'driver_info' => $driverInfo,
                'eta' => $eta,
                'delivery_address' => $order->delivery_address,
                'order_amount' => '$' . number_format($order->order_amount, 2),
            ], [
                ['label' => 'Track on map', 'action' => 'track_map'],
                ['label' => 'Contact driver', 'action' => 'contact_driver'],
                ['label' => 'Report issue', 'action' => 'report_issue'],
                ['label' => 'View order details', 'action' => 'view_order'],
            ], [
                'show_tracking' => true,
                'show_driver_info' => $driverInfo !== null,
                'show_eta' => $eta !== null,
            ]);

        } catch (\Exception $e) {
            Log::error('UrbanGoodzAIExecutionService: executeDelivery failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);

            return $this->buildResult(false, [
                'message' => 'I had trouble checking your delivery status.',
            ], [
                ['label' => 'Try again', 'action' => 'retry'],
            ]);
        }
    }

    // ─── CREATOR COMMERCE ─────────────────────────────────────────────

    public function executeCreatorCommerce(array $params): array
    {
        try {
            $actionType = $this->determineActionType($params);

            if ($actionType === 'detail' && !empty($params['creator_profile_id'])) {
                $profile = UrbanGoodzCreatorProfile::find($params['creator_profile_id']);
                if ($profile) {
                    $campaigns = $profile->campaigns()
                        ->with('campaign')
                        ->get()
                        ->map(fn($a) => [
                            'id' => $a->campaign->id ?? null,
                            'title' => $a->campaign->title ?? null,
                            'type' => $a->campaign->type ?? null,
                            'payout' => $a->campaign->flat_payout ? '$' . number_format($a->campaign->flat_payout, 2) : null,
                        ])
                        ->toArray();

                    return $this->buildResult(true, [
                        'creator' => [
                            'id' => $profile->id,
                            'handle' => $profile->handle,
                            'display_name' => $profile->display_name,
                            'bio' => $profile->bio,
                            'city' => $profile->city,
                            'niches' => $profile->niches,
                            'is_approved' => $profile->is_approved,
                            'is_featured' => $profile->is_featured,
                        ],
                        'campaigns' => $campaigns,
                    ], [
                        ['label' => 'Contact creator', 'action' => 'contact_creator'],
                        ['label' => 'View content samples', 'action' => 'view_content'],
                        ['label' => 'Invite to campaign', 'action' => 'invite_to_campaign'],
                    ], [
                        'show_creator_profile' => true,
                        'show_campaign_list' => true,
                    ]);
                }
            }

            $query = UrbanGoodzCreatorProfile::where('is_approved', true);

            if (!empty($params['search_query']) || !empty($params['niches'])) {
                $searchTerm = $params['search_query'] ?? $params['niches'];
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('display_name', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('handle', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('bio', 'LIKE', "%{$searchTerm}%")
                      ->orWhereJsonContains('niches', $searchTerm);
                });
            }

            if (!empty($params['city'])) {
                $query->where('city', 'LIKE', "%{$params['city']}%");
            }

            $creators = $query->limit(20)
                ->get()
                ->map(fn($c) => [
                    'id' => $c->id,
                    'handle' => $c->handle,
                    'display_name' => $c->display_name,
                    'bio' => mb_substr($c->bio ?? '', 0, 150),
                    'city' => $c->city,
                    'niches' => $c->niches,
                    'is_featured' => $c->is_featured,
                    'avatar_url' => $c->avatar_url,
                ])
                ->toArray();

            $campaigns = UrbanGoodzCreatorCampaign::where('status', 'active')
                ->limit(10)
                ->get()
                ->map(fn($c) => [
                    'id' => $c->id,
                    'title' => $c->title,
                    'type' => $c->type,
                    'category' => $c->category,
                    'city' => $c->city,
                    'pay_type' => $c->pay_type,
                    'flat_payout' => $c->flat_payout ? '$' . number_format($c->flat_payout, 2) : null,
                    'deadline' => $c->deadline?->format('M d, Y'),
                ])
                ->toArray();

            return $this->buildResult(true, [
                'matched_creators' => $creators,
                'campaigns' => $campaigns,
                'total_creators' => count($creators),
                'total_campaigns' => count($campaigns),
            ], [
                ['label' => 'View creator profile', 'action' => 'view_creator'],
                ['label' => 'Apply as creator', 'action' => 'apply_creator'],
                ['label' => 'View campaign details', 'action' => 'view_campaign'],
                ['label' => 'Filter results', 'action' => 'filter_creators'],
            ], [
                'show_creator_list' => true,
                'show_campaign_list' => true,
            ]);

        } catch (\Exception $e) {
            Log::error('UrbanGoodzAIExecutionService: executeCreatorCommerce failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);

            return $this->buildResult(false, [
                'message' => 'I had trouble searching creators and campaigns.',
            ], [
                ['label' => 'Try again', 'action' => 'retry'],
            ]);
        }
    }

    // ─── COMMUNITY ────────────────────────────────────────────────────

    public function executeCommunity(array $params): array
    {
        try {
            $query = UrbanGoodzCommunityPost::where('is_published', true);

            if (!empty($params['search_query']) || !empty($params['keyword'])) {
                $searchTerm = $params['search_query'] ?? $params['keyword'];
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('title', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('body', 'LIKE', "%{$searchTerm}%");
                });
            }

            if (!empty($params['post_type'])) {
                $query->where('type', $params['post_type']);
            }

            $posts = $query->orderByDesc('published_at')
                ->limit(20)
                ->get()
                ->map(fn($p) => [
                    'id' => $p->id,
                    'title' => $p->title,
                    'body' => mb_substr($p->body ?? '', 0, 200),
                    'type' => $p->type,
                    'author' => $p->author_name,
                    'published_at' => $p->published_at?->format('M d, Y'),
                    'comment_count' => $p->comments()->count(),
                ])
                ->toArray();

            $marketplaceItems = UrbanGoodzCommunityMarketplaceItem::where('is_active', true)
                ->where('status', 'available');

            if (!empty($params['search_query'])) {
                $searchTerm = $params['search_query'];
                $marketplaceItems->where(function ($q) use ($searchTerm) {
                    $q->where('title', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('description', 'LIKE', "%{$searchTerm}%");
                });
            }

            $marketplaceItems = $marketplaceItems->limit(10)
                ->get()
                ->map(fn($i) => [
                    'id' => $i->id,
                    'title' => $i->title,
                    'description' => mb_substr($i->description ?? '', 0, 150),
                    'price' => $i->price ? '$' . number_format($i->price, 2) : null,
                    'condition' => $i->condition,
                    'seller' => $i->seller_name,
                    'location' => $i->location,
                ])
                ->toArray();

            return $this->buildResult(true, [
                'results' => $posts,
                'marketplace_items' => $marketplaceItems,
                'total_posts' => count($posts),
                'total_marketplace_items' => count($marketplaceItems),
            ], [
                ['label' => 'View post', 'action' => 'view_post'],
                ['label' => 'Create post', 'action' => 'create_post'],
                ['label' => 'Comment', 'action' => 'comment'],
                ['label' => 'View marketplace item', 'action' => 'view_marketplace_item'],
            ], [
                'show_posts' => true,
                'show_marketplace' => count($marketplaceItems) > 0,
            ]);

        } catch (\Exception $e) {
            Log::error('UrbanGoodzAIExecutionService: executeCommunity failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);

            return $this->buildResult(false, [
                'message' => 'I had trouble loading community content.',
            ], [
                ['label' => 'Try again', 'action' => 'retry'],
            ]);
        }
    }

    // ─── EARN MONEY ───────────────────────────────────────────────────

    public function executeEarnMoney(array $params): array
    {
        try {
            $query = UrbanGoodzEarnMoneyOpportunity::where('status', 'active');

            if (!empty($params['opportunity_type'])) {
                $query->where('type', $params['opportunity_type']);
            }

            $now = now();
            $query->where(function ($q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })->where(function ($q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            });

            $opportunities = $query->orderByDesc('reward_amount')
                ->limit(20)
                ->get()
                ->map(fn($o) => [
                    'id' => $o->id,
                    'title' => $o->title,
                    'description' => mb_substr($o->description ?? '', 0, 200),
                    'type' => $o->type,
                    'reward_amount' => '$' . number_format($o->reward_amount, 2),
                    'reward_type' => $o->reward_type,
                    'starts_at' => $o->starts_at?->format('M d, Y'),
                    'ends_at' => $o->ends_at?->format('M d, Y'),
                ])
                ->toArray();

            return $this->buildResult(true, [
                'opportunities' => $opportunities,
                'total_found' => count($opportunities),
            ], [
                ['label' => 'Apply for opportunity', 'action' => 'apply_opportunity'],
                ['label' => 'View details', 'action' => 'view_opportunity'],
                ['label' => 'Get referral code', 'action' => 'get_referral_code'],
                ['label' => 'View earnings history', 'action' => 'view_earnings'],
            ], [
                'show_opportunities' => true,
                'show_reward_amounts' => true,
            ]);

        } catch (\Exception $e) {
            Log::error('UrbanGoodzAIExecutionService: executeEarnMoney failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);

            return $this->buildResult(false, [
                'message' => 'I had trouble loading earning opportunities.',
            ], [
                ['label' => 'Try again', 'action' => 'retry'],
            ]);
        }
    }

    // ─── EVENTS ───────────────────────────────────────────────────────

    public function executeEvents(array $params): array
    {
        try {
            $query = UrbanGoodzEvent::where('status', 'published')
                ->orWhere('status', 'active');

            if (!empty($params['search_query'])) {
                $searchTerm = $params['search_query'];
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('title', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('description', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('location', 'LIKE', "%{$searchTerm}%");
                });
            }

            if (!empty($params['event_type'])) {
                $query->where('title', 'LIKE', "%{$params['event_type']}%");
            }

            if (!empty($params['location'])) {
                $query->where('location', 'LIKE', "%{$params['location']}%");
            }

            if (!empty($params['date'])) {
                $query->whereDate('starts_at', $params['date']);
            }

            $events = $query->where('starts_at', '>=', now())
                ->orderBy('starts_at')
                ->limit(20)
                ->get()
                ->map(fn($e) => [
                    'id' => $e->id,
                    'title' => $e->title,
                    'description' => mb_substr($e->description ?? '', 0, 200),
                    'location' => $e->location,
                    'starts_at' => $e->starts_at->format('M d, Y g:i A'),
                    'ends_at' => $e->ends_at?->format('M d, Y g:i A'),
                    'ticket_price' => $e->ticket_price > 0
                        ? '$' . number_format($e->ticket_price, 2)
                        : 'Free',
                    'capacity' => $e->capacity,
                    'organizer' => $e->organizer_name,
                ])
                ->toArray();

            return $this->buildResult(true, [
                'events' => $events,
                'total_found' => count($events),
            ], [
                ['label' => 'Register interest', 'action' => 'register_interest'],
                ['label' => 'Get tickets', 'action' => 'get_tickets'],
                ['label' => 'Share event', 'action' => 'share_event'],
                ['label' => 'View event details', 'action' => 'view_event'],
            ], [
                'show_events' => true,
                'show_pricing' => true,
            ]);

        } catch (\Exception $e) {
            Log::error('UrbanGoodzAIExecutionService: executeEvents failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);

            return $this->buildResult(false, [
                'message' => 'I had trouble loading events.',
            ], [
                ['label' => 'Try again', 'action' => 'retry'],
            ]);
        }
    }

    // ─── HELPER METHODS ───────────────────────────────────────────────

    private function buildResult(
        bool $success,
        array $data,
        array $nextSteps = [],
        array $uiHints = []
    ): array {
        return [
            'success' => $success,
            'data' => $data,
            'next_steps' => $nextSteps,
            'ui_hints' => $uiHints,
            'actions_available' => array_map(fn($s) => $s['action'] ?? null, $nextSteps),
        ];
    }

    private function determineActionType(array $entities): string
    {
        return match (true) {
            isset($entities['action_type']) => $entities['action_type'],
            isset($entities['status']) || isset($entities['request_id']) || isset($entities['order_id']) => 'status',
            !empty($entities['search_query']) || !empty($entities['keyword']) => 'search',
            $entities['create'] ?? false => 'create',
            isset($entities['item_id']) || isset($entities['job_id']) || isset($entities['load_id']) => 'detail',
            default => 'default',
        };
    }

    private function buildCustomerContext(int $customerId): array
    {
        try {
            $customer = User::find($customerId);
            if (!$customer) return [];

            return [
                'customer_id' => $customerId,
                'customer' => [
                    'name' => $customer->name ?? trim(($customer->f_name ?? '') . ' ' . ($customer->l_name ?? '')),
                    'email' => $customer->email,
                ],
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getGenericNextSteps(): array
    {
        return [
            ['label' => 'Try again', 'action' => 'retry'],
            ['label' => 'Contact support', 'action' => 'contact_support'],
        ];
    }

    private function getGenericUiHints(): array
    {
        return [
            'show_suggestions' => true,
        ];
    }

    private function logAction(array $data): void
    {
        try {
            UrbanGoodzActivityLog::create([
                'loggable_type' => 'App\\Models\\UrbanGoodzAIConversation',
                'loggable_id' => null,
                'event' => $data['event'] ?? 'action_executed',
                'description' => $data['action'] ?? null,
                'causer_type' => $data['customer_id'] ? 'App\\Models\\User' : null,
                'causer_id' => $data['customer_id'] ?? null,
                'metadata' => array_filter([
                    'intent_slug' => $data['intent_slug'] ?? null,
                    'module' => $data['module'] ?? null,
                    'confidence' => $data['confidence'] ?? null,
                    'success' => $data['success'] ?? null,
                    'execution_time_ms' => $data['execution_time_ms'] ?? null,
                    'metadata' => $data['metadata'] ?? null,
                ], fn($v) => $v !== null),
            ]);
        } catch (\Exception $e) {
            Log::warning('UrbanGoodzAIExecutionService: Failed to log action', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
