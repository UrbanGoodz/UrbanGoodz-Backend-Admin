<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Services\AdyenPaymentGateway;
use InvalidArgumentException;
use Illuminate\Support\Manager;

class PaymentProviderManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return config('urban_goodz_payments.provider', 'staged_test');
    }

    public function createStagedTestDriver(): PaymentGatewayInterface
    {
        return new StagedTestPaymentGateway();
    }

    public function createAdyenDriver(): PaymentGatewayInterface
    {
        return new AdyenPaymentGateway();
    }

    public function createStripeDriver(): PaymentGatewayInterface
    {
        return new StripePaymentGateway();
    }

    public function createDisabledDriver(): PaymentGatewayInterface
    {
        return new class implements PaymentGatewayInterface {
            public function providerName(): string
            {
                return 'disabled';
            }

            public function isEnabled(): bool
            {
                return false;
            }

            public function createPaymentLink(\App\Models\OrderAnywhereRequest $request, float $amount, string $currency, string $reference, ?string $returnUrl = null, ?string $description = null): array
            {
                throw new InvalidArgumentException('Payments are currently disabled.');
            }

            public function authorize(\App\Models\OrderAnywhereRequest $request, float $amount, string $currency, string $reference, ?string $context = null): array
            {
                throw new InvalidArgumentException('Payments are currently disabled.');
            }

            public function capture(\App\Models\OrderAnywhereRequest $request, float $amount, string $currency, string $reference): array
            {
                throw new InvalidArgumentException('Payments are currently disabled.');
            }

            public function refund(\App\Models\OrderAnywhereRequest $request, float $amount, string $currency, string $reference, ?string $reason = null): array
            {
                throw new InvalidArgumentException('Payments are currently disabled.');
            }

            public function cancel(\App\Models\OrderAnywhereRequest $request, ?string $reference = null): array
            {
                throw new InvalidArgumentException('Payments are currently disabled.');
            }

            public function validateWebhook(array|string $payload, array $headers = []): bool
            {
                return false;
            }

            public function parseWebhook(array|string $payload, array $headers = []): array
            {
                return [];
            }

            public function retrieveTransaction(string $providerReference): array
            {
                throw new InvalidArgumentException('Payments are currently disabled.');
            }
        };
    }

    public function resolveProvider(?string $provider = null): PaymentGatewayInterface
    {
        $provider = $provider ?? $this->getDefaultDriver();

        if ($provider === 'disabled') {
            return $this->createDisabledDriver();
        }

        return $this->driver($provider);
    }

    public function activeProvider(): PaymentGatewayInterface
    {
        return $this->resolveProvider();
    }

    public function isLiveMode(): bool
    {
        return config('urban_goodz_payments.mode') === 'live_controlled';
    }

    public function isDisabled(): bool
    {
        return config('urban_goodz_payments.mode') === 'disabled'
            || $this->getDefaultDriver() === 'disabled';
    }

    public function providerSupportsLiveControlled(): bool
    {
        $provider = $this->getDefaultDriver();

        return in_array($provider, ['adyen', 'stripe'], true);
    }

    public function stripeLiveKeysAvailable(): bool
    {
        $stripe = config('urban_goodz_payments.stripe');

        return $this->getDefaultDriver() === 'stripe'
            && ! empty($stripe['live_secret_key'])
            && ! empty($stripe['live_publishable_key']);
    }

    public function connectEnabled(): bool
    {
        return (bool) config('urban_goodz_payments.stripe.connect_enabled', false);
    }

    public function connectClientId(): ?string
    {
        return config('urban_goodz_payments.stripe.connect_client_id') ?: null;
    }

    public function connectAccountId(): ?string
    {
        return config('urban_goodz_payments.stripe.connect_account_id') ?: null;
    }
}
