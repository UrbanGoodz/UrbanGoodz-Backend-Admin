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
        // Every business this seeder creates is invented (~1,900 of them, with
        // randomised Black-/woman-owned flags). That is fine for a dev database
        // and never acceptable on the live marketplace, so it refuses there.
        // It also runs from DatabaseSeeder, so a plain `db:seed` would hit it.
        if (app()->environment('production') || config('app.url') === 'https://admin.urbangoodzdelivery.com') {
            $this->command?->warn('UrbanGoodzIngestionSeeder skipped: it creates fictional businesses and must not run against production.');
            return;
        }

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

        // ── Market Concentration Config ───────────────────────────────────────
        // TX Gulf Coast + major metros = highest density.
        // Non-TX markets are present but lighter.
        $cities = [
            // ── PRIMARY TX MARKETS (highest concentration) ────────────────────
            [
                'name'         => 'Houston',
                'state'        => 'TX',
                'country'      => 'US',
                'is_tx'        => true,
                'count'        => 25,   // 25 businesses per category
                'area_code'    => '713',
                'zip'          => '77002',
                'latitude'     => '29.7604',
                'longitude'    => '-95.3698',
                'zone_name'    => 'Houston',
            ],
            [
                'name'         => 'Dallas',
                'state'        => 'TX',
                'country'      => 'US',
                'is_tx'        => true,
                'count'        => 20,
                'area_code'    => '214',
                'zip'          => '75201',
                'latitude'     => '32.7767',
                'longitude'    => '-96.7970',
                'zone_name'    => 'Dallas',
            ],
            [
                'name'         => 'Austin',
                'state'        => 'TX',
                'country'      => 'US',
                'is_tx'        => true,
                'count'        => 15,
                'area_code'    => '512',
                'zip'          => '78701',
                'latitude'     => '30.2672',
                'longitude'    => '-97.7431',
                'zone_name'    => 'Austin',
            ],
            [
                'name'         => 'Galveston',
                'state'        => 'TX',
                'country'      => 'US',
                'is_tx'        => true,
                'count'        => 12,
                'area_code'    => '409',
                'zip'          => '77550',
                'latitude'     => '29.3013',
                'longitude'    => '-94.7977',
                'zone_name'    => 'Galveston',
            ],
            [
                'name'         => 'Brazoria County',
                'state'        => 'TX',
                'country'      => 'US',
                'is_tx'        => true,
                'count'        => 12,
                'area_code'    => '979',
                'zip'          => '77515',
                'latitude'     => '29.1644',
                'longitude'    => '-95.4350',
                'zone_name'    => 'Brazoria',
            ],
            [
                'name'         => 'Matagorda County',
                'state'        => 'TX',
                'country'      => 'US',
                'is_tx'        => true,
                'count'        => 10,
                'area_code'    => '979',
                'zip'          => '77414',
                'latitude'     => '28.9951',
                'longitude'    => '-96.0017',
                'zone_name'    => 'Matagorda',
            ],
            // ── SECONDARY MARKETS (lighter presence) ─────────────────────────
            [
                'name'         => 'Atlanta',
                'state'        => 'GA',
                'country'      => 'US',
                'is_tx'        => false,
                'count'        => 3,
                'area_code'    => '404',
                'zip'          => '30303',
                'latitude'     => '33.7490',
                'longitude'    => '-84.3880',
                'zone_name'    => 'Houston',   // default zone until ATL zone exists
            ],
            [
                'name'         => 'Los Angeles',
                'state'        => 'CA',
                'country'      => 'US',
                'is_tx'        => false,
                'count'        => 3,
                'area_code'    => '213',
                'zip'          => '90012',
                'latitude'     => '34.0522',
                'longitude'    => '-118.2437',
                'zone_name'    => 'Houston',   // default zone until LA zone exists
            ],
        ];


        // Ensure modules exist
        foreach ($categories as $cat) {
            Module::firstOrCreate(
                ['module_name' => $cat],
                [
                    'module_type' => $this->getModuleType($cat),
                    'status' => 1,
                ]
            );
        }

        // ── Ensure a Zone record exists for every TX market ───────────────────
        // Each city gets its own zone polygon. Non-TX cities fall back to
        // the Houston zone until their own zones are created in the admin.
        $zonePolygons = [
            'Houston'   => [[29.5, -95.8], [30.2, -95.8], [30.2, -95.1], [29.5, -95.1], [29.5, -95.8]],
            'Dallas'    => [[32.5, -97.2], [33.2, -97.2], [33.2, -96.4], [32.5, -96.4], [32.5, -97.2]],
            'Austin'    => [[30.0, -98.1], [30.6, -98.1], [30.6, -97.5], [30.0, -97.5], [30.0, -98.1]],
            'Galveston' => [[29.1, -95.1], [29.5, -95.1], [29.5, -94.5], [29.1, -94.5], [29.1, -95.1]],
            'Brazoria'  => [[28.9, -95.7], [29.4, -95.7], [29.4, -95.1], [28.9, -95.1], [28.9, -95.7]],
            'Matagorda' => [[28.7, -96.4], [29.2, -96.4], [29.2, -95.8], [28.7, -95.8], [28.7, -96.4]],
        ];

        $zoneCache = [];
        $defaultZone = Zone::firstOrCreate(
            ['name' => 'Houston'],
            ['status' => 1, 'coordinates' => $this->makePolygon($zonePolygons['Houston'])]
        );
        $zoneCache['Houston'] = $defaultZone;

        foreach ($zonePolygons as $zoneName => $pts) {
            if ($zoneName === 'Houston') continue;
            $zoneCache[$zoneName] = Zone::firstOrCreate(
                ['name' => $zoneName],
                ['status' => 1, 'coordinates' => $this->makePolygon($pts)]
            );
        }

        foreach ($cities as $city) {
            $countPerCategory = $city['count'];
            $zone = $zoneCache[$city['zone_name']] ?? $defaultZone;

            foreach ($categories as $cat) {
                $module = Module::where('module_name', $cat)->first();

                for ($i = 1; $i <= $countPerCategory; $i++) {
                    $businessName = $this->generateBusinessName($cat, $city['name'], $i);
                    $slug = Str::slug($businessName) . '-' . Str::random(4);

                    // Scatter lat/lng slightly within the city so businesses
                    // don't all stack on the exact same coordinate.
                    $latJitter  = (rand(-50, 50) / 10000);
                    $lngJitter  = (rand(-50, 50) / 10000);

                    $b = UrbanGoodzSourcedBusiness::create([
                        'name'              => $businessName,
                        'slug'              => $slug,
                        'display_name'      => $businessName,
                        'description'       => "{$businessName} is a sample {$cat} business in {$city['name']}, {$city['state']} (development seed data).",
                        'short_description' => "Premium {$cat} in {$city['name']}.",
                        'business_type'     => Str::slug($cat),
                        'module_id'         => $module ? $module->id : null,
                        'module_name'       => $cat,
                        'category_ids'      => [1],
                        'tags'              => [$cat, 'Local', $city['name'], $city['state']],
                        'phone'             => $city['area_code'] . '-555-' . sprintf('%04d', (1000 + $i) % 9000 + 1000),
                        // .example is reserved (RFC 2606); a made-up .com can be a real stranger's domain.
                        'email'             => 'contact@' . Str::slug($businessName) . '.example',
                        'website'           => 'https://www.' . Str::slug($businessName) . '.example',
                        'social_links'      => [
                            'instagram' => 'https://instagram.com/' . Str::slug($businessName),
                            'facebook'  => 'https://facebook.com/' . Str::slug($businessName),
                        ],
                        'address'           => "{$i}00 Main Street, {$city['name']}, {$city['state']}",
                        'city'              => $city['name'],
                        'state'             => $city['state'],
                        'country_code'      => $city['country'],
                        'zip'               => $city['zip'],
                        'latitude'          => (string)((float)$city['latitude']  + $latJitter),
                        'longitude'         => (string)((float)$city['longitude'] + $lngJitter),
                        'zone_id'           => $zone->id,
                        'zone_name'         => $zone->name,
                        'is_launch_market'  => $city['is_tx'],
                        'is_nationwide'     => false,
                        'is_worldwide'      => false,
                        'is_black_owned'    => ($i % 2 === 0),  // ~50 % Black-owned representation
                        'is_woman_owned'    => ($i % 3 === 0),
                        'is_local_business' => true,
                        'fulfillment_modes' => ['delivery', 'pickup', 'order_anywhere'],
                        'onboarding_status' => 'public_sourced',
                        'source_status'     => 'ai_sourced',
                        'source_urls'       => ['https://google.com/search?q=' . urlencode($businessName)],
                        'data_confidence_score' => 85,
                        'demand_score'      => rand(0, 10),
                    ]);

                    // Add Sourced Products
                    $productNames = $this->generateProductNames($cat, $businessName);
                    foreach ($productNames as $prodName) {
                        UrbanGoodzSourcedProduct::create([
                            'sourced_business_id' => $b->id,
                            'module_id' => $b->module_id,
                            'name' => $prodName,
                            'slug' => Str::slug($prodName) . '-' . Str::random(4),
                            'short_description' => "Sample {$prodName} from {$businessName}.",
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

    private function makePolygon(array $points): \MatanYadaev\EloquentSpatial\Objects\Polygon
    {
        $spatialPoints = array_map(
            fn($pt) => new \MatanYadaev\EloquentSpatial\Objects\Point($pt[0], $pt[1]),
            $points
        );
        return new \MatanYadaev\EloquentSpatial\Objects\Polygon([
            new \MatanYadaev\EloquentSpatial\Objects\LineString($spatialPoints),
        ]);
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
        // City-specific name flavoring for the TX primary markets.
        $cityTag = match (true) {
            str_contains($city, 'Houston')   => ['H-Town', 'Bayou City', 'Space City', 'Houston'],
            str_contains($city, 'Dallas')    => ['Big D', 'Metroplex', 'Oak Cliff', 'Dallas'],
            str_contains($city, 'Austin')    => ['ATX', 'Keep Austin', 'Capitol City', 'Austin'],
            str_contains($city, 'Galveston') => ['Island', 'Seawall', 'Gulf Coast', 'Galveston'],
            str_contains($city, 'Brazoria')  => ['Brazoria', 'Brazos', 'Gulf Prairie', 'Bay Area'],
            str_contains($city, 'Matagorda') => ['Bay City', 'Matagorda', 'Coastal Bend', 'Gulf Bend'],
            default                          => [$city],
        };
        $tag = $cityTag[$index % count($cityTag)];

        $prefixes = [
            'Restaurants'                => ['Flavor Palace', 'Tasty Bites', 'Southern Kitchen', 'Green Garden Table', 'Corner Cafe', 'The Bistro Hub', 'Noodle Craft', 'Smoked BBQ Co.', 'Bespoke Platters', 'The Daily Grind', 'Gulf Smokehouse', 'Pecan Street Kitchen', 'Coastal Grill', 'The Pit Stop Diner', 'Heritage Table', 'Lone Star Kitchen', 'Tex-Mex Corner', 'Bayou Eats', 'Island Grill', 'Prairie Kitchen', 'Market Street Cafe', 'The Iron Skillet', 'Pearl St Bistro', 'The Social Table', 'Creek Side Eatery'],
            'Food Trucks'                => ['Taco Wheels', 'Slide & Ride Burgers', 'Rolling Crepes', 'Wok on Wheels', 'The Waffle Rig', 'Grillers Mobile', 'Curry Cruisers', 'Boba Express Truck', 'Vegan Voyage', 'Spicy Grill Rollers', 'Gulf Coast Tacos', 'The BBQ Wagon', 'Bayou Bites Truck', 'Island Flavors Truck', 'Prairie Smokehouse Truck', 'Kolache Kart', 'Breakfast Bus', 'Street Eats TX', 'The Lobster Truck', 'Coastal Crepe Truck', 'Fusion Wheels', 'Night Market Truck', 'The Sausage Rig', 'Rolling Ramen', 'Coastal Catch Truck'],
            'Grocery / Markets'          => ['Cornerstone Grocers', 'Fresh Pick Market', 'Urban Pantry', 'Heritage Bodega', 'Sunrise Organics', 'Green Grocer Depot', 'Midtown Mart', 'Family Pride Foods', 'Nature\'s Basket', 'The Neighborhood Deli', 'Gulf Coast Market', 'Lone Star Grocery', 'Prairie Fresh Market', 'Coastal Provisions', 'Island Pantry', 'The Corner Bodega', 'Bayou Fresh Mart', 'Texas Farm Stand', 'Coast to Coast Foods', 'Harvest Market', 'The General Store', 'Creekside Market', 'Inland Fresh', 'Laguna Market', 'Bay City Grocers'],
            'Retail / Shopping'          => ['Boutique 713', 'Main Street Apparel', 'Urban Closet', 'Trendsetters Depot', 'Style & Grace', 'Modern Haberdashery', 'The Gift Box', 'Luxe Living Essentials', 'Sneaker Spot', 'Vintage Threads', 'Gulf Style Boutique', 'Lone Star Retail', 'ATX Fashion Loft', 'Island Boutique', 'Prairie Style Co.', 'Bayou Streetwear', 'The Coastal Closet', 'Prairie Finds', 'The Treasure Chest', 'Gulf Goods Co.', 'Heritage Retail', 'TX Threads', 'The Style District', 'Bayside Boutique', 'Pearl Market Co.'],
            'Beauty Supply / Hair Providerz' => ['Classic Crown Supplies', 'Melanin Glow Skincare', 'Urban Tresses Braiding', 'Elite Wig Salon', 'Barber Depot', 'Velvet Edge Control', 'Luxe Lash & Beauty', 'Royal Hair Weaves', 'Organic Glow Cosmetics', 'Beauty Emporium', 'Gulf Coast Braids', 'Lone Star Beauty Supply', 'ATX Glow Studio', 'Island Hair Boutique', 'Prairie Beauty Bar', 'Coastal Lash Studio', 'The Crown Room', 'Melanin Magic Beauty', 'Soft Waves Salon', 'Style & Soul Beauty', 'Heritage Barber Co.', 'Bayou Braids Studio', 'Natural Roots Salon', 'Gulf Glow Beauty', 'The Beauty Lab TX'],
            'Pharmacy / Health'          => ['Community Care Pharmacy', 'Heights Health Hub', 'Wellness Point', 'Nature\'s Apothecary', 'Prime Care Meds', 'Express Pharmacy', 'Vibrant Life Herbs', 'Central Apothecary', 'Shield Health Supplies', 'First Choice Pharmacy', 'Gulf Coast Health', 'Lone Star Wellness', 'ATX Apothecary', 'Island Health Hub', 'Prairie Health Center', 'Coastal Wellness Pharmacy', 'Bay City Drugs', 'Bayou Health Store', 'TX Herb & Wellness', 'The Healing Dispensary', 'Gulf Pharmacy Pro', 'Matagorda Meds', 'Heritage Health Hub', 'Creekside Pharmacy', 'Natural Wellness TX'],
            'Liquor / Beveragez'         => ['Cask & Key Liquors', 'Midtown Wine Cellars', 'Brews & Spirits Depot', 'Cheers Wine & Spirits', 'The Bottle Shop', 'Liquor Hub', 'Sunset Beveragez', 'Grapevine Cellars', 'Premium Bottle Co.', 'Liquid Gold Spirits', 'Gulf Coast Spirits', 'Lone Star Liquor', 'ATX Craft Cellars', 'Island Spirits', 'Prairie Wine & Spirits', 'Coastal Cellar', 'Bay City Liquors', 'Texas Craft Bottles', 'The Speakeasy TX', 'Bayou Cellar', 'Gulf Seltzer & More', 'Fine Wine TX', 'Craft Can Depot', 'Creekside Spirits', 'The Brew House TX'],
            'THC / CBD'                  => ['Green Leaf Wellness', 'CannaBliss Depot', 'Urban Hemp Co.', 'Nature\'s Relief CBD', 'The Joint Dispensary', 'Green Relief Spot', 'Botanical Healing CBD', 'Elevate Wellness', 'High Integrity Hemp', 'Holistic Canna Co.', 'Gulf Coast Hemp', 'Lone Star CBD', 'ATX Wellness Depot', 'Island Canna Co.', 'Prairie Hemp Collective', 'Coastal CBD Shop', 'Bayou Botanicals', 'TX Hemp Authority', 'The Herb Cabinet', 'Canna Culture TX', 'Gulf Green Wellness', 'Bay City Hemp', 'Terpene Trail TX', 'Roots & Remedy CBD', 'The Relief Room TX'],
            'Home-Based Businessz'       => ['Sweet Treats Bakery', 'Crafted Comforts Co.', 'Stitch & Sew Designs', 'The Soap Artisan', 'Made with Love Cakes', 'Handmade Haven Co.', 'Artisanal Candle Lab', 'Crochet & Co.', 'Petal & Stem Floral', 'Custom Woodworks', 'Gulf Coast Crafts', 'Lone Star Home Baking', 'ATX Artisan Studio', 'Island Made Co.', 'Prairie Petal Designs', 'Coastal Soap Works', 'Bayou Stitch Studio', 'Texas Home Goods', 'The Craft Cottage', 'Candle & Clay TX', 'Gulf Bloom Floral', 'Heritage Handmade', 'Backyard Botanicals', 'Creekside Crafts', 'Bay City Made'],
            'Local Events / Creators'    => ['Creative Pop-up Collective', 'Artisan Markets', 'Community Creators Expo', 'Neighborhood Art Walk', 'Sunset Plaza Markets', 'Local Creator Hub', 'Festivals TX', 'Maker Market', 'Indie Creator Showcase', 'Urban Night Markets', 'Gulf Coast Events', 'Lone Star Pop-ups', 'ATX Creator Market', 'Island Festival Co.', 'Prairie Art Walk', 'Coastal Night Market', 'Bayou Collective', 'TX Creator Expo', 'The Art Bazaar', 'Open Mic TX', 'Gulf Outdoor Market', 'Heritage Market TX', 'Bay City Arts', 'Creative Junction TX', 'Creekside Arts Collective'],
            'Car Rentalz'                => ['Drive Town Rentals', 'Exotic Rides', 'Eco-Drive Car Share', 'Budget Wheels Co.', 'Cruisin Car Rentals', 'Midtown Autos', 'Select Luxury Rides', 'Dependable Drive Rentals', 'Urban Roadsters', 'Metro Car Rentalz', 'Gulf Coast Rides', 'Lone Star Auto Rental', 'ATX Wheels', 'Island Car Rentals', 'Prairie Drives', 'Coastal Auto Share', 'Bayou Rental Cars', 'TX Fleet Rentals', 'The Car Lot TX', 'Auto Escape TX', 'Gulf Drive Luxury', 'Heritage Auto Rental', 'Bay City Autos', 'Prairie Fleet Co.', 'Creekside Rides TX'],
            'Equipment Rentalz'          => ['Pro Tool Rentals', 'Party Rentals', 'Heavy Duty Equipment', 'Event Production Gear', 'Sound & Light', 'Urban Tool Share', 'Reliable Rental Depot', 'Construction Masters', 'Builders Equipment Co.', 'Stage & Sound', 'Gulf Coast Equipment', 'Lone Star Tool Rental', 'ATX Event Rentals', 'Island Party Gear', 'Prairie Equipment Co.', 'Coastal Stage Rentals', 'Bayou Tool Share', 'TX Construction Rental', 'The Equipment Yard', 'Heavy Lift TX', 'Gulf Sound & Stage', 'Heritage Equipment', 'Bay City Rentals', 'Drill & Build TX', 'Creekside Tool Co.'],
            'Courier / Parcel'           => ['Swift Delivery Co.', 'Courier Express', 'Metro Parcel Service', 'Downtown Delivery Boys', 'Zip Courier Service', 'Urban Dispatchers', 'Red Line Logistics', 'Apex Delivery Systems', 'Rocket Parcels', 'Lone Star Couriers', 'Gulf Coast Courier', 'TX Parcel Pro', 'ATX Delivery', 'Island Courier', 'Prairie Delivery Co.', 'Coastal Parcel Service', 'Bayou Courier', 'TX Express Dispatch', 'Same Day TX', 'The Delivery Guild TX', 'Gulf Freight Express', 'Heritage Couriers', 'Bay City Delivery', 'Direct Dispatch TX', 'Creekside Courier'],
            'Medical Courier'            => ['Safe Guard Lab Logistics', 'Medi-Transport Systems', 'Specimen Courier', 'Rx Express Delivery', 'LifeLine Medical Courier', 'Med-Route Express', 'CarePath Logistics', 'Apex Lab Courier', 'Precision Health Deliveries', 'Priority Medical Transport', 'Gulf Coast Med Courier', 'TX BioLogistics', 'ATX Lab Runs', 'Island Medical Courier', 'Prairie Rx Express', 'Coastal Health Logistics', 'Bayou Medical Dispatch', 'TX Specimen Transport', 'The Med Carrier TX', 'BioRoute TX', 'Gulf Path Medical', 'Heritage Health Logistics', 'Bay City Med Courier', 'Critical Care Courier TX', 'Creekside Lab Express'],
            'Professional Services'      => ['Tax Solutions Group', 'Notary Services', 'Bayou Consultants', 'Design Lab', 'Urban Legal Assistance', 'Pro Clean Services', 'Digital Marketing TX', 'Copy & Print', 'Apex Business Services', 'Lone Star Bookkeepers', 'Gulf Coast Accounting', 'TX Business Consulting', 'ATX Marketing Group', 'Island Pro Services', 'Prairie Legal Group', 'Coastal Business Hub', 'Bayou Strategy Group', 'TX Tax Pros', 'Notary & More TX', 'Creative Agency TX', 'Gulf Compliance Group', 'Heritage Bookkeeping', 'Bay City Consulting', 'Lone Star Legal', 'Creekside Business Services'],
            'Fashion Fit'                => ['Custom Tailors 713', 'Alteration Masters', 'Bespoke Suits Co.', 'The Wedding Fitter', 'Heights Tailoring', 'Urban Fit Alterations', 'Perfect Hem Designs', 'Stitch & Style Tailors', 'Fashion Fit Alterations', 'Elite Tailors', 'Gulf Coast Tailors', 'TX Bespoke', 'ATX Stitch Studio', 'Island Fashion Fit', 'Prairie Seamstress', 'Coastal Alterations', 'Bayou Couture', 'TX Fabric & Thread', 'The Fitting Room TX', 'Perfect Fit TX', 'Gulf Hem & Stitch', 'Heritage Tailors', 'Bay City Tailoring', 'Style Lab TX', 'Creekside Couture'],
            'Creator Commerce'           => ['Vanguard Streetwear', 'Creator Merch', 'Subtle Flex Apparel', 'Urban Icon Designs', 'The Creator Vault', 'Iconic Drops Co.', 'Limited Edition Merch', 'Streets & Threads', 'Bold Statement Apparel', 'Creator Studio', 'Gulf Coast Drops', 'TX Creator Merch', 'ATX Icon Shop', 'Island Creator Co.', 'Prairie Creator Merch', 'Coastal Streetwear', 'Bayou Drip Co.', 'TX Bold Apparel', 'The Creator Lab TX', 'Rare Threads TX', 'Gulf Streetwear Co.', 'Heritage Drops', 'Bay City Creator', 'Fresh Gear TX', 'Creekside Creator Co.'],
            'Order Anywhere'             => ['Order Anything Concierge', 'Personal Shopper Hub', 'Urban Goods Runner', 'Errand Boys', 'Universal Sourcing Group', 'The Shopping Agent', 'Quick Fetch Services', 'Anywhere Delivery Hub', 'Custom Sourcing Pros', 'Urban Request Runners', 'Gulf Coast Concierge', 'TX Errand Pro', 'ATX Fetch', 'Island Runner Service', 'Prairie Shopper', 'Coastal Sourcing', 'Bayou Request Co.', 'TX Order Runner', 'The Fetch Pro TX', 'Personal Shopper TX', 'Gulf Request Co.', 'Heritage Runner', 'Bay City Errands', 'Quick Source TX', 'Creekside Concierge'],
            'Book Anything / Services'   => ['Home Care Pro Booking', 'Urban Handyman Services', 'Cleaning Booking', 'Appliance Repairs', 'Lawn Care', 'Apex Event Planners', 'Elite Auto Detailers', 'Comfort Zone HVAC', 'Pro Paint Services', 'Modern Plumbers Booking', 'Gulf Coast Home Services', 'TX Handyman Pro', 'ATX Home Booking', 'Island Home Services', 'Prairie Service Pros', 'Coastal Fix-It', 'Bayou Home Co.', 'TX Book & Fix', 'The Service Hub TX', 'Pro Book TX', 'Gulf Home Services', 'Heritage Handyman', 'Bay City Services', 'Ranch Ready Services TX', 'Creekside Home Pro'],
            'Logistics / Load Board'     => ['Load Board Logistics', 'Freight Brokerage', 'Apex Load Board', 'Metro Cargo Handlers', 'Midtown Freight Services', 'Urban Load Sourcing', 'Interstate Load Board', 'Freight Kings', 'Red Line Cargo', 'Logistics TX', 'Gulf Coast Load Board', 'TX Freight Pros', 'ATX Cargo', 'Island Freight Co.', 'Prairie Logistics', 'Coastal Freight Board', 'Bayou Cargo Co.', 'TX Load Sourcing', 'The Freight Exchange TX', 'Cargo Connect TX', 'Gulf Freight Hub', 'Heritage Logistics', 'Bay City Freight', 'Inland Freight TX', 'Creekside Cargo'],
        ];

        $prefixList = $prefixes[$category] ?? ['Local Business'];
        $name = $prefixList[($index - 1) % count($prefixList)];
        return "{$name} – {$tag}";
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
