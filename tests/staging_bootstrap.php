<?php

/**
 * PHPUnit bootstrap for the staging P0 suite.
 *
 * This worktree shares vendor/ with the main backend checkout, so Composer's
 * generated PSR-4 map resolves the "Tests\" namespace to the MAIN repo's
 * tests/ directory. Tests\CreatesApplication then does
 * `require __DIR__.'/../bootstrap/app.php'`, which booted the main repo's
 * application instead of this worktree - silently testing the wrong code
 * against the wrong database.
 *
 * Regenerating the autoloader would write into the shared vendor tree, so
 * instead we prepend a loader that binds "Tests\" to THIS worktree.
 */

$base = dirname(__DIR__);

require $base.'/vendor/autoload.php';

/**
 * The same shadowing applies to "App\" and "Database\": without this, the
 * suite would exercise the MAIN repo's controllers and seeders while claiming
 * to test this worktree. Every namespace this worktree owns is rebound here.
 */
$prefixes = [
    'Tests\\'    => $base.'/tests/',
    'App\\'      => $base.'/app/',
    'Database\\' => $base.'/database/',
];

spl_autoload_register(static function (string $class) use ($prefixes): void {
    foreach ($prefixes as $prefix => $dir) {
        if (! str_starts_with($class, $prefix)) {
            continue;
        }

        $relative = substr($class, strlen($prefix));
        $path = $dir.str_replace('\\', '/', $relative).'.php';

        if (is_file($path)) {
            require_once $path;

            return;
        }
    }
}, true, true); // prepend, so it wins over the shared vendor classmap
