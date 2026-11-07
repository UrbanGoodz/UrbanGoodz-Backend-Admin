<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\InsertsRows;
use Tests\TestCase;

/**
 * Real concurrency on money-out: eight OS processes request a payout of 80 against a
 * balance that covers exactly one. Exactly one request may be created and the payee may
 * never be able to withdraw more than they are owed.
 *
 * Commits rows (separate processes cannot see an open transaction), cleans up after itself,
 * and only runs against the allow-listed test database.
 */
class PayoutRaceTest extends TestCase
{
    use InsertsRows;

    private const WORKERS = 8;

    private array $drivers = [];
    private array $vendors = [];

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('urban_goodz_driver_payout_requests')->where(fn ($q) => $q->whereIn('delivery_man_id', $this->drivers)->orWhereIn('vendor_id', $this->vendors))->delete();
        DB::table('urban_goodz_driver_earnings')->whereIn('delivery_man_id', $this->drivers)->delete();
        DB::table('delivery_men')->whereIn('id', $this->drivers)->delete();
        DB::table('store_wallets')->whereIn('vendor_id', $this->vendors)->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        parent::tearDown();
    }

    private function race(string $task, int $id): array
    {
        $env = [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => config('database.connections.mysql.host'),
            'DB_PORT' => (string) config('database.connections.mysql.port'), 'DB_DATABASE' => config('database.connections.mysql.database'),
            'DB_USERNAME' => config('database.connections.mysql.username'), 'DB_PASSWORD' => (string) config('database.connections.mysql.password'),
            'UG_TEST_DB_ALLOWLIST' => config('database.connections.mysql.database'), 'CACHE_DRIVER' => 'array', 'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync', 'PATH' => getenv('PATH'), 'HOME' => getenv('HOME') ?: '/tmp',
        ];
        $start = microtime(true) + 3.0;
        $procs = [];
        for ($i = 0; $i < self::WORKERS; $i++) {
            $cmd = [PHP_BINARY, base_path('tests/Support/parallel_worker.php'), $task, sprintf('%.6f', $start), (string) $id, (string) $id];
            $procs[] = [proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $env), $pipes];
        }
        $out = [];
        foreach ($procs as [$proc, $pipes]) {
            $out[] = trim(stream_get_contents($pipes[1]).' '.stream_get_contents($pipes[2]));
            proc_close($proc);
        }

        return $out;
    }

    private function assertExactlyOneWin(array $results): void
    {
        $this->assertSame([], array_filter($results, fn ($r) => str_starts_with($r, 'ERROR')), 'a worker crashed: '.implode(' | ', $results));
        $this->assertSame(1, count(array_filter($results, fn ($r) => str_contains($r, 'WIN'))), 'wins: '.implode(',', $results));
    }

    public function test_concurrent_driver_withdrawals_cannot_exceed_the_balance(): void
    {
        $driver = $this->drivers[] = (int) $this->insertRow('delivery_men', ['status' => 1, 'active' => 1, 'application_status' => 'approved', 'email' => 'payout-'.uniqid().'@urbangoodz.test', 'phone' => '+1555'.random_int(1000000, 9999999)]);
        $this->insertRow('urban_goodz_driver_earnings', ['delivery_man_id' => $driver, 'amount' => 100, 'status' => 'pending']);

        $results = $this->race('driver_payout', $driver);

        $this->assertExactlyOneWin($results);
        $this->assertSame(1, DB::table('urban_goodz_driver_payout_requests')->where('delivery_man_id', $driver)->count());
        $this->assertLessThanOrEqual(100.0, (float) DB::table('urban_goodz_driver_payout_requests')->where('delivery_man_id', $driver)->sum('requested_amount'));
    }

    public function test_concurrent_vendor_withdrawals_create_one_request_and_reserve_once(): void
    {
        $vendor = 880000 + random_int(1, 9999);
        $this->vendors[] = $vendor;
        $this->insertRow('store_wallets', ['vendor_id' => $vendor, 'total_earning' => 100, 'total_withdrawn' => 0, 'pending_withdraw' => 0, 'collected_cash' => 0]);

        $results = $this->race('vendor_payout', $vendor);

        $this->assertExactlyOneWin($results);
        $this->assertSame(1, DB::table('urban_goodz_driver_payout_requests')->where('vendor_id', $vendor)->count());
        $this->assertSame(80.0, (float) DB::table('store_wallets')->where('vendor_id', $vendor)->value('pending_withdraw'), 'the reserve must be taken exactly once');
    }
}
