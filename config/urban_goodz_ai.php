<?php

return [
    /*
    | Supported providers: openai, openrouter, gemini, disabled.
    | Unknown values fail closed; provider selection is never inferred from a key.
    */
    'provider' => env('AI_PROVIDER', 'openai'),
    'request_timeout' => (int) env('AI_REQUEST_TIMEOUT', env('OPENAI_REQUEST_TIMEOUT', 30)),
    'max_retries' => (int) env('AI_MAX_RETRIES', 1),
    'retry_delay_ms' => (int) env('AI_RETRY_DELAY_MS', 250),
    'max_tokens' => (int) env('AI_MAX_TOKENS', 1500),
    'temperature' => (float) env('AI_TEMPERATURE', 0.4),

    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', env('URBAN_GOODZ_AI_MODEL', 'gpt-4o-mini')),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        ],

        'openrouter' => [
            'api_key' => env('OPENROUTER_API_KEY'),
            'model' => env('OPENROUTER_MODEL', ''),
            'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
            'site_url' => env('OPENROUTER_SITE_URL', env('APP_URL')),
            'app_name' => env('OPENROUTER_APP_NAME', 'Urban Goodz'),
        ],

        'gemini' => [
            'api_key' => env('GEMINI_API_KEY', env('GOOGLE_API_KEY')),
            'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        ],
    ],
];
