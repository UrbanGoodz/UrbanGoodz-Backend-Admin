<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminRole;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The AI Chief of Staff route, controller and view all shipped and the page has
 * always been reachable by typing the URL. It was invisible to the owner because
 * no sidebar that production actually renders carried an entry for it:
 * `layouts.admin.app` includes `_sidebar_{module_type}`, every `admin/urban-goodz*`
 * request resolves to module_type "settings" (CurrentModule), and
 * `_sidebar_settings.blade.php` had zero Urban Goodz links.
 *
 * These tests pin the route, the navigation entry, and the permission behaviour
 * so the surface cannot silently become unreachable again.
 */
class UrbanGoodzAiChiefOfStaffVisibilityTest extends TestCase
{
    use DatabaseTransactions;

    private const ROUTE_NAME = 'admin.urban-goodz.ai-chief-of-staff';
    private const URI = '/admin/urban-goodz/ai-chief-of-staff';
    private const PERMISSION = 'urban_goodz_control_center';

    private function owner(): Admin
    {
        $admin = Admin::firstOrCreate(
            ['email' => 'cos_owner_test@urbangoodz.com'],
            [
                'f_name' => 'Chief', 'l_name' => 'Owner', 'phone' => '5550000001',
                'password' => bcrypt('password'), 'role_id' => 1, 'image' => 'def.png',
            ]
        );

        return $this->makeSessionValid($admin, 1);
    }

    /**
     * AdminMiddleware logs the user straight back out unless `is_logged_in` is
     * set and the session token matches, so a plain actingAs() is not enough.
     */
    private function makeSessionValid(Admin $admin, int $roleId): Admin
    {
        $admin->forceFill([
            'role_id' => $roleId,
            'is_logged_in' => 1,
            'login_remember_token' => 'cos-test-token',
        ])->save();

        $this->withSession(['login_remember_token' => 'cos-test-token']);

        return $admin->fresh();
    }

    /**
     * An admin whose role grants some Urban Goodz access but NOT the
     * control-center permission the Chief of Staff surface requires.
     */
    private function restrictedAdmin(): Admin
    {
        $role = AdminRole::firstOrCreate(
            ['name' => 'cos-restricted-test-role'],
            ['modules' => json_encode(['urban_goodz_dashboard']), 'status' => 1]
        );
        $role->forceFill(['modules' => json_encode(['urban_goodz_dashboard'])])->save();

        $admin = Admin::firstOrCreate(
            ['email' => 'cos_restricted_test@urbangoodz.com'],
            [
                'f_name' => 'Restricted', 'l_name' => 'Admin', 'phone' => '5550000002',
                'password' => bcrypt('password'), 'role_id' => $role->id, 'image' => 'def.png',
            ]
        );

        return $this->makeSessionValid($admin, $role->id);
    }

    public function test_chief_of_staff_route_is_registered_under_the_expected_uri(): void
    {
        $this->assertTrue(
            Route::has(self::ROUTE_NAME),
            'Route ' . self::ROUTE_NAME . ' is not registered.'
        );

        $this->assertSame(self::URI, route(self::ROUTE_NAME, [], false));
    }

    public function test_chief_of_staff_route_carries_the_standard_admin_middleware(): void
    {
        $route = Route::getRoutes()->getByName(self::ROUTE_NAME);
        $middleware = $route->gatherMiddleware();

        // current-module is what populates config('module.current_module_type'),
        // which layouts.admin.app interpolates into the sidebar partial name.
        foreach (['web', 'admin', 'current-module'] as $expected) {
            $this->assertContains($expected, $middleware, "Missing `{$expected}` middleware.");
        }
    }

    public function test_owner_can_open_the_chief_of_staff_page(): void
    {
        $response = $this->actingAs($this->owner(), 'admin')->get(self::URI);

        $response->assertStatus(200);
    }

    public function test_owner_sees_the_chief_of_staff_navigation_entry(): void
    {
        $response = $this->actingAs($this->owner(), 'admin')->get(self::URI);

        $response->assertStatus(200);
        // The rendered admin shell must carry a link to the surface, not just
        // the page body — this is the defect the owner actually reported.
        $response->assertSee(self::URI, false);
        $response->assertSee('AI Chief of Staff', false);
    }

    public function test_restricted_admin_is_denied_the_chief_of_staff_page(): void
    {
        $response = $this->actingAs($this->restrictedAdmin(), 'admin')->get(self::URI);

        $response->assertStatus(403);
    }

    public function test_restricted_admin_does_not_see_the_navigation_entry(): void
    {
        $this->actingAs($this->restrictedAdmin(), 'admin');
        Config::set('module.current_module_type', 'settings');

        $sidebar = view('layouts.admin.partials._sidebar_settings')->render();

        $this->assertStringNotContainsString(self::URI, $sidebar);
    }

    public function test_owner_sees_the_navigation_entry_in_the_settings_sidebar(): void
    {
        $this->actingAs($this->owner(), 'admin');
        Config::set('module.current_module_type', 'settings');

        $sidebar = view('layouts.admin.partials._sidebar_settings')->render();

        $this->assertStringContainsString(self::URI, $sidebar);
        $this->assertStringContainsString('AI Chief of Staff', $sidebar);
    }

    public function test_unauthenticated_request_is_redirected_not_served(): void
    {
        $response = $this->get(self::URI);

        $this->assertSame(302, $response->getStatusCode());
    }
}
