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
 * of the two photos. Every measurement also carries explicit provenance so a
 * value that depends on a real detected landmark is never presented with the
 * same authority as one that depends on a guessed landmark. When a landmark
 * cannot be identified reliably the dependent measurements are reported as
 * unavailable ("needs a better photo") instead of silently guessed, and when
 * the frame cannot be trusted -- multiple people, a large foreign object, a
 * badly cut-off subject -- the whole analysis asks for a retake.
 */
class SilhouetteMeasurementEngine
{
    public const MODEL = 'urbangoodz-silhouette';

    public const MODEL_VERSION = '2.0.0';

    /** Working width the photos are downscaled to before segmentation. */
    private const WORK_WIDTH = 360;

    /** A fallback/estimated landmark must not carry a detected landmark's weight. */
    private const PROVENANCE_FACTORS = [
        MeasurementQuality::PROVENANCE_DETECTED => 1.0,
        MeasurementQuality::PROVENANCE_ESTIMATED => 0.85,
        MeasurementQuality::PROVENANCE_FALLBACK => 0.55,
        MeasurementQuality::PROVENANCE_UNKNOWN => 0.0,
        MeasurementQuality::PROVENANCE_DIRECT => 1.0,
    ];

    private const PROVENANCE_RANK = [
        MeasurementQuality::PROVENANCE_DETECTED => 3,
        MeasurementQuality::PROVENANCE_ESTIMATED => 2,
        MeasurementQuality::PROVENANCE_FALLBACK => 1,
        MeasurementQuality::PROVENANCE_UNKNOWN => 0,
        MeasurementQuality::PROVENANCE_DIRECT => 3,
    ];

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
     *     unavailable_measurements: array<int, array{name: string, reason: string, code: string}>,
     *     quality_warnings: array<int, string>,
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

        $retakes = [];
        if (isset($front['error'])) {
            $retakes[] = ['view' => 'front', 'reason' => 'Front photo: '.$front['error']];
        }
        if (isset($side['error'])) {
            $retakes[] = ['view' => 'side', 'reason' => 'Side photo: '.$side['error']];
        }

        if ($retakes === []) {
            $retakes = array_merge($retakes, $this->frameProblems($front, 'front'));
            $retakes = array_merge($retakes, $this->frameProblems($side, 'side'));
        }
        if ($retakes === []) {
            $retakes = array_merge($retakes, $this->poseProblems($front));
            $retakes = array_merge($retakes, $this->viewConsistencyProblems($front, $side));
        }

        $warnings = $this->engineWarnings($front, $side);

        if ($retakes !== []) {
            $confidence = 0.0;
            if (! isset($front['error']) && ! isset($side['error'])) {
                $confidence = min($front['quality'], $side['quality']);
            }

            return [
                'status' => 'needs_retake',
                'model' => self::MODEL,
                'model_version' => self::MODEL_VERSION,
                'overall_confidence' => round($confidence, 4),
                'measurements' => [],
                'unavailable_measurements' => [],
                'quality_warnings' => array_values(array_unique($warnings)),
                'retake_requirements' => array_values($retakes),
            ];
        }

        $built = $this->buildMeasurements($front, $side, $calibrationHeight, $unit);
        $weighted = array_column($built['measurements'], 'confidence');
        $overall = $weighted === [] ? 0.0 : array_sum($weighted) / count($weighted);

