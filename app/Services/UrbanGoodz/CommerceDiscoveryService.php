<?php

namespace App\Services\UrbanGoodz;

class CommerceDiscoveryService
{
    public function discover(string $queryText, array $entities = [], array $context = []): array
    {
        $options = [];
        $queryLower = strtolower($queryText);
        
        // Mock data logic for demonstration, matching intent
        
        if (str_contains($queryLower, 'tv') || str_contains($queryLower, 'electronics')) {
            $price = 299.99;
            $options[] = $this->createOption(
                '1', 'Samsung 50" Class 4K Smart TV', 'Samsung', 'Best Buy', 'external_retailer',
                '123 Best Buy St', 'https://bestbuy.com/mock', $price, 'Available', 'In Stock', 'Today by 5pm',
                'https://mock.url/tv.jpg', 'A great TV.', 'external'
            );
        } elseif (str_contains($queryLower, 'sneaker') || str_contains($queryLower, 'apparel')) {
             $price = 120.00;
             $options[] = $this->createOption(
                '2', 'Nike Air Force 1', 'Nike', 'Foot Locker', 'external_retailer',
                '456 Mall Rd', 'https://footlocker.com/mock', $price, 'Available', 'In Stock', 'Tomorrow',
                'https://mock.url/shoe.jpg', 'Classic sneakers.', 'external'
            );
        } elseif (str_contains($queryLower, 'cake') || str_contains($queryLower, 'bakery')) {
             $price = 45.00;
             $options[] = $this->createOption(
                '3', 'Custom Birthday Cake', 'Local Bakery', 'Sweet Treats', 'sourced_merchant',
                '789 Sugar Ln', '', $price, 'Available', 'Pre-order', '2 Days',
                'https://mock.url/cake.jpg', 'Delicious custom cake.', 'sourced'
            );
        } else {
             // Generic fallback
             $price = 50.00;
             $options[] = $this->createOption(
                '4', 'Assorted Goods', 'Various', 'Target', 'external_retailer',
                '101 Target Blvd', 'https://target.com/mock', $price, 'Available', 'In Stock', 'Today',
                'https://mock.url/goods.jpg', 'Assorted items.', 'external'
            );
        }

        return $options;
    }

    private function createOption(
        $id, $title, $brand, $merchant_name, $merchant_type, $merchant_address, $merchant_url, 
        $price, $availability, $availability_label, $estimated_delivery, $image_url, 
        $description, $source
    ) {
        $delivery_fee = 7.99;
        $service_fee = max(5.0, round($price * 0.15, 2));
        $estimated_tax = round($price * 0.0825, 2);
        $estimated_total = round($price + $delivery_fee + $service_fee + $estimated_tax, 2);

        return [
            'id' => $id,
            'title' => $title,
            'brand' => $brand,
            'merchant_name' => $merchant_name,
            'merchant_type' => $merchant_type,
            'merchant_address' => $merchant_address,
            'merchant_url' => $merchant_url,
            'price' => $price,
            'delivery_fee' => $delivery_fee,
            'service_fee' => $service_fee,
            'estimated_tax' => $estimated_tax,
            'estimated_total' => $estimated_total,
            'availability' => $availability,
            'availability_label' => $availability_label,
            'estimated_delivery' => $estimated_delivery,
            'image_url' => $image_url,
            'description' => $description,
            'source' => $source,
            'badge' => 'Verified',
            'confidence_score' => 0.95
        ];
    }
}
