<?php

namespace App\Services\ServiceBookings;

use App\Contracts\ServiceBookingPaymentGateway;
use App\Models\UrbanGoodzServiceRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class StripeServiceBookingPaymentGateway implements ServiceBookingPaymentGateway
{
    public function charge(UrbanGoodzServiceRequest $booking, string $paymentToken, string $idempotencyKey): array
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

        $amount = $booking->deposit_amount_minor ?: $booking->quoted_amount_minor;

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
            'succeeded', 'requires_capture' => $data['status'],
            default => throw new RuntimeException('Stripe returned unexpected status: ' . ($data['status'] ?? 'null')),
        };

        if (empty($data['id'])) {
            throw new RuntimeException('Stripe returned a response without an ID.');
        }

        return ['id' => (string) $data['id'], 'status' => $status];
    }
}
