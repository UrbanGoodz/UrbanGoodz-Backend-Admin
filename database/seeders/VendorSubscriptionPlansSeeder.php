<?php

namespace Database\Seeders;

use App\Models\SubscriptionPackage;
use Illuminate\Database\Seeder;

/**
 * Vendor subscription tiers - the StoreSubscription/SubscriptionPackage
 * system existed in code but had zero packages defined, so it generated
 * no revenue despite being fully built. Pricing is a starting point (admin
 * can edit anytime via the existing package management UI), set at
 * standard local-marketplace SaaS rates - low enough that a real vendor's
 * order volume justifies upgrading, not a final business decision.
 */
class VendorSubscriptionPlansSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'package_name' => 'Starter',
                'price' => 0,
                'validity' => 30,
                'max_order' => '50',
                'max_product' => '25',
                'pos' => 0,
                'mobile_app' => 1,
                'chat' => 1,
                'review' => 1,
                'self_delivery' => 0,
                'status' => 1,
                'default' => 1,
                'colour' => '#6c757d',
                'text' => 'Free entry tier - up to 50 orders and 25 products per month.',
            ],
            [
                'package_name' => 'Growth',
                'price' => 29.99,
                'validity' => 30,
                'max_order' => '500',
                'max_product' => '250',
                'pos' => 1,
                'mobile_app' => 1,
                'chat' => 1,
                'review' => 1,
                'self_delivery' => 1,
                'status' => 1,
                'default' => 0,
                'colour' => '#ED9914',
                'text' => 'For growing vendors - POS access, self-delivery, up to 500 orders/month.',
            ],
            [
                'package_name' => 'Premium',
                'price' => 79.99,
                'validity' => 30,
                'max_order' => '999999',
                'max_product' => '999999',
                'pos' => 1,
                'mobile_app' => 1,
                'chat' => 1,
                'review' => 1,
                'self_delivery' => 1,
                'status' => 1,
                'default' => 0,
                'colour' => '#28a745',
                'text' => 'Unlimited orders and products, every feature enabled.',
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPackage::firstOrCreate(
                ['package_name' => $plan['package_name']],
                $plan
            );
        }

        $this->command->info('Vendor subscription plans seeded: Starter (free), Growth ($29.99), Premium ($79.99).');
    }
}