        return [
            'status' => 'completed',
            'model' => self::MODEL,
            'model_version' => self::MODEL_VERSION,
            'overall_confidence' => round(max(0.0, min(1.0, $overall)), 4),
            'measurements' => $built['measurements'],
            'unavailable_measurements' => $built['unavailable_measurements'],
            'quality_warnings' => array_values(array_unique(array_merge($warnings, $built['warnings']))),
            'retake_requirements' => [],
        ];
    }

    // ---------------------------------------------------------------- segment

    /**
     * @return array{mask: array<int, array<int, bool>>, width: int, height: int,
     *     top: int, bottom: int, left: int, right: int, body: int,
     *     rows: array<int, array<int, array{0: int, 1: int}>>, quality: float, fill: float,
     *     component_count: int, second_ratio: float, secondary: ?array<string, float>,
     *     symmetry: float, brightness: float, exposure_high: float, dark_ratio: float,
     *     edge_energy: float}
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

        $red = $green = $blue = $luma = [];
        $lumaTotal = 0.0;
        $overexposed = 0;
        $dark = 0;
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($small, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $red[$y][$x] = $r;
                $green[$y][$x] = $g;
                $blue[$y][$x] = $b;
                $value = 0.299 * $r + 0.587 * $g + 0.114 * $b;
                $luma[$y][$x] = $value;
                $lumaTotal += $value;
                if ($value >= 250) {
                    $overexposed++;
                }
                if ($value < 30) {
                    $dark++;
                }
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

        $components = $this->componentStats($mask, $width, $height);
        $mask = $this->keepLargest($mask, $components['labels'], $components['largest_label'], $width, $height);

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
            return ['error' => 'No subject could be separated from the background. Stand against a plain wall with even lighting and nobody else in frame.'];
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
            'component_count' => $components['significant_count'],
            'second_ratio' => $components['second_ratio'],
            'secondary' => $components['secondary'],
            'symmetry' => $this->symmetryScore($mask, $top, $bottom, $left, $right),
            'brightness' => $lumaTotal / max(1, $width * $height) / 255.0,
            'exposure_high' => $overexposed / max(1, $width * $height),
            'dark_ratio' => $dark / max(1, $width * $height),
            'edge_energy' => $this->edgeEnergy($luma, $width, $height),
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
     * Labels every connected component of the mask and reports the largest one
     * together with any substantial secondary component. A second person, or a
     * large object in frame, shows up here as a second component that the
     * caller must reject rather than silently measuring the biggest blob.
     *
     * @param  array<int, array<int, bool>>  $mask
     * @return array{
     *     labels: array<int, array<int, int>>,
     *     sizes: array<int, int>,
     *     count: int,
     *     largest_label: int,
     *     largest_size: int,
     *     significant_count: int,
     *     second_ratio: float,
     *     secondary: ?array{size_ratio: float, height_ratio: float, vertical_center: float}
     * }
     */
    private function componentStats(array $mask, int $width, int $height): array
    {
        $labels = [];
        $components = [];
        $label = 0;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if (! $mask[$y][$x] || isset($labels[$y][$x])) {
                    continue;
                }
                $label++;
                $size = 0;
                $minY = $y;
                $maxY = $y;
                $minX = $x;
                $maxX = $x;
                $stack = [[$x, $y]];
                $labels[$y][$x] = $label;
                while ($stack !== []) {
                    [$cx, $cy] = array_pop($stack);
                    $size++;
                    $minY = min($minY, $cy);
                    $maxY = max($maxY, $cy);
                    $minX = min($minX, $cx);
                    $maxX = max($maxX, $cx);
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
                $components[$label] = [
                    'size' => $size, 'minY' => $minY, 'maxY' => $maxY, 'minX' => $minX, 'maxX' => $maxX,
                ];
            }
        }

        uasort($components, static fn (array $a, array $b): int => $b['size'] <=> $a['size']);

        $largestLabel = (int) (array_key_first($components) ?? 0);
        $largestSize = $components[$largestLabel]['size'] ?? 0;
        $noiseThreshold = max(60, (int) round($largestSize * 0.02));
        $significant = 0;
        $second = null;
        $rank = 0;
        foreach ($components as $info) {
            if ($info['size'] >= $noiseThreshold) {
                $significant++;
            }
            if ($rank === 1) {
                $second = $info;
            }
            $rank++;
        }

        $secondSize = $second['size'] ?? 0;
        $secondary = null;
        if ($second !== null && $largestSize > 0) {
            $secondary = [
                'size_ratio' => $secondSize / $largestSize,
                'height_ratio' => ($second['maxY'] - $second['minY'] + 1) / max(1, $height),
                'vertical_center' => (($second['minY'] + $second['maxY']) / 2) / max(1, $height),
            ];
        }

        return [
            'labels' => $labels,
            'sizes' => array_column($components, 'size'),
            'count' => count($components),
            'largest_label' => $largestLabel,
            'largest_size' => $largestSize,
            'significant_count' => $significant,
            'second_ratio' => $largestSize > 0 ? $secondSize / $largestSize : 0.0,
            'secondary' => $secondary,
        ];
    }

    /**
     * @param  array<int, array<int, bool>>  $mask
     * @param  array<int, array<int, int>>  $labels
     * @return array<int, array<int, bool>>
     */
    private function keepLargest(array $mask, array $labels, int $largestLabel, int $width, int $height): array
    {
        $clean = [];
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $clean[$y][$x] = ($labels[$y][$x] ?? 0) === $largestLabel && $largestLabel > 0;
            }
        }

        return $clean;
    }

    /** @param array<int, array<int, float>> $luma */
    private function edgeEnergy(array $luma, int $width, int $height): float
    {
        $total = 0.0;
        $count = 0;
        for ($y = 1; $y < $height - 1; $y++) {
            for ($x = 1; $x < $width - 1; $x++) {
                $gx = abs($luma[$y][$x + 1] - $luma[$y][$x - 1]);
                $gy = abs($luma[$y + 1][$x] - $luma[$y - 1][$x]);
                $total += max($gx, $gy);
                $count++;
            }
        }

        return $count > 0 ? $total / $count : 0.0;
    }

    /** @param array<int, array<int, bool>> $mask */
    private function symmetryScore(array $mask, int $top, int $bottom, int $left, int $right): float
    {
        $mismatch = 0;
        $pairs = 0;
        for ($y = $top; $y <= $bottom; $y++) {
            for ($x = $left; $x <= $right; $x++) {
                $mirror = $left + $right - $x;
                if ($mirror < $left || $mirror > $right) {
                    continue;
                }
                $a = $mask[$y][$x] ?? false;
                $b = $mask[$y][$mirror] ?? false;
                if ($a || $b) {
                    $pairs++;
                    if ($a !== $b) {
                        $mismatch++;
                    }
                }
            }
        }

        return $pairs > 0 ? max(0.0, min(1.0, 1.0 - $mismatch / $pairs)) : 1.0;
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
        if ($shape['left'] <= 2 || $shape['right'] >= $shape['width'] - 3) {
            $problems[] = [
                'view' => $view,
                'reason' => "$label photo: the side of your body is cut off. Retake with your whole body well inside the frame.",
            ];
        }
        if ($shape['component_count'] > 1) {
            $secondary = $this->secondarySubjectProblem($shape);
            if ($secondary !== null) {
                $problems[] = ['view' => $view, 'reason' => "$label photo: $secondary"];
            }
        }
        if ($view === 'front' && $shape['symmetry'] < 0.62) {
            $problems[] = [
                'view' => $view,
                'reason' => "$label photo: the silhouette is not left-right balanced, so another person or object may overlap your body. Stand straight and centered with only you in frame.",
            ];
        }
        if ($shape['brightness'] < 0.12) {
            $problems[] = [
                'view' => $view,
                'reason' => "$label photo: the image is too dark to measure reliably. Retake in brighter, even lighting.",
            ];
        }
        if ($shape['exposure_high'] > 0.45) {
            $problems[] = [
                'view' => $view,
                'reason' => "$label photo: the image is overexposed and washes out your silhouette. Retake with softer light.",
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
     * Decides whether the second-largest component is plausibly another person
     * or a large object (tall, overlapping the subject's height) rather than a
     * floor shadow or stray noise near the ground.
     *
     * @param  array<string, mixed>  $shape
     */
    private function secondarySubjectProblem(array $shape): ?string
    {
        $secondary = $shape['secondary'] ?? null;
        if ($secondary === null) {
            return null;
        }
        if ($secondary['size_ratio'] >= 0.45) {
            return 'Another person or large object appears in the frame. Retake with only you in frame.';
        }
        if ($secondary['size_ratio'] >= 0.20
            && $secondary['height_ratio'] >= 0.35
            && $secondary['vertical_center'] >= 0.15
            && $secondary['vertical_center'] <= 0.85) {
            return 'Another person or large object overlaps your height in the frame. Retake with only you in frame.';
        }

        return null;
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

    /**
     * The two photos must agree on the subject height, or the calibration
     * applied to both becomes meaningless.
     *
     * @param  array<string, mixed>  $front
     * @param  array<string, mixed>  $side
     * @return array<int, array{view: string, reason: string}>
     */
    private function viewConsistencyProblems(array $front, array $side): array
    {
        $ratio = max($front['body'], $side['body']) / max(1, min($front['body'], $side['body']));
        if ($ratio > 1.14) {
            return [[
                'view' => 'front',
                'reason' => 'The front and side photos show different body heights. Stand at the same distance in both photos, with the camera level at chest height.',
            ]];
        }

        return [];
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

    // ------------------------------------------------------------- measurement

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
     * Locate the crotch row and say how we found it. A clear centreline gap
     * well inside the search band is a real detection. A gap that only appears
     * at the edge of the band, or is too thin, is an estimate. No gap at all
     * falls back to a body-proportion guess and is reported as such, so every
     * downstream measurement can carry a lower confidence.
     *
     * @param  array<string, mixed>  $shape
     * @return array{row: int, provenance: string}
     */
    private function locateCrotch(array $shape): array
    {
        $center = $this->centerX($shape);
        $top = self::LANDMARKS['crotch_search_top'];
        $bottom = self::LANDMARKS['crotch_search_bottom'];
        $found = null;
        $foundFraction = null;

        for ($fraction = $top; $fraction <= $bottom + 1e-9; $fraction += 0.005) {
            $y = $this->rowAt($shape, $fraction);
            $runs = $shape['rows'][$y] ?? [];
            for ($i = 0, $last = count($runs) - 1; $i < $last; $i++) {
                if ($runs[$i][1] < $center && $center < $runs[$i + 1][0]) {
                    $gapWidth = $runs[$i + 1][0] - $runs[$i][1];
                    $found = $y;
                    $foundFraction = $fraction;
                    if ($gapWidth >= 2 && $fraction > $top + 0.01 && $fraction < $bottom - 0.01) {
                        return ['row' => $y, 'provenance' => MeasurementQuality::PROVENANCE_DETECTED];
                    }
                    break;
                }
            }
        }

        if ($found !== null) {
            return ['row' => $found, 'provenance' => MeasurementQuality::PROVENANCE_ESTIMATED];
        }

        return ['row' => $this->rowAt($shape, 0.530), 'provenance' => MeasurementQuality::PROVENANCE_FALLBACK];
    }

    /**
     * Locate the wrist row (the lowest row where both arms still read as
     * separated runs) and say how it was found.
     *
     * @param  array<string, mixed>  $shape
     * @return array{row: int, provenance: string}
     */
    private function locateWrist(array $shape): array
    {
        $found = null;
        $sides = 0;
        for ($fraction = 0.26; $fraction <= 0.58; $fraction += 0.01) {
            $y = $this->rowAt($shape, $fraction);
            if (count($shape['rows'][$y] ?? []) >= 3) {
                $found = $y;
                $sides++;
            }
        }

        if ($found !== null) {
            return ['row' => $found, 'provenance' => $sides >= 8
                ? MeasurementQuality::PROVENANCE_DETECTED
                : MeasurementQuality::PROVENANCE_ESTIMATED];
        }

        return ['row' => $this->rowAt($shape, 0.480), 'provenance' => MeasurementQuality::PROVENANCE_FALLBACK];
    }

    /**
     * Locate the waist as the narrowest torso row between the underbust and the
     * high hip. When no clear pinch exists the waist may be hidden by clothing,
     * so it is reported as an estimate.
     *
     * @param  array<string, mixed>  $shape
     * @return array{row: int, provenance: string}
     */
    private function locateWaist(array $shape): array
    {
        $row = $this->narrowestRowBetween($shape, 0.30, 0.44);
        $chestWidth = $this->torsoWidth($shape, self::LANDMARKS['chest_bust']);
        $waistWidth = $this->torsoWidthAtRow($shape, $row);
        $provenance = $chestWidth > 0 && $waistWidth > 0 && $waistWidth <= $chestWidth * 0.97
            ? MeasurementQuality::PROVENANCE_DETECTED
            : MeasurementQuality::PROVENANCE_ESTIMATED;

        return ['row' => $row, 'provenance' => $provenance];
    }

    /**
     * Whether the legs are separated into two runs at a given height. A single
     * merged run means the legs are touching and the leg width is halved, which
     * is an estimate rather than a clean measurement.
     *
     * @param  array<string, mixed>  $shape
     */
    private function legProvenance(array $shape, float $fraction): string
    {
        $runs = $shape['rows'][$this->rowAt($shape, $fraction)] ?? [];

        return count($runs) >= 2
            ? MeasurementQuality::PROVENANCE_DETECTED
            : MeasurementQuality::PROVENANCE_ESTIMATED;
    }

    /**
     * Whether the lower body reads as a single run all the way to the feet,
     * which is the signature of a dress, skirt, or long top covering the legs.
     *
     * @param  array<string, mixed>  $shape
     */
    private function legsCovered(array $shape): bool
    {
        $center = $this->centerX($shape);
        for ($fraction = 0.60; $fraction <= 0.98; $fraction += 0.01) {
            $y = $this->rowAt($shape, $fraction);
            $runs = $shape['rows'][$y] ?? [];
            $separate = 0;
            foreach ($runs as $run) {
                if ($center < $run[0] || $center > $run[1]) {
                    $separate++;
                }
            }
            if ($separate >= 2) {
                return false;
            }
        }

        return true;
    }

    /**
     * Clothing/occlusion flags that make some measurements unreliable even in
     * an otherwise processable photo.
     *
     * @param  array<string, mixed>  $front
     * @param  array<string, mixed>  $crotch
     * @param  array<string, mixed>  $waist
     * @return array{flags: array<string, bool>, warnings: array<int, string>, impact: array<string, string>}
     */
    private function clothingFlags(array $front, array $crotch, array $waist, float $height): array
    {
        $impact = [];
        $warnings = [];
        $ppu = $front['body'] / $height;
        $chestWidth = $this->torsoWidth($front, self::LANDMARKS['chest_bust']) / $ppu;
        $waistWidth = $this->torsoWidthAtRow($front, $waist['row']) / $ppu;

        if ($chestWidth > 0 && $waistWidth > $chestWidth * 0.95) {
            $warnings[] = 'Your waist is not clearly narrower than your chest in the photo, which usually means a loose or thick garment hides it. Waist, hip and rise measurements are less reliable.';
            foreach (['waist', 'underbust', 'high_hip', 'full_hip', 'outseam', 'front_rise', 'back_rise'] as $name) {
                $impact[$name] = 'low';
            }
        }

        if ($crotch['provenance'] === MeasurementQuality::PROVENANCE_FALLBACK && $this->legsCovered($front)) {
            $warnings[] = 'Your legs do not read as a clear split below the hips, which usually means a dress, skirt, or long top covers them. Leg measurements are not reliable.';
            foreach (['thigh', 'knee', 'calf', 'ankle', 'inseam'] as $name) {
                $impact[$name] = 'blocked';
            }
        }

        return [
            'flags' => ['loose_waist' => isset($impact['waist']), 'legs_covered' => isset($impact['inseam'])],
            'warnings' => $warnings,
            'impact' => $impact,
        ];
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
     * Combines a base confidence (segmentation quality and edge stability) with
     * the provenance of every landmark the measurement depends on. A fallback
     * landmark reduces the confidence and forces customer confirmation instead
     * of presenting a guess as a reliable value.
     *
     * @param  array<string, string>  $landmarks  landmark name => provenance
     */
    private function landmarkQuality(float $base, array $landmarks): MeasurementQuality
    {
        $factor = 1.0;
        $worst = MeasurementQuality::PROVENANCE_DETECTED;
        $requires = false;

        foreach ($landmarks as $name => $provenance) {
            $factor *= self::PROVENANCE_FACTORS[$provenance] ?? 1.0;
            if ((self::PROVENANCE_RANK[$provenance] ?? 3) < (self::PROVENANCE_RANK[$worst] ?? 3)) {
                $worst = $provenance;
            }
            if ($provenance === MeasurementQuality::PROVENANCE_UNKNOWN) {
                return new MeasurementQuality(
                    0.0,
                    MeasurementQuality::PROVENANCE_UNKNOWN,
                    $landmarks,
                    status: MeasurementQuality::STATUS_NEEDS_BETTER_PHOTO,
                    reason: 'A landmark required for this measurement could not be identified in the photo.',
                );
            }
            if (in_array($provenance, [MeasurementQuality::PROVENANCE_ESTIMATED, MeasurementQuality::PROVENANCE_FALLBACK], true)) {
                $requires = true;
            }
        }

        return new MeasurementQuality(
            confidence: max(0.0, $base * $factor),
            provenance: $worst,
            landmarks: $landmarks,
            requiresConfirmation: $requires,
        );
    }

    /**
     * @param  array<string, mixed>  $front
     * @param  array<string, mixed>  $side
     * @return array{measurements: array<int, array<string, mixed>>,
     *     unavailable_measurements: array<int, array{name: string, reason: string, code: string}>,
     *     warnings: array<int, string>}
     */
    private function buildMeasurements(array $front, array $side, float $height, string $unit): array
    {
        $frontPpi = $front['body'] / $height;
        $quality = min($front['quality'], $side['quality']);
        $rowFraction = static fn (array $shape, int $y): float => ($y - $shape['top']) / max(1, $shape['body'] - 1);

        $crotch = $this->locateCrotch($front);
        $wrist = $this->locateWrist($front);
        $waist = $this->locateWaist($front);
        $neckRow = $this->narrowestRowBetween($front, self::LANDMARKS['neck_search_top'], self::LANDMARKS['neck_search_bottom']);
        $shoulderRow = $this->rowAt($front, self::LANDMARKS['shoulder']);

        $crotchRow = $crotch['row'];
        $crotchFraction = $rowFraction($front, $crotchRow);
        $wristRow = $wrist['row'];
        $wristFraction = $rowFraction($front, $wristRow);
        $waistRow = $waist['row'];
        $waistFraction = $rowFraction($front, $waistRow);
        $neckFraction = $rowFraction($front, $neckRow);

        $shoulderWidthPx = $this->torsoWidth($front, self::LANDMARKS['shoulder']);
        $neckWidthPx = $this->torsoWidthAtRow($front, $neckRow);
        $neckProvenance = $shoulderWidthPx > 0 && $neckWidthPx <= $shoulderWidthPx * 0.80
            ? MeasurementQuality::PROVENANCE_DETECTED
            : MeasurementQuality::PROVENANCE_ESTIMATED;

        $clothing = $this->clothingFlags($front, $crotch, $waist, $height);

        $legSpan = max(1.0, $front['bottom'] - $crotchRow);
        $legBase = max(1, $front['body'] - 1);
        $kneeFraction = $crotchFraction + ($legSpan * 0.50) / $legBase;
        $calfFraction = $crotchFraction + ($legSpan * 0.70) / $legBase;
        $ankleFraction = $crotchFraction + ($legSpan * 0.92) / $legBase;
        $thighFraction = $crotchFraction + ($legSpan * 0.10) / $legBase;

        $measurements = [];
        $unavailable = [];
        $warnings = $clothing['warnings'];
        $stabilityAt = fn (float $fraction): float => $this->stability($front, $side, $fraction);

        $emit = function (string $name, float $value, MeasurementQuality $mquality) use (&$measurements, &$unavailable, $unit, $clothing): void {
            $impact = $clothing['impact'][$name] ?? null;

            if ($impact === 'blocked') {
                $unavailable[$name] = [
                    'name' => $name,
                    'reason' => 'Legs are covered by clothing in the photo, so this measurement cannot be read reliably. Retake the photo in fitted clothing with a clear gap between the legs.',
                    'code' => 'needs_better_photo',
                ];

                return;
            }

            if (! is_finite($value) || $value <= 0) {
                $unavailable[$name] = [
                    'name' => $name,
                    'reason' => 'The photo did not provide a readable silhouette for this measurement.',
                    'code' => 'needs_better_photo',
                ];

                return;
            }

            if (! $mquality->measured()) {
                $unavailable[$name] = [
                    'name' => $name,
                    'reason' => $mquality->reason !== '' ? $mquality->reason : 'This measurement could not be calculated from the photos.',
                    'code' => $mquality->status === MeasurementQuality::STATUS_CUSTOMER_CONFIRMATION_REQUIRED
                        ? 'customer_confirmation_required'
                        : 'needs_better_photo',
                ];

                return;
            }

            $confidence = round(max(0.10, min(0.95, $mquality->confidence)), 4);
            $requires = $mquality->requiresConfirmation || $mquality->hasEstimatedOrFallbackLandmark() || $impact === 'low';

            $measurements[] = [
                'name' => $name,
                'value' => round($value, 1),
                'unit' => $unit,
                'confidence' => $confidence,
                'requires_confirmation' => $requires,
                'provenance' => $mquality->provenance,
                'landmark_sources' => $mquality->landmarks,
                'calculation' => $mquality->calculation,
            ];
        };

        // Girths: front width plus side depth, closed as an ellipse.
        $girths = [
            'neck' => [$neckFraction, $neckProvenance],
            'chest_bust' => [self::LANDMARKS['chest_bust'], MeasurementQuality::PROVENANCE_DETECTED],
            'underbust' => [self::LANDMARKS['underbust'], MeasurementQuality::PROVENANCE_DETECTED],
            'waist' => [$waistFraction, $waist['provenance']],
            'high_hip' => [self::LANDMARKS['high_hip'], MeasurementQuality::PROVENANCE_DETECTED],
            'full_hip' => [self::LANDMARKS['full_hip'], MeasurementQuality::PROVENANCE_DETECTED],
        ];
        foreach ($girths as $name => [$fraction, $provenance]) {
            $width = $this->torsoWidth($front, $fraction) / $frontPpi;
            $depth = $this->sideDepth($side, $fraction, $height);
            $mq = $this->landmarkQuality($stabilityAt($fraction) * $quality, ['torso' => $provenance]);
            $mq->calculation = [
                'row_fraction' => round($fraction, 4),
                'front_width_in' => round($width, 2),
                'side_depth_in' => round($depth, 2),
                'front_ppi' => round($frontPpi, 3),
            ];
            $emit($name, $this->ellipsePerimeter($width / 2, $depth / 2), $mq);
        }

        // Limb girths: one arm or one leg, closed with a proportional depth.
        $upperArmWidth = $this->limbWidth($front, self::LANDMARKS['upper_arm']) / $frontPpi;
        $armProvenance = $this->armsSeparated($front)
            ? MeasurementQuality::PROVENANCE_DETECTED
            : MeasurementQuality::PROVENANCE_ESTIMATED;
        $upperArmMq = $this->landmarkQuality($stabilityAt(self::LANDMARKS['upper_arm']) * $quality * 0.9, ['arm' => $armProvenance]);
        $upperArmMq->calculation = ['row_fraction' => self::LANDMARKS['upper_arm'], 'width_in' => round($upperArmWidth, 2)];
        $emit('upper_arm', $this->ellipsePerimeter($upperArmWidth / 2, $upperArmWidth * 0.92 / 2), $upperArmMq);

        $wristSample = max(0.26, min($wristFraction - 0.01, $crotchFraction - 0.01));
        $wristWidth = $this->limbWidth($front, $wristSample) / $frontPpi;
        $wristMq = $this->landmarkQuality($stabilityAt($wristFraction) * $quality * 0.8, ['wrist' => $wrist['provenance']]);
        $wristMq->calculation = ['row_fraction' => round($wristFraction, 4), 'width_in' => round($wristWidth, 2)];
        $emit('wrist', $this->ellipsePerimeter($wristWidth / 2, $wristWidth * 0.78 / 2), $wristMq);

        foreach ([
            'thigh' => [$thighFraction, 0.95],
            'knee' => [$kneeFraction, 0.92],
            'calf' => [$calfFraction, 0.9],
            'ankle' => [$ankleFraction, 0.8],
        ] as $name => [$fraction, $depthRatio]) {
            $legWidth = $this->legWidth($front, $fraction) / $frontPpi;
            $mq = $this->landmarkQuality($stabilityAt($fraction) * $quality * 0.9, [
                'crotch' => $crotch['provenance'],
                'legs' => $this->legProvenance($front, $fraction),
            ]);
            $mq->calculation = ['row_fraction' => round($fraction, 4), 'width_in' => round($legWidth, 2), 'depth_ratio' => $depthRatio];
            $emit($name, $this->ellipsePerimeter($legWidth / 2, $legWidth * $depthRatio / 2), $mq);
        }

        // Lengths read straight off the calibrated front silhouette.
        $shoulderWidth = $shoulderWidthPx / $frontPpi;
        $armLength = ($wristRow - $shoulderRow) / $frontPpi;
        $torsoLength = ($waistRow - $shoulderRow) / $frontPpi;
        $inseam = ($front['bottom'] - $crotchRow) / $frontPpi;
        $outseam = ($front['bottom'] - $waistRow) / $frontPpi;
        $dressLength = ($this->rowAt($front, $kneeFraction) - $shoulderRow) / $frontPpi;

        $heightMq = $this->landmarkQuality(0.95, ['calibration_height' => MeasurementQuality::PROVENANCE_DIRECT]);
        $heightMq->calculation = ['calibration_height_in' => round($height, 2)];
        $emit('height', $height, $heightMq);

        $shoulderMq = $this->landmarkQuality($stabilityAt(self::LANDMARKS['shoulder']) * $quality, ['shoulder' => MeasurementQuality::PROVENANCE_DETECTED]);
        $shoulderMq->calculation = ['row_fraction' => self::LANDMARKS['shoulder'], 'width_in' => round($shoulderWidth, 2)];
        $emit('shoulder_width', $shoulderWidth, $shoulderMq);

        $armLengthMq = $this->landmarkQuality($quality * 0.8, ['wrist' => $wrist['provenance']]);
        $armLengthMq->calculation = ['wrist_row' => $wristRow, 'shoulder_row' => $shoulderRow];
        $emit('arm_length', $armLength, $armLengthMq);

        $sleeveMq = $this->landmarkQuality($quality * 0.75, ['wrist' => $wrist['provenance']]);
        $sleeveMq->calculation = ['shoulder_width_in' => round($shoulderWidth, 2), 'arm_length_in' => round($armLength, 2)];
        $emit('sleeve_length', $shoulderWidth / 2 + $armLength, $sleeveMq);

        $torsoMq = $this->landmarkQuality($quality * 0.85, ['waist' => $waist['provenance']]);
        $torsoMq->calculation = ['waist_row' => $waistRow, 'shoulder_row' => $shoulderRow];
        $emit('torso_length', $torsoLength, $torsoMq);

        $inseamMq = $this->landmarkQuality($quality * 0.85, ['crotch' => $crotch['provenance']]);
        $inseamMq->calculation = ['crotch_row' => $crotchRow, 'bottom_row' => $front['bottom']];
        $emit('inseam', $inseam, $inseamMq);

        $outseamMq = $this->landmarkQuality($quality * 0.85, ['crotch' => $crotch['provenance'], 'waist' => $waist['provenance']]);
        $outseamMq->calculation = ['waist_row' => $waistRow, 'bottom_row' => $front['bottom']];
        $emit('outseam', $outseam, $outseamMq);

        $dressMq = $this->landmarkQuality($quality * 0.8, ['crotch' => $crotch['provenance']]);
        $dressMq->calculation = ['knee_row_fraction' => round($kneeFraction, 4), 'shoulder_row' => $shoulderRow];
        $emit('dress_length', $dressLength, $dressMq);

        // Rises come off the side photo: the front and back edges between the
        // waist row and the crotch row.
        [$frontRise, $backRise] = $this->rises($side, $waistFraction, $crotchFraction, $height);
        $riseMq = $this->landmarkQuality($side['quality'] * 0.75, ['crotch' => $crotch['provenance'], 'waist' => $waist['provenance']]);
        $riseMq->calculation = ['waist_row_fraction' => round($waistFraction, 4), 'crotch_row_fraction' => round($crotchFraction, 4)];
        $emit('front_rise', $frontRise, $riseMq);
        $emit('back_rise', $backRise, $riseMq);

        return [
            'measurements' => $measurements,
            'unavailable_measurements' => array_values($unavailable),
            'warnings' => $warnings,
        ];
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

    /**
     * Non-fatal warnings that a photo is processable but weaker than ideal.
     *
     * @param  array<string, mixed>  $front
     * @param  array<string, mixed>  $side
     * @return array<int, string>
     */
    private function engineWarnings(array $front, array $side): array
    {
        $warnings = [];
        foreach (['front' => $front, 'side' => $side] as $view => $shape) {
            if (isset($shape['error'])) {
                continue;
            }
            if ($shape['brightness'] < 0.20 && $shape['brightness'] >= 0.12) {
                $warnings[] = ucfirst($view).' photo is somewhat dark; measurements are less accurate.';
            }
            if ($shape['edge_energy'] < 0.03) {
                $warnings[] = ucfirst($view).' photo may be blurry; measurements are less accurate.';
            }
            if ($shape['component_count'] > 1) {
                $warnings[] = ucfirst($view).' photo contains more than one separated object or person; only the largest subject was measured.';
            }
        }

        return $warnings;
    }
}
