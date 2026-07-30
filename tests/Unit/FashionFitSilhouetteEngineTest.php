<?php

namespace Tests\Unit;

use App\Services\FashionFit\FashionFitAnalysisService;
use App\Services\FashionFit\SilhouetteMeasurementEngine;
use Tests\TestCase;

/**
 * Renders synthetic front and side photographs of a body with known
 * dimensions, then checks the engine recovers those dimensions from the pixels.
 */
class FashionFitSilhouetteEngineTest extends TestCase
{
    private const HEIGHT_INCHES = 70.0;

    private const PIXELS_PER_INCH = 20;

    /**
     * Profiles are [fraction of body height, inches] pairs. They are lists, not
     * maps, because PHP truncates float array keys to integers.
     */
    private const FRONT_PROFILE = [
        [0.000, 1.20], [0.025, 2.60], [0.070, 3.10], [0.110, 2.80], [0.130, 2.52],
        [0.152, 2.40], [0.185, 9.00], [0.260, 6.50],
        [0.305, 6.00], [0.375, 5.50], [0.425, 6.00], [0.480, 6.75], [0.530, 6.60],
    ];

    private const SIDE_PROFILE = [
        [0.000, 1.60], [0.025, 3.10], [0.070, 3.70], [0.110, 3.30], [0.130, 2.80],
        [0.152, 2.40], [0.185, 4.60], [0.260, 4.50],
        [0.305, 4.20], [0.375, 4.00], [0.425, 4.40], [0.480, 4.75], [0.530, 4.20],
        [0.577, 3.60], [0.765, 2.30], [0.859, 2.60], [0.962, 1.70], [1.000, 1.70],
    ];

    private const LEG_PROFILE = [
        [0.530, 4.30], [0.577, 4.00], [0.765, 2.40], [0.859, 2.50], [0.962, 1.60], [1.000, 1.80],
    ];

    private const LEG_CENTER = [[0.530, 2.40], [1.000, 2.10]];

    private const ARM_CENTER = [[0.210, 8.60], [0.300, 9.60], [0.500, 10.00]];

    private const ARM_HALF_WIDTH = [[0.210, 1.85], [0.270, 1.80], [0.500, 1.05]];

    public function test_engine_recovers_known_dimensions_from_front_and_side_photos(): void
    {
        $result = $this->engine()->measure(
            $this->frontPhoto(),
            $this->sidePhoto(),
            self::HEIGHT_INCHES,
            'in',
        );

        $this->assertSame('completed', $result['status'], 'Engine asked for a retake: '.json_encode($result['retake_requirements']));
        $this->assertSame(SilhouetteMeasurementEngine::MODEL, $result['model']);

        $values = $this->byName($result['measurements']);

        // Circumferences closed from front width and side depth.
        $this->assertEqualsWithDelta(34.9, $values['chest_bust'], 4.0);
        $this->assertEqualsWithDelta(30.0, $values['waist'], 4.0);
        $this->assertEqualsWithDelta(36.7, $values['full_hip'], 4.5);
        $this->assertEqualsWithDelta(15.1, $values['neck'], 3.0);

        // Lengths read straight off the calibrated silhouette.
        $this->assertEqualsWithDelta(70.0, $values['height'], 0.01);
        $this->assertEqualsWithDelta(18.0, $values['shoulder_width'], 1.5);
        $this->assertEqualsWithDelta(32.9, $values['inseam'], 2.5);
        $this->assertEqualsWithDelta(43.8, $values['outseam'], 3.0);
        $this->assertEqualsWithDelta(22.1, $values['arm_length'], 3.0);
    }

    public function test_every_supported_measurement_is_produced_and_validates(): void
    {
        $result = $this->engine()->measure($this->frontPhoto(), $this->sidePhoto(), self::HEIGHT_INCHES, 'in');

        $produced = array_column($result['measurements'], 'name');
        $this->assertSame([], array_diff(config('fashion_fit_ai.allowed_measurements'), $produced),
            'The engine did not produce every supported measurement.');

        foreach ($result['measurements'] as $measurement) {
            $this->assertGreaterThan(0, $measurement['value'], $measurement['name'].' must be positive.');
            $this->assertGreaterThan(0, $measurement['confidence']);
            $this->assertLessThanOrEqual(1, $measurement['confidence']);
            $this->assertIsBool($measurement['requires_confirmation']);
        }

        // The structured contract the queued analysis enforces must accept it.
        $validated = app(FashionFitAnalysisService::class)->validateResult($result);
        $this->assertSame('completed', $validated['status']);
    }

    public function test_measurements_scale_with_the_calibration_height(): void
    {
        $engine = $this->engine();
        $front = $this->frontPhoto();
        $side = $this->sidePhoto();

        $small = $this->byName($engine->measure($front, $side, 60.0, 'in')['measurements']);
        $large = $this->byName($engine->measure($front, $side, 72.0, 'in')['measurements']);

        $this->assertEqualsWithDelta(72.0 / 60.0, $large['waist'] / $small['waist'], 0.02);
        $this->assertEqualsWithDelta(72.0 / 60.0, $large['inseam'] / $small['inseam'], 0.02);
    }

