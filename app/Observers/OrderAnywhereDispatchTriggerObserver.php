<?php

namespace App\Observers;

use App\Models\AiDispatch;
use App\Models\OrderAnywhereRequest;
use App\Services\OrderAnywhereOrderConversionService;
use Illuminate\Support\Facades\DB;

/**
 * Converts an approved Order Anywhere request into a real Order and starts
 * nearest-driver dispatch the moment the request links to an Order.
 *
 * This is the business trigger for the delivery flow:
 *   request created                     -> nothing (no Order yet)
 *   request approved / order_id linked  -> Order created + dispatch offer sent
 *   repeated conversion                 -> no-op (idempotent)
 *
 * The dispatch decision is expressed as a pure plan so it can be unit-tested
 * without depending on transaction timing (same pattern as
 * OrderAnywhereCardSafetyObserver).
 */
class OrderAnywhereDispatchTriggerObserver
{
    public function __construct(
        private OrderAnywhereOrderConversionService $conversionService,
    ) {}

    public function created(OrderAnywhereRequest $request): void
    {
        $this->runWhenReady($request, static::planForCreated($request));
    }

    public function updated(OrderAnywhereRequest $request): void
    {
        $this->runWhenReady($request, static::planForUpdated($request));
    }

    private function runWhenReady(OrderAnywhereRequest $request, array $plan): void
    {
        if (! $plan['dispatch'] && ! $plan['convert']) {
            return;
        }

        $requestId = $request->id;
        DB::afterCommit(function () use ($requestId, $plan) {
            $request = OrderAnywhereRequest::find($requestId);
            if (! $request) {
                return;
            }

            if ($request->assigned_delivery_man_id) {
                return;
            }

            $service = app(OrderAnywhereOrderConversionService::class);

            if ($plan['convert']) {
                $service->convertToOrder($request);
                $request->refresh();
            }

            if ($plan['dispatch'] || $request->order_id) {
                $order = $service->convertToOrder($request);

                if ($order) {
                    $service->autoDispatchNearestDriver($order, $request->id);
                }
            }
        });
    }

    /**
     * A freshly created request is never dispatched unless an Order was linked
     * at creation time (i.e. it was already converted).
     *
     * @return array{dispatch: bool, convert: bool}
     */
    public static function planForCreated(OrderAnywhereRequest $request): array
    {
        return [
            'dispatch' => $request->order_id !== null,
            'convert' => false,
        ];
    }

    /**
     * @return array{dispatch: bool, convert: bool}
     */
    public static function planForUpdated(OrderAnywhereRequest $request): array
    {
        $becameApproved = $request->isDirty('status') && $request->status === 'approved';
        $orderJustLinked = $request->isDirty('order_id') && $request->order_id !== null;

        return [
            'dispatch' => $orderJustLinked,
            'convert' => $becameApproved,
        ];
    }
}
