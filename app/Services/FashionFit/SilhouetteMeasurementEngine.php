<?php

namespace App\Services\FashionFit;

use GdImage;
use RuntimeException;

/**
 * Derives body measurements from a front and a side full-body photo.
 *
 * The engine segments the subject from the background, calibrates pixels
 * against the customer-supplied standing height, reads body widths from the
 * front photo and body depths from the side photo, and converts the two into
 * circumferences with an elliptical cross-section approximation.
 *
 * Nothing here is fabricated: every returned value is derived from the pixels
 * of the two photos. When the photos cannot be segmented well enough to
 * measure, the engine returns `needs_retake` with the reasons instead.
 */
class SilhouetteMeasurementEngine
{
    public const MODEL = 'urbangoodz-silhouette';

    public const MODEL_VERSION = '1.0.0';

    /** Working width the photos are downscaled to before segmentation. */
    private const WORK_WIDTH = 360;

    /**
     * Vertical landmark positions as a fraction of total body height measured
     * down from the top of the head. Classic anthropometric proportions.
     */
    private const LANDMARKS = [
        // Below the chin, above the shoulder ramp.
        'neck_search_top' => 0.138,
        'neck_search_bottom' => 0.184,
        'shoulder' => 0.185,
        'chest_bust' => 0.260,
        'underbust' => 0.305,
        'waist' => 0.375,
        'high_hip' => 0.425,
        'full_hip' => 0.480,
        'upper_arm' => 0.270,
        'crotch_search_top' => 0.440,
        'crotch_search_bottom' => 0.620,
    ];

    /**
     * @return array{
     *     status: string,
     *     model: string,
     *     model_version: string,
     *     overall_confidence: float,
     *     measurements: array<int, array<string, mixed>>,
     *     retake_requirements: array<int, array{view: string, reason: string}>
     * }
     */
    public function measure(string $frontBinary, string $sideBinary, float $calibrationHeight, string $unit): array
    {
        if (! extension_loaded('gd')) {
            throw new RuntimeException('The GD extension is required by the Fashion Fit silhouette engine.');
        }
        if ($calibrationHeight <= 0) {
            throw new RuntimeException('A positive calibration height is required.');
        }

        $front = $this->segment($frontBinary);
        $side = $this->segment($sideBinary);

        $retakes = array_merge(
            $this->frameProblems($front, 'front'),
            $this->frameProblems($side, 'side'),
        );

        if ($retakes === []) {
            $retakes = array_merge($retakes, $this->poseProblems($front));
        }

        if ($retakes !== []) {
            return [
                'status' => 'needs_retake',
                'model' => self::MODEL,
                'model_version' => self::MODEL_VERSION,
                'overall_confidence' => round(min($front['quality'], $side['quality']), 4),
                'measurements' => [],
                'retake_requirements' => array_values($retakes),
            ];
        }

        $measurements = $this->buildMeasurements($front, $side, $calibrationHeight, $unit);

        $weighted = array_column($measurements, 'confidence');
        $overall = $weighted === [] ? 0.0 : array_sum($weighted) / count($weighted);

        return [
            'status' => 'completed',
            'model' => self::MODEL,
            'model_version' => self::MODEL_VERSION,
            'overall_confidence' => round(max(0.0, min(1.0, $overall)), 4),
            'measurements' => $measurements,
            'retake_requirements' => [],
        ];
    }

    // ---------------------------------------------------------------- segment

