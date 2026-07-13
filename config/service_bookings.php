<?php

return [
    'categories' => ['barber','hair_stylist','braider','nail_technician','makeup_artist','mobile_mechanic','photographer','dj','contractor','tax_professional','home_health_provider','personal_trainer'],
    'platform_fee_percent' => (float) env('SERVICE_BOOKING_PLATFORM_FEE_PERCENT', 15),
    'payment' => [
        'sandbox' => filter_var(env('SERVICE_BOOKING_PAYMENT_SANDBOX', true), FILTER_VALIDATE_BOOL),
        'endpoint' => env('SERVICE_BOOKING_PAYMENT_ENDPOINT'),
        'secret' => env('SERVICE_BOOKING_PAYMENT_SECRET'),
        'timeout' => (int) env('SERVICE_BOOKING_PAYMENT_TIMEOUT', 30),
    ],
];
