<?php

namespace App\Services\Payments;

use App\Contracts\Payments\CardIssuingGatewayInterface;
use App\Models\BusinessSetting;
use InvalidArgumentException;

class CardIssuingProviderManager
{
    private ?CardIssuingGatewayInterface $resolved = null;

    public function resolve(?string $provider = null): CardIssuingGatewayInterface
    {
        $provider = $provider ?? config('urban_goodz_payments.issuing.provider', 'manual');

        return match ($provider) {
            'disabled', 'unconfigured', 'manual' => new ManualIssuingProvider(),
            'staged_test' => new StagedTestIssuingGateway(),
            'stripe' => new \App\Services\Payments\StripeIssuingProvider(),
            default => throw new InvalidArgumentException(
                "Card issuing provider [{$provider}] is not implemented. Available: disabled, staged_test, stripe."
            ),
        };
    }

    public function activeProvider(): CardIssuingGatewayInterface
    {
        if (! $this->resolved) {
            $this->resolved = $this->resolve();
        }

        return $this->resolved;
    }

    public function isAvailable(): bool
    {
        $provider = config('urban_goodz_payments.issuing.provider', 'disabled');
        $mode = config('urban_goodz_payments.issuing.mode', 'sandbox');

        return ! $this->isEmergencyDisabled()
            && $mode !== 'disabled'
            && in_array($provider, ['staged_test', 'stripe'], true)
            && ($provider !== 'staged_test' || app()->environment('testing'))
            && $this->activeProvider()->isEnabled();
    }

    public function configuredProviderName(): string
    {
        return $this->isAvailable()
            ? (string) config('urban_goodz_payments.issuing.provider')
            : 'unconfigured';
    }

    public function configurationStatus(): string
    {
        if ($this->isEmergencyDisabled()) {
            return 'emergency_disabled';
        }

        return $this->isAvailable() ? 'configured' : 'not_configured';
    }

    public function isEmergencyDisabled(): bool
    {
        return (string) BusinessSetting::withoutGlobalScopes()
            ->where('key', 'order_anywhere_card_emergency_disabled')
            ->value('value') === '1';
    }

    public function isLiveMode(): bool
    {
        return config('urban_goodz_payments.issuing.mode') === 'live_controlled';
    }

    public function maxCardAmount(): float
    {
        return (float) config('urban_goodz_payments.issuing.max_driver_card_amount', 50.00);
    }

    public function bufferPercent(): float
    {
        return (float) config('urban_goodz_payments.issuing.driver_card_buffer_percent', 10);
    }

    public function defaultExpiryMinutes(): int
    {
        return (int) config('urban_goodz_payments.issuing.default_expiry_minutes', 120);
    }
}
