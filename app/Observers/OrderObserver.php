<?php

namespace App\Observers;

use App\Events\UrbanGoodzRealtimeUpdate;
use App\Models\Order;
use App\Models\OrderReference;
use App\Models\Store;

class OrderObserver
{
    /**
     * Handle the Order "created" event.
     */
    public function created(Order $order): void
    {
        $OrderReference = new OrderReference();
        $OrderReference->order_id = $order->id;
        $OrderReference->save();

        $this->broadcastOrderState($order);
    }

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        if ($order->wasChanged(['order_status', 'payment_status', 'delivery_man_id'])) {
            $this->broadcastOrderState($order);
        }
    }

    /**
     * Handle the Order "deleted" event.
     */
    public function deleted(Order $order): void
    {
        //
    }

    /**
     * Handle the Order "restored" event.
     */
    public function restored(Order $order): void
    {
        //
    }

    /**
     * Handle the Order "force deleted" event.
     */
    public function forceDeleted(Order $order): void
    {
        //
    }

    private function broadcastOrderState(Order $order): void
    {
        $status = (string) ($order->order_status ?: 'pending');

        if ((int) $order->user_id > 0 && ! $order->is_guest) {
            event(
                UrbanGoodzRealtimeUpdate::shopperOrder(
                    (int) $order->user_id,
                    (int) $order->id,
                    $status
                )
            );
        }

        if ((int) $order->store_id > 0) {
            $vendorId = Store::withoutGlobalScopes()
                ->whereKey($order->store_id)
                ->value('vendor_id');

            if ((int) $vendorId > 0) {
                event(
                    UrbanGoodzRealtimeUpdate::vendorOrder(
                        (int) $vendorId,
                        (int) $order->id,
                        $status
                    )
                );
            }
        }

        if ((int) $order->delivery_man_id > 0) {
            event(
                UrbanGoodzRealtimeUpdate::driverAssignment(
                    (int) $order->delivery_man_id,
                    'order',
                    (int) $order->id,
                    $status
                )
            );
        }

        if ($order->wasChanged('payment_status') && (int) $order->user_id > 0) {
            event(
                UrbanGoodzRealtimeUpdate::paymentStatus(
                    'shopper',
                    (int) $order->user_id,
                    (int) $order->id,
                    (string) $order->payment_status
                )
            );
        }

        event(
            UrbanGoodzRealtimeUpdate::adminOperation(
                'order',
                (int) $order->id,
                $status
            )
        );
    }
}
