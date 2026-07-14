<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoadSourcingSetting extends Model
{
    protected $table = 'load_sourcing_settings';

    protected $fillable = ['setting_key', 'setting_value', 'setting_type', 'description'];

    public static function get(string $key, $default = null)
    {
        $setting = static::where('setting_key', $key)->first();
        if (!$setting) return $default;
        return match ($setting->setting_type) {
            'integer' => (int) $setting->setting_value,
            'decimal' => (float) $setting->setting_value,
            'boolean' => filter_var($setting->setting_value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($setting->setting_value, true),
            default => $setting->setting_value,
        };
    }

    public static function set(string $key, mixed $value, string $type = 'string', ?string $description = null): self
    {
        return static::updateOrCreate(
            ['setting_key' => $key],
            [
                'setting_value' => is_array($value) ? json_encode($value) : (string) $value,
                'setting_type' => $type,
                'description' => $description,
            ]
        );
    }

    public static function getAll(): array
    {
        return static::pluck('setting_value', 'setting_key')->toArray();
    }
}
