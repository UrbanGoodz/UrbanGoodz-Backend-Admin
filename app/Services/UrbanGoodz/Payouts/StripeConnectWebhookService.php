<?php

namespace App\Services\UrbanGoodz\Payouts;

use App\Models\UrbanGoodzConnectedAccount;
use App\Models\UrbanGoodzConnectedPayout;
use App\Models\UrbanGoodzPayoutTransfer;
use App\Services\Payments\StripeConnectGateway;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class StripeConnectWebhookService
{
    private const EVENTS = [
        'account.updated', 'v2.core.account.updated', 'capability.updated',
        'balance.available', 'transfer.created', 'transfer.failed', 'transfer.reversed',
        'payout.created', 'payout.updated', 'payout.paid', 'payout.failed',
        'payout.canceled', 'charge.refunded', 'charge.dispute.created',
        'charge.dispute.closed',
    ];

    public function __construct(
        private readonly ConnectedPayoutService $payouts,
        private readonly StripeConnectGateway $stripe
    ) {}

    /**
     * The caller must verify the Stripe signature before invoking this method.
     */
    public function processVerifiedPayload(string $rawPayload): bool
    {
        $event = json_decode($rawPayload, true);
        $type = (string) ($event['type'] ?? '');
        if (! in_array($type, self::EVENTS, true) || empty($event['id'])) {
            return false;
        }

        $object = (array) data_get($event, 'data.object', []);
        $stripeAccountId = $event['account'] ?? $object['account'] ?? $object['destination'] ?? null;
        if (in_array($type, ['account.updated', 'v2.core.account.updated'], true)) {
            $stripeAccountId = $object['id'] ?? $stripeAccountId;
        }
        $account = $stripeAccountId
            ? UrbanGoodzConnectedAccount::where('stripe_account_id', $stripeAccountId)->first()
            : null;
        $created = (int) ($event['created'] ?? 0);

        $record = DB::table('urban_goodz_stripe_connect_events')
            ->where('stripe_event_id', $event['id'])->first();
        if ($record?->status === 'processed' || $record?->status === 'ignored') {
            return true;
        }
        if (! $record) {
            DB::table('urban_goodz_stripe_connect_events')->insert([
                'stripe_event_id' => $event['id'],
                'connected_account_id' => $account?->id,
                'stripe_account_id' => $stripeAccountId,
                'event_type' => $type,
                'object_id' => $object['id'] ?? null,
                'stripe_created_at' => $created ?: null,
                'payload_sha256' => hash('sha256', $rawPayload),
                'sanitized_payload' => json_encode($this->sanitize($event, $object)),
                'status' => 'processing',
                'attempts' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('urban_goodz_stripe_connect_events')->where('id', $record->id)
                ->increment('attempts', 1, ['updated_at' => now()]);
        }

        try {
            if (! $account && ! in_array($type, [
                'transfer.created', 'transfer.failed', 'transfer.reversed',
                'charge.refunded', 'charge.dispute.created', 'charge.dispute.closed',
            ], true)) {
                $this->finish($event['id'], 'ignored', 'Connected account is not owned by Urban Goodz.');
                return true;
            }

            match ($type) {
                'account.updated', 'v2.core.account.updated' => $this->accountUpdated($account, $object, $created),
                'capability.updated' => $this->capabilityUpdated($account, $object, $created),
                'balance.available' => $this->balanceAvailable($account, $created),
                'transfer.created', 'transfer.failed', 'transfer.reversed' => $this->transferUpdated($type, $object, $created),
                'payout.created', 'payout.updated', 'payout.paid', 'payout.failed', 'payout.canceled'
                    => $this->payoutUpdated($account, $type, $object, $created),
                'charge.refunded' => $this->refundUpdated($event['id'], $object),
                'charge.dispute.created', 'charge.dispute.closed' => $this->disputeUpdated($account, $type, $object),
            };
            $this->finish($event['id'], 'processed');
        } catch (Throwable $exception) {
            $this->finish($event['id'], 'failed', Str::limit($exception->getMessage(), 500));
            throw $exception;
        }

        return true;
    }

    private function accountUpdated(UrbanGoodzConnectedAccount $account, array $object, int $created): void
    {
        if ($this->isOlder($account->last_stripe_event_at, $created)) {
            return;
        }
        if (! isset($object['configuration'])) {
            $object['configuration']['recipient']['capabilities']['stripe_balance'] = [
                'stripe_transfers' => [
                    'status' => data_get($object, 'capabilities.transfers') === 'active' ? 'active' : 'restricted',
                ],
                'payouts' => [
                    'status' => ($object['payouts_enabled'] ?? false) ? 'active' : 'restricted',
                ],
            ];
        }
        $this->payouts->applyAccountStatus($account, $object);
        $account->update(['last_stripe_event_at' => $created ? Carbon::createFromTimestampUTC($created) : now()]);
    }

    private function capabilityUpdated(UrbanGoodzConnectedAccount $account, array $object, int $created): void
    {
        if ($this->isOlder($account->last_stripe_event_at, $created)) {
            return;
        }
        $field = ($object['id'] ?? '') === 'transfers'
            ? 'transfer_capability_status'
            : ((($object['id'] ?? '') === 'payouts') ? 'payout_capability_status' : null);
        if ($field) {
            $account->update([
                $field => $object['status'] ?? 'pending',
                'last_stripe_event_at' => $created ? Carbon::createFromTimestampUTC($created) : now(),
            ]);
        }
    }

    private function balanceAvailable(UrbanGoodzConnectedAccount $account, int $created): void
    {
        $balance = $this->stripe->retrieveBalance($account->stripe_account_id);
        $currency = strtolower($account->currency);
        $account->update([
            'available_balance_cents' => (int) collect($balance['available'] ?? [])
                ->where('currency', $currency)->sum('amount'),
            'pending_balance_cents' => (int) collect($balance['pending'] ?? [])
                ->where('currency', $currency)->sum('amount'),
            'last_synced_at' => now(),
            'last_stripe_event_at' => $created ? Carbon::createFromTimestampUTC($created) : now(),
        ]);
    }

    private function transferUpdated(string $type, array $object, int $created): void
    {
        $transfer = UrbanGoodzPayoutTransfer::where('stripe_transfer_id', $object['id'] ?? '')
            ->orWhere('id', data_get($object, 'metadata.urban_goodz_transfer_id'))->first();
        if (! $transfer || $this->isOlder($transfer->last_stripe_event_at, $created)) {
            return;
        }
        $status = match ($type) {
            'transfer.failed' => 'failed',
            'transfer.reversed' => ((int) ($object['amount_reversed'] ?? 0) >= $transfer->amount_cents)
                ? 'reversed' : 'partially_reversed',
            default => 'created',
        };
        $transfer->update([
            'stripe_transfer_id' => $object['id'] ?? $transfer->stripe_transfer_id,
            'status' => $status,
            'reversed_amount_cents' => max($transfer->reversed_amount_cents, (int) ($object['amount_reversed'] ?? 0)),
            'failure_code' => $object['failure_code'] ?? null,
            'failure_message' => Str::limit((string) ($object['failure_message'] ?? ''), 500) ?: null,
            'last_stripe_event_at' => $created ? Carbon::createFromTimestampUTC($created) : now(),
        ]);
    }

    private function payoutUpdated(
        UrbanGoodzConnectedAccount $account,
        string $type,
        array $object,
        int $created
    ): void {
        $payout = UrbanGoodzConnectedPayout::firstOrNew(['stripe_payout_id' => $object['id']]);
        if ($payout->exists && $this->isOlder($payout->last_stripe_event_at, $created)) {
            return;
        }
        $status = match ($type) {
            'payout.paid' => 'paid',
            'payout.failed' => 'failed',
            'payout.canceled' => 'canceled',
            default => $object['status'] ?? 'pending',
        };
        $returned = $status === 'failed' && in_array($object['failure_code'] ?? null, [
            'account_closed', 'no_account', 'invalid_account_number', 'could_not_process',
        ], true);
        $payout->fill([
            'connected_account_id' => $account->id,
            'amount_cents' => abs((int) ($object['amount'] ?? 0)),
            'currency' => strtoupper((string) ($object['currency'] ?? $account->currency)),
            'status' => $returned ? 'returned' : $status,
            'method' => $object['method'] ?? null,
            'type' => $object['type'] ?? null,
            'failure_code' => $object['failure_code'] ?? null,
            'failure_message' => Str::limit((string) ($object['failure_message'] ?? ''), 500) ?: null,
            'arrival_at' => isset($object['arrival_date']) ? Carbon::createFromTimestampUTC($object['arrival_date']) : null,
            'paid_at' => $status === 'paid' ? now() : $payout->paid_at,
            'returned_at' => $returned ? now() : $payout->returned_at,
            'last_stripe_event_at' => $created ? Carbon::createFromTimestampUTC($created) : now(),
        ])->save();
    }

    private function refundUpdated(string $eventId, array $object): void
    {
        $paymentId = $object['payment_intent'] ?? null;
        if (! $paymentId) {
            return;
        }
        $transfer = UrbanGoodzPayoutTransfer::where('provider_payment_id', $paymentId)
            ->with('recipient.settlement')->first();
        $snapshot = $transfer?->recipient?->settlement;
        if (! $snapshot) {
            return;
        }
        $delta = (int) ($object['amount_refunded'] ?? 0) - $snapshot->refunded_cents;
        if ($delta > 0) {
            $this->payouts->reverseRefund($snapshot, $delta, 'Stripe refund webhook', "stripe:{$eventId}");
        }
    }

    private function disputeUpdated(?UrbanGoodzConnectedAccount $account, string $type, array $object): void
    {
        $paymentId = $object['payment_intent'] ?? $object['charge'] ?? null;
        $query = UrbanGoodzPayoutTransfer::where('provider_payment_id', $paymentId);
        if ($type === 'charge.dispute.created') {
            $query->update(['status' => 'dispute_hold', 'blocked_reason' => 'open_dispute']);
            $account?->update(['refund_hold' => true]);
        } else {
            $won = ($object['status'] ?? '') === 'won';
            $query->where('status', 'dispute_hold')->update([
                'status' => 'manual_review',
                'blocked_reason' => $won ? null : 'dispute_lost',
            ]);
            if ($won) {
                $account?->update(['refund_hold' => false]);
            }
        }
    }

    private function sanitize(array $event, array $object): array
    {
        return [
            'id' => $event['id'],
            'type' => $event['type'],
            'account' => $event['account'] ?? null,
            'created' => $event['created'] ?? null,
            'livemode' => (bool) ($event['livemode'] ?? false),
            'object' => [
                'id' => $object['id'] ?? null,
                'object' => $object['object'] ?? null,
                'status' => $object['status'] ?? null,
                'amount' => $object['amount'] ?? null,
                'amount_refunded' => $object['amount_refunded'] ?? null,
                'amount_reversed' => $object['amount_reversed'] ?? null,
                'currency' => $object['currency'] ?? null,
                'failure_code' => $object['failure_code'] ?? null,
            ],
        ];
    }

    private function isOlder($lastEventAt, int $created): bool
    {
        return $created > 0 && $lastEventAt && $created < $lastEventAt->timestamp;
    }

    private function finish(string $eventId, string $status, ?string $error = null): void
    {
        DB::table('urban_goodz_stripe_connect_events')->where('stripe_event_id', $eventId)->update([
            'status' => $status,
            'error_message' => $error,
            'processed_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
