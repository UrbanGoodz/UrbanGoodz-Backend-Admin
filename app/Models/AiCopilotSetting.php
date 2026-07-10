<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiCopilotSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    protected $casts = [
        'value' => 'string',
    ];
}
