<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class UrbanGoodzDataCenterSourceSafetyTest extends TestCase
{
    public function test_data_center_routes_cover_queue_review_visibility_and_rollback(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2) . '/routes/admin.php');

        foreach ([
            "prefix' => 'data-center",
            "batches/{batch}/preview",
            "batches/{batch}/retry",
            "batches/{batch}/approve",
            "batches/{batch}/visibility",
            "batches/{batch}/rollback",
            "businesses/{business}/review",
            "products/{product}/review",
            "images/{image}/review",
        ] as $requiredRoute) {
            $this->assertStringContainsString($requiredRoute, $routes);
        }
    }

    public function test_staging_service_never_creates_live_store_or_item(): void
    {
        $service = file_get_contents(dirname(__DIR__, 2) . '/app/Services/UrbanGoodzDataCenterService.php');

        $this->assertStringNotContainsString('Store::create(', $service);
        $this->assertStringNotContainsString('Item::create(', $service);
        $this->assertStringContainsString("'api_visible' => false", $service);
        $this->assertStringContainsString("'shopper_visible' => false", $service);
        $this->assertStringContainsString("'live_records_created' => 0", $service);
    }

    public function test_consumer_discovery_route_cannot_publish_marketplace_records(): void
    {
        $controller = file_get_contents(
            dirname(__DIR__, 2) . '/app/Http/Controllers/Api/V1/UrbanGoodzDiscoveryController.php'
        );

        $start = strpos($controller, 'public function entityAction');
        $end = strpos($controller, 'public function opportunities', $start);
        $action = substr($controller, $start, $end - $start);

        $this->assertStringContainsString('403', $action);
        $this->assertStringNotContainsString('publishApprovedListings', $action);
        $this->assertStringContainsString('->apiVisible()', $controller);
    }

    public function test_legacy_provisioning_requires_data_center_verification(): void
    {
        $service = file_get_contents(dirname(__DIR__, 2) . '/app/Services/UrbanGoodzIngestionService.php');

        foreach ([
            'validation_status is not valid',
            'source has not been verified',
            'record is not classified as production',
            'record is classified as a duplicate',
            'has not passed review and validation',
        ] as $gate) {
            $this->assertStringContainsString($gate, $service);
        }
    }

    public function test_marketplace_api_has_distinct_api_and_shopper_visibility_surfaces(): void
    {
        $controller = file_get_contents(
            dirname(__DIR__, 2) . '/app/Http/Controllers/Api/V1/UrbanGoodzMarketplaceDataController.php'
        );
        $routes = file_get_contents(dirname(__DIR__, 2) . '/routes/api/v1/urban_goodz.php');

        $this->assertStringContainsString('->apiVisible()', $controller);
        $this->assertStringContainsString('->shopperVisible()', $controller);
        $this->assertStringContainsString('urban-goodz/marketplace-data', $routes);
        $this->assertStringContainsString('shopper/catalogs', $routes);
    }

    public function test_catalog_image_pipeline_distinguishes_logo_cover_gallery_and_product_images(): void
    {
        $migration = file_get_contents(
            dirname(__DIR__, 2) . '/database/migrations/2026_07_30_210000_add_marketplace_data_center_controls.php'
        );
        $service = file_get_contents(dirname(__DIR__, 2) . '/app/Services/UrbanGoodzDataCenterService.php');
        $product = file_get_contents(dirname(__DIR__, 2) . '/app/Models/UrbanGoodzSourcedProduct.php');

        $this->assertStringContainsString("'image_role'", $migration);
        $this->assertStringContainsString("['logo', 'cover', 'gallery']", $service);
        $this->assertStringContainsString("'entity_type' => 'product'", $service);
        $this->assertStringContainsString('function sourcedImages()', $product);
    }
}
