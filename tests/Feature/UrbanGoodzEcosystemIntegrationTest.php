<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class UrbanGoodzEcosystemIntegrationTest extends TestCase
{
    public function test_ai_operations_admin_route_uses_the_canonical_name(): void
    {
        $this->assertTrue(Route::has('admin.urban-goodz.ai-operations.index'));
        $this->assertFalse(Route::has('urban-goodz.ai-operations.index'));

        $matchedRoute = Route::getRoutes()->match(Request::create('/admin/urban-goodz/ai-operations', 'GET'));
        $this->assertSame('admin.urban-goodz.ai-operations.index', $matchedRoute->getName());
    }

    // ═══════════════════════════════════════════
    // DATABASE INTEGRITY
    // ═══════════════════════════════════════════

    public function test_database_connection_works()
    {
        DB::connection()->getPdo();
        $this->assertTrue(true);
    }

    public function test_core_tables_exist()
    {
        $required = [
            'admins', 'users', 'zones', 'modules', 'delivery_men', 'vehicles',
            'orders', 'order_details', 'order_transactions',
            'vendors', 'stores',
            'urban_goodz_business_clients', 'urban_goodz_business_client_users',
            'urban_goodz_load_board_loads', 'urban_goodz_service_requests',
            'urban_goodz_community_marketplace_items', 'fashion_fit_profiles',
        ];

        foreach ($required as $table) {
            $this->assertTrue(
                DB::getSchemaBuilder()->hasTable($table),
                "Table '{$table}' must exist"
            );
        }
    }

    public function test_driver_earnings_table_has_required_columns()
    {
        $cols = ['id', 'delivery_man_id', 'earning_type', 'amount', 'currency', 'status', 'created_at'];
        foreach ($cols as $col) {
            $this->assertTrue(
                DB::getSchemaBuilder()->hasColumn('urban_goodz_driver_earnings', $col),
                "urban_goodz_driver_earnings.{$col} must exist"
            );
        }
    }

    public function test_route_packages_table_has_required_status_columns()
    {
        $cols = ['id', 'tracking_id', 'business_client_id', 'status', 'dropoff_address', 'created_at'];
        foreach ($cols as $col) {
            $this->assertTrue(
                DB::getSchemaBuilder()->hasColumn('urban_goodz_route_packages', $col),
                "urban_goodz_route_packages.{$col} must exist"
            );
        }
    }

    public function test_package_scans_table_has_required_location_columns()
    {
        $cols = ['id', 'package_id', 'scan_type', 'scanned_by', 'latitude', 'longitude', 'created_at'];
        foreach ($cols as $col) {
            $this->assertTrue(
                DB::getSchemaBuilder()->hasColumn('urban_goodz_package_scans', $col),
                "urban_goodz_package_scans.{$col} must exist"
            );
        }
    }

    // ═══════════════════════════════════════════
    // AUTH SYSTEMS
    // ═══════════════════════════════════════════

    public function test_business_portal_guard_configured()
    {
        $guard = config('auth.guards.business');
        $this->assertNotNull($guard, 'business guard must exist');
        $this->assertEquals('session', $guard['driver']);
        $this->assertEquals('business_clients', $guard['provider']);
    }

    public function test_business_portal_provider_configured()
    {
        $provider = config('auth.providers.business_clients');
        $this->assertNotNull($provider);
        $this->assertEquals('eloquent', $provider['driver']);
        $this->assertEquals(
            \App\Models\UrbanGoodzBusinessClientUser::class,
            $provider['model']
        );
    }

    public function test_business_portal_password_broker_configured()
    {
        $broker = config('auth.passwords.business_clients');
        $this->assertNotNull($broker, 'business_clients password broker must exist');
        $this->assertEquals('business_clients', $broker['provider']);
    }

    public function test_driver_guard_configured()
    {
        $guard = config('auth.guards.delivery_men');
        $this->assertNotNull($guard, 'delivery_men guard must exist');
        $this->assertEquals('session', $guard['driver']);
        $this->assertEquals('delivery_men', $guard['provider']);
    }

    public function test_vendor_guard_configured()
    {
        $guard = config('auth.guards.vendor');
        $this->assertNotNull($guard, 'vendor guard must exist');
        $this->assertEquals('vendors', $guard['provider']);
    }

    // ═══════════════════════════════════════════
    // ROUTE REGISTRATION
    // ═══════════════════════════════════════════

    public function test_business_portal_login_route_exists()
    {
        $response = $this->get('/business/login');
        $response->assertStatus(200);
    }

    public function test_business_portal_forgot_password_route_exists()
    {
        $response = $this->get('/business/forgot-password');
        $response->assertStatus(200);
    }

    public function test_business_portal_unauthenticated_redirects()
    {
        $protected = [
            '/business/dashboard',
            '/business/routes',
            '/business/users',
        ];
        foreach ($protected as $uri) {
            $response = $this->get($uri);
            $response->assertRedirect('/business/login');
        }
    }

    public function test_admin_login_route_exists()
    {
        $loginSlug = \App\CentralLogics\Helpers::get_login_url('admin_login_url');
        $this->assertNotEmpty($loginSlug, 'admin login URL must be configured');
        $response = $this->get('/login/' . $loginSlug);
        $response->assertStatus(200);
    }

    public function test_admin_unauthenticated_redirects_to_login()
    {
        $response = $this->get('/admin');
        $response->assertRedirect();
    }

    // ═══════════════════════════════════════════
    // API ENDPOINTS
    // ═══════════════════════════════════════════

    public function test_customer_config_api_responds()
    {
        $response = $this->getJson('/api/v1/config');
        $response->assertOk();
    }

    public function test_external_config_api_responds()
    {
        $response = $this->getJson('/api/v1/configurations');
        $response->assertOk();
    }

    public function test_customer_login_rejects_empty_credentials()
    {
        $response = $this->postJson('/api/v1/auth/login', []);
        $response->assertForbidden()->assertJsonPath('errors.0.code', 'login_type');
    }

    public function test_vendor_login_rejects_empty_credentials()
    {
        $response = $this->postJson('/api/v1/auth/vendor/login', []);
        $response->assertForbidden()->assertJsonPath('errors.0.code', 'email');
    }

    public function test_driver_api_rejects_no_token()
    {
        $response = $this->getJson('/api/v1/delivery-man/profile');
        $response->assertUnauthorized();
    }

    public function test_service_bookings_api_requires_auth()
    {
        $response = $this->getJson('/api/v1/customer/service-bookings');
        $response->assertUnauthorized();
    }

    public function test_urban_goodz_app_config_requires_auth()
    {
        $response = $this->getJson('/api/v1/urban-goodz/app-config');
        $response->assertUnauthorized();
    }

    public function test_fashion_fit_scan_api_requires_auth()
    {
        $response = $this->getJson('/api/v1/fashion-fit/profiles');
        $response->assertUnauthorized();
    }

    // ═══════════════════════════════════════════
    // BUSINESS PORTAL AUTH FLOW
    // ═══════════════════════════════════════════

    public function test_business_login_rejects_invalid_credentials()
    {
        $response = $this->post('/business/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'wrongpassword',
        ]);
        $response->assertSessionHasErrors();
    }

    public function test_business_forgot_password_rejects_invalid_email()
    {
        $response = $this->post('/business/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);
        // Should redirect back with status (doesn't reveal whether email exists)
        $response->assertRedirect();
    }

    // ═══════════════════════════════════════════
    // MODEL INTEGRITY
    // ═══════════════════════════════════════════

    public function test_delivery_man_model_has_required_fillable()
    {
        $model = new \App\Models\DeliveryMan();
        $required = ['f_name', 'l_name', 'email', 'phone', 'password', 'zone_id'];
        foreach ($required as $field) {
            $this->assertTrue($model->isFillable($field), "DeliveryMan must allow {$field}");
        }
    }

    public function test_order_model_has_required_fillable()
    {
        $model = new \App\Models\Order();
        $casts = $model->getCasts();
        $this->assertArrayHasKey('order_amount', $casts);
        $this->assertArrayHasKey('delivery_man_id', $casts);
        $this->assertArrayHasKey('store_id', $casts);
    }

    public function test_vendor_model_has_required_mass_assignment_policy()
    {
        $model = new \App\Models\Vendor();
        $this->assertContains('id', $model->getGuarded());
        $this->assertTrue($model->isFillable('email'));
    }

    public function test_business_client_user_has_roles()
    {
        $roles = \App\Models\UrbanGoodzBusinessClientUser::ROLES;
        $this->assertContains('owner_admin', $roles);
        $this->assertContains('dispatcher', $roles);
        $this->assertContains('billing_manager', $roles);
        $this->assertContains('read_only_viewer', $roles);
    }

    // ═══════════════════════════════════════════
    // MIDDLEWARE
    // ═══════════════════════════════════════════

    public function test_business_middleware_exists()
    {
        $file = app_path('Http/Middleware/BusinessMiddleware.php');
        $this->assertFileExists($file, 'BusinessMiddleware.php must exist');
    }

    public function test_dispatcher_middleware_exists()
    {
        $file = app_path('Http/Middleware/DispatcherMiddleware.php');
        $this->assertFileExists($file, 'DispatcherMiddleware.php must exist');
    }

    // ═══════════════════════════════════════════
    // VIEWS
    // ═══════════════════════════════════════════

    public function test_admin_login_view_contains_ug_branding()
    {
        $viewPath = resource_path('views/auth/login.blade.php');
        $this->assertFileExists($viewPath);
        $content = file_get_contents($viewPath);
        $this->assertStringContainsString('ug-admin.css', $content, 'Admin login must load ug-admin.css');
    }

    public function test_business_login_view_contains_ug_branding()
    {
        $viewPath = resource_path('views/business/auth/login.blade.php');
        $this->assertFileExists($viewPath);
        $content = file_get_contents($viewPath);
        $this->assertStringContainsString('Urban Goodz', $content, 'Business login must show brand name');
    }

    public function test_recaptcha_custom_captcha_visible()
    {
        $viewPath = resource_path('views/admin-views/partials/_recaptcha.blade.php');
        $this->assertFileExists($viewPath);
        $content = file_get_contents($viewPath);
        $this->assertStringNotContainsString(
            'd-none',
            $content,
            'Custom captcha must not be hidden with d-none'
        );
    }

    // ═══════════════════════════════════════════
    // ARTISAN COMMANDS
    // ═══════════════════════════════════════════

    public function test_ecosystem_test_command_exists()
    {
        $file = app_path('Console/Commands/UrbanGoodzEcosystemTest.php');
        $this->assertFileExists($file, 'UrbanGoodzEcosystemTest command must exist');
    }

    public function test_create_test_driver_command_exists()
    {
        $file = app_path('Console/Commands/CreateTestDriver.php');
        $this->assertFileExists($file, 'CreateTestDriver command must exist');
    }

    public function test_create_business_owner_command_exists()
    {
        $file = app_path('Console/Commands/CreateBusinessOwner.php');
        $this->assertFileExists($file, 'CreateBusinessOwner command must exist');
    }

    // ═══════════════════════════════════════════
    // CONTROLLERS
    // ═══════════════════════════════════════════

    public function test_business_auth_controller_exists()
    {
        $file = app_path('Http/Controllers/Admin/UrbanGoodz/BusinessAuthController.php');
        $this->assertFileExists($file);
    }

    public function test_business_portal_controller_exists()
    {
        $file = app_path('Http/Controllers/Admin/UrbanGoodz/BusinessPortalController.php');
        $this->assertFileExists($file);
    }

    public function test_business_forgot_password_controller_exists()
    {
        $file = app_path('Http/Controllers/Admin/UrbanGoodz/BusinessForgotPasswordController.php');
        $this->assertFileExists($file);
    }

    public function test_business_reset_password_controller_exists()
    {
        $file = app_path('Http/Controllers/Admin/UrbanGoodz/BusinessResetPasswordController.php');
        $this->assertFileExists($file);
    }

    // ═══════════════════════════════════════════
    // BRANDING
    // ═══════════════════════════════════════════

    public function test_ug_admin_css_exists()
    {
        $file = public_path('assets/admin/css/ug-admin.css');
        $this->assertFileExists($file, 'ug-admin.css must exist');
    }

    public function test_logo_svgs_rebranded_to_orange()
    {
        $logos = [
            'logo.svg',
            'logo-white.svg',
            'logo-short.svg',
            'logo-short-white.svg',
        ];
        foreach ($logos as $logo) {
            $path = public_path("assets/admin/svg/logos/{$logo}");
            $this->assertFileExists($path, "{$logo} must exist");
            $content = file_get_contents($path);
            $this->assertStringContainsString(
                '#ED9914',
                $content,
                "{$logo} must use orange #ED9914"
            );
            $this->assertStringNotContainsString(
                '#00868F',
                $content,
                "{$logo} must not contain teal #00868F"
            );
        }
    }
}
