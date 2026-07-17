<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\UrbanGoodzDriverPricingPolicy;

class UrbanGoodzDriverPricingPolicySeeder extends Seeder
{
    public function run(): void
    {
        $policies = [
            [
                'policy_type' => 'marketplace_delivery',
                'name' => 'Default Marketplace Payout Policy',
                'payout_model' => 'base_mileage_time',
                'base_fare' => 3.50,
                'rate_per_mile' => 0.75,
                'rate_per_minute' => 0.15,
                'minimum_payout' => 4.00,
                'maximum_payout' => 50.00,
                'minimum_margin' => 15.00,
                'is_active' => true,
            ],
            [
                'policy_type' => 'courier_parcel',
                'name' => 'Default Courier & Parcel Policy',
                'payout_model' => 'base_mileage',
                'base_fare' => 4.00,
                'rate_per_mile' => 0.90,
                'minimum_payout' => 5.00,
                'maximum_payout' => 100.00,
                'minimum_margin' => 10.00,
                'is_active' => true,
            ],
            [
                'policy_type' => 'business_routes',
                'name' => 'Default Business Multi-Stop Policy',
                'payout_model' => 'per_stop',
                'rate_per_stop' => 2.50,
                'minimum_payout' => 10.00,
                'minimum_margin' => 12.00,
                'is_active' => true,
            ],
            [
                'policy_type' => 'dedicated_routes',
                'name' => 'Default Dedicated Routes Policy',
                'payout_model' => 'per_package',
                'rate_per_package' => 1.50,
                'minimum_payout' => 20.00,
                'minimum_margin' => 10.00,
                'is_active' => true,
            ],
            [
                'policy_type' => 'logistics_loads',
                'name' => 'Default Logistics Load Board Policy',
                'payout_model' => 'fixed_payout',
                'fixed_amount' => 150.00,
                'minimum_payout' => 50.00,
                'minimum_margin' => 8.00,
                'is_active' => true,
            ],
            [
                'policy_type' => 'medical_courier',
                'name' => 'Default Medical Courier Policy',
                'payout_model' => 'base_mileage',
                'base_fare' => 10.00,
                'rate_per_mile' => 1.50,
                'minimum_payout' => 15.00,
                'minimum_margin' => 20.00,
                'is_active' => true,
            ],
            [
                'policy_type' => 'order_anywhere',
                'name' => 'Default Order Anywhere Policy',
                'payout_model' => 'base_mileage_time',
                'base_fare' => 5.00,
                'rate_per_mile' => 1.00,
                'rate_per_minute' => 0.20,
                'minimum_payout' => 6.50,
                'minimum_margin' => 18.00,
                'is_active' => true,
            ],
            [
                'policy_type' => 'returns_exceptions',
                'name' => 'Default Returns & Exceptions Policy',
                'payout_model' => 'fixed_payout',
                'fixed_amount' => 3.00,
                'minimum_payout' => 3.00,
                'is_active' => true,
            ],
        ];

        foreach ($policies as $policy) {
            UrbanGoodzDriverPricingPolicy::updateOrCreate(
                ['policy_type' => $policy['policy_type'], 'zone_id' => null],
                $policy
            );
        }
    }
}
