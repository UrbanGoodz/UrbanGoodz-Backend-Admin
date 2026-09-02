<?php

namespace Database\Seeders;

use App\Models\BusinessSetting;
use Illuminate\Database\Seeder;

/**
 * Modules\ReelsModule\Http\Controllers\Api\V1\Vendor\ReelController::store()
 * hard-gates every vendor reel upload behind
 * Helpers::get_business_settings('vendor_can_upload_reels'). That key had no
 * row at all, so the check was always falsy and every vendor upload 403'd
 * with "feature_disabled" - platform-wide, regardless of approval status.
 * Found while verifying the vendor Reels feature end to end after a
 * (unrelated, session-collision) user report of a Reels "unauthorized"
 * error.
 */
class EnableVendorReelUploadsSeeder extends Seeder
{
    public function run(): void
    {
        BusinessSetting::updateOrCreate(
            ['key' => 'vendor_can_upload_reels'],
            ['value' => '1']
        );

        $this->command->info('vendor_can_upload_reels enabled.');
    }
}
