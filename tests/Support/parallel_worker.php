<?php

/**
 * Child process for ConcurrentAssignmentTest: boots the app against the test database,
 * waits for a shared start time so the workers collide, runs one operation and prints
 * WIN or LOSE.
 *
 *   php parallel_worker.php <task> <startMicrotime> <id> <driverId>
 */

use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $task, $start, $id, $driverId] = $argv + [null, null, null, null, null];

while (microtime(true) < (float) $start) {
    // busy-wait: every worker releases at the same instant
}

try {
    $result = match ($task) {
        'accept_load' => app(\App\Services\UrbanGoodz\UrbanGoodzLoadBoardService::class)->acceptLoad((int) $id, (int) $driverId, null, true),
        'accept_job' => app(\App\Services\UrbanGoodz\UrbanGoodzMedicalCourierService::class)->assignDriver((int) $id, (int) $driverId),
        'deliver_job' => app(\App\Services\UrbanGoodz\UrbanGoodzMedicalCourierService::class)->updateStatus((int) $id, 'delivered', (int) $driverId),
        'complete_load' => app(\App\Services\UrbanGoodz\UrbanGoodzLoadBoardService::class)->updateStatus((int) $id, 'completed', 1, 'admin'),
        'driver_payout' => \App\Services\UrbanGoodzPayoutGuard::createDriverPayout((int) $id, 80.0, [
            'payee_type' => 'driver', 'delivery_man_id' => (int) $id, 'payout_type' => 'weekly', 'requested_amount' => 80,
            'instant_fee' => 0, 'net_amount' => 80, 'status' => 'pending',
        ]),
        'vendor_payout' => is_object($r = \App\Services\UrbanGoodzPayoutGuard::createVendorPayout((int) $id, 80.0, [
            'payee_type' => 'vendor', 'vendor_id' => (int) $id, 'delivery_man_id' => null, 'payout_type' => 'weekly',
            'requested_amount' => 80, 'instant_fee' => 0, 'net_amount' => 80, 'currency' => 'USD', 'status' => 'pending',
        ])) ? $r : null,
        default => null,
    };
    echo $result ? 'WIN' : 'LOSE';
} catch (\Throwable $e) {
    echo 'ERROR '.get_class($e).': '.$e->getMessage();
}
