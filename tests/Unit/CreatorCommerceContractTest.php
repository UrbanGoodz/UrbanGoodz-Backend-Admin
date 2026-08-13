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
        $this->assertStringContainsString('Only products owned by this store may be tagged.', $controller);
        $this->assertStringContainsString('moderation_required', $controller);
    }

    public function test_attribution_validates_customer_and_store_order_ownership(): void
    {
        $controller = file_get_contents(__DIR__.'/../../Modules/ReelsModule/Http/Controllers/Api/V1/CreatorCommerceController.php');
        $this->assertStringContainsString("where('user_id', \$userId)->where('store_id', \$attribution->store_id)", $controller);
        $this->assertStringContainsString('Attribution has already been finalized.', $controller);
        $this->assertStringContainsString('UrbanGoodzCreatorEarning::create', $controller);
    }

    public function test_file_backed_tester_routes_are_removed(): void
    {
        $routes = file_get_contents(__DIR__.'/../../routes/api/v1/urban_goodz.php');
        $this->assertStringNotContainsString('CreatorCommerceTesterController', $routes);
    }

    public function test_existing_reel_app_routes_are_preserved(): void
    {
        $routes = file_get_contents(__DIR__.'/../../Modules/ReelsModule/Routes/api/v1/api.php');

        foreach ([
            "Route::get('list'",
            "Route::get('details'",
            "Route::get('stats'",
            "Route::post('like'",
            "Route::post('visit'",
        ] as $route) {
            $this->assertStringContainsString($route, $routes);
        }
    }

    public function test_all_shopper_creator_commerce_constants_have_registered_routes(): void
    {
        $routes = file_get_contents(__DIR__.'/../../routes/api/v1/urban_goodz.php');

        foreach ([
            "Route::get('reels'",
            "Route::post('action'",
            "Route::post('conversion'",
            "Route::get('opportunities'",
            "Route::get('analytics'",
        ] as $route) {
            $this->assertStringContainsString($route, $routes);
        }
    }

    public function test_creator_vendor_and_admin_workflow_routes_are_registered(): void
    {
        $routes = file_get_contents(__DIR__.'/../../Modules/ReelsModule/Routes/api/v1/api.php');

        foreach ([
            "Route::post('report'",
            "Route::post('attribution'",
            "Route::post('attribution/order'",
            "Route::get('{reel}/comments'",
            "Route::post('{reel}/comments'",
            "Route::delete('comments/{comment}'",
            "Route::get('opportunities'",
            "Route::post('opportunities/{campaign}/accept'",
            "Route::get('campaigns'",
            "Route::put('campaigns/{assignment}'",
            "Route::get('analytics'",
            "Route::post('store'",
            "Route::put('update'",
            "Route::delete('delete'",
            "Route::post('{reel}/publish'",
            "Route::post('{reel}/unpublish'",
            "Route::get('creator/profile'",
            "Route::get('creator/earnings'",
            "Route::get('creator/analytics'",
            "Route::post('creator/campaigns'",
            "Route::put('creator/campaigns/{campaign}'",
            "Route::get('comments', [CreatorModerationController::class",
            "Route::put('comments/{comment}'",
        ] as $route) {
            $this->assertStringContainsString($route, $routes);
        }
    }

    public function test_comments_and_replies_are_persistent_moderated_and_counted(): void
    {
        $migration = file_get_contents(
            __DIR__.'/../../Modules/ReelsModule/Database/Migrations/2026_07_30_210000_complete_creator_commerce_social.php'
        );
        $controller = file_get_contents(
            __DIR__.'/../../Modules/ReelsModule/Http/Controllers/Api/V1/CreatorCommerceController.php'
        );
        $admin = file_get_contents(
            __DIR__.'/../../Modules/ReelsModule/Http/Controllers/Api/V1/Admin/CreatorModerationController.php'
        );

        $this->assertStringContainsString("Schema::create('creator_reel_comments'", $migration);
        $this->assertStringContainsString("foreignId('parent_id')", $migration);
        $this->assertStringContainsString("increment('total_comments')", $controller);
        $this->assertStringContainsString('Replies may only be one level deep.', $controller);
        $this->assertStringContainsString('moderateComment', $admin);
    }

    public function test_campaign_and_analytics_queries_are_creator_or_vendor_scoped(): void
    {
        $controller = file_get_contents(
            __DIR__.'/../../Modules/ReelsModule/Http/Controllers/Api/V1/CreatorCommerceController.php'
        );

        $this->assertStringContainsString(
            "['campaign_id' => \$campaign->id, 'creator_profile_id' => \$profile->id]",
            $controller
        );
        $this->assertStringContainsString(
            "where('vendor_id', \$request['vendor']->id)",
            $controller
        );
        $this->assertStringContainsString(
            "where('creator_profile_id', \$profile->id)",
            $controller
        );
        $this->assertStringContainsString("'conversion_rate'", $controller);
    }

    public function test_creator_applications_and_promotions_are_account_scoped(): void
    {
        $controller = file_get_contents(
            __DIR__.'/../../app/Http/Controllers/Api/V1/CreatorCommerceController.php'
        );

        $this->assertStringContainsString('$this->requireIdentity($email, $phone);', $controller);
        $this->assertStringContainsString(
            '$this->applicationsForIdentity($email, $phone)',
            $controller
        );
        $this->assertStringContainsString(
            "abort_unless(\$applicationId, 403, 'Submit a creator application before proposing content.')",
            $controller
        );
        $this->assertStringContainsString("'email' => \$email", $controller);
        $this->assertStringContainsString("'phone' => \$phone", $controller);
    }
}
