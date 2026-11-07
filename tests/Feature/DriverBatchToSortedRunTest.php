<?php

namespace Tests\Feature;

use App\Models\DeliveryMan;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InsertsRows;
use Tests\TestCase;

/**
 * The scanned-in batch -> driver run -> driver-chosen finish -> sorted stops flow.
 *
 *  1. A batch that has been scanned in and is ready for routing becomes a run for the driver.
 *  2. The driver says where they want to finish (hub, an address, or open) and the stops are
 *     persisted in an order that ends near that finish.
 *  3. Only drivers a company may use can claim its batch, and only once intake is finished.
 */
class DriverBatchToSortedRunTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRows;

    private const API = '/api/v1/urban-goodz/driver';

    private int $companyA;
    private int $companyB;
    private int $batch;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        config()->set('urban_goodz.distance_matrix.provider', 'haversine');
        config()->set('urban_goodz.distance_matrix.google_maps_key', '');

        $this->companyA = $this->insertRow('urban_goodz_business_clients', ['company_name' => 'A', 'account_type' => 'business', 'status' => 'approved', 'email' => 'a'.uniqid().'@urbangoodz.test']);
        $this->companyB = $this->insertRow('urban_goodz_business_clients', ['company_name' => 'B', 'account_type' => 'business', 'status' => 'approved', 'email' => 'b'.uniqid().'@urbangoodz.test']);
        $this->batch = $this->batchFor($this->companyA, 'READY_FOR_ROUTING');
    }

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    private function batchFor(int $company, string $status): int
    {
        $batch = $this->insertRow('urban_goodz_intake_batches', [
            'business_client_id' => $company, 'batch_name' => 'Scanned stack', 'service_date' => now()->toDateString(), 'status' => $status,
        ]);
        // Packages scattered in a line from the depot out to the east so the right order is obvious.
        foreach ([[29.90, -95.10], [29.78, -95.34], [29.84, -95.22], [29.80, -95.28], [29.88, -95.14], [29.82, -95.25]] as $i => [$lat, $lng]) {
            $this->insertRow('urban_goodz_batch_packages', [
                'intake_batch_id' => $batch, 'business_client_id' => $company, 'tracking_id' => "PKG-{$batch}-{$i}", 'barcode' => "BC-{$batch}-{$i}",
                'recipient_name' => "R{$i}", 'dropoff_address' => "{$i} Main St", 'dropoff_city' => 'Houston', 'dropoff_state' => 'TX', 'dropoff_zip' => '77001',
                'dropoff_lat' => $lat, 'dropoff_lng' => $lng, 'is_active' => 1,
            ]);
        }

        return $batch;
    }

    private function driver(string $tag, array $over = []): DeliveryMan
    {
        return DeliveryMan::create(array_merge([
            'f_name' => $tag, 'l_name' => 'x', 'phone' => '+1555'.random_int(1000000, 9999999),
            'email' => "bd-{$tag}-".uniqid().'@urbangoodz.test', 'password' => bcrypt('x'),
            'auth_token' => 'tok-'.$tag.'-'.bin2hex(random_bytes(10)), 'status' => 1, 'active' => 1, 'application_status' => 'approved',
            'ownership_type' => 'urban_goodz',
        ], $over));
    }

    private function build(DeliveryMan $d, ?int $batch = null)
    {
        return $this->postJson(self::API.'/routes/from-batch?token='.$d->auth_token, [
            'batch_id' => $batch ?? $this->batch, 'pickup_lat' => 29.76, 'pickup_lng' => -95.36, 'pickup_label' => 'Hub',
        ]);
    }

    private function order(int $routeId): array
    {
        return DB::table('urban_goodz_route_packages')->where('dedicated_route_id', $routeId)->orderBy('stop_order')->pluck('tracking_id')->all();
    }

    // --------------------------------------------------------------------- the flow

    public function test_scanned_batch_becomes_a_run_and_the_drivers_finish_choice_sorts_it(): void
    {
        $driver = $this->driver('ug');

        $built = $this->build($driver)->assertCreated();
        $routeId = (int) $built->json('route.route_id');
        $this->assertGreaterThan(0, $routeId);
        $this->assertSame(6, (int) $built->json('routed'));
        $this->assertSame($driver->id, (int) DB::table('urban_goodz_dedicated_routes')->where('id', $routeId)->value('assigned_driver_id'));
        $this->assertSame('not_optimized', DB::table('urban_goodz_dedicated_routes')->where('id', $routeId)->value('optimization_status'));

        // Finish near the hub: the far-east stops should come first and the run should head back toward the hub.
        $this->postJson(self::API."/routes/{$routeId}/finish?token={$driver->auth_token}", ['mode' => 'hub'])->assertOk();
        $toHub = $this->order($routeId);
        $this->assertCount(6, $toHub);

        // Finish far to the east (driver lives out there): the run should end at the far-east stops instead.
        $this->postJson(self::API."/routes/{$routeId}/finish?token={$driver->auth_token}", ['mode' => 'address', 'end_lat' => 29.92, 'end_lng' => -95.08])->assertOk();
        $toEast = $this->order($routeId);

        $this->assertEqualsCanonicalizing($toHub, $toEast, 'a stop was lost or duplicated by re-sorting');
        $this->assertNotSame($toHub, $toEast, 'changing the finish point must change the order');

        // East finish: stops lie along a line out from the hub, so the shortest route to an eastern
        // finish visits them strictly west -> east and the easternmost stop is last.
        $lng = fn (string $t) => (float) DB::table('urban_goodz_route_packages')->where('tracking_id', $t)->value('dropoff_lng');
        $this->assertSame($toEast, collect($toEast)->sortBy($lng)->values()->all(), 'an eastern finish should visit the stops west to east');

        // Hub finish: a round trip, where many orders tie on a line, so judge it against the true optimum.
        $this->assertLessThanOrEqual($this->bestLoop($toHub) * 1.01, $this->loopMiles($toHub), 'the hub run is longer than the shortest round trip');
    }

    private function point(string $trackingId): array
    {
        $row = DB::table('urban_goodz_route_packages')->where('tracking_id', $trackingId)->first();

        return [(float) $row->dropoff_lat, (float) $row->dropoff_lng];
    }

    private function miles(array $a, array $b): float
    {
        $h = sin(deg2rad($b[0] - $a[0]) / 2) ** 2 + cos(deg2rad($a[0])) * cos(deg2rad($b[0])) * sin(deg2rad($b[1] - $a[1]) / 2) ** 2;

        return 2 * 3958.8 * asin(min(1, sqrt($h)));
    }

    private function loopMiles(array $trackingIds): float
    {
        $hub = [29.76, -95.36];
        $pts = array_merge([$hub], array_map(fn ($t) => $this->point($t), $trackingIds), [$hub]);
        $sum = 0.0;
        for ($i = 0; $i < count($pts) - 1; $i++) {
            $sum += $this->miles($pts[$i], $pts[$i + 1]);
        }

        return $sum;
    }

    private function bestLoop(array $trackingIds): float
    {
        $best = INF;
        $perm = function (array $items, array $chosen = []) use (&$perm, &$best) {
            if (! $items) {
                $best = min($best, $this->loopMiles($chosen));

                return;
            }
            foreach ($items as $k => $item) {
                $rest = $items;
                unset($rest[$k]);
                $perm($rest, array_merge($chosen, [$item]));
            }
        };
        $perm($trackingIds);

        return $best;
    }

    // ------------------------------------------------------------- who may claim a batch

    public function test_a_company_driver_can_claim_their_own_companys_batch(): void
    {
        $own = $this->driver('own', ['business_client_id' => $this->companyA, 'ownership_type' => 'business_owned']);
        $this->build($own)->assertCreated();
    }

    public function test_another_companys_driver_and_vendor_owned_drivers_cannot_claim_the_batch(): void
    {
        $other = $this->driver('other', ['business_client_id' => $this->companyB, 'ownership_type' => 'business_owned']);
        $vendor = $this->driver('vendor', ['ownership_type' => 'vendor_owned', 'vendor_id' => 5, 'available_for_marketplace' => 0]);

        foreach ([$other, $vendor] as $driver) {
            $this->build($driver)->assertNotFound();
        }
        $this->assertSame(0, DB::table('urban_goodz_dedicated_routes')->where('intake_batch_id', $this->batch)->count(), 'a route was created for an unauthorised driver');
    }

    public function test_a_batch_still_being_scanned_in_cannot_be_claimed(): void
    {
        $driver = $this->driver('ug');
        foreach (['DRAFT', 'OPEN_FOR_INTAKE', 'VALIDATING', 'CANCELLED'] as $status) {
            $batch = $this->batchFor($this->companyA, $status);
            $this->build($driver, $batch)->assertNotFound();
        }
    }

    public function test_a_batch_cannot_be_claimed_twice(): void
    {
        $this->build($this->driver('first'))->assertCreated();
        $this->build($this->driver('second'))->assertStatus(422);
    }

    public function test_building_a_run_requires_a_logged_in_driver(): void
    {
        $this->postJson(self::API.'/routes/from-batch', ['batch_id' => $this->batch])->assertUnauthorized();
    }

    public function test_a_driver_cannot_set_the_finish_of_someone_elses_run(): void
    {
        $owner = $this->driver('owner');
        $routeId = (int) $this->build($owner)->json('route.route_id');
        $intruder = $this->driver('intruder');

        $this->postJson(self::API."/routes/{$routeId}/finish?token={$intruder->auth_token}", ['mode' => 'hub'])->assertNotFound();
        $this->assertSame('not_optimized', DB::table('urban_goodz_dedicated_routes')->where('id', $routeId)->value('optimization_status'));
    }
}
