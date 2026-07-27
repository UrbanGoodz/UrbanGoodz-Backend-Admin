<?php

namespace App\Observers;

use App\Events\UrbanGoodzRealtimeUpdate;
use App\Models\UrbanGoodzLoadBoardLoad;

class UrbanGoodzLoadBoardLoadObserver
{
    public function created(UrbanGoodzLoadBoardLoad $load): void
    {
        $this->broadcast($load);
    }

    public function updated(UrbanGoodzLoadBoardLoad $load): void
    {
        if ($load->wasChanged(['status', 'dispatch_status', 'dispatcher_id', 'assigned_driver_id'])) {
            $this->broadcast($load);
        }
    }

    private function broadcast(UrbanGoodzLoadBoardLoad $load): void
    {
        $status = (string) ($load->dispatch_status ?: $load->status ?: 'pending');

        if ((int) $load->dispatcher_id > 0) {
            event(
                UrbanGoodzRealtimeUpdate::dispatcherLoad(
                    (int) $load->dispatcher_id,
                    (int) $load->id,
                    $status
                )
            );
        }

        if ((int) $load->assigned_driver_id > 0) {
            event(
                UrbanGoodzRealtimeUpdate::driverAssignment(
                    (int) $load->assigned_driver_id,
                    'load',
                    (int) $load->id,
                    $status
                )
            );
        }

        event(
            UrbanGoodzRealtimeUpdate::adminOperation(
                'load',
                (int) $load->id,
                $status
            )
        );
    }
}
