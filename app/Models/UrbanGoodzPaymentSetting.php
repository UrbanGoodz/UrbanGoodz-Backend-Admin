<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class UrbanGoodzPaymentSetting extends Model
{
    protected $table = 'urban_goodz_payment_settings';

    protected $fillable = [
        'setting_key',
        'value',
        'source',
        'value_type',
        'last_changed_by_admin_id',
        'last_changed_at',
    ];

    protected $casts = [
        'last_changed_at' => 'datetime',
        'last_changed_by_admin_id' => 'integer',
    ];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = static::where('setting_key', $key)->first();

        if (! $setting) {
            return $default;
        }

        return match ($setting->value_type) {
            'boolean' => (bool) $setting->value,
            'integer' => (int) $setting->value,
            'float', 'decimal' => (float) $setting->value,
            'json' => json_decode($setting->value, true),
            default => $setting->value,
        };
    }

    public static function setValue(string $key, mixed $value, string $source = 'owner', ?int $adminId = null): static
    {
        return DB::transaction(function () use ($key, $value, $source, $adminId) {
            $existing = static::where('setting_key', $key)->lockForUpdate()->first();
            $oldValue = $existing?->value;
            $oldSource = $existing?->source;

            $type = match (true) {
                is_bool($value) => 'boolean',
                is_int($value) => 'integer',
                is_float($value) => 'decimal',
                is_array($value) => 'json',
                default => 'string',
            };

            $serialized = is_array($value)
                ? json_encode($value, JSON_THROW_ON_ERROR)
                : ($value === null ? null : (string) $value);

            $instance = static::updateOrCreate(
                ['setting_key' => $key],
                [
                    'value' => $serialized,
                    'source' => $source,
                    'value_type' => $type,
                    'last_changed_by_admin_id' => $adminId,
                    'last_changed_at' => now(),
                ]
            );

            UrbanGoodzPaymentSettingAudit::create([
                'setting_key' => $key,
                'old_value' => $oldValue,
                'new_value' => $instance->value,
                'old_source' => $oldSource,
                'new_source' => $source,
                'admin_id' => $adminId,
                'action' => $existing ? 'update' : 'create',
            ]);

            return $instance;
        });
    }

    public static function allAsArray(): array
    {
        return static::all()->pluck('value', 'setting_key')->toArray();
    }

    public function auditor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Admin::class, 'last_changed_by_admin_id');
    }
}
