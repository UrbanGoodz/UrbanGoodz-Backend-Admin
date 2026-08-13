<?php

namespace App\Services\ServiceBookings;

use App\Contracts\ServiceBookingPaymentGateway;
use App\Models\UrbanGoodzServiceRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class StripeServiceBookingPaymentGateway implements ServiceBookingPaymentGateway
{
    public function charge(
        UrbanGoodzServiceRequest $booking,
        string $paymentToken,
        string $idempotencyKey,
        ?int $amountMinor = null
    ): array
    {
        $sandbox = config('service_bookings.payment.sandbox', true);
        $secret = $sandbox
            ? config('service_bookings.payment.stripe_secret_sandbox')
            : config('service_bookings.payment.stripe_secret_live');
        $endpoint = config('service_bookings.payment.stripe_endpoint', 'https://api.stripe.com/v1/payment_intents');
        $timeout = config('service_bookings.payment.timeout', 30);

        if (!$secret) {
            throw new RuntimeException(
                $sandbox
                    ? 'Service booking Stripe sandbox secret key is not configured.'
                    : 'Service booking Stripe live secret key is not configured.'
            );
        }

        $amount = $amountMinor ?? ($booking->deposit_amount_minor ?: $booking->quoted_amount_minor);

        $response = Http::withBasicAuth($secret, '')
            ->timeout($timeout)
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->asForm()
            ->post($endpoint, [
                'amount' => $amount,
                'currency' => strtolower($booking->currency),
                'payment_method' => $paymentToken,
                'confirmation_method' => 'manual',
                'confirm' => 'true',
                'metadata[booking_id]' => $booking->id,
                'metadata[environment]' => $sandbox ? 'sandbox' : 'live',
            ]);

        if (!$response->successful()) {
            $error = $response->json('error.message', 'Stripe payment provider rejected the charge.');
            throw new RuntimeException($error);
        }

        $data = $response->json();

        $status = match ($data['status'] ?? null) {
            'succeeded' => $data['status'],
            default => throw new RuntimeException('Stripe payment was not completed: ' . ($data['status'] ?? 'null')),
        };

        if (empty($data['id'])) {
            throw new RuntimeException('Stripe returned a response without an ID.');
        }

        return ['id' => (string) $data['id'], 'status' => $status];
    }

    public function refund(
        string $providerPaymentId,
        int $amountMinor,
        string $currency,
        string $idempotencyKey
    ): array {
        $sandbox = config('service_bookings.payment.sandbox', true);
        $secret = $sandbox
            ? config('service_bookings.payment.stripe_secret_sandbox')
            : config('service_bookings.payment.stripe_secret_live');
        if (!$secret) {
            throw new RuntimeException('Service booking Stripe secret key is not configured.');
        }

        $response = Http::withBasicAuth($secret, '')
            ->timeout(config('service_bookings.payment.timeout', 30))
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->asForm()
            ->post(config('service_bookings.payment.stripe_refund_endpoint', 'https://api.stripe.com/v1/refunds'), [
                'payment_intent' => $providerPaymentId,
                'amount' => $amountMinor,
                'metadata[environment]' => $sandbox ? 'sandbox' : 'live',
            ]);

        if (!$response->successful()) {
            throw new RuntimeException($response->json('error.message', 'Stripe rejected the service refund.'));
        }
        $data = $response->json();
        if (empty($data['id']) || !in_array($data['status'] ?? null, ['pending', 'succeeded'], true)) {
            throw new RuntimeException('Stripe returned an invalid refund response.');
        }

        return ['id' => (string) $data['id'], 'status' => (string) $data['status']];
    }
}
