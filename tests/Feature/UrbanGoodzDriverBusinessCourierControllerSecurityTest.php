<?php

namespace Tests\Feature;

use Tests\TestCase;

class UrbanGoodzDriverBusinessCourierControllerSecurityTest extends TestCase
{
    private function controllerSource(): string
    {
        return file_get_contents(app_path('Http/Controllers/Api/UrbanGoodzDriverBusinessCourierController.php'));
    }

    private function methodSource(string $method): string
    {
        $source = $this->controllerSource();
        $pattern = '/(?:public|private) function ' . preg_quote($method, '/') . '\([^)]*\)(.*?)(?=\n    (?:public|private) function|\n})/s';

        $this->assertMatchesRegularExpression($pattern, $source, "Method {$method} was not found.");
        preg_match($pattern, $source, $matches);

        return $matches[1];
    }

    public function test_mutating_business_job_endpoints_scope_to_requested_job_id(): void
    {
        foreach ([
            'acceptJob',
            'startJob',
            'markPickup',
            'markDelivery',
            'submitPickupProof',
            'submitDeliveryProof',
            'reportException',
        ] as $method) {
            $methodSource = $this->methodSource($method);

            $this->assertStringContainsString('->assignedToDriver($driver->id)', $methodSource, "{$method} must scope to the authenticated driver.");
            $this->assertStringContainsString('->whereKey($jobId)', $methodSource, "{$method} must scope to the requested job id.");
            $this->assertStringContainsString('->firstOrFail()', $methodSource, "{$method} must return 404 for inaccessible jobs.");
            $this->assertLessThan(
                strpos($methodSource, '->firstOrFail()'),
                strpos($methodSource, '->whereKey($jobId)'),
                "{$method} must apply job id scoping before firstOrFail."
            );
        }
    }

    public function test_driver_business_job_response_does_not_expose_admin_notes(): void
    {
        $this->assertStringNotContainsString("'admin_notes'", $this->controllerSource());
        $this->assertStringNotContainsString('admin_notes', $this->methodSource('jobDetailResponse'));
    }

    public function test_proof_url_requires_https(): void
    {
        $this->assertStringContainsString("'proof_url' => ['required_without:proof', 'url', 'starts_with:https://']", $this->controllerSource());
    }
}