<?php

namespace App\Services\UrbanGoodz;

use App\Models\UrbanGoodzAIIntent;

class UrbanGoodzModuleRouter
{
    private const INTENT_MODULE_MAP = [
        'order-anywhere'       => 'orderAnywhere',
        'fashion-fit'          => 'fashionFit',
        'book-services'        => 'bookServices',
        'book-anything'        => 'bookServices',
        'rentals'              => 'rentals',
        'rental'               => 'rentals',
        'marketplace'          => 'marketplaceSearch',
        'marketplace-search'   => 'marketplaceSearch',
        'community-marketplace'=> 'marketplaceSearch',
        'medical-courier'      => 'medicalCourier',
        'medical'              => 'medicalCourier',
        'load-board'           => 'loadBoard',
        'freight'              => 'loadBoard',
        'logistics'            => 'loadBoard',
        'delivery'             => 'delivery',
        'track-order'          => 'delivery',
        'order-status'         => 'delivery',
        'creator-commerce'     => 'creatorCommerce',
        'creator'              => 'creatorCommerce',
        'influencer'           => 'creatorCommerce',
        'community'            => 'community',
        'community-post'       => 'community',
        'earn-money'           => 'earnMoney',
        'referral'             => 'earnMoney',
        'events'               => 'events',
        'event'                => 'events',
        // Internal operations. Admin/dispatcher only - the registry enforces
        // that, not this map.
        'operations'           => 'operations',
        'ops'                  => 'operations',
        'queue'                => 'operations',
    ];

    private const ACTION_TEMPLATES = [
        'orderAnywhere' => [
            'create'  => 'create_order_anywhere_request',
            'status'  => 'get_order_anywhere_status',
            'default' => 'create_order_anywhere_request',
        ],
        'fashionFit' => [
            'create'  => 'submit_stylist_request',
            'search'  => 'search_stylists',
            'status'  => 'get_stylist_requests',
            'default' => 'submit_stylist_request',
        ],
        'bookServices' => [
            'create'  => 'submit_book_anything_request',
            'search'  => 'search_service_providers',
            'status'  => 'get_book_anything_status',
            'default' => 'submit_book_anything_request',
        ],
        'rentals' => [
            'search'  => 'search_rental_assets',
            'book'    => 'book_rental_asset',
            'status'  => 'get_rental_booking_status',
            'default' => 'search_rental_assets',
        ],
        'marketplaceSearch' => [
            'search'  => 'search_marketplace',
            'detail'  => 'get_marketplace_item',
            'create'  => 'list_marketplace_item',
            'default' => 'search_marketplace',
        ],
        'medicalCourier' => [
            'search'  => 'search_medical_courier_jobs',
            'detail'  => 'get_medical_courier_job',
            'create'  => 'create_medical_courier_job',
            'status'  => 'get_medical_courier_status',
            'default' => 'search_medical_courier_jobs',
        ],
        'loadBoard' => [
            'search'  => 'search_load_board',
            'detail'  => 'get_load_board_load',
            'create'  => 'post_load_to_board',
            'bid'     => 'bid_on_load',
            // Operational verbs. These resolve to registry actions that are
            // confirmation-gated and delegate to UrbanGoodzLoadBoardService.
            'accept'      => 'accept_load',
            'assign'      => 'accept_load',
            'reassign'    => 'reassign_load',
            'dispatch'    => 'update_load_status',
            'status_set'  => 'update_load_status',
            'cancel'      => 'cancel_load',
            'review'      => 'review_load',
            'accept_bid'  => 'accept_load_bid',
            'reject_bid'  => 'reject_load_bid',
            'stats'       => 'get_load_board_stats',
            'default' => 'search_load_board',
        ],
        'operations' => [
            'retry'   => 'retry_queue_job',
            'out_of_stock' => 'get_out_of_stock_by_store',
            'inventory'    => 'get_out_of_stock_by_store',
            'requeue' => 'retry_queue_job',
            'default' => 'retry_queue_job',
        ],
        'delivery' => [
            'track'   => 'track_delivery',
            'status'  => 'get_delivery_status',
            'create'  => 'create_delivery_request',
            // Operational: assigning a courier to an order. Delegates to
            // OrderController::add_delivery_man, which owns the availability
            // and max-order rules, the status transition, the driver
            // counters and the customer notification.
            'assign'    => 'assign_order',
            'cancel'    => 'cancel_order',
            'reassign'  => 'assign_order',
            'default' => 'get_delivery_status',
        ],
        'creatorCommerce' => [
            'search'  => 'search_creators',
            'detail'  => 'get_creator_profile',
            'apply'   => 'apply_as_creator',
            'campaign'=> 'get_creator_campaigns',
            'default' => 'search_creators',
        ],
        'community' => [
            'search'  => 'search_community_posts',
            'create'  => 'create_community_post',
            'detail'  => 'get_community_post',
            'default' => 'search_community_posts',
        ],
        'earnMoney' => [
            'search'  => 'search_earn_money_opportunities',
            'detail'  => 'get_earn_money_opportunity',
            'apply'   => 'apply_earn_money_opportunity',
            'default' => 'search_earn_money_opportunities',
        ],
        'events' => [
            'search'  => 'search_events',
            'detail'  => 'get_event_detail',
            'interest'=> 'register_event_interest',
            'default' => 'search_events',
        ],
    ];

