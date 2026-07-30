<?php

return [

    'legacy_route_chunking' => env('URBAN_GOODZ_LEGACY_ROUTE_CHUNKING', false),

    // provider: haversine | google_maps | openrouteservice
    'distance_matrix' => [
        'provider' => env('URBAN_GOODZ_DISTANCE_MATRIX_PROVIDER', 'haversine'),
        'google_maps_key' => env('URBAN_GOODZ_GOOGLE_MAPS_KEY', ''),
        'cache_ttl_hours' => env('URBAN_GOODZ_DISTANCE_CACHE_TTL_HOURS', 24),
        'batch_size' => env('URBAN_GOODZ_DISTANCE_BATCH_SIZE', 25),
        'request_delay_ms' => env('URBAN_GOODZ_DISTANCE_REQUEST_DELAY_MS', 100),
    ],

    /*
     * OpenRouteService (openrouteservice.org) -- road routing.
     * This is NOT openrouter.ai (OPENROUTER_*), which is an LLM gateway.
     * Set URBAN_GOODZ_DISTANCE_MATRIX_PROVIDER=openrouteservice to use it.
     */
    'openrouteservice' => [
        'enabled' => env('ORS_ENABLED', false),
        'api_key' => env('ORS_API_KEY', ''),
        'base_url' => env('ORS_BASE_URL', 'https://api.openrouteservice.org'),
        'profile' => env('ORS_PROFILE', 'driving-car'),
        'timeout_seconds' => env('ORS_TIMEOUT_SECONDS', 8),
        'connect_timeout_seconds' => env('ORS_CONNECT_TIMEOUT_SECONDS', 4),
        'max_retries' => env('ORS_MAX_RETRIES', 2),
        'retry_base_delay_ms' => env('ORS_RETRY_BASE_DELAY_MS', 250),
        'max_retry_after_seconds' => env('ORS_MAX_RETRY_AFTER_SECONDS', 5),
        'cache_ttl_hours' => env('ORS_CACHE_TTL_HOURS', 24),
        'max_locations' => env('ORS_MAX_LOCATIONS', 50),
    ],

    'clustering' => [
        'algorithm_version' => '1.0.0',
        'seeding' => 'kmeans_plus_plus',
        'max_iterations' => env('URBAN_GOODZ_CLUSTER_MAX_ITERATIONS', 100),
        'rebalance_threshold' => env('URBAN_GOODZ_CLUSTER_REBALANCE_THRESHOLD', 0.3),
    ],

    'sequencing' => [
        'algorithm_version' => '1.0.0',
        'initial' => 'nearest_feasible_neighbor',
        'improvement' => '2opt',
        'max_2opt_iterations' => env('URBAN_GOODZ_2OPT_MAX_ITERATIONS', 50),
    ],

    'planning' => [
        'default_service_time_minutes' => env('URBAN_GOODZ_DEFAULT_SERVICE_TIME_MINUTES', 10),
        'default_average_speed_mph' => env('URBAN_GOODZ_DEFAULT_AVG_SPEED_MPH', 30),
        'driver_shift_limit_hours' => env('URBAN_GOODZ_DRIVER_SHIFT_LIMIT_HOURS', 10),
        'break_after_hours' => env('URBAN_GOODZ_BREAK_AFTER_HOURS', 5),
        'break_duration_minutes' => env('URBAN_GOODZ_BREAK_DURATION_MINUTES', 30),
    ],

    'ai_workforce' => [
        'enabled' => env('URBAN_GOODZ_AI_WORKFORCE_ENABLED', false),
        'global_kill_switch' => env('URBAN_GOODZ_AI_WORKFORCE_KILL_SWITCH', false),

        'demand_thresholds' => [
            'enabled' => env('URBAN_GOODZ_DEMAND_TRIGGER_ENABLED', true),
            'rolling_window_days' => env('URBAN_GOODZ_DEMAND_WINDOW_DAYS', 30),
            'min_requests' => env('URBAN_GOODZ_DEMAND_MIN_REQUESTS', 3),
            'min_unique_customers' => env('URBAN_GOODZ_DEMAND_MIN_CUSTOMERS', 2),
            'min_estimated_value' => env('URBAN_GOODZ_DEMAND_MIN_VALUE', 0),
            'valid_statuses' => ['pending_review', 'sourcing', 'quote_ready', 'awaiting_payment', 'approved', 'completed'],
            'excluded_categories' => [],
            'excluded_businesses' => [],
            'cooldown_days' => env('URBAN_GOODZ_DEMAND_COOLDOWN_DAYS', 30),
        ],

        'outreach' => [
            'automatic_enabled' => env('URBAN_GOODZ_OUTREACH_AUTO_ENABLED', false),
            'first_send_approval_required' => env('URBAN_GOODZ_OUTREACH_FIRST_APPROVAL', true),
            'max_contact_attempts' => env('URBAN_GOODZ_OUTREACH_MAX_ATTEMPTS', 4),
            'sequence_days' => [0, 3, 7, 12],
            'sending_hours_start' => env('URBAN_GOODZ_OUTREACH_HOURS_START', '09:00'),
            'sending_hours_end' => env('URBAN_GOODZ_OUTREACH_HOURS_END', '17:00'),
            'sending_timezone' => env('URBAN_GOODZ_OUTREACH_TIMEZONE', 'America/Chicago'),
            'sender_name' => env('URBAN_GOODZ_OUTREACH_SENDER_NAME', 'Urban Goodz'),
            'sender_email' => env('URBAN_GOODZ_OUTREACH_SENDER_EMAIL', ''),
            'reply_to_email' => env('URBAN_GOODZ_OUTREACH_REPLY_TO', ''),
            'physical_address' => env('URBAN_GOODZ_OUTREACH_ADDRESS', ''),
            'unsubscribe_url' => env('URBAN_GOODZ_OUTREACH_UNSUBSCRIBE_URL', ''),
            'onboarding_url' => env('URBAN_GOODZ_OUTREACH_ONBOARDING_URL', ''),
            'domain_cooldown_seconds' => env('URBAN_GOODZ_OUTREACH_DOMAIN_COOLDOWN', 60),
            'prospect_cooldown_days' => env('URBAN_GOODZ_OUTREACH_PROSPECT_COOLDOWN', 3),
        ],

        'daily_brief' => [
            'enabled' => env('URBAN_GOODZ_DAILY_BRIEF_ENABLED', true),
            'send_hour' => env('URBAN_GOODZ_DAILY_BRIEF_HOUR', '08:00'),
            'recipients' => [], // Admin IDs
        ],

        'market_filters' => [],
    ],
];

