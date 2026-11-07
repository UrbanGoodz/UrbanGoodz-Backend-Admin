<?php

namespace App\Services;

use App\Models\BusinessSetting;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Admin-adjustable Order Anywhere dispatch behaviour. A value saved by an admin (business_settings)
 * wins; otherwise the config/env default applies. Read straight from the database each time so a
 * change takes effect on the next dispatch tick, not after a cache flush.
 */
class OrderAnywhereDispatchSettings
{
    private const PREFIX = 'order_anywhere_dispatch_';

    /** name => [config key, type, min, max] */
    private const FIELDS = [
        'auto_dispatch'          => ['auto_dispatch', 'bool', null, null],
        'radius_miles'           => ['radius_miles', 'float', 1, 500],
        'offer_minutes'          => ['offer_minutes', 'int', 1, 60],
        'escalate_after_minutes' => ['escalate_after_minutes', 'int', 0, 1440],
        'cancel_after_minutes'   => ['cancel_after_minutes', 'int', 0, 10080],
    ];

    public static function get(string $name): bool|int|float
    {
        [$configKey, $type, $min, $max] = self::FIELDS[$name] ?? throw new InvalidArgumentException("Unknown dispatch setting [{$name}].");

        $stored = BusinessSetting::where('key', self::PREFIX . $name)->value('value');
        $raw = $stored !== null && $stored !== '' ? $stored : config("urban_goodz_payments.order_anywhere.{$configKey}");

        return match ($type) {
            'bool' => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            'int'  => max($min, min($max, (int) $raw)),
            'float' => max($min, min($max, (float) $raw)),
        };
    }

    public static function all(): array
    {
        return collect(array_keys(self::FIELDS))->mapWithKeys(fn ($n) => [$n => self::get($n)])->all();
    }

    public static function update(array $input): array
    {
        foreach (array_intersect_key($input, self::FIELDS) as $name => $value) {
            [, $type, $min, $max] = self::FIELDS[$name];
            if ($type !== 'bool' && (! is_numeric($value) || $value < $min || $value > $max)) {
                throw new InvalidArgumentException("{$name} must be between {$min} and {$max}.");
            }
            BusinessSetting::updateOrInsert(
                ['key' => self::PREFIX . $name],
                ['value' => $type === 'bool' ? (filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0') : (string) $value, 'updated_at' => now()]
            );
        }
        Cache::forget('business_settings_all_data');

        return self::all();
    }
}
