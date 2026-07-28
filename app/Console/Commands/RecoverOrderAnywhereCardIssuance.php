<?php

namespace App\Console\Commands;

use App\Models\OrderAnywhereRequest;
use App\Models\UrbanGoodzOrderAnywhereCardRequest;
use App\Services\OrderAnywhereCardService;
use Illuminate\Console\Command;

class RecoverOrderAnywhereCardIssuance extends Command
{
    protected $signature = 'order-anywhere:recover-card-issuance {--limit=100}';

    protected $description = 'Reevaluate pending Order Anywhere purchase-card issuance safely.';

    public function handle(OrderAnywhereCardService $cards): int
    {
        OrderAnywhereRequest::query()
            ->where('fulfillment_type', OrderAnywhereRequest::FULFILLMENT_EXTERNAL_MERCHANT)
            ->whereIn('status', [
                'approved',
                'shopper_assigned',
                'shopper_accepted',
                'shopping',
                'purchased',
                'picked_up',
                'out_for_delivery',
            ])
            ->whereIn('payment_status', ['authorized', 'captured'])
            ->whereNotNull('assigned_delivery_man_id')
            ->where(function ($query) {
                $query->where('merchant_purchase_amount', '>', 0)
                    ->orWhere('item_subtotal', '>', 0);
            })
            ->whereDoesntHave('cardRequests', function ($query) {
                $query->whereIn('card_status', [
                    'issued',
                    'active',
                    'authorized',
                    'issuance_pending',
                    'awaiting_provider_configuration',
                ]);
            })
            ->orderBy('id')
            ->limit(max(1, min((int) $this->option('limit'), 500)))
            ->get()
            ->each(function (OrderAnywhereRequest $request) use ($cards) {
                try {
                    $cards->createCardRequest($request);
                } catch (\Symfony\Component\HttpKernel\Exception\HttpException) {
                    // A concurrent lifecycle change made this request ineligible.
                }
            });

        // Close cards whose usable window lapsed before the driver spent them, so the
        // provider object does not outlive the approved purchase window.
        UrbanGoodzOrderAnywhereCardRequest::query()
            ->whereIn('card_status', ['issued', 'active'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit(max(1, min((int) $this->option('limit'), 500)))
            ->get()
            ->each(function (UrbanGoodzOrderAnywhereCardRequest $card) use ($cards) {
                try {
                    $cards->expireCard($card);
                } catch (\Throwable) {
                    // The next scheduled pass retries this card.
                }
            });

        UrbanGoodzOrderAnywhereCardRequest::query()
            ->whereIn('card_status', [
                'awaiting_provider_configuration',
                'issuance_retry_pending',
                'failed',
            ])
            ->where(function ($query) {
                $query->whereNull('retry_eligible_at')
                    ->orWhere('retry_eligible_at', '<=', now());
            })
            ->orderBy('id')
            ->limit(max(1, min((int) $this->option('limit'), 500)))
            ->get()
            ->each(function (UrbanGoodzOrderAnywhereCardRequest $card) use ($cards) {
                $request = OrderAnywhereRequest::find($card->order_anywhere_request_id);
                if (! $request) {
                    return;
                }
                try {
                    $cards->createCardRequest($request);
                } catch (\Symfony\Component\HttpKernel\Exception\HttpException) {
                    // The request is no longer eligible; lifecycle observers own revocation.
                }
            });

        return self::SUCCESS;
    }
}