    public function route(
        string $intentSlug,
        array $entities = [],
        array $customerContext = [],
        ?int $customerId = null
    ): array {
        $moduleKey = $this->resolveModule($intentSlug);
        $intent = UrbanGoodzAIIntent::where('slug', $intentSlug)->first();

        if ($moduleKey === null) {
            return $this->buildFallbackResponse($intentSlug, $entities, $customerContext);
        }

        $actionType = $this->determineActionType($entities);
        $actionName = $this->resolveAction($moduleKey, $actionType);
        $params = $this->buildParams($moduleKey, $actionType, $entities, $customerContext, $customerId);

        return [
            'success' => true,
            'module' => $moduleKey,
            'intent_slug' => $intentSlug,
            'capability_slug' => $intent?->capability_slug,
            'admin_section_key' => $intent?->admin_section_key,
            'actions' => [
                [
                    'action' => $actionName,
                    // The executor receives only params, so the resolved
                    // action travels with them. Deliberately a separate key
                    // rather than 'action_type': determineActionType() reads
                    // 'action_type' first, so setting that would change how
                    // every existing module resolves its action, which is a
                    // regression risk this does not need to take.
                    'params' => array_merge($params, ['_routed_action' => $actionName]),
                    'api_endpoint' => $this->resolveEndpoint($actionName),
                    'method' => $this->resolveMethod($actionName),
                ],
            ],
            'confidence_hint' => $this->buildConfidenceHint($intentSlug, $entities),
        ];
    }

    public function routeMultiAction(
        string $intentSlug,
        array $entities = [],
        array $customerContext = [],
        ?int $customerId = null
    ): array {
        $singleResult = $this->route($intentSlug, $entities, $customerContext, $customerId);

        if (!$singleResult['success']) {
            return $singleResult;
        }

        $moduleKey = $singleResult['module'];
        $secondaryActions = $this->buildSecondaryActions($moduleKey, $entities, $customerContext, $customerId);

        $singleResult['actions'] = array_merge($singleResult['actions'], $secondaryActions);

        return $singleResult;
    }

    public function getModuleForCapabilitySlug(string $capabilitySlug): ?string
    {
        return UrbanGoodzAIIntent::where('capability_slug', $capabilitySlug)
            ->where('is_active', true)
            ->value('slug');
    }

    public function getAvailableModules(): array
    {
        $intents = UrbanGoodzAIIntent::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['slug', 'name', 'description', 'capability_slug', 'admin_section_key']);

        $modules = [];
        foreach ($intents as $intent) {
            $moduleKey = self::INTENT_MODULE_MAP[$intent->slug] ?? null;
            if ($moduleKey === null) continue;

            $modules[$moduleKey] ??= [
                'module' => $moduleKey,
                'intents' => [],
            ];
            $modules[$moduleKey]['intents'][] = [
                'slug' => $intent->slug,
                'name' => $intent->name,
                'description' => $intent->description,
                'capability_slug' => $intent->capability_slug,
            ];
        }

