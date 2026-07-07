<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\UrbanGoodzSourcedBusiness;
use App\Models\UrbanGoodzSourcedProduct;
use App\Models\UrbanGoodzSourcedImage;
use App\Models\Module;
use App\Models\Zone;
use Illuminate\Support\Str;

class UrbanGoodzIngestionSeeder extends Seeder
{
    public function run()
    {
        $categories = [
            'Restaurants',
            'Food Trucks',
            'Grocery / Markets',
            'Retail / Shopping',
            'Beauty Supply / Hair Providerz',
            'Pharmacy / Health',
            'Liquor / Beveragez',
            'THC / CBD',
            'Home-Based Businessz',
            'Local Events / Creators',
            'Car Rentalz',
            'Equipment Rentalz',
            'Courier / Parcel',
            'Medical Courier',
            'Professional Services',
            'Fashion Fit',
            'Creator Commerce',
            'Order Anywhere',
            'Book Anything / Services',
            'Logistics / Load Board'
        ];

        $cities = [
            ['name' => 'Houston', 'state' => 'TX', 'country' => 'US', 'is_tx' => true],
            ['name' => 'Atlanta', 'state' => 'GA', 'country' => 'US', 'is_tx' => false],
            ['name' => 'Los Angeles', 'state' => 'CA', 'country' => 'US', 'is_tx' => false],
        ];

        // Ensure modules exist
        foreach ($categories as $cat) {
            Module::firstOrCreate(
                ['module_name' => $cat],
                [
                    'module_type' => $this->getModuleType($cat),
                    'status' => 1,
                    'theme' => 'default',
                    'slug' => Str::slug($cat)
                ]
            );
        }

        // Ensure default Zone exists
        $zone = Zone::firstOrCreate(
            ['name' => 'Houston'],
            [
                'display_name' => 'Houston Zone',
                'status' => 1,
                'is_default' => true,
                'coordinates' => new \MatanYadaev\EloquentSpatial\Objects\Polygon([
                    new \MatanYadaev\EloquentSpatial\Objects\LineString([
                        new \MatanYadaev\EloquentSpatial\Objects\Point(29.5, -95.8),
                        new \MatanYadaev\EloquentSpatial\Objects\Point(30.2, -95.8),
                        new \MatanYadaev\EloquentSpatial\Objects\Point(30.2, -95.1),
                        new \MatanYadaev\EloquentSpatial\Objects\Point(29.5, -95.1),
                        new \MatanYadaev\EloquentSpatial\Objects\Point(29.5, -95.8),
                    ])
                ])
            ]
        );

        foreach ($cities as $city) {
            $countPerCategory = $city['is_tx'] ? 10 : 5;

            foreach ($categories as $cat) {
                $module = Module::where('module_name', $cat)->first();

                for ($i = 1; $i <= $countPerCategory; $i++) {
                    $businessName = $this->generateBusinessName($cat, $city['name'], $i);
                    $slug = Str::slug($businessName) . '-' . Str::random(4);

                    $b = UrbanGoodzSourcedBusiness::create([
                        'name' => $businessName,
                        'slug' => $slug,
                        'display_name' => $businessName,
                        'description' => "{$businessName} is a premier provider of {$cat} in {$city['name']}, {$city['state']}. Sourced by Urban Goodz from public listings.",
                        'short_description' => "Premium {$cat} in {$city['name']}.",
                        'business_type' => Str::slug($cat),
                        'module_id' => $module ? $module->id : null,
                        'module_name' => $cat,
                        'category_ids' => [1],
                        'tags' => [$cat, 'Local', $city['name']],
                        'phone' => $city['is_tx'] ? "713-555-" . sprintf("%04d", 1000 + $i) : "404-555-" . sprintf("%04d", 2000 + $i),
                        'email' => "contact@" . Str::slug($businessName) . ".com",
                        'website' => "https://www." . Str::slug($businessName) . ".com",
                        'social_links' => [
                            'instagram' => "https://instagram.com/" . Str::slug($businessName),
                            'facebook' => "https://facebook.com/" . Str::slug($businessName)
                        ],
                        'address' => "{$i}00 Main Street, {$city['name']}, {$city['state']}",
                        'city' => $city['name'],
                        'state' => $city['state'],
                        'country_code' => $city['country'],
                        'zip' => $city['is_tx'] ? '77002' : '30303',
                        'latitude' => $city['is_tx'] ? '29.7604' : '33.7490',
                        'longitude' => $city['is_tx'] ? '-95.3698' : '-84.3880',
                        'zone_id' => $zone->id,
                        'zone_name' => $zone->name,
                        'is_launch_market' => $city['is_tx'],
                        'is_nationwide' => false,
                        'is_worldwide' => false,
                        'is_black_owned' => ($i % 2 === 0), // 50% Black-owned representation
                        'is_woman_owned' => ($i % 3 === 0),
                        'is_local_business' => true,
                        'fulfillment_modes' => ['delivery', 'pickup', 'order_anywhere'],
                        'onboarding_status' => 'public_sourced',
                        'source_status' => 'ai_sourced',
                        'source_urls' => ["https://google.com/search?q=" . urlencode($businessName)],
                        'data_confidence_score' => 85,
                        'demand_score' => rand(0, 10),
                    ]);

                    // Add Sourced Products
                    $productNames = $this->generateProductNames($cat, $businessName);
                    foreach ($productNames as $prodName) {
                        UrbanGoodzSourcedProduct::create([
                            'sourced_business_id' => $b->id,
                            'module_id' => $b->module_id,
                            'name' => $prodName,
                            'slug' => Str::slug($prodName) . '-' . Str::random(4),
                            'short_description' => "Genuine {$prodName} offered by {$businessName}.",
                            'full_description' => "Request a quote for {$prodName} from {$businessName} via Urban Goodz Order Anywhere.",
                            'price' => rand(15, 120),
                            'price_type' => 'fixed',
                            'currency' => 'USD',
                            'stock_status' => 'in_stock',
                            'item_type' => $this->getItemType($cat),
                            'requires_quote' => false,
                            'requires_admin_review' => true,
                            'is_active' => false,
                            'is_public' => false,
                        ]);
                    }

                    // Add Sourced Image
                    UrbanGoodzSourcedImage::create([
                        'entity_type' => 'business',
                        'entity_id' => $b->id,
                        'image_url' => "/assets/images/urban_goodz/fallbacks/" . Str::slug($cat) . ".png",
                        'rights_status' => 'generated_placeholder',
                        'review_status' => 'pending'
                    ]);
                }
            }
        }
    }

