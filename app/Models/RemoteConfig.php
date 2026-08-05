<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RemoteConfig extends Model
{
    use HasFactory;

    protected $table = 'remote_configs';

    protected $fillable = [
        'app_name',
        'platform',
        'key',
        'value',
        'type',
        'description',
        'is_active',
    ];

    protected $casts = [
        'value' => 'array',
        'is_active' => 'boolean',
    ];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $item = static::where('key', $key)->where('is_active', true)->first();
        return $item ? $item->value : $default;
    }
}
