<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeatureFlag extends Model
{
    use HasFactory;

    protected $table = 'feature_flags';

    protected $fillable = [
        'key',
        'name',
        'description',
        'enabled_globally',
        'rules',
    ];

    protected $casts = [
        'enabled_globally' => 'boolean',
        'rules' => 'array',
    ];

    public static function isEnabled(string $key, array $context = []): bool
    {
        $flag = static::where('key', $key)->first();
        if (!$flag) {
            return false;
        }

        if (!$flag->enabled_globally) {
            return false;
        }

        // Targeted rules evaluation if present
        if (!empty($flag->rules) && is_array($flag->rules)) {
            if (isset($flag->rules['disabled_apps']) && isset($context['app_name'])) {
                if (in_array($context['app_name'], (array)$flag->rules['disabled_apps'], true)) {
                    return false;
                }
            }
        }

        return true;
    }
}
