<?php

namespace App\Services;

use App\Models\BusinessSetting;

/**
 * Instant payout pricing.
 *
 * Weekly payouts are free. Same-day money costs Urban Goodz something to
 * front, so an instant payout carries a fee -- and every part of that fee is
 * configurable rather than compiled in.
 *
 * Rates are held per payee kind. A driver cashing out $80 and a vendor cashing
 * out $4,000 are not the same risk, and a single rate would either overcharge
 * one or undercharge the other.
 *
 * As elsewhere: `business_settings.key` carries no unique index, so writes go
 * through updateOrCreate. An upsert appends a second row and the reader keeps
 * returning the stale value.
 */
class UrbanGoodzPayoutSettings
{
    public const PAYEE_DRIVER = 'driver';
    public const PAYEE_VENDOR = 'vendor';

    private const DEFAULTS = [
        'ug_instant_payout_enabled' => '1',
        // Basis points: 150 = 1.50%.
        'ug_instant_payout_driver_bps' => '150',
        'ug_instant_payout_driver_min' => '0.50',
        'ug_instant_payout_driver_cap' => '15.00',
        'ug_instant_payout_vendor_bps' => '100',
        'ug_instant_payout_vendor_min' => '1.00',
        'ug_instant_payout_vendor_cap' => '50.00',
        // Below this there is nothing worth fronting.
        'ug_instant_payout_minimum_amount' => '5.00',
    ];

    public static function all(): array
    {
        $out = [];
        foreach (self::DEFAULTS as $key => $default) {
            $out[$key] = self::raw($key);
        }
        return $out;
    }

    public static function raw(string $key): string
    {
        $row = BusinessSetting::where('key', $key)->first();
        $value = $row?->value;

        return ($value === null || $value === '')
            ? (self::DEFAULTS[$key] ?? '')
            : (string) $value;
    }

    public static function put(string $key, string|int|float|bool $value): void
    {
        if (!array_key_exists($key, self::DEFAULTS)) {
            return;
        }

        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        }

        BusinessSetting::updateOrCreate(['key' => $key], ['value' => (string) $value]);
    }

    public static function instantEnabled(): bool
    {
        return self::raw('ug_instant_payout_enabled') === '1';
    }

    public static function minimumAmount(): float
    {
        return max(0, (float) self::raw('ug_instant_payout_minimum_amount'));
    }

    /**
     * Quote an instant payout without creating anything.
     *
     * Returns the fee, what the payee actually receives, and the basis it was
     * worked out from — so a driver can be shown "$1.20 fee, you get $78.80"
     * before they commit, rather than discovering it afterwards.
     */
    public static function quote(float $amount, string $payeeType = self::PAYEE_DRIVER): array
    {
        $amount = round(max(0, $amount), 2);

        if (!self::instantEnabled()) {
            return self::refusal($amount, 'instant_disabled',
                'Same-day payouts are turned off at the moment. Your weekly payout is unaffected.');
        }

        if ($amount < self::minimumAmount()) {
            return self::refusal($amount, 'below_minimum',
                'Same-day payout needs at least $' . number_format(self::minimumAmount(), 2)
                . '. Smaller balances go out with your weekly payout, free of charge.');
        }

        $isVendor = $payeeType === self::PAYEE_VENDOR;
        $bps = (int) self::raw($isVendor ? 'ug_instant_payout_vendor_bps' : 'ug_instant_payout_driver_bps');
        $min = (float) self::raw($isVendor ? 'ug_instant_payout_vendor_min' : 'ug_instant_payout_driver_min');
        $cap = (float) self::raw($isVendor ? 'ug_instant_payout_vendor_cap' : 'ug_instant_payout_driver_cap');

        $fee = round($amount * ($bps / 10000), 2);
        $fee = max($fee, $min);
        if ($cap > 0) {
            $fee = min($fee, $cap);
        }

        // The fee can never exceed the payout. A misconfigured minimum on a
        // small balance would otherwise hand somebody a negative payout.
        $fee = min($fee, $amount);

        return [
            'available' => true,
            'code' => null,
            'message' => null,
            'amount' => $amount,
            'fee' => $fee,
            'net' => round($amount - $fee, 2),
            'basis' => [
                'percent_bps' => $bps,
                'percent' => round($bps / 100, 2),
                'minimum' => $min,
                'cap' => $cap > 0 ? $cap : null,
            ],
            // Stated plainly next to the instant option, so the free choice is
            // never the hidden one.
            'weekly_alternative' => [
                'fee' => 0.0,
                'net' => $amount,
                'note' => 'Weekly payouts are always free.',
            ],
        ];
    }

    private static function refusal(float $amount, string $code, string $message): array
    {
        return [
            'available' => false,
            'code' => $code,
            'message' => $message,
            'amount' => $amount,
            'fee' => 0.0,
            'net' => $amount,
            'basis' => null,
            'weekly_alternative' => [
                'fee' => 0.0,
                'net' => $amount,
                'note' => 'Weekly payouts are always free.',
            ],
        ];
    }
}
