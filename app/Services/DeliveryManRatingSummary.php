<?php

namespace App\Services;

final class DeliveryManRatingSummary
{
    /**
     * Build the driver rating summary from an already-loaded review relation.
     */
    public function build(?iterable $reviews): array
    {
        $counts = array_fill(1, 5, 0);
        $ratings = [];
        $total = 0;

        foreach ($reviews ?? [] as $review) {
            $total++;

            $value = is_array($review) ? ($review['rating'] ?? null) : ($review?->rating ?? null);
            if (!is_numeric($value)) {
                continue;
            }

            $rating = (float) $value;
            $ratings[] = $rating;

            $star = (int) $rating;
            if ($rating === (float) $star && isset($counts[$star])) {
                $counts[$star]++;
            }
        }

        $distribution = [];
        foreach ($counts as $star => $count) {
            $distribution[$star] = [
                'count' => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100, 2) : 0.0,
            ];
        }

        return [
            'average' => $ratings === [] ? 0.0 : round(array_sum($ratings) / count($ratings), 1),
            'total' => $total,
            'distribution' => $distribution,
        ];
    }
}
