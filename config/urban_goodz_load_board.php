<?php

return [

    'enabled' => env('LOAD_BOARD_ENABLED', true),

    'providers' => [

        'dat' => [
            'enabled' => env('DAT_LOAD_BOARD_ENABLED', false),
            'api_key' => env('DAT_API_KEY', ''),
            'session_token' => env('DAT_SESSION_TOKEN', ''),
            'base_url' => env('DAT_API_BASE_URL', 'https://api.dat.com'),
            'timeout' => env('DAT_API_TIMEOUT', 30),
            'max_per_sync' => env('DAT_MAX_PER_SYNC', 250),
            'sync_interval_minutes' => env('DAT_SYNC_INTERVAL', 30),
            'default_filters' => ['equipment_type' => 'van'],
        ],

        'truckstop' => [
            'enabled' => env('TRUCKSTOP_LOAD_BOARD_ENABLED', false),
            'client_id' => env('TRUCKSTOP_CLIENT_ID', ''),
            'client_secret' => env('TRUCKSTOP_CLIENT_SECRET', ''),
            'access_token' => env('TRUCKSTOP_ACCESS_TOKEN', ''),
            'base_url' => env('TRUCKSTOP_API_BASE_URL', 'https://api.truckstop.com'),
            'timeout' => env('TRUCKSTOP_API_TIMEOUT', 30),
            'max_per_sync' => env('TRUCKSTOP_MAX_PER_SYNC', 250),
            'sync_interval_minutes' => env('TRUCKSTOP_SYNC_INTERVAL', 30),
            'default_filters' => ['equipment_type' => 'van'],
        ],

        'trulos' => [
            'enabled' => env('TRULOS_LOAD_BOARD_ENABLED', false),
            'api_key' => env('TRULOS_API_KEY', ''),
            'base_url' => env('TRULOS_API_BASE_URL', ''),
            'timeout' => 30,
        ],

        'tb_load' => [
            'enabled' => env('TBLOAD_LOAD_BOARD_ENABLED', false),
            'api_key' => env('TBLOAD_API_KEY', ''),
            'base_url' => env('TBLOAD_API_BASE_URL', ''),
            'timeout' => 30,
        ],

        'direct_freight' => [
            'enabled' => env('DIRECT_FREIGHT_ENABLED', false),
            'api_key' => env('DIRECT_FREIGHT_API_KEY', ''),
            'base_url' => env('DIRECT_FREIGHT_API_BASE_URL', ''),
            'timeout' => 30,
        ],

        'trucker_path' => [
            'enabled' => env('TRUCKER_PATH_ENABLED', false),
            'api_key' => env('TRUCKER_PATH_API_KEY', ''),
            'base_url' => env('TRUCKER_PATH_API_BASE_URL', ''),
            'timeout' => 30,
        ],

        'trucksmarter' => [
            'enabled' => env('TRUCKSMARTER_ENABLED', false),
            'api_key' => env('TRUCKSMARTER_API_KEY', ''),
            'base_url' => env('TRUCKSMARTER_API_BASE_URL', ''),
            'timeout' => 30,
        ],
    ],

    'sourcing' => [
        'platform_fee_percent' => (float) env('SOURCING_PLATFORM_FEE_PERCENT', 12.0),
        'fuel_cost_per_mile' => (float) env('SOURCING_FUEL_COST_PER_MILE', 0.75),
        'toll_estimation_per_mile' => (float) env('SOURCING_TOLL_ESTIMATION_PER_MILE', 0.05),
        'default_max_deadhead_miles' => (int) env('SOURCING_MAX_DEADHEAD', 100),
        'minimum_confidence_threshold' => (int) env('SOURCING_MIN_CONFIDENCE', 30),
        'auto_alert_threshold' => (int) env('SOURCING_AUTO_ALERT_THRESHOLD', 70),
    ],

    'sync' => [
        'enabled' => env('LOAD_BOARD_SYNC_ENABLED', true),
        'dry_run' => env('LOAD_BOARD_SYNC_DRY_RUN', false),
        'log_sync_results' => true,
        'purge_unsynced_days' => env('LOAD_BOARD_PURGE_DAYS', 7),
    ],

];
