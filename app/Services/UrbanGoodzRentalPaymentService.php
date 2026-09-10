<?php

namespace App\Services;

use App\Models\UrbanGoodzPaymentLedger;
use App\Models\UrbanGoodzRentalBooking;
use App\Services\Payments\PaymentProviderManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Card lifecycle for rental bookings.
 *
 * Deliberately separate from UrbanGoodzPaymentService rather than an extension
 * of it. That service is ~1,900 lines fused with Order Anywhere's economics -
 * vendor payout splits, driver payouts, merchant purchase funds, platform fee
 * reserves - none of which a rental has. What the two genuinely share is the
 * gateway contract (PayableRequest) and the polymorphic payment ledger, and
 * both are reused here.
 *
 * A rental carries two independent money flows:
 *
 *   rent     authorize -> capture            (the customer is charged)
 *   deposit  authorize -> release OR claim   (a hold that usually reverses)
 *
 * The deposit is the part worth being careful about. It is normally authorized
 * at pickup and never captured: on a clean return the hold is voided, and only
 * when an inspection finds damage is part of it captured. Treating it as an
 * ordinary charge would take real money from customers who returned the asset
 * intact.
 */
class UrbanGoodzRentalPaymentService
{
    public function __construct(private PaymentProviderManager $providerManager)
    {
    }

    // ------------------------------------------------------------ rent

    public function authorizeRent(UrbanGoodzRentalBooking $booking, array $data = []): UrbanGoodzRentalBooking
    {
        return DB::transaction(function () use ($booking, $data) {
            $fresh = UrbanGoodzRentalBooking::lockForUpdate()->findOrFail($booking->id);

            $amount = (float) ($data['amount'] ?? $fresh->total_amount);

            $gateway = $this->gatewayFor($fresh);
            $reference = (string) ($data['reference'] ?? ('rent-' . $fresh->id . '-' . Str::uuid()));
            $key = $this->idempotencyKey($fresh, 'rent_authorize', $gateway->providerName(), $reference);

            // Idempotency before the state guard: retrying the same reference
            // must replay cleanly even though the booking now reads
            // "authorized". Without an explicit reference the key is unique
            // per call, so a blind double-authorize still fails loudly below.
            if ($this->findLedger($key)) {
                return $fresh->fresh();
            }

            if (! in_array($fresh->payment_status, ['pending', 'authorization_failed'], true)) {
                throw new \InvalidArgumentException(
                    'Cannot authorize: payment status is ' . $fresh->payment_status . '. Must be pending.'
                );
            }

            $this->assertPositive($amount, 'authorization');

            $result = $gateway->authorize($fresh, $amount, $this->currency($fresh), $reference, 'rental');

            $fresh->forceFill([
                'payment_status' => 'authorized',
                'authorized_amount' => $amount,
                'payment_provider' => $gateway->providerName(),
                'authorization_reference' => $reference,
                'provider_reference' => $result['psp_reference'] ?? $result['reference'] ?? $reference,
                'payment_authorized_at' => now(),
                'authorization_expires_at' => $data['expires_at'] ?? now()->addDays(7),
            ])->save();

            $this->ledger($fresh, 'rent_authorization', 'hold', $amount, 'authorized', [
                'idempotency_key' => $key,
                'reference' => $reference,
            ]);

            return $fresh->fresh();
        });
    }

    public function captureRent(UrbanGoodzRentalBooking $booking, array $data = []): UrbanGoodzRentalBooking
    {
        return DB::transaction(function () use ($booking, $data) {
            $fresh = UrbanGoodzRentalBooking::lockForUpdate()->findOrFail($booking->id);

            // State first: an unauthorized booking has no authorized_amount, so
            // checking the amount first would report a confusing "must be
            // positive" instead of the real problem.
            if ($fresh->payment_status !== 'authorized') {
                throw new \InvalidArgumentException(
                    'Cannot capture: payment status is ' . $fresh->payment_status . '. Must be authorized.'
                );
            }

            $authorized = (float) $fresh->authorized_amount;
            $amount = (float) ($data['amount'] ?? $authorized);
            $this->assertPositive($amount, 'capture');

            if ($amount > $authorized) {
                throw new \InvalidArgumentException(
                    "Capture amount \${$amount} exceeds the authorized \${$authorized}."
                );
            }

            $gateway = $this->gatewayFor($fresh);
            $reference = (string) ($data['reference'] ?? ('rent-cap-' . $fresh->id . '-' . Str::uuid()));

            $key = $this->idempotencyKey($fresh, 'rent_capture', $gateway->providerName(), $reference);
            if ($this->findLedger($key)) {
                return $fresh->fresh();
            }

            $gateway->capture($fresh, $amount, $this->currency($fresh), $reference);

            $fresh->forceFill([
                'payment_status' => 'captured',
                'captured_amount' => $amount,
                'capture_reference' => $reference,
                'payment_captured_at' => now(),
            ])->save();

            $this->ledger($fresh, 'rent_capture', 'debit', $amount, 'captured', [
                'idempotency_key' => $key,
                'reference' => $reference,
            ]);

            return $fresh->fresh();
        });
    }