    /**
     * @return array{mask: array<int, array<int, bool>>, width: int, height: int,
     *     top: int, bottom: int, left: int, right: int, body: int,
     *     rows: array<int, array<int, array{0: int, 1: int}>>, quality: float, fill: float}
     */
    private function segment(string $binary): array
    {
        $image = @imagecreatefromstring($binary);
        if (! $image instanceof GdImage) {
            throw new RuntimeException('The photo could not be decoded.');
        }
        $image = $this->applyExifOrientation($image, $binary);

        $sourceWidth = imagesx($image);
        $sourceHeight = imagesy($image);
        $scale = min(1.0, self::WORK_WIDTH / max(1, $sourceWidth));
        $width = max(8, (int) round($sourceWidth * $scale));
        $height = max(8, (int) round($sourceHeight * $scale));

        $small = imagescale($image, $width, $height, IMG_BILINEAR_FIXED);
        imagedestroy($image);
        if (! $small instanceof GdImage) {
            throw new RuntimeException('The photo could not be resized for analysis.');
        }

        $red = $green = $blue = [];
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($small, $x, $y);
                $red[$y][$x] = ($rgb >> 16) & 0xFF;
                $green[$y][$x] = ($rgb >> 8) & 0xFF;
                $blue[$y][$x] = $rgb & 0xFF;
            }
        }
        imagedestroy($small);

        [$backgroundR, $backgroundG, $backgroundB] = $this->borderMedian($red, $green, $blue, $width, $height);

        $distance = [];
        $histogram = array_fill(0, 256, 0);
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $value = (int) round(sqrt(
                    ($red[$y][$x] - $backgroundR) ** 2
                    + ($green[$y][$x] - $backgroundG) ** 2
                    + ($blue[$y][$x] - $backgroundB) ** 2
                ) / 1.733);
                $value = max(0, min(255, $value));
                $distance[$y][$x] = $value;
                $histogram[$value]++;
            }
        }

        $threshold = max(14, min(120, $this->otsu($histogram, $width * $height)));

        $mask = [];
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $mask[$y][$x] = $distance[$y][$x] > $threshold;
            }
        }

        $mask = $this->largestComponent($mask, $width, $height);

        $top = $height;
        $bottom = -1;
        $left = $width;
        $right = -1;
        $body = 0;
        $rows = [];
        for ($y = 0; $y < $height; $y++) {
            $runs = $this->runs($mask[$y], $width);
            $rows[$y] = $runs;
            foreach ($runs as $run) {
                $body += $run[1] - $run[0] + 1;
                $top = min($top, $y);
                $bottom = max($bottom, $y);
                $left = min($left, $run[0]);
                $right = max($right, $run[1]);
            }
        }

        if ($bottom < 0) {
            throw new RuntimeException('No subject could be separated from the background.');
        }

        $borderForeground = 0;
        $borderTotal = 0;
        for ($x = 0; $x < $width; $x++) {
            foreach ([0, $height - 1] as $y) {
                $borderTotal++;
                $borderForeground += $mask[$y][$x] ? 1 : 0;
            }
        }
        for ($y = 0; $y < $height; $y++) {
            foreach ([0, $width - 1] as $x) {
                $borderTotal++;
                $borderForeground += $mask[$y][$x] ? 1 : 0;
            }
        }

        $fill = $body / max(1, $width * $height);
        $quality = max(0.0, min(1.0, 1.0 - ($borderForeground / max(1, $borderTotal)) * 2.2));

        return [
            'mask' => $mask,
            'width' => $width,
            'height' => $height,
            'top' => $top,
            'bottom' => $bottom,
            'left' => $left,
            'right' => $right,
            'body' => $bottom - $top + 1,
            'rows' => $rows,
            'quality' => $quality,
            'fill' => $fill,
        ];
    }

    private function applyExifOrientation(GdImage $image, string $binary): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }
        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($binary));
        $orientation = is_array($exif) ? ($exif['Orientation'] ?? 1) : 1;
        $rotated = match ((int) $orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => null,
        };
        if ($rotated instanceof GdImage) {
            imagedestroy($image);
            return $rotated;
        }

        return $image;
    }

    /**
     * @param  array<int, array<int, int>>  $red
     * @param  array<int, array<int, int>>  $green
     * @param  array<int, array<int, int>>  $blue
     * @return array{0: float, 1: float, 2: float}
     */
    private function borderMedian(array $red, array $green, array $blue, int $width, int $height): array
    {
        $band = max(2, (int) round($width * 0.03));
        $reds = $greens = $blues = [];
        for ($y = 0; $y < $height; $y++) {
            $edgeRow = $y < $band || $y >= $height - $band;
            for ($x = 0; $x < $width; $x++) {
                if (! $edgeRow && $x >= $band && $x < $width - $band) {
                    continue;
                }
                $reds[] = $red[$y][$x];
                $greens[] = $green[$y][$x];
                $blues[] = $blue[$y][$x];
            }
        }

        return [$this->median($reds), $this->median($greens), $this->median($blues)];
    }

    /** @param array<int, int|float> $values */
    private function median(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }
        sort($values);
        $middle = intdiv(count($values), 2);

        return count($values) % 2 === 1
            ? (float) $values[$middle]
            : ((float) $values[$middle - 1] + (float) $values[$middle]) / 2;
    }

    /** @param array<int, int> $histogram */
    private function otsu(array $histogram, int $total): int
    {
        $sum = 0.0;
        for ($i = 0; $i < 256; $i++) {
            $sum += $i * $histogram[$i];
        }
        $sumBackground = 0.0;
        $weightBackground = 0;
        $best = 0.0;
        $threshold = 0;
        for ($i = 0; $i < 256; $i++) {
            $weightBackground += $histogram[$i];
            if ($weightBackground === 0) {
                continue;
            }
            $weightForeground = $total - $weightBackground;
            if ($weightForeground === 0) {
                break;
            }
            $sumBackground += $i * $histogram[$i];
            $meanBackground = $sumBackground / $weightBackground;
            $meanForeground = ($sum - $sumBackground) / $weightForeground;
            $between = $weightBackground * $weightForeground * ($meanBackground - $meanForeground) ** 2;
            if ($between > $best) {
                $best = $between;
                $threshold = $i;
            }
        }

        return $threshold;
    }

    /**
     * @param  array<int, array<int, bool>>  $mask
     * @return array<int, array<int, bool>>
     */
    private function largestComponent(array $mask, int $width, int $height): array
    {
        $labels = [];
        $bestSize = 0;
        $bestLabel = 0;
        $label = 0;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if (! $mask[$y][$x] || isset($labels[$y][$x])) {
                    continue;
                }
                $label++;
                $size = 0;
                $stack = [[$x, $y]];
                $labels[$y][$x] = $label;
                while ($stack !== []) {
                    [$cx, $cy] = array_pop($stack);
                    $size++;
                    foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
                        $nx = $cx + $dx;
                        $ny = $cy + $dy;
                        if ($nx < 0 || $ny < 0 || $nx >= $width || $ny >= $height) {
                            continue;
                        }
                        if (! $mask[$ny][$nx] || isset($labels[$ny][$nx])) {
                            continue;
                        }
                        $labels[$ny][$nx] = $label;
                        $stack[] = [$nx, $ny];
                    }
                }
                if ($size > $bestSize) {
                    $bestSize = $size;
                    $bestLabel = $label;
                }
            }
        }

        $clean = [];
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $clean[$y][$x] = ($labels[$y][$x] ?? 0) === $bestLabel && $bestLabel > 0;
            }
        }

        return $clean;
    }

    /**
     * @param  array<int, bool>  $row
     * @return array<int, array{0: int, 1: int}>
     */
    private function runs(array $row, int $width): array
    {
        $runs = [];
        $start = null;
        for ($x = 0; $x < $width; $x++) {
            if ($row[$x]) {
                $start ??= $x;
                continue;
            }
            if ($start !== null) {
                $runs[] = [$start, $x - 1];
                $start = null;
            }
        }
        if ($start !== null) {
            $runs[] = [$start, $width - 1];
        }

        return $runs;
    }

    // -------------------------------------------------------------- validation

    /**
     * @param  array<string, mixed>  $shape
     * @return array<int, array{view: string, reason: string}>
     */
    private function frameProblems(array $shape, string $view): array
    {
        $problems = [];
        $label = ucfirst($view);

        if ($shape['body'] < $shape['height'] * 0.5) {
            $problems[] = [
                'view' => $view,
                'reason' => "$label photo: your whole body is too small in the frame. Step back until your head and feet both fit, with the camera held level at chest height.",
            ];
        }
        if ($shape['bottom'] >= $shape['height'] - 2) {
            $problems[] = [
                'view' => $view,
                'reason' => "$label photo: your feet are cut off at the bottom. Retake with both feet fully visible inside the frame.",
            ];
        }
        if ($shape['top'] <= 1) {
            $problems[] = [
                'view' => $view,
                'reason' => "$label photo: the top of your head is cut off. Retake with your whole body inside the frame.",
            ];
        }
        if ($shape['fill'] > 0.62 || $shape['quality'] < 0.4) {
            $problems[] = [
                'view' => $view,
                'reason' => "$label photo: the background could not be separated from your body. Stand against a plain wall with even lighting and nobody else in frame.",
            ];
        }
        if (abs($this->centerlineTilt($shape)) > 0.16) {
            $problems[] = [
                'view' => $view,
                'reason' => "$label photo: the camera was not level. Rest the phone on a level surface at chest height and retake.",
            ];
        }

        return $problems;
    }

    /**
     * @param  array<string, mixed>  $front
     * @return array<int, array{view: string, reason: string}>
     */
    private function poseProblems(array $front): array
    {
        if ($this->armsSeparated($front)) {
            return [];
        }

        // With the arms against the body there is no gap between arm and torso,
        // so the torso edge cannot be found and every girth would be inflated
        // by the arms. Ask for the pose again rather than measure the arms.
        return [[
            'view' => 'front',
            'reason' => 'Front photo: your arms are resting against your torso, so your chest, waist and hip edges are hidden. Retake standing straight with both arms held slightly away from your body, and a clear gap of daylight on each side.',
        ]];
    }

    /** @param array<string, mixed> $shape */
    private function centerlineTilt(array $shape): float
    {
        $points = [];
        for ($fraction = 0.25; $fraction <= 0.85; $fraction += 0.05) {
            $y = $this->rowAt($shape, $fraction);
            $runs = $shape['rows'][$y] ?? [];
            if ($runs === []) {
                continue;
            }
            $spanLeft = $runs[0][0];
            $spanRight = $runs[count($runs) - 1][1];
            $points[] = [$fraction, ($spanLeft + $spanRight) / 2];
        }
        if (count($points) < 4) {
            return 0.0;
        }

        $n = count($points);
        $meanX = array_sum(array_column($points, 0)) / $n;
        $meanY = array_sum(array_column($points, 1)) / $n;
        $numerator = 0.0;
        $denominator = 0.0;
        foreach ($points as [$fx, $cx]) {
            $numerator += ($fx - $meanX) * ($cx - $meanY);
            $denominator += ($fx - $meanX) ** 2;
        }
        if ($denominator == 0.0) {
            return 0.0;
        }

        return ($numerator / $denominator) / max(1, $shape['body']);
    }

    /** @param array<string, mixed> $shape */
    private function armsSeparated(array $shape): bool
    {
        $withGap = 0;
        $sampled = 0;
        for ($fraction = 0.26; $fraction <= 0.46; $fraction += 0.02) {
            $y = $this->rowAt($shape, $fraction);
            $sampled++;
            if (count($shape['rows'][$y] ?? []) >= 3) {
                $withGap++;
            }
        }

        return $sampled > 0 && $withGap / $sampled >= 0.25;
    }

    // ------------------------------------------------------------ measurement

    /** @param array<string, mixed> $shape */
    private function rowAt(array $shape, float $fraction): int
    {
        $y = (int) round($shape['top'] + $fraction * max(1, $shape['body'] - 1));

        return max($shape['top'], min($shape['bottom'], $y));
    }

    /** @param array<string, mixed> $shape */
    private function centerX(array $shape): float
    {
        $values = [];
        for ($fraction = 0.20; $fraction <= 0.45; $fraction += 0.025) {
            $runs = $shape['rows'][$this->rowAt($shape, $fraction)] ?? [];
            if ($runs === []) {
                continue;
            }
            $widest = $runs[0];
            foreach ($runs as $run) {
                if ($run[1] - $run[0] > $widest[1] - $widest[0]) {
                    $widest = $run;
                }
            }
            $values[] = ($widest[0] + $widest[1]) / 2;
        }

        return $values === [] ? ($shape['left'] + $shape['right']) / 2 : $this->median($values);
    }

    /**
     * Width of the run that contains the body centreline: the torso only, with
     * arms excluded when they are held away from the body.
     *
     * @param  array<string, mixed>  $shape
     */
    private function torsoWidth(array $shape, float $fraction): float
    {
        return $this->torsoWidthAtRow($shape, $this->rowAt($shape, $fraction));
    }

    /** @param array<string, mixed> $shape */
    private function torsoWidthAtRow(array $shape, int $y): float
    {
        $runs = $shape['rows'][$y] ?? [];
        if ($runs === []) {
            return 0.0;
        }
        $center = $this->centerX($shape);
        foreach ($runs as $run) {
            if ($center >= $run[0] - 0.5 && $center <= $run[1] + 0.5) {
                return (float) ($run[1] - $run[0] + 1);
            }
        }
        $closest = $runs[0];
        $bestDistance = PHP_FLOAT_MAX;
        foreach ($runs as $run) {
            $distance = min(abs($center - $run[0]), abs($center - $run[1]));
            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $closest = $run;
            }
        }

        return (float) ($closest[1] - $closest[0] + 1);
    }

    /**
     * Width of the widest run that sits entirely to one side of the centreline,
     * which is an arm when the arms are held away from the torso.
     *
     * @param  array<string, mixed>  $shape
     */
    private function limbWidth(array $shape, float $fraction): float
    {
        $y = $this->rowAt($shape, $fraction);
        $runs = $shape['rows'][$y] ?? [];
        $center = $this->centerX($shape);
        $best = 0.0;
        foreach ($runs as $run) {
            if ($center >= $run[0] - 0.5 && $center <= $run[1] + 0.5) {
                continue;
            }
            $best = max($best, (float) ($run[1] - $run[0] + 1));
        }

        return $best;
    }

    /**
     * First row below the hips where the silhouette splits into two legs, i.e.
     * where the body centreline falls into a gap between two runs. Gaps that
     * sit beside the centreline are arms held away from the torso, not legs.
     *
     * @param  array<string, mixed>  $shape
     */
    private function crotchRow(array $shape): int
    {
        $center = $this->centerX($shape);
        for (
            $fraction = self::LANDMARKS['crotch_search_top'];
            $fraction <= self::LANDMARKS['crotch_search_bottom'];
            $fraction += 0.005
        ) {
            $y = $this->rowAt($shape, $fraction);
            $runs = $shape['rows'][$y] ?? [];
            for ($i = 0, $last = count($runs) - 1; $i < $last; $i++) {
                if ($runs[$i][1] < $center && $center < $runs[$i + 1][0]) {
                    return $y;
                }
            }
        }

        return $this->rowAt($shape, 0.530);
    }

    /**
     * Lowest row where a separated arm run is still present, i.e. the wrist.
     *
     * @param  array<string, mixed>  $shape
     */
    private function wristRow(array $shape): int
    {
        $last = null;
        for ($fraction = 0.26; $fraction <= 0.58; $fraction += 0.01) {
            $y = $this->rowAt($shape, $fraction);
            if (count($shape['rows'][$y] ?? []) >= 3) {
                $last = $y;
            }
        }

        return $last ?? $this->rowAt($shape, 0.480);
    }

    /** @param array<string, mixed> $shape */
    private function narrowestRowBetween(array $shape, float $from, float $to): int
    {
        $bestRow = $this->rowAt($shape, $from);
        $bestWidth = PHP_FLOAT_MAX;
        for ($fraction = $from; $fraction <= $to; $fraction += 0.005) {
            $y = $this->rowAt($shape, $fraction);
            $width = $this->torsoWidthAtRow($shape, $y);
            if ($width > 0 && $width < $bestWidth) {
                $bestWidth = $width;
                $bestRow = $y;
            }
        }

        return $bestRow;
    }

    /**
     * @param  array<string, mixed>  $front
     * @param  array<string, mixed>  $side
     * @return array<int, array<string, mixed>>
     */
    private function buildMeasurements(array $front, array $side, float $height, string $unit): array
    {
        $frontPixelsPerUnit = $front['body'] / $height;
        $quality = min($front['quality'], $side['quality']);

        $rowFraction = static fn (array $shape, int $y): float => ($y - $shape['top']) / max(1, $shape['body'] - 1);

        $crotchRow = $this->crotchRow($front);
        $crotchFraction = $rowFraction($front, $crotchRow);
        $wristRow = $this->wristRow($front);
        $wristFraction = $rowFraction($front, $wristRow);
        $neckRow = $this->narrowestRowBetween($front, self::LANDMARKS['neck_search_top'], self::LANDMARKS['neck_search_bottom']);
        $neckFraction = $rowFraction($front, $neckRow);
        $shoulderRow = $this->rowAt($front, self::LANDMARKS['shoulder']);
        $waistRow = $this->rowAt($front, self::LANDMARKS['waist']);

        $legSpan = max(1.0, $front['bottom'] - $crotchRow);
        $kneeFraction = $crotchFraction + ($legSpan * 0.50) / max(1, $front['body'] - 1);
        $calfFraction = $crotchFraction + ($legSpan * 0.70) / max(1, $front['body'] - 1);
        $ankleFraction = $crotchFraction + ($legSpan * 0.92) / max(1, $front['body'] - 1);
        $thighFraction = $crotchFraction + ($legSpan * 0.10) / max(1, $front['body'] - 1);

        $measurements = [];
        $add = function (string $name, float $value, float $confidence) use (&$measurements, $unit): void {
            if ($value <= 0 || ! is_finite($value)) {
                return;
            }
            $confidence = round(max(0.30, min(0.95, $confidence)), 4);
            $measurements[] = [
                'name' => $name,
                'value' => round($value, 2),
                'unit' => $unit,
                'confidence' => $confidence,
                'requires_confirmation' => $confidence < 0.62,
            ];
        };

        // Girths: front width plus side depth, closed as an ellipse.
        $girths = [
            'neck' => $neckFraction,
            'chest_bust' => self::LANDMARKS['chest_bust'],
            'underbust' => self::LANDMARKS['underbust'],
            'waist' => self::LANDMARKS['waist'],
            'high_hip' => self::LANDMARKS['high_hip'],
            'full_hip' => self::LANDMARKS['full_hip'],
        ];
        foreach ($girths as $name => $fraction) {
            $width = $this->torsoWidth($front, $fraction) / $frontPixelsPerUnit;
            $depth = $this->sideDepth($side, $fraction, $height);
            $add($name, $this->ellipsePerimeter($width / 2, $depth / 2), $this->stability($front, $side, $fraction) * $quality);
        }

        // Limb girths: one arm or one leg, closed with a proportional depth.
        $upperArmWidth = $this->limbWidth($front, self::LANDMARKS['upper_arm']) / $frontPixelsPerUnit;
        $add('upper_arm', $this->ellipsePerimeter($upperArmWidth / 2, $upperArmWidth * 0.92 / 2), $this->stability($front, $side, self::LANDMARKS['upper_arm']) * $quality * 0.9);

        $wristSample = max(0.26, min($wristFraction - 0.01, $crotchFraction - 0.01));
        $wristWidth = $this->limbWidth($front, $wristSample) / $frontPixelsPerUnit;
        $add('wrist', $this->ellipsePerimeter($wristWidth / 2, $wristWidth * 0.78 / 2), $this->stability($front, $side, $wristFraction) * $quality * 0.8);

        foreach ([
            'thigh' => [$thighFraction, 0.95],
            'knee' => [$kneeFraction, 0.92],
            'calf' => [$calfFraction, 0.9],
            'ankle' => [$ankleFraction, 0.8],
        ] as $name => [$fraction, $depthRatio]) {
            $legWidth = $this->legWidth($front, $fraction) / $frontPixelsPerUnit;
            $add($name, $this->ellipsePerimeter($legWidth / 2, $legWidth * $depthRatio / 2), $this->stability($front, $side, $fraction) * $quality * 0.9);
        }

        // Lengths read straight off the calibrated front silhouette.
        $shoulderWidth = $this->torsoWidth($front, self::LANDMARKS['shoulder']) / $frontPixelsPerUnit;
        $armLength = ($wristRow - $shoulderRow) / $frontPixelsPerUnit;
        $torsoLength = ($waistRow - $shoulderRow) / $frontPixelsPerUnit;
        $inseam = ($front['bottom'] - $crotchRow) / $frontPixelsPerUnit;
        $outseam = ($front['bottom'] - $waistRow) / $frontPixelsPerUnit;
        $dressLength = ($this->rowAt($front, $kneeFraction) - $shoulderRow) / $frontPixelsPerUnit;

        $add('height', $height, 0.95);
        $add('shoulder_width', $shoulderWidth, $this->stability($front, $side, self::LANDMARKS['shoulder']) * $quality);
        $add('arm_length', $armLength, $quality * 0.8);
        $add('sleeve_length', $shoulderWidth / 2 + $armLength, $quality * 0.75);
        $add('torso_length', $torsoLength, $quality * 0.85);
        $add('inseam', $inseam, $quality * 0.85);
        $add('outseam', $outseam, $quality * 0.85);
        $add('dress_length', $dressLength, $quality * 0.8);

        // Rises come off the side photo: the front and back edges between the
        // waist row and the crotch row.
        [$frontRise, $backRise] = $this->rises($side, self::LANDMARKS['waist'], $crotchFraction, $height);
        $add('front_rise', $frontRise, $side['quality'] * 0.75);
        $add('back_rise', $backRise, $side['quality'] * 0.75);

        return $measurements;
    }

    /**
     * Width of a single leg: the run immediately left or right of the
     * centreline, so a hand or a sleeve further out is never mistaken for one.
     *
     * @param  array<string, mixed>  $shape
     */
    private function legWidth(array $shape, float $fraction): float
    {
        $y = $this->rowAt($shape, $fraction);
        $runs = $shape['rows'][$y] ?? [];
        if ($runs === []) {
            return 0.0;
        }
        if (count($runs) === 1) {
            // Legs still touch at this height; split the combined span in half.
            return ($runs[0][1] - $runs[0][0] + 1) / 2;
        }

        $center = $this->centerX($shape);
        $best = null;
        $bestDistance = PHP_FLOAT_MAX;
        foreach ($runs as $run) {
            $distance = $center < $run[0]
                ? $run[0] - $center
                : ($center > $run[1] ? $center - $run[1] : 0.0);
            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $best = $run;
            }
        }

        return $best === null ? 0.0 : (float) ($best[1] - $best[0] + 1);
    }

    /** @param array<string, mixed> $side */
    private function sideDepth(array $side, float $fraction, float $height): float
    {
        $pixelsPerUnit = $side['body'] / $height;

        return $this->torsoWidth($side, $fraction) / $pixelsPerUnit;
    }

    /**
     * @param  array<string, mixed>  $side
     * @return array{0: float, 1: float}
     */
    private function rises(array $side, float $waistFraction, float $crotchFraction, float $height): array
    {
        $pixelsPerUnit = $side['body'] / $height;
        $waistRow = $this->rowAt($side, $waistFraction);
        $crotchRow = $this->rowAt($side, $crotchFraction);
        $waistRuns = $side['rows'][$waistRow] ?? [];
        $crotchRuns = $side['rows'][$crotchRow] ?? [];
        if ($waistRuns === [] || $crotchRuns === []) {
            return [0.0, 0.0];
        }
        $waistFront = $waistRuns[0][0];
        $waistBack = $waistRuns[count($waistRuns) - 1][1];
        $crotchFront = $crotchRuns[0][0];
        $crotchBack = $crotchRuns[count($crotchRuns) - 1][1];
        $drop = $crotchRow - $waistRow;

        $frontRise = sqrt($drop ** 2 + ($crotchFront - $waistFront) ** 2) / $pixelsPerUnit;
        $backRise = sqrt($drop ** 2 + ($crotchBack - $waistBack) ** 2) / $pixelsPerUnit;

        return [$frontRise, $backRise];
    }

    /**
     * How steady the silhouette is around a landmark. A body edge that jumps
     * from row to row means a noisy segmentation and a less certain value.
     *
     * @param  array<string, mixed>  $front
     * @param  array<string, mixed>  $side
     */
    private function stability(array $front, array $side, float $fraction): float
    {
        $samples = [];
        foreach ([-0.015, 0.0, 0.015] as $offset) {
            $at = max(0.0, min(1.0, $fraction + $offset));
            $samples[] = $this->torsoWidth($front, $at);
            $samples[] = $this->torsoWidth($side, $at);
        }
        $frontSamples = [$samples[0], $samples[2], $samples[4]];
        $sideSamples = [$samples[1], $samples[3], $samples[5]];

        $spread = static function (array $values): float {
            $mean = array_sum($values) / max(1, count($values));
            if ($mean <= 0.0) {
                return 1.0;
            }
            $deviation = 0.0;
            foreach ($values as $value) {
                $deviation = max($deviation, abs($value - $mean));
            }

            return $deviation / $mean;
        };

        return max(0.30, min(0.95, 1.0 - ($spread($frontSamples) + $spread($sideSamples)) * 1.6));
    }

    /** Ramanujan's approximation of an ellipse perimeter. */
    private function ellipsePerimeter(float $a, float $b): float
    {
        if ($a <= 0 || $b <= 0) {
            return 0.0;
        }

        return M_PI * (3 * ($a + $b) - sqrt((3 * $a + $b) * ($a + 3 * $b)));
    }
}
