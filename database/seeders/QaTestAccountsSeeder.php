<?php

namespace Database\Seeders;

use App\Models\DeliveryMan;
use App\Models\Store;
use App\Models\StoreSchedule;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Zone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Clearly-labeled, disposable QA accounts, distinct from real production
 * accounts (2 real admins, 131 vendors, 15 delivery men, 37 users already
 * exist - none of this touches or resembles those). Same plaintext
 * password across all three so a human tester only needs to remember one
 * thing; each is a fresh account created for this purpose, not an existing
 * credential exposed. Intentionally excludes an admin account: the two
 * existing admins are real accounts belonging to the actual business
 * owner, and minting a third with a shared test password would be a
 * meaningfully different (and unnecessary) risk than the customer/vendor/
 * driver accounts below.
 */
class QaTestAccountsSeeder extends Seeder
{
    private const PASSWORD = 'UrbanGoodzQA2026!';

    public function run(): void
    {
        $zone = Zone::first();
        $zoneId = $zone?->id ?? 1;

        $user = User::updateOrCreate(
            ['email' => 'qa.customer@urbangoodzdelivery.com'],
            [
                'f_name' => 'QA',
                'l_name' => 'Customer',
                'phone' => '+12815550101',
                'password' => Hash::make(self::PASSWORD),
                'status' => 1,
                'is_phone_verified' => 1,
                'zone_id' => $zoneId,
            ]
        );

        $vendor = Vendor::updateOrCreate(
            ['email' => 'qa.vendor@urbangoodzdelivery.com'],
            [
                'f_name' => 'QA',
                'l_name' => 'Vendor',
                'phone' => '+12815550102',
                'password' => Hash::make(self::PASSWORD),
                'status' => 1,
            ]
        );

        Store::updateOrCreate(
            ['vendor_id' => $vendor->id],
            [
                'name' => 'QA Test Store',
                'phone' => '+12815550102',
                'email' => 'qa.vendor@urbangoodzdelivery.com',
                'address' => '901 Bagby St, Houston, TX 77002, USA',
                'latitude' => '29.7633',
                'longitude' => '-95.3708',
                'module_id' => 13, // Retail/Shopping - active; id 1 ("Demo Module") is inactive
                'zone_id' => $zoneId,
                'status' => 1,
                'active' => 1,
            ]
        );

        $store = Store::where('vendor_id', $vendor->id)->first();
        foreach (range(0, 6) as $day) {
            StoreSchedule::updateOrCreate(
                ['store_id' => $store->id, 'day' => $day],
                ['opening_time' => '00:00:00', 'closing_time' => '23:59:59']
            );
        }

        DeliveryMan::updateOrCreate(
            ['email' => 'qa.driver@urbangoodzdelivery.com'],
            [
                'f_name' => 'QA',
                'l_name' => 'Driver',
                'phone' => '+12815550103',
                'identity_number' => 'QA-TEST-0001',
                'identity_type' => 'driving_licence',
                'password' => Hash::make(self::PASSWORD),
                'zone_id' => $zoneId,
                'status' => 1,
                'active' => 1,
                'type' => 'zone_wise',
                'application_status' => 'approved',
                'is_delivery' => 1,
            ]
        );

        $this->command->info('QA test accounts ready - customer/vendor/driver, password: '.self::PASSWORD);
    }
}
