<?php

namespace Tests\Feature;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CrossAppAIRoleIsolationTest extends TestCase
{
    public function test_customer_vendor_and_driver_cross_app_routes_use_their_own_guards(): void
    {
        $this->assertRouteMiddleware(
            'api/v1/urban-goodz/cross-app/ai/customer/history',
            ['auth:api', 'throttle:120,1'],
            ['vendor.api', 'dm.api']
        );
        $this->assertRouteMiddleware(
            'api/v1/urban-goodz/cross-app/ai/vendor/daily-brief',
            ['vendor.api', 'actch:vendor_app', 'throttle:120,1'],
            ['auth:api', 'dm.api']
        );
        $this->assertRouteMiddleware(
            'api/v1/urban-goodz/cross-app/ai/driver/daily-summary',
            ['dm.api', 'throttle:120,1'],
            ['auth:api', 'vendor.api']
        );
    }

    public function test_business_and_dispatcher_are_not_exposed_under_customer_passport(): void
    {
        $uris = collect(Route::getRoutes())->map(fn (RoutingRoute $route) => $route->uri());

        $this->assertFalse($uris->contains(
            fn (string $uri) => str_starts_with($uri, 'api/v1/urban-goodz/cross-app/ai/business/')
        ));
        $this->assertFalse($uris->contains(
            fn (string $uri) => str_starts_with($uri, 'api/v1/urban-goodz/cross-app/ai/dispatcher/')
        ));
    }

    public function test_business_and_dispatcher_keep_their_existing_session_authorities(): void
    {
        $businessRoute = Route::getRoutes()->getByName('business.ai.route.optimize');
        $dispatcherRoute = Route::getRoutes()->getByName('business.dispatcher.dashboard');

        $this->assertNotNull($businessRoute);
        $this->assertContains('business', $businessRoute->gatherMiddleware());
        $this->assertNotNull($dispatcherRoute);
        $this->assertContains('business', $dispatcherRoute->gatherMiddleware());
        $this->assertContains('dispatcher', $dispatcherRoute->gatherMiddleware());
        $this->assertContains('dispatch-territory', $dispatcherRoute->gatherMiddleware());
    }

    public function test_all_cross_app_role_surfaces_reject_unauthenticated_requests(): void
    {
        $this->getJson('/api/v1/urban-goodz/cross-app/ai/customer/history')->assertUnauthorized();
        $this->getJson('/api/v1/urban-goodz/cross-app/ai/vendor/daily-brief')->assertUnauthorized();
        $this->getJson('/api/v1/urban-goodz/cross-app/ai/driver/daily-summary')->assertUnauthorized();
    }

    public function test_controller_fails_closed_when_a_role_actor_is_missing(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Api/V1/UrbanGoodz/CrossAppAIController.php'));

        $this->assertStringContainsString('authenticatedActorId', $source);
        $this->assertStringContainsString("abort_unless(is_numeric(\$actorId)", $source);
        $this->assertStringContainsString("request->user('delivery_men')", $source);
        $this->assertStringContainsString("request->get('vendor')", $source);
        $this->assertStringNotContainsString("Auth::guard('dm')", $source);
        $this->assertStringNotContainsString("request->user('dm')?->id ?? Auth::guard('dm')->id()", $source);
        $this->assertStringNotContainsString("request->user('vendor')?->id ?? Auth::guard('vendor')->id()", $source);
    }

    private function assertRouteMiddleware(string $uri, array $required, array $forbidden): void
    {
        $route = collect(Route::getRoutes())->first(fn (RoutingRoute $route) => $route->uri() === $uri);

        $this->assertNotNull($route, "Missing route {$uri}");
        $middleware = $route->gatherMiddleware();

        foreach ($required as $name) {
            $this->assertContains($name, $middleware, "{$uri} is missing {$name}");
        }
        foreach ($forbidden as $name) {
            $this->assertNotContains($name, $middleware, "{$uri} must not use {$name}");
        }
    }
}
