<?php

namespace App\Services;

use App\Models\UrbanGoodzSourcedBusiness;

/**
 * Weighted profile-completeness score per the UG business-enrichment spec.
 * image_role convention this relies on: 'logo', 'cover', 'gallery' (default).
 * Nothing else in the app assigns 'logo'/'cover' yet -- real enrichment code
 * must set image_role explicitly when it stores a business's logo/hero shot,
 * or those categories will always read as missing.
 */
class UrbanGoodzCompletenessScoringService
{
    private const WEIGHTS = [
        'logo' => 10,
        'cover_image' => 10,
        'gallery' => 10,
        'description' => 10,
        'contact' => 10,
        'hours' => 10,
        'categories' => 10,
        'products_or_services' => 20,
        'product_imagery' => 10,
    ];

    public function score(UrbanGoodzSourcedBusiness $business): array
    {
        $images = $business->relationLoaded('images') ? $business->images : $business->images()->get();
        $products = $business->relationLoaded('products') ? $business->products : $business->products()->get();

        $categoryIds = (array) $business->category_ids;
        $hasRealCategories = !empty($categoryIds) && !(count($categoryIds) === 1 && (int) $categoryIds[0] === 1);

        $productImageCount = 0;
        foreach ($products as $product) {
            $productImageCount += $product->relationLoaded('sourcedImages')
                ? $product->sourcedImages->count()
                : $product->sourcedImages()->count();
        }

        $checks = [
            'logo' => $images->contains('image_role', 'logo'),
            'cover_image' => $images->contains('image_role', 'cover'),
            'gallery' => $images->where('image_role', 'gallery')->isNotEmpty(),
            'description' => filled($business->description) || filled($business->short_description),
            'contact' => filled($business->phone) && filled($business->address),
            'hours' => filled($business->hours),
            'categories' => $hasRealCategories,
            'products_or_services' => $products->isNotEmpty(),
            'product_imagery' => $productImageCount > 0,
        ];

        $score = 0;
        foreach ($checks as $key => $met) {
            if ($met) {
                $score += self::WEIGHTS[$key];
            }
        }

        return [
            'score' => $score,
            'breakdown' => $checks,
        ];
    }

    public function scoreAndPersist(UrbanGoodzSourcedBusiness $business): UrbanGoodzSourcedBusiness
    {
        $result = $this->score($business);

        $business->update([
            'completeness_score' => $result['score'],
            'completeness_breakdown' => $result['breakdown'],
        ]);

        return $business;
    }
}
