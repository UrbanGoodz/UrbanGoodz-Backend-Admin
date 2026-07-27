<?php

namespace Tests\Feature\Api;

use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The JSON dashboard payload was removed from the browser-facing admin URL and
 * relocated here. These tests prove the capability was relocated, not deleted.
 */
class DispatcherSourcingApiTest extends TestCase
{
    use DatabaseTransactions;

    private const URI = '/api/v1/admin/dispatcher-sourcing';

    private function actingAsAdmin(): Admin
    {
        $admin = Admin::query()->where('role_id', 1)->first() ?? Admin::query()->first();

        if (!$admin) {
            $this->markTestSkipped('No admin row available in the test database.');
        }

        $admin->forceFill([
            'is_logged_in' => 1,
            'login_remember_token' => 'dispatcher-sourcing-api-token',
        ])->saveQuietly();

        $this->withSession(['login_remember_token' => 'dispatcher-sourcing-api-token'])
            ->be($admin, 'admin');

        return $admin;
    }

    public function test_api_route_is_registered_and_bound_to_the_json_action(): void
    {
        $route = Route::getRoutes()->getByName('api.v1.admin.dispatcher-sourcing.dashboard');

        $this->assertNotNull($route, 'The dispatcher-sourcing JSON API route is not registered.');
        $this->assertSame('api/v1/admin/dispatcher-sourcing', $route->uri());
        $this->assertStringEndsWith('@apiDashboard', $route->getActionName());
    }

    public function test_authorized_admin_receives_structured_json(): void
    {
        $this->actingAsAdmin();

        $response = $this->getJson(self::URI);

        $response->assertOk();
        $this->assertStringStartsWith(
            'application/json',
            (string) $response->headers->get('content-type')
        );

        $response->assertJsonStructure([
            'eligible_drivers',
            'available_loads',
            'saved_searches',
            'top_recommendations',
        ]);

        $payload = $response->json();
        $this->assertIsInt($payload['eligible_drivers']);
        $this->assertIsInt($payload['available_loads']);
        $this->assertIsInt($payload['saved_searches']);
        $this->assertIsArray($payload['top_recommendations']);
    }

    public function test_best_loads_endpoint_returns_json_envelope(): void
    {
        $this->actingAsAdmin();

        $response = $this->getJson(self::URI . '/best-loads');

        $response->assertOk();
        $response->assertJsonStructure(['success', 'count', 'loads']);
    }

    public function test_saved_searches_endpoint_returns_json_without_500(): void
    {
        $this->actingAsAdmin();

        // This previously threw a 500 because it dereferenced a null auth('business') user.
        $response = $this->getJson(self::URI . '/saved-searches');

        $response->assertOk();
        $response->assertJsonStructure(['success', 'saved_searches']);
    }

    public function test_missing_driver_returns_404_not_200(): void
    {
        $this->actingAsAdmin();

        $this->getJson(self::URI . '/best-for-driver/99999999')
            ->assertNotFound()
            ->assertJson(['success' => false]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->get(self::URI);

        $this->assertContains(
            $response->getStatusCode(),
            [302, 401, 403],
            'Unauthenticated API call should be redirected or refused, got ' . $response->getStatusCode()
        );
        $this->assertStringNotContainsString('"eligible_drivers"', $response->getContent());
    }
}
