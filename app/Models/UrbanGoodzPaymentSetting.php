<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class UrbanGoodzPaymentSetting extends Model
{
    protected $fillable = [
        'setting_key',
        'value',
        'value_type',
        'source',
        'last_changed_by_admin_id',
        'last_changed_at',
    ];

    protected $casts = [
        'last_changed_at' => 'datetime',
        'last_changed_by_admin_id' => 'integer',
    ];

    public function auditor(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'last_changed_by_admin_id');
    }

    public static function setPlatformFeePercent(float $percent, int $adminId): self
    {
        return DB::transaction(function () use ($percent, $adminId) {
            $formatted = rtrim(rtrim(number_format($percent, 4, '.', ''), '0'), '.');
            $existing = static::where('setting_key', 'platform_fee_percent')
                ->lockForUpdate()
                ->first();

            $setting = static::updateOrCreate(
                ['setting_key' => 'platform_fee_percent'],
                [
                    'value' => $formatted,
                    'value_type' => 'decimal',
                    'source' => 'owner_payment_center',
                    'last_changed_by_admin_id' => $adminId,
                    'last_changed_at' => now(),
                ]
            );

            UrbanGoodzPaymentSettingAudit::create([
                'setting_key' => 'platform_fee_percent',
                'old_value' => $existing?->value,
                'new_value' => $setting->value,
                'old_source' => $existing?->source,
                'new_source' => 'owner_payment_center',
                'admin_id' => $adminId,
                'action' => $existing ? 'update' : 'create',
                'metadata' => ['control' => 'payment_center'],
            ]);

            return $setting->fresh('auditor');
        });
    }
}
