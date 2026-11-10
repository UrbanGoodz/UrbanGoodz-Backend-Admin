<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\DeliveryMan;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Suspending / activating a delivery man and approving / denying a delivery man
 * application are state changes. They used to be plain GET links, which carry no
 * CSRF token and can be fired by a crafted link or an <img> tag. They must be
 * POST-only.
 */
class AdminDriverStateChangeRequiresPostTest extends TestCase
{
    use DatabaseTransactions;

    private function makeDriver(array $over = []): DeliveryMan
    {
        return DeliveryMan::create(array_merge([
            'f_name' => 'Drv', 'l_name' => 'Post',
            'phone' => '+1555'.random_int(1000000, 9999999),
            'email' => 'driver-post-'.uniqid().'@urbangoodz.test',
            'password' => bcrypt('password'),
            'auth_token' => 'tok-'.bin2hex(random_bytes(12)),
            'status' => 1, 'application_status' => 'approved', 'active' => 1,
        ], $over));
    }

    private function actAsAdmin(): void
    {
        $admin = Admin::create([
            'f_name' => 'Post', 'l_name' => 'Admin', 'email' => 'post-admin-'.uniqid().'@urbangoodz.test',
            'phone' => '1555'.random_int(1000000, 9999999), 'password' => bcrypt('x'), 'role_id' => 1,
            'is_logged_in' => 1, 'login_remember_token' => 'tok',
        ]);
        $this->withoutMiddleware(\App\Http\Middleware\ActivationCheckMiddleware::class);
        $this->actingAs($admin, 'admin')->withSession(['login_remember_token' => 'tok']);
    }

    /** The app's exception handler may turn the 405 into a redirect; either is fine, a 2xx is not. */
    private function assertGetIsRefused(string $uri): void
    {
        $status = $this->get($uri)->getStatusCode();
        $this->assertContains($status, [405, 302], "GET {$uri} must not be served (got {$status})");
    }

    public function test_both_routes_are_registered_post_only_under_their_existing_names(): void
    {
        foreach (['admin.users.delivery-man.status', 'admin.users.delivery-man.application'] as $name) {
            $route = app('router')->getRoutes()->getByName($name);
            $this->assertNotNull($route, "route {$name} must still exist");
            $this->assertSame(['POST'], $route->methods(), "route {$name} must be POST-only");
            $this->assertContains('web', $route->gatherMiddleware(), "route {$name} must run the web (CSRF) middleware group");
        }
        $this->assertStringEndsWith('/admin/users/delivery-man/status/5/0', route('admin.users.delivery-man.status', [5, 0]));
        $this->assertStringEndsWith('/admin/users/delivery-man/update-application/5/denied', route('admin.users.delivery-man.application', [5, 'denied']));
    }

    public function test_get_does_not_suspend_a_driver(): void
    {
        $driver = $this->makeDriver();
        $this->actAsAdmin();

        $this->assertGetIsRefused("/admin/users/delivery-man/status/{$driver->id}/0");

        $this->assertSame(1, (int) $driver->fresh()->status);
    }

    public function test_get_does_not_change_an_application(): void
    {
        $driver = $this->makeDriver(['application_status' => 'pending', 'status' => 0]);
        $this->actAsAdmin();

        $this->assertGetIsRefused("/admin/users/delivery-man/update-application/{$driver->id}/approved");
        $this->assertGetIsRefused("/admin/users/delivery-man/update-application/{$driver->id}/denied");

        $fresh = $driver->fresh();
        $this->assertSame('pending', $fresh->application_status);
        $this->assertSame(0, (int) $fresh->status);
    }

    public function test_admin_post_suspends_and_reactivates_a_driver(): void
    {
        $driver = $this->makeDriver();
        $this->actAsAdmin();

        $this->post("/admin/users/delivery-man/status/{$driver->id}/0");
        $this->assertSame(0, (int) $driver->fresh()->status);

        $this->post("/admin/users/delivery-man/status/{$driver->id}/1");
        $this->assertSame(1, (int) $driver->fresh()->status);
    }

    public function test_admin_post_approves_and_denies_an_application(): void
    {
        $driver = $this->makeDriver(['application_status' => 'pending', 'status' => 0]);
        $this->actAsAdmin();

        $this->post("/admin/users/delivery-man/update-application/{$driver->id}/approved");
        $this->assertSame('approved', $driver->fresh()->application_status);

        $this->post("/admin/users/delivery-man/update-application/{$driver->id}/denied");
        $this->assertSame('denied', $driver->fresh()->application_status);
    }

    public function test_post_without_an_admin_session_changes_nothing(): void
    {
        $active = $this->makeDriver();
        $pending = $this->makeDriver(['application_status' => 'pending', 'status' => 0]);

        $status = $this->post("/admin/users/delivery-man/status/{$active->id}/0")->getStatusCode();
        $this->assertNotSame(200, $status);
        $this->post("/admin/users/delivery-man/update-application/{$pending->id}/approved");

        $this->assertSame(1, (int) $active->fresh()->status);
        $this->assertSame('pending', $pending->fresh()->application_status);
    }

    public function test_post_without_a_csrf_token_is_rejected_when_csrf_verification_is_on(): void
    {
        // Laravel skips CSRF verification while running unit tests; turn that
        // bypass off for this request so the real middleware behaviour is seen.
        $driver = $this->makeDriver();
        $this->actAsAdmin();
        $this->app->instance('env', 'testing-csrf');
        $this->app['env'] = 'testing-csrf';

        if ($this->app->runningUnitTests()) {
            $this->markTestSkipped('could not disable the unit-test CSRF bypass');
        }

        $this->post("/admin/users/delivery-man/status/{$driver->id}/0")->assertStatus(419);

        $this->assertSame(1, (int) $driver->fresh()->status);
    }

    public function test_the_vendor_panel_driver_status_and_earning_routes_are_post_only(): void
    {
        foreach (['vendor.delivery-man.status', 'vendor.delivery-man.earning'] as $name) {
            $route = \Illuminate\Support\Facades\Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "{$name} must exist");
            $this->assertSame(['POST'], $route->methods(), "{$name} changes state and must not be reachable by GET");
        }
    }
}
