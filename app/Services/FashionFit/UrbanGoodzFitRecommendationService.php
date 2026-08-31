<?php

namespace App\Services\FashionFit;

use App\Models\FashionFitMeasurement;
use App\Models\FashionFitProfile;
use Illuminate\Support\Facades\DB;

/**
 * Customer + real product -> recommended size, never a guess. Returns
 * data_sufficiency=insufficient (with an honest explanation of exactly what's
 * missing) whenever the product has no real garment attributes or no real
 * brand size chart -- it never falls back to a generic/guessed size.
 */
class UrbanGoodzFitRecommendationService
{
    // Which approved measurement drives the fit check, per garment category.
    // Categories not listed here have no known mapping -- insufficient by design.
    private const CATEGORY_MEASUREMENT = [
        'top' => 'chest_bust',
        'dress' => 'chest_bust',
        'outerwear' => 'chest_bust',
        'bottom' => 'waist',
    ];

    private const CHART_FIELD = [
        'chest_bust' => ['chest_bust_min', 'chest_bust_max'],
        'waist' => ['waist_min', 'waist_max'],
    ];

    public function recommend(FashionFitProfile $profile, string $productType, int $productId): array
    {
        $garment = DB::table('urban_goodz_garment_attributes')
            ->where('product_type', $productType)
            ->where('product_id', $productId)
            ->first();

        if (!$garment || empty($garment->garment_category) || empty($garment->brand)) {
            return $this->insufficient($profile, $productType, $productId,
                'No garment category and brand are recorded for this product yet, so a size cannot be recommended.');
        }

        $measurementName = self::CATEGORY_MEASUREMENT[$garment->garment_category] ?? null;
        if ($measurementName === null) {
            return $this->insufficient($profile, $productType, $productId,
                "Fit recommendation is not yet supported for garment category '{$garment->garment_category}'.");
        }

        $measurement = FashionFitMeasurement::where('profile_id', $profile->id)
            ->where('name', $measurementName)
            ->whereNotNull('approved_at')
            ->latest('approved_at')
            ->first();

        if (!$measurement) {
            return $this->insufficient($profile, $productType, $productId,
                "Your approved {$measurementName} measurement is required to recommend a size for this garment, and it isn't on file yet.");
        }

        $charts = DB::table('urban_goodz_brand_size_charts')
            ->where('brand', $garment->brand)
            ->where('garment_category', $garment->garment_category)
            ->get();

        if ($charts->isEmpty()) {
            return $this->insufficient($profile, $productType, $productId,
                "No size chart is on file yet for {$garment->brand} {$garment->garment_category} garments.");
        }

        [$minField, $maxField] = self::CHART_FIELD[$measurementName];
        $customerValue = $this->convertUnit((float) $measurement->value, $measurement->unit, $charts->first()->unit ?? 'in');

        $matches = $charts->filter(function ($row) use ($minField, $maxField, $customerValue) {
            $min = $row->{$minField};
            $max = $row->{$maxField};
            return $min !== null && $max !== null && $customerValue >= (float) $min && $customerValue <= (float) $max;
        })->values();

        if ($matches->count() === 1) {
            $row = $matches->first();
            return $this->store($profile, $productType, $productId, [
                'recommended_size' => $row->size_label,
                'confidence' => (int) round(($measurement->confidence ?? 0.75) * 100),
                'data_sufficiency' => 'sufficient',
                'explanation' => sprintf(
                    'Your %s measurement of %.1f%s falls within %s\'s %s size range (%.1f-%.1f%s) for %s garments.',
                    $measurementName, $customerValue, $charts->first()->unit ?? 'in',
                    $garment->brand, $row->size_label, (float) $row->{$minField}, (float) $row->{$maxField},
                    $charts->first()->unit ?? 'in', $garment->garment_category
                ),
            ]);
        }

        if ($matches->count() > 1) {
            $labels = $matches->pluck('size_label')->implode(', ');
            return $this->insufficient($profile, $productType, $productId,
                "Your {$measurementName} measurement falls within more than one {$garment->brand} size range ({$labels}) -- not enough separation between these sizes to confidently recommend one.");
        }

        return $this->insufficient($profile, $productType, $productId,
            "Your {$measurementName} measurement of {$customerValue} doesn't fall within any known {$garment->brand} {$garment->garment_category} size range.");
    }

    private function insufficient(FashionFitProfile $profile, string $productType, int $productId, string $reason): array
    {
        return $this->store($profile, $productType, $productId, [
            'recommended_size' => null,
            'confidence' => null,
            'data_sufficiency' => 'insufficient',
            'explanation' => 'Not enough sizing information is available to confidently recommend a size. '.$reason,
        ]);
    }

    private function store(FashionFitProfile $profile, string $productType, int $productId, array $result): array
    {
        $id = DB::table('urban_goodz_fit_recommendations')->insertGetId(array_merge($result, [
            'fashion_fit_profile_id' => $profile->id,
            'product_type' => $productType,
            'product_id' => $productId,
            'computed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        return array_merge($result, ['id' => $id]);
    }

    private function convertUnit(float $value, string $from, string $to): float
    {
        if ($from === $to) {
            return $value;
        }
        return $from === 'in' ? $value * 2.54 : $value / 2.54;
    }
}