    /**
     * Refund a captured rental.
     *
     * The idempotency check runs above the state guards, but only for
     * provider-confirmed calls - a redelivered webhook must replay cleanly
     * even once the booking reads "refunded", while an operator clicking
     * refund twice must still fail loudly. Same distinction as the Order
     * Anywhere refund path.
     */
    public function refundRent(UrbanGoodzRentalBooking $booking, array $data = []): UrbanGoodzRentalBooking
    {
        return DB::transaction(function () use ($booking, $data) {
            $fresh = UrbanGoodzRentalBooking::lockForUpdate()->findOrFail($booking->id);

            $captured = (float) $fresh->captured_amount;
            $alreadyRefunded = (float) $fresh->refunded_amount;
            $amount = (float) ($data['amount'] ?? ($captured - $alreadyRefunded));

            $gateway = $this->gatewayFor($fresh);
            $reference = (string) ($data['reference'] ?? $fresh->refund_reference ?? ('rent-ref-' . $fresh->id . '-' . Str::uuid()));
            $key = $data['idempotency_key']
                ?? $this->idempotencyKey($fresh, 'rent_refund', $gateway->providerName(), $reference);

            $providerConfirmed = ! empty($data['idempotency_key']) || ($data['source'] ?? null) === 'webhook';

            if ($providerConfirmed && $this->findLedger($key)) {
                return $fresh->fresh();
            }

            if (! in_array($fresh->payment_status, ['captured', 'partially_refunded'], true)) {
                throw new \InvalidArgumentException(
                    'Cannot refund: payment status is ' . $fresh->payment_status . '. Must be captured.'
                );
            }

            $this->assertPositive($amount, 'refund');

            if ($alreadyRefunded + $amount > $captured) {
                $remaining = $captured - $alreadyRefunded;
                throw new \InvalidArgumentException(
                    "Refund amount \${$amount} exceeds the remaining refundable \${$remaining}."
                );
            }

            if (($data['source'] ?? null) !== 'webhook') {
                $gateway->refund($fresh, $amount, $this->currency($fresh), $reference, $data['reason'] ?? null);
            }

            $total = $alreadyRefunded + $amount;

            $fresh->forceFill([
                'refunded_amount' => $total,
                'payment_status' => $total >= $captured ? 'refunded' : 'partially_refunded',
                'refund_reference' => $reference,
                'payment_refunded_at' => now(),
            ])->save();

            $this->ledger($fresh, 'rent_refund', 'credit', $amount, 'refunded', [
                'idempotency_key' => $key,
                'reference' => $reference,
            ]);

            return $fresh->fresh();
        });
    }

    // --------------------------------------------------------- deposit

    /** Place the damage-deposit hold. This money is not taken, only reserved. */
    public function holdDeposit(UrbanGoodzRentalBooking $booking, array $data = []): UrbanGoodzRentalBooking
    {
        return DB::transaction(function () use ($booking, $data) {
            $fresh = UrbanGoodzRentalBooking::lockForUpdate()->findOrFail($booking->id);

            $amount = (float) ($data['amount'] ?? $fresh->deposit_amount);
            $this->assertPositive($amount, 'deposit hold');

            if ($fresh->deposit_status === 'held') {
                return $fresh->fresh();
            }

            if (! in_array($fresh->deposit_status, ['pending', null], true)) {
                throw new \InvalidArgumentException(
                    'Cannot hold deposit: deposit status is ' . $fresh->deposit_status . '.'
                );
            }

            $gateway = $this->gatewayFor($fresh);
            $reference = (string) ($data['reference'] ?? ('dep-' . $fresh->id . '-' . Str::uuid()));

            $key = $this->idempotencyKey($fresh, 'deposit_authorize', $gateway->providerName(), $reference);
            if ($this->findLedger($key)) {
                return $fresh->fresh();
            }

            $gateway->authorize($fresh, $amount, $this->currency($fresh), $reference, 'rental_deposit');

            $fresh->forceFill([
                'deposit_status' => 'held',
                'deposit_authorized_amount' => $amount,
                'deposit_authorization_reference' => $reference,
                'deposit_authorized_at' => now(),
            ])->save();

            $this->ledger($fresh, 'deposit_authorization', 'hold', $amount, 'authorized', [
                'idempotency_key' => $key,
                'reference' => $reference,
            ]);

            return $fresh->fresh();
        });
    }

