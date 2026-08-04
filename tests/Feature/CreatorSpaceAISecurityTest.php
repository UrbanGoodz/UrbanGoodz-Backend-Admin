<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\V1\UrbanGoodz\CreatorSpaceAIController;
use App\Models\User;
use App\Models\UrbanGoodzCreatorApplication;
use App\Models\UrbanGoodzCreatorContent;
use App\Models\UrbanGoodzCreatorProduct;
use App\Models\UrbanGoodzCreatorProfile;
use App\Services\UrbanGoodz\CreatorSpaceAIService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class CreatorSpaceAISecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        $this->createTables();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_creator_ai_routes_resolve_and_keep_customer_api_auth(): void
    {
        $brand = $this->routeFor('api/v1/urban-goodz/creator/ai/brand-matches', 'GET');
        $analytics = $this->routeFor('api/v1/urban-goodz/creator/ai/reel-analytics', 'POST');

        $this->assertSame(
            'App\Http\Controllers\Api\V1\UrbanGoodz\CreatorSpaceAIController@matchBrand',
            $brand->getActionName()
        );
        $this->assertContains('auth:api', $brand->gatherMiddleware());

        $this->assertSame(
            'App\Http\Controllers\Api\V1\UrbanGoodz\CreatorSpaceAIController@generateReelAnalytics',
            $analytics->getActionName()
        );
        $this->assertContains('auth:api', $analytics->gatherMiddleware());
    }

    public function test_brand_matching_rejects_foreign_creator_profile_and_calls_service_for_owner(): void
    {
        [$owner, $ownerProfile, $foreignProfile] = $this->creatorFixtures();

        $service = Mockery::mock(CreatorSpaceAIService::class);
        $service->shouldNotReceive('matchCreatorToBrand')->with($foreignProfile->id);
        $service->shouldReceive('matchCreatorToBrand')
            ->once()
            ->with($ownerProfile->id)
            ->andReturn(['success' => true, 'matches' => []]);

        $controller = new CreatorSpaceAIController($service);

        $foreign = $controller->matchBrand($this->requestFor($owner, ['creator_id' => $foreignProfile->id]));
        $this->assertSame(403, $foreign->getStatusCode());

        $owned = $controller->matchBrand($this->requestFor($owner, ['creator_id' => $ownerProfile->id]));
        $this->assertSame(200, $owned->getStatusCode());
        $this->assertTrue(json_decode($owned->getContent(), true)['success']);
    }

    public function test_reel_analytics_rejects_foreign_content_and_calls_service_for_owner(): void
    {
        [$owner, $ownerProfile, $foreignProfile] = $this->creatorFixtures();
        $ownerContent = UrbanGoodzCreatorContent::create([
            'creator_profile_id' => $ownerProfile->id,
            'creator_application_id' => $ownerProfile->creator_application_id,
            'title' => 'Owner reel',
            'content_type' => 'reel',
        ]);
        $foreignContent = UrbanGoodzCreatorContent::create([
            'creator_profile_id' => $foreignProfile->id,
            'creator_application_id' => $foreignProfile->creator_application_id,
            'title' => 'Foreign reel',
            'content_type' => 'reel',
        ]);

        $service = Mockery::mock(CreatorSpaceAIService::class);
        $service->shouldNotReceive('generateReelAnalytics')->with($foreignContent->id);
        $service->shouldReceive('generateReelAnalytics')
            ->once()
            ->with($ownerContent->id)
            ->andReturn(['success' => true, 'engagement_score' => 80]);

        $controller = new CreatorSpaceAIController($service);

        $foreign = $controller->generateReelAnalytics($this->requestFor($owner, ['content_id' => $foreignContent->id], 'POST'));
        $this->assertSame(403, $foreign->getStatusCode());

        $owned = $controller->generateReelAnalytics($this->requestFor($owner, ['content_id' => $ownerContent->id], 'POST'));
        $this->assertSame(200, $owned->getStatusCode());
        $this->assertTrue(json_decode($owned->getContent(), true)['success']);
    }

    public function test_product_tags_and_performance_fail_closed_for_unowned_ids(): void
    {
        [$owner, $ownerProfile, $foreignProfile] = $this->creatorFixtures();
        $foreignProduct = UrbanGoodzCreatorProduct::create([
            'creator_application_id' => $foreignProfile->creator_application_id,
            'name' => 'Foreign product',
        ]);

        $service = Mockery::mock(CreatorSpaceAIService::class);
        $service->shouldNotReceive('generateProductTags');
        $service->shouldNotReceive('analyzeCreatorPerformance');

        $controller = new CreatorSpaceAIController($service);

        $tags = $controller->generateProductTags($this->requestFor($owner, ['product_ids' => [$foreignProduct->id]], 'POST'));
        $this->assertSame(403, $tags->getStatusCode());

        $performance = $controller->analyzePerformance($this->requestFor($owner, ['creator_id' => $foreignProfile->id], 'POST'));
        $this->assertSame(403, $performance->getStatusCode());
    }

    public function test_creator_ai_id_endpoints_fail_closed_without_authenticated_user(): void
    {
        [, $ownerProfile] = $this->creatorFixtures();

        $service = Mockery::mock(CreatorSpaceAIService::class);
        $service->shouldNotReceive('matchCreatorToBrand');

        $response = (new CreatorSpaceAIController($service))
            ->matchBrand(Request::create('/api/v1/urban-goodz/creator/ai/brand-matches', 'GET', [
                'creator_id' => $ownerProfile->id,
            ]));

        $this->assertSame(403, $response->getStatusCode());
    }

    private function creatorFixtures(): array
    {
        $owner = User::create([
            'f_name' => 'Owner',
            'l_name' => 'Creator',
            'email' => 'creator@example.test',
            'phone' => '+1 (555) 010-1111',
        ]);

        $ownerApplication = UrbanGoodzCreatorApplication::create([
            'creator_name' => 'Owner Creator',
            'email' => 'creator@example.test',
            'phone' => '5550101111',
            'status' => 'approved',
        ]);
        $foreignApplication = UrbanGoodzCreatorApplication::create([
            'creator_name' => 'Foreign Creator',
            'email' => 'other@example.test',
            'phone' => '5550102222',
            'status' => 'approved',
        ]);

        $ownerProfile = UrbanGoodzCreatorProfile::create([
            'creator_application_id' => $ownerApplication->id,
            'display_name' => 'Owner Creator',
            'status' => 'approved',
        ]);
        $foreignProfile = UrbanGoodzCreatorProfile::create([
            'creator_application_id' => $foreignApplication->id,
            'display_name' => 'Foreign Creator',
            'status' => 'approved',
        ]);

        return [$owner, $ownerProfile, $foreignProfile];
    }

    private function requestFor(User $user, array $parameters, string $method = 'GET'): Request
    {
        $request = Request::create('/api/v1/urban-goodz/creator/ai/test', $method, $parameters);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function routeFor(string $uri, string $method): \Illuminate\Routing\Route
    {
        $route = collect(Route::getRoutes()->getRoutes())->first(function ($route) use ($uri, $method) {
            return $route->uri() === $uri && in_array($method, $route->methods(), true);
        });

        $this->assertNotNull($route, "Missing route {$method} {$uri}");

        return $route;
    }

    private function createTables(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('f_name')->nullable();
            $table->string('l_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
        });
        Schema::create('urban_goodz_creator_applications', function (Blueprint $table) {
            $table->id();
            $table->string('creator_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
        Schema::create('urban_goodz_creator_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->unsignedBigInteger('creator_application_id')->nullable();
            $table->string('display_name')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
        Schema::create('urban_goodz_creator_content', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('creator_profile_id')->nullable();
            $table->unsignedBigInteger('creator_application_id')->nullable();
            $table->string('title');
            $table->string('content_type')->default('reel');
            $table->timestamps();
        });
        Schema::create('urban_goodz_creator_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('creator_application_id')->nullable();
            $table->string('name');
            $table->timestamps();
        });
    }
}
