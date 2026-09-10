<?php

namespace App\Services\UrbanGoodz;

use App\Models\UrbanGoodzPaymentTransaction;
use App\Models\UrbanGoodzStrandedOffer;
use App\Models\UrbanGoodzStrandedRequest;
use App\Models\UrbanGoodzStrandedResponder;
// Lives in App\Services, not App\Services\UrbanGoodz. Without this import PHP
// resolves it relative to this namespace and the container fails at runtime --
// which php -l cannot catch.
use App\Services\UrbanGoodzStrandedDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Money for Urban Goodz Stranded.
 *
 * The platform's existing gateway is tightly bound to OrderAnywhereRequest --
 * every method on it takes one -- so a Stranded request cannot travel through
 * it without contorting either side. This talks to Stripe directly for the two
 * movements Stranded actually needs, and records both in
 * urban_goodz_payment_transactions, which is polymorphic and therefore already
 * capable of holding them.
 *
 * Two movements, and they behave differently on purpose:
 *
 *   the fee     charged immediately, non-refundable once broadcast
 *   the reward  authorised and HELD, released only when the work is confirmed
 *
 * The fee buys the broadcast. Charging it and then failing to broadcast would
 * take money for nothing, so the two are committed together and the broadcast
 * failure path is explicit rather than swallowed.
 */
class UrbanGoodzStrandedPaymentService
{
    private const API = 'https://api.stripe.com/v1';

    public function __construct(private readonly UrbanGoodzStrandedDispatcher $dispatcher)
    {
    }

    /**
     * Charge the help request fee, then broadcast.
     *
     * $paymentMethod is a Stripe payment method id supplied by the client SDK.
     * Card details never reach this server, which is the point of collecting
     * them client-side in the first place.
     */
    public function payFeeAndBroadcast(UrbanGoodzStrandedRequest $request, string $paymentMethod): array
    {
        if ($request->help_request_fee_status === 'paid') {
            throw new RuntimeException('The help request fee has already been paid.');
        }

        $amountMinor = (int) $request->help_request_fee_minor;

        // A waived fee still has to broadcast. Charging zero would be rejected
        // by Stripe and would strand the request at the gate.
        if ($amountMinor <= 0) {
            $request->update(['help_request_fee_status' => 'waived']);
            return $this->broadcastAndReport($request->fresh(), null);
        }

        $intent = $this->stripe('POST', '/payment_intents', [
            'amount' => $amountMinor,
            'currency' => strtolower($request->currency ?: 'usd'),
            'payment_method' => $paymentMethod,
            'confirm' => 'true',
            'description' => 'Urban Goodz Stranded help request fee - ' . $request->request_number,
            'metadata[request_number]' => $request->request_number,
            'metadata[request_uuid]' => $request->uuid,
            'automatic_payment_methods[enabled]' => 'true',
            'automatic_payment_methods[allow_redirects]' => 'never',
        ]);

        $ledgerRow = $this->record($request, 'fee', $amountMinor, $intent);

        if (($intent['status'] ?? null) !== 'succeeded') {
            throw new RuntimeException(
                $intent['error']['message']
                ?? 'The payment did not complete (' . ($intent['status'] ?? 'unknown') . ').'
            );
        }

        $request->update([
            'help_request_fee_status' => 'paid',
            // The ledger row id, not the Stripe reference. This column is a
            // bigint; a Stripe id like pi_3U1... silently casts to 0 in it.
            // The provider's own id lives on the ledger row's
            // provider_payment_id, which is a string and the right home for it.
            'help_request_fee_transaction_id' => $ledgerRow->id,
        ]);

        return $this->broadcastAndReport($request->fresh(), $intent['id'] ?? null);
    }

    /**
     * Release held reward money to the responder once the customer confirms.
     *
     * Idempotent by design: confirmation can arrive twice -- a double tap, a
     * retried request -- and paying a responder twice for one rescue is not a
     * recoverable mistake.
     */
    public function releaseEscrow(UrbanGoodzStrandedRequest $request): array
    {
        if ($request->escrow_status === 'released') {
            return ['released' => false, 'reason' => 'already_released', 'amount_minor' => 0];
        }

        if ($request->escrow_status !== 'held') {
            return ['released' => false, 'reason' => 'nothing_held', 'amount_minor' => 0];
        }

        $offer = $request->selected_offer_id
            ? UrbanGoodzStrandedOffer::find($request->selected_offer_id)
            : null;

        $amountMinor = $offer ? $offer->payableAmountMinor() : 0;

        DB::transaction(function () use ($request, $amountMinor) {
            $request->update([
                'escrow_status' => 'released',
                'escrow_released_at' => now(),
            ]);

            if ($amountMinor > 0) {
                UrbanGoodzPaymentTransaction::create([
                    'payable_type' => UrbanGoodzStrandedRequest::class,
                    'payable_id' => $request->id,
                    'provider' => 'stripe',
                    'environment' => config('app.env') === 'production' ? 'live' : 'test',
                    'transaction_type' => 'escrow_release',
                    'internal_status' => 'completed',
                    'provider_status' => 'released',
                    'amount_minor' => $amountMinor,
                    'currency' => $request->currency ?: 'USD',
                    'merchant_reference' => $request->request_number,
                    // One release per request, enforced by the ledger rather
                    // than by remembering to check.
                    'idempotency_key' => 'stranded_escrow_release_' . $request->id,
                ]);
            }
        });

        // Releasing escrow only unlocks the money; it does not move it. The
        // transfer is deliberately OUTSIDE the transaction above: a Stripe
        // call inside a DB transaction holds row locks for the length of a
        // network round trip, and a timeout would roll back the release while
        // Stripe may still have sent the money.
        $payout = $this->payoutResponder($request, $offer, $amountMinor);

        return [
            'released' => true,
            'reason' => null,
            'amount_minor' => $amountMinor,
            'payout' => $payout,
        ];
    }

