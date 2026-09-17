<?php

namespace App\Domain\Stranded\Payments;

use App\Domain\Stranded\Notifications\UrbanGoodzStrandedNotifier;
use App\Domain\Stranded\Support\MoniqueOperationsAlertService;
use App\Models\UrbanGoodzPaymentTransaction;
use App\Models\UrbanGoodzStrandedOffer;
use App\Models\UrbanGoodzStrandedRequest;
use App\Models\UrbanGoodzStrandedResponder;
// Lives in App\Services, not this namespace. Without this import PHP resolves
// it relative to this namespace and the container fails at runtime -- which
// php -l cannot catch.
use App\Services\UrbanGoodzStrandedDispatcher;
use App\Services\UrbanGoodzStrandedSettings;
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

    public function __construct(
        private readonly UrbanGoodzStrandedDispatcher $dispatcher,
        private readonly UrbanGoodzStrandedNotifier $notifier,
        private readonly MoniqueOperationsAlertService $alerts
    ) {
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
            $this->alerts->paymentFailed($request, 'help request fee charge');
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
     * Authorise (hold, not capture) the reward amount when a responder is
     * selected. This is what escrow_status = 'held' should have meant all
     * along -- previously it was a bare flag with no Stripe authorization
     * behind it at all.
     *
     * Called before the offer-selection transaction, never inside it: an
     * external API call must not run while holding the row lock that
     * selectOffer() takes. If the transaction that follows this call fails
     * (someone else selected first), the caller must void the hold via
     * voidRewardHold() -- holding a customer's card for a job that never
     * happened is not acceptable, even briefly.
     */
    public function authorizeReward(UrbanGoodzStrandedRequest $request, UrbanGoodzStrandedOffer $offer, string $paymentMethod): array
    {
        $amountMinor = $offer->payableAmountMinor();

        if ($amountMinor <= 0) {
            return ['held' => false, 'ledger_id' => null, 'intent_id' => null];
        }

        $intent = $this->stripe('POST', '/payment_intents', [
            'amount' => $amountMinor,
            'currency' => strtolower($request->currency ?: 'usd'),
            'payment_method' => $paymentMethod,
            'confirm' => 'true',
            'capture_method' => 'manual',
            'description' => 'Urban Goodz Stranded reward hold - ' . $request->request_number,
            'metadata[request_number]' => $request->request_number,
            'metadata[request_uuid]' => $request->uuid,
            'metadata[offer_id]' => (string) $offer->getKey(),
            'automatic_payment_methods[enabled]' => 'true',
            'automatic_payment_methods[allow_redirects]' => 'never',
        ]);

        $ledgerRow = $this->record($request, 'reward_hold', $amountMinor, $intent);

        // requires_capture is success for a manual-capture intent -- Stripe
        // never marks it "succeeded" until it is actually captured.
        if (($intent['status'] ?? null) !== 'requires_capture') {
            return [
                'held' => false,
                'ledger_id' => $ledgerRow->id,
                'intent_id' => $intent['id'] ?? null,
                'message' => $intent['error']['message']
                    ?? 'Your card could not be authorized for the reward amount (' . ($intent['status'] ?? 'unknown') . ').',
            ];
        }

        return ['held' => true, 'ledger_id' => $ledgerRow->id, 'intent_id' => $intent['id'] ?? null];
    }

    /**
     * Release a reward hold that will never be captured -- a losing offer in
     * a race, or any other path where the customer is not actually going to
     * owe this money. Best-effort: a failure here is logged, not thrown, since
     * an uncaptured manual-capture hold also expires on its own after 7 days.
     */
    public function voidRewardHold(string $intentId): void
    {
        try {
            $this->stripe('POST', '/payment_intents/' . $intentId . '/cancel');
        } catch (\Throwable $e) {
            Log::error('Stranded reward hold void failed (will auto-expire)', [
                'intent' => $intentId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Capture the held reward and transfer it to the responder once the
     * customer confirms the work is done.
     *
     * Idempotent by design: confirmation can arrive twice -- a double tap, a
     * retried request -- and paying a responder twice for one rescue is not a
     * recoverable mistake. It is also safe to call again after a partial
     * failure: capture is skipped if the hold is already captured, so a retry
     * only needs to complete whichever step actually failed.
     */
    public function releaseEscrow(UrbanGoodzStrandedRequest $request): array
    {
        if ($request->escrow_status === 'released') {
            return ['released' => false, 'reason' => 'already_released', 'amount_minor' => 0];
        }

        if (!in_array($request->escrow_status, ['held', 'payout_held'], true)) {
            return ['released' => false, 'reason' => 'nothing_held', 'amount_minor' => 0];
        }

        $offer = $request->selected_offer_id
            ? UrbanGoodzStrandedOffer::find($request->selected_offer_id)
            : null;

        $amountMinor = $offer ? $offer->payableAmountMinor() : 0;

        if ($amountMinor <= 0) {
            $request->update(['escrow_status' => 'released', 'escrow_released_at' => now()]);
            return ['released' => true, 'reason' => null, 'amount_minor' => 0];
        }

        $holdLedger = $request->reward_hold_transaction_id
            ? UrbanGoodzPaymentTransaction::find($request->reward_hold_transaction_id)
            : null;
        $intentId = $holdLedger?->provider_payment_id;

        if (!$intentId) {
            Log::error('Stranded escrow release attempted with no reward hold on record', [
                'request' => $request->request_number,
            ]);
            $request->update(['escrow_status' => 'payout_held']);
            return ['released' => false, 'reason' => 'no_hold_on_record', 'amount_minor' => $amountMinor];
        }

        try {
            $charge = $this->captureIfNeeded($intentId);
        } catch (\Throwable $e) {
            Log::error('Stranded escrow capture failed', [
                'request' => $request->request_number,
                'intent' => $intentId,
                'error' => $e->getMessage(),
            ]);
            $request->update(['escrow_status' => 'payout_held']);
            return ['released' => false, 'reason' => 'capture_failed', 'amount_minor' => $amountMinor];
        }

        $responder = ($offer && $offer->responder_id)
            ? UrbanGoodzStrandedResponder::where('user_id', $offer->responder_id)
                ->where('responder_type', $offer->responder_type)
                ->first()
            : null;

        if (!$responder || !$responder->canReceivePayouts()) {
            Log::warning('Stranded escrow captured but responder cannot receive a transfer yet', [
                'request' => $request->request_number,
                'responder_id' => $offer?->responder_id,
            ]);
            $request->update(['escrow_status' => 'payout_held']);
            return ['released' => false, 'reason' => 'responder_not_payout_ready', 'amount_minor' => $amountMinor];
        }

        // Community Samaritans keep the full amount; only professional/vendor
        // responder types carry the platform's configured commission, per
        // UrbanGoodzStrandedSettings::providerCommissionBps()'s own doc: taken
        // "from a professional provider's price."
        $commissionBps = $offer->responder_type === 'samaritan' ? 0 : UrbanGoodzStrandedSettings::providerCommissionBps();
        $transferMinor = $amountMinor - (int) round($amountMinor * $commissionBps / 10000);

        try {
            $transfer = $this->stripe('POST', '/transfers', array_filter([
                'amount' => $transferMinor,
                'currency' => strtolower($request->currency ?: 'usd'),
                'destination' => $responder->stripe_connect_account_id,
                'source_transaction' => $charge['id'] ?? null,
                'description' => 'Urban Goodz Stranded payout - ' . $request->request_number,
                'metadata[request_number]' => $request->request_number,
                'metadata[request_uuid]' => $request->uuid,
            ]), 'stranded_transfer_' . $request->id);
        } catch (\Throwable $e) {
            Log::error('Stranded responder transfer failed', [
                'request' => $request->request_number,
                'destination' => $responder->stripe_connect_account_id,
                'error' => $e->getMessage(),
            ]);
            $request->update(['escrow_status' => 'payout_held']);
            return ['released' => false, 'reason' => 'transfer_failed', 'amount_minor' => $amountMinor];
        }

        if (!($transfer['id'] ?? null)) {
            Log::error('Stranded responder transfer returned no id', [
                'request' => $request->request_number,
                'response' => $transfer,
            ]);
            $request->update(['escrow_status' => 'payout_held']);
            return ['released' => false, 'reason' => 'transfer_failed', 'amount_minor' => $amountMinor];
        }

        DB::transaction(function () use ($request, $transferMinor, $transfer) {
            $request->update([
                'escrow_status' => 'released',
                'escrow_released_at' => now(),
            ]);

            UrbanGoodzPaymentTransaction::create([
                'payable_type' => UrbanGoodzStrandedRequest::class,
                'payable_id' => $request->id,
                'provider' => 'stripe',
                'environment' => ($transfer['livemode'] ?? false) ? 'live' : 'test',
                'transaction_type' => 'responder_payout',
                'internal_status' => 'completed',
                // A ledger row is not proof of payment on its own -- the real
                // Stripe transfer id and status live here, not just a status
                // string this application made up.
                'provider_status' => 'paid',
                'amount_minor' => $transferMinor,
                'currency' => strtoupper($request->currency ?: 'USD'),
                'merchant_reference' => $request->request_number,
                'provider_payment_id' => $transfer['id'],
                'idempotency_key' => 'stranded_escrow_release_' . $request->id,
            ]);
        });

        return ['released' => true, 'reason' => null, 'amount_minor' => $transferMinor];
    }

    /** requires_capture -> captured. Already-captured is left alone, not re-captured. */
    private function captureIfNeeded(string $intentId): array
    {
        $intent = $this->stripe('GET', '/payment_intents/' . $intentId);

        if (($intent['status'] ?? null) === 'succeeded') {
            return $intent['charges']['data'][0] ?? ['id' => $intent['latest_charge'] ?? null];
        }

        $captured = $this->stripe('POST', '/payment_intents/' . $intentId . '/capture');

        if (($captured['status'] ?? null) !== 'succeeded') {
            throw new RuntimeException(
                $captured['error']['message'] ?? 'Capture did not succeed (' . ($captured['status'] ?? 'unknown') . ').'
            );
        }

        return $captured['charges']['data'][0] ?? ['id' => $captured['latest_charge'] ?? null];
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

        $this->notifier->requestPosted($request);

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
     * $idempotencyKey matters specifically for /transfers: this call can run
     * again on a queued-job retry after the first attempt already succeeded
     * on Stripe's side but failed before this application recorded it (a
     * crash between the API response and the DB write). Without it, a retry
     * would create a second real transfer. With it, Stripe returns the
     * original transfer object instead of creating another one -- this is
     * Stripe's own mechanism for exactly this failure mode, not something
     * this application enforces itself.
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
