<?php

return [
    'mode' => env('URBAN_GOODZ_PAYMENT_MODE', 'staged_test'),
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
    'staged_test' => [
        'enabled' => env('URBAN_GOODZ_STAGED_TEST_ENABLED', true),
    ],
    'currency' => env('URBAN_GOODZ_CURRENCY', 'USD'),
    'default_platform_fee_percent' => env('URBAN_GOODZ_PLATFORM_FEE_PERCENT', 10),
];
