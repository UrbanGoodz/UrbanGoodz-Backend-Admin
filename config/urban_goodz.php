<?php

return [
    'brand_name' => env('URBAN_GOODZ_BRAND_NAME', 'Urban Goodz'),

    'default_city' => env('URBAN_GOODZ_DEFAULT_CITY', 'Houston'),
    'default_country' => env('URBAN_GOODZ_DEFAULT_COUNTRY', 'US'),
    'distance_unit' => env('URBAN_GOODZ_DISTANCE_UNIT', 'miles'),
    'currency' => env('URBAN_GOODZ_CURRENCY', 'USD'),

    'floating_ai_enabled' => env('URBAN_GOODZ_FLOATING_AI_ENABLED', true),

    'ai_model' => env('URBAN_GOODZ_AI_MODEL', 'gpt-4o'),
    'ai_temperature' => env('URBAN_GOODZ_AI_TEMPERATURE', 0.4),
    'ai_max_tokens' => env('URBAN_GOODZ_AI_MAX_TOKENS', 1500),
    'ai_concierge_enabled' => env('URBAN_GOODZ_AI_CONCIERGE_ENABLED', true),
    'ai_copilot_enabled' => env('URBAN_GOODZ_AI_COPILOT_ENABLED', true),
    'ai_load_board_enabled' => env('URBAN_GOODZ_AI_LOAD_BOARD_ENABLED', true),

    'order_anywhere_owner' => 'master_admin',
];
