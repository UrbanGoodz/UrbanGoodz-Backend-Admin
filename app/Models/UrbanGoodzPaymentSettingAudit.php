<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UrbanGoodzPaymentSettingAudit extends Model
{
    protected $fillable = [
        'setting_key',
        'old_value',
        'new_value',
        'old_source',
        'new_source',
        'admin_id',
        'action',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }
}
