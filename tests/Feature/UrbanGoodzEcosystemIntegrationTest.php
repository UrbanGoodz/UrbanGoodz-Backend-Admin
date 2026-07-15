<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UrbanGoodzEcosystemIntegrationTest extends TestCase
{
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
            'admins', 'users', 'zones', 'modules', 'delivery_man', 'vehicles',
            'orders', 'order_details', 'order_transactions',
            'sellers', 'seller_wallets', 'seller_earnings',
            'urban_goodz_business_clients', 'urban_goodz_business_client_users',
            'dispatch_companies', 'service_bookings', 'product_marketplace_listings',
            'fashion_fit_body_scans',
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
        $cols = ['id', 'dm_id', 'order_id', 'amount', 'tips', 'cash_in_hand', 'created_at'];
        foreach ($cols as $col) {
            $this->assertTrue(
                DB::getSchemaBuilder()->hasColumn('driver_earnings', $col),
                "driver_earnings.{$col} must exist"
            );
        }
    }

    public function test_order_status_histories_table_has_required_columns()
    {
        $cols = ['id', 'order_id', 'status', 'driver_id', 'created_at'];
        foreach ($cols as $col) {
            $this->assertTrue(
                DB::getSchemaBuilder()->hasColumn('order_status_histories', $col),
                "order_status_histories.{$col} must exist"
            );
        }
    }

    public function test_driver_location_track_table_has_required_columns()
    {
        $cols = ['id', 'dm_id', 'latitude', 'longitude', 'created_at'];
        foreach ($cols as $col) {
            $this->assertTrue(
                DB::getSchemaBuilder()->hasColumn('driver_location_track', $col),
                "driver_location_track.{$col} must exist"
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
        $guard = config('auth.guards.dm');
        $this->assertNotNull($guard, 'dm guard must exist');
        $this->assertEquals('session', $guard['driver']);
    }

    public function test_seller_guard_configured()
    {
        $guard = config('auth.guards.seller');
        $this->assertNotNull($guard, 'seller guard must exist');
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
            '/business/orders',
            '/business/users',
        ];
        foreach ($protected as $uri) {
            $response = $this->get($uri);
            $response->assertRedirect('/business/login');
        }
    }

    public function test_admin_login_route_exists()
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }

    public function test_admin_unauthenticated_redirects_to_login()
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect();
    }

    // ═══════════════════════════════════════════
    // API ENDPOINTS
    // ═══════════════════════════════════════════

    public function test_customer_config_api_responds()
    {
        $response = $this->getJson('/api/v1/customer/config');
        $response->assertOk();
    }

    public function test_seller_config_api_responds()
    {
        $response = $this->getJson('/api/v1/seller/config');
        $response->assertOk();
    }

    public function test_customer_login_rejects_empty_credentials()
    {
        $response = $this->postJson('/api/v1/customer/login', []);
        $response->assertJson(['status' => false]);
    }

    public function test_seller_login_rejects_empty_credentials()
    {
        $response = $this->postJson('/api/v1/seller/login', []);
        $response->assertJson(['status' => false]);
    }

    public function test_driver_api_rejects_no_token()
    {
        $response = $this->getJson('/api/v1/urban-goodz/driver/busy-list');
        $response->assertJson(['status' => false]);
    }

    public function test_service_bookings_api_requires_auth()
    {
        $response = $this->getJson('/api/v1/urban-goodz/service-bookings');
        $response->assertJson(['status' => false]);
    }

    public function test_product_marketplace_api_requires_auth()
    {
        $response = $this->getJson('/api/v1/urban-goodz/products');
        $response->assertJson(['status' => false]);
    }

    public function test_fashion_fit_scan_api_requires_auth()
    {
        $response = $this->postJson('/api/v1/urban-goodz/fashion-fit/body-scan', []);
        $response->assertJson(['status' => false]);
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
        $fillable = $model->getFillable();
        $required = ['f_name', 'l_name', 'email', 'phone', 'password', 'zone_id'];
        foreach ($required as $field) {
            $this->assertContains($field, $fillable, "DeliveryMan must fillable {$field}");
        }
    }

    public function test_order_model_has_required_fillable()
    {
        $model = new \App\Models\Order();
        $fillable = $model->getFillable();
        $this->assertNotEmpty($fillable, 'Order must have fillable fields');
    }

    public function test_seller_model_has_required_fillable()
    {
        $model = new \App\Models\Seller();
        $fillable = $model->getFillable();
        $this->assertNotEmpty($fillable, 'Seller must have fillable fields');
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
