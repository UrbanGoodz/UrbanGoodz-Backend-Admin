<?php

namespace Tests\Unit;

use App\Services\FashionFit\FashionFitAnalysisService;
use RuntimeException;
use Tests\TestCase;

class FashionFitAiContractTest extends TestCase
{
    public function test_completed_provider_fixture_passes_structured_validation(): void
    {
        $fixture = json_decode(file_get_contents(__DIR__.'/../Fixtures/fashion_fit_ai_completed.json'), true);
        $validated = app(FashionFitAnalysisService::class)->validateResult($fixture);

        $this->assertSame('completed', $validated['status']);
        $this->assertSame('contract-test-model', $validated['model']);
        $this->assertCount(3, $validated['measurements']);
        $this->assertTrue($validated['measurements'][2]['requires_confirmation']);
    }

    public function test_invalid_or_fabricated_measurements_are_rejected(): void
    {
        $this->expectException(RuntimeException::class);

        app(FashionFitAnalysisService::class)->validateResult([
            'status' => 'completed',
            'model' => 'bad',
            'model_version' => 'bad',
            'overall_confidence' => 4.2,
            'measurements' => [[
                'name' => 'invented_measurement', 'value' => -1, 'unit' => 'pixels',
                'confidence' => 7, 'requires_confirmation' => false,
            ]],
        ]);
    }

    public function test_retake_response_requires_view_specific_instructions(): void
    {
        $validated = app(FashionFitAnalysisService::class)->validateResult([
            'status' => 'needs_retake', 'model' => 'contract-test-model', 'model_version' => '2026-07',
            'overall_confidence' => 0.2,
            'retake_requirements' => [['view' => 'side', 'reason' => 'Full body is not visible.']],
        ]);

        $this->assertSame('needs_retake', $validated['status']);
        $this->assertSame('side', $validated['retake_requirements'][0]['view']);
    }

    public function test_customer_provider_and_admin_routes_are_authenticated(): void
    {
        $routes = file_get_contents(__DIR__.'/../../routes/api/v1/fashion_fit.php');

        $this->assertStringContainsString("middleware(['auth:api', 'throttle:api'])", $routes);
        $this->assertStringContainsString("middleware(['vendor.api', 'actch:vendor_app', 'throttle:api'])", $routes);
        $this->assertStringContainsString("middleware(['auth:admin', 'throttle:api'])", $routes);
    }

    public function test_photo_storage_and_provider_access_fail_closed(): void
    {
        $storage = file_get_contents(__DIR__.'/../../app/Services/UrbanGoodz/UrbanGoodzFileStorageService.php');
        $provider = file_get_contents(__DIR__.'/../../app/Http/Controllers/Api/V1/Vendor/FashionFitWorkflowController.php');

        $this->assertStringContainsString("storeAs(\$directory, \$filename, 'local')", $storage);
        $this->assertStringContainsString('Raw photo access is not authorized.', $provider);
        $this->assertStringContainsString('Customer photo-sharing consent is not active.', $provider);
        $this->assertStringContainsString('Provider access has been revoked.', $provider);
    }
}
