<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Pulls real product catalogs from public storefront JSON endpoints that
 * Shopify and WooCommerce intentionally expose for anyone to read (no
 * auth, no anti-bot bypass -- these are documented public APIs). Returns
 * [] when a site isn't running a supported platform or has no catalog;
 * callers must never fabricate a product to fill that gap.
 */
class UrbanGoodzStorefrontCatalogService
{
    public function fetchRealProductCatalog(string $websiteUrl, int $limit = 50): array
    {
        $base = rtrim(trim($websiteUrl), '/');
        if ($base === '' || !filter_var($base, FILTER_VALIDATE_URL)) {
            return [];
        }

        $products = $this->fetchShopifyProducts($base, $limit);
        if (!empty($products)) {
            return $products;
        }

        return $this->fetchWooCommerceProducts($base, $limit);
    }

    public function fetchShopifyProducts(string $baseUrl, int $limit = 50): array
    {
        $limit = max(1, min(250, $limit));
        $data = $this->getJson("{$baseUrl}/products.json?limit={$limit}");

        if (empty($data['products']) || !is_array($data['products'])) {
            return [];
        }

        $products = [];
        foreach ($data['products'] as $p) {
            if (empty($p['title'])) {
                continue;
            }

            $variant = $p['variants'][0] ?? [];
            $images = array_values(array_filter(array_map(
                fn ($img) => $img['src'] ?? null,
                $p['images'] ?? []
            )));
            $canonicalUrl = isset($p['handle']) ? "{$baseUrl}/products/{$p['handle']}" : null;

            $products[] = [
                'name' => $p['title'],
                'external_product_id' => isset($p['id']) ? (string) $p['id'] : null,
                'sku' => $variant['sku'] ?? null,
                'canonical_url' => $canonicalUrl,
                'source_url' => $canonicalUrl ?? $baseUrl,
                'source_type' => 'catalog',
                'source_platform' => 'shopify',
                'brand' => $p['vendor'] ?? null,
                'full_description' => $p['body_html'] ?? null,
                'short_description' => !empty($p['body_html'])
                    ? Str::limit(trim(strip_tags($p['body_html'])), 300)
                    : null,
                'price' => isset($variant['price']) ? (float) $variant['price'] : null,
                'stock_status' => array_key_exists('available', $variant)
                    ? ($variant['available'] ? 'in_stock' : 'out_of_stock')
                    : 'unknown',
                'images' => $images,
                'thumbnail' => $images[0] ?? null,
            ];
        }

        return $products;
    }

    public function fetchWooCommerceProducts(string $baseUrl, int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));
        $data = $this->getJson("{$baseUrl}/wp-json/wc/store/v1/products?per_page={$limit}");

        if (empty($data) || !is_array($data)) {
            return [];
        }

        $products = [];
        foreach ($data as $p) {
            if (!is_array($p) || empty($p['name'])) {
                continue;
            }

            $prices = $p['prices'] ?? [];
            $minorUnit = (int) ($prices['currency_minor_unit'] ?? 2);
            $price = isset($prices['price']) ? ((float) $prices['price']) / (10 ** $minorUnit) : null;

            $images = array_values(array_filter(array_map(
                fn ($img) => $img['src'] ?? null,
                $p['images'] ?? []
            )));

            $brandNames = array_values(array_filter(array_map(
                fn ($b) => $b['name'] ?? null,
                $p['brands'] ?? []
            )));

            $products[] = [
                'name' => $p['name'],
                'external_product_id' => isset($p['id']) ? (string) $p['id'] : null,
                'sku' => $p['sku'] ?? null,
                'canonical_url' => $p['permalink'] ?? null,
                'source_url' => $p['permalink'] ?? $baseUrl,
                'source_type' => 'catalog',
                'source_platform' => 'woocommerce',
                'brand' => !empty($brandNames) ? implode(', ', $brandNames) : null,
                'full_description' => $p['description'] ?? null,
                'short_description' => !empty($p['short_description']) ? trim(strip_tags($p['short_description'])) : null,
                'price' => $price,
                'currency' => $prices['currency_code'] ?? null,
                'stock_status' => array_key_exists('is_in_stock', $p)
                    ? ($p['is_in_stock'] ? 'in_stock' : 'out_of_stock')
                    : 'unknown',
                'images' => $images,
                'thumbnail' => $images[0] ?? null,
            ];
        }

        return $products;
    }

    private function getJson(string $url): ?array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: UrbanGoodzBot/1.0 (+https://urbangoodzdelivery.com)\r\n",
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            return null;
        }

        $statusLine = $http_response_header[0] ?? '';
        if (!preg_match('/\s(\d{3})\s/', $statusLine, $m) || $m[1] !== '200') {
            return null;
        }

        $data = json_decode($response, true);
        return is_array($data) ? $data : null;
    }
}