    public function test_units_are_carried_through_unchanged(): void
    {
        $result = $this->engine()->measure($this->frontPhoto(), $this->sidePhoto(), 178.0, 'cm');

        foreach ($result['measurements'] as $measurement) {
            $this->assertSame('cm', $measurement['unit']);
        }
    }

    public function test_cut_off_feet_are_reported_as_a_retake_instead_of_measurements(): void
    {
        $result = $this->engine()->measure(
            $this->frontPhoto(cropFeet: true),
            $this->sidePhoto(),
            self::HEIGHT_INCHES,
            'in',
        );

        $this->assertSame('needs_retake', $result['status']);
        $this->assertSame([], $result['measurements']);
        $reasons = implode(' ', array_column($result['retake_requirements'], 'reason'));
        $this->assertStringContainsString('feet', strtolower($reasons));
        $this->assertSame(['front'], array_unique(array_column($result['retake_requirements'], 'view')));

        $validated = app(FashionFitAnalysisService::class)->validateResult($result);
        $this->assertSame('needs_retake', $validated['status']);
    }

    public function test_arms_resting_against_the_torso_are_reported_as_a_retake(): void
    {
        $result = $this->engine()->measure(
            $this->frontPhoto(armsTucked: true),
            $this->sidePhoto(),
            self::HEIGHT_INCHES,
            'in',
        );

        $this->assertSame('needs_retake', $result['status']);
        $reasons = implode(' ', array_column($result['retake_requirements'], 'reason'));
        $this->assertStringContainsString('arms', strtolower($reasons));
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
            $values[$measurement['name']] = (float) $measurement['value'];
        }

        return $values;
    }

    private function frontPhoto(bool $cropFeet = false, bool $armsTucked = false): string
    {
        return $this->render(function (float $fraction) use ($armsTucked): array {
            $spans = [];
            if ($fraction < 0.530) {
                $half = $this->interpolate(self::FRONT_PROFILE, $fraction);
                $spans[] = [-$half, $half];
                if ($fraction >= 0.210 && $fraction <= 0.500) {
                    $center = $this->interpolate(self::ARM_CENTER, $fraction);
                    $width = $this->interpolate(self::ARM_HALF_WIDTH, $fraction);
                    if ($armsTucked) {
                        // Arms pinned to the body: the torso edge disappears.
                        $center = $half + $width - 0.2;
                    }
                    $spans[] = [$center - $width, $center + $width];
                    $spans[] = [-$center - $width, -$center + $width];
                }

                return $spans;
            }

            $legHalf = $this->interpolate(self::LEG_PROFILE, $fraction);
            $legCenter = $this->interpolate(self::LEG_CENTER, $fraction);
            $spans[] = [$legCenter - $legHalf, $legCenter + $legHalf];
            $spans[] = [-$legCenter - $legHalf, -$legCenter + $legHalf];

            return $spans;
        }, cropFeet: $cropFeet);
    }

    private function sidePhoto(): string
    {
        return $this->render(function (float $fraction): array {
            $half = $this->interpolate(self::SIDE_PROFILE, $fraction);
            // The foot projects forward of the ankle.
            $shift = $fraction >= 0.962 ? -1.4 : 0.0;

            return [[-$half + $shift, $half + $shift]];
        });
    }

    /**
     * @param  callable(float): array<int, array{0: float, 1: float}>  $spans
     *   Horizontal spans in inches relative to the body centreline, for a given
     *   fraction of the body height.
     */
    private function render(callable $spans, bool $cropFeet = false): string
    {
        $bodyPixels = (int) round(self::HEIGHT_INCHES * self::PIXELS_PER_INCH);
        $topMargin = 90;
        $bottomMargin = $cropFeet ? 0 : 90;
        $width = 900;
        $height = $topMargin + $bodyPixels + $bottomMargin;
        // A cropped photo keeps the same subject but loses the bottom of the frame.
        $canvasHeight = $cropFeet ? $topMargin + (int) round($bodyPixels * 0.93) : $height;

        $image = imagecreatetruecolor($width, $canvasHeight);
        $background = imagecolorallocate($image, 232, 230, 226);
        $body = imagecolorallocate($image, 58, 56, 64);
        imagefilledrectangle($image, 0, 0, $width, $canvasHeight, $background);

        $centerX = intdiv($width, 2);
        for ($row = 0; $row < $bodyPixels; $row++) {
            $y = $topMargin + $row;
            if ($y >= $canvasHeight) {
                break;
            }
            $fraction = $row / max(1, $bodyPixels - 1);
            foreach ($spans($fraction) as [$from, $to]) {
                $x1 = $centerX + (int) round($from * self::PIXELS_PER_INCH);
                $x2 = $centerX + (int) round($to * self::PIXELS_PER_INCH);
                if ($x2 < $x1) {
                    [$x1, $x2] = [$x2, $x1];
                }
                imagefilledrectangle($image, max(0, $x1), $y, min($width - 1, $x2), $y, $body);
            }
        }

        ob_start();
        imagepng($image);
        $binary = (string) ob_get_clean();
        imagedestroy($image);

        return $binary;
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
