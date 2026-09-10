<?php

namespace Tests\Unit;

use App\Services\FashionFit\PhotoQualityAnalyzer;
use App\Services\FashionFit\SilhouetteMeasurementEngine;
use Tests\TestCase;

/**
 * Hardening coverage for the honest-measurement guarantees: the engine must
 * reject multi-person / foreign-object / unusable frames, propagate landmark
 * provenance into every measurement, report measurement-specific unavailability
 * instead of fabricating values, and never present false precision.
 */
class FashionFitHardeningTest extends TestCase
{
    private const HEIGHT_INCHES = 70.0;

    private const PPI = 20;

    private const FRONT_PROFILE = [
        [0.000, 1.20], [0.025, 2.60], [0.070, 3.10], [0.110, 2.80], [0.130, 2.52],
        [0.152, 2.40], [0.185, 9.00], [0.260, 6.50],
        [0.305, 6.00], [0.375, 5.50], [0.425, 6.00], [0.480, 6.75], [0.530, 6.60],
    ];

    private const LEG_PROFILE = [
        [0.530, 4.30], [0.577, 4.00], [0.765, 2.40], [0.859, 2.50], [0.962, 1.60], [1.000, 1.80],
    ];

    private const LEG_CENTER = [[0.530, 2.40], [1.000, 2.10]];

    private const SIDE_PROFILE = [
        [0.000, 1.60], [0.025, 3.10], [0.070, 3.70], [0.110, 3.30], [0.130, 2.80],
        [0.152, 2.40], [0.185, 4.60], [0.260, 4.50],
        [0.305, 4.20], [0.375, 4.00], [0.425, 4.40], [0.480, 4.75], [0.530, 4.20],
        [0.577, 3.60], [0.765, 2.30], [0.859, 2.60], [0.962, 1.70], [1.000, 1.70],
    ];

    public function test_good_photos_carry_provenance_without_false_precision(): void
    {
        $result = $this->engine()->measure($this->frontPhoto(), $this->sidePhoto(), self::HEIGHT_INCHES, 'in');

        $this->assertSame('completed', $result['status']);
        $this->assertSame(SilhouetteMeasurementEngine::MODEL_VERSION, $result['model_version']);

        $provenance = ['detected', 'estimated', 'fallback', 'unknown', 'direct'];
        foreach ($result['measurements'] as $measurement) {
            $this->assertContains($measurement['provenance'], $provenance, $measurement['name'].' provenance must be explicit.');
            $this->assertIsArray($measurement['landmark_sources'], $measurement['name'].' must carry landmark provenance.');
            $this->assertNotSame('unknown', $measurement['provenance'], $measurement['name'].' must be traceable on a clean photo.');
            $this->assertSame((float) round($measurement['value'], 1), (float) $measurement['value'], $measurement['name'].' must not show false precision.');
        }
    }

    public function test_two_people_in_frame_are_rejected_as_a_retake(): void
    {
        $result = $this->engine()->measure($this->frontPhoto(twoPeople: true), $this->sidePhoto(), self::HEIGHT_INCHES, 'in');

        $this->assertSame('needs_retake', $result['status'], 'Reasons: '.json_encode($result['retake_requirements'] ?? []));
        $this->assertSame([], $result['measurements']);
        $reasons = strtolower(implode(' ', array_column($result['retake_requirements'], 'reason')));
        $this->assertStringContainsString('person', $reasons);
    }

    public function test_large_foreign_object_in_frame_is_rejected_as_a_retake(): void
    {
        $result = $this->engine()->measure($this->frontPhoto(foreignObject: true), $this->sidePhoto(), self::HEIGHT_INCHES, 'in');

        $this->assertSame('needs_retake', $result['status'], 'Reasons: '.json_encode($result['retake_requirements'] ?? []));
        $this->assertSame([], $result['measurements']);
        $reasons = strtolower(implode(' ', array_column($result['retake_requirements'], 'reason')));
        $this->assertStringContainsString('object', $reasons);
    }

    public function test_covered_legs_report_inseam_as_unavailable_while_height_is_measured(): void
    {
        $result = $this->engine()->measure($this->frontPhoto(dress: true), $this->sidePhoto(), self::HEIGHT_INCHES, 'in');

        $this->assertSame('completed', $result['status'], 'A covered-leg photo must still measure the upper body.');
        $values = $this->byName($result['measurements']);
        $this->assertArrayHasKey('height', $values);
        $this->assertArrayHasKey('chest_bust', $values);

        $unavailable = $this->byName($result['unavailable_measurements']);
        $this->assertArrayHasKey('inseam', $unavailable, 'Inseam must be reported unavailable when the legs are covered.');
        $this->assertSame('needs_better_photo', $unavailable['inseam']['code']);
        $this->assertStringContainsString('covered', strtolower($unavailable['inseam']['reason']));
    }

