<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Urban Goodz request reference.
 *
 * Generates one short, non-guessable reference per HTTP request / CLI run.
 * The SAME value is:
 *   - attached to every log record (see App\Logging\UgRequestIdTap + config/logging.php)
 *   - displayed on branded error pages
 *
 * This lets support staff correlate a user-reported reference with the full
 * exception in storage/logs/laravel.log WITHOUT ever exposing a stack trace,
 * file path, or secret to the browser.
 */
class UgRequestId
{
    protected static ?string $id = null;

    /**
     * Stable reference for the current request. Format: UG-XXXXXXXXXXXX
     */
    public static function get(): string
    {
        if (static::$id === null) {
            static::$id = 'UG-' . strtoupper(Str::random(12));
        }

        return static::$id;
    }

    /**
     * Reset (used by tests only).
     */
    public static function flush(): void
    {
        static::$id = null;
    }
}
