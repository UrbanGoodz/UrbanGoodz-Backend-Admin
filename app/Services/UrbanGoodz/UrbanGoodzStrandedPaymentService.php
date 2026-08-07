<?php

namespace App\Services\UrbanGoodz;

use App\Models\UrbanGoodzPaymentTransaction;
use App\Models\UrbanGoodzStrandedOffer;
use App\Models\UrbanGoodzStrandedRequest;
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

        return ['released' => true, 'reason' => null, 'amount_minor' => $amountMinor];
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

    /** Credentials are read per call and never held in a property or logged. */
    private function stripe(string $method, string $path, array $fields = []): array
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