    public function test_unpinched_waist_is_never_presented_as_a_high_confidence_detection(): void
    {
        $result = $this->engine()->measure($this->frontPhoto(straightTorso: true), $this->sidePhoto(), self::HEIGHT_INCHES, 'in');

        $this->assertSame('completed', $result['status'], 'Reasons: '.json_encode($result['retake_requirements'] ?? []));
        $values = $this->byName($result['measurements']);
        $this->assertNotSame('detected', $values['waist']['provenance'], 'A hidden waist must not be reported as detected.');
        $this->assertTrue($values['waist']['requires_confirmation'], 'A guessed waist must force customer confirmation.');
        $this->assertLessThan(1.0, (float) $values['waist']['confidence']);
    }

    public function test_dark_photos_fail_closed_with_a_retake_instead_of_measurements(): void
    {
        $result = $this->engine()->measure(
            $this->frontPhoto(shade: 0.08),
            $this->sidePhoto(shade: 0.08),
            self::HEIGHT_INCHES,
            'in',
        );

        $this->assertSame('needs_retake', $result['status']);
        $this->assertSame([], $result['measurements']);
    }

    public function test_upload_quality_analyzer_flags_dark_and_blurry_photos(): void
    {
        $analyzer = new PhotoQualityAnalyzer();

        $good = $analyzer->analyze($this->frontPhoto());
        $this->assertFalse($good['dark']);
        $this->assertFalse($good['overexposed']);
        $this->assertFalse($good['blurry']);
        $this->assertSame([], $good['warnings']);

        $dark = $analyzer->analyze($this->frontPhoto(shade: 0.08));
        $this->assertTrue($dark['dark']);
        $this->assertStringContainsString('dark', strtolower(implode(' ', $dark['warnings'])));

        $blurry = $analyzer->analyze($this->blurred($this->frontPhoto()));
        $this->assertTrue($blurry['blurry']);
        $this->assertStringContainsString('blurry', strtolower(implode(' ', $blurry['warnings'])));
    }

    // ------------------------------------------------------------------ setup

