<?php

/*
| Error tracking (Sentry). Entirely OFF until SENTRY_LARAVEL_DSN is set in .env, so deploying this changes
| nothing by itself: no DSN, no network calls, no data leaves the server.
|
| Privacy: send_default_pii is false (no cookies, IPs, user details), SQL bindings are not attached to
| breadcrumbs, and performance tracing is off. Only the exception, its stack trace and the route are sent.
*/
return [
    'dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),

    'release' => env('SENTRY_RELEASE'),

    'environment' => env('SENTRY_ENVIRONMENT', env('APP_ENV', 'production')),

    'send_default_pii' => false,

    'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.0),

    'breadcrumbs' => [
        'logs' => true,
        'cache' => false,
        'livewire' => false,
        'sql_queries' => false,
        'sql_bindings' => false,
        'queue_info' => true,
        'command_info' => true,
        'http_client_requests' => false,
        'notifications' => false,
    ],

    'tracing' => [
        'queue_job_transactions' => false,
        'queue_jobs' => false,
        'sql_queries' => false,
        'sql_origin' => false,
        'views' => false,
        'default_integrations' => false,
    ],

    // Expected, noisy exceptions that are not bugs.
    'ignore_exceptions' => [
        Illuminate\Auth\AuthenticationException::class,
        Illuminate\Validation\ValidationException::class,
        Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        Illuminate\Session\TokenMismatchException::class,
    ],
];
