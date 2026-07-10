<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class UrbanGoodzActivityLog extends Model
{
    protected $fillable = [
        'loggable_type',
        'loggable_id',
        'event',
        'description',
        'causer_type',
        'causer_id',
        'old_values',
        'new_values',
        'metadata',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
    ];

    public function loggable(): MorphTo
    {
        return $this->morphTo();
    }

    public function causer(): MorphTo
    {
        return $this->morphTo();
    }
}
