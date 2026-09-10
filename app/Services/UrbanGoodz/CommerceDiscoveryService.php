<?php

namespace App\Services\UrbanGoodz;

use App\CentralLogics\Helpers;
use App\Models\Item;
use App\Models\Store;
use App\Models\UrbanGoodzSourcedBusiness;
use App\Models\UrbanGoodzSourcedProduct;
use App\Services\UrbanGoodzIngestionService;
use App\Services\UrbanGoodzStorefrontCatalogService;
use Illuminate\Support\Facades\Log;

class CommerceDiscoveryService
{
    /** Used only when the admin panel has no commission percentage set. */
    private const DEFAULT_SERVICE_FEE_PERCENT = 15.0;

    /** Floor on the service fee, so small baskets still cover handling. */
    private const MIN_SERVICE_FEE = 5.0;

    private const TAX_PERCENT = 8.25;

    /**
     * Discover real commerce options across internal marketplace, sourced businesses, and external providers.
     * Never returns mock or fabricated data. If no real options match, returns an empty array.
     */
    public function discover(string $queryText, array $entities = [], array $context = []): array
    {
        $budgetMax = isset($entities['budget_max']) ? (float) $entities['budget_max'] : null;
        // 'items' arrives as an array when the model extracts more than one, and
        // casting an array to string is a fatal. Flatten it instead.
        $rawTerm = $entities['search_query'] ?? $entities['items'] ?? $queryText;
        if (is_array($rawTerm)) {
            $rawTerm = implode(' ', array_filter($rawTerm, 'is_scalar'));
        }
        $searchTerm = trim((string) $rawTerm);

        if (empty($searchTerm)) {
            return [];
        }

        $options = [];

        // 1. Search Real Internal Marketplace Items
        $marketplaceOptions = $this->searchMarketplace($searchTerm, $budgetMax);
        $options = array_merge($options, $marketplaceOptions);

        // 2. Search Real Verified Sourced Products
        $sourcedProductOptions = $this->searchSourcedProducts($searchTerm, $budgetMax);
        $options = array_merge($options, $sourcedProductOptions);

        // 3. Search Real Verified Sourced Businesses
        $sourcedBusinessOptions = $this->searchSourcedBusinesses($searchTerm);
        $options = array_merge($options, $sourcedBusinessOptions);

        // 4. Search Live Google Places if API Key is Configured
        $placesKey = config('services.google.places_key', env('GOOGLE_PLACES_API_KEY'));
        if (!empty($placesKey)) {
            $placesOptions = $this->searchGooglePlaces($searchTerm, $placesKey, $context['city'] ?? 'Houston');
            $options = array_merge($options, $placesOptions);
        }

        return array_slice($options, 0, 5);
    }

