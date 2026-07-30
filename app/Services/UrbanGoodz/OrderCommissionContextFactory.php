<?php

namespace App\Services\UrbanGoodz;

use App\Models\Order;

/**
 * Builds the commission context for a marketplace order.
 *
 * Kept separate from OrderLogic so the same mapping can be exercised directly
 * in tests and reused by the settlement simulator.
 */
class OrderCommissionContextFactory
{
    public static function forOrder(Order $order, int $qualifyingAmountCents = 0): CommissionContext
    {
        return new CommissionContext(
            transactionType: CommissionContext::TYPE_MARKETPLACE_ORDER,
            qualifyingAmountCents: $qualifyingAmountCents,
            moduleId: $order->module_id !== null ? (int) $order->module_id : null,
            partnerType: $order->store_id !== null ? 'store' : null,
            partnerId: $order->store_id !== null ? (int) $order->store_id : null,
            zoneId: $order->zone_id !== null ? (int) $order->zone_id : null,
            subjectType: Order::class,
            subjectId: (int) $order->id,
            // Settle on the terms in force when the order was placed, not the
            // terms in force whenever settlement happens to run.
            at: $order->created_at ?? now(),
        );
    }
}
