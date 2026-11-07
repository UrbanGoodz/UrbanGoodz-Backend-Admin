<?php

namespace App\Console\Commands;

use App\Models\AiDispatch;
use App\Models\OrderAnywhereRequest;
use App\Services\OrderAnywhereDispatchSettings;
use App\Services\OrderAnywhereOpsNotifier;
use App\Services\OrderAnywhereOrderConversionService;
use App\Services\UrbanGoodzPaymentService;
use App\Services\UrbanGoodzAiDispatchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Keeps Order Anywhere dispatch moving without a person. Every minute:
 *  1. lapsed offers are expired;
 *  2. every approved external-merchant request that has no driver and no live offer is offered
 *     to the next nearest driver (drivers who already declined/lapsed are skipped);
 *  3. a request nobody has accepted after ORDER_ANYWHERE_ESCALATE_MINUTES is flagged for an admin.
 * A request an admin assigned a specific driver to is left alone -- that is the override.
 */
class DispatchPendingOrderAnywhere extends Command
{
    protected $signature = 'order-anywhere:dispatch-pending';
    protected $description = 'Expire lapsed offers, re-offer unassigned Order Anywhere jobs, escalate stuck ones';

    public function handle(OrderAnywhereOrderConversionService $conversion, UrbanGoodzAiDispatchService $dispatches, OrderAnywhereOpsNotifier $ops, UrbanGoodzPaymentService $payments): int
    {
        if (! OrderAnywhereDispatchSettings::get('auto_dispatch')) {
            return self::SUCCESS;
        }

        $dispatches->expireStaleOffers(30);
        $escalateAfter = OrderAnywhereDispatchSettings::get('escalate_after_minutes');
        $cancelAfter = OrderAnywhereDispatchSettings::get('cancel_after_minutes');
        $offered = $flagged = $cancelled = 0;

        OrderAnywhereRequest::query()
            ->where('status', 'approved')
            ->where('fulfillment_type', OrderAnywhereRequest::FULFILLMENT_EXTERNAL_MERCHANT)
            ->whereNull('assigned_delivery_man_id')
            ->orderBy('id')
            ->limit(200)
            ->get()
            ->each(function (OrderAnywhereRequest $request) use ($conversion, $ops, $payments, $escalateAfter, $cancelAfter, &$offered, &$flagged, &$cancelled) {
                try {
                    if ($request->order_id) {
                        $live = AiDispatch::where('order_id', $request->order_id)
                            ->whereIn('status', [AiDispatch::STATUS_PENDING_DRIVER, AiDispatch::STATUS_SENT, AiDispatch::STATUS_VIEWED, AiDispatch::STATUS_ACCEPTED])
                            ->exists();
                        if ($live) {
                            return;
                        }
                    }

                    if ($conversion->handleApproved($request->fresh())) {
                        $offered++;
                    }

                    // Measured from when we first started waiting, not updated_at, which every
                    // retry would reset.
                    $request->refresh();
                    $meta = $request->metadata ?? [];
                    if (empty($meta['dispatch_waiting_since'])) {
                        $meta['dispatch_waiting_since'] = now()->toIso8601String();
                        $request->update(['metadata' => $meta]);
                    }
                    $waitedMinutes = (int) \Illuminate\Support\Carbon::parse($meta['dispatch_waiting_since'])->diffInMinutes(now());

                    if ($waitedMinutes >= $escalateAfter && empty($meta['needs_manual_dispatch_at'])) {
                        $request->update(['metadata' => [...$meta, 'needs_manual_dispatch_at' => now()->toIso8601String()]]);
                        Log::warning('Order Anywhere request needs a manual driver', ['request_id' => $request->id, 'waited_minutes' => $waitedMinutes]);
                        $ops->admins(
                            'Order Anywhere: no driver available',
                            "Request {$request->request_number} has had no driver for {$waitedMinutes} min. Assign one manually or it will be cancelled and the customer's hold released"
                                . ($cancelAfter > 0 ? " in about " . max(0, $cancelAfter - $waitedMinutes) . ' min.' : '.'),
                            ['request_id' => $request->id],
                            'order_anywhere_no_driver'
                        );
                        $ops->customer($request->customer_id, 'Finding your driver', "We're still looking for a driver near the pickup. You haven't been charged, and we'll keep trying.", ['request_id' => $request->id]);
                        $flagged++;
                    }

                    // Nobody took it: give up cleanly. Nothing was captured (money moves when a
                    // driver accepts), so cancelling voids the authorization -- no charge, no refund.
                    if ($cancelAfter > 0 && $waitedMinutes >= $cancelAfter) {
                        $payments->cancelAndReturnFunds($request->fresh(), 'No driver available');
                        $ops->customer($request->customer_id, 'Order cancelled', 'We could not find a driver for your order, so it was cancelled. Your card has not been charged and any hold is released.', ['request_id' => $request->id]);
                        $ops->admins('Order Anywhere: cancelled, no driver', "Request {$request->request_number} was cancelled after {$waitedMinutes} min with no driver. The customer's payment was released.", ['request_id' => $request->id], 'order_anywhere_cancelled_no_driver');
                        $cancelled++;
                    }
                } catch (\Throwable $e) {
                    Log::error('Order Anywhere dispatch retry failed', ['request_id' => $request->id, 'error' => $e->getMessage()]);
                }
            });

        $this->info("offered={$offered} flagged={$flagged} cancelled={$cancelled}");

        return self::SUCCESS;
    }
}
