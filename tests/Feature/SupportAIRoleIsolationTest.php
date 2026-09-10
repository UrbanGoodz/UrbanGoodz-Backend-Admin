<?php

namespace Tests\Feature;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SupportAIRoleIsolationTest extends TestCase
{
    public function test_every_support_ai_route_targets_a_real_controller_method(): void
    {
        $routes = collect(Route::getRoutes())->filter(
            fn (RoutingRoute $route) => str_starts_with($route->uri(), 'api/v1/urban-goodz/support/ai/')
        );

        $this->assertCount(5, $routes);
        foreach ($routes as $route) {
            [$class, $method] = explode('@', $route->getActionName());
            $this->assertTrue(class_exists($class), "Missing controller {$class}");
            $this->assertTrue(method_exists($class, $method), "Missing action {$class}@{$method}");
            $this->assertContains('auth:api', $route->gatherMiddleware());
            $this->assertContains('throttle:60,1,ug-support-ai', $route->gatherMiddleware());
        }
    }

    public function test_support_controller_scopes_orders_and_conversations_to_the_customer(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Api/V1/UrbanGoodz/SupportAIController.php'));

        $this->assertStringContainsString("Order::where('user_id', \$customerId)", $source);
        $this->assertGreaterThanOrEqual(3, substr_count($source, "where('customer_id', \$customerId)"));
        $this->assertStringNotContainsString("'customer_id' => ['nullable', 'integer']", $source);
        $this->assertStringContainsString('authenticatedCustomerId', $source);
        $this->assertStringContainsString("abort_unless(is_numeric(\$customerId)", $source);
    }

    public function test_feedback_is_validated_and_persisted_in_conversation_metadata(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Api/V1/UrbanGoodz/SupportAIController.php'));

        $this->assertStringContainsString("'rating' => ['required', 'integer', 'min:1', 'max:5']", $source);
        $this->assertStringContainsString("\$metadata['feedback']", $source);
        $this->assertStringContainsString("update(['metadata' => \$metadata])", $source);
    }

    public function test_support_surfaces_reject_unauthenticated_requests(): void
    {
        $this->postJson('/api/v1/urban-goodz/support/ai/classify')->assertUnauthorized();
        $this->postJson('/api/v1/urban-goodz/support/ai/auto-resolve')->assertUnauthorized();
        $this->postJson('/api/v1/urban-goodz/support/ai/escalate')->assertUnauthorized();
        $this->getJson('/api/v1/urban-goodz/support/ai/knowledge-base?query=order')->assertUnauthorized();
        $this->postJson('/api/v1/urban-goodz/support/ai/feedback')->assertUnauthorized();
    }
}
