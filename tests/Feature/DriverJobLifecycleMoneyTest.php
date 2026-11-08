<?php

namespace Tests\Feature;

use App\Models\DeliveryMan;
use App\Models\OrderAnywhereRequest;
use App\Models\UrbanGoodzBusinessClient;
use App\Models\UrbanGoodzDedicatedRoute;
use App\Models\UrbanGoodzDriverEarning;
use App\Models\UrbanGoodzFinancialRule;
use App\Models\UrbanGoodzFinancialSettlementSnapshot;
use App\Models\UrbanGoodzRoutePackage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A driver must never be able to be paid twice for a route, finish a route they have not
 * delivered, or push an Order Anywhere order to `completed` without doing the work.
 */
class DriverJobLifecycleMoneyTest extends TestCase
{
    use DatabaseTransactions;

    private DeliveryMan $driver;
    private UrbanGoodzDedicatedRoute $route;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        UrbanGoodzFinancialRule::query()->delete();

        $sfx = str_replace('.', '', uniqid('', true));
        $business = UrbanGoodzBusinessClient::create(['company_name' => "Money {$sfx}", 'email' => "money-{$sfx}@urbangoodz.test", 'status' => 'approved']);
        $this->driver = DeliveryMan::create([
            'f_name' => 'Job', 'l_name' => 'Driver', 'phone' => substr("72{$sfx}", 0, 20), 'email' => "job-{$sfx}@urbangoodz.test",
            'password' => bcrypt('x'), 'auth_token' => hash('sha256', $sfx), 'application_status' => 'approved', 'status' => 1, 'active' => 1,
        ]);
        $this->route = UrbanGoodzDedicatedRoute::create([
            'business_client_id' => $business->id, 'route_name' => "Money Route {$sfx}", 'route_type' => 'package_routes', 'source_module' => 'package_routes',
            'pickup_location' => 'Hub', 'pickup_lat' => 29.75, 'pickup_lng' => -95.36, 'end_location' => 'Hub', 'end_lat' => 29.75, 'end_lng' => -95.36,
            'scheduled_date' => now()->toDateString(), 'status' => 'active', 'assigned_driver_id' => $this->driver->id, 'total_packages' => 2,
            'max_packages_per_batch' => 10, 'capacity_packages' => 10, 'capacity_weight_lbs' => 100,
            'route_offer_amount' => 25.00, 'driver_pay_per_package' => 5.00, 'route_completion_bonus' => 3.00,
        ]);
        foreach (['A', 'B'] as $i => $tag) {
            UrbanGoodzRoutePackage::create([
                'dedicated_route_id' => $this->route->id, 'business_client_id' => $business->id, 'tracking_id' => "MON-{$tag}-{$sfx}", 'barcode' => "B-{$tag}-{$sfx}",
                'qr_code' => "Q-{$tag}-{$sfx}", 'dropoff_name' => 'R', 'dropoff_address' => "{$i}00 Main", 'dropoff_lat' => 29.76 + $i / 100, 'dropoff_lng' => -95.37,
                'delivery_window_start' => now()->startOfDay()->addHours(8), 'delivery_window_end' => now()->startOfDay()->addHours(17),
                'priority' => 'normal', 'weight' => 5, 'status' => 'pending', 'requires_photo' => false,
            ]);
        }
    }

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    private function call_(string $path)
    {
        return $this->postJson("/api/v1/urban-goodz/driver/{$path}?token=".$this->driver->auth_token);
    }

    private function money(): array
    {
        return [
            'earnings' => UrbanGoodzDriverEarning::where('delivery_man_id', $this->driver->id)->count(),
            'cents' => (int) round(UrbanGoodzDriverEarning::where('delivery_man_id', $this->driver->id)->sum('amount') * 100),
            'snapshots' => UrbanGoodzFinancialSettlementSnapshot::where('source_type', 'dedicated_route')->where('source_id', (string) $this->route->id)->count(),
        ];
    }

    public function test_a_route_cannot_be_reopened_and_completed_again_for_a_second_payout(): void
    {
        $this->route->packages()->update(['status' => 'delivered']);

        $this->call_("routes/{$this->route->id}/completed")->assertOk();
        $afterFirst = $this->money();
        $this->assertGreaterThan(0, $afterFirst['cents']);

        // The reopen vectors: both "start" endpoints used to accept a completed route.
        $this->call_("routes/{$this->route->id}/started")->assertStatus(409);
        $this->call_("active-jobs/{$this->route->id}/start")->assertStatus(409);
        $this->assertSame('completed', $this->route->fresh()->status);

        $this->call_("routes/{$this->route->id}/completed")->assertStatus(400);
        $this->call_("active-jobs/{$this->route->id}/complete")->assertStatus(409);

        $this->assertSame($afterFirst, $this->money(), 'no second payout, bonus or settlement may appear');
    }

    public function test_the_active_jobs_endpoint_cannot_finish_a_route_with_undelivered_packages(): void
    {
        $this->call_("active-jobs/{$this->route->id}/complete")->assertStatus(409);

        $this->assertSame('active', $this->route->fresh()->status);
        $this->assertNull($this->route->fresh()->route_completed_at);
        $this->assertSame(0, $this->money()['earnings']);
    }

    public function test_the_active_jobs_endpoint_settles_a_fully_delivered_route_exactly_like_the_route_endpoint(): void
    {
        $this->route->packages()->update(['status' => 'delivered']);

        $this->call_("active-jobs/{$this->route->id}/complete")->assertOk();

        $this->assertSame('completed', $this->route->fresh()->status);
        $this->assertSame(1, $this->money()['snapshots'], 'completion must go through settlement');
        $this->assertGreaterThan(0, $this->money()['cents']);
    }

    private function orderAnywhere(string $status, array $over = []): OrderAnywhereRequest
    {
        return OrderAnywhereRequest::create(array_merge([
            'request_number' => OrderAnywhereRequest::nextRequestNumber(), 'status' => $status, 'payment_status' => 'captured', 'quote_amount' => 60,
            'captured_amount' => 60, 'fulfillment_type' => 'external_merchant', 'assigned_delivery_man_id' => $this->driver->id,
        ], $over));
    }

    public function test_a_driver_cannot_force_an_order_anywhere_request_to_completed(): void
    {
        foreach (['approved', 'shopping', 'purchased', 'picked_up'] as $state) {
            $r = $this->orderAnywhere($state);
            $this->call_("active-jobs/{$r->id}/complete")->assertStatus(422);
            $this->assertSame($state, $r->fresh()->status, "'{$state}' must not jump to completed");
        }

        $delivering = $this->orderAnywhere('out_for_delivery');
        $this->call_("active-jobs/{$delivering->id}/complete")->assertOk();
        $this->assertSame('completed', $delivering->fresh()->status);
    }

    public function test_a_driver_cannot_walk_away_from_a_job_after_buying_the_goods(): void
    {
        $bought = $this->orderAnywhere('purchased');
        $this->call_("active-jobs/{$bought->id}/cancel")->assertStatus(422);
        $this->assertSame('purchased', $bought->fresh()->status);
        $this->assertSame($this->driver->id, (int) $bought->fresh()->assigned_delivery_man_id);
    }

    public function test_releasing_a_job_before_buying_puts_it_back_for_automatic_dispatch_not_in_the_review_queue(): void
    {
        $r = $this->orderAnywhere('approved');
        $this->call_("active-jobs/{$r->id}/cancel")->assertOk();

        $fresh = $r->fresh();
        $this->assertSame('approved', $fresh->status, 'a paid order must not fall back to pending_review');
        $this->assertNull($fresh->assigned_delivery_man_id);
    }
}
