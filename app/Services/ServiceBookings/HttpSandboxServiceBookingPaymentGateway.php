<?php

namespace App\Services\ServiceBookings;

use App\Contracts\ServiceBookingPaymentGateway;
use App\Models\UrbanGoodzServiceRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class HttpSandboxServiceBookingPaymentGateway implements ServiceBookingPaymentGateway
{
    public function charge(
        UrbanGoodzServiceRequest $booking,
        string $paymentToken,
        string $idempotencyKey,
        ?int $amountMinor = null
    ): array
    {
        $endpoint = config('service_bookings.payment.endpoint');
        $key = config('service_bookings.payment.secret');
        if (!config('service_bookings.payment.sandbox') || !$endpoint || !$key) {
            throw new RuntimeException('Service booking sandbox payment gateway is not configured.');
        }
        $amount = $amountMinor ?? ($booking->deposit_amount_minor ?: $booking->quoted_amount_minor);
        $response = Http::asJson()->withToken($key)->timeout(config('service_bookings.payment.timeout', 30))
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])->post($endpoint, [
                'amount_minor' => $amount, 'currency' => $booking->currency,
                'payment_token' => $paymentToken,
                'metadata' => ['booking_id' => $booking->id, 'environment' => 'sandbox'],
            ]);
        if (!$response->successful()) {
            throw new RuntimeException('Sandbox payment provider rejected the charge.');
        }
        $data = $response->json();
        if (!is_array($data) || ($data['status'] ?? null) !== 'succeeded' || empty($data['id'])) {
            throw new RuntimeException('Sandbox payment provider returned an invalid response.');
        }
        return ['id' => (string) $data['id'], 'status' => (string) $data['status']];
    }

    public function refund(
        string $providerPaymentId,
        int $amountMinor,
        string $currency,
        string $idempotencyKey
    ): array {
        $endpoint = config('service_bookings.payment.refund_endpoint');
        $key = config('service_bookings.payment.secret');
        if (!config('service_bookings.payment.sandbox') || !$endpoint || !$key) {
            throw new RuntimeException('Service booking sandbox refund gateway is not configured.');
        }
        $response = Http::asJson()
            ->withToken($key)
            ->timeout(config('service_bookings.payment.timeout', 30))
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->post($endpoint, [
                'provider_payment_id' => $providerPaymentId,
                'amount_minor' => $amountMinor,
                'currency' => $currency,
            ]);
        $data = $response->json();
        if (!$response->successful() || empty($data['id']) || !in_array($data['status'] ?? null, ['pending', 'succeeded'], true)) {
            throw new RuntimeException('Sandbox payment provider rejected the refund.');
        }

        return ['id' => (string) $data['id'], 'status' => (string) $data['status']];
    }
}