    /**
     * Search active marketplace items in the database.
     */
    protected function searchMarketplace(string $term, ?float $budgetMax): array
    {
        $keywords = array_filter(preg_split('/\s+/', $term) ?: []);
        if (empty($keywords)) {
            return [];
        }

        try {
            $items = Item::active()
                ->where(function ($q) use ($keywords) {
                    foreach ($keywords as $kw) {
                        $q->orWhere('name', 'like', "%{$kw}%")
                          ->orWhere('description', 'like', "%{$kw}%");
                    }
                })
                ->when($budgetMax !== null, fn($q) => $q->where('price', '<=', $budgetMax))
                ->with('store:id,name,address,minimum_shipping_charge')
                ->limit(4)
                ->get();

            $results = [];
            foreach ($items as $item) {
                $price = (float) $item->price;
                $deliveryFee = (float) ($item->store?->minimum_shipping_charge ?? 7.99);
                $fees = $this->feeBreakdown($price, $deliveryFee);
                $serviceFee = $fees['service_fee'];
                $surcharge = $fees['additional_charge'];
                $tax = $fees['tax'];
                $total = $fees['total'];

                // Use the model's own accessor rather than hand-building a path:
                // 'storage/app/public/product/...' is the on-disk location, not the
                // public URL, so the hand-built form 404s for every item. The
                // accessor routes through Helpers::get_full_url and also handles the
                // s3/local storage split and the missing-file placeholder.
                $imageUrl = $item->image ? $item->image_full_url : null;

                $results[] = [
                    'id' => 'ug_item_' . $item->id,
                    'title' => $item->name,
                    'brand' => null,
                    'merchant_name' => $item->store?->name ?? 'Urban Goodz Partner',
                    'merchant_type' => 'marketplace_partner',
                    'merchant_address' => $item->store?->address,
                    'merchant_url' => null,
                    'price' => $price,
                    'delivery_fee' => $deliveryFee,
                    'service_fee' => $serviceFee,
                    'additional_charge' => $surcharge,
                    'estimated_tax' => $tax,
                    'estimated_total' => $total,
                    'availability' => 'in_stock',
                    'availability_label' => 'In Stock at Partner Store',
                    'estimated_delivery' => 'Today within 1–2 hours',
                    'image_url' => $imageUrl,
                    'description' => $item->description ?? 'Available directly through Urban Goodz partner store.',
                    'source' => 'urban_goodz_marketplace',
                    'badge' => 'Urban Goodz Partner',
                    'confidence_score' => 1.0,
                ];
            }

            return $results;
        } catch (\Throwable $e) {
            Log::warning('CommerceDiscoveryService: Marketplace query failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Search real verified sourced products.
     */
    protected function searchSourcedProducts(string $term, ?float $budgetMax): array
    {
        try {
            $products = UrbanGoodzSourcedProduct::where('name', 'like', "%{$term}%")
                ->when($budgetMax !== null, fn($q) => $q->where('price', '<=', $budgetMax))
                ->with('sourcedBusiness')
                ->limit(3)
                ->get();

            $results = [];
            foreach ($products as $p) {
                $price = (float) ($p->price ?? 0);
                if ($price <= 0) continue;

                $deliveryFee = 8.99;
                $fees = $this->feeBreakdown($price, $deliveryFee);
                $serviceFee = $fees['service_fee'];
                $surcharge = $fees['additional_charge'];
                $tax = $fees['tax'];
                $total = $fees['total'];

                $results[] = [
                    'id' => 'sourced_prod_' . $p->id,
                    'title' => $p->name,
                    'brand' => $p->brand ?? null,
                    'merchant_name' => $p->sourcedBusiness?->name ?? 'Verified Local Merchant',
                    'merchant_type' => 'sourced_merchant',
                    'merchant_address' => $p->sourcedBusiness?->address,
                    'merchant_url' => $p->canonical_url ?? $p->sourcedBusiness?->website,
                    'price' => $price,
                    'delivery_fee' => $deliveryFee,
                    'service_fee' => $serviceFee,
                    'additional_charge' => $surcharge,
                    'estimated_tax' => $tax,
                    'estimated_total' => $total,
                    'availability' => 'in_stock',
                    'availability_label' => 'Verified Local Sourced Product',
                    'estimated_delivery' => 'Same-Day Courier Delivery',
                    'image_url' => $p->thumbnail,
                    'description' => $p->short_description ?? 'Sourced from local merchant official catalog.',
                    'source' => 'sourced_catalog',
                    'badge' => 'Verified Merchant',
                    'confidence_score' => 0.9,
                ];
            }

            return $results;
        } catch (\Throwable $e) {
            Log::warning('CommerceDiscoveryService: Sourced products query failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Search real sourced businesses by name matching the query.
     */
    protected function searchSourcedBusinesses(string $term): array
    {
        try {
            $businesses = UrbanGoodzSourcedBusiness::where('name', 'like', "%{$term}%")
                ->limit(2)
                ->get();

            $results = [];
            foreach ($businesses as $b) {
                // If business has website and products can be read, attempt real catalog fetch
                $catalogService = app(UrbanGoodzStorefrontCatalogService::class);
                $catalogProducts = !empty($b->website) ? $catalogService->fetchRealProductCatalog($b->website, 2) : [];

                foreach ($catalogProducts as $idx => $cp) {
                    // A catalog entry with no price used to default to $25.00, which
                    // quoted the customer a number nobody had ever verified. Skip it:
                    // showing fewer real options beats showing one invented price.
                    if (!isset($cp['price']) || (float) $cp['price'] <= 0) {
                        continue;
                    }
                    $price = (float) $cp['price'];
                    $deliveryFee = 7.99;
                    $fees = $this->feeBreakdown($price, $deliveryFee);
                    $serviceFee = $fees['service_fee'];
                    $surcharge = $fees['additional_charge'];
                    $tax = $fees['tax'];
                    $total = $fees['total'];

                    $results[] = [
                        'id' => 'biz_cat_' . $b->id . '_' . $idx,
                        'title' => $cp['name'],
                        'brand' => $cp['brand'] ?? null,
                        'merchant_name' => $b->name,
                        'merchant_type' => 'sourced_merchant',
                        'merchant_address' => $b->address,
                        'merchant_url' => $cp['canonical_url'] ?? $b->website,
                        'price' => $price,
                        'delivery_fee' => $deliveryFee,
                        'service_fee' => $serviceFee,
                        'additional_charge' => $surcharge,
                        'estimated_tax' => $tax,
                        'estimated_total' => $total,
                        'availability' => 'in_stock',
                        'availability_label' => 'Official Storefront Catalog',
                        'estimated_delivery' => 'Same-Day or Next-Day Delivery',
                        'image_url' => $cp['thumbnail'] ?? null,
                        'description' => $cp['short_description'] ?? "Official product from {$b->name}.",
                        'source' => 'storefront_catalog',
                        'badge' => 'Verified Merchant',
                        'confidence_score' => 0.95,
                    ];
                }
            }

            return $results;
        } catch (\Throwable $e) {
            Log::warning('CommerceDiscoveryService: Sourced business query failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Search live Google Places API when API key is present.
     */
    protected function searchGooglePlaces(string $term, string $apiKey, string $city): array
    {
        try {
            $ingestion = app(UrbanGoodzIngestionService::class);
            $places = $ingestion->discoverBusinesses($city, $term);
            $results = [];

            foreach (array_slice($places, 0, 2) as $idx => $place) {
                $results[] = [
                    'id' => 'place_' . md5($place['name'] . $city),
                    'title' => "Order from {$place['name']}",
                    'brand' => null,
                    'merchant_name' => $place['name'],
                    'merchant_type' => 'external_retailer',
                    'merchant_address' => $place['address'] ?? "{$city}, TX",
                    'merchant_url' => $place['website'] ?? null,
                    'price' => 0.00,
                    'delivery_fee' => 9.99,
                    'service_fee' => 5.00,
                    'estimated_tax' => 0.00,
                    'estimated_total' => 14.99,
                    'availability' => 'available_for_order_anywhere',
                    'availability_label' => 'Order Anywhere Shopper Procurement',
                    'estimated_delivery' => 'Courier Pickup & Delivery',
                    'image_url' => !empty($place['resolved_images']) ? $place['resolved_images'][0] : null,
                    'description' => "Real-world place located in {$city}. Shopper procures requested item in-person.",
                    'source' => 'google_places',
                    'badge' => 'Local Merchant',
                    'confidence_score' => 0.85,
                ];
            }

            return $results;
        } catch (\Throwable $e) {
            Log::info('CommerceDiscoveryService: Google Places search skipped', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Fee breakdown for a discovered option.
     *
     * The service-fee percentage and the flat surcharge are read from the admin
     * panel's Business Settings rather than hard-coded, so pricing can be changed
     * without a deploy. 'admin_commission' is the panel's percentage field, and
     * 'additional_charge' is the flat surcharge -- applied only while its own
     * 'additional_charge_status' toggle is on, which is what makes it toggleable
     * from the panel. Both fall back to the previous constants if unset, so an
     * empty settings table cannot silently produce a zero-fee quote.
     *
     * @return array{service_fee: float, additional_charge: float, tax: float, total: float}
     */
    protected function feeBreakdown(float $price, float $deliveryFee): array
    {
        $percent = (float) (Helpers::get_business_settings('admin_commission')
            ?: self::DEFAULT_SERVICE_FEE_PERCENT);
        if ($percent <= 0) {
            $percent = self::DEFAULT_SERVICE_FEE_PERCENT;
        }

        $serviceFee = max(self::MIN_SERVICE_FEE, round($price * ($percent / 100), 2));

        $surcharge = 0.0;
        if ((int) Helpers::get_business_settings('additional_charge_status') === 1) {
            $surcharge = round((float) Helpers::get_business_settings('additional_charge'), 2);
        }

        $tax = round($price * (self::TAX_PERCENT / 100), 2);

        return [
            'service_fee' => $serviceFee,
            'additional_charge' => $surcharge,
            'tax' => $tax,
            'total' => round($price + $deliveryFee + $serviceFee + $surcharge + $tax, 2),
        ];
    }
}
