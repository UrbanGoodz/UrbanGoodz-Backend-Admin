<?php

namespace App\Enums\Payments;

enum PaymentStatus: string
{
    case QUOTED = 'quoted';
    case PAYMENT_LINK_CREATED = 'payment_link_created';
    case PENDING = 'pending';
    case REQUIRES_ACTION = 'requires_action';
    case AUTHORIZED = 'authorized';
    case CAPTURED = 'captured';
    case PARTIALLY_CAPTURED = 'partially_captured';
    case FAILED = 'failed';
    case CANCELED = 'canceled';
    case REFUND_PENDING = 'refund_pending';
    case PARTIALLY_REFUNDED = 'partially_refunded';
    case REFUNDED = 'refunded';
    case REFUND_FAILED = 'refund_failed';
    case DISPUTED = 'disputed';

    public static function fromAdyenEvent(string $eventCode, bool $success): self
    {
        return match ($eventCode) {
            'AUTHORISATION' => $success ? self::AUTHORIZED : self::FAILED,
            'CAPTURE' => $success ? self::CAPTURED : self::FAILED,
            'CAPTURE_FAILED' => self::FAILED,
            'REFUND' => $success ? self::REFUNDED : self::REFUND_FAILED,
            'REFUND_FAILED' => self::REFUND_FAILED,
            'CANCELLATION' => self::CANCELED,
            'CANCEL_OR_REFUND' => $success ? self::REFUNDED : self::CANCELED,
            default => self::PENDING,
        };
    }

    public static function fromStripeEvent(string $eventType): self
    {
        return match ($eventType) {
            'checkout.session.completed' => self::CAPTURED,
            'checkout.session.expired' => self::CANCELED,
            'payment_intent.succeeded' => self::CAPTURED,
            'payment_intent.payment_failed' => self::FAILED,
            'payment_intent.canceled' => self::CANCELED,
            'charge.succeeded' => self::CAPTURED,
            'charge.failed' => self::FAILED,
            'charge.refunded' => self::REFUNDED,
            'charge.dispute.created' => self::DISPUTED,
            'refund.created' => self::REFUND_PENDING,
            'refund.succeeded' => self::REFUNDED,
            'refund.failed' => self::REFUND_FAILED,
            default => self::PENDING,
        };
    }
}
