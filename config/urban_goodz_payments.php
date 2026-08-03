<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Payment Provider
    |--------------------------------------------------------------------------
    |
    | staged_test   — Local simulated payments only, no external API calls
    | adyen         — Adyen API (test or live depending on mode)
    | stripe        — Stripe API (test or live depending on mode)
    | disabled      — All payment actions blocked
    |
    */
    'provider' => env('URBAN_GOODZ_PAYMENT_PROVIDER', 'staged_test'),

    /*
    |--------------------------------------------------------------------------
    | Payment Mode
    |--------------------------------------------------------------------------
    |
    | sandbox           — Test environment, test keys, no real money
    | live_controlled    — Live keys enforced, low dollar cap, admin confirmation
    | disabled           — No payment capture allowed at all
    |
    */
    'mode' => env('URBAN_GOODZ_PAYMENT_MODE', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | Adyen Configuration
    |--------------------------------------------------------------------------
    */
    'adyen' => [
        'enabled' => env('URBAN_GOODZ_ADYEN_ENABLED', false),
        'env' => env('URBAN_GOODZ_ADYEN_ENV', 'sandbox'),
        'api_key' => env('URBAN_GOODZ_ADYEN_API_KEY', ''),
        'merchant_account' => env('URBAN_GOODZ_ADYEN_MERCHANT_ACCOUNT', ''),
        'client_key' => env('URBAN_GOODZ_ADYEN_CLIENT_KEY', ''),
        'hmac_key' => env('URBAN_GOODZ_ADYEN_HMAC_KEY', ''),
        'username' => env('URBAN_GOODZ_ADYEN_USERNAME', ''),
        'password' => env('URBAN_GOODZ_ADYEN_PASSWORD', ''),
        'origin' => env('URBAN_GOODZ_ADYEN_ORIGIN', 'https://localhost'),
        'return_url' => env('URBAN_GOODZ_ADYEN_RETURN_URL', 'https://localhost/adyen/callback'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stripe Configuration
    |--------------------------------------------------------------------------
    |
    | Sandbox keys:   STRIPE_PUBLISHABLE_KEY / STRIPE_SECRET_KEY / STRIPE_WEBHOOK_SECRET
    | Live keys:      STRIPE_LIVE_PUBLISHABLE_KEY / STRIPE_LIVE_SECRET_KEY / STRIPE_LIVE_WEBHOOK_SECRET
    | Connect:        STRIPE_CONNECT_CLIENT_ID / STRIPE_CONNECT_ACCOUNT_ID (optional)
    |
    */
    'stripe' => [
        'enabled' => env('STRIPE_ENABLED', false),
        'capture_method' => env('STRIPE_CAPTURE_METHOD', 'automatic'),
        'success_url' => env('STRIPE_SUCCESS_URL', 'https://localhost/stripe/success'),
        'cancel_url' => env('STRIPE_CANCEL_URL', 'https://localhost/stripe/cancel'),
        // Sandbox / Test
        'publishable_key' => env('STRIPE_PUBLISHABLE_KEY', ''),
        'secret_key' => env('STRIPE_SECRET_KEY', ''),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', ''),
        // Live
        'live_publishable_key' => env('STRIPE_LIVE_PUBLISHABLE_KEY', ''),
        'live_secret_key' => env('STRIPE_LIVE_SECRET_KEY', ''),
        'live_webhook_secret' => env('STRIPE_LIVE_WEBHOOK_SECRET', ''),
        // Connect (optional — not enabled until configured)
        'connect_client_id' => env('STRIPE_CONNECT_CLIENT_ID', ''),
        'connect_account_id' => env('STRIPE_CONNECT_ACCOUNT_ID', ''),
        'connect_enabled' => env('STRIPE_CONNECT_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Staged Test (DB-only, no external API calls)
    |--------------------------------------------------------------------------
    */
    'staged_test' => [
        'enabled' => env('URBAN_GOODZ_STAGED_TEST_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Live Controlled Safeguards
    |--------------------------------------------------------------------------
    */
    'live_controlled' => [
        'enabled' => env('ORDER_ANYWHERE_LIVE_PAYMENTS_ENABLED', false),
        'max_amount' => (float) env('ORDER_ANYWHERE_MAX_LIVE_TEST_AMOUNT', 50.00),
        'allowed_customers' => array_filter(explode(',', env('ORDER_ANYWHERE_ALLOWED_TEST_CUSTOMERS', ''))),
        'allowed_admins' => array_filter(explode(',', env('ORDER_ANYWHERE_ALLOWED_ADMIN_USERS', ''))),
    ],

    /*
    |--------------------------------------------------------------------------
    | General
    |--------------------------------------------------------------------------
    */
    'currency' => env('URBAN_GOODZ_CURRENCY', 'USD'),
    // Owner-managed database settings take precedence. The hard-coded value is
    // intentionally isolated as a sandbox/disabled safety fallback and is never
    // permitted to supply Live-controlled economics.
    'default_platform_fee_percent' => env('URBAN_GOODZ_PLATFORM_FEE_PERCENT') !== null
        ? (float) env('URBAN_GOODZ_PLATFORM_FEE_PERCENT')
        : null,
    'safe_non_live_platform_fee_percent' => 10.0,
    'default_dispatcher_commission_rate' => (float) env('URBAN_GOODZ_DISPATCHER_COMMISSION_RATE', 0),

    /*
    |--------------------------------------------------------------------------
    | Card Issuing Configuration
    |--------------------------------------------------------------------------
    |
    | issuing_provider  — manual|stripe|marqeta|lithic|airwallex|adyen
    | cards_mode        — disabled|sandbox|live_controlled
    |
    */
    'issuing' => [
        'provider' => env('URBAN_GOODZ_ISSUING_PROVIDER', 'manual'),
        'mode' => env('URBAN_GOODZ_CARDS_MODE', 'sandbox'),
        'max_driver_card_amount' => (float) env('URBAN_GOODZ_MAX_DRIVER_CARD_AMOUNT', 50.00),
        'driver_card_buffer_percent' => (float) env('URBAN_GOODZ_DRIVER_CARD_BUFFER_PERCENT', 10),
        'default_expiry_minutes' => (int) env('URBAN_GOODZ_DRIVER_CARD_DEFAULT_EXPIRY_MINUTES', 120),
    ],
];
