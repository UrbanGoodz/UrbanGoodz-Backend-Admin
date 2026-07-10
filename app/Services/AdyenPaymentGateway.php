<?php

namespace App\Services;

use Adyen\AdyenException;
use Adyen\Client;
use Adyen\Model\Checkout\Amount;
use Adyen\Model\Checkout\CreatePaymentLinkRequest;
use Adyen\Model\Checkout\PaymentCaptureRequest;
use Adyen\Model\Checkout\PaymentRefundRequest;
use Adyen\Service\Checkout\ModificationsApi;
use Adyen\Service\Checkout\PaymentsApi;
use Adyen\Service\Checkout\PaymentLinksApi;
use Adyen\Util\HmacSignature;
use App\Contracts\Payments\PaymentGatewayInterface;
use App\Models\OrderAnywhereRequest;
use Illuminate\Support\Facades\Log;

class AdyenPaymentGateway implements PaymentGatewayInterface
{
    private ?Client $client;
    private string $merchantAccount;
    private string $hmacKey;
    private bool $enabled;

    public function __construct()
    {
        $config = config('urban_goodz_payments.adyen');
        $this->enabled = $config['enabled'] ?? false;
        $this->merchantAccount = $config['merchant_account'] ?? '';
        $this->hmacKey = $config['hmac_key'] ?? '';

        if ($this->enabled) {
            $this->client = new Client();
            $this->client->setXApiKey($config['api_key'] ?? '');
            $this->client->setEnvironment(
                ($config['env'] ?? 'sandbox') === 'live' ? Client::ENDPOINT_LIVE : Client::ENDPOINT_CHECKOUT_TEST
            );
        } else {
            $this->client = null;
        }
    }

