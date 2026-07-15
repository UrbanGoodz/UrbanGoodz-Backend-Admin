<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BusinessSetting;

class ToggleRecaptcha extends Command
{
    protected $signature = 'urban-goods:toggle-recaptcha {--enable : Enable reCAPTCHA instead of disabling}';
    protected $description = 'Enable or disable reCAPTCHA for admin login';

    public function handle()
    {
        $s = BusinessSetting::where('key', 'recaptcha')->first();
        if (!$s) {
            $this->error('recaptcha setting not found in business_settings table');
            return 1;
        }
        $d = json_decode($s->value, true);
        $was = $d['status'] ?? 0;
        $d['status'] = $this->option('enable') ? 1 : 0;
        $s->value = json_encode($d);
        $s->save();
        $now = $d['status'] ? 'ENABLED' : 'DISABLED';
        $this->info("reCAPTCHA: $was -> {$d['status']} ($now)");
        return 0;
    }
}
