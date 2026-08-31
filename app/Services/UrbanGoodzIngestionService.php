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
     * Discover candidate businesses via Google Places API or configured external provider.
     * Falls back to seeded database candidates when no API key is configured.
     */
    public function discoverBusinesses(string $city, string $category, array $filters = []): array
    {
        $apiKey = config('services.google.places_key', env('GOOGLE_PLACES_API_KEY'));
        $candidates = [];

        if (!empty($apiKey)) {
            $candidates = $this->fetchFromGooglePlaces($city, $category, $apiKey, $filters);
        }

        if (empty($candidates)) {
            $candidates = $this->fetchSeededCandidates($city, $category);
        }

        $results = [];
        foreach ($candidates as $cand) {
            $isDuplicate = $this->checkDuplicates($cand);
            if ($isDuplicate) {
                Log::info("UG Ingestion: Duplicate business found: '{$cand['name']}' in {$city}. Skipping.");
                continue;
            }

            $cand = $this->enrichBusiness($cand);
            $cand['data_confidence_score'] = $this->scoreConfidence($cand);

            $classification = $this->classifyBusiness($cand);
            $cand = array_merge($cand, $classification);

            // Only real product data ever reaches extractProducts(): pull a
            // real catalog from the business's own site when one exists,
            // and never fabricate a fallback when it doesn't.
            if (empty($cand['products']) && !empty($cand['website'])) {
                $cand['products'] = app(UrbanGoodzStorefrontCatalogService::class)
                    ->fetchRealProductCatalog($cand['website']);
            }

            // Resolve real images only -- Places photos when available,
            // otherwise a manually supplied real image, otherwise none.
            $cand['resolved_images'] = $this->resolveRealImages($cand, $apiKey);

            $results[] = $cand;
        }

        return $results;
    }

    /**
     * Raw Places API (New) Text Search -- returns unmapped place results.
     * Shared by the bulk city/category discovery flow and single-business
     * lookups (matching a named Order Anywhere request to a real listing).
     * The legacy Places API (maps.googleapis.com/maps/api/place/*) is
     * deprecated and blocked for this project; this calls
     * places.googleapis.com instead, which returns hours/photos/website/
     * phone in a single request (no separate Details call needed).
     */
    private function textSearchPlaces(string $query, string $apiKey, int $maxResultCount = 20): array
    {
        $fieldMask = implode(',', [
            'places.id',
            'places.displayName',
            'places.formattedAddress',
            'places.location',
            'places.internationalPhoneNumber',
            'places.websiteUri',
            'places.regularOpeningHours',
            'places.photos',
        ]);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", [
                    'Content-Type: application/json',
                    "X-Goog-Api-Key: {$apiKey}",
                    "X-Goog-FieldMask: {$fieldMask}",
                ]),
                'content' => json_encode([
                    'textQuery' => $query,
                    'maxResultCount' => max(1, min(20, $maxResultCount)),
                ]),
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents('https://places.googleapis.com/v1/places:searchText', false, $context);
        if ($response === false) {
            Log::warning("UG Ingestion: Places API request failed for query: {$query}");
            return [];
        }

        $data = json_decode($response, true);
        if (isset($data['error'])) {
            Log::warning("UG Ingestion: Places API error for '{$query}': " . json_encode($data['error']));
            return [];
        }

        return is_array($data['places'] ?? null) ? $data['places'] : [];
    }

    /**
     * Map one Places (New) result into our candidate array shape.
     */
    private function mapPlaceResult(array $place, string $category, string $city): array
    {
        $address = $place['formattedAddress'] ?? '';
        $placeId = $place['id'] ?? null;

        return [
            'name' => $place['displayName']['text'] ?? '',
            'category' => $category,
            'phone' => $place['internationalPhoneNumber'] ?? null,
            'address' => $address,
            'city' => $city,
            'state' => $this->extractStateFromAddress($address),
            'latitude' => $place['location']['latitude'] ?? null,
            'longitude' => $place['location']['longitude'] ?? null,
            'website' => $place['websiteUri'] ?? null,
            'google_place_id' => $placeId,
            'hours' => $place['regularOpeningHours']['weekdayDescriptions'] ?? null,
            'hours_source_url' => $placeId ? "https://www.google.com/maps/place/?q=place_id:{$placeId}" : null,
            'hours_verified_at' => isset($place['regularOpeningHours']) ? now() : null,
            'google_photo_names' => array_column($place['photos'] ?? [], 'name'),
            'is_black_owned' => false,
            'source_urls' => $placeId ? ["https://www.google.com/maps/place/?q=place_id:{$placeId}"] : [],
            'products' => [],
        ];
    }

    /**
     * Fetch businesses from Places API (New) Text Search for the bulk
     * city/category discovery flow.
     */
    private function fetchFromGooglePlaces(string $city, string $category, string $apiKey, array $filters): array
    {
        $query = "{$category} in {$city}";
        $places = $this->textSearchPlaces($query, $apiKey, 20);
        if (empty($places)) {
            return [];
        }

        $results = array_map(fn ($place) => $this->mapPlaceResult($place, $category, $city), $places);
        Log::info("UG Ingestion: Fetched " . count($results) . " candidates from Places API for '{$query}'");
        return $results;
    }

    /**
     * Match a named Order Anywhere request to a real Places listing. The
     * customer already named a specific real business -- this looks it up
     * by name (+ address/website hint if given) so the review queue sees
     * that business's real hours/photos/phone/address, not a bare stub.
     * Returns null when Places has no confident match; callers must keep
     * whatever the customer actually typed in that case.
     */
    public function matchOrderAnywhereRequestToPlace(OrderAnywhereRequest $request, string $apiKey, string $city = 'Houston'): ?array
    {
        $query = trim($request->store_vendor_name . ' ' . ($request->store_vendor_address_or_website ?? ''));
        if ($query === '') {
            return null;
        }

        $places = $this->textSearchPlaces($query, $apiKey, 1);
        if (empty($places)) {
            return null;
        }

        return $this->mapPlaceResult($places[0], 'Order Anywhere request', $city);
    }

    /**
     * Extract state code from a formatted address string.
     */
    private function extractStateFromAddress(string $address): string
    {
        preg_match('/,\s*([A-Z]{2})\s*\d{5}/', $address, $matches);
        return $matches[1] ?? 'TX';
    }

    /**
     * Fetch seeded candidates from the urban_goodz_sourced_businesses table.
     */
    private function fetchSeededCandidates(string $city, string $category): array
    {
        $sourced = UrbanGoodzSourcedBusiness::whereRaw('LOWER(city) = ?', [strtolower($city)])
            ->where('admin_review_status', '!=', 'rejected')
            ->limit(20)
            ->get()
            ->map(fn($b) => [
                'name' => $b->name,
                'category' => $b->category_name ?? $category,
                'phone' => $b->phone ?? '',
                'address' => $b->address ?? '',
                'city' => $b->city ?? $city,
                'state' => $b->state ?? 'TX',
                'latitude' => $b->latitude ?? null,
                'longitude' => $b->longitude ?? null,
                'website' => $b->website ?? null,
                'email' => $b->email ?? null,
                'is_black_owned' => $b->is_black_owned ?? false,
                'source_urls' => $b->source_urls ?? [],
                // $b->products is a relationship call -- always returns a
                // Collection object, never null, so `?? []` never applies
                // and empty()-checks on it downstream always read "not
                // empty" even with zero real products. Normalize to a
                // plain array so emptiness checks behave correctly.
                'products' => $b->products ? $b->products->toArray() : [],
            ])
            ->toArray();

        if (!empty($sourced)) {
            Log::info("UG Ingestion: Fetched " . count($sourced) . " seeded candidates for '{$city}' / '{$category}'");
        }

        return $sourced;
    }

    /**
     * Pass through only verified fields. Never invent website, social
     * links, email, or descriptions -- an unverified guess stored in these
     * fields is indistinguishable from a verified fact downstream, and the
     * review queue has no way to tell them apart. Leave unknown fields
     * null; a real scraper/enrichment pass fills them from actual sources.
     */
    public function enrichBusiness(array $candidate): array
    {
        $candidate['website'] = $candidate['website'] ?? null;
        $candidate['social_links'] = $candidate['social_links'] ?? null;
        $candidate['email'] = $candidate['email'] ?? null;
        $candidate['short_description'] = $candidate['short_description'] ?? null;
        $candidate['description'] = $candidate['description'] ?? null;
        $candidate['source_status'] = $candidate['source_status'] ?? 'ai_sourced';
        $candidate['source_urls'] = $candidate['source_urls'] ?? [];

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

        // Resolve category IDs from the categories table by module
        $categoryIds = [];
        if ($moduleId) {
            $categoryIds = \DB::table('categories')
                ->where('module_id', $moduleId)
                ->pluck('id')
                ->toArray();
        }

        return [
            'module_id' => $moduleId,
            'module_name' => $matchedModule,
            'category_ids' => !empty($categoryIds) ? $categoryIds : [1],
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
     * Extract products for a candidate.
     */
    public function extractProducts(array $candidate): array
    {
        if (empty($candidate['products'])) {
            // No real catalog found -- stay empty rather than invent a
            // generic "Custom request" product the business never listed.
            return [];
        }

        $products = [];
        foreach ($candidate['products'] as $prod) {
            $products[] = [
                'name' => $prod['name'],
                'short_description' => $prod['short_description'] ?? null,
                'full_description' => $prod['full_description'] ?? null,
                'price' => $prod['price'] ?? null,
                'price_type' => isset($prod['price']) ? 'fixed' : 'quote_required',
                'currency' => $prod['currency'] ?? 'USD',
                'stock_status' => $prod['stock_status'] ?? 'unknown',
                'item_type' => 'product',
                'images' => $prod['images'] ?? null,
                'thumbnail' => $prod['thumbnail'] ?? null,
                'sku' => $prod['sku'] ?? null,
                'external_product_id' => $prod['external_product_id'] ?? null,
                'canonical_url' => $prod['canonical_url'] ?? null,
                'brand' => $prod['brand'] ?? null,
                'source_url' => $prod['source_url'] ?? null,
                'source_type' => $prod['source_type'] ?? null,
                'requires_quote' => !isset($prod['price']),
                'requires_admin_review' => true,
                'is_active' => false,
                'is_public' => false,
            ];
        }

        return $products;
    }

    /**
     * Resolve real business images only -- never a generated placeholder.
     * Downloads real Places photos when photo references are present (the
     * first becomes the cover image, the rest fill the gallery); otherwise
     * passes through a single manually supplied real image. Returns []
     * when nothing verified is available.
     */
    public function resolveRealImages(array $entity, ?string $googleApiKey = null): array
    {
        $images = [];

        $photoNames = $entity['google_photo_names'] ?? [];
        if (!empty($photoNames) && !empty($googleApiKey)) {
            $slug = Str::slug($entity['name'] ?? 'business');
            foreach (array_slice($photoNames, 0, 6) as $index => $photoName) {
                $stored = $this->downloadAndStorePlacesPhoto($photoName, $googleApiKey, $slug);
                if ($stored === null) {
                    continue;
                }
                $stored['image_role'] = $index === 0 ? 'cover' : 'gallery';
                $images[] = $stored;
            }
        }

        if (empty($images) && !empty($entity['image'])) {
            $images[] = [
                'image_url' => $entity['image'],
                'rights_status' => $entity['image_rights_status'] ?? 'unknown_review_required',
                'review_status' => 'pending',
                'image_role' => $entity['image_role'] ?? 'gallery',
            ];
        }

        return $images;
    }

    /**
     * Download one Places (New) photo and store it locally. Places photos
     * are not guaranteed clear for downstream commercial reuse (attribution
     * / usage restrictions under Google's ToS), so this always comes back
     * rights_status=unknown_review_required -- a human clears it before it
     * can go live, same as every other sourced image. Rejects failed
     * fetches and suspiciously tiny/broken images rather than storing them.
     */
    private function downloadAndStorePlacesPhoto(string $photoName, string $apiKey, string $businessSlug): ?array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "X-Goog-Api-Key: {$apiKey}\r\n",
                'timeout' => 15,
                'ignore_errors' => true,
                'follow_location' => 1,
            ],
        ]);

        $bytes = @file_get_contents(
            "https://places.googleapis.com/v1/{$photoName}/media?maxWidthPx=1600",
            false,
            $context
        );

        if ($bytes === false || strlen($bytes) < 2048) {
            return null;
        }

        $info = @getimagesizefromstring($bytes);
        if ($info === false || $info[0] < 200 || $info[1] < 200) {
            return null;
        }

        $format = match ($info['mime'] ?? '') {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => null,
        };
        if ($format === null) {
            return null;
        }

        $hash = hash('sha256', $bytes);
        $path = "urban_goodz/sourced_images/{$businessSlug}/{$hash}.{$format}";

        if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
            \Illuminate\Support\Facades\Storage::disk('public')->put($path, $bytes);
        }

        return [
            'image_url' => asset('storage/' . $path),
            'local_path' => $path,
            'source_url' => "https://places.googleapis.com/v1/{$photoName}/media",
            'width' => $info[0],
            'height' => $info[1],
            'format' => $format,
            'file_size_bytes' => strlen($bytes),
            'content_hash' => $hash,
            'source_platform' => 'google_places',
            'rights_status' => 'unknown_review_required',
            'review_status' => 'pending',
        ];
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

            // Only store image rows for images that were actually resolved
            // to a real photo (Places download or a manually supplied URL).
            foreach ($cand['resolved_images'] ?? [] as $img) {
                UrbanGoodzSourcedImage::create(array_merge($img, [
                    'entity_type' => 'business',
                    'entity_id' => $business->id,
                ]));
            }

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

        // Create new pending business from request details. Start with
        // exactly what the customer gave us; a real Places match (if found)
        // overlays real address/phone/hours/photos, never invented ones,
        // and never overwrites the name the customer actually typed.
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

        $apiKey = config('services.google.places_key', env('GOOGLE_PLACES_API_KEY'));
        $placeMatch = !empty($apiKey)
            ? $this->matchOrderAnywhereRequestToPlace($request, $apiKey, $candidate['city'])
            : null;
        if ($placeMatch !== null) {
            unset($placeMatch['name']);
            $candidate = array_merge($candidate, array_filter($placeMatch, fn ($v) => $v !== null && $v !== ''));
            $candidate['data_confidence_score'] = max($candidate['data_confidence_score'], $this->scoreConfidence($candidate));
        }

        $classification = $this->classifyBusiness($candidate);
        $candidate = array_merge($candidate, $classification);

        $business = UrbanGoodzSourcedBusiness::create($candidate);

        // Store any real photos the Places match resolved.
        if (!empty($apiKey)) {
            foreach ($this->resolveRealImages($candidate, $apiKey) as $img) {
                UrbanGoodzSourcedImage::create(array_merge($img, [
                    'entity_type' => 'business',
                    'entity_id' => $business->id,
                ]));
            }
        }

        // A matched real website may expose a real catalog too. The first
        // real product found becomes the request's suggested match -- never
        // auto-quoted, just something a human reviewer can see and confirm.
        $matchedProductId = null;
        if (!empty($candidate['website'])) {
            $catalog = app(UrbanGoodzStorefrontCatalogService::class)->fetchRealProductCatalog($candidate['website']);
            foreach ($this->extractProducts(['products' => $catalog]) as $prod) {
                $created = UrbanGoodzSourcedProduct::create(array_merge($prod, [
                    'sourced_business_id' => $business->id,
                    'module_id' => $business->module_id,
                ]));
                $matchedProductId = $matchedProductId ?? $created->id;
            }
        }

        // Link the request to the real business/product it resolved to.
        // These columns existed on order_anywhere_requests but were never
        // written to anywhere in the app before this.
        $request->update([
            'business_id' => $business->id,
            'product_id' => $matchedProductId,
        ]);

        // Add custom requested item as pending product -- this is literally
        // what the customer asked for, kept regardless of any real catalog
        // match above.
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

        // Eligibility gate: never bypass review/safety rules when provisioning.
        $failures = $this->provisionEligibilityFailures($b);
        if (!empty($failures)) {
            throw new \RuntimeException(
                'Refusing to provision sourced business ' . $businessId . ': ' . implode('; ', $failures)
            );
        }

        // Map Sourced Business to Urban Goodz active store structure
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
     * Legacy mock candidates method — replaced by fetchSeededCandidates + Google Places API.
     * @deprecated Use discoverBusinesses() which routes through real APIs.
     */
    private function getMockCandidates(string $city, string $category): array
    {
        return $this->fetchSeededCandidates($city, $category);
    }

    /**
     * Return a list of reasons a sourced business is NOT eligible for provisioning.
     * Empty array means eligible. Mirrors the P5/P6 review-to-provision eligibility rules.
     */
    private function provisionEligibilityFailures(UrbanGoodzSourcedBusiness $b): array
    {
        $failures = [];

        if ($b->admin_review_status !== 'approved') {
            $failures[] = 'admin_review_status is not approved';
        }
        if ($b->validation_status !== 'valid') {
            $failures[] = 'validation_status is not valid';
        }
        if (!$b->source_verified) {
            $failures[] = 'source has not been verified';
        }
        if ($b->record_classification !== 'production') {
            $failures[] = 'record is not classified as production';
        }
        if ($b->duplicate_of_business_id) {
            $failures[] = 'record is classified as a duplicate';
        }
        if (empty($b->category_ids)) {
            $failures[] = 'category_ids is empty';
        } elseif (in_array(1, (array) $b->category_ids, true)) {
            $failures[] = 'category_ids contains 1 (fallback not allowed)';
        } else {
            foreach ((array) $b->category_ids as $cid) {
                $catModule = \DB::table('categories')->where('id', $cid)->value('module_id');
                if ($catModule !== $b->module_id) {
                    $failures[] = "category {$cid} does not match module {$b->module_id}";
                }
            }
        }

        $url = is_array($b->source_urls) ? ($b->source_urls[0] ?? '') : $b->source_urls;
        if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            $failures[] = 'source_url is invalid';
        }

        $moduleStatus = \DB::table('modules')->where('id', $b->module_id)->value('status');
        if ($moduleStatus != 1) {
            $failures[] = "module {$b->module_id} is not active";
        }

        if ((array) $b->fulfillment_modes === ['review_only']) {
            $failures[] = 'age-restricted row requires separate compliance approval';
        }

        if (in_array('partnered_status_true', (array) $b->tags, true)) {
            $failures[] = 'partnered status must be false';
        }

        $products = UrbanGoodzSourcedProduct::where('sourced_business_id', $b->id)->get();
        if ($products->isEmpty()) {
            $failures[] = 'approved catalog has no products';
        }
        foreach ($products as $product) {
            if ($product->admin_review_status !== 'approved' || $product->validation_status !== 'valid') {
                $failures[] = "product {$product->id} has not passed review and validation";
                continue;
            }
            if (!$product->category_id || !in_array((int) $product->category_id, (array) $b->category_ids, true)) {
                $failures[] = "product {$product->id} does not have an approved business category";
            }
        }

        return $failures;
    }
}
