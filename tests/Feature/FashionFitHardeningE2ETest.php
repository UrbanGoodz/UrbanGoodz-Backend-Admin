<?php

namespace Tests\Feature;

use App\Models\FashionFitAnalysis;
use App\Models\FashionFitMeasurement;
use App\Models\FashionFitMeasurementVersion;
use App\Models\FashionFitProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * End-to-end hardening coverage over the real API: identical photo sets must
 * not be re-analysed (idempotency by content hash), customer corrections and
 * approvals must survive re-analysis, measurement history must log both AI and
 * manual actors, and one customer must never read another customer's profile.
 */
class FashionFitHardeningE2ETest extends TestCase
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
            ['email' => 'fashionfit-hardening-e2e@urbangoodz.com'],
            [
                'f_name' => 'Hardened',
                'l_name' => 'Fit',
                'phone' => '8889990000',
                'password' => bcrypt('password'),
                'is_active' => 1,
                'is_verified' => 1,
            ]
        );

        Passport::actingAs($this->customer);
    }

    public function test_duplicate_submission_of_identical_photos_is_idempotent(): void
    {
        $profileUuid = $this->createProfile();
        $this->uploadPhotos($profileUuid);

        $first = $this->postJson("/api/v1/fashion-fit/profiles/{$profileUuid}/analyses")->assertStatus(200);
        $firstUuid = $first->json('data.uuid');
        $this->assertNotEmpty($firstUuid);
        $this->assertSame('completed', FashionFitAnalysis::where('uuid', $firstUuid)->value('status'));

        $this->assertSame(1, FashionFitAnalysis::where('profile_id', $this->profileId($profileUuid))->count());

        $second = $this->postJson("/api/v1/fashion-fit/profiles/{$profileUuid}/analyses")->assertStatus(200);
        $this->assertSame($firstUuid, $second->json('data.uuid'), 'Re-submitting identical photos must return the existing analysis.');
        $this->assertSame(1, FashionFitAnalysis::where('profile_id', $this->profileId($profileUuid))->count(), 'No new analysis row may be created.');
    }

    public function test_corrections_and_approval_survive_reanalysis_and_appear_in_history(): void
    {
        $profileUuid = $this->createProfile();
        $this->uploadPhotos($profileUuid);
        $analysisUuid = $this->postJson("/api/v1/fashion-fit/profiles/{$profileUuid}/analyses")->assertStatus(200)->json('data.uuid');
        $this->assertSame('completed', FashionFitAnalysis::where('uuid', $analysisUuid)->value('status'));

        $profileId = $this->profileId($profileUuid);
        $measurements = FashionFitMeasurement::where('profile_id', $profileId)->get();

        // A customer must confirm every flagged measurement before approval.
        foreach ($measurements->where('requires_confirmation', true) as $flagged) {
            $this->putJson("/api/v1/fashion-fit/profiles/{$profileUuid}/measurements/{$flagged->id}", [
                'value' => $flagged->value, 'unit' => $flagged->unit,
            ])->assertStatus(200);
        }

        $waist = $measurements->firstWhere('name', 'waist');
        $correctedValue = ((float) $waist->value) + 0.5;
        $this->putJson("/api/v1/fashion-fit/profiles/{$profileUuid}/measurements/{$waist->id}", [
            'value' => $correctedValue, 'unit' => 'in',
        ])->assertStatus(200);

        $approve = $this->postJson("/api/v1/fashion-fit/profiles/{$profileUuid}/approve")->assertStatus(200);
        $this->assertSame('approved', $approve->json('data.status'));

        // Re-analysing the identical photos must not disturb the approval or the correction.
        $resubmit = $this->postJson("/api/v1/fashion-fit/profiles/{$profileUuid}/analyses")->assertStatus(200);
        $this->assertSame($analysisUuid, $resubmit->json('data.uuid'));

        $profile = FashionFitProfile::where('id', $profileId)->firstOrFail();
        $this->assertSame('approved', $profile->status, 'Re-analysis must not downgrade an approved profile.');

        $waist->refresh();
        $this->assertSame($correctedValue, (float) $waist->value, 'A locked measurement must not be overwritten by re-analysis.');
        $this->assertSame('manual_correction', $waist->source);
        $this->assertNotNull($waist->approved_at);

        $history = $this->getJson("/api/v1/fashion-fit/profiles/{$profileUuid}/history")->assertStatus(200)->json('data');
        $waistVersions = collect($history)->filter(fn ($version) => ($version['measurement']['name'] ?? null) === 'waist')->values();
        $this->assertTrue($waistVersions->contains('source', 'ai'), 'History must include the original AI measurement.');
        $this->assertTrue($waistVersions->contains('source', 'manual_correction'), 'History must include the customer correction.');
        $correction = $waistVersions->firstWhere('source', 'manual_correction');
        $this->assertSame($correctedValue, (float) $correction['value']);
        $this->assertTrue($correction['corrected']);
        $this->assertTrue($correction['approved'], 'The approved correction must be marked approved.');
        $this->assertSame($this->customer->id, (int) $correction['actor_id']);
        $this->assertSame('customer', $correction['actor_type']);
    }

    public function test_another_customer_cannot_read_the_profile_or_history(): void
    {
        $profileUuid = $this->createProfile();
        $this->uploadPhotos($profileUuid);
        $this->postJson("/api/v1/fashion-fit/profiles/{$profileUuid}/analyses")->assertStatus(200);

        $intruder = User::firstOrCreate(
            ['email' => 'fashionfit-intruder-e2e@urbangoodz.com'],
            [
                'f_name' => 'Nosy',
                'l_name' => 'Customer',
                'phone' => '6667778888',
                'password' => bcrypt('password'),
                'is_active' => 1,
                'is_verified' => 1,
            ]
        );
        Passport::actingAs($intruder);

        $this->getJson("/api/v1/fashion-fit/profiles/{$profileUuid}")->assertStatus(404);
        $this->getJson("/api/v1/fashion-fit/profiles/{$profileUuid}/history")->assertStatus(404);
        $this->postJson("/api/v1/fashion-fit/profiles/{$profileUuid}/approve")->assertStatus(404);
    }

    // ------------------------------------------------------------------ setup

    private function profileId(string $uuid): int
    {
        return (int) FashionFitProfile::where('uuid', $uuid)->value('id');
    }

    private function createProfile(): string
    {
        return $this->postJson('/api/v1/fashion-fit/profiles', [
            'name' => 'Hardening Profile',
            'units' => 'in',
            'calibration_height' => 70,
            'fit_preferences' => ['fit' => 'standard'],
            'ai_processing_consent' => true,
            'measurement_sharing_consent' => true,
            'photo_sharing_consent' => true,
        ])->assertStatus(201)->json('data.uuid');
    }

    private function uploadPhotos(string $profileUuid): void
    {
        $this->postJson("/api/v1/fashion-fit/profiles/{$profileUuid}/photos", [
            'view' => 'front', 'confirmed_for_upload' => true,
            'photo' => UploadedFile::fake()->createWithContent('front.png', $this->frontPhoto()),
        ])->assertStatus(201);

        $this->postJson("/api/v1/fashion-fit/profiles/{$profileUuid}/photos", [
            'view' => 'side', 'confirmed_for_upload' => true,
            'photo' => UploadedFile::fake()->createWithContent('side.png', $this->sidePhoto()),
        ])->assertStatus(201);
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
