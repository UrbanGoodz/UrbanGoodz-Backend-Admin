<?php

namespace App\Services\Payments;

use App\Models\UrbanGoodzPaymentSetting;
use Illuminate\Support\Facades\Schema;

class PaymentSettings
{
    public function mode(): string
    {
        $mode = (string) $this->value('payment_mode', config('urban_goodz_payments.mode', 'disabled'));

        return in_array($mode, ['disabled', 'sandbox', 'live_controlled'], true)
            ? $mode
            : 'disabled';
    }

    public function platformFeePercent(): float
    {
        return min(50, max(0, (float) $this->value(
            'platform_fee_percent',
            config('urban_goodz_payments.default_platform_fee_percent', 10)
        )));
    }

    public function value(string $key, mixed $fallback = null): mixed
    {
        try {
            if (Schema::hasTable('urban_goodz_payment_settings')) {
                return UrbanGoodzPaymentSetting::getValue($key, $fallback);
            }
        } catch (\Throwable) {
            // Migrations and emergency maintenance must retain the safe fallback.
        }

        return $fallback;
    }
}
