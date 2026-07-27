<?php

namespace App\Observers;

use App\Events\UrbanGoodzRealtimeUpdate;
use App\Models\UrbanGoodzDedicatedRoute;

class UrbanGoodzDedicatedRouteObserver
{
    public function created(UrbanGoodzDedicatedRoute $route): void
    {
        $this->broadcast($route);
    }

    public function updated(UrbanGoodzDedicatedRoute $route): void
    {
        if ($route->wasChanged(['status', 'assigned_driver_id'])) {
            $this->broadcast($route);
        }
    }

    private function broadcast(UrbanGoodzDedicatedRoute $route): void
    {
        $status = (string) ($route->status ?: 'pending');

        if ((int) $route->business_client_id > 0) {
            event(
                UrbanGoodzRealtimeUpdate::businessRoute(
                    (int) $route->business_client_id,
                    (int) $route->id,
                    $status
                )
            );
        }

        if ((int) $route->assigned_driver_id > 0) {
            event(
                UrbanGoodzRealtimeUpdate::driverAssignment(
                    (int) $route->assigned_driver_id,
                    'dedicated_route',
                    (int) $route->id,
                    $status
                )
            );
        }

        event(
            UrbanGoodzRealtimeUpdate::adminOperation(
                'dedicated_route',
                (int) $route->id,
                $status
            )
        );
    }
}