        return array_values($modules);
    }

    private function resolveModule(string $intentSlug): ?string
    {
        return self::INTENT_MODULE_MAP[$intentSlug] ?? null;
    }

    private function determineActionType(array $entities): string
    {
        if (isset($entities['action_type'])) {
            return $entities['action_type'];
        }

        if (isset($entities['status']) || isset($entities['request_id']) || isset($entities['order_id'])) {
            return 'status';
        }

        if (!empty($entities['search_query']) || !empty($entities['keyword']) || !empty($entities['location'])) {
            return 'search';
        }

        if (($entities['create'] ?? false) === true) {
            return 'create';
        }

        if (isset($entities['item_id']) || isset($entities['job_id']) || isset($entities['load_id'])) {
            return 'detail';
        }

        return 'default';
    }

    private function resolveAction(string $moduleKey, string $actionType): string
    {
        $templates = self::ACTION_TEMPLATES[$moduleKey] ?? [];
        return $templates[$actionType] ?? $templates['default'] ?? "action_{$moduleKey}_{$actionType}";
    }

    private function buildParams(
        string $moduleKey,
        string $actionType,
        array $entities,
        array $customerContext,
        ?int $customerId
    ): array {
        $base = array_filter([
            'customer_id' => $customerId,
            'source' => 'ai_concierge',
        ], fn($v) => $v !== null);

        return match ($moduleKey) {
            'orderAnywhere'      => $this->buildOrderAnywhereParams($actionType, $entities, $base),
            'fashionFit'         => $this->buildFashionFitParams($actionType, $entities, $base),
            'bookServices'       => $this->buildBookServicesParams($actionType, $entities, $base),
            'rentals'            => $this->buildRentalsParams($actionType, $entities, $base),
            'marketplaceSearch'  => $this->buildMarketplaceParams($actionType, $entities, $base),
            'medicalCourier'     => $this->buildMedicalCourierParams($actionType, $entities, $base),
            'loadBoard'          => $this->buildLoadBoardParams($actionType, $entities, $base),
            'operations'         => array_merge($base, [
                'job_uuid' => $entities['job_uuid'] ?? $entities['job_id'] ?? null,
                'all'      => $entities['all'] ?? false,
            ]),
            'delivery'           => $this->buildDeliveryParams($actionType, $entities, $base),
            'creatorCommerce'    => $this->buildCreatorCommerceParams($actionType, $entities, $base),
            'community'          => $this->buildCommunityParams($actionType, $entities, $base),
            'earnMoney'          => $this->buildEarnMoneyParams($actionType, $entities, $base),
            'events'             => $this->buildEventsParams($actionType, $entities, $base),
            default              => array_merge($base, $entities),
        };
    }

    private function buildOrderAnywhereParams(string $actionType, array $entities, array $base): array
    {
        return array_merge($base, [
            'store_name'      => $entities['store_name'] ?? $entities['store'] ?? null,
            'items'           => $entities['items'] ?? $entities['item'] ?? null,
            'delivery_address'=> $entities['delivery_address'] ?? $entities['address'] ?? null,
            'special_notes'   => $entities['special_notes'] ?? $entities['notes'] ?? null,
            'budget_max'      => $entities['budget_max'] ?? $entities['budget'] ?? null,
            'request_id'      => $entities['request_id'] ?? null,
        ]);
    }

    private function buildFashionFitParams(string $actionType, array $entities, array $base): array
    {
        return array_merge($base, [
            'service_type'    => $entities['service_type'] ?? $entities['type'] ?? null,
            'garment_type'    => $entities['garment_type'] ?? $entities['garment'] ?? null,
            'description'     => $entities['description'] ?? $entities['notes'] ?? null,
            'preferred_date'  => $entities['preferred_date'] ?? $entities['date'] ?? null,
            'location'        => $entities['location'] ?? null,
            'budget'          => $entities['budget'] ?? null,
            'request_id'      => $entities['request_id'] ?? null,
        ]);
    }

    private function buildBookServicesParams(string $actionType, array $entities, array $base): array
    {
        return array_merge($base, [
            'service_name'    => $entities['service_name'] ?? $entities['service'] ?? null,
            'description'     => $entities['description'] ?? $entities['notes'] ?? null,
            'preferred_date'  => $entities['preferred_date'] ?? $entities['date'] ?? null,
            'preferred_time'  => $entities['preferred_time'] ?? $entities['time'] ?? null,
            'location'        => $entities['location'] ?? null,
            'budget_amount'   => $entities['budget_amount'] ?? $entities['budget'] ?? null,
            'category'        => $entities['category'] ?? null,
            'request_id'      => $entities['request_id'] ?? null,
        ]);
    }

    private function buildRentalsParams(string $actionType, array $entities, array $base): array
    {
        return array_merge($base, [
            'asset_type'      => $entities['asset_type'] ?? $entities['type'] ?? null,
            'search_query'    => $entities['search_query'] ?? $entities['keyword'] ?? null,
            'location'        => $entities['location'] ?? null,
            'start_date'      => $entities['start_date'] ?? $entities['date'] ?? null,
            'end_date'        => $entities['end_date'] ?? null,
            'max_daily_rate'  => $entities['max_daily_rate'] ?? $entities['budget'] ?? null,
            'make'            => $entities['make'] ?? null,
            'model'           => $entities['model'] ?? null,
            'booking_id'      => $entities['booking_id'] ?? null,
        ]);
    }

    private function buildMarketplaceParams(string $actionType, array $entities, array $base): array
    {
        return array_merge($base, [
            'search_query'    => $entities['search_query'] ?? $entities['keyword'] ?? $entities['query'] ?? null,
            'category'        => $entities['category'] ?? null,
            'min_price'       => $entities['min_price'] ?? null,
            'max_price'       => $entities['max_price'] ?? null,
            'location'        => $entities['location'] ?? null,
            'condition'       => $entities['condition'] ?? null,
            'item_id'         => $entities['item_id'] ?? null,
            'title'           => $entities['title'] ?? null,
            'description'     => $entities['description'] ?? null,
            'price'           => $entities['price'] ?? null,
        ]);
    }

    private function buildMedicalCourierParams(string $actionType, array $entities, array $base): array
    {
        return array_merge($base, [
            'pickup_location'       => $entities['pickup_location'] ?? $entities['pickup'] ?? null,
            'delivery_location'     => $entities['delivery_location'] ?? $entities['delivery'] ?? null,
            'pickup_facility_name'  => $entities['pickup_facility_name'] ?? null,
            'delivery_facility_name'=> $entities['delivery_facility_name'] ?? null,
            'specimen_type'         => $entities['specimen_type'] ?? null,
            'requires_refrigeration'=> $entities['requires_refrigeration'] ?? null,
            'priority'              => $entities['priority'] ?? null,
            'pickup_window_start'   => $entities['pickup_window_start'] ?? null,
            'pickup_window_end'     => $entities['pickup_window_end'] ?? null,
            'delivery_window_start' => $entities['delivery_window_start'] ?? null,
            'delivery_window_end'   => $entities['delivery_window_end'] ?? null,
            'job_id'                => $entities['job_id'] ?? null,
            'job_number'            => $entities['job_number'] ?? null,
        ]);
    }

    private function buildLoadBoardParams(string $actionType, array $entities, array $base): array
    {
        return array_merge($base, [
            'origin_state'     => $entities['origin_state'] ?? $entities['origin'] ?? null,
            'destination_state'=> $entities['destination_state'] ?? $entities['destination'] ?? null,
            'origin_city'      => $entities['origin_city'] ?? null,
            'destination_city' => $entities['destination_city'] ?? null,
            'load_type'        => $entities['load_type'] ?? $entities['type'] ?? null,
            'equipment_type'   => $entities['equipment_type'] ?? $entities['equipment'] ?? null,
            'min_weight'       => $entities['min_weight'] ?? null,
            'max_weight'       => $entities['max_weight'] ?? null,
            'min_rate'         => $entities['min_rate'] ?? null,
            'max_rate'         => $entities['max_rate'] ?? null,
            'is_hazmat'        => $entities['is_hazmat'] ?? null,
            'is_expedited'     => $entities['is_expedited'] ?? null,
            'load_id'          => $entities['load_id'] ?? null,
            'load_number'      => $entities['load_number'] ?? null,
            'bid_amount'       => $entities['bid_amount'] ?? null,
            // Operational parameters. UrbanGoodzLoadBoardService needs the
            // target driver for accept/reassign, the bid for bid decisions,
            // and the status/decision for transitions and reviews.
            'driver_id'        => $entities['driver_id'] ?? $entities['delivery_man_id'] ?? null,
            'bid_id'           => $entities['bid_id'] ?? null,
            'status'           => $entities['status'] ?? null,
            'decision'         => $entities['decision'] ?? null,
            'reason'           => $entities['reason'] ?? null,
            'notes'            => $entities['notes'] ?? null,
        ]);
    }

    private function buildDeliveryParams(string $actionType, array $entities, array $base): array
    {
        return array_merge($base, [
            'order_id'         => $entities['order_id'] ?? $entities['request_id'] ?? null,
            'order_number'     => $entities['order_number'] ?? null,
            'pickup_address'   => $entities['pickup_address'] ?? $entities['pickup'] ?? null,
            'delivery_address' => $entities['delivery_address'] ?? $entities['delivery'] ?? $entities['address'] ?? null,
            'item_description' => $entities['item_description'] ?? $entities['description'] ?? null,
            'preferred_time'   => $entities['preferred_time'] ?? $entities['time'] ?? null,
            'special_notes'    => $entities['special_notes'] ?? $entities['notes'] ?? null,
            // Operational: the courier to assign.
            'driver_id'        => $entities['driver_id'] ?? $entities['delivery_man_id'] ?? null,
        ]);
    }

    private function buildCreatorCommerceParams(string $actionType, array $entities, array $base): array
    {
        return array_merge($base, [
            'search_query'     => $entities['search_query'] ?? $entities['keyword'] ?? null,
            'niches'           => $entities['niches'] ?? $entities['niche'] ?? null,
            'city'             => $entities['city'] ?? $entities['location'] ?? null,
            'creator_profile_id'=> $entities['creator_profile_id'] ?? null,
            'campaign_id'      => $entities['campaign_id'] ?? null,
            'handle'           => $entities['handle'] ?? null,
        ]);
    }

    private function buildCommunityParams(string $actionType, array $entities, array $base): array
    {
        return array_merge($base, [
            'search_query'     => $entities['search_query'] ?? $entities['keyword'] ?? $entities['query'] ?? null,
            'post_type'        => $entities['post_type'] ?? $entities['type'] ?? null,
            'title'            => $entities['title'] ?? null,
            'body'             => $entities['body'] ?? $entities['content'] ?? null,
            'post_id'          => $entities['post_id'] ?? null,
        ]);
    }

    private function buildEarnMoneyParams(string $actionType, array $entities, array $base): array
    {
        return array_merge($base, [
            'opportunity_type' => $entities['opportunity_type'] ?? $entities['type'] ?? null,
            'opportunity_id'   => $entities['opportunity_id'] ?? null,
            'referral_code'    => $entities['referral_code'] ?? null,
        ]);
    }

    private function buildEventsParams(string $actionType, array $entities, array $base): array
    {
        return array_merge($base, [
            'search_query'     => $entities['search_query'] ?? $entities['keyword'] ?? null,
            'event_type'       => $entities['event_type'] ?? $entities['type'] ?? null,
            'location'         => $entities['location'] ?? null,
            'date'             => $entities['date'] ?? null,
            'event_id'         => $entities['event_id'] ?? null,
        ]);
    }

    private function buildSecondaryActions(
        string $moduleKey,
        array $entities,
        array $customerContext,
        ?int $customerId
    ): array {
        $secondary = [];

        if ($moduleKey === 'orderAnywhere' && !empty($entities['store_name'])) {
            $secondary[] = [
                'action' => 'search_marketplace',
                'params' => [
                    'customer_id' => $customerId,
                    'search_query' => $entities['store_name'],
                    'source' => 'ai_concierge_contextual',
                ],
                'api_endpoint' => '/api/v1/urban-goodz/discovery/entities',
                'method' => 'GET',
            ];
        }

        if ($moduleKey === 'delivery' && !empty($entities['order_id'])) {
            $secondary[] = [
                'action' => 'get_order_anywhere_status',
                'params' => [
                    'customer_id' => $customerId,
                    'request_id' => $entities['order_id'],
                    'source' => 'ai_concierge_contextual',
                ],
                'api_endpoint' => '/api/v1/order-anywhere/requests/{id}',
                'method' => 'GET',
            ];
        }

        if ($moduleKey === 'bookServices' && !empty($entities['location'])) {
            $secondary[] = [
                'action' => 'search_service_providers',
                'params' => [
                    'customer_id' => $customerId,
                    'location' => $entities['location'],
                    'category' => $entities['category'] ?? null,
                    'source' => 'ai_concierge_contextual',
                ],
                'api_endpoint' => '/api/v1/urban-goodz/book-anything/records',
                'method' => 'GET',
            ];
        }

        if ($moduleKey === 'rentals' && !empty($entities['location'])) {
            $secondary[] = [
                'action' => 'search_rental_assets',
                'params' => [
                    'customer_id' => $customerId,
                    'location' => $entities['location'],
                    'asset_type' => $entities['asset_type'] ?? null,
                    'source' => 'ai_concierge_contextual',
                ],
                'api_endpoint' => '/api/v1/urban-goodz/rentals/assets',
                'method' => 'GET',
            ];
        }

        return $secondary;
    }

    private function resolveEndpoint(string $actionName): string
    {
        return match ($actionName) {
            'create_order_anywhere_request'    => '/api/v1/order-anywhere/requests',
            'get_order_anywhere_status'        => '/api/v1/order-anywhere/requests/{id}',
            'authorize_payment'                => '/api/v1/order-anywhere/requests/{id}/authorize-payment',
            'submit_stylist_request'           => '/api/v1/urban-goodz/fashion/stylist-requests',
            'search_stylists'                  => '/api/v1/urban-goodz/fashion/stylist-requests',
            'get_stylist_requests'             => '/api/v1/urban-goodz/fashion/stylist-requests',
            'submit_book_anything_request'     => '/api/v1/urban-goodz/book-anything/request',
            'search_service_providers'         => '/api/v1/urban-goodz/book-anything/records',
            'get_book_anything_status'         => '/api/v1/urban-goodz/book-anything/records/{id}',
            'search_rental_assets'             => '/api/v1/urban-goodz/rentals/assets',
            'book_rental_asset'                => '/api/v1/urban-goodz/rentals/bookings',
            'get_rental_booking_status'        => '/api/v1/urban-goodz/rentals/bookings/{id}',
            'search_marketplace'               => '/api/v1/urban-goodz/discovery/entities',
            'get_marketplace_item'             => '/api/v1/urban-goodz/discovery/entities/{id}',
            'list_marketplace_item'            => '/api/v1/urban-goodz/discovery/entities',
            'search_medical_courier_jobs'      => '/api/v1/urban-goodz/medical-courier/jobs',
            'get_medical_courier_job'          => '/api/v1/urban-goodz/medical-courier/jobs/{id}',
            'create_medical_courier_job'       => '/api/v1/urban-goodz/medical-courier/jobs',
            'get_medical_courier_status'       => '/api/v1/urban-goodz/medical-courier/jobs/{id}',
            'search_load_board'                => '/api/v1/urban-goodz/load-board/loads',
            'get_load_board_load'              => '/api/v1/urban-goodz/load-board/loads/{id}',
            'post_load_to_board'               => '/api/v1/urban-goodz/load-board/loads',
            'bid_on_load'                      => '/api/v1/urban-goodz/driver/load-board/{id}/bid',
            'track_delivery'                   => '/api/v1/urban-goodz/driver/active-jobs/{id}',
            'get_delivery_status'              => '/api/v1/urban-goodz/driver/active-jobs/{id}',
            'create_delivery_request'          => '/api/v1/urban-goodz/driver/routes',
            'search_creators'                  => '/api/v1/urban-goodz/events',
            'get_creator_profile'              => '/api/v1/urban-goodz/creator/{id}',
            'apply_as_creator'                 => '/api/v1/urban-goodz/creator/apply',
            'get_creator_campaigns'            => '/api/v1/urban-goodz/creator/campaigns',
            'search_community_posts'           => '/api/v1/urban-goodz/community/posts',
            'create_community_post'            => '/api/v1/urban-goodz/community/posts',
            'get_community_post'               => '/api/v1/urban-goodz/community/posts/{id}',
            'search_earn_money_opportunities'  => '/api/v1/urban-goodz/earn-money/opportunities',
            'get_earn_money_opportunity'       => '/api/v1/urban-goodz/earn-money/opportunities/{id}',
            'apply_earn_money_opportunity'     => '/api/v1/urban-goodz/earn-money/opportunities/{id}/accept',
            'search_events'                    => '/api/v1/urban-goodz/events',
            'get_event_detail'                 => '/api/v1/urban-goodz/events/{id}',
            'register_event_interest'          => '/api/v1/urban-goodz/events/{id}/interest',
            default                            => '/api/v1/urban-goodz/discovery/entities',
        };
    }

    private function resolveMethod(string $actionName): string
    {
        return match (true) {
            str_starts_with($actionName, 'create_'),
            str_starts_with($actionName, 'submit_'),
            str_starts_with($actionName, 'list_'),
            str_starts_with($actionName, 'post_'),
            str_starts_with($actionName, 'apply_'),
            str_starts_with($actionName, 'register_'),
            str_starts_with($actionName, 'bid_'),
            str_starts_with($actionName, 'book_'),
            str_starts_with($actionName, 'authorize_') => 'POST',
            default => 'GET',
        };
    }

    private function buildFallbackResponse(string $intentSlug, array $entities, array $customerContext): array
    {
        return [
            'success' => false,
            'module' => null,
            'intent_slug' => $intentSlug,
            'actions' => [],
            'fallback' => true,
            'message' => "I can help with orders, deliveries, bookings, rentals, marketplace searches, and more. "
                . "Could you tell me a bit more about what you need? For example, are you looking to "
                . "place an order, book a service, or track a delivery?",
            'available_capabilities' => [
                'order-anywhere',
                'fashion-fit',
                'book-services',
                'rentals',
                'marketplace-search',
                'medical-courier',
                'load-board',
                'delivery',
                'creator-commerce',
                'community',
                'earn-money',
                'events',
            ],
        ];
    }

    private function buildConfidenceHint(string $intentSlug, array $entities): array
    {
        $hints = [];

        $entityCount = count($entities);
        if ($entityCount === 0) {
            $hints[] = 'No entities extracted — caller may need to prompt for more details.';
        } elseif ($entityCount <= 2) {
            $hints[] = 'Limited entities extracted — consider confirming key details before executing.';
        }

        $criticalFields = match ($intentSlug) {
            'order-anywhere'   => ['store_name', 'items', 'delivery_address'],
            'fashion-fit'      => ['service_type', 'garment_type'],
            'book-services'    => ['service_name', 'preferred_date', 'location'],
            'rentals'          => ['asset_type', 'location'],
            'marketplace'      => ['search_query'],
            'medical-courier'  => ['pickup_location', 'delivery_location'],
            'load-board'       => ['origin_state', 'destination_state'],
            'delivery'         => ['order_id'],
            default            => [],
        };

        $missing = array_diff($criticalFields, array_keys($entities));
        if (!empty($missing)) {
            $hints[] = "Missing critical fields for {$intentSlug}: " . implode(', ', $missing) . '. Consider prompting the customer.';
        }

        return $hints;
    }
}
