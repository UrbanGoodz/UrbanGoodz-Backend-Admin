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
 * Dedicated to scripted/curl-based backend verification, kept separate from
 * QaTestAccountsSeeder's qa.customer/qa.vendor/qa.driver (which are for a
 * human tester on a phone). Vendor and delivery-man logins in this backend
 * store a single active token per account (login overwrites auth_token), so
 * a human session and an automated session sharing one account will silently
 * kick each other out mid-test. These accounts exist so automated checks
 * never invalidate a live manual test session, and vice versa.
 */
class QaAutomationAccountsSeeder extends Seeder
{
    private const PASSWORD = 'UrbanGoodzQA2026!';

    public function run(): void
    {
        $zone = Zone::first();
        $zoneId = $zone?->id ?? 1;

        User::updateOrCreate(
            ['email' => 'qa.automation.customer@urbangoodzdelivery.com'],
            [
                'f_name' => 'QA',
                'l_name' => 'Automation Customer',
                'phone' => '+12815550111',
                'password' => Hash::make(self::PASSWORD),
                'status' => 1,
                'is_phone_verified' => 1,
                'zone_id' => $zoneId,
            ]
        );

        $vendor = Vendor::updateOrCreate(
            ['email' => 'qa.automation.vendor@urbangoodzdelivery.com'],
            [
                'f_name' => 'QA',
                'l_name' => 'Automation Vendor',
                'phone' => '+12815550112',
                'password' => Hash::make(self::PASSWORD),
                'status' => 1,
            ]
        );

        Store::updateOrCreate(
            ['vendor_id' => $vendor->id],
            [
                'name' => 'QA Automation Store',
                'phone' => '+12815550112',
                'email' => 'qa.automation.vendor@urbangoodzdelivery.com',
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
            ['email' => 'qa.automation.driver@urbangoodzdelivery.com'],
            [
                'f_name' => 'QA',
                'l_name' => 'Automation Driver',
                'phone' => '+12815550113',
                'identity_number' => 'QA-AUTO-0001',
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

        $this->command->info('QA automation accounts ready - customer/vendor/driver, password: '.self::PASSWORD);
    }
}
