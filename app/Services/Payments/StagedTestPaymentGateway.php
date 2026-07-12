<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Models\OrderAnywhereRequest;
use Illuminate\Support\Str;

class StagedTestPaymentGateway implements PaymentGatewayInterface
{
    public function providerName(): string
    {
        return 'staged_test';
    }

    public function isEnabled(): bool
    {
        if (app()->environment('production')) {
            return false;
        }

        $mode = config('urban_goodz_payments.mode', 'disabled');
        if ($mode !== 'sandbox' && $mode !== 'test') {
            return false;
        }

        return (bool) config('urban_goodz_payments.staged_test.enabled', false);
    }

    public function createPaymentLink(OrderAnywhereRequest $request, float $amount, string $currency, string $reference, ?string $returnUrl = null, ?string $description = null): array
    {
        $uniqueId = Str::uuid()->toString();

        return [
            'success' => true,
            'provider' => $this->providerName(),
            'provider_reference' => "staged_test_oa_{$request->id}_link_{$uniqueId}",
            'merchant_reference' => $reference,
            'payment_link_id' => "STG_LINK_" . bin2hex(random_bytes(16)),
            'payment_url' => "/admin/urban-goodz/order-anywhere?staged_test=1&ref=" . urlencode($reference),
            'status' => 'active',
            'amount' => $amount,
            'currency' => $currency,
            'staged_test' => true,
        ];
    }

    public function authorize(OrderAnywhereRequest $request, float $amount, string $currency, string $reference, ?string $context = null): array
    {
        $uniqueId = Str::uuid()->toString();

        return [
            'success' => true,
            'provider' => $this->providerName(),
            'provider_reference' => "staged_test_oa_{$request->id}_auth_{$uniqueId}",
            'merchant_reference' => $reference,
            'status' => 'authorized',
            'amount' => $amount,
            'currency' => $currency,
            'staged_test' => true,
        ];
    }

    public function capture(OrderAnywhereRequest $request, float $amount, string $currency, string $reference): array
    {
        $uniqueId = Str::uuid()->toString();

        return [
            'success' => true,
            'provider' => $this->providerName(),
            'provider_reference' => "staged_test_oa_{$request->id}_capture_{$uniqueId}",
            'merchant_reference' => $reference,
            'status' => 'captured',
            'amount' => $amount,
            'currency' => $currency,
            'staged_test' => true,
        ];
    }

    public function refund(OrderAnywhereRequest $request, float $amount, string $currency, string $reference, ?string $reason = null): array
    {
        $uniqueId = Str::uuid()->toString();

        return [
            'success' => true,
            'provider' => $this->providerName(),
            'provider_reference' => "staged_test_oa_{$request->id}_refund_{$uniqueId}",
            'merchant_reference' => $reference,
            'status' => 'refunded',
            'amount' => $amount,
            'currency' => $currency,
            'reason' => $reason,
            'staged_test' => true,
        ];
    }

    public function cancel(OrderAnywhereRequest $request, ?string $reference = null): array
    {
        $uniqueId = Str::uuid()->toString();

        return [
            'success' => true,
            'provider' => $this->providerName(),
            'provider_reference' => "staged_test_oa_{$request->id}_cancel_{$uniqueId}",
            'merchant_reference' => $reference ?? $request->request_number,
            'status' => 'canceled',
            'staged_test' => true,
        ];
    }

    public function validateWebhook(array|string $payload, array $headers = []): bool
    {
        return $this->isEnabled();
    }

    public function parseWebhook(array|string $payload, array $headers = []): array
    {
        if (is_array($payload)) {
            if (isset($payload[0]) && is_array($payload[0])) {
                return $payload;
            }
            if (isset($payload['event_code'])) {
                return [$payload];
            }
        }
        return [];
    }

    public function retrieveTransaction(string $providerReference): array
    {
        return [
            'success' => true,
            'provider' => $this->providerName(),
            'provider_reference' => $providerReference,
            'status' => 'staged_test',
        ];
    }
}
