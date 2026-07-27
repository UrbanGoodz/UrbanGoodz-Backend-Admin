<?php

namespace App\Services\UrbanGoodz\Compensation;

use InvalidArgumentException;

/**
 * Allocates the customer charge across every party.
 *
 * Deliberate model: the driver's amount is whatever the compensation rule
 * computed — it is never a percentage left over after everyone else is paid.
 * Dispatcher, creator, vendor, provider and tax take their configured shares of
 * the basis, and Urban Goodz takes the residual.
 *
 * If the residual is negative the split is reported as a deficit rather than
 * silently clamped, because a rule that pays out more than it collects is a
 * configuration error the operator must see.
 */
final class SplitCalculator
{
    public const PARTIES = ['dispatcher', 'creator', 'vendor', 'provider', 'tax'];

    public function __construct(private readonly string $roundingMode = Money::HALF_UP)
    {
    }

    /**
     * @param  array<string,array<string,mixed>>  $config
     */
    public function calculate(
        array $config,
        CompensationContext $ctx,
        int $driverCents,
        int $passThroughCents = 0
    ): array {
        $basisKey = $config['basis'] ?? 'customer_charge';
        $basisCents = match ($basisKey) {
            'linehaul' => $ctx->linehaulCents,
            'delivery_charge' => $ctx->deliveryChargeCents,
            default => $ctx->customerChargeCents,
        };

        $shares = [];
        $consumed = 0;

        foreach (self::PARTIES as $party) {
            $partyConfig = $config[$party] ?? null;

            if (!is_array($partyConfig)) {
                $shares[$party] = 0;
                continue;
            }

            $cents = 0;

            if (isset($partyConfig['fixed_cents'])) {
                $cents = (int) $partyConfig['fixed_cents'];
                if ($cents < 0) {
                    throw new InvalidArgumentException("Split [{$party}] fixed_cents may not be negative.");
                }
            } elseif (isset($partyConfig['percent'])) {
                $percent = (float) $partyConfig['percent'];
                if ($percent < 0) {
                    throw new InvalidArgumentException("Split [{$party}] percent may not be negative.");
                }
                $cents = Money::percent($basisCents, $percent, $this->roundingMode);
            }

            $shares[$party] = $cents;
            $consumed += $cents;
        }

        // Tips are passed straight through to the driver and are not platform revenue.
        $driverTotal = $driverCents + $passThroughCents;

        $platformCents = $basisCents - $consumed - $driverCents;

        $result = [
            'basis' => $basisKey,
            'basis_cents' => $basisCents,
            'driver_cents' => $driverCents,
            'driver_pass_through_cents' => $passThroughCents,
            'driver_total_cents' => $driverTotal,
            'platform_cents' => $platformCents,
            'is_deficit' => $platformCents < 0,
        ];

        foreach ($shares as $party => $cents) {
            $result[$party . '_cents'] = $cents;
        }

        // Reconciliation: every cent of the basis is accounted for.
        $result['reconciled_cents'] = $driverCents + $consumed + $platformCents;
        $result['reconciles'] = $result['reconciled_cents'] === $basisCents;

        return $result;
    }
}