    /**
     * Clean return: void the hold. Nothing is captured and nothing is
     * refunded, because no money ever moved.
     */
    public function releaseDeposit(UrbanGoodzRentalBooking $booking, array $data = []): UrbanGoodzRentalBooking
    {
        return DB::transaction(function () use ($booking, $data) {
            $fresh = UrbanGoodzRentalBooking::lockForUpdate()->findOrFail($booking->id);

            if ($fresh->deposit_status === 'released') {
                return $fresh->fresh();
            }

            if ($fresh->deposit_status !== 'held') {
                throw new \InvalidArgumentException(
                    'Cannot release deposit: deposit status is ' . $fresh->deposit_status . '. Must be held.'
                );
            }

            $gateway = $this->gatewayFor($fresh);
            $held = (float) $fresh->deposit_authorized_amount;
            $reference = (string) ($fresh->deposit_authorization_reference ?? ('dep-rel-' . $fresh->id));

            $key = $this->idempotencyKey($fresh, 'deposit_release', $gateway->providerName(), $reference);
            if ($this->findLedger($key)) {
                return $fresh->fresh();
            }

            $gateway->cancel($fresh, $reference);

            $fresh->forceFill([
                'deposit_status' => 'released',
                'deposit_released_at' => now(),
            ])->save();

            $this->ledger($fresh, 'deposit_release', 'release', $held, 'released', [
                'idempotency_key' => $key,
                'reference' => $reference,
            ]);

            return $fresh->fresh();
        });
    }

    /**
     * Damage found: capture part (or all) of the held deposit. Any remainder
     * of the hold is released rather than left dangling against the customer's
     * available credit.
     */
    public function claimDeposit(UrbanGoodzRentalBooking $booking, array $data = []): UrbanGoodzRentalBooking
    {
        return DB::transaction(function () use ($booking, $data) {
            $fresh = UrbanGoodzRentalBooking::lockForUpdate()->findOrFail($booking->id);

            if ($fresh->deposit_status !== 'held') {
                throw new \InvalidArgumentException(
                    'Cannot claim deposit: deposit status is ' . $fresh->deposit_status . '. Must be held.'
                );
            }

            $held = (float) $fresh->deposit_authorized_amount;
            $amount = (float) ($data['amount'] ?? $held);
            $this->assertPositive($amount, 'deposit claim');

            if ($amount > $held) {
                throw new \InvalidArgumentException(
                    "Claim amount \${$amount} exceeds the held deposit of \${$held}."
                );
            }

            $gateway = $this->gatewayFor($fresh);
            $reference = (string) ($data['reference'] ?? ('dep-cap-' . $fresh->id . '-' . Str::uuid()));

            $key = $this->idempotencyKey($fresh, 'deposit_claim', $gateway->providerName(), $reference);
            if ($this->findLedger($key)) {
                return $fresh->fresh();
            }

            $gateway->capture($fresh, $amount, $this->currency($fresh), $reference);

            $fresh->forceFill([
                'deposit_status' => $amount >= $held ? 'claimed' : 'partially_claimed',
                'deposit_captured_amount' => $amount,
                'deposit_capture_reference' => $reference,
                'deposit_released_at' => now(),
            ])->save();

            $this->ledger($fresh, 'deposit_claim', 'debit', $amount, 'captured', [
                'idempotency_key' => $key,
                'reference' => $reference,
                'metadata' => ['held_amount' => $held, 'released_remainder' => round($held - $amount, 2)],
            ]);

            return $fresh->fresh();
        });
    }

    // --------------------------------------------------------- internals

    private function assertPositive(float $amount, string $label): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException(ucfirst($label) . ' amount must be positive.');
        }
    }

    private function currency(UrbanGoodzRentalBooking $booking): string
    {
        return $booking->currency ?: config('urban_goodz_payments.currency', 'USD');
    }

    private function gatewayFor(UrbanGoodzRentalBooking $booking)
    {
        $provider = $booking->payment_provider;

        if (! $provider) {
            return $this->providerManager->activeProvider();
        }

        return $this->providerManager->resolveProvider($provider);
    }

    private function idempotencyKey(
        UrbanGoodzRentalBooking $booking,
        string $event,
        string $provider,
        string $reference
    ): string {
        return implode(':', ['rental', $provider, $booking->id, $event, $reference]);
    }

    private function findLedger(string $key): ?UrbanGoodzPaymentLedger
    {
        return UrbanGoodzPaymentLedger::where('idempotency_key', $key)->first();
    }

    private function ledger(
        UrbanGoodzRentalBooking $booking,
        string $event,
        string $direction,
        float $amount,
        string $status,
        array $options = []
    ): UrbanGoodzPaymentLedger {
        $key = $options['idempotency_key'];

        return UrbanGoodzPaymentLedger::firstOrCreate(
            ['idempotency_key' => $key],
            [
                'ledger_number' => UrbanGoodzPaymentLedger::nextLedgerNumber(),
                'feature' => $booking->getPayableFeature(),
                'payable_type' => UrbanGoodzRentalBooking::class,
                'payable_id' => $booking->id,
                'event_type' => $event,
                'direction' => $direction,
                'amount' => $amount,
                'currency' => $this->currency($booking),
                'payment_status' => $status,
                'reference' => $options['reference'] ?? null,
                'customer_id' => $booking->customer_id,
                'created_by_admin_id' => auth('admin')->id() ?? null,
                'metadata' => $options['metadata'] ?? [],
            ]
        );
    }
}
