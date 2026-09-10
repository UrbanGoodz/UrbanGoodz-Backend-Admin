<?php

namespace App\Services\FashionFit;

use GdImage;
use RuntimeException;

/**
 * Cheap, memory-safe photo quality checks run at upload time. These are
 * advisory warnings that help the customer retake a photo before analysis;
 * the measurement engine performs the authoritative frame validation on the
 * downscaled working image.
 */
class PhotoQualityAnalyzer
{
    private const WORK_WIDTH = 480;

    private const DARK_LUMA = 0.15;

    private const OVEREXPOSED_RATIO = 0.25;

    private const EDGE_STRENGTH = 24;

    private const BLUR_EDGE_RATIO = 0.005;

    /**
     * @return array{mean_luma: float, dark: bool, overexposed_ratio: float,
     *     overexposed: bool, edge_ratio: float, blurry: bool, warnings: array<int, string>}
     */
    public function analyze(string $binary): array
    {
        $image = @imagecreatefromstring($binary);
        if (! $image instanceof GdImage) {
            throw new RuntimeException('The photo could not be decoded for quality analysis.');
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1.0, self::WORK_WIDTH / max(1, $width));
        $smallWidth = max(8, (int) round($width * $scale));
        $smallHeight = max(8, (int) round($height * $scale));
        $small = imagescale($image, $smallWidth, $smallHeight, IMG_BILINEAR_FIXED);
        imagedestroy($image);
        if (! $small instanceof GdImage) {
            throw new RuntimeException('The photo could not be resized for quality analysis.');
        }

        $luma = [];
        $lumaTotal = 0.0;
        $overexposed = 0;
        for ($y = 0; $y < $smallHeight; $y++) {
            for ($x = 0; $x < $smallWidth; $x++) {
                $rgb = imagecolorat($small, $x, $y);
                $value = (0.2126 * (($rgb >> 16) & 0xFF) + 0.7152 * (($rgb >> 8) & 0xFF) + 0.0722 * ($rgb & 0xFF)) / 255.0;
                $luma[$y][$x] = $value;
                $lumaTotal += $value;
                if ($value > 0.98) {
                    $overexposed++;
                }
            }
        }
        imagedestroy($small);

        $pixels = max(1, $smallWidth * $smallHeight);
        $meanLuma = $lumaTotal / $pixels;
        $overexposedRatio = $overexposed / $pixels;

        $edgeTotal = 0.0;
        $edgePixels = 0;
        for ($y = 0; $y < $smallHeight; $y++) {
            for ($x = 0; $x < $smallWidth; $x++) {
                $dx = $x + 1 < $smallWidth ? abs($luma[$y][$x + 1] - $luma[$y][$x]) : 0.0;
                $dy = $y + 1 < $smallHeight ? abs($luma[$y + 1][$x] - $luma[$y][$x]) : 0.0;
                $magnitude = max($dx, $dy);
                $edgeTotal += $magnitude;
                if ($magnitude * 255.0 > self::EDGE_STRENGTH) {
                    $edgePixels++;
                }
            }
        }
        $edgeRatio = $edgePixels / $pixels;

        $dark = $meanLuma < self::DARK_LUMA;
        $overexposedFlag = $overexposedRatio > self::OVEREXPOSED_RATIO;
        $blurry = $edgeRatio < self::BLUR_EDGE_RATIO;

        $warnings = [];
        if ($dark) {
            $warnings[] = 'The photo is too dark. Retake it with brighter, even lighting.';
        }
        if ($overexposedFlag) {
            $warnings[] = 'The photo is overexposed. Move out of direct sunlight or bright backlighting.';
        }
        if ($blurry) {
            $warnings[] = 'The photo appears blurry. Hold the camera steady and focus on the body.';
        }

        return [
            'mean_luma' => round($meanLuma, 4),
            'dark' => $dark,
            'overexposed_ratio' => round($overexposedRatio, 4),
            'overexposed' => $overexposedFlag,
            'edge_ratio' => round($edgeRatio, 6),
            'blurry' => $blurry,
            'warnings' => $warnings,
        ];
    }
}
