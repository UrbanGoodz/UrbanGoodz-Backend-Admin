<?php

namespace App\Services\Payments;

use App\Contracts\Payments\CardIssuingGatewayInterface;
use InvalidArgumentException;

class CardIssuingProviderManager
{
    private ?CardIssuingGatewayInterface $resolved = null;

    public function resolve(?string $provider = null): CardIssuingGatewayInterface
    {
        $provider = $provider ?? config('urban_goodz_payments.issuing.provider', 'manual');

        return match ($provider) {
            'manual' => new ManualIssuingProvider(),
            'staged_test' => new StagedTestIssuingGateway(),
            'stripe' => new \App\Services\Payments\StripeIssuingProvider(),
            default => throw new InvalidArgumentException(
                "Card issuing provider [{$provider}] is not yet implemented. Available: manual, staged_test, stripe."
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
        $provider = config('urban_goodz_payments.issuing.provider', 'manual');
        $mode = config('urban_goodz_payments.issuing.mode', 'sandbox');

        return $mode !== 'disabled' && in_array($provider, ['manual', 'staged_test', 'stripe'], true);
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
