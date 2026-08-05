<?php

namespace Tests\Feature;

use App\Models\FeatureFlag;
use App\Models\MobileRelease;
use App\Models\RemoteConfig;
use App\Services\MobileReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MobileReleaseApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_version_api_returns_no_update_when_no_releases_exist(): void
    {
        $response = $this->getJson('/api/v1/app/version?app=shopper&platform=android&build_number=100');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.has_update', false)
            ->assertJsonPath('data.required', false);
    }

    public function test_version_api_detects_optional_update(): void
    {
        $service = app(MobileReleaseService::class);
        $service->createRelease([
            'app_name' => 'shopper',
            'platform' => 'android',
            'version_name' => '1.2.0',
            'build_number' => 10200,
            'minimum_build_number' => 10000,
            'required' => false,
            'release_notes' => 'New features added',
            'apk_url' => 'https://urbangoodz.app/releases/shopper.apk',
        ]);

        $response = $this->getJson('/api/v1/app/version?app=shopper&platform=android&build_number=10100');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.has_update', true)
            ->assertJsonPath('data.required', false)
            ->assertJsonPath('data.latest_version', '1.2.0')
            ->assertJsonPath('data.latest_build', 10200);
    }

    public function test_version_api_detects_forced_required_update(): void
    {
        $service = app(MobileReleaseService::class);
        $service->createRelease([
            'app_name' => 'shopper',
            'platform' => 'android',
            'version_name' => '2.0.0',
            'build_number' => 20000,
            'minimum_build_number' => 15000,
            'required' => true,
        ]);

        $response = $this->getJson('/api/v1/app/version?app=shopper&platform=android&build_number=10000');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.has_update', true)
            ->assertJsonPath('data.required', true);
    }

    public function test_release_service_calculates_sha256_for_uploaded_apk(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->create('test-release.apk', 500);

        $service = app(MobileReleaseService::class);
        $release = $service->createRelease([
            'app_name' => 'vendor',
            'platform' => 'android',
            'version_name' => '1.0.1',
            'build_number' => 10010,
        ], $file);

        $this->assertNotNull($release->sha256);
        $this->assertEquals(64, strlen($release->sha256));
        $this->assertNotNull($release->apk_url);
    }

    public function test_rollback_reverts_to_previous_active_release(): void
    {
        $service = app(MobileReleaseService::class);
        $v1 = $service->createRelease([
            'app_name' => 'driver',
            'platform' => 'android',
            'version_name' => '1.0.0',
            'build_number' => 100,
            'enabled' => true,
        ]);

        $v2 = $service->createRelease([
            'app_name' => 'driver',
            'platform' => 'android',
            'version_name' => '1.1.0',
            'build_number' => 110,
            'enabled' => true,
        ]);

        $rolledBack = $service->rollback('driver', 'android');

        $this->assertEquals(100, $rolledBack->build_number);
        $this->assertTrue($rolledBack->enabled);
        $this->assertFalse($v2->fresh()->enabled);
    }

    public function test_remote_config_api_returns_fashion_fit_rules(): void
    {
        RemoteConfig::create([
            'app_name' => 'all',
            'platform' => 'all',
            'key' => 'fashion_fit',
            'value' => [
                'enabled' => true,
                'min_width' => 1080,
                'required_photo_count' => 2,
            ],
            'type' => 'json',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/app/config?app=shopper&platform=android');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('fashion_fit.enabled', true)
            ->assertJsonPath('fashion_fit.min_width', 1080);
    }

    public function test_feature_flags_api_evaluates_toggles(): void
    {
        FeatureFlag::create([
            'key' => 'virtual_try_on',
            'name' => 'Virtual Try-On',
            'enabled_globally' => true,
        ]);

        FeatureFlag::create([
            'key' => 'experimental_features',
            'name' => 'Experimental Features',
            'enabled_globally' => false,
        ]);

        $response = $this->getJson('/api/v1/app/feature-flags?app=shopper&platform=android');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('flags.virtual_try_on', true)
            ->assertJsonPath('flags.experimental_features', false);
    }
}