    private function getModuleType($category): string
    {
        return match ($category) {
            'Restaurants', 'Food Trucks' => 'food',
            'Grocery / Markets' => 'grocery',
            'Pharmacy / Health' => 'pharmacy',
            'Car Rentalz', 'Equipment Rentalz' => 'rental',
            'Courier / Parcel', 'Medical Courier', 'Logistics / Load Board' => 'parcel',
            default => 'ecommerce',
        };
    }

    private function getItemType($category): string
    {
        return match ($category) {
            'Restaurants', 'Food Trucks' => 'food',
            'Car Rentalz', 'Equipment Rentalz' => 'rental',
            'Courier / Parcel', 'Medical Courier', 'Logistics / Load Board' => 'courier',
            'Book Anything / Services', 'Professional Services' => 'service',
            default => 'product',
        };
    }

    private function generateBusinessName($category, $city, $index): string
    {
        $prefixes = [
            'Restaurants' => ['Flavor Palace', 'Tasty Bites', 'Southern Kitchen', 'Green Garden Table', 'Corner Cafe', 'The Bistro Hub', 'Noodle Craft', 'Smoked BBQ Co.', 'Bespoke Platters', 'The Daily Grind'],
            'Food Trucks' => ['Taco Wheels', 'Slide & Ride Burgers', 'Rolling Crepes', 'Wok on Wheels', 'The Waffle Rig', 'H-Town Grillers', 'Curry Cruisers', 'Boba Express Truck', 'Vegan Voyage', 'Spicy Grill Rollers'],
            'Grocery / Markets' => ['Cornerstone Grocers', 'Fresh Pick Market', 'Urban Pantry', 'Heritage Bodega', 'Sunrise Organics', 'Green Grocer Depot', 'Midtown Mart', 'Family Pride Foods', 'Nature\'s Basket', 'The Neighborhood Deli'],
            'Retail / Shopping' => ['Boutique 713', 'Main Street Apparel', 'Urban Closet', 'Trendsetters Depot', 'Style & Grace', 'Modern Haberdashery', 'The Gift Box', 'Luxe Living Essentials', 'Sneaker Spot', 'Vintage Threads'],
            'Beauty Supply / Hair Providerz' => ['Classic Crown Supplies', 'Melanin Glow Skincare', 'Urban Tresses Braiding', 'Elite Wig Salon', 'H-Town Barber Depot', 'Velvet Edge Control', 'Luxe Lash & Beauty', 'Royal Hair Weaves', 'Organic Glow Cosmetics', 'Beauty Emporium'],
            'Pharmacy / Health' => ['Community Care Pharmacy', 'Heights Health Hub', 'Wellness Point', 'Nature\'s Apothecary', 'Prime Care Meds', 'Express Pharmacy', 'Vibrant Life Herbs', 'Central Apothecary', 'Shield Health Supplies', 'First Choice Pharmacy'],
            'Liquor / Beveragez' => ['Cask & Key Liquors', 'Midtown Wine Cellars', 'Brews & Spirits Depot', 'Cheers Wine & Spirits', 'The Bottle Shop', 'Heights Liquor Hub', 'Sunset Beveragez', 'Grapevine Cellars', 'Premium Bottle Co.', 'Liquid Gold Spirits'],
            'THC / CBD' => ['Green Leaf Wellness', 'CannaBliss Depot', 'Urban Hemp Co.', 'Nature\'s Relief CBD', 'The Joint Dispensary', 'Green Relief Spot', 'Botanical Healing CBD', 'Elevate Wellness', 'High Integrity Hemp', 'Holistic Canna Co.'],
            'Home-Based Businessz' => ['Sweet Treats Bakery', 'Crafted Comforts Co.', 'Stitch & Sew Designs', 'The Soap Artisan', 'Made with Love Cakes', 'Handmade Haven Co.', 'Artisanal Candle Lab', 'Crochet & Co.', 'Petal & Stem Floral', 'Custom Woodworks'],
            'Local Events / Creators' => ['Creative Pop-up Collective', 'H-Town Artisan Markets', 'Community Creators Expo', 'Neighborhood Art Walk', 'Sunset Plaza Markets', 'Local Creator Hub', 'Bayou City Festivals', 'Heights Maker Market', 'Indie Creator Showcase', 'Urban Night Markets'],
            'Car Rentalz' => ['Drive Town Rentals', 'H-Town Exotic Rides', 'Eco-Drive Car Share', 'Budget Wheels Co.', 'Cruisin Car Rentals', 'Midtown Autos', 'Select Luxury Rides', 'Dependable Drive rentals', 'Urban Roadsters', 'Metro Car Rentalz'],
            'Equipment Rentalz' => ['Pro Tool Rentals', 'Heights Party Rentals', 'Heavy Duty Equipment', 'Event Production Gear', 'Midtown Sound & Light', 'Urban Tool Share', 'Reliable Rental Depot', 'Construction Masters', 'Builders Equipment Co.', 'Houston Stage & Sound'],
            'Courier / Parcel' => ['Swift Delivery Co.', 'H-Town Courier Express', 'Metro Parcel Service', 'Downtown Delivery Boys', 'Zip Courier Service', 'Urban Dispatchers', 'Red Line Logistics', 'Apex Delivery Systems', 'Rocket Parcels', 'Lone Star Couriers'],
            'Medical Courier' => ['Safe Guard Lab Logistics', 'Medi-Transport Systems', 'H-Town Specimen Courier', 'Rx Express Delivery', 'LifeLine Medical Courier', 'Med-Route Express', 'CarePath Logistics', 'Apex Lab Courier', 'Precision Health Deliveries', 'Priority Medical Transport'],
            'Professional Services' => ['Tax Solutions Group', 'Metro Notary Services', 'Bayou City Consultants', 'Heights Design Lab', 'Urban Legal Assistance', 'Pro Clean Services', 'H-Town Digital Marketing', 'Midtown Copy & Print', 'Apex Business Services', 'Lone Star Bookkeepers'],
            'Fashion Fit' => ['Custom Tailors 713', 'Alteration Masters', 'Bespoke Suits Co.', 'The Wedding Fitter', 'Heights Tailoring', 'Urban Fit Alterations', 'Perfect Hem Designs', 'Stitch & Style Tailors', 'Fashion Fit Alterations', 'Elite Tailors H-Town'],
            'Creator Commerce' => ['Vanguard Streetwear', 'H-Town Creator Merch', 'Subtle Flex Apparel', 'Urban Icon Designs', 'The Creator Vault', 'Iconic Drops Co.', 'Limited Edition Merch', 'Streets & Threads', 'Bold Statement Apparel', 'Bayou Creator Studio'],
            'Order Anywhere' => ['Order Anything Concierge', 'Personal Shopper Hub', 'Urban Goods Runner', 'H-Town Errand Boys', 'Universal Sourcing Group', 'The Shopping Agent', 'Quick Fetch Services', 'Anywhere Delivery Hub', 'Custom Sourcing Pros', 'Urban Request Runners'],
            'Book Anything / Services' => ['Home Care Pro Booking', 'Urban Handyman Services', 'Midtown Cleaning Booking', 'Bayou Appliance Repairs', 'Heights Lawn Care', 'Apex Event Planners', 'Elite Auto Detailers', 'Comfort Zone HVAC', 'Pro Paint Services', 'Modern Plumbers Booking'],
            'Logistics / Load Board' => ['Load Board Logistics', 'Freight Brokerage H-Town', 'Apex Load Board', 'Metro Cargo Handlers', 'Midtown Freight Services', 'Urban Load Sourcing', 'Interstate Load Board', 'Lone Star Freight Kings', 'Red Line Cargo', 'Bayou City Logistics']
        ];

        $prefixList = $prefixes[$category] ?? ['Local Business'];
        $name = $prefixList[($index - 1) % count($prefixList)];
        return "{$name} {$city}";
    }

    private function generateProductNames($category, $businessName): array
    {
        return match ($category) {
            'Restaurants', 'Food Trucks' => ['Classic Dish Combo', 'Signature Appetizer', 'Special House Beverage', 'Gourmet Side Order'],
            'Car Rentalz', 'Equipment Rentalz' => ['Standard Daily Rental', 'Premium Weekend Package', 'Weekly Extended Rental'],
            'Courier / Parcel', 'Medical Courier', 'Logistics / Load Board' => ['Same-Day Local Delivery', 'Priority Overnight Delivery', 'Standard Route Sourcing'],
            'Book Anything / Services', 'Professional Services', 'Fashion Fit' => ['Initial Consultation Service', 'Standard Service Request', 'Full Bespoke Implementation'],
            default => ['Basic Starter Kit', 'Standard Essential Pack', 'Premium Luxury Selection'],
        };
    }
}
