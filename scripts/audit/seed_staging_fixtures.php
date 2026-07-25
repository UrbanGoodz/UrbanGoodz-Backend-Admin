<?php

/**
 * Runs StagingRoleFixtureSeeder against the isolated staging database.
 *
 * This worktree shares its vendor/ directory with the main backend checkout,
 * so `composer dump-autoload` (which would write into that shared vendor tree)
 * is deliberately avoided. The seeder class is required explicitly instead,
 * which keeps the main repo untouched.
 *
 * Usage:
 *     export STAGING_FIXTURE_PASSWORD=...
 *     APP_ENV=staging php scripts/audit/seed_staging_fixtures.php
 */

$base = dirname(__DIR__, 2);

require $base.'/vendor/autoload.php';

/** @var Illuminate\Foundation\Application $app */
$app = require $base.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

require_once $base.'/database/seeders/StagingRoleFixtureSeeder.php';

$db = Illuminate\Support\Facades\DB::connection()->getDatabaseName();
fwrite(STDOUT, "env={$app->environment()} db={$db}\n");

try {
    $seeder = new Database\Seeders\StagingRoleFixtureSeeder();
    $seeder->setContainer($app);
    $seeder->run();
} catch (Throwable $e) {
    // Never echo the raw driver message: Laravel appends the full SQL with its
    // bindings, which for these tables includes password hashes.
    $msg = $e->getMessage();
    if (($cut = stripos($msg, ' (Connection:')) !== false) {
        $msg = substr($msg, 0, $cut);
    }
    fwrite(STDERR, 'FIXTURE SEED FAILED: '.$msg."\n");
    exit(1);
}

// Report what exists now, without printing any credential material.
$report = [
    'admins (9001 full, 9002 restricted)' => ['admins', [9001, 9002]],
    'users / shopper'                     => ['users', [9001]],
    'vendors approved/pending/rejected'   => ['vendors', [9001, 9002, 9003]],
    'stores approved/pending/rejected'    => ['stores', [9001, 9002, 9003]],
    'delivery_men online/offline/pending' => ['delivery_men', [9001, 9002, 9003]],
    'business client'                     => ['urban_goodz_business_clients', [9001]],
    'business owner + dispatcher'         => ['urban_goodz_business_client_users', [9001, 9002]],
];

$ok = true;
foreach ($report as $label => [$table, $ids]) {
    $found = Illuminate\Support\Facades\DB::table($table)->whereIn('id', $ids)->count();
    $want = count($ids);
    $mark = $found === $want ? 'OK  ' : 'MISS';
    if ($found !== $want) {
        $ok = false;
    }
    fwrite(STDOUT, sprintf("[%s] %-38s %d/%d\n", $mark, $label, $found, $want));
}

// State assertions - the whole point of "deterministic" fixtures.
$assert = [
    'store 9001 approved' => ['stores', 9001, 'admin_approval_status', 'approved'],
    'store 9002 pending'  => ['stores', 9002, 'admin_approval_status', 'pending'],
    'store 9003 rejected' => ['stores', 9003, 'admin_approval_status', 'rejected'],
    'driver 9001 online'  => ['delivery_men', 9001, 'active', 1],
    'driver 9002 offline' => ['delivery_men', 9002, 'active', 0],
    'driver 9003 pending' => ['delivery_men', 9003, 'application_status', 'pending'],
    'dispatcher role'     => ['urban_goodz_business_client_users', 9002, 'role', 'dispatcher'],
];

foreach ($assert as $label => [$table, $id, $col, $want]) {
    $got = Illuminate\Support\Facades\DB::table($table)->where('id', $id)->value($col);
    $pass = (string) $got === (string) $want;
    if (! $pass) {
        $ok = false;
    }
    fwrite(STDOUT, sprintf("[%s] %-38s %s=%s\n", $pass ? 'OK  ' : 'FAIL', $label, $col, var_export($got, true)));
}

fwrite(STDOUT, $ok ? "FIXTURES: ALL DETERMINISTIC STATES VERIFIED\n" : "FIXTURES: INCOMPLETE\n");
exit($ok ? 0 : 1);
