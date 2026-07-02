<?php

namespace App\Support;

use App\Models\BusinessSetting;

class UrbanGoodzMeasurementSettings
{
    public const KEYS = [
        'fashion_measurements_enabled',
        'photo_assisted_measurements_enabled',
        'measurement_free_tester_mode',
        'platform_measurement_fee',
        'paid_measurements_enabled',
        'default_currency',
        'face_blur_required',
        'creator_space_measurement_photo_block_enabled',
    ];

    public static function all(): array
    {
        $settings = [];

        foreach (self::KEYS as $key) {
            $settings[$key] = self::get($key);
        }

        return $settings;
    }

    public static function get(string $key)
    {
        $setting = BusinessSetting::where('key', $key)->first();
        $value = $setting?->value ?? config('urban_goodz_measurements.' . $key);

        if (is_string($value) && in_array(strtolower($value), ['true', 'false'], true)) {
            return strtolower($value) === 'true';
        }

        if (in_array($key, ['fashion_measurements_enabled', 'photo_assisted_measurements_enabled', 'measurement_free_tester_mode', 'paid_measurements_enabled', 'face_blur_required', 'creator_space_measurement_photo_block_enabled'], true)) {
            return (bool) $value;
        }

        if ($key === 'platform_measurement_fee') {
            return (float) $value;
        }

        return $value;
    }

    public static function setMany(array $data): array
    {
        foreach (self::KEYS as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            BusinessSetting::updateOrCreate(
                ['key' => $key],
                ['value' => is_bool($data[$key]) ? ($data[$key] ? '1' : '0') : $data[$key]]
            );
        }

        return self::all();
    }
}