    private function engine(): SilhouetteMeasurementEngine
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('The GD extension is required for the Fashion Fit silhouette engine.');
        }

        return new SilhouetteMeasurementEngine();
    }

    /** @param array<int, array<string, mixed>> $measurements @return array<string, float> */
    private function byName(array $measurements): array
    {
        $values = [];
        foreach ($measurements as $measurement) {
            $values[$measurement['name']] = $measurement;
        }

        return $values;
    }

    private function frontPhoto(
        bool $twoPeople = false,
        bool $foreignObject = false,
        bool $dress = false,
        bool $straightTorso = false,
        float $shade = 1.0,
    ): string {
        return $this->render(function (float $fraction) use ($dress, $straightTorso): array {
            $spans = [];
            $profile = match (true) {
                $straightTorso => $this->straightTorsoProfile(),
                default => self::FRONT_PROFILE,
            };
            $half = $this->interpolate($profile, $fraction);
            if ($fraction < 0.530) {
                $spans[] = [-$half, $half];
                if ($fraction >= 0.210 && $fraction <= 0.500) {
                    $center = $this->interpolate([[0.210, 8.60], [0.300, 9.60], [0.500, 10.00]], $fraction);
                    $width = $this->interpolate([[0.210, 1.85], [0.270, 1.80], [0.500, 1.05]], $fraction);
                    $spans[] = [$center - $width, $center + $width];
                    $spans[] = [-$center - $width, -$center + $width];
                }

                return $spans;
            }

            if ($dress) {
                // A single merged run to the feet: the signature of a dress.
                return [[-$half, $half]];
            }

            $legHalf = $this->interpolate(self::LEG_PROFILE, $fraction);
            $legCenter = $this->interpolate(self::LEG_CENTER, $fraction);
            $spans[] = [$legCenter - $legHalf, $legCenter + $legHalf];
            $spans[] = [-$legCenter - $legHalf, -$legCenter + $legHalf];

            return $spans;
        }, twoPeople: $twoPeople, foreignObject: $foreignObject, shade: $shade);
    }

    private function sidePhoto(float $shade = 1.0): string
    {
        return $this->render(function (float $fraction): array {
            $half = $this->interpolate(self::SIDE_PROFILE, $fraction);
            $shift = $fraction >= 0.962 ? -1.4 : 0.0;

            return [[-$half + $shift, $half + $shift]];
        }, shade: $shade);
    }

    private function straightTorsoProfile(): array
    {
        // The shoulders stay as wide as the normal pose so the arms remain
        // connected to the torso; only the waist pinch is removed (uniform
        // width from chest to hips, so a waist landmark cannot be located).
        return [
            [0.000, 1.20], [0.025, 2.60], [0.070, 3.10], [0.110, 2.80], [0.130, 2.52],
            [0.152, 2.40], [0.185, 9.00], [0.260, 6.50],
            [0.305, 6.50], [0.375, 6.50], [0.425, 6.50], [0.480, 6.50], [0.530, 6.50],
        ];
    }

    /**
     * @param  callable(float): array<int, array{0: float, 1: float}>  $spans
     */
    private function render(
        callable $spans,
        bool $twoPeople = false,
        bool $foreignObject = false,
        float $shade = 1.0,
    ): string {
        $bodyPixels = (int) round(self::HEIGHT_INCHES * self::PPI);
        $topMargin = 90;
        $bottomMargin = 90;
        $width = $twoPeople ? 1200 : 900;
        $height = $topMargin + $bodyPixels + $bottomMargin;

        $image = imagecreatetruecolor($width, $height);
        $background = imagecolorallocate($image, (int) round(232 * $shade), (int) round(230 * $shade), (int) round(226 * $shade));
        $body = imagecolorallocate($image, (int) round(58 * $shade), (int) round(56 * $shade), (int) round(64 * $shade));
        imagefilledrectangle($image, 0, 0, $width, $height, $background);

        // Wide subjects reach +/-11 inches from center, so a second person must
        // sit well clear of the subject to stay a distinct component.
        $centers = $twoPeople ? [-240, 240] : [0];

        foreach ($centers as $centerOffset) {
            $centerX = intdiv($width, 2) + $centerOffset;
            for ($row = 0; $row < $bodyPixels; $row++) {
                $y = $topMargin + $row;
                $fraction = $row / max(1, $bodyPixels - 1);
                foreach ($spans($fraction) as [$from, $to]) {
                    $x1 = $centerX + (int) round($from * self::PPI);
                    $x2 = $centerX + (int) round($to * self::PPI);
                    if ($x2 < $x1) {
                        [$x1, $x2] = [$x2, $x1];
                    }
                    imagefilledrectangle($image, max(0, $x1), $y, min($width - 1, $x2), $y, $body);
                }
            }
        }

        if ($foreignObject) {
            // A tall box well clear of the subject on the right side. The subject
            // reaches about x=672 at the outstretched arm, so the box starts past
            // x=690 with a visible gap of background between them.
            $boxLeft = 690;
            $boxRight = 790;
            $boxTop = $topMargin + (int) round(0.15 * $bodyPixels);
            $boxBottom = $topMargin + (int) round(0.70 * $bodyPixels);
            imagefilledrectangle($image, $boxLeft, $boxTop, $boxRight, $boxBottom, $body);
        }

        ob_start();
        imagepng($image);
        $binary = (string) ob_get_clean();
        imagedestroy($image);

        return $binary;
    }

    /** Downscaling then resampling back up smears every edge far enough to read as blurry. */
    private function blurred(string $binary): string
    {
        $image = imagecreatefromstring($binary);
        $w = imagesx($image);
        $h = imagesy($image);
        $tiny = imagescale($image, max(8, intdiv($w, 20)), max(8, intdiv($h, 20)));
        $blurred = imagescale($tiny, $w, $h, IMG_BILINEAR_FIXED);
        ob_start();
        imagepng($blurred);
        $out = (string) ob_get_clean();
        imagedestroy($image);
        imagedestroy($tiny);
        imagedestroy($blurred);

        return $out;
    }

    /** @param array<int, array{0: float, 1: float}> $profile */
    private function interpolate(array $profile, float $fraction): float
    {
        $keys = array_column($profile, 0);
        $values = array_column($profile, 1);
        if ($fraction <= $keys[0]) {
            return $values[0];
        }
        $last = count($keys) - 1;
        if ($fraction >= $keys[$last]) {
            return $values[$last];
        }
        for ($i = 0; $i < $last; $i++) {
            if ($fraction >= $keys[$i] && $fraction <= $keys[$i + 1]) {
                $span = $keys[$i + 1] - $keys[$i];
                $ratio = $span == 0.0 ? 0.0 : ($fraction - $keys[$i]) / $span;

                return $values[$i] + ($values[$i + 1] - $values[$i]) * $ratio;
            }
        }

        return $values[$last];
    }
}
