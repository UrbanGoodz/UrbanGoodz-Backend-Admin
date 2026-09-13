<?php

namespace Tests\Feature;

use App\Events\UrbanGoodzRealtimeUpdate;
use App\Models\UrbanGoodzBusinessClient;
use App\Models\UrbanGoodzDedicatedRoute;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

/**
 * A broken realtime broadcast must not break the route change behind it.
 *
 * The observer broadcasts on create and on status/driver changes. Whether that
 * broadcast runs inside the request depends on configuration - with a sync
 * queue it does - and when it runs inline, a transport failure throws *after*
 * the route write has already committed. The database then records a completed
 * change while the caller is handed a 500.
 */
class RouteBroadcastFailureIsContainedTest extends TestCase
{
    use DatabaseTransactions;

    private function makeRoute(): UrbanGoodzDedicatedRoute
    {
        $business = UrbanGoodzBusinessClient::create([
            'company_name' => 'Broadcast Guard Co',
            'email' => 'broadcast-guard@urbangoodz.test',
            'status' => 'approved',
        ]);

        return UrbanGoodzDedicatedRoute::create([
            'business_client_id' => $business->id,
            'route_name' => 'Broadcast Guard Route',
            'route_label' => 'BG',
            'total_packages' => 0,
            'scheduled_date' => now()->toDateString(),
            'route_type' => 'bulk_delivery',
            'status' => 'active',
            'pickup_lat' => 29.7604000,
            'pickup_lng' => -95.3698000,
            'pickup_location' => 'Houston Hub',
        ]);
    }

    /** Make every realtime broadcast blow up, the way a dead transport would. */
    private function breakBroadcasting(): void
    {
        Event::listen(UrbanGoodzRealtimeUpdate::class, function (): void {
            throw new RuntimeException('broadcast transport is unreachable');
        });
    }

    public function test_a_status_change_survives_a_broadcast_that_throws(): void
    {
        $route = $this->makeRoute();

        $this->breakBroadcasting();

        // The observer fires on a status change. Before the guard this threw.
        $route->update(['status' => 'in_progress']);

        self::assertSame(
            'in_progress',
            UrbanGoodzDedicatedRoute::find($route->id)->status,
            'The route status change did not persist.'
        );
    }

    public function test_creating_a_route_survives_a_broadcast_that_throws(): void
    {
        $this->breakBroadcasting();

        $route = $this->makeRoute();

        self::assertNotNull(
            UrbanGoodzDedicatedRoute::find($route->id),
            'The route was not created when its broadcast failed.'
        );
    }

    public function test_a_driver_assignment_survives_a_broadcast_that_throws(): void
    {
        $route = $this->makeRoute();

        $this->breakBroadcasting();

        $route->update(['assigned_driver_id' => 424242]);

        self::assertSame(
            424242,
            (int) UrbanGoodzDedicatedRoute::find($route->id)->assigned_driver_id,
            'The driver assignment did not persist.'
        );
    }
}
