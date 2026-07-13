<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class CreatorCommerceContractTest extends TestCase
{
    public function test_public_feed_requires_published_and_moderated_reels(): void
    {
        $model = file_get_contents(__DIR__.'/../../Modules/ReelsModule/Entities/Reel.php');
        $this->assertStringContainsString("where('publication_status', 'published')", $model);
        $this->assertStringContainsString("where('moderation_status', 'approved')", $model);
    }

    public function test_vendor_tags_and_reel_mutations_are_store_scoped(): void
    {
        $controller = file_get_contents(__DIR__.'/../../Modules/ReelsModule/Http/Controllers/Api/V1/Vendor/ReelController.php');
        $this->assertStringContainsString("where('store_id', \$storeId)", $controller);
        $this->assertStringContainsString("Only products owned by this store may be tagged.", $controller);
        $this->assertStringContainsString('moderation_required', $controller);
    }

    public function test_attribution_validates_customer_and_store_order_ownership(): void
    {
        $controller = file_get_contents(__DIR__.'/../../Modules/ReelsModule/Http/Controllers/Api/V1/CreatorCommerceController.php');
        $this->assertStringContainsString("where('user_id', \$userId)->where('store_id', \$attribution->store_id)", $controller);
        $this->assertStringContainsString("Attribution has already been finalized.", $controller);
        $this->assertStringContainsString('UrbanGoodzCreatorEarning::create', $controller);
    }

    public function test_file_backed_tester_routes_are_removed(): void
    {
        $routes = file_get_contents(__DIR__.'/../../routes/api/v1/urban_goodz.php');
        $this->assertStringNotContainsString('CreatorCommerceTesterController', $routes);
    }
}
