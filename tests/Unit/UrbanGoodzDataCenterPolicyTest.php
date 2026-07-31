<?php

namespace Tests\Unit;

use App\Services\UrbanGoodzDataCenterPolicy;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/app/Services/UrbanGoodzDataCenterPolicy.php';

class UrbanGoodzDataCenterPolicyTest extends TestCase
{
    private UrbanGoodzDataCenterPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new UrbanGoodzDataCenterPolicy();
    }

    public function test_consumer_goods_priority_order_is_explicit(): void
    {
        $categories = [
            'Shopping and Retail',
            'Boutiques and Fashion',
            'Beauty Supply',
            'Grocery',
            'Home-Based Businesses',
            'Pharmacy and Health',
            'Services',
            'Restaurants',
        ];

        $priorities = array_map(fn ($category) => $this->policy->priorityFor($category), $categories);

        $this->assertSame([10, 20, 30, 40, 50, 60, 70, 90], $priorities);
    }

    public function test_demo_and_test_records_are_never_classified_as_production(): void
    {
        $this->assertSame('demo', $this->policy->classify([
            'name' => 'Sample Boutique',
            'city' => 'Houston',
            'state' => 'TX',
            'source_name' => 'demo catalog',
        ]));
        $this->assertSame('test', $this->policy->classify([
            'name' => 'QA Beauty',
            'city' => 'Houston',
            'state' => 'TX',
            'source_name' => 'staging fixture',
        ]));
    }

    public function test_duplicate_fingerprint_is_classified_separately(): void
    {
        $candidate = [
            'name' => 'Urban Closet',
            'city' => 'Houston',
            'state' => 'TX',
            'website' => 'https://urbancloset.example/catalog',
        ];

        $this->assertSame('duplicate', $this->policy->classify(
            $candidate,
            [$this->policy->fingerprint($candidate)]
        ));
    }

    public function test_normal_record_is_production_candidate_but_not_implicitly_approved(): void
    {
        $candidate = [
            'name' => 'Bayou Beauty Supply',
            'city' => 'Houston',
            'state' => 'TX',
            'website' => 'https://bayou-beauty.example',
        ];

        $this->assertSame('production', $this->policy->classify($candidate));
    }

    public function test_business_validation_requires_location_category_and_verifiable_source(): void
    {
        $errors = $this->policy->validateBusiness([
            'name' => 'Incomplete Listing',
            'source_urls' => ['javascript:alert(1)'],
        ]);

        $this->assertContains('city is required', $errors);
        $this->assertContains('state is required', $errors);
        $this->assertContains('category is required', $errors);
        $this->assertContains('source URL must use http or https', $errors);
    }

    public function test_product_validation_rejects_false_fixed_price_claims(): void
    {
        $errors = $this->policy->validateProduct([
            'name' => 'Premium Wig',
            'price_type' => 'fixed',
        ]);

        $this->assertSame(['fixed-price product requires a price'], $errors);
    }

    public function test_api_exposure_requires_review_validation_production_and_verified_source(): void
    {
        $failures = $this->policy->exposureFailures([
            'admin_review_status' => 'approved',
            'validation_status' => 'valid',
            'record_classification' => 'production',
            'source_verified' => false,
            'duplicate_of_business_id' => null,
        ], false);

        $this->assertSame(['source has not been verified'], $failures);
    }

    public function test_shopper_exposure_additionally_requires_approved_image(): void
    {
        $record = [
            'admin_review_status' => 'approved',
            'validation_status' => 'valid',
            'record_classification' => 'production',
            'source_verified' => true,
            'duplicate_of_business_id' => null,
            'approved_image_count' => 0,
        ];

        $this->assertSame([], $this->policy->exposureFailures($record, false));
        $this->assertSame(
            ['shopper visibility requires an approved image'],
            $this->policy->exposureFailures($record, true)
        );
    }

    public function test_duplicate_record_cannot_pass_visibility_gate(): void
    {
        $failures = $this->policy->exposureFailures([
            'admin_review_status' => 'approved',
            'validation_status' => 'valid',
            'record_classification' => 'duplicate',
            'source_verified' => true,
            'duplicate_of_business_id' => 42,
            'approved_image_count' => 1,
        ], true);

        $this->assertContains('record is not classified as production', $failures);
        $this->assertContains('record is classified as a duplicate', $failures);
    }
}
