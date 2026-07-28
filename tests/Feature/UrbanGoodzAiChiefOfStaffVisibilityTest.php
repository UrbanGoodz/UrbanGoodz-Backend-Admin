<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminRole;
use App\Services\UrbanGoodz\AiChiefOfStaffService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The AI Chief of Staff route, controller and view all shipped and the page has
 * always been reachable by typing the URL. It was invisible to the owner because
 * no sidebar that production actually renders carried an entry for it:
 * `layouts.admin.app` includes `_sidebar_{module_type}`, every `admin/urban-goodz*`
 * request resolves to module_type "settings" (CurrentModule), and
 * `_sidebar_settings.blade.php` had zero Urban Goodz links.
 *
 * These tests pin the route, the navigation entry, the permission behaviour,
 * and the ten-section functional dashboard so the surface cannot silently
 * become unreachable or broken again.
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

    // ── Visibility tests (original 8) ───────────────────────────────────

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

    // ── Functional dashboard tests ───────────────────────────────────────

    public function test_owner_page_renders_all_ten_section_headings(): void
    {
        $response = $this->actingAs($this->owner(), 'admin')->get(self::URI);

        $response->assertStatus(200);
        $response->assertSee('Orders and Fulfillment', false);
        $response->assertSee('Routes and Exceptions', false);
        $response->assertSee('Driver Operations', false);
        $response->assertSee('Vendors and Businesses', false);
        $response->assertSee('Payments and Ledger', false);
        $response->assertSee('Load Sourcing', false);
        $response->assertSee('AI Provider Health', false);
        $response->assertSee('Recommended Actions', false);
        $response->assertSee('Critical Alerts', false);
        $response->assertSee('Executive Briefing', false);
    }

    public function test_route_summary_with_data_returns_grounded_counts(): void
    {
        if (!Schema::hasTable('urban_goodz_dedicated_routes')) {
            $this->markTestSkipped('urban_goodz_dedicated_routes table not present');
        }

        $service = app(AiChiefOfStaffService::class);
        $before = $service->getRouteAndExceptionSummary()['active_routes']['count'];

        $clientId = DB::table('urban_goodz_business_clients')->insertGetId([
            'company_name' => 'COS Test Client',
            'account_type' => 'business',
            'email' => 'cos_test_client_' . uniqid() . '@test.com',
            'phone' => '5550001000',
            'country' => 'US',
        ]);

        DB::table('urban_goodz_dedicated_routes')->insert([
            'business_client_id' => $clientId,
            'route_name' => 'cos-test-route-1',
            'route_type' => 'delivery',
            'status' => 'active',
            'scheduled_date' => today()->toDateString(),
            'total_packages' => 5,
            'completed_packages' => 3,
            'failed_packages' => 1,
            'returned_packages' => 0,
            'driver_pay_per_package' => 10.00,
            'business_charge_per_package' => 25.00,
            'optimization_status' => 'not_optimized',
        ]);

        DB::table('urban_goodz_dedicated_routes')->insert([
            'business_client_id' => $clientId,
            'route_name' => 'cos-test-route-2',
            'route_type' => 'delivery',
            'status' => 'in_progress',
            'scheduled_date' => today()->toDateString(),
            'total_packages' => 3,
            'completed_packages' => 0,
            'failed_packages' => 0,
            'returned_packages' => 0,
            'driver_pay_per_package' => 10.00,
            'business_charge_per_package' => 25.00,
            'optimization_status' => 'not_optimized',
        ]);

        $result = $service->getRouteAndExceptionSummary();

        $this->assertArrayHasKey('active_routes', $result);
        $this->assertTrue($result['active_routes']['available']);
        $this->assertSame($before + 2, $result['active_routes']['count']);
        $this->assertArrayHasKey('scheduled_routes', $result);
        $this->assertArrayHasKey('unassigned_routes', $result);
        $this->assertArrayHasKey('late_routes', $result);
        $this->assertArrayHasKey('returned_packages', $result);
        $this->assertArrayHasKey('redelivery_requirements', $result);
        $this->assertArrayHasKey('medical_handoff_exceptions', $result);
        $this->assertArrayHasKey('logistics_exceptions', $result);
    }

    public function test_route_summary_truthfully_marks_unsupported_incident_source_unavailable(): void
    {
        $service = app(AiChiefOfStaffService::class);
        $result = $service->getRouteAndExceptionSummary();

        $this->assertFalse($result['courier_incidents']['available']);
        $this->assertNull($result['courier_incidents']['count']);
        $this->assertSame('unavailable', $result['courier_incidents']['state']);
        $this->assertSame(
            'No supported courier incident source is deployed.',
            $result['courier_incidents']['reason']
        );
    }

    public function test_driver_issue_summary_returns_grounded_counts(): void
    {
        if (!Schema::hasTable('delivery_men')) {
            $this->markTestSkipped('delivery_men table not present');
        }

        DB::table('delivery_men')->insert([
            'f_name' => 'CosTest', 'l_name' => 'Driver', 'phone' => '5559990001',
            'identity_image' => 'test.png', 'password' => bcrypt('pass'),
            'status' => 1, 'active' => 1, 'application_status' => 'approved',
            'type' => 'zone_wise', 'is_delivery' => 1,
        ]);

        DB::table('delivery_men')->insert([
            'f_name' => 'CosTest', 'l_name' => 'Pending', 'phone' => '5559990002',
            'identity_image' => 'test.png', 'password' => bcrypt('pass'),
            'status' => 1, 'active' => 1, 'application_status' => 'pending',
            'type' => 'zone_wise', 'is_delivery' => 1,
        ]);

        $service = app(AiChiefOfStaffService::class);
        $result = $service->getDriverIssueSummary();

        $this->assertArrayHasKey('total_drivers', $result);
        $this->assertTrue($result['total_drivers']['available']);
        $this->assertGreaterThanOrEqual(2, $result['total_drivers']['count']);

        $this->assertTrue($result['incomplete_onboarding']['available']);
        $this->assertGreaterThanOrEqual(1, $result['incomplete_onboarding']['count']);
        $this->assertArrayHasKey('inactive_drivers', $result);
        $this->assertArrayHasKey('suspended_drivers', $result);
        $this->assertArrayHasKey('missing_vehicle_data', $result);
        $this->assertArrayHasKey('expired_documents', $result);
        $this->assertArrayHasKey('payout_issues', $result);
        $this->assertArrayHasKey('failed_assignments', $result);
        $this->assertArrayHasKey('late_deliveries', $result);
        $this->assertArrayHasKey('unassigned_work', $result);
        $this->assertArrayHasKey('repeated_cancellations', $result);
        $this->assertArrayHasKey('medical_eligibility_gaps', $result);
        $this->assertArrayHasKey('logistics_capability_gaps', $result);
        $this->assertFalse($result['unresolved_incidents']['available']);
    }

    public function test_provider_health_when_not_configured(): void
    {
        $mockService = $this->createMock(\App\Services\UrbanGoodz\UrbanGoodzAIService::class);
        $mockService->method('isConfigured')->willReturn(false);
        $mockService->method('providerName')->willReturn('gemini');

        $service = new AiChiefOfStaffService($mockService);
        $result = $service->getProviderHealth();

        $this->assertTrue($result['available']);
        $this->assertFalse($result['configured']);
        $this->assertTrue($result['enabled']);
        $this->assertFalse($result['healthy']);
        $this->assertSame('gemini', $result['provider']);
        $this->assertSame('NO', $result['credentials_present']);
        $this->assertSame('not_configured', $result['connectivity_state']);
        $this->assertSame('provider_not_configured', $result['error_code']);
        $this->assertSame('deterministic_only', $result['fallback_state']);
        $this->assertNull($result['last_success']);
        $this->assertSame('provider_not_configured', $result['last_failure_category']);
        $this->assertSame('AI provider credentials are not configured.', $result['reason']);
    }

    public function test_provider_health_when_configured_and_healthy(): void
    {
        $mockService = $this->createMock(\App\Services\UrbanGoodz\UrbanGoodzAIService::class);
        $mockService->method('isConfigured')->willReturn(true);
        $mockService->method('providerName')->willReturn('openai');
        $mockService->method('healthCheck')->willReturn([
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'configured' => true,
            'healthy' => true,
            'error_code' => null,
            'checked_at' => now()->toIso8601String(),
        ]);

        $service = new AiChiefOfStaffService($mockService);
        $result = $service->getProviderHealth();

        $this->assertTrue($result['configured']);
        $this->assertTrue($result['enabled']);
        $this->assertTrue($result['healthy']);
        $this->assertSame('openai', $result['provider']);
        $this->assertSame('gpt-4o', $result['model']);
        $this->assertSame('YES', $result['credentials_present']);
        $this->assertSame('connected', $result['connectivity_state']);
        $this->assertNotNull($result['last_success']);
        $this->assertNull($result['last_failure_category']);
        $this->assertSame('primary_healthy', $result['fallback_state']);
    }

    public function test_provider_health_when_configured_but_unhealthy(): void
    {
        $mockService = $this->createMock(\App\Services\UrbanGoodz\UrbanGoodzAIService::class);
        $mockService->method('isConfigured')->willReturn(true);
        $mockService->method('providerName')->willReturn('openai');
        $mockService->method('healthCheck')->willReturn([
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'configured' => true,
            'healthy' => false,
            'error_code' => 'connection_refused',
            'checked_at' => now()->toIso8601String(),
        ]);

        $service = new AiChiefOfStaffService($mockService);
        $result = $service->getProviderHealth();

        $this->assertTrue($result['configured']);
        $this->assertTrue($result['enabled']);
        $this->assertFalse($result['healthy']);
        $this->assertSame('disconnected', $result['connectivity_state']);
        $this->assertNull($result['last_success']);
        $this->assertSame('connection_refused', $result['last_failure_category']);
        $this->assertSame('deterministic_fallback', $result['fallback_state']);
    }

    public function test_provider_health_when_service_not_injected(): void
    {
        $service = new AiChiefOfStaffService(null);
        $result = $service->getProviderHealth();

        $this->assertFalse($result['configured']);
        $this->assertFalse($result['enabled']);
        $this->assertFalse($result['available']);
        $this->assertSame('NO', $result['credentials_present']);
        $this->assertSame('unavailable', $result['connectivity_state']);
        $this->assertSame('deterministic_only', $result['fallback_state']);
        $this->assertNotNull($result['reason']);
    }

    public function test_provider_health_no_secrets_in_response(): void
    {
        $mockService = $this->createMock(\App\Services\UrbanGoodz\UrbanGoodzAIService::class);
        $mockService->method('isConfigured')->willReturn(true);
        $mockService->method('providerName')->willReturn('openai');
        $mockService->method('healthCheck')->willReturn([
            'provider' => 'openai', 'model' => 'gpt-4o',
            'configured' => true, 'healthy' => true,
            'error_code' => null, 'checked_at' => now()->toIso8601String(),
        ]);

        $service = new AiChiefOfStaffService($mockService);
        $result = $service->getProviderHealth();

        $serialized = json_encode($result);
        $this->assertStringNotContainsString('sk-', $serialized, 'API key prefix must not appear.');
        $this->assertStringNotContainsString('Bearer', $serialized, 'Bearer token must not appear.');
        $this->assertStringNotContainsString('api_key', $serialized, 'API key field must not appear.');
        $this->assertStringNotContainsString('secret', $serialized, 'Secret must not appear.');
    }

    public function test_provider_health_exception_is_sanitized(): void
    {
        $mockService = $this->createMock(\App\Services\UrbanGoodz\UrbanGoodzAIService::class);
        $mockService->method('isConfigured')->willReturn(true);
        $mockService->method('providerName')->willReturn('openai');
        $mockService->method('healthCheck')->willThrowException(
            new \RuntimeException('Bearer sk-owner-secret-token')
        );

        $service = new AiChiefOfStaffService($mockService);
        $result = $service->getProviderHealth();
        $serialized = json_encode($result);

        $this->assertSame('health_check_exception', $result['last_failure_category']);
        $this->assertSame('Provider health check failed.', $result['reason']);
        $this->assertStringNotContainsString('sk-owner-secret-token', $serialized);
        $this->assertStringNotContainsString('Bearer', $serialized);
    }

    public function test_recommendations_include_deterministic_when_alerts_exist(): void
    {
        $mockService = $this->createMock(\App\Services\UrbanGoodz\UrbanGoodzAIService::class);
        $mockService->method('isConfigured')->willReturn(false);

        $service = new AiChiefOfStaffService($mockService);
        $result = $service->getRecommendations();

        $this->assertArrayHasKey('deterministic', $result);
        $this->assertArrayHasKey('ai_analysis', $result);
        $this->assertIsArray($result['deterministic']);
    }

    public function test_recommendations_ai_analysis_unavailable_when_provider_not_configured(): void
    {
        $mockService = $this->createMock(\App\Services\UrbanGoodz\UrbanGoodzAIService::class);
        $mockService->method('isConfigured')->willReturn(false);

        $service = new AiChiefOfStaffService($mockService);
        $result = $service->getRecommendations();

        $this->assertFalse($result['ai_analysis']['available']);
        $this->assertSame('not_configured', $result['ai_analysis']['status']);
        $this->assertSame('provider_not_configured', $result['ai_analysis']['failure_category']);
        $this->assertNotNull($result['ai_analysis']['generated_at']);
        $this->assertSame([], $result['ai_analysis']['items']);
    }

    public function test_no_fabricated_zero_for_missing_tables(): void
    {
        $service = app(AiChiefOfStaffService::class);
        $sections = [
            $service->getOrdersAndFulfillment(),
            $service->getRouteAndExceptionSummary(),
            $service->getDriverIssueSummary(),
            $service->getVendorAndBusinessSummary(),
            $service->getPaymentsAndLedger(),
            $service->getLoadSourcingStatus(),
        ];

        foreach ($sections as $section) {
            foreach ($section as $key => $item) {
                $this->assertArrayHasKey('available', $item, "Section item '{$key}' must declare availability.");
                $this->assertArrayHasKey('count', $item, "Section item '{$key}' must have a count.");
                $this->assertArrayHasKey('state', $item, "Section item '{$key}' must declare a state.");
                if (!$item['available']) {
                    $this->assertNull($item['count'], "Section item '{$key}' must use null, not 0, for missing data.");
                    $this->assertSame('unavailable', $item['state']);
                    $this->assertNotEmpty($item['reason']);
                }
            }
        }
    }

    public function test_section_states_do_not_classify_healthy_fact_totals_as_incidents(): void
    {
        $service = app(AiChiefOfStaffService::class);

        $drivers = $service->getDriverIssueSummary();
        $vendors = $service->getVendorAndBusinessSummary();
        $payments = $service->getPaymentsAndLedger();

        if ($drivers['total_drivers']['available'] && $drivers['total_drivers']['count'] > 0) {
            $this->assertSame('healthy', $drivers['total_drivers']['state']);
        }
        if ($vendors['total_vendors']['available'] && $vendors['total_vendors']['count'] > 0) {
            $this->assertSame('healthy', $vendors['total_vendors']['state']);
        }
        if ($payments['captured']['available'] && $payments['captured']['count'] > 0) {
            $this->assertSame('healthy', $payments['captured']['state']);
        }
    }

    public function test_direct_action_links_are_absolute_urls(): void
    {
        $service = app(AiChiefOfStaffService::class);

        $sections = [
            $service->getOrdersAndFulfillment(),
            $service->getRouteAndExceptionSummary(),
            $service->getDriverIssueSummary(),
            $service->getVendorAndBusinessSummary(),
            $service->getPaymentsAndLedger(),
            $service->getLoadSourcingStatus(),
        ];

        foreach ($sections as $section) {
            foreach ($section as $key => $item) {
                $this->assertArrayHasKey('url', $item, "Section item '{$key}' must have a url.");
                $this->assertStringStartsWith('/', $item['url'], "URL for '{$key}' must start with /.");
            }
        }

        $this->assertSame(
            '/admin/urban-goodz/dedicated-routes',
            $service->getRouteAndExceptionSummary()['active_routes']['url']
        );
        $this->assertSame(
            '/admin/urban-goodz/business-clients',
            $service->getVendorAndBusinessSummary()['business_clients']['url']
        );
        $this->assertSame(
            '/admin/urban-goodz/driver-payouts',
            $service->getDriverIssueSummary()['pending_payouts']['url']
        );
    }

    public function test_no_secret_exposure_in_view(): void
    {
        $response = $this->actingAs($this->owner(), 'admin')->get(self::URI);

        $response->assertStatus(200);
        $content = $response->getContent();

        // Check only our section content for secret leaks, not the admin layout
        // which legitimately contains Firebase config with AIza prefix.
        $ourSections = [
            'Orders and Fulfillment',
            'Routes and Exceptions',
            'Driver Operations',
            'Payments and Ledger',
            'Load Sourcing',
            'AI Provider Health',
            'Recommended Actions',
        ];

        foreach ($ourSections as $section) {
            $sectionPos = strpos($content, $section);
            if ($sectionPos === false) {
                continue; // Section text already checked elsewhere
            }
            // Get ~2000 chars around the section
            $start = max(0, $sectionPos - 500);
            $chunk = substr($content, $start, 3000);
            $this->assertStringNotContainsString('sk-', $chunk, "OpenAI key prefix leaked near '{$section}'.");
            $this->assertStringNotContainsString('Bearer ', $chunk, "Bearer token leaked near '{$section}'.");
            $this->assertStringNotContainsString('base64:', $chunk, "APP_KEY leaked near '{$section}'.");
        }

        // The main page body (excluding layout) should not contain sk- or Bearer
        $bodyStart = strpos($content, '<!-- ========== START MAIN CONTENT');
        if ($bodyStart !== false) {
            $mainContent = substr($content, $bodyStart);
            $this->assertStringNotContainsString('sk-', $mainContent, 'OpenAI key prefix leaked in main content.');
            $this->assertStringNotContainsString('Bearer ', $mainContent, 'Bearer token leaked in main content.');
            $this->assertStringNotContainsString('base64:', $mainContent, 'APP_KEY leaked in main content.');
        }
    }

    public function test_no_tracked_language_files_written_by_translate(): void
    {
        $langPath = base_path('resources/lang/en/messages.php');
        $hashBefore = file_exists($langPath) ? md5_file($langPath) : null;

        $response = $this->actingAs($this->owner(), 'admin')->get(self::URI);
        $response->assertStatus(200);

        $hashAfter = file_exists($langPath) ? md5_file($langPath) : null;

        $this->assertSame($hashBefore, $hashAfter, 'translate() must not write to tracked PHP lang files.');
    }

    public function test_all_ten_sections_render_in_page(): void
    {
        $response = $this->actingAs($this->owner(), 'admin')->get(self::URI);

        $response->assertStatus(200);
        $content = $response->getContent();

        $expectedSections = [
            'Executive Briefing',
            'Orders and Fulfillment',
            'Routes and Exceptions',
            'Driver Operations',
            'Vendors and Businesses',
            'Payments and Ledger',
            'Load Sourcing',
            'AI Provider Health',
            'Recommended Actions',
            'Deterministic Recommendations',
        ];

        foreach ($expectedSections as $section) {
            $this->assertStringContainsString($section, $content, "Section '{$section}' not found in rendered page.");
        }
    }

    public function test_ai_generation_success_populates_ai_analysis(): void
    {
        $mockService = $this->createMock(\App\Services\UrbanGoodz\UrbanGoodzAIService::class);
        $mockService->method('isConfigured')->willReturn(true);
        $mockService->method('providerName')->willReturn('openai');
        $mockService->method('chatResult')->willReturn([
            'success' => true,
            'response' => json_encode([
                ['title' => 'Review expired certifications', 'detail' => '3 certs expiring soon.', 'priority' => 'high'],
                ['title' => 'Check failed deliveries', 'detail' => '1 package failed.', 'priority' => 'medium'],
            ]),
            'error_code' => null,
            'provider' => 'openai',
            'model' => 'gpt-4o',
        ]);

        $service = new AiChiefOfStaffService($mockService);
        $result = $service->getRecommendations();

        $this->assertNotNull($result['ai_analysis']);
        $this->assertSame('ai_generated', $result['ai_analysis']['type']);
        $this->assertSame('openai', $result['ai_analysis']['provider']);
        $this->assertSame('gpt-4o', $result['ai_analysis']['model']);
        $this->assertSame('available', $result['ai_analysis']['status']);
        $this->assertTrue($result['ai_analysis']['available']);
        $this->assertNotNull($result['ai_analysis']['generated_at']);
        $this->assertNull($result['ai_analysis']['failure_category']);
        $this->assertCount(2, $result['ai_analysis']['items']);
        $this->assertSame('Review expired certifications', $result['ai_analysis']['items'][0]['title']);
        $this->assertSame('high', $result['ai_analysis']['items'][0]['priority']);
    }

    public function test_ai_generation_failure_uses_deterministic_fallback(): void
    {
        $mockService = $this->createMock(\App\Services\UrbanGoodz\UrbanGoodzAIService::class);
        $mockService->method('isConfigured')->willReturn(true);
        $mockService->method('providerName')->willReturn('openai');
        $mockService->method('chatResult')->willThrowException(
            new \RuntimeException('Provider timeout with Bearer sk-owner-secret-token')
        );

        $service = new AiChiefOfStaffService($mockService);
        $result = $service->getRecommendations();

        $this->assertArrayHasKey('deterministic', $result);
        $this->assertIsArray($result['deterministic']);
        $this->assertNotNull($result['ai_analysis']);
        $this->assertSame('ai_generated', $result['ai_analysis']['type']);
        $this->assertFalse($result['ai_analysis']['available'] ?? true);
        $this->assertSame('failed', $result['ai_analysis']['status']);
        $this->assertSame('generation_exception', $result['ai_analysis']['failure_category']);
        $this->assertSame(
            'AI analysis generation failed; deterministic recommendations remain active.',
            $result['ai_analysis']['reason']
        );
        $serialized = json_encode($result['ai_analysis']);
        $this->assertStringNotContainsString('sk-owner-secret-token', $serialized);
        $this->assertStringNotContainsString('Bearer', $serialized);
    }

    public function test_ai_failure_result_uses_sanitized_deterministic_fallback(): void
    {
        $mockService = $this->createMock(\App\Services\UrbanGoodz\UrbanGoodzAIService::class);
        $mockService->method('isConfigured')->willReturn(true);
        $mockService->method('providerName')->willReturn('gemini');
        $mockService->method('chatResult')->willReturn([
            'success' => false,
            'response' => 'raw provider response with sk-owner-secret-token',
            'error_code' => 'provider_unavailable',
            'provider' => 'gemini',
            'model' => 'gemini-2.5-flash',
        ]);

        $result = (new AiChiefOfStaffService($mockService))->getRecommendations();
        $serialized = json_encode($result);

        $this->assertIsArray($result['deterministic']);
        $this->assertFalse($result['ai_analysis']['available']);
        $this->assertSame('provider_unavailable', $result['ai_analysis']['failure_category']);
        $this->assertStringNotContainsString('raw provider response', $serialized);
        $this->assertStringNotContainsString('sk-owner-secret-token', $serialized);
    }

    public function test_invalid_ai_response_fails_closed_without_breaking_deterministic_brief(): void
    {
        $mockService = $this->createMock(\App\Services\UrbanGoodz\UrbanGoodzAIService::class);
        $mockService->method('isConfigured')->willReturn(true);
        $mockService->method('providerName')->willReturn('openai');
        $mockService->method('chatResult')->willReturn([
            'success' => true,
            'response' => '{"unexpected":"object"}',
            'error_code' => null,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
        ]);

        $result = (new AiChiefOfStaffService($mockService))->getRecommendations();

        $this->assertIsArray($result['deterministic']);
        $this->assertFalse($result['ai_analysis']['available']);
        $this->assertSame('invalid_provider_response', $result['ai_analysis']['failure_category']);
    }

    public function test_ai_recommendation_text_redacts_secret_patterns(): void
    {
        $mockService = $this->createMock(\App\Services\UrbanGoodz\UrbanGoodzAIService::class);
        $mockService->method('isConfigured')->willReturn(true);
        $mockService->method('providerName')->willReturn('openai');
        $mockService->method('chatResult')->willReturn([
            'success' => true,
            'response' => json_encode([[
                'title' => 'Rotate sk-owner-secret-token',
                'detail' => 'Authorization: Bearer owner-token-value',
                'priority' => 'high',
            ]]),
            'error_code' => null,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
        ]);

        $result = (new AiChiefOfStaffService($mockService))->getRecommendations();
        $serialized = json_encode($result['ai_analysis']);

        $this->assertTrue($result['ai_analysis']['available']);
        $this->assertStringContainsString('[redacted]', $serialized);
        $this->assertStringNotContainsString('sk-owner-secret-token', $serialized);
        $this->assertStringNotContainsString('owner-token-value', $serialized);
    }

    public function test_provider_health_section_exposes_no_secrets_in_rendered_page(): void
    {
        $response = $this->actingAs($this->owner(), 'admin')->get(self::URI);

        $response->assertStatus(200);
        $content = $response->getContent();

        $healthPos = strpos($content, 'AI Provider Health');
        if ($healthPos !== false) {
            $chunk = substr($content, $healthPos, 2000);
            $this->assertStringNotContainsString('sk-', $chunk, 'API key prefix leaked in provider health section.');
            $this->assertStringNotContainsString('Bearer ', $chunk, 'Bearer token leaked in provider health section.');
            $this->assertStringNotContainsString('base64:', $chunk, 'APP_KEY leaked in provider health section.');
            $this->assertStringNotContainsString('api_key', $chunk, 'api_key field leaked in provider health section.');
        }
    }
}
