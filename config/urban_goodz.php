<?php

return [

    'legacy_route_chunking' => env('URBAN_GOODZ_LEGACY_ROUTE_CHUNKING', false),

    'distance_matrix' => [
        'provider' => env('URBAN_GOODZ_DISTANCE_MATRIX_PROVIDER', 'haversine'),
        'google_maps_key' => env('URBAN_GOODZ_GOOGLE_MAPS_KEY', ''),
        'cache_ttl_hours' => env('URBAN_GOODZ_DISTANCE_CACHE_TTL_HOURS', 24),
        'batch_size' => env('URBAN_GOODZ_DISTANCE_BATCH_SIZE', 25),
        'request_delay_ms' => env('URBAN_GOODZ_DISTANCE_REQUEST_DELAY_MS', 100),
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
];
