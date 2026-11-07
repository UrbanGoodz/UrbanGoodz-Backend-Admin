<?php

namespace Tests\Feature;

use App\Models\DeliveryMan;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InsertsRows;
use Tests\TestCase;

/**
 * Chain of custody on dedicated medical routes (package scans).
 *
 * The custody log must record what the driver actually attested. It used to write
 * seal_intact = true on every pickup and drop-off whether or not anyone checked a seal.
 */
class MedicalRouteCustodyTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRows;

    private const API = '/api/v1/urban-goodz/driver';

    private DeliveryMan $driver;
    private int $route;
    private int $package;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $this->driver = DeliveryMan::create([
            'f_name' => 'Med', 'l_name' => 'Driver', 'phone' => '+1555'.random_int(1000000, 9999999),
            'email' => 'medroute-'.uniqid().'@urbangoodz.test', 'password' => bcrypt('x'), 'auth_token' => 'tok-'.bin2hex(random_bytes(10)),
            'status' => 1, 'active' => 1, 'application_status' => 'approved', 'has_medical_courier_training' => 1,
        ]);
        $this->route = $this->insertRow('urban_goodz_dedicated_routes', [
            'route_name' => 'Lab to clinic', 'route_type' => 'medical_courier', 'assigned_driver_id' => $this->driver->id, 'status' => 'active',
            'scheduled_date' => now()->toDateString(), 'total_packages' => 1,
        ]);
        $this->package = $this->insertRow('urban_goodz_route_packages', [
            'dedicated_route_id' => $this->route, 'tracking_id' => 'MED-1', 'barcode' => 'MED-BAR-1', 'status' => 'pending',
            'requires_custody' => 1, 'requires_signature' => 1, 'dropoff_name' => 'Clinic', 'dropoff_address' => '9 Clinic Rd',
        ]);
    }

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    private function scan(array $event)
    {
        return $this->postJson(self::API."/routes/{$this->route}/package-events?token={$this->driver->auth_token}", $event + [
            'identifier' => 'MED-BAR-1', 'identifier_type' => 'barcode', 'idempotency_key' => 'k-'.uniqid(),
        ]);
    }

    private function custody(string $event)
    {
        return DB::table('urban_goodz_medical_custody_logs')->where('package_id', $this->package)->where('custody_event', $event)->first();
    }

    public function test_pickup_and_dropoff_write_custody_entries_with_the_drivers_attestation(): void
    {
        $this->scan(['action' => 'pickup', 'seal_intact' => true, 'temperature' => 4.5])->assertSuccessful();
        $pickup = $this->custody('pickup');
        $this->assertNotNull($pickup, 'no custody entry for the pickup');
        $this->assertSame(1, (int) $pickup->seal_intact);
        $this->assertEquals(4.5, (float) $pickup->temperature);
        $this->assertSame($this->driver->id, (int) $pickup->to_user_id);

        $this->scan(['action' => 'delivery', 'signature' => 'data:image/png;base64,AAA', 'seal_intact' => true, 'temperature' => 5.1])->assertSuccessful();
        $drop = $this->custody('dropoff');
        $this->assertNotNull($drop, 'no custody entry for the drop-off');
        $this->assertSame('data:image/png;base64,AAA', $drop->signature);
        $this->assertEquals(5.1, (float) $drop->temperature);
    }

    public function test_a_broken_seal_is_recorded_as_broken(): void
    {
        $this->scan(['action' => 'pickup', 'seal_intact' => false])->assertSuccessful();

        $this->assertSame(0, (int) $this->custody('pickup')->seal_intact, 'a driver-reported broken seal must not be recorded as intact');
    }

    public function test_an_unattested_seal_is_recorded_as_unknown_not_as_intact(): void
    {
        $this->scan(['action' => 'pickup'])->assertSuccessful();

        $this->assertNull($this->custody('pickup')->seal_intact, 'a seal nobody checked was recorded as intact');
        $this->assertNull($this->custody('pickup')->temperature);
    }

    public function test_a_replayed_event_does_not_duplicate_the_custody_entry(): void
    {
        $key = 'replay-'.uniqid();
        $this->scan(['action' => 'pickup', 'idempotency_key' => $key, 'seal_intact' => true])->assertSuccessful();
        $this->scan(['action' => 'pickup', 'idempotency_key' => $key, 'seal_intact' => true])->assertSuccessful();

        $this->assertSame(1, DB::table('urban_goodz_medical_custody_logs')->where('package_id', $this->package)->where('custody_event', 'pickup')->count());
    }

    public function test_invalid_attestations_are_rejected(): void
    {
        $this->scan(['action' => 'pickup', 'temperature' => 500])->assertStatus(422);
        $this->scan(['action' => 'pickup', 'seal_intact' => 'maybe'])->assertStatus(422);
        $this->assertNull($this->custody('pickup'));
    }

    public function test_a_package_that_needs_a_signature_cannot_be_delivered_without_one(): void
    {
        $this->scan(['action' => 'pickup'])->assertSuccessful();

        $this->assertContains($this->scan(['action' => 'delivery'])->getStatusCode(), [409, 422]);
        $this->assertNull($this->custody('dropoff'));
        $this->assertNotSame('delivered', DB::table('urban_goodz_route_packages')->where('id', $this->package)->value('status'));
    }
}
