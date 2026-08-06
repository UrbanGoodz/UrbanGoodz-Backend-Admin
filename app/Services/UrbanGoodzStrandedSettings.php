<?php

namespace App\Services;

use App\Models\BusinessSetting;

/**
 * The single authority for every configurable Urban Goodz Stranded value.
 *
 * No amount of money is hard-coded anywhere else. Controllers, the dispatch
 * worker and the admin screens all resolve through here, so changing a price
 * is an admin action rather than a deploy.
 *
 * Values live in `business_settings` as key/value rows. Note that
 * `business_settings.key` carries NO unique index in this schema, so writes
 * must go through updateOrCreate() (a where-based lookup). An
 * INSERT ... ON DUPLICATE KEY UPDATE silently appends a second row instead of
 * updating the first, and the reader below would then keep returning the old
 * value.
 *
 * The defaults here are the seed values from the product specification and
 * exist only so a fresh install works before an administrator has saved
 * anything. They are not the source of truth once a row exists.
 */
class UrbanGoodzStrandedSettings
{
    /** Toggle: is the Help Request Fee charged at all. */
    public const KEY_FEE_ENABLED = 'stranded_help_request_fee_enabled';
    /** Help Request Fee, in minor units. */
    public const KEY_FEE_MINOR = 'stranded_help_request_fee_minor';
    /** Optional paid upgrade for faster dispatch, in minor units. */
    public const KEY_PRIORITY_UPGRADE_MINOR = 'stranded_priority_upgrade_minor';
    /** Urban Goodz+ members' reduced fee, in minor units. */
    public const KEY_MEMBER_FEE_MINOR = 'stranded_member_help_request_fee_minor';
    /** Commission taken from a professional provider's price, in basis points. */
    public const KEY_PROVIDER_COMMISSION_BPS = 'stranded_provider_commission_bps';
    /** Comma-separated radius ladder, in miles. */
    public const KEY_RADIUS_LADDER = 'stranded_broadcast_radius_ladder';
    /** Seconds a responder has to answer a broadcast. */
    public const KEY_OFFER_TTL_SECONDS = 'stranded_offer_ttl_seconds';
    /** Minutes of community-only window before professionals are offered. */
    public const KEY_ESCALATION_MINUTES = 'stranded_escalation_minutes';

    private const DEFAULTS = [
        self::KEY_FEE_ENABLED => '1',
        self::KEY_FEE_MINOR => '500',
        self::KEY_PRIORITY_UPGRADE_MINOR => '1000',
        self::KEY_MEMBER_FEE_MINOR => '0',
        self::KEY_PROVIDER_COMMISSION_BPS => '1500',
        self::KEY_RADIUS_LADDER => '10,15,20,25',
        self::KEY_OFFER_TTL_SECONDS => '45',
        self::KEY_ESCALATION_MINUTES => '10',
    ];

    /** Every editable key with its default, for the admin screen. */
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

    /**
     * Writes through updateOrCreate deliberately -- see the class docblock for
     * why ON DUPLICATE KEY UPDATE is unsafe against this table.
     */
    public static function put(string $key, string|int|bool $value): void
    {
        if (!array_key_exists($key, self::DEFAULTS)) {
            return;
        }

        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        }

        BusinessSetting::updateOrCreate(['key' => $key], ['value' => (string) $value]);
    }

    public static function feeEnabled(): bool
    {
        return self::raw(self::KEY_FEE_ENABLED) === '1';
    }

    /**
     * What this customer is actually charged to raise a request.
     *
     * Returns 0 when the fee is switched off, so callers never need to check
     * the toggle separately -- "no fee" and "a fee of zero" behave the same
     * way everywhere downstream.
     */
    public static function helpRequestFeeMinor(bool $isMember = false): int
    {
        if (!self::feeEnabled()) {
            return 0;
        }

        if ($isMember) {
            return max(0, (int) self::raw(self::KEY_MEMBER_FEE_MINOR));
        }

        return max(0, (int) self::raw(self::KEY_FEE_MINOR));
    }

    public static function priorityUpgradeMinor(): int
    {
        return max(0, (int) self::raw(self::KEY_PRIORITY_UPGRADE_MINOR));
    }

    public static function providerCommissionBps(): int
    {
        return min(10000, max(0, (int) self::raw(self::KEY_PROVIDER_COMMISSION_BPS)));
    }

    /**
     * The broadcast radius ladder, in miles. Always ascending, de-duplicated
     * and non-empty, so a malformed admin entry cannot leave dispatch with
     * nowhere to broadcast.
     */
    public static function radiusLadder(): array
    {
        $parts = array_filter(
            array_map('trim', explode(',', self::raw(self::KEY_RADIUS_LADDER))),
            fn ($v) => $v !== '' && is_numeric($v) && (int) $v > 0
        );

        $ladder = array_values(array_unique(array_map('intval', $parts)));
        sort($ladder);

        return $ladder ?: array_map('intval', explode(',', self::DEFAULTS[self::KEY_RADIUS_LADDER]));
    }

    public static function offerTtlSeconds(): int
    {
        return max(5, (int) self::raw(self::KEY_OFFER_TTL_SECONDS));
    }

    public static function escalationMinutes(): int
    {
        return max(1, (int) self::raw(self::KEY_ESCALATION_MINUTES));
    }
}
