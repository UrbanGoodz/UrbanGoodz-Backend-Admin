<?php

namespace App\Contracts\Payments;

/**
 * A record that can carry a card authorization through to capture or refund.
 *
 * The payment gateways only ever needed five things from the record they were
 * charging - an id, a human reference for the provider's description, the
 * customer, and the stored capture/provider references used to reconcile a
 * later webhook. They were nonetheless type-hinted directly to
 * OrderAnywhereRequest, which is why no other vertical could take a payment.
 *
 * Implementing this interface is what makes a model chargeable. Deliberately
 * narrow: anything vertical-specific (vendor payout splits, driver payouts,
 * rental deposits) belongs to that vertical's own service, not here.
 */
interface PayableRequest
{
    /** Primary key of the underlying record. */
    public function getPayableId(): int;

    /**
     * Short human-readable reference shown to the provider (statement
     * descriptors, dashboard search). Order Anywhere uses its request number;
     * rentals use a booking reference.
     */
    public function getPayableReference(): string;

    /** Customer being charged, when the vertical tracks one. */
    public function getPayableCustomerId(): ?int;

    /** Provider reference stored at capture time, used to reconcile refunds. */
    public function getCaptureReference(): ?string;

    /** Provider-side payment identifier (PSP reference). */
    public function getProviderReference(): ?string;

    /**
     * Ledger discriminator for this vertical, e.g. "order_anywhere" or
     * "rental". Used as the payment ledger `feature` value and as the
     * idempotency-key prefix, so it must be stable for the life of the record.
     */
    public function getPayableFeature(): string;
}
