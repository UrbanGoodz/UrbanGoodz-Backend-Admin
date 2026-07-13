<?php

namespace App\Services\ServiceBookings;

use App\Contracts\ServiceBookingPaymentGateway;
use App\Models\UrbanGoodzServiceRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class HttpSandboxServiceBookingPaymentGateway implements ServiceBookingPaymentGateway
{
    public function charge(UrbanGoodzServiceRequest $booking, string $paymentToken, string $idempotencyKey): array
    {
        $endpoint = config('service_bookings.payment.endpoint');
        $key = config('service_bookings.payment.secret');
        if (!config('service_bookings.payment.sandbox') || !$endpoint || !$key) {
            throw new RuntimeException('Service booking sandbox payment gateway is not configured.');
        }
        $amount = $booking->deposit_amount_minor ?: $booking->quoted_amount_minor;
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
        if (!is_array($data) || !in_array($data['status'] ?? null, ['authorized', 'succeeded'], true) || empty($data['id'])) {
            throw new RuntimeException('Sandbox payment provider returned an invalid response.');
        }
        return ['id' => (string) $data['id'], 'status' => (string) $data['status']];
    }
}
