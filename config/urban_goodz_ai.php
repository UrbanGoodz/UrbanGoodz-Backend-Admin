<?php

return [
    /*
    | Supported providers: openai, openrouter, gemini, disabled.
    | Unknown values fail closed; provider selection is never inferred from a key.
    |
    | SELECTION PATH (the only one that exists):
    |   config('urban_goodz_ai.provider')                      <- this line
    |     -> App\Services\UrbanGoodz\AI\AIProviderManager::resolve()
    |        -> App\Services\UrbanGoodz\UrbanGoodzAIService::__construct()
    |
    | Presence of GEMINI_API_KEY does NOT select Gemini. The only way to make
    | Gemini the live provider is to set AI_PROVIDER=gemini in .env and clear the
    | config cache. The production default stays 'openai' on purpose - changing it
    | is an owner decision, not a code default. See:
    |   AIProviderManager::selectionDiagnostics()
    |   docs/urban-goodz/notifications/AI_PROVIDER_SELECTION.md
    */
    'provider' => env('AI_PROVIDER', 'openai'),

    /*
    | The provider name the platform falls back to when AI_PROVIDER is absent.
    | Kept as a named constant so tests can assert the shipped default did not
    | silently change.
    */
    'default_provider' => 'openai',
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

        /*
        | Gemini model note (verified against Google AI Studio, July 2026):
        |   gemini-2.5-flash -> HTTP 404, "no longer available to new users"
        |   gemini-2.0-flash -> HTTP 429 on the free tier
        |   gemini-flash-latest -> works; Google repoints the alias itself
        | The default MUST stay a "-latest" alias so a new key keeps working when
        | Google retires a numbered snapshot.
        */
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY', env('GOOGLE_API_KEY')),
            'model' => env('GEMINI_MODEL', 'gemini-flash-latest'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        ],
    ],
];