    /**
     * Pay the responder for real.
     *
     * This is the step that used to be done by hand. Escrow release wrote a
     * ledger row saying the money had moved; nothing had actually left the
     * platform balance, and someone reconciled it manually afterwards.
     *
     * A responder can only be paid into a Stripe connected account, and that
     * account only accepts transfers once Stripe has cleared their identity
     * and bank details. Neither is something the platform can do on their
     * behalf, so "not payable yet" is an expected state, not an error: the
     * payout is recorded as pending and retried later by
     * `urbangoodz:stranded-payouts-retry`.
     *
     * Returns a status array; never throws, because a failed payout must not
     * roll back a completed rescue.
     */
    public function payoutResponder(
        UrbanGoodzStrandedRequest $request,
        ?UrbanGoodzStrandedOffer $offer = null,
        ?int $amountMinor = null
    ): array {
        $offer ??= $request->selected_offer_id
            ? UrbanGoodzStrandedOffer::find($request->selected_offer_id)
            : null;

        $amountMinor ??= $offer ? $offer->payableAmountMinor() : 0;

        if (!$offer || $amountMinor <= 0) {
            return ['paid' => false, 'reason' => 'nothing_owed', 'amount_minor' => 0];
        }

        $key = 'stranded_responder_payout_' . $request->id;

        // The ledger, not a status column, is the source of truth for whether
        // this responder has already been paid.
        $existing = UrbanGoodzPaymentTransaction::where('idempotency_key', $key)->first();

        if ($existing && $existing->internal_status === 'completed') {
            return ['paid' => false, 'reason' => 'already_paid', 'amount_minor' => 0];
        }

        $responder = UrbanGoodzStrandedResponder::find($offer->responder_id);

        if (!$responder) {
            return $this->recordPayout($request, $key, $amountMinor, 'failed', 'responder_missing', null)
                + ['paid' => false, 'reason' => 'responder_missing'];
        }

        $blocker = $this->payoutBlocker($responder);

        if ($blocker !== null) {
            $this->recordPayout($request, $key, $amountMinor, 'pending', $blocker, null);

            return ['paid' => false, 'reason' => $blocker, 'amount_minor' => $amountMinor];
        }

        try {
            $transfer = $this->stripe('POST', '/transfers', [
                'amount' => $amountMinor,
                'currency' => strtolower($request->currency ?: 'USD'),
                'destination' => $responder->stripe_account_id,
                'transfer_group' => $request->request_number,
                'metadata[request_number]' => $request->request_number,
                'metadata[responder_id]' => (string) $responder->id,
            ], $key);
        } catch (\Throwable $e) {
            // Unreachable provider is retryable, so this stays pending rather
            // than failing permanently.
            Log::error('Stranded responder payout could not reach Stripe', [
                'request' => $request->request_number,
                'responder' => $responder->id,
                'error' => $e->getMessage(),
            ]);

            $this->recordPayout($request, $key, $amountMinor, 'pending', 'provider_unreachable', null);

            return ['paid' => false, 'reason' => 'provider_unreachable', 'amount_minor' => $amountMinor];
        }

        if (isset($transfer['error']) || empty($transfer['id'])) {
            $message = $transfer['error']['message'] ?? 'unknown_error';

            Log::error('Stranded responder payout rejected by Stripe', [
                'request' => $request->request_number,
                'responder' => $responder->id,
                'stripe_error' => $message,
            ]);

            $this->recordPayout($request, $key, $amountMinor, 'pending', 'provider_rejected', null);

            return ['paid' => false, 'reason' => 'provider_rejected', 'amount_minor' => $amountMinor];
        }

        $this->recordPayout($request, $key, $amountMinor, 'completed', 'paid', $transfer['id']);

        return [
            'paid' => true,
            'reason' => null,
            'amount_minor' => $amountMinor,
            'transfer_id' => $transfer['id'],
        ];
    }

