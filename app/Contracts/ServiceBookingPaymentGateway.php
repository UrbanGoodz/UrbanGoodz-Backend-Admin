<?php
namespace App\Contracts;
use App\Models\UrbanGoodzServiceRequest;
interface ServiceBookingPaymentGateway
{
    public function charge(
        UrbanGoodzServiceRequest $booking,
        string $paymentToken,
        string $idempotencyKey,
        ?int $amountMinor = null
    ): array;

    public function refund(
        string $providerPaymentId,
        int $amountMinor,
        string $currency,
        string $idempotencyKey
    ): array;
}