    public function providerName(): string
    {
        return 'adyen';
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    // ─── PaymentGatewayInterface Implementation ─────────────────────────

    public function createPaymentLink(OrderAnywhereRequest $request, float $amount, string $currency, string $reference, ?string $returnUrl = null, ?string $description = null): array
    {
        if (! $this->enabled || ! $this->client) {
            return $this->stagedTestPaymentLink($amount, $currency, $reference);
        }

        try {
            $paymentLinksApi = new PaymentLinksApi($this->client);

            $params = [
                'merchantAccount' => $this->merchantAccount,
                'amount' => new Amount([
                    'value' => $this->toMinorUnits($amount, $currency),
                    'currency' => $currency,
                ]),
                'reference' => $reference,
                'countryCode' => 'US',
                'returnUrl' => $returnUrl ?? config('urban_goodz_payments.adyen.return_url'),
            ];

            if ($description) {
                $params['description'] = $description;
            }

            $linkRequest = new CreatePaymentLinkRequest($params);
            $response = $paymentLinksApi->create($linkRequest);

            return [
                'success' => true,
                'provider' => $this->providerName(),
                'provider_reference' => $response->getId(),
                'merchant_reference' => $reference,
                'payment_link_id' => $response->getId(),
                'payment_url' => $response->getUrl(),
                'status' => $response->getStatus(),
                'amount' => $amount,
                'currency' => $currency,
                'staged_test' => false,
            ];
        } catch (AdyenException $e) {
            Log::error('Adyen payment link creation failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function authorize(OrderAnywhereRequest $request, float $amount, string $currency, string $reference, ?string $context = null): array
    {
        if (! $this->enabled || ! $this->client) {
            return $this->stagedTestAuthorize($amount, $currency, $reference);
        }

        $pspReference = $context ?? $request->psp_reference ?? $reference;

        try {
            $gatewayResult = $this->legacyCapture($pspReference, $amount, $currency, $reference);

            return [
                'success' => true,
                'provider' => $this->providerName(),
                'provider_reference' => $gatewayResult['psp_reference'] ?? $pspReference,
                'merchant_reference' => $reference,
                'status' => 'authorized',
                'amount' => $amount,
                'currency' => $currency,
                'staged_test' => false,
            ];
        } catch (AdyenException $e) {
            Log::error('Adyen authorize failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function capture(OrderAnywhereRequest $request, float $amount, string $currency, string $reference): array
    {
        if (! $this->enabled || ! $this->client) {
            return $this->stagedTestCapture($amount, $currency, $reference);
        }

        try {
            $pspReference = $request->authorization_reference ?? $request->psp_reference ?? $reference;
            $gatewayResult = $this->legacyCapture($pspReference, $amount, $currency, $reference);

            return [
                'success' => true,
                'provider' => $this->providerName(),
                'provider_reference' => $gatewayResult['psp_reference'] ?? $pspReference,
                'merchant_reference' => $reference,
                'status' => 'captured',
                'amount' => $amount,
                'currency' => $currency,
                'staged_test' => false,
            ];
        } catch (AdyenException $e) {
            Log::error('Adyen capture failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function refund(OrderAnywhereRequest $request, float $amount, string $currency, string $reference, ?string $reason = null): array
    {
        if (! $this->enabled || ! $this->client) {
            return $this->stagedTestRefund($amount, $currency, $reference);
        }

        try {
            $modificationsApi = new ModificationsApi($this->client);
            $pspReference = $request->capture_reference ?? $request->psp_reference ?? $reference;

            $params = [
                'merchantAccount' => $this->merchantAccount,
                'amount' => new Amount([
                    'value' => $this->toMinorUnits($amount, $currency),
                    'currency' => $currency,
                ]),
                'reference' => $reference,
            ];

            if ($reason) {
                $params['merchantRefundReason'] = $reason;
            }

            $refundRequest = new PaymentRefundRequest($params);
            $response = $modificationsApi->refundCapturedPayment($pspReference, $refundRequest);

            return [
                'success' => true,
                'provider' => $this->providerName(),
                'provider_reference' => $response->getPspReference(),
                'merchant_reference' => $reference,
                'status' => 'refunded',
                'amount' => $amount,
                'currency' => $currency,
                'staged_test' => false,
            ];
        } catch (AdyenException $e) {
            Log::error('Adyen refund failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function cancel(OrderAnywhereRequest $request, ?string $reference = null): array
    {
        return [
            'success' => true,
            'provider' => $this->providerName(),
            'provider_reference' => $request->psp_reference ?? $reference,
            'merchant_reference' => $reference ?? $request->request_number,
            'status' => 'canceled',
            'staged_test' => ! $this->enabled,
        ];
    }

    public function validateWebhook(array|string $payload, array $headers = []): bool
    {
        if (empty($this->hmacKey)) {
            Log::warning('Adyen HMAC key not configured, skipping webhook validation');

            return true;
        }

        try {
            $hmacSignature = new HmacSignature();

            return $hmacSignature->isValidNotificationHMAC($this->hmacKey, $payload);
        } catch (AdyenException $e) {
            Log::error('Adyen webhook HMAC validation failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function parseWebhook(array|string $payload, array $headers = []): array
    {
        if (! is_array($payload) || ! isset($payload['notificationItems'])) {
            return [];
        }

        $events = [];

        foreach ($payload['notificationItems'] as $item) {
            $notification = $item['NotificationRequestItem'] ?? $item;

            $events[] = [
                'event_code' => $notification['eventCode'] ?? '',
                'success' => ($notification['success'] ?? 'false') === 'true',
                'provider_reference' => $notification['pspReference'] ?? null,
                'merchant_reference' => $notification['merchantReference'] ?? null,
                'amount_minor' => (int) ($notification['amount']['value'] ?? 0),
                'currency' => $notification['amount']['currency'] ?? 'USD',
                'raw' => $notification,
            ];
        }

        return $events;
    }

    public function retrieveTransaction(string $providerReference): array
    {
        return [
            'success' => true,
            'provider' => $this->providerName(),
            'provider_reference' => $providerReference,
            'status' => 'unknown',
        ];
    }

    // ─── Legacy Backward-Compatible Methods ──────────────────────────────

    public function createSession(float $amount, string $currency, string $reference, ?string $returnUrl = null): array
    {
        if (! $this->enabled || ! $this->client) {
            return $this->stagedTestSession($amount, $currency, $reference);
        }

        try {
            $paymentsApi = new PaymentsApi($this->client);

            $sessionRequest = new \Adyen\Model\Checkout\CreateCheckoutSessionRequest([
                'merchantAccount' => $this->merchantAccount,
                'amount' => new Amount([
                    'value' => $this->toMinorUnits($amount, $currency),
                    'currency' => $currency,
                ]),
                'reference' => $reference,
                'returnUrl' => $returnUrl ?? config('urban_goodz_payments.adyen.return_url'),
                'countryCode' => 'US',
            ]);

            $response = $paymentsApi->sessions($sessionRequest);

            return [
                'id' => $response->getId(),
                'session_data' => $response->getSessionData(),
                'amount' => $amount,
                'currency' => $currency,
                'reference' => $reference,
            ];
        } catch (AdyenException $e) {
            Log::error('Adyen session creation failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function legacyCapture(string $pspReference, float $amount, string $currency, string $reference): array
    {
        if (! $this->enabled || ! $this->client) {
            return $this->stagedTestCapture($amount, $currency, $reference);
        }

        try {
            $modificationsApi = new ModificationsApi($this->client);

            $captureRequest = new PaymentCaptureRequest([
                'merchantAccount' => $this->merchantAccount,
                'amount' => new Amount([
                    'value' => $this->toMinorUnits($amount, $currency),
                    'currency' => $currency,
                ]),
                'reference' => $reference,
            ]);

            $response = $modificationsApi->captureAuthorisedPayment($pspReference, $captureRequest);

            return [
                'success' => true,
                'psp_reference' => $response->getPspReference(),
                'reference' => $reference,
                'status' => 'captured',
            ];
        } catch (AdyenException $e) {
            Log::error('Adyen capture failed', [
                'psp_reference' => $pspReference,
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function legacyRefund(string $pspReference, float $amount, string $currency, string $reference, ?string $reason = null): array
    {
        if (! $this->enabled || ! $this->client) {
            return $this->stagedTestRefund($amount, $currency, $reference);
        }

        try {
            $modificationsApi = new ModificationsApi($this->client);

            $params = [
                'merchantAccount' => $this->merchantAccount,
                'amount' => new Amount([
                    'value' => $this->toMinorUnits($amount, $currency),
                    'currency' => $currency,
                ]),
                'reference' => $reference,
            ];

            if ($reason) {
                $params['merchantRefundReason'] = $reason;
            }

            $refundRequest = new PaymentRefundRequest($params);
            $response = $modificationsApi->refundCapturedPayment($pspReference, $refundRequest);

            return [
                'success' => true,
                'psp_reference' => $response->getPspReference(),
                'reference' => $reference,
                'status' => 'refunded',
            ];
        } catch (AdyenException $e) {
            Log::error('Adyen refund failed', [
                'psp_reference' => $pspReference,
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    // ─── Private Helpers ──────────────────────────────────────────────────

    private function toMinorUnits(float $amount, string $currency): int
    {
        $minorUnits = ['USD' => 2, 'EUR' => 2, 'GBP' => 2, 'JPY' => 0, 'KRW' => 0];
        $exponent = $minorUnits[$currency] ?? 2;

        return (int) round($amount * pow(10, $exponent));
    }

    // ─── Staged Test Mode Fallbacks ───────────────────────────────────────

    private function stagedTestSession(float $amount, string $currency, string $reference): array
    {
        return [
            'id' => 'STG_' . bin2hex(random_bytes(16)),
            'session_data' => 'staged_test_session',
            'amount' => $amount,
            'currency' => $currency,
            'reference' => $reference,
            'staged_test' => true,
        ];
    }

    private function stagedTestPaymentLink(float $amount, string $currency, string $reference): array
    {
        return [
            'success' => true,
            'provider' => $this->providerName(),
            'provider_reference' => 'STG_LINK_' . bin2hex(random_bytes(16)),
            'merchant_reference' => $reference,
            'payment_link_id' => 'STG_LINK_' . bin2hex(random_bytes(16)),
            'payment_url' => '/admin/urban-goodz/order-anywhere?staged_test=1&ref=' . urlencode($reference),
            'status' => 'active',
            'amount' => $amount,
            'currency' => $currency,
            'staged_test' => true,
        ];
    }

    private function stagedTestAuthorize(float $amount, string $currency, string $reference): array
    {
        return [
            'success' => true,
            'provider' => $this->providerName(),
            'provider_reference' => 'STG_' . bin2hex(random_bytes(12)),
            'merchant_reference' => $reference,
            'status' => 'authorized',
            'amount' => $amount,
            'currency' => $currency,
            'staged_test' => true,
        ];
    }

    private function stagedTestCapture(float $amount, string $currency, string $reference): array
    {
        return [
            'success' => true,
            'provider' => $this->providerName(),
            'provider_reference' => 'STG_' . bin2hex(random_bytes(12)),
            'merchant_reference' => $reference,
            'status' => 'captured',
            'amount' => $amount,
            'currency' => $currency,
            'staged_test' => true,
        ];
    }

    private function stagedTestRefund(float $amount, string $currency, string $reference): array
    {
        return [
            'success' => true,
            'provider' => $this->providerName(),
            'provider_reference' => 'STG_' . bin2hex(random_bytes(12)),
            'merchant_reference' => $reference,
            'status' => 'refunded',
            'amount' => $amount,
            'currency' => $currency,
            'staged_test' => true,
        ];
    }
}
