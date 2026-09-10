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
    'provider' => env('AI_PROVIDER', 'gemini'),
    'fallback_provider' => env('AI_FALLBACK_PROVIDER', 'openai'),

    /*
    | The provider name AIProviderManager::selectionDiagnostics() reports as
    | the shipped default. Kept as its own key (rather than re-deriving from
    | 'provider' above) so a diagnostics caller can tell "this is what ships"
    | from "this is what AI_PROVIDER is currently set to" -- must track the
    | fallback literal on the 'provider' line above.
    */
    'default_provider' => 'gemini',
    'request_timeout' => (int) env('AI_REQUEST_TIMEOUT', env('OPENAI_REQUEST_TIMEOUT', 30)),
    'max_retries' => (int) env('AI_MAX_RETRIES', 1),
    'retry_delay_ms' => (int) env('AI_RETRY_DELAY_MS', 250),
    // Gemini 3.x bills internal reasoning against maxOutputTokens — a measured
    // reply of 114 tokens spent 521 more on thinking. Too low a ceiling
    // truncates the visible answer mid-sentence with finishReason STOP, so this
    // carries headroom for the reasoning budget as well as the reply.
    'max_tokens' => (int) env('AI_MAX_TOKENS', 3000),
    'temperature' => (float) env('AI_TEMPERATURE', 0.4),

    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', env('URBAN_GOODZ_AI_MODEL', 'gpt-4o-mini')),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        ],

        'openrouter' => [
            'api_key' => env('OPENROUTER_API_KEY'),
            'model' => env('OPENROUTER_MODEL', 'openai/gpt-4o-mini'),
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
            // gemini-flash-latest is verified live against the production key;
            // Google automatically points it to the current stable Flash snapshot.
            'model' => env('GEMINI_MODEL', 'gemini-flash-latest'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        ],
    ],

    /*
    | Tavus CVI -- the real-time video avatar (a live face that actually
    | talks/lip-syncs), separate from the text/voice concierge above. Optional:
    | UrbanGoodzTavusService::isConfigured() gates every call, so the customer
    | app's video-avatar entry point degrades to "not available right now"
    | rather than failing, until these are set.
    */
    'tavus' => [
        'api_key' => env('TAVUS_API_KEY'),
        'base_url' => env('TAVUS_BASE_URL', 'https://tavusapi.com/v2'),
        // A "face" (avatar likeness) and a "PAL" (persona/behavior config),
        // both created once in the Tavus dashboard from the concierge's photo
        // (displayed as "Skylar"; env keys below keep their original names).
        'face_id' => env('TAVUS_MONIQUE_FACE_ID'),
        'pal_id' => env('TAVUS_MONIQUE_PAL_ID'),
    ],

    /*
    | Monique Chief of Staff Execution & Entitlement Configuration
    */
    'execution' => [
        'default_adapter' => env('AI_EXECUTION_ADAPTER', 'native'),
        'polsia' => [
            'api_key' => env('POLSIA_API_KEY'),
            'endpoint' => env('POLSIA_ENDPOINT', 'https://api.polsia.com/v1'),
            'timeout' => (int) env('POLSIA_TIMEOUT', 30),
        ],
    ],

    'monique_pricing' => [
        'trial_days' => (int) env('MONIQUE_TRIAL_DAYS', 30),
        'monthly_fee' => (float) env('MONIQUE_MONTHLY_FEE', 49.00),
        'post_trial_policy' => env('MONIQUE_POST_TRIAL_POLICY', 'auto_charge'), // 'auto_charge', 'explicit_opt_in', 'auto_disable'
        'default_auto_continue' => (bool) env('MONIQUE_AUTO_CONTINUE', true),
    ],
];
