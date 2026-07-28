<?php

namespace App\Services;

use App\Models\UrbanGoodzPaymentSetting;
use Illuminate\Support\Facades\Schema;
use LogicException;

class UrbanGoodzPaymentSettings
{
    public function platformFee(): array
    {
        if (Schema::hasTable('urban_goodz_payment_settings')) {
            $setting = UrbanGoodzPaymentSetting::with('auditor')
                ->where('setting_key', 'platform_fee_percent')
                ->first();

            if ($setting) {
                $value = $this->validatedPercent($setting->value);

                return [
                    'effective_percent' => $value,
                    'source' => 'owner_database',
                    'source_label' => 'Owner-configured database setting',
                    'configured' => true,
                    'owner_configured' => true,
                    'changed_by' => $this->adminName($setting),
                    'changed_at' => $setting->last_changed_at,
                ];
            }
        }

        $environmentValue = config('urban_goodz_payments.default_platform_fee_percent');
        if ($environmentValue !== null && $environmentValue !== '') {
            return [
                'effective_percent' => $this->validatedPercent($environmentValue),
                'source' => 'environment',
                'source_label' => 'Environment setting — NOT OWNER-CONFIGURED',
                'configured' => true,
                'owner_configured' => false,
                'changed_by' => null,
                'changed_at' => null,
            ];
        }

        $mode = (string) config('urban_goodz_payments.mode', 'sandbox');
        if ($mode === 'live_controlled') {
            throw new LogicException(
                'Live-controlled payments require an owner-configured platform fee in Payment Center.'
            );
        }

        return [
            'effective_percent' => $this->validatedPercent(
                config('urban_goodz_payments.safe_non_live_platform_fee_percent', 10)
            ),
            'source' => 'safe_non_live_fallback',
            'source_label' => 'Safe non-Live fallback — NOT OWNER-CONFIGURED',
            'configured' => false,
            'owner_configured' => false,
            'changed_by' => null,
            'changed_at' => null,
        ];
    }

    public function platformFeePercent(): float
    {
        return $this->platformFee()['effective_percent'];
    }

    public function savePlatformFee(float $percent, int $adminId): UrbanGoodzPaymentSetting
    {
        $this->validatedPercent($percent);

        return UrbanGoodzPaymentSetting::setPlatformFeePercent($percent, $adminId);
    }

    private function validatedPercent(mixed $value): float
    {
        if (! is_numeric($value) || (float) $value < 0 || (float) $value > 100) {
            throw new LogicException('The effective Urban Goodz platform fee must be between 0 and 100 percent.');
        }

        return round((float) $value, 4);
    }

    private function adminName(UrbanGoodzPaymentSetting $setting): ?string
    {
        if (! $setting->auditor) {
            return null;
        }

        $name = trim($setting->auditor->f_name . ' ' . $setting->auditor->l_name);

        return $name !== '' ? $name : 'Owner #' . $setting->last_changed_by_admin_id;
    }
}