    /**
     * Why this responder cannot be paid right now, or null if they can be.
     *
     * `payouts_enabled` is checked separately from the account id existing
     * because Stripe issues the account immediately but withholds payouts
     * until verification clears -- transferring in that window just fails.
     */
    private function payoutBlocker(UrbanGoodzStrandedResponder $responder): ?string
    {
        if ($responder->payout_hold) {
            return 'payout_held';
        }

        if (empty($responder->stripe_account_id)) {
            return 'no_connected_account';
        }

        if (!$responder->payouts_enabled) {
            return 'onboarding_incomplete';
        }

        return null;
    }

    /**
     * One ledger row per request, updated in place as the payout progresses
     * from pending to completed. Creating a second row on retry would both
     * violate the unique idempotency key and overstate what was paid out.
     */
    private function recordPayout(
        UrbanGoodzStrandedRequest $request,
        string $key,
        int $amountMinor,
        string $status,
        string $providerStatus,
        ?string $transferId
    ): array {
        UrbanGoodzPaymentTransaction::updateOrCreate(
            ['idempotency_key' => $key],
            [
                'payable_type' => UrbanGoodzStrandedRequest::class,
                'payable_id' => $request->id,
                'provider' => 'stripe',
                'environment' => config('app.env') === 'production' ? 'live' : 'test',
                'transaction_type' => 'responder_payout',
                'internal_status' => $status,
                'provider_status' => $providerStatus,
                'amount_minor' => $amountMinor,
                'currency' => strtoupper($request->currency ?: 'USD'),
                'merchant_reference' => $request->request_number,
                'provider_payment_id' => $transferId,
            ]
        );

        return ['amount_minor' => $amountMinor];
    }

    /**
     * The fee has been taken, so the broadcast must happen or the customer is
     * owed an explanation. A failure here is reported, never swallowed.
     */
    private function broadcastAndReport(UrbanGoodzStrandedRequest $request, ?string $intentId): array
    {
        try {
            $notified = $this->dispatcher->broadcast($request);
        } catch (\Throwable $e) {
            Log::error('Stranded broadcast failed after fee was taken', [
                'request' => $request->request_number,
                'intent' => $intentId,
                'error' => $e->getMessage(),
            ]);

            return [
                'paid' => true,
                'broadcast' => false,
                'responders_notified' => 0,
                'message' => 'Your payment went through, but we could not reach responders just yet. We are retrying.',
            ];
        }

        return [
            'paid' => true,
            'broadcast' => true,
            'responders_notified' => $notified,
            'message' => $notified > 0
                ? 'Help request sent to ' . $notified . ' nearby responder(s).'
                : 'No responders are free right now. We are widening the search.',
        ];
    }

    private function record(UrbanGoodzStrandedRequest $request, string $type, int $amountMinor, array $intent): UrbanGoodzPaymentTransaction
    {
        return UrbanGoodzPaymentTransaction::create([
            'payable_type' => UrbanGoodzStrandedRequest::class,
            'payable_id' => $request->id,
            'provider' => 'stripe',
            'environment' => ($intent['livemode'] ?? false) ? 'live' : 'test',
            'transaction_type' => $type,
            'internal_status' => ($intent['status'] ?? '') === 'succeeded' ? 'completed' : 'failed',
            'provider_status' => $intent['status'] ?? 'unknown',
            'amount_minor' => $amountMinor,
            'currency' => strtoupper($request->currency ?: 'USD'),
            'merchant_reference' => $request->request_number,
            'provider_payment_id' => $intent['id'] ?? null,
            'idempotency_key' => 'stranded_' . $type . '_' . $request->id,
        ]);
    }

    /**
     * Credentials are read per call and never held in a property or logged.
     *
     * $idempotencyKey is passed to Stripe as a request header, not a field.
     * For transfers this is the difference between a retry being free and a
     * retry paying a responder twice: our own unique ledger key stops a second
     * ROW being written, but only Stripe's header stops a second TRANSFER when
     * the first response was lost in flight rather than never sent.
     */
    private function stripe(string $method, string $path, array $fields = [], ?string $idempotencyKey = null): array
    {
        $row = DB::table('addon_settings')->where('key_name', 'stripe')->first();

        if (!$row || !$row->is_active) {
            throw new RuntimeException('Card payments are not available right now.');
        }

        $values = json_decode($row->mode === 'live' ? $row->live_values : $row->test_values, true);
        $key = $values['api_key'] ?? null;

        if (!$key) {
            throw new RuntimeException('Card payments are not configured.');
        }

        $ch = curl_init(self::API . $path);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERPWD => $key . ':',
        ];

        if ($idempotencyKey !== null) {
            $opts[CURLOPT_HTTPHEADER] = ['Idempotency-Key: ' . $idempotencyKey];
        }

        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = http_build_query($fields);
        }

        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException('Could not reach the payment provider. ' . $err);
        }

        return json_decode($body, true) ?: [];
    }
}
