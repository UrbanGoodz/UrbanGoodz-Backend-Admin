<?php

namespace Database\Seeders;

use App\Models\UrbanGoodzBusinessType;
use App\Models\UrbanGoodzCapability;
use Illuminate\Database\Seeder;

class UrbanGoodzBusinessTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['slug' => 'restaurant', 'name' => 'Restaurant', 'description' => 'Restaurants and dining establishments', 'sort_order' => 1],
            ['slug' => 'food_truck', 'name' => 'Food Truck', 'description' => 'Mobile food vendors and food trucks', 'sort_order' => 2],
            ['slug' => 'grocery_market', 'name' => 'Grocery / Market', 'description' => 'Grocery stores, markets, and food suppliers', 'sort_order' => 3],
            ['slug' => 'retail_shopping', 'name' => 'Retail / Shopping', 'description' => 'General retail stores and shopping outlets', 'sort_order' => 4],
            ['slug' => 'beauty_supply_hair_provider', 'name' => 'Beauty Supply / Hair', 'description' => 'Beauty supply stores, hair care, wigs, and cosmetics', 'sort_order' => 5],
            ['slug' => 'service_provider', 'name' => 'Service Provider', 'description' => 'Professional service providers and consultants', 'sort_order' => 6],
            ['slug' => 'rental_provider', 'name' => 'Rental Provider', 'description' => 'General rental service providers', 'sort_order' => 7],
            ['slug' => 'car_rental', 'name' => 'Car Rental', 'description' => 'Car rental services and vehicle hire', 'sort_order' => 8],
            ['slug' => 'vehicle_rental', 'name' => 'Vehicle Rental', 'description' => 'Motorcycle, scooter, and other vehicle rentals', 'sort_order' => 9],
            ['slug' => 'equipment_rental', 'name' => 'Equipment Rental', 'description' => 'Equipment, tool, and machinery rental services', 'sort_order' => 10],
            ['slug' => 'event_vendor', 'name' => 'Event Vendor', 'description' => 'Event organizers, vendors, and pop-up markets', 'sort_order' => 11],
            ['slug' => 'courier', 'name' => 'Courier / Parcel', 'description' => 'Courier services and parcel delivery', 'sort_order' => 12],
            ['slug' => 'medical_courier', 'name' => 'Medical Courier', 'description' => 'Medical specimen and pharmaceutical courier services', 'sort_order' => 13],
            ['slug' => 'creator', 'name' => 'Creator', 'description' => 'Content creators, influencers, and digital commerce', 'sort_order' => 14],
            ['slug' => 'fashion_fit_provider', 'name' => 'Fashion Fit Provider', 'description' => 'Tailoring, alterations, and fashion measurement services', 'sort_order' => 15],
        ];

        foreach ($types as $data) {
            UrbanGoodzBusinessType::firstOrCreate(
                ['slug' => $data['slug']],
                $data
            );
        }

        $capabilities = [
            ['slug' => 'direct-checkout', 'name' => 'Direct Checkout', 'description' => 'Customer can checkout directly without admin intervention', 'admin_section_key' => null, 'group' => 'fulfillment', 'is_core' => true, 'sort_order' => 1],
            ['slug' => 'public-listing', 'name' => 'Public Listing', 'description' => 'Business appears in public search and discovery', 'admin_section_key' => 'discovery', 'group' => 'core', 'is_core' => true, 'sort_order' => 2],
            ['slug' => 'admin-managed', 'name' => 'Admin-Managed', 'description' => 'Business requires admin oversight for orders', 'admin_section_key' => null, 'group' => 'core', 'is_core' => true, 'sort_order' => 3],
            ['slug' => 'order-anywhere', 'name' => 'Order Anywhere', 'description' => 'Request items from any business not yet fully onboarded', 'admin_section_key' => 'order-anywhere', 'group' => 'fulfillment', 'is_core' => false, 'sort_order' => 10],
            ['slug' => 'fashion-fit', 'name' => 'Fashion Fit', 'description' => 'Tailoring, alterations, and fashion measurement services', 'admin_section_key' => 'fashion-fit', 'group' => 'fashion', 'is_core' => false, 'sort_order' => 20],
            ['slug' => 'ai-concierge', 'name' => 'AI Concierge', 'description' => 'AI-powered customer query handling and intent routing', 'admin_section_key' => 'ai-concierge', 'group' => 'ai', 'is_core' => false, 'sort_order' => 30],
            ['slug' => 'book-anything', 'name' => 'Book Anything', 'description' => 'Service booking and appointment scheduling', 'admin_section_key' => 'book-anything', 'group' => 'services', 'is_core' => false, 'sort_order' => 40],
            ['slug' => 'creator-commerce', 'name' => 'Creator Commerce', 'description' => 'Creator merchandise sales and influencer commerce tools', 'admin_section_key' => 'creator-commerce', 'group' => 'content', 'is_core' => false, 'sort_order' => 50],
            ['slug' => 'community-marketplace', 'name' => 'Community Marketplace', 'description' => 'Community posts, marketplace listings, and social commerce', 'admin_section_key' => 'community', 'group' => 'social', 'is_core' => false, 'sort_order' => 60],
            ['slug' => 'earn-money', 'name' => 'Earn Money', 'description' => 'Referral programs, affiliate opportunities, and gigs', 'admin_section_key' => 'earn-money', 'group' => 'monetization', 'is_core' => false, 'sort_order' => 70],
            ['slug' => 'logistics', 'name' => 'Logistics / Load Board', 'description' => 'Freight matching and logistics coordination', 'admin_section_key' => 'logistics', 'group' => 'logistics', 'is_core' => false, 'sort_order' => 80],
            ['slug' => 'medical-courier', 'name' => 'Medical Courier', 'description' => 'Medical specimen and pharmaceutical transport', 'admin_section_key' => 'medical-courier', 'group' => 'logistics', 'is_core' => false, 'sort_order' => 90],
            ['slug' => 'events', 'name' => 'Events', 'description' => 'Event creation, ticketing, and promotion', 'admin_section_key' => 'events', 'group' => 'social', 'is_core' => false, 'sort_order' => 100],
            ['slug' => 'rental-inventory', 'name' => 'Rental Inventory', 'description' => 'Rental item inventory and availability management', 'admin_section_key' => 'rentals', 'group' => 'fulfillment', 'is_core' => false, 'sort_order' => 110],
            ['slug' => 'vehicle-inventory', 'name' => 'Vehicle Inventory', 'description' => 'Vehicle fleet inventory and availability management', 'admin_section_key' => 'vehicle-rentals', 'group' => 'fulfillment', 'is_core' => false, 'sort_order' => 115],
            ['slug' => 'rental-calendar', 'name' => 'Rental Calendar', 'description' => 'Rental availability calendar and scheduling', 'admin_section_key' => 'rentals', 'group' => 'fulfillment', 'is_core' => false, 'sort_order' => 120],
            ['slug' => 'daily-rate-management', 'name' => 'Daily Rate Management', 'description' => 'Set and manage daily rental pricing', 'admin_section_key' => 'rentals', 'group' => 'fulfillment', 'is_core' => false, 'sort_order' => 130],
            ['slug' => 'hourly-rate-management', 'name' => 'Hourly Rate Management', 'description' => 'Set and manage hourly rental pricing', 'admin_section_key' => 'rentals', 'group' => 'fulfillment', 'is_core' => false, 'sort_order' => 140],
            ['slug' => 'deposit-management', 'name' => 'Deposit Management', 'description' => 'Manage rental deposits and security holds', 'admin_section_key' => 'rentals', 'group' => 'fulfillment', 'is_core' => false, 'sort_order' => 150],
            ['slug' => 'renter-verification', 'name' => 'Renter Verification', 'description' => 'Verify renter identity and credentials', 'admin_section_key' => 'rentals', 'group' => 'fulfillment', 'is_core' => false, 'sort_order' => 160],
            ['slug' => 'pickup-return-management', 'name' => 'Pickup & Return', 'description' => 'Manage rental pickup and return logistics', 'admin_section_key' => 'rentals', 'group' => 'fulfillment', 'is_core' => false, 'sort_order' => 170],
            ['slug' => 'damage-report-management', 'name' => 'Damage Report', 'description' => 'Report and manage rental damage claims', 'admin_section_key' => 'rentals', 'group' => 'fulfillment', 'is_core' => false, 'sort_order' => 180],
            ['slug' => 'plus', 'name' => 'Urban Goodz+', 'description' => 'Premium subscription benefits and exclusive features', 'admin_section_key' => 'plus', 'group' => 'subscription', 'is_core' => false, 'sort_order' => 190],
            ['slug' => 'spotlight', 'name' => 'Black-Owned Spotlight', 'description' => 'Black-owned business promotion and discovery', 'admin_section_key' => 'spotlight', 'group' => 'marketing', 'is_core' => false, 'sort_order' => 200],
            ['slug' => 'discovery', 'name' => 'Discovery / Search', 'description' => 'Search analytics and demand signal capture', 'admin_section_key' => 'discovery', 'group' => 'ai', 'is_core' => false, 'sort_order' => 210],
            ['slug' => 'ask', 'name' => 'Ask Urban Goodz', 'description' => 'Customer Q&A and knowledge base', 'admin_section_key' => 'ai-concierge', 'group' => 'ai', 'is_core' => false, 'sort_order' => 220],
            ['slug' => 'menu-management', 'name' => 'Menu Management', 'description' => 'Digital menu and item listing management', 'admin_section_key' => null, 'group' => 'fulfillment', 'is_core' => false, 'sort_order' => 230],
            ['slug' => 'product-inventory', 'name' => 'Product Inventory', 'description' => 'Product stock and inventory tracking', 'admin_section_key' => null, 'group' => 'fulfillment', 'is_core' => false, 'sort_order' => 240],
            ['slug' => 'appointment-booking', 'name' => 'Appointment Booking', 'description' => 'Schedule and manage customer appointments', 'admin_section_key' => 'book-anything', 'group' => 'services', 'is_core' => false, 'sort_order' => 250],
            ['slug' => 'local-delivery', 'name' => 'Local Delivery', 'description' => 'Local area delivery service', 'admin_section_key' => null, 'group' => 'logistics', 'is_core' => false, 'sort_order' => 260],
            ['slug' => 'driver-dispatch', 'name' => 'Driver Dispatch', 'description' => 'Dispatch drivers to pickup and delivery locations', 'admin_section_key' => null, 'group' => 'logistics', 'is_core' => false, 'sort_order' => 270],
            ['slug' => 'file-uploads', 'name' => 'File Uploads', 'description' => 'Upload and manage files across features', 'admin_section_key' => 'file-library', 'group' => 'core', 'is_core' => false, 'sort_order' => 280],
            ['slug' => 'customer-messaging', 'name' => 'Customer Messaging', 'description' => 'Direct messaging between customers and businesses', 'admin_section_key' => null, 'group' => 'social', 'is_core' => false, 'sort_order' => 290],
            ['slug' => 'payment-splits', 'name' => 'Payment Splits', 'description' => 'Split payments between multiple parties', 'admin_section_key' => 'payments', 'group' => 'core', 'is_core' => false, 'sort_order' => 300],
            ['slug' => 'wallet-payouts', 'name' => 'Wallet Payouts', 'description' => 'Wallet balance payouts and withdrawals', 'admin_section_key' => 'payments', 'group' => 'core', 'is_core' => false, 'sort_order' => 310],
            ['slug' => 'financial-reports', 'name' => 'Financial Reports', 'description' => 'Financial reporting and analytics', 'admin_section_key' => 'payments', 'group' => 'core', 'is_core' => false, 'sort_order' => 320],
            ['slug' => 'ai-concierge-intake', 'name' => 'AI Concierge Intake', 'description' => 'AI-powered customer intake and triage', 'admin_section_key' => 'ai-concierge', 'group' => 'ai', 'is_core' => false, 'sort_order' => 330],
        ];

        foreach ($capabilities as $data) {
            UrbanGoodzCapability::firstOrCreate(
                ['slug' => $data['slug']],
                $data
            );
        }

        $defaultCapabilities = [
            'restaurant' => ['direct-checkout', 'public-listing', 'admin-managed', 'order-anywhere', 'menu-management', 'local-delivery'],
            'food_truck' => ['direct-checkout', 'public-listing', 'admin-managed', 'order-anywhere', 'menu-management', 'local-delivery'],
            'grocery_market' => ['direct-checkout', 'public-listing', 'admin-managed', 'order-anywhere', 'product-inventory', 'local-delivery'],
            'retail_shopping' => ['direct-checkout', 'public-listing', 'admin-managed', 'order-anywhere', 'product-inventory'],
            'beauty_supply_hair_provider' => ['direct-checkout', 'public-listing', 'admin-managed', 'order-anywhere', 'product-inventory', 'appointment-booking'],
            'service_provider' => ['book-anything', 'public-listing', 'admin-managed', 'appointment-booking', 'customer-messaging'],
            'rental_provider' => ['direct-checkout', 'public-listing', 'admin-managed', 'rental-inventory', 'rental-calendar', 'daily-rate-management', 'hourly-rate-management', 'deposit-management', 'renter-verification', 'pickup-return-management', 'damage-report-management'],
            'car_rental' => ['direct-checkout', 'public-listing', 'admin-managed', 'vehicle-inventory', 'rental-calendar', 'daily-rate-management', 'deposit-management', 'renter-verification', 'pickup-return-management', 'damage-report-management'],
            'vehicle_rental' => ['direct-checkout', 'public-listing', 'admin-managed', 'vehicle-inventory', 'rental-calendar', 'daily-rate-management', 'hourly-rate-management', 'deposit-management', 'renter-verification', 'pickup-return-management', 'damage-report-management'],
            'equipment_rental' => ['direct-checkout', 'public-listing', 'admin-managed', 'rental-inventory', 'rental-calendar', 'daily-rate-management', 'deposit-management', 'pickup-return-management', 'damage-report-management'],
            'event_vendor' => ['events', 'public-listing', 'admin-managed', 'order-anywhere', 'community-marketplace', 'payment-splits'],
            'courier' => ['direct-checkout', 'public-listing', 'admin-managed', 'logistics', 'local-delivery', 'driver-dispatch', 'proof-of-pickup', 'proof-of-delivery'],
            'medical_courier' => ['medical-courier', 'public-listing', 'admin-managed', 'driver-dispatch', 'proof-of-pickup', 'proof-of-delivery'],
            'creator' => ['creator-commerce', 'public-listing', 'admin-managed', 'order-anywhere', 'community-marketplace', 'events', 'file-uploads'],
            'fashion_fit_provider' => ['fashion-fit', 'public-listing', 'admin-managed', 'order-anywhere', 'ai-concierge', 'book-anything', 'appointment-booking', 'file-uploads'],
        ];

        foreach ($defaultCapabilities as $typeSlug => $capSlugs) {
            $type = UrbanGoodzBusinessType::where('slug', $typeSlug)->first();
            if (!$type) continue;

            foreach ($capSlugs as $capSlug) {
                $cap = UrbanGoodzCapability::where('slug', $capSlug)->first();
                if (!$cap) continue;

                $isRequired = in_array($capSlug, ['direct-checkout', 'public-listing', 'admin-managed']);

                $type->capabilities()->syncWithoutDetaching([
                    $cap->id => ['is_required' => $isRequired],
                ]);
            }
        }
    }
}
