<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Both panel layouts POST the browser's FCM token to /subscribeToTopic on every
 * page load. The route was never registered, so the call answered 405 and no
 * admin or vendor browser was ever subscribed to its notification topic.
 */
class FcmTopicSubscriptionTest extends TestCase
{
    public function test_the_route_the_panel_layouts_post_to_exists(): void
    {
        $route = Route::getRoutes()->getByName('subscribeToTopic');

        $this->assertNotNull($route, 'The layouts POST to /subscribeToTopic; the route must exist.');
        $this->assertContains('POST', $route->methods());
        $this->assertSame('subscribeToTopic', $route->uri());
    }

    /** A shared unnamed bucket would rate-limit unrelated endpoints together. */
    public function test_the_route_rate_limiter_is_named(): void
    {
        $middleware = Route::getRoutes()->getByName('subscribeToTopic')->gatherMiddleware();

        $this->assertContains('throttle:30,1,web-fcm-subscribe-topic', $middleware);
    }

    public function test_an_anonymous_caller_cannot_subscribe_a_device_to_the_admin_topic(): void
    {
        $response = $this->postJson('/subscribeToTopic', [
            'token' => 'fake-device-token',
            'topic' => 'admin_message',
        ]);

        $this->assertSame(403, $response->status(), 'Anyone could otherwise receive admin notifications.');
    }

    public function test_an_anonymous_caller_cannot_subscribe_a_device_to_a_store_topic(): void
    {
        $response = $this->postJson('/subscribeToTopic', [
            'token' => 'fake-device-token',
            'topic' => 'store_panel_1_message',
        ]);

        $this->assertSame(403, $response->status());
    }

    public function test_the_payload_the_layouts_send_is_still_what_is_validated(): void
    {
        $this->postJson('/subscribeToTopic', ['topic' => 'admin_message'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('token');

        $this->postJson('/subscribeToTopic', ['token' => 'fake-device-token'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('topic');
    }
}
