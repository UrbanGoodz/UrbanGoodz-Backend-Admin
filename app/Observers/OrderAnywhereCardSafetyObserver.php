<?php

namespace App\Observers;

use App\Models\OrderAnywhereRequest;
use App\Services\OrderAnywhereCardService;
use Illuminate\Support\Facades\DB;

class OrderAnywhereCardSafetyObserver
{
    /**
     * Lifecycle changes that must revoke card access, in precedence order. The last
     * matching rule wins so that a terminal order state outranks a budget change.
     */
    public function updated(OrderAnywhereRequest $request): void
    {
        $plan = static::planFor($request);

        if (! $plan['reason'] && ! $plan['evaluate']) {
            return;
        }

        $requestId = $request->id;
        DB::afterCommit(function () use ($requestId, $plan) {
            app(OrderAnywhereCardService::class)->applyLifecyclePlan(
                $requestId,
                $plan['reason'],
                $plan['evaluate']
            );
        });
    }

    /**
     * Decide what the change requires. Pure so the mapping is directly testable
     * without depending on transaction-commit timing.
     *
     * @return array{reason: ?string, evaluate: bool}
     */
    public static function planFor(OrderAnywhereRequest $request): array
    {
        $reason = null;

        if ($request->wasChanged('assigned_delivery_man_id')) {
            $reason = 'driver_reassigned';
        }
        if ($request->wasChanged('status')
            && in_array($request->status, ['cancelled', 'rejected', 'completed'], true)) {
            $reason = 'order_' . $request->status;
        }
        if ($request->wasChanged('payment_status')
            && in_array($request->payment_status, ['refunded', 'partially_refunded', 'disputed'], true)) {
            $reason = 'payment_' . $request->payment_status;
        }
        if ($request->wasChanged([
            'merchant_purchase_amount',
            'item_subtotal',
            'quote_amount',
            'final_amount',
        ])) {
            $reason = 'approved_budget_changed';
        }
        if ($request->wasChanged('metadata')
            && (data_get($request->metadata, 'fraud_status') === 'blocked'
                || data_get($request->metadata, 'security_review') === 'blocked')) {
            $reason = 'security_review_blocked';
        }

        $evaluate = $request->wasChanged([
            'status',
            'payment_status',
            'assigned_delivery_man_id',
            'merchant_purchase_amount',
            'item_subtotal',
            'financial_rules_snapshot',
            'metadata',
        ]);

        return ['reason' => $reason, 'evaluate' => $evaluate];
    }
}
