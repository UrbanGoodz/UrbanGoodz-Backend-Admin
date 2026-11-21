<?php

namespace Tests\Feature;

use App\Models\DeliveryMan;
use App\Services\DriverFleetService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DriverFleetServiceTest extends TestCase
{
    use DatabaseTransactions;

    private function driver(string $tag, ?int $storeId, array $over = []): DeliveryMan
    {
        return DeliveryMan::create(array_merge([
            'f_name' => 'Fleet', 'l_name' => $tag, 'phone' => '+1555'.random_int(1000000, 9999999),
            'email' => "fleet-{$tag}-".uniqid().'@urbangoodz.test', 'password' => bcrypt('x'),
            'auth_token' => 'tok-'.bin2hex(random_bytes(8)), 'status' => 1, 'application_status' => 'approved',
            'active' => 1, 'store_id' => $storeId,
        ], $over));
    }

    private function ping(DeliveryMan $d, float $lat, float $lng): void
    {
        DB::table('delivery_histories')->insert([
            'delivery_man_id' => $d->id, 'latitude' => $lat, 'longitude' => $lng, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_a_vendor_sees_only_their_own_stores_drivers_and_an_admin_sees_everyone(): void
    {
        $mine = $this->driver('mine', 900001);
        $theirs = $this->driver('theirs', 900002);
        $platform = $this->driver('platform', null);

        $vendorView = collect(app(DriverFleetService::class)->fleet(900001)['drivers'])->pluck('id')->all();
        $this->assertSame([$mine->id], $vendorView, 'a vendor must never see another store\'s or the platform\'s drivers');

        $adminIds = collect(app(DriverFleetService::class)->fleet(null)['drivers'])->pluck('id')->all();
        foreach ([$mine, $theirs, $platform] as $d) {
            $this->assertContains($d->id, $adminIds);
        }
    }

    public function test_state_reflects_live_signal_route_and_offline_status(): void
    {
        $live = $this->driver('live', 900003);
        $this->ping($live, 29.7589, -95.3677);
        $quiet = $this->driver('quiet', 900003);
        $off = $this->driver('off', 900003, ['active' => 0]);
        $onRoute = $this->driver('route', 900003);
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('urban_goodz_dedicated_routes')->insert([
            'route_name' => 'Fleet Test Route', 'assigned_driver_id' => $onRoute->id, 'status' => 'in_progress',
            'scheduled_date' => now()->toDateString(), 'total_packages' => 10, 'completed_packages' => 4,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $by = collect(app(DriverFleetService::class)->fleet(900003)['drivers'])->keyBy('id');

        $this->assertSame('available', $by[$live->id]['state']);
        $this->assertEqualsWithDelta(29.7589, $by[$live->id]['lat'], 0.0001);
        $this->assertSame('no_signal', $by[$quiet->id]['state']);
        $this->assertSame('offline', $by[$off->id]['state']);
        $this->assertSame('on_route', $by[$onRoute->id]['state']);
        $this->assertSame(4, $by[$onRoute->id]['route']['completed_packages']);
    }

    public function test_pending_drivers_are_not_shown(): void
    {
        $pending = $this->driver('pending', 900004, ['application_status' => 'pending']);

        $ids = collect(app(DriverFleetService::class)->fleet(900004)['drivers'])->pluck('id')->all();
        $this->assertNotContains($pending->id, $ids);
    }
}
