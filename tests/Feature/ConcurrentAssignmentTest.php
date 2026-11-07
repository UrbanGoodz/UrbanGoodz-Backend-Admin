<?php

namespace Tests\Feature;

use App\Models\DeliveryMan;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InsertsRows;
use Tests\TestCase;

/**
 * Real concurrency: several OS processes hit the same load / specimen job at the same
 * instant. Exactly one may win, and money may move exactly once.
 *
 * This test COMMITS rows (separate processes cannot see an open transaction), so it cleans
 * up after itself and only ever runs against the allow-listed test database
 * (tests/CreatesApplication.php enforces that).
 */
class ConcurrentAssignmentTest extends TestCase
{
    use InsertsRows;

    private const WORKERS = 8;

    private array $drivers = [];
    private array $loads = [];
    private array $jobs = [];

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('urban_goodz_load_board_audit_logs')->whereIn('load_id', $this->loads)->delete();
        DB::table('urban_goodz_load_board_loads')->whereIn('id', $this->loads)->delete();
        DB::table('urban_goodz_medical_courier_custody_logs')->whereIn('job_id', $this->jobs)->delete();
        DB::table('urban_goodz_medical_courier_jobs')->whereIn('id', $this->jobs)->delete();
        DB::table('urban_goodz_driver_earnings')->whereIn('delivery_man_id', $this->drivers)->delete();
        DB::table('delivery_men')->whereIn('id', $this->drivers)->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        parent::tearDown();
    }

    private function driver(): int
    {
        $id = (int) DB::table('delivery_men')->insertGetId([
            'f_name' => 'Race', 'l_name' => 'Driver', 'phone' => '+1555'.random_int(1000000, 9999999),
            'email' => 'race-'.uniqid().'@urbangoodz.test', 'password' => 'x', 'status' => 1, 'active' => 1,
            'application_status' => 'approved', 'load_board_eligible' => 1, 'has_medical_courier_training' => 1,
            'current_orders' => 0, 'created_at' => now(), 'updated_at' => now(),
        ] + $this->requiredDriverColumns());

        return $this->drivers[] = $id;
    }

    private function requiredDriverColumns(): array
    {
        $extra = [];
        foreach (\Illuminate\Support\Facades\Schema::getColumns('delivery_men') as $col) {
            if (! $col['nullable'] && $col['default'] === null && ! $col['auto_increment']
                && ! in_array($col['name'], ['f_name', 'l_name', 'phone', 'email', 'password', 'status', 'active', 'application_status', 'created_at', 'updated_at'], true)) {
                $extra[$col['name']] = str_contains(strtolower($col['type_name']), 'int') || in_array(strtolower($col['type_name']), ['decimal', 'float', 'double'], true) ? 0 : 'x';
            }
        }

        return $extra;
    }

    /** @return string[] one WIN / LOSE / ERROR line per worker */
    private function race(string $task, int $id, array $driverIds): array
    {
        $env = [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => config('database.connections.mysql.host'),
            'DB_PORT' => (string) config('database.connections.mysql.port'), 'DB_DATABASE' => config('database.connections.mysql.database'),
            'DB_USERNAME' => config('database.connections.mysql.username'), 'DB_PASSWORD' => (string) config('database.connections.mysql.password'),
            'UG_TEST_DB_ALLOWLIST' => config('database.connections.mysql.database'), 'CACHE_DRIVER' => 'array', 'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync', 'PATH' => getenv('PATH'), 'HOME' => getenv('HOME') ?: '/tmp',
        ];
        $start = microtime(true) + 3.0;   // enough for every worker to boot before the gun
        $procs = [];
        foreach ($driverIds as $driverId) {
            $cmd = [PHP_BINARY, base_path('tests/Support/parallel_worker.php'), $task, sprintf('%.6f', $start), (string) $id, (string) $driverId];
            $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $env);
            $procs[] = [$proc, $pipes];
        }
        $out = [];
        foreach ($procs as [$proc, $pipes]) {
            $out[] = trim(stream_get_contents($pipes[1]).' '.stream_get_contents($pipes[2]));
            proc_close($proc);
        }

        return $out;
    }

    public function test_eight_drivers_racing_for_one_load_produce_exactly_one_assignment(): void
    {
        $load = $this->loads[] = $this->insertRow('urban_goodz_load_board_loads', ['status' => 'available', 'load_number' => 'RACE-'.uniqid(), 'is_hazmat' => 0]);
        $drivers = array_map(fn () => $this->driver(), range(1, self::WORKERS));

        $results = $this->race('accept_load', $load, $drivers);

        $this->assertSame([], array_filter($results, fn ($r) => str_starts_with($r, 'ERROR')), 'a worker crashed: '.implode(' | ', $results));
        $this->assertSame(1, count(array_filter($results, fn ($r) => str_contains($r, 'WIN'))), 'wins: '.implode(',', $results));

        $assigned = (int) DB::table('urban_goodz_load_board_loads')->where('id', $load)->value('assigned_driver_id');
        $this->assertContains($assigned, $drivers);
        $this->assertSame(1, (int) DB::table('delivery_men')->whereIn('id', $drivers)->sum('current_orders'), 'only the winner may have its order counter raised');
        $this->assertSame(1, DB::table('urban_goodz_load_board_audit_logs')->where('load_id', $load)->where('new_value', 'assigned')->count(), 'one assignment must leave one audit event');
    }

    public function test_eight_drivers_racing_for_one_specimen_job_produce_exactly_one_assignment(): void
    {
        $job = $this->jobs[] = $this->insertRow('urban_goodz_medical_courier_jobs', ['status' => 'pending', 'job_number' => 'RACE-'.uniqid()]);
        $drivers = array_map(fn () => $this->driver(), range(1, self::WORKERS));

        $results = $this->race('accept_job', $job, $drivers);

        $this->assertSame([], array_filter($results, fn ($r) => str_starts_with($r, 'ERROR')), 'a worker crashed: '.implode(' | ', $results));
        $this->assertSame(1, count(array_filter($results, fn ($r) => str_contains($r, 'WIN'))), 'wins: '.implode(',', $results));
        $this->assertSame(1, DB::table('urban_goodz_medical_courier_custody_logs')->where('job_id', $job)->where('action', 'assigned')->count());
    }

    public function test_concurrent_delivery_confirmations_pay_the_driver_exactly_once(): void
    {
        $driver = $this->driver();
        $job = $this->jobs[] = $this->insertRow('urban_goodz_medical_courier_jobs', [
            'status' => 'in_transit', 'assigned_driver_id' => $driver, 'job_number' => 'RACE-'.uniqid(), 'payout_amount' => 80,
        ]);

        $results = $this->race('deliver_job', $job, array_fill(0, self::WORKERS, $driver));

        $this->assertSame([], array_filter($results, fn ($r) => str_starts_with($r, 'ERROR')), 'a worker crashed: '.implode(' | ', $results));
        $this->assertSame(1, count(array_filter($results, fn ($r) => str_contains($r, 'WIN'))), 'wins: '.implode(',', $results));
        $this->assertSame(1, DB::table('urban_goodz_driver_earnings')->where('delivery_man_id', $driver)->where('earning_type', 'medical_courier')->count(), 'the driver was paid more than once');
    }

    public function test_concurrent_load_completions_pay_the_driver_exactly_once(): void
    {
        $driver = $this->driver();
        $load = $this->loads[] = $this->insertRow('urban_goodz_load_board_loads', [
            'status' => 'delivered', 'assigned_driver_id' => $driver, 'load_number' => 'RACE-'.uniqid(), 'payout_amount' => 900,
        ]);

        $results = $this->race('complete_load', $load, array_fill(0, self::WORKERS, $driver));

        $this->assertSame([], array_filter($results, fn ($r) => str_starts_with($r, 'ERROR')), 'a worker crashed: '.implode(' | ', $results));
        $this->assertSame(1, count(array_filter($results, fn ($r) => str_contains($r, 'WIN'))), 'wins: '.implode(',', $results));
        $this->assertSame(1, DB::table('urban_goodz_driver_earnings')->where('delivery_man_id', $driver)->where('load_id', $load)->count(), 'the driver was paid more than once for one load');
    }
}
