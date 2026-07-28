<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzPaymentSettingAudit extends Model
{
    protected $table = 'urban_goodz_payment_setting_audits';

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

    public function admin(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }
}
