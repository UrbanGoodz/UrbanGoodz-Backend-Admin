<?php

namespace App\Services;

use App\Models\UrbanGoodzImportBatch;
use App\Models\UrbanGoodzSourcedBusiness;
use App\Models\UrbanGoodzSourcedProduct;
use App\Models\UrbanGoodzSourcedImage;
use App\Models\UrbanGoodzDemandSignal;
use App\Models\OrderAnywhereRequest;
use App\Models\Store;
use App\Models\Item;
use App\Models\Zone;
use App\Models\Module;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class UrbanGoodzIngestionService
{
    /**
     * Discover candidate businesses via mock search query simulation.
     */
    public function discoverBusinesses(string $city, string $category, array $filters = []): array
    {
        $query = "{$city} local {$category} business";
        Log::info("UG Ingestion: Simulating discovery query: '{$query}'");

        // Simulate external API fetch from directories/social links
        $candidates = $this->getMockCandidates($city, $category);

        $results = [];
        foreach ($candidates as $cand) {
            // Step 3: Deduplicate
            $isDuplicate = $this->checkDuplicates($cand);
            if ($isDuplicate) {
                Log::info("UG Ingestion: Duplicate business found: '{$cand['name']}' in {$city}. Skipping.");
                continue;
            }

            // Step 2 & 8: Enrich & Score Confidence
            $cand = $this->enrichBusiness($cand);
            $cand['data_confidence_score'] = $this->scoreConfidence($cand);

            // Step 4: Classify module and category
            $classification = $this->classifyBusiness($cand);
            $cand = array_merge($cand, $classification);

            $results[] = $cand;
        }

        return $results;
    }

    /**
     * Enrich candidate record with mock website/social lookups and check policies.
     */
    public function enrichBusiness(array $candidate): array
    {
        $domain = Str::slug($candidate['name']) . '.com';
        
        $candidate['website'] = $candidate['website'] ?? "https://www.{$domain}";
        $candidate['social_links'] = $candidate['social_links'] ?? [
            'instagram' => "https://instagram.com/" . Str::slug($candidate['name']),
            'facebook' => "https://facebook.com/" . Str::slug($candidate['name']),
            'tiktok' => null,
            'youtube' => null,
        ];
        $candidate['email'] = $candidate['email'] ?? "info@{$domain}";
        $candidate['short_description'] = $candidate['short_description'] ?? $this->generateDescriptions($candidate, 'short');
        $candidate['description'] = $candidate['description'] ?? $this->generateDescriptions($candidate, 'long');
        $candidate['source_status'] = $candidate['source_status'] ?? 'ai_sourced';
        
        // Add source URLs
        $candidate['source_urls'] = $candidate['source_urls'] ?? [
            "https://google.com/search?q=" . urlencode($candidate['name'] . ' ' . $candidate['city'])
        ];

        return $candidate;
    }

    /**
     * Map category query to modules.
     */
    public function classifyBusiness(array $candidate): array
    {
        $category = strtolower($candidate['category'] ?? '');
        
        $moduleMap = [
            'restaurant' => ['module_name' => 'Restaurants', 'type' => 'food'],
            'food truck' => ['module_name' => 'Food Trucks', 'type' => 'food'],
            'grocery' => ['module_name' => 'Grocery / Markets', 'type' => 'grocery'],
            'retail' => ['module_name' => 'Retail / Shopping', 'type' => 'shop'],
            'beauty' => ['module_name' => 'Beauty Supply / Hair Providerz', 'type' => 'shop'],
            'hair' => ['module_name' => 'Beauty Supply / Hair Providerz', 'type' => 'shop'],
            'pharmacy' => ['module_name' => 'Pharmacy / Health', 'type' => 'pharmacy'],
            'liquor' => ['module_name' => 'Liquor / Beveragez', 'type' => 'shop'],
            'thc' => ['module_name' => 'THC / CBD', 'type' => 'shop'],
            'home' => ['module_name' => 'Home-Based Businessz', 'type' => 'shop'],
            'event' => ['module_name' => 'Local Events / Creators', 'type' => 'shop'],
            'car' => ['module_name' => 'Car Rentalz', 'type' => 'rental'],
            'equipment' => ['module_name' => 'Equipment Rentalz', 'type' => 'rental'],
            'courier' => ['module_name' => 'Courier / Parcel', 'type' => 'parcel'],
            'medical' => ['module_name' => 'Medical Courier', 'type' => 'parcel'],
            'service' => ['module_name' => 'Professional Services', 'type' => 'shop'],
            'fashion' => ['module_name' => 'Fashion Fit', 'type' => 'shop'],
            'creator' => ['module_name' => 'Creator Commerce', 'type' => 'shop'],
            'logistics' => ['module_name' => 'Logistics / Load Board', 'type' => 'parcel'],
        ];

        $matchedModule = 'Retail / Shopping';
        foreach ($moduleMap as $key => $val) {
            if (str_contains($category, $key)) {
                $matchedModule = $val['module_name'];
                break;
            }
        }

        // Fetch actual module ID from DB if exists
        $moduleId = null;
        $dbModule = Module::where('module_name', $matchedModule)->first();
        if ($dbModule) {
            $moduleId = $dbModule->id;
        }

        return [
            'module_id' => $moduleId,
            'module_name' => $matchedModule,
            'category_ids' => [1], // Default placeholder category ID
            'tags' => [$candidate['category'] ?? 'Local'],
            'fulfillment_modes' => ['delivery', 'pickup', 'quote_required', 'order_anywhere'],
        ];
    }

    /**
     * Check for duplicates in sourced or active databases.
     */
    public function checkDuplicates(array $candidate): bool
    {
        $name = strtolower(trim($candidate['name']));
        $city = strtolower(trim($candidate['city'] ?? ''));

        // Check if exists in active stores table
        $storeExists = Store::whereRaw('LOWER(name) = ?', [$name])
            ->when($city, function($q) use ($city) {
                return $q->whereRaw('LOWER(address) LIKE ?', ["%{$city}%"]);
            })
            ->exists();

        if ($storeExists) return true;

        // Check if exists in sourced business table
        $sourcedExists = UrbanGoodzSourcedBusiness::whereRaw('LOWER(name) = ?', [$name])
            ->when($city, function($q) use ($city) {
                return $q->whereRaw('LOWER(city) = ?', [$city]);
            })
            ->exists();

        return $sourcedExists;
    }

    /**
     * Score confidence level 0-100.
     */
    public function scoreConfidence(array $candidate): int
    {
        $score = 0;
        if (!empty($candidate['website'])) $score += 25;
        if (!empty($candidate['phone'])) $score += 15;
        if (!empty($candidate['address'])) $score += 20;
        if (!empty($candidate['social_links'])) $score += 20;
        if (!empty($candidate['products'])) $score += 20;

        return min(100, $score);
    }

    /**
     * Generate marketplace copy following strict branding guides.
     */
    public function generateDescriptions(array $entity, string $type = 'long'): string
    {
        $name = $entity['name'];
        $city = $entity['city'] ?? 'Houston';
        $category = $entity['category'] ?? 'local services';

        if ($type === 'short') {
            return "{$name} offers premium {$category} in {$city}. Requested through Urban Goodz.";
        }

        return "{$name} is a {$city}-based business publicly listed as offering {$category}. Urban Goodz customers can request items or booking quotes from this business through Order Anywhere while the listing awaits owner verification. Not yet claimed. Sourced by Urban Goodz.";
    }

    /**
     * Extract products for a candidate.
     */
    public function extractProducts(array $candidate): array
    {
        if (empty($candidate['products'])) {
            // Create a general quote-required placeholder product
            return [
                [
                    'name' => "Custom request from {$candidate['name']}",
                    'price' => null,
                    'price_type' => 'quote_required',
                    'stock_status' => 'unknown',
                    'item_type' => 'custom_request',
                    'requires_quote' => true,
                    'requires_admin_review' => true,
                    'is_active' => false,
                    'is_public' => false,
                ]
            ];
        }

        $products = [];
        foreach ($candidate['products'] as $prod) {
            $products[] = [
                'name' => $prod['name'],
                'price' => $prod['price'] ?? null,
                'price_type' => isset($prod['price']) ? 'fixed' : 'quote_required',
                'stock_status' => 'in_stock',
                'item_type' => 'product',
                'requires_quote' => !isset($prod['price']),
                'requires_admin_review' => true,
                'is_active' => false,
                'is_public' => false,
            ];
        }

        return $products;
    }

    /**
     * Handle images and fallbacks.
     */
    public function findImages(array $entity): array
    {
        return [
            'image_url' => $entity['image'] ?? $this->generateBrandedFallbackImage($entity['category'] ?? 'general'),
            'rights_status' => 'generated_placeholder',
            'review_status' => 'pending',
        ];
    }

    /**
     * Generate branded fallback image url.
     */
    public function generateBrandedFallbackImage(string $category): string
    {
        $cat = Str::slug($category);
        return "/assets/images/urban_goodz/fallbacks/{$cat}.png";
    }

    /**
     * Create proactive import batch.
     */
    public function createImportBatch(string $city, string $state, string $category, string $module): UrbanGoodzImportBatch
    {
        $batch = UrbanGoodzImportBatch::create([
            'city' => $city,
            'state' => $state,
            'category' => $category,
            'module' => $module,
            'source_query' => "{$category} in {$city}, {$state}",
            'source_platforms' => ['google_maps', 'instagram'],
            'status' => 'running',
        ]);

        $candidates = $this->discoverBusinesses($city, $category);
        $importedCount = 0;
        $needsReviewCount = 0;

        foreach ($candidates as $cand) {
            $business = UrbanGoodzSourcedBusiness::create(array_merge($cand, [
                'onboarding_status' => 'public_sourced',
                'source_status' => 'ai_sourced',
                'admin_review_status' => 'pending',
            ]));

            // Add products
            $prods = $this->extractProducts($cand);
            foreach ($prods as $prod) {
                UrbanGoodzSourcedProduct::create(array_merge($prod, [
                    'sourced_business_id' => $business->id,
                    'import_batch_id' => $batch->id,
                ]));
            }

            // Add image reference
            $img = $this->findImages($cand);
            UrbanGoodzSourcedImage::create([
                'entity_type' => 'business',
                'entity_id' => $business->id,
                'image_url' => $img['image_url'],
                'rights_status' => $img['rights_status'],
                'review_status' => $img['review_status'],
            ]);

            $importedCount++;
            $needsReviewCount++;
        }

        $batch->update([
            'total_found' => $importedCount,
            'total_imported' => $importedCount,
            'total_needs_review' => $needsReviewCount,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return $batch;
    }

    /**
     * Convert Customer Order Anywhere Request into Sourced Candidate.
     */
    public function convertCustomerRequestToCandidate(OrderAnywhereRequest $request): UrbanGoodzSourcedBusiness
    {
        Log::info("UG Ingestion: Converting Order Anywhere request #{$request->request_number} to business candidate.");

        // Check if candidate exists already
        $existing = UrbanGoodzSourcedBusiness::whereRaw('LOWER(name) = ?', [strtolower($request->store_vendor_name)])->first();
        if ($existing) {
            // Update demand score
            $existing->increment('demand_score');
            
            // Add demand signal
            $this->updateDemandSignals([
                'customer_id' => $request->customer_id,
                'requested_item' => $request->item_details,
                'requested_vendor' => $request->store_vendor_name,
                'source' => 'order_anywhere',
                'matched_entity_id' => $existing->id,
            ]);

            return $existing;
        }

        // Create new pending business from request details
        $candidate = [
            'name' => $request->store_vendor_name,
            'slug' => Str::slug($request->store_vendor_name) . '-' . Str::random(4),
            'display_name' => $request->store_vendor_name,
            'address' => $request->store_vendor_address_or_website,
            'city' => 'Houston', // Default launch market
            'state' => 'TX',
            'country_code' => 'US',
            'is_launch_market' => true,
            'onboarding_status' => 'public_sourced',
            'source_status' => 'customer_requested',
            'admin_review_status' => 'pending',
            'data_confidence_score' => 30, // customer screenshot / manual request confidence
            'demand_score' => 1,
            'fulfillment_modes' => ['order_anywhere'],
        ];

        $classification = $this->classifyBusiness($candidate);
        $candidate = array_merge($candidate, $classification);

        $business = UrbanGoodzSourcedBusiness::create($candidate);

        // Add custom requested item as pending product
        UrbanGoodzSourcedProduct::create([
            'sourced_business_id' => $business->id,
            'module_id' => $business->module_id,
            'name' => $request->item_details ?? "Requested custom item",
            'price' => $request->budget_estimate,
            'price_type' => $request->budget_estimate ? 'starting_at' : 'quote_required',
            'requires_quote' => true,
            'requires_admin_review' => true,
            'is_active' => false,
            'is_public' => false,
            'item_type' => 'custom_request',
        ]);

        // Add demand signal
        $this->updateDemandSignals([
            'customer_id' => $request->customer_id,
            'requested_item' => $request->item_details,
            'requested_vendor' => $request->store_vendor_name,
            'source' => 'order_anywhere',
            'matched_entity_id' => $business->id,
            'converted_to_order_anywhere_request_id' => $request->id,
        ]);

        return $business;
    }

    /**
     * Record search queries / Order Anywhere triggers.
     */
    public function updateDemandSignals(array $data): UrbanGoodzDemandSignal
    {
        $signal = UrbanGoodzDemandSignal::create([
            'customer_id' => $data['customer_id'] ?? null,
            'query_text' => $data['query_text'] ?? null,
            'requested_item' => $data['requested_item'] ?? null,
            'requested_vendor' => $data['requested_vendor'] ?? null,
            'source' => $data['source'] ?? 'search',
            'matched_entity_id' => $data['matched_entity_id'] ?? null,
            'matched_product_id' => $data['matched_product_id'] ?? null,
            'city' => $data['city'] ?? 'Houston',
            'state' => $data['state'] ?? 'TX',
            'zone_id' => $data['zone_id'] ?? null,
            'demand_count' => 1,
            'converted_to_order_anywhere_request_id' => $data['converted_to_order_anywhere_request_id'] ?? null,
        ]);

        // Calculate opportunity score based on demand signals
        if ($signal->matched_entity_id) {
            $signalsCount = UrbanGoodzDemandSignal::where('matched_entity_id', $signal->matched_entity_id)->count();
            $oppScore = min(100, $signalsCount * 10);
            $signal->update(['opportunity_score' => $oppScore]);

            UrbanGoodzSourcedBusiness::where('id', $signal->matched_entity_id)->update([
                'demand_score' => $signalsCount,
            ]);
        }

        return $signal;
    }

    /**
     * Export batch results to compatible CSV format.
     */
    public function exportImportCsv(int $batchId): string
    {
        $batch = UrbanGoodzImportBatch::findOrFail($batchId);
        $businesses = UrbanGoodzSourcedBusiness::where('import_batch_id', $batchId)->get();

        $headers = [
            'name', 'display_name', 'description', 'short_description', 'business_type',
            'module_name', 'category_name', 'phone', 'email', 'website', 'instagram',
            'address', 'city', 'state', 'country_code', 'zip', 'latitude', 'longitude',
            'onboarding_status', 'source_status', 'data_confidence_score'
        ];

        $csvContent = implode(',', $headers) . "\n";
        foreach ($businesses as $b) {
            $row = [
                $b->name, $b->display_name ?? $b->name,
                str_replace(',', ' ', $b->description ?? ''),
                str_replace(',', ' ', $b->short_description ?? ''),
                $b->business_type, $b->module_name,
                $b->tags[0] ?? 'General', $b->phone, $b->email, $b->website,
                $b->social_links['instagram'] ?? '',
                str_replace(',', ' ', $b->address ?? ''),
                $b->city, $b->state, $b->country_code, $b->zip,
                $b->latitude, $b->longitude, $b->onboarding_status,
                $b->source_status, $b->data_confidence_score
            ];
            $csvContent .= implode(',', array_map(fn($v) => '"' . $v . '"', $row)) . "\n";
        }

        return $csvContent;
    }

    /**
     * Publish approved sourced listing as live active store.
     */
    public function publishApprovedListings(int $businessId): Store
    {
        $b = UrbanGoodzSourcedBusiness::findOrFail($businessId);

        // Map Sourced Business to 6amMart active store structure
        $store = Store::create([
            'name' => $b->name,
            'phone' => $b->phone ?? '0000000000',
            'email' => $b->email,
            'logo' => null,
            'address' => $b->address,
            'latitude' => $b->latitude,
            'longitude' => $b->longitude,
            'module_id' => $b->module_id ?? 1,
            'zone_id' => $b->zone_id ?? 1,
            'status' => 1,
            'active' => true,
            'delivery' => true,
            'take_away' => true,
            'slug' => Str::slug($b->name),
            'vendor_id' => 1, // Default system/admin vendor till claimed
        ]);

        $b->update([
            'onboarding_status' => 'active',
            'admin_review_status' => 'approved',
        ]);

        // Publish products
        $products = UrbanGoodzSourcedProduct::where('sourced_business_id', $b->id)->get();
        foreach ($products as $p) {
            Item::create([
                'name' => $p->name,
                'description' => $p->full_description ?? $p->short_description,
                'price' => $p->price ?? 0.00,
                'store_id' => $store->id,
                'module_id' => $store->module_id,
                'category_id' => $p->category_id ?? 1,
                'status' => 1,
                'slug' => Str::slug($p->name),
            ]);
            $p->update([
                'store_id' => $store->id,
                'is_active' => true,
                'is_public' => true,
            ]);
        }

        return $store;
    }

    /**
     * Internal mock listings database to support the min 5 / 10 population handoff rules.
     */
    private function getMockCandidates(string $city, string $category): array
    {
        // Define some realistic businesses per category in different cities
        // TX cities require 10, other cities require 5.
        $isTx = strtolower($city) === 'houston' || strtolower($city) === 'austin' || strtolower($city) === 'dallas' || str_contains(strtolower($city), 'tx') || str_contains(strtolower($city), 'texas');
        $count = $isTx ? 10 : 5;

        $templates = [
            'Restaurants' => [
                ['name' => 'Soul Food Haven', 'category' => 'Soul Food', 'phone' => '832-555-0101', 'address' => '1202 Martin Luther King Blvd'],
                ['name' => 'Taco Fusion Truck', 'category' => 'Mexican Fusion', 'phone' => '832-555-0102', 'address' => '2405 Washington Ave'],
                ['name' => 'Green Garden Salads', 'category' => 'Vegan/Healthy', 'phone' => '832-555-0103', 'address' => '506 Yale St'],
                ['name' => 'The Breakfast Corner', 'category' => 'Breakfast & Brunch', 'phone' => '832-555-0104', 'address' => '3300 Main St'],
                ['name' => 'Smokin Barbecue Co.', 'category' => 'Texas BBQ', 'phone' => '832-555-0105', 'address' => '1902 N Durham Dr'],
                ['name' => 'Pasta Bella Bistro', 'category' => 'Italian', 'phone' => '832-555-0106', 'address' => '4410 Westheimer Rd'],
                ['name' => 'Tokyo Express Sushi', 'category' => 'Japanese Sushi', 'phone' => '832-555-0107', 'address' => '2200 Shepherd Dr'],
                ['name' => 'Island Spice Caribbean', 'category' => 'Caribbean', 'phone' => '832-555-0108', 'address' => '8803 Bissonnet St'],
                ['name' => 'The Burger Lab', 'category' => 'Burgers & Fries', 'phone' => '832-555-0109', 'address' => '1001 Heights Blvd'],
                ['name' => 'Dessert Oasis Bakery', 'category' => 'Bakery & Sweets', 'phone' => '832-555-0110', 'address' => '1515 Kirby Dr'],
            ],
            'Beauty Supply / Hair Providerz' => [
                ['name' => 'Classic Crown Beauty Supply', 'category' => 'Beauty Supply', 'phone' => '832-555-0201', 'address' => '4102 Almeda Rd'],
                ['name' => 'Urban Glow Hair Extensionz', 'category' => 'Hair Extensions', 'phone' => '832-555-0202', 'address' => '6700 Highway 6'],
                ['name' => 'Natural Roots Braiding Salon', 'category' => 'Hair Braiding', 'phone' => '832-555-0203', 'address' => '2300 Southmore Blvd'],
                ['name' => 'Organic Tresses Boutique', 'category' => 'Hair Products', 'phone' => '832-555-0204', 'address' => '1205 W 19th St'],
                ['name' => 'H-Town Barber Supply', 'category' => 'Barber Tools', 'phone' => '832-555-0205', 'address' => '900 Richmond Ave'],
                ['name' => 'Elegant Wigs & Beyond', 'category' => 'Wig Boutique', 'phone' => '832-555-0206', 'address' => '5400 Fannin St'],
                ['name' => 'Edge Control & Lashes Depot', 'category' => 'Cosmetics Store', 'phone' => '832-555-0207', 'address' => '3200 Scott St'],
                ['name' => 'Melanin Skincare Lab', 'category' => 'Skincare Products', 'phone' => '832-555-0208', 'address' => '7100 Almeda Rd'],
                ['name' => 'Nail Artisan Lounge', 'category' => 'Nail Supplies', 'phone' => '832-555-0209', 'address' => '4005 Washington Ave'],
                ['name' => 'Hair Magic Beauty Mall', 'category' => 'Beauty Supply', 'phone' => '832-555-0210', 'address' => '1100 Gessner Rd'],
            ]
        ];

        // Fallback generator for other categories
        $candidates = [];
        $srcCategory = isset($templates[$category]) ? $category : 'Restaurants';
        $items = $templates[$srcCategory];

        for ($i = 0; $i < $count; $i++) {
            $item = $items[$i % count($items)];
            $nameSuffix = $isTx ? "" : " " . Str::upper(substr($city, 0, 3));
            
            // Adjust address according to city name
            $address = $item['address'];
            if (!$isTx) {
                $address = str_replace(['Blvd', 'Ave', 'St', 'Dr', 'Rd'], ['Avenue', 'Street', 'Boulevard', 'Way', 'Drive'], $address) . ", {$city}";
            }

            $candidates[] = [
                'name' => $item['name'] . $nameSuffix,
                'category' => $category,
                'phone' => $item['phone'],
                'address' => $address,
                'city' => $city,
                'state' => $isTx ? 'TX' : 'GA', // Default fallback GA for Atlanta, etc.
                'is_black_owned' => ($i % 3 === 0) ? true : false,
                'products' => [
                    ['name' => "Starter Package from " . $item['name'], 'price' => 25.00],
                    ['name' => "Premium Package from " . $item['name'], 'price' => 75.00],
                    ['name' => "Custom request quote item", 'price' => null]
                ]
            ];
        }

        return $candidates;
    }
}
