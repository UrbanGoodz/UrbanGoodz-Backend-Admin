<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\Module;
use App\Models\UrbanGoodzBusinessClient;
use App\Models\UrbanGoodzBusinessClientUser;
use App\Models\Zone;
use Illuminate\Database\Query\Expression;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Rebuild target: replaces the vacuous `goto()` + `expect(page).toBeDefined()`
 * Playwright pattern with real HTTP-level assertions that do not depend on
 * the unfinished Admin authentication repair (see docs/qa/E2E_REBUILD_TEST_INVENTORY.md).
 * Every test here hits a real route, performs a real action, and asserts a
 * real, falsifiable outcome (validation errors, redirect target, guard identity,
 * or JSON payload shape) rather than "the page object exists".
 */
class UrbanGoodzPublicSurfaceValidationBoundaryTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Helpers::get_business_settings() checks config('<key>_conf') before it
        // falls back to a process-lifetime-cached DB lookup (see app/CentralLogics/Helpers.php:1315-1352).
        // Setting the *_conf config directly is the established pattern this codebase's
        // own tests use (see AdminLoginRecoveryRegressionTest::withoutMiddleware usage of
        // config()->set('recaptcha_conf', ...)) and avoids cross-test pollution from that
        // static cache, which otherwise persists for the lifetime of the PHPUnit process.
        config()->set('toggle_store_registration_conf', ['value' => '1']);
        config()->set('landing_page_conf', ['value' => '0']);
    }

    // ─── PUBLIC ROUTE RENDERING & NAVIGATION ────────────────────────────

    public function test_public_root_redirects_to_the_registered_login_route(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('login'));

        $this->get('/')->assertRedirect(route('login', ['tab' => 'admin']));
    }

    /**
     * With the "landing_page" business setting configured, these routes must render
     * their real Blade view (not merely respond with some non-500 status).
     *
     * @dataProvider publicInformationalRoutes
     */
    public function test_public_informational_pages_render_the_configured_landing_view(string $uri, string $expectedView): void
    {
        // BLOCKER: the local `urbangoodz_test` database schema is drifted from the
        // current migrations (see docs/qa/E2E_REBUILD_TEST_INVENTORY.md §6 and
        // test-support/reports/phpunit-full.txt) — rendering these views queries a
        // table missing a `type` column that current migrations define. This is an
        // environment/schema-sync issue, not a route or test defect; re-enable once
        // `urbangoodz_test` is migrated to match HEAD.
        $this->markTestSkipped('Blocked on urbangoodz_test schema drift (missing `type` column) — see docs/qa/E2E_REBUILD_TEST_INVENTORY.md §6.');

        config()->set('landing_page_conf', ['value' => '1']);

        $this->get($uri)->assertOk()->assertViewIs($expectedView);
    }

    public static function publicInformationalRoutes(): array
    {
        return [
            'terms-and-conditions' => ['/terms-and-conditions', 'terms-and-conditions'],
            'about-us' => ['/about-us', 'about-us'],
        ];
    }

    /**
     * Real, current app behavior (app/Http/Controllers/HomeController.php:209-225): these
     * routes are gated behind the "landing_page" business setting and 404 by design when
     * it is unconfigured — this is not a route-registration bug, it is the documented
     * fallback path, and is exactly what an honest "route rendering" test must assert
     * instead of assuming success.
     */
    public function test_public_informational_pages_404_cleanly_when_landing_page_is_unconfigured(): void
    {
        config()->set('landing_page_conf', ['value' => '0']);

        $this->get('/terms-and-conditions')->assertNotFound();
        $this->get('/about-us')->assertNotFound();
    }

    public function test_public_zone_list_api_returns_json(): void
    {
        $this->getJson('/api/v1/zone/list')
            ->assertOk()
            ->assertHeader('content-type', 'application/json');
    }

    /**
     * routes/update.php:7 registers a global Route::fallback() that redirects any
     * unmatched web route to '/' (302), rather than a true 404. This is real,
     * intentional application routing behavior — asserted here so a future change
     * to that fallback is caught instead of silently changing user-facing behavior.
     */
    public function test_unknown_public_route_falls_back_to_the_home_redirect_not_a_silent_200(): void
    {
        $this->get('/this-route-does-not-exist-e2e-rebuild-probe')
            ->assertRedirect('/');
    }

    // ─── VENDOR (BUSINESS) REGISTRATION VALIDATION ──────────────────────

    public function test_vendor_registration_rejects_submission_missing_all_required_fields(): void
    {
        $response = $this->postJson('/vendor/apply', []);

        $response->assertOk(); // controller returns 200 with an errors[] payload, not a 4xx
        $errors = collect($response->json('errors'))->pluck('code')->all();

        // Every unconditionally-required field per VendorController.php:86-101 ($cover_photo
        // is 'nullable' and intentionally excluded). Enumerated directly against that
        // validator, not assumed, so this fails loudly if the controller's required set changes.
        foreach ([
            'f_name', 'name', 'address', 'latitude', 'longitude', 'email', 'phone',
            'minimum_delivery_time', 'maximum_delivery_time', 'password',
            'zone_id', 'module_id', 'logo', 'delivery_time_type',
        ] as $field) {
            $this->assertContains($field, $errors, "Expected a validation error for [$field]");
        }
    }

    public function test_vendor_registration_requires_zone_id_specifically(): void
    {
        $response = $this->postJson('/vendor/apply', [
            'f_name' => 'Test',
            'name' => ['default' => 'Test Store'],
            'address' => ['default' => '123 Main St'],
            'latitude' => '29.7604',
            'longitude' => '-95.3698',
            'email' => 'zone-required-probe@urbangoodz.test',
            'phone' => '5551234567',
            'minimum_delivery_time' => '10',
            'maximum_delivery_time' => '20',
            'password' => 'Str0ng!Passw0rd',
            'module_id' => '1',
            'delivery_time_type' => 'min',
            // zone_id intentionally omitted
        ]);

        $response->assertOk();
        $errors = collect($response->json('errors'))->pluck('code')->all();
        $this->assertContains('zone_id', $errors);
    }

    public function test_vendor_registration_requires_a_logo_upload(): void
    {
        $response = $this->postJson('/vendor/apply', [
            'f_name' => 'Test',
            'name' => ['default' => 'Test Store'],
            'address' => ['default' => '123 Main St'],
            'latitude' => '29.7604',
            'longitude' => '-95.3698',
            'email' => 'upload-required-probe@urbangoodz.test',
            'phone' => '5551234568',
            'minimum_delivery_time' => '10',
            'maximum_delivery_time' => '20',
            'password' => 'Str0ng!Passw0rd',
            'zone_id' => '999999',
            'module_id' => '1',
            'delivery_time_type' => 'min',
            // logo intentionally omitted
        ]);

        $response->assertOk();
        $errors = collect($response->json('errors'))->pluck('code')->all();
        $this->assertContains('logo', $errors);
    }

    // ─── ZONE DROPDOWN / ZONE-TO-MODULE DEPENDENCY ──────────────────────

    public function test_zone_scoped_module_dropdown_only_returns_modules_mapped_to_that_zone(): void
    {
        $zoneA = Zone::firstOrCreate(
            ['name' => 'E2E Rebuild Zone A'],
            ['coordinates' => new Expression("ST_GeomFromText('POLYGON((0 0, 0 100, 100 100, 100 0, 0 0))')"), 'status' => 1]
        );
        $zoneB = Zone::firstOrCreate(
            ['name' => 'E2E Rebuild Zone B'],
            ['coordinates' => new Expression("ST_GeomFromText('POLYGON((200 200, 200 300, 300 300, 300 200, 200 200))')"), 'status' => 1]
        );
        $moduleInZoneA = Module::firstOrCreate(
            ['module_name' => 'E2E Rebuild Food Module'],
            ['module_type' => 'food', 'status' => 1]
        );
        $moduleInZoneA->zones()->syncWithoutDetaching([$zoneA->id]);

        $response = $this->getJson('/vendor/get-all-modules?zone_id=' . $zoneA->id . '&q=');
        $response->assertOk();
        $names = collect($response->json())->pluck('text')->all();
        $this->assertContains('E2E Rebuild Food Module', $names);

        $responseForOtherZone = $this->getJson('/vendor/get-all-modules?zone_id=' . $zoneB->id . '&q=');
        $responseForOtherZone->assertOk();
        $namesForOtherZone = collect($responseForOtherZone->json())->pluck('text')->all();
        $this->assertNotContains('E2E Rebuild Food Module', $namesForOtherZone, 'Module scoped to Zone A must not appear for Zone B');
    }

    public function test_module_zone_dependency_check_reflects_real_mapping(): void
    {
        $zone = Zone::firstOrCreate(
            ['name' => 'E2E Rebuild Dependency Zone'],
            ['coordinates' => new Expression("ST_GeomFromText('POLYGON((400 400, 400 500, 500 500, 500 400, 400 400))')"), 'status' => 1]
        );
        $unmappedModule = Module::firstOrCreate(
            ['module_name' => 'E2E Rebuild Unmapped Module'],
            ['module_type' => 'food', 'status' => 1]
        );

        $response = $this->getJson('/vendor/check-module-type?id=' . $unmappedModule->id . '&zone_id=' . $zone->id);
        $response->assertOk();
        $this->assertFalse((bool) $response->json('module_zone'), 'Unmapped module/zone pair must report false');
    }

    // ─── BUSINESS PORTAL LOGIN VALIDATION ───────────────────────────────

    public function test_business_login_rejects_empty_submission_with_validation_errors(): void
    {
        $response = $this->from(route('business.login'))->post(route('business.login.submit'), []);

        $response->assertRedirect(route('business.login'));
        $response->assertSessionHasErrors(['email', 'password']);
    }

    public function test_business_login_rejects_invalid_credentials_without_revealing_account_existence(): void
    {
        // Real account, wrong password.
        $client = UrbanGoodzBusinessClient::firstOrCreate(
            ['email' => 'e2e-rebuild-enum-probe@urbangoodz.test'],
            ['company_name' => 'E2E Rebuild Enumeration Probe Co', 'status' => 'approved']
        );
        UrbanGoodzBusinessClientUser::firstOrCreate(
            ['business_client_id' => $client->id, 'email' => 'e2e-rebuild-enum-probe-user@urbangoodz.test'],
            ['first_name' => 'Enum', 'last_name' => 'Probe', 'password' => bcrypt('the-real-password'), 'role' => 'owner_admin', 'is_active' => true, 'status' => 'active']
        );

        $wrongPasswordResponse = $this->from(route('business.login'))->post(route('business.login.submit'), [
            'email' => 'e2e-rebuild-enum-probe-user@urbangoodz.test',
            'password' => 'wrong-password',
        ]);
        $wrongPasswordResponse->assertRedirect(route('business.login'));
        $wrongPasswordResponse->assertSessionHasErrors();
        // The shared test session's 'errors' key gets overwritten by the next request's
        // withErrors() call, so this must be read now, not after the second request fires.
        $wrongPasswordMessages = session('errors')->getBag('default')->all();
        $this->assertGuest('business');

        // No account at all, made up email — same shape of request, different failure cause.
        $noSuchAccountResponse = $this->from(route('business.login'))->post(route('business.login.submit'), [
            'email' => 'no-such-business-user@urbangoodz.test',
            'password' => 'wrong-password',
        ]);
        $noSuchAccountResponse->assertRedirect(route('business.login'));
        $noSuchAccountResponse->assertSessionHasErrors();
        $noSuchAccountMessages = session('errors')->getBag('default')->all();
        $this->assertGuest('business');

        // The two failure modes must be genuinely indistinguishable to the client — compare
        // the actual error text, not just its absence of a specific substring.
        $this->assertNotEmpty($wrongPasswordMessages);
        $this->assertNotEmpty($noSuchAccountMessages);
        $this->assertSame(
            $noSuchAccountMessages,
            $wrongPasswordMessages,
            'A wrong password on a real account and a login for a nonexistent account must produce byte-identical error text — any difference is an account-enumeration oracle.'
        );
    }

    // ─── ROUTE AUTHORIZATION BOUNDARIES / UNAUTHENTICATED REDIRECTS ─────

    public function test_unauthenticated_business_dashboard_redirects_to_business_login(): void
    {
        $this->get(route('business.dashboard'))->assertRedirect(route('business.login'));
    }

    public function test_unauthenticated_dispatcher_dashboard_redirects_to_business_login(): void
    {
        // The dispatcher surface is nested under the business guard (routes/business.php:149-172);
        // there is no standalone /dispatcher/login route in this application.
        $this->get(route('business.dispatcher.dashboard'))->assertRedirect(route('business.login'));
    }

    public function test_unauthenticated_admin_route_redirects_to_admin_login_not_an_open_page(): void
    {
        // AdminMiddleware::handle() (app/Http/Middleware/AdminMiddleware.php) redirects to
        // route('login', [Helpers::get_login_url('admin_login_url') ?? 'admin']); with no
        // DataSetting rows seeded, get_login_url() falls through to its 'admin' default, and
        // login/{tab} (routes/web.php:53) has a single positional parameter, so this resolves
        // to exactly the same URL asserted at test_public_root_redirects_to_the_registered_login_route
        // above — asserted exactly here too, not just "contains /login/", so a regression to a
        // *different* role's login page (which would also contain that substring) is caught.
        $this->get('/admin/urban-goodz/ai-operations')
            ->assertRedirect(route('login', ['tab' => 'admin']));
    }

    // ─── ROLE SEPARATION (GUARD ISOLATION) ──────────────────────────────

    public function test_authenticated_business_user_cannot_access_admin_guarded_routes(): void
    {
        $client = UrbanGoodzBusinessClient::firstOrCreate(
            ['email' => 'e2e-rebuild-role-separation@urbangoodz.test'],
            ['company_name' => 'E2E Rebuild Role Separation Co', 'status' => 'approved']
        );
        $businessUser = UrbanGoodzBusinessClientUser::firstOrCreate(
            ['business_client_id' => $client->id, 'email' => 'e2e-rebuild-role-separation-user@urbangoodz.test'],
            ['first_name' => 'Role', 'last_name' => 'Separation', 'password' => bcrypt('password'), 'role' => 'owner_admin', 'is_active' => true, 'status' => 'active']
        );

        $this->actingAs($businessUser, 'business');

        // The 'admin' middleware only trusts the 'admin' guard; an authenticated
        // business-guard user must be treated as unauthenticated on admin routes and
        // land on the exact same admin-login URL an anonymous visitor would (not merely
        // "some URL containing /login/", which a redirect to a different role's login
        // page would also satisfy).
        $this->get('/admin/urban-goodz/ai-operations')
            ->assertRedirect(route('login', ['tab' => 'admin']));
        $this->assertGuest('admin');
    }

    public function test_authenticated_business_user_can_reach_business_scoped_routes(): void
    {
        $client = UrbanGoodzBusinessClient::firstOrCreate(
            ['email' => 'e2e-rebuild-business-access@urbangoodz.test'],
            ['company_name' => 'E2E Rebuild Business Access Co', 'status' => 'approved']
        );
        $businessUser = UrbanGoodzBusinessClientUser::firstOrCreate(
            ['business_client_id' => $client->id, 'email' => 'e2e-rebuild-business-access-user@urbangoodz.test'],
            ['first_name' => 'Business', 'last_name' => 'Access', 'password' => bcrypt('password'), 'role' => 'owner_admin', 'is_active' => true, 'status' => 'active']
        );

        $this->actingAs($businessUser, 'business');

        // BLOCKER: BusinessPortalController@dashboard queries a table missing the
        // `business_client_id` column in the local `urbangoodz_test` schema (same
        // schema-drift class as the informational-pages skip above). Re-enable once
        // urbangoodz_test is migrated to match HEAD.
        $this->markTestSkipped('Blocked on urbangoodz_test schema drift (missing `business_client_id` column) — see docs/qa/E2E_REBUILD_TEST_INVENTORY.md §6.');

        $this->get(route('business.dashboard'))->assertOk();
    }

    // ─── API VALIDATION ──────────────────────────────────────────────────

    public function test_customer_api_login_rejects_missing_login_type(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(403);
        $this->assertArrayHasKey('errors', $response->json());
    }

    public function test_customer_api_login_rejects_invalid_login_type(): void
    {
        $response = $this->postJson('/api/v1/auth/login', ['login_type' => 'not-a-real-type']);

        $response->assertStatus(403);
    }

    // ─── CSRF ENFORCEMENT ────────────────────────────────────────────────

    public function test_business_login_route_is_covered_by_csrf_middleware(): void
    {
        // Laravel's base VerifyCsrfToken middleware short-circuits during PHPUnit runs
        // (Illuminate\Foundation\Application::runningUnitTests()), by design, specifically
        // so feature tests don't need a real token for every POST. That means a request-level
        // "POST without a token returns 419" assertion cannot actually prove CSRF protection
        // inside a PHPUnit test — it would just assert PHPUnit's own test harness behavior.
        $this->assertRouteIsCsrfProtected('business.login.submit', 'POST', '/business/login');
    }

    public function test_vendor_registration_route_is_covered_by_csrf_middleware(): void
    {
        // routes/web.php:210-212 — Route::group(['prefix' => 'vendor', 'as' => 'restaurant.'], ...)
        // registers POST vendor/apply as 'restaurant.store' (not a 'vendor.*' name, despite the URI).
        $this->assertRouteIsCsrfProtected('restaurant.store', 'POST', '/vendor/apply');
    }

    /**
     * What we *can* prove without a real HTTP round trip:
     *  1. The route carries the 'web' middleware group (which is where VerifyCsrfToken lives),
     *     AND has not opted itself out via a route-level ->withoutMiddleware(...) call — checked
     *     against Route::excludedMiddleware(), not just the assigned-middleware list, which a
     *     bare ->gatherMiddleware() check would miss entirely.
     *  2. The exact target URI is not matched by the app's real VerifyCsrfToken::$except list,
     *     using Laravel's own protected inExceptArray() method (invoked via reflection) against
     *     a real Request for that URI — not a hand-rolled Str::is() reimplementation, which would
     *     miss a normalized pattern (no leading slash, wildcard segment, etc.) that Laravel's own
     *     trim()+Request::is()/fullUrlIs() matching would still catch.
     */
    private function assertRouteIsCsrfProtected(string $routeName, string $method, string $uri): void
    {
        $route = collect(\Illuminate\Support\Facades\Route::getRoutes())->first(fn ($r) => $r->getName() === $routeName);
        $this->assertNotNull($route, "Route [$routeName] must exist");

        $this->assertContains('web', $route->gatherMiddleware(), "Route [$routeName] must run through the 'web' middleware group");

        $excluded = $route->excludedMiddleware();
        $csrfClass = \App\Http\Middleware\VerifyCsrfToken::class;
        $this->assertNotContains($csrfClass, $excluded, "Route [$routeName] must not opt out of $csrfClass via withoutMiddleware()");
        $this->assertNotContains('web', $excluded, "Route [$routeName] must not opt out of the entire 'web' group via withoutMiddleware()");

        $middleware = app(\App\Http\Middleware\VerifyCsrfToken::class);
        $inExceptArray = new \ReflectionMethod($middleware, 'inExceptArray');
        $inExceptArray->setAccessible(true);
        $request = \Illuminate\Http\Request::create($uri, $method);

        $this->assertFalse(
            $inExceptArray->invoke($middleware, $request),
            "[$uri] must not be matched by VerifyCsrfToken's real except-list logic (getExcludedPaths() + trim + Request::is()/fullUrlIs())"
        );
    }
}
