<?php

namespace App\Services;

use App\Models\OrderAnywhereRequest;
use App\Models\UrbanGoodzOrderAnywhereCardRequest;
use InvalidArgumentException;

/**
 * The one place a driver's action moves an Order Anywhere request along its state machine, so the
 * driver endpoints and the unified active-jobs endpoints cannot disagree (or skip a step).
 */
class OrderAnywhereDriverFlow
{
    /**
     * What each driver step means for each fulfilment path -- one legal step at a time.
     *
     * external_merchant: approved -> shopper_assigned -> shopper_accepted -> shopping
     *                    -> purchased -> picked_up -> out_for_delivery -> delivered -> completed
     * participating_vendor: ready_for_pickup -> driver_assigned -> picked_up
     *                    -> out_for_delivery -> delivered -> completed
     */
    public const STEPS = [
        'external_merchant' => [
            'accepted'  => ['approved' => ['shopper_assigned', 'shopper_accepted'], 'shopper_assigned' => ['shopper_accepted']],
            'shopping'  => ['shopper_accepted' => ['shopping']],
            'purchased' => ['shopping' => ['purchased']],
            'picked_up' => ['purchased' => ['picked_up']],
            'en_route'  => ['picked_up' => ['out_for_delivery']],
            'delivered' => ['out_for_delivery' => ['delivered', 'completed']],
        ],
        'participating_vendor' => [
            'accepted'  => ['ready_for_pickup' => ['driver_assigned']],
            'picked_up' => ['driver_assigned' => ['picked_up']],
            'en_route'  => ['picked_up' => ['out_for_delivery']],
            'delivered' => ['out_for_delivery' => ['delivered', 'completed']],
        ],
    ];

    /** States from which a driver may hand the job back before any goods are bought. */
    public const RELEASABLE = [
        'external_merchant' => ['approved' => 'approved', 'shopper_assigned' => 'approved', 'shopper_accepted' => 'approved'],
        'participating_vendor' => ['driver_assigned' => 'ready_for_pickup'],
    ];

    /** @throws InvalidArgumentException when the move is not legal from the current state */
    public function advance(OrderAnywhereRequest $model, string $driverStatus): OrderAnywhereRequest
    {
        $path = self::STEPS[$model->fulfillment_type ?: 'external_merchant'] ?? [];
        $steps = $path[$driverStatus][$model->status] ?? null;

        if ($steps === null) {
            throw new InvalidArgumentException("Cannot move to '{$driverStatus}' from '{$model->status}'.");
        }

        // The driver pays with the temporary purchase card the platform issued. When a card exists
        // for this order, "purchased" needs a recorded card purchase; otherwise a driver could claim
        // the purchase without spending the funds.
        if ($driverStatus === 'purchased' && $model->card_issued) {
            $spent = UrbanGoodzOrderAnywhereCardRequest::where('id', $model->card_request_id)->where('captured_amount', '>', 0)->exists();
            if (! $spent) {
                throw new InvalidArgumentException('Complete the purchase with the issued card before marking it purchased.');
            }
        }

        $model->driver_task_status = $driverStatus;
        foreach ($steps as $next) {
            $model->transitionTo($next);
        }

        return $model->fresh();
    }

    /** Hand the job back before buying anything: it returns to automatic dispatch, not the review queue. */
    public function release(OrderAnywhereRequest $model): OrderAnywhereRequest
    {
        $backTo = (self::RELEASABLE[$model->fulfillment_type ?: 'external_merchant'] ?? [])[$model->status] ?? null;

        if ($backTo === null) {
            throw new InvalidArgumentException("This job is '{$model->status}' and can no longer be handed back. Report an issue instead.");
        }

        $model->forceFill([
            'status' => $backTo,
            'assigned_delivery_man_id' => null,
            'driver_task_status' => 'cancelled',
        ])->save();

        return $model->fresh();
    }
}
