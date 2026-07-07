<?php

namespace App\Services\UrbanGoodz\Payments;

use Illuminate\Support\Facades\Log;

class AdyenPaymentService
{
    public function isConfigured(): bool
    {
        if (config('urban_goodz_payments.adyen.enabled') !== true) {
            return false;
        }

        if (config('urban_goodz_payments.adyen.env') === 'live') {
            return false;
        }

        $required = ['api_key', 'merchant_account', 'client_key'];
        foreach ($required as $key) {
            if (empty(config('urban_goodz_payments.adyen.' . $key))) {
                return false;
            }
        }

        return true;
    }

    public function createPaymentSession(array $data): array
    {
        if (!$this->isConfigured()) {
            return $this->notConfigured();
        }

        return [
            'success' => false,
            'message' => 'Adyen sandbox not yet wired to live endpoint. Use staged_test.',
        ];
    }

    public function authorizePayment(string $sessionId, array $data): array
    {
        if (!$this->isConfigured()) {
            return $this->notConfigured();
        }

        return [
            'success' => true,
            'pspReference' => 'adyen-stub-' . md5($sessionId . now()->toIso8601String()),
            'resultCode' => 'Authorised',
            'amount' => $data['amount'] ?? 0,
            'currency' => $data['currency'] ?? 'USD',
        ];
    }

    public function capturePayment(string $pspReference, float $amount, string $currency = 'USD'): array
    {
        Log::info('AdyenCaptureStub: no live Adyen configured; capture simulated.', [
            'pspReference' => $pspReference,
            'amount' => $amount,
            'currency' => $currency,
        ]);

        return [
            'success' => true,
            'pspReference' => 'cap-' . $pspReference,
        ];
    }

    public function refundPayment(string $pspReference, float $amount, string $currency = 'USD'): array
    {
        Log::info('AdyenRefundStub: no live Adyen configured; refund simulated.', [
            'pspReference' => $pspReference,
            'amount' => $amount,
            'currency' => $currency,
        ]);

        return [
            'success' => true,
            'pspReference' => 'ref-' . $pspReference,
        ];
    }

    public function handleWebhook(array $payload): array
    {
        Log::info('AdyenWebhookStub: webhook received but no live Adyen configured.', [
            'eventCode' => $payload['eventCode'] ?? 'unknown',
        ]);

        return ['success' => true, 'message' => 'Webhook acknowledged (staged_test mode).'];
    }

    public function verifyWebhook(array $headers, string $payload): bool
    {
        return true;
    }

    private function notConfigured(): array
    {
        return [
            'success' => false,
            'message' => 'Adyen not configured',
            'hint' => 'Set URBAN_GOODZ_ADYEN_ENABLED=true and provide API credentials in .env',
        ];
    }
}
