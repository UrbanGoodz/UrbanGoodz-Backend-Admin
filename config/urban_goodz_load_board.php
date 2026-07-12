<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Load Board Provider Configuration
    |--------------------------------------------------------------------------
    |
    | Configure API credentials for external load board providers.
    | Set enabled to true and fill in credentials for each active provider.
    |
    */

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
            'default_filters' => [
                'equipment_type' => 'van',
            ],
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
            'default_filters' => [
                'equipment_type' => 'van',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global Sync Settings
    |--------------------------------------------------------------------------
    */

    'sync' => [
        'enabled' => env('LOAD_BOARD_SYNC_ENABLED', true),
        'dry_run' => env('LOAD_BOARD_SYNC_DRY_RUN', false),
        'log_sync_results' => true,
        'purge_unsynced_days' => env('LOAD_BOARD_PURGE_DAYS', 7),
    ],

];
