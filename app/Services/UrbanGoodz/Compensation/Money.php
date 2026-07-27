<?php

namespace App\Services\UrbanGoodz\Compensation;

use InvalidArgumentException;

/**
 * Integer-cent money helpers.
 *
 * Every compensation calculation runs in integer cents. Floating point is only
 * ever used for the incoming rate/quantity multiplication, and the result is
 * immediately rounded back to an integer under an explicit, named rounding mode
 * so that two runs of the same rule always produce the same cents.
 */
final class Money
{
    public const HALF_UP = 'half_up';
    public const HALF_EVEN = 'half_even';
    public const FLOOR = 'floor';
    public const CEIL = 'ceil';

    public static function modes(): array
    {
        return [self::HALF_UP, self::HALF_EVEN, self::FLOOR, self::CEIL];
    }

    /**
     * Round a fractional cent value to whole cents.
     */
    public static function round(float $cents, string $mode = self::HALF_UP): int
    {
        if (!in_array($mode, self::modes(), true)) {
            throw new InvalidArgumentException("Unknown rounding mode [{$mode}].");
        }

        // Guard against binary representation drift (e.g. 2.0000000000000004).
        $cents = round($cents, 6);

        return match ($mode) {
            self::FLOOR => (int) floor($cents),
            self::CEIL => (int) ceil($cents),
            self::HALF_EVEN => (int) round($cents, 0, PHP_ROUND_HALF_EVEN),
            default => (int) round($cents, 0, PHP_ROUND_HALF_UP),
        };
    }

    /**
     * rate (cents) x quantity, rounded to whole cents.
     */
    public static function multiply(int $rateCents, float $quantity, string $mode = self::HALF_UP): int
    {
        if ($quantity < 0) {
            throw new InvalidArgumentException('Quantity may not be negative.');
        }

        return self::round($rateCents * $quantity, $mode);
    }

    /**
     * A percentage of a base amount. Percent is expressed as 0-100.
     */
    public static function percent(int $baseCents, float $percent, string $mode = self::HALF_UP): int
    {
        if ($percent < 0) {
            throw new InvalidArgumentException('Percentage may not be negative.');
        }

        return self::round($baseCents * ($percent / 100), $mode);
    }

    public static function clamp(int $cents, ?int $min, ?int $max): int
    {
        if ($min !== null && $cents < $min) {
            $cents = $min;
        }

        if ($max !== null && $cents > $max) {
            $cents = $max;
        }

        return $cents;
    }

    public static function toDecimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    public static function fromDecimal(float|int|string $amount): int
    {
        return self::round(((float) $amount) * 100, self::HALF_UP);
    }

    /**
     * Split an amount across weights without losing or inventing cents.
     *
     * Largest-remainder allocation: every recipient gets the floor of its share,
     * then leftover cents are handed out one at a time to the largest remainders.
     * Guarantees array_sum($result) === $totalCents exactly.
     *
     * @param  array<string,float>  $weights
     * @return array<string,int>
     */
    public static function allocate(int $totalCents, array $weights): array
    {
        $weightSum = array_sum($weights);

        if ($weightSum <= 0) {
            return array_map(static fn () => 0, $weights);
        }

        $allocated = [];
        $remainders = [];
        $running = 0;

        foreach ($weights as $key => $weight) {
            $exact = $totalCents * ($weight / $weightSum);
            $floor = (int) floor($exact);
            $allocated[$key] = $floor;
            $remainders[$key] = $exact - $floor;
            $running += $floor;
        }

        $leftover = $totalCents - $running;

        if ($leftover > 0) {
            arsort($remainders);
            foreach (array_keys($remainders) as $key) {
                if ($leftover <= 0) {
                    break;
                }
                $allocated[$key]++;
                $leftover--;
            }
        }

        return $allocated;
    }
}
