<?php

return [
    'enabled' => env('FASHION_FIT_AI_ENABLED', false),
    'provider' => env('FASHION_FIT_AI_PROVIDER', 'http'),
    'endpoint' => env('FASHION_FIT_AI_ENDPOINT'),
    'api_key' => env('FASHION_FIT_AI_API_KEY'),
    'model' => env('FASHION_FIT_AI_MODEL'),
    'model_version' => env('FASHION_FIT_AI_MODEL_VERSION'),
    'timeout' => (int) env('FASHION_FIT_AI_TIMEOUT', 90),
    'max_attempts' => (int) env('FASHION_FIT_AI_MAX_ATTEMPTS', 3),
    'staged_payments_enabled' => (bool) env('FASHION_FIT_STAGED_PAYMENTS_ENABLED', false),
    'consent_version' => env('FASHION_FIT_CONSENT_VERSION', '2026-07-12'),
    'required_views' => ['front', 'side'],
    'optional_views' => ['back'],
    'max_photo_kb' => 10240,
    'min_width' => 720,
    'min_height' => 1280,
    'allowed_measurements' => [
        'height', 'shoulder_width', 'chest_bust', 'underbust', 'waist', 'high_hip',
        'full_hip', 'neck', 'arm_length', 'upper_arm', 'wrist', 'torso_length',
        'inseam', 'outseam', 'thigh', 'knee', 'calf', 'ankle', 'front_rise',
        'back_rise', 'dress_length', 'sleeve_length',
    ],
];
