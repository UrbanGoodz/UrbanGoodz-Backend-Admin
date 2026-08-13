<?php

namespace Tests\Feature;

use App\Models\FashionFitAnalysis;
use App\Models\FashionFitMeasurement;
use App\Models\FashionFitPhoto;
use App\Models\FashionFitProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * End-to-end verification of the real Fashion Fit measurement path: a customer
 * creates a profile, uploads front and side photos, submits an analysis, and
 * the Silhouette engine produces persisted measurements that validate against
 * the structured contract. The queue runs synchronously in the testing env, so
 * ProcessFashionFitAnalysis executes inside the request.
 */
class FashionFitSilhouetteE2ETest extends TestCase
{
    use DatabaseTransactions;

    private const PPI = 20;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('gd')) {
            $this->markTestSkipped('The GD extension is required for the Fashion Fit silhouette engine.');
        }

        Storage::fake('local');

        $this->customer = User::firstOrCreate(
            ['email' => 'fashionfit-e2e@urbangoodz.com'],
            [
                'f_name' => 'Fashion',
                'l_name' => 'Fit',
                'phone' => '7778889999',
                'password' => bcrypt('password'),
                'is_active' => 1,
                'is_verified' => 1,
            ]
        );

        Passport::actingAs($this->customer);
    }

    public function test_full_customer_photo_measurement_flow_produces_real_measurements(): void
    {
        $create = $this->postJson('/api/v1/fashion-fit/profiles', [
            'name' => 'E2E Fit Profile',
            'units' => 'in',
            'calibration_height' => 70,
            'fit_preferences' => ['fit' => 'standard'],
            'ai_processing_consent' => true,
            'measurement_sharing_consent' => true,
            'photo_sharing_consent' => true,
        ]);
        $create->assertStatus(201);
        $profileUuid = $create->json('data.uuid');
        $this->assertNotEmpty($profileUuid);

        $profile = FashionFitProfile::where('uuid', $profileUuid)->firstOrFail();
        $this->assertSame('photos_pending', $profile->status);

        $front = $this->postJson("/api/v1/fashion-fit/profiles/{$profileUuid}/photos", [
            'view' => 'front',
            'confirmed_for_upload' => true,
            'photo' => \Illuminate\Http\UploadedFile::fake()->createWithContent('front.png', $this->frontPhoto()),
        ])->assertStatus(201);

        $side = $this->postJson("/api/v1/fashion-fit/profiles/{$profileUuid}/photos", [
            'view' => 'side',
            'confirmed_for_upload' => true,
            'photo' => \Illuminate\Http\UploadedFile::fake()->createWithContent('side.png', $this->sidePhoto()),
        ])->assertStatus(201);

        $submit = $this->postJson("/api/v1/fashion-fit/profiles/{$profileUuid}/analyses");
        $this->assertContains($submit->status(), [200, 202]);
        $analysisUuid = $submit->json('data.uuid');
        $this->assertNotEmpty($analysisUuid);

        $analysis = FashionFitAnalysis::where('uuid', $analysisUuid)->with('measurements')->firstOrFail();
        $this->assertSame('completed', $analysis->status, 'Analysis must complete: '.$analysis->failure_summary);
        $this->assertSame('silhouette', $analysis->provider);
        $this->assertSame('urbangoodz-silhouette', $analysis->model_name);

        $measurementRows = FashionFitMeasurement::where('profile_id', $profile->id)->get();
        $this->assertGreaterThanOrEqual(20, $measurementRows->count(), 'Engine must persist the full measurement set.');

        $byName = $measurementRows->keyBy('name');
        $this->assertTrue($byName->has('chest_bust'));
        $this->assertTrue($byName->has('waist'));
        $this->assertTrue($byName->has('full_hip'));
        $this->assertTrue($byName->has('inseam'));

        foreach ($measurementRows as $measurement) {
            $this->assertGreaterThan(0, (float) $measurement->value, $measurement->name.' must be positive.');
            $this->assertSame('ai', $measurement->source);
            $this->assertTrue($measurement->confidence > 0 && $measurement->confidence <= 1);
        }

        // The structured contract must accept the persisted result shape.
        $result = $this->buildResult($analysis);
        $validated = app(\App\Services\FashionFit\FashionFitAnalysisService::class)->validateResult($result);
        $this->assertSame('completed', $validated['status']);

        $profile->refresh();
        $this->assertSame('customer_review', $profile->status, 'Profile must await customer review after measurements.');
    }

    // ------------------------------------------------------------------ setup

    private function buildResult(FashionFitAnalysis $analysis): array
    {
        return [
            'status' => $analysis->status,
            'model' => $analysis->model_name,
            'model_version' => $analysis->model_version,
            'overall_confidence' => (float) $analysis->overall_confidence,
            'measurements' => $analysis->measurements->map(fn ($m) => [
                'name' => $m->name,
                'value' => (float) $m->value,
                'unit' => $m->unit,
                'confidence' => (float) $m->confidence,
                'requires_confirmation' => (bool) $m->requires_confirmation,
            ])->all(),
            'retake_requirements' => [],
        ];
    }

    private function frontPhoto(): string
    {
        return $this->render(function (float $fraction): array {
            $spans = [];
            $half = $this->interpolate($this->profile(), $fraction);
            $spans[] = [-$half, $half];
            if ($fraction >= 0.210 && $fraction <= 0.500) {
                $center = $this->interpolate([[0.210, 8.60], [0.300, 9.60], [0.500, 10.00]], $fraction);
                $width = $this->interpolate([[0.210, 1.85], [0.270, 1.80], [0.500, 1.05]], $fraction);
                $spans[] = [$center - $width, $center + $width];
                $spans[] = [-$center - $width, -$center + $width];
            }
            if ($fraction >= 0.530) {
                $legHalf = $this->interpolate([
                    [0.530, 4.30], [0.577, 4.00], [0.765, 2.40], [0.859, 2.50], [0.962, 1.60], [1.000, 1.80],
                ], $fraction);
                $legCenter = $this->interpolate([[0.530, 2.40], [1.000, 2.10]], $fraction);
                return [
                    [$legCenter - $legHalf, $legCenter + $legHalf],
                    [-$legCenter - $legHalf, -$legCenter + $legHalf],
                ];
            }
            return $spans;
        });
    }

    private function sidePhoto(): string
    {
        return $this->render(function (float $fraction): array {
            $half = $this->interpolate([
                [0.000, 1.60], [0.025, 3.10], [0.070, 3.70], [0.110, 3.30], [0.130, 2.80],
                [0.152, 2.40], [0.185, 4.60], [0.260, 4.50],
                [0.305, 4.20], [0.375, 4.00], [0.425, 4.40], [0.480, 4.75], [0.530, 4.20],
                [0.577, 3.60], [0.765, 2.30], [0.859, 2.60], [0.962, 1.70], [1.000, 1.70],
            ], $fraction);
            $shift = $fraction >= 0.962 ? -1.4 : 0.0;
            return [[-$half + $shift, $half + $shift]];
        });
    }

    private function profile(): array
    {
        return [
            [0.000, 1.20], [0.025, 2.60], [0.070, 3.10], [0.110, 2.80], [0.130, 2.52],
            [0.152, 2.40], [0.185, 9.00], [0.260, 6.50],
            [0.305, 6.00], [0.375, 5.50], [0.425, 6.00], [0.480, 6.75], [0.530, 6.60],
        ];
    }

    private function render(callable $spans): string
    {
        $bodyPixels = (int) round(70 * self::PPI);
        $topMargin = 90;
        $bottomMargin = 90;
        $width = 900;
        $height = $topMargin + $bodyPixels + $bottomMargin;

        $image = imagecreatetruecolor($width, $height);
        $background = imagecolorallocate($image, 232, 230, 226);
        $body = imagecolorallocate($image, 58, 56, 64);
        imagefilledrectangle($image, 0, 0, $width, $height, $background);

        $centerX = intdiv($width, 2);
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

        ob_start();
        imagepng($image);
        $binary = (string) ob_get_clean();
        imagedestroy($image);

        return $binary;
    }

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
