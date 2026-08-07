<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;

trait CreatesApplication
{
    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $environment = (string) ($_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?: '');
        $database = (string) ($_SERVER['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: '');
        $host = strtolower((string) ($_SERVER['DB_HOST'] ?? getenv('DB_HOST') ?: ''));
        $allowlist = array_filter(array_map(
            'trim',
            explode(',', (string) ($_SERVER['UG_TEST_DB_ALLOWLIST'] ?? getenv('UG_TEST_DB_ALLOWLIST') ?: ''))
        ));

        if ($environment !== 'testing') {
            throw new \RuntimeException('Unsafe test target: APP_ENV must be testing.');
        }

        // Both halves matter. The allowlist stops a stray DB_DATABASE, and the
        // "_test_" requirement stops the allowlist itself from being pointed at
        // a working database -- which is exactly how the local development
        // database was destroyed once: a RefreshDatabase suite dropped every
        // table, and the from-scratch migrate that followed did not complete.
        if (!in_array($database, $allowlist, true) || !str_contains($database, '_test_')) {
            throw new \RuntimeException('Unsafe test target: database is not explicitly allowlisted.');
        }

        if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], true)
            || preg_match('/prod(uction)?|stag(e|ing)?|demo|live/i', $host)) {
            throw new \RuntimeException('Unsafe test target: only a local database host is permitted.');
        }

        if (preg_match('/prod(uction)?|stag(e|ing)?|demo|live/i', $database)) {
            throw new \RuntimeException('Unsafe test target: production-like database name rejected.');
        }

        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        $configuredConnection = (string) config('database.default');
        $configuredDatabase = (string) config("database.connections.{$configuredConnection}.database");
        $configuredHost = strtolower((string) config("database.connections.{$configuredConnection}.host"));

        if ($configuredConnection !== 'mysql'
            || $configuredDatabase !== $database
            || $configuredHost !== $host) {
            throw new \RuntimeException('Unsafe test target: runtime database configuration changed during bootstrap.');
        }

        return $app;
    }
}
