<?php

namespace Tests\Feature;

use App\Models\DeliveryMan;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DriverDeliveryProofTest extends TestCase
{
    use DatabaseTransactions;

    private const API = '/api/v1/urban-goodz/driver';

    private DeliveryMan $driver;
    private int $routeId;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Storage::fake('public');
        $this->driver = DeliveryMan::create([
            'f_name' => 'Drv', 'l_name' => 'Proof', 'phone' => '+1555'.random_int(1000000, 9999999),
            'email' => 'proof-'.uniqid().'@urbangoodz.test', 'password' => bcrypt('password'),
            'auth_token' => 'tok-'.bin2hex(random_bytes(12)), 'status' => 1, 'application_status' => 'approved', 'active' => 1,
        ]);
        $this->routeId = $this->row('urban_goodz_dedicated_routes', [
            'route_name' => 'Proof Route', 'assigned_driver_id' => $this->driver->id, 'status' => 'in_progress',
            'scheduled_date' => now()->toDateString(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    private function row(string $table, array $values): int
    {
        $row = [];
        foreach (Schema::getColumns($table) as $col) {
            $name = $col['name'];
            if (array_key_exists($name, $values)) {
                $row[$name] = $values[$name];
                continue;
            }
            if ($col['nullable'] || $col['default'] !== null || $col['auto_increment']) {
                continue;
            }
            $type = strtolower($col['type_name']);
            $row[$name] = match (true) {
                str_contains($type, 'int'), in_array($type, ['decimal', 'float', 'double'], true) => 0,
                $type === 'date' => now()->toDateString(),
                in_array($type, ['datetime', 'timestamp'], true) => now()->toDateTimeString(),
                $type === 'json' => '[]',
                default => 'x',
            };
        }
        if (Schema::hasColumn($table, 'created_at') && ! isset($row['created_at'])) {
            $row['created_at'] = $row['updated_at'] = now();
        }

        return (int) DB::table($table)->insertGetId($row);
    }

    private function package(bool $requiresPhoto): string
    {
        $tracking = 'PF'.random_int(100000, 999999);
        $this->row('urban_goodz_route_packages', [
            'dedicated_route_id' => $this->routeId, 'tracking_id' => $tracking, 'status' => 'picked_up',
            'requires_photo' => $requiresPhoto ? 1 : 0, 'requires_signature' => 0, 'age_restricted' => 0,
        ]);

        return $tracking;
    }

    private function dropoff(string $tracking, array $body = [], array $files = [])
    {
        return $this->call('POST', self::API."/routes/{$this->routeId}/scan-dropoff?token=".$this->driver->auth_token,
            ['tracking_id' => $tracking] + $body, [], $files, ['HTTP_ACCEPT' => 'application/json']);
    }

    public function test_a_required_photo_cannot_be_skipped_or_faked(): void
    {
        $t = $this->package(true);
        $this->dropoff($t)->assertStatus(422)->assertJsonPath('requires', 'photo');
        $this->dropoff($t, ['photo' => '/images/mock_photo.png'])->assertStatus(422)->assertJsonPath('requires', 'photo');
        $this->assertSame('picked_up', DB::table('urban_goodz_route_packages')->where('tracking_id', $t)->value('status'));
    }

    public function test_an_uploaded_photo_completes_the_delivery_and_is_stored(): void
    {
        $t = $this->package(true);
        $this->dropoff($t, [], ['photo_file' => UploadedFile::fake()->image('drop.jpg', 600, 400)])->assertSuccessful();

        $row = DB::table('urban_goodz_route_packages')->where('tracking_id', $t)->first();
        $this->assertSame('delivered', $row->status);
        $this->assertStringStartsWith('storage/delivery-proof/photos/', $row->proof_photo);
        Storage::disk('public')->assertExists(substr($row->proof_photo, strlen('storage/')));
    }

    public function test_packages_that_do_not_require_proof_still_complete_and_placeholders_are_dropped(): void
    {
        $t = $this->package(false);
        $this->dropoff($t, ['photo' => '/images/mock_photo.png', 'signature' => 'MOCK_SIGNATURE'])->assertSuccessful();

        $row = DB::table('urban_goodz_route_packages')->where('tracking_id', $t)->first();
        $this->assertSame('delivered', $row->status);
        $this->assertEmpty($row->proof_photo);
        $this->assertEmpty($row->recipient_signature);
    }

    public function test_the_route_map_lists_numbered_stops_with_navigate_links_for_the_assigned_driver_only(): void
    {
        $pkgA = $this->row('urban_goodz_route_packages', [
            'dedicated_route_id' => $this->routeId, 'tracking_id' => 'MA1', 'status' => 'picked_up',
            'dropoff_lat' => 29.7589, 'dropoff_lng' => -95.3677, 'dropoff_address' => '901 Bagby St',
        ]);
        $pkgB = $this->row('urban_goodz_route_packages', [
            'dedicated_route_id' => $this->routeId, 'tracking_id' => 'MA2', 'status' => 'delivered',
            'dropoff_lat' => 29.76, 'dropoff_lng' => -95.37, 'dropoff_address' => '2 Main St',
        ]);
        $this->row('urban_goodz_route_optimization_stops', ['dedicated_route_id' => $this->routeId, 'package_id' => $pkgA, 'stop_order' => 1]);
        $this->row('urban_goodz_route_optimization_stops', ['dedicated_route_id' => $this->routeId, 'package_id' => $pkgB, 'stop_order' => 2]);

        $res = $this->getJson(self::API."/routes/{$this->routeId}/map?token=".$this->driver->auth_token)->assertOk();

        $res->assertJsonPath('total_stops', 2)->assertJsonPath('pending_stops', 1);
        $this->assertStringContainsString('destination=29.7589,-95.3677', $res->json('stops.0.navigate_url'));
        $this->assertSame([1], $res->json('next_trip.stop_orders'), 'only the pending stop is in the next trip');

        $other = DeliveryMan::create([
            'f_name' => 'Other', 'l_name' => 'Drv', 'phone' => '+1555'.random_int(1000000, 9999999),
            'email' => 'other-'.uniqid().'@urbangoodz.test', 'password' => bcrypt('password'),
            'auth_token' => 'tok-'.bin2hex(random_bytes(12)), 'status' => 1, 'application_status' => 'approved', 'active' => 1,
        ]);
        $this->getJson(self::API."/routes/{$this->routeId}/map?token=".$other->auth_token)->assertStatus(404);
    }
}
