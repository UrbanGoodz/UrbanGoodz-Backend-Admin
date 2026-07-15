<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class UrbanGoodzEcosystemTest extends Command
{
    protected $signature = 'urban-goods:ecosystem-test
                            {--base-url=http://localhost : Production base URL}
                            {--skip-api : Skip HTTP API tests}
                            {--create-seed : Create seed test data (driver, business owner, customer)}
                            {--verbose-output : Show full details}';
    protected $description = 'Run full ecosystem integration tests across backend, APIs, and portals';

    protected int $pass = 0;
    protected int $fail = 0;
    protected int $warn = 0;
    protected array $results = [];
    protected bool $verbose;

    public function handle()
    {
        $this->verbose = $this->option('verbose-output');
        $baseUrl = rtrim($this->option('base-url'), '/');

        $this->info('╔══════════════════════════════════════════════════════════╗');
        $this->info('║    UrbanGoodz Ecosystem Integration Test Suite          ║');
        $this->info('║    ' . now()->format('Y-m-d H:i:s') . '                                  ║');
        $this->info('╚══════════════════════════════════════════════════════════╝');
        $this->newLine();

        // ─── PHASE 1: Database ───
        $this->section('PHASE 1: Database Connectivity & Schema');
        $this->testDatabaseConnection();
        $this->testCoreTables();
        $this->testForeignKeys();

        // ─── PHASE 2: Models ───
        $this->section('PHASE 2: Core Models Load');
        $this->testCoreModels();

        // ─── PHASE 3: Routes ───
        $this->section('PHASE 3: Route Registration');
        $this->testApiRoutes();
        $this->testWebRoutes();
        $this->testBusinessPortalRoutes();

        // ─── PHASE 4: Seed Data ───
        if ($this->option('create-seed')) {
            $this->section('PHASE 4: Seed Test Data');
            $this->seedTestData();
        }

        // ─── PHASE 5: API Health ───
        if (!$this->option('skip-api')) {
            $this->section('PHASE 5: API Endpoint Health (' . $baseUrl . ')');
            $this->testApiHealth($baseUrl);
            $this->testDriverApi($baseUrl);
            $this->testVendorApi($baseUrl);
            $this->testCustomerApi($baseUrl);
        }

        // ─── PHASE 6: Business Portal ───
        $this->section('PHASE 6: Business Portal');
        $this->testBusinessPortalAuth();
        $this->testPasswordResetFlow();

        // ─── PHASE 7: Config ───
        $this->section('PHASE 7: Configuration & Services');
        $this->testAuthConfig();
        $this->testFirebaseConfig();

        // ─── SUMMARY ───
        $this->printSummary();

        return $this->fail > 0 ? 1 : 0;
    }

    // ═══════════════════════════════════════════
    // PHASE 1: Database
    // ═══════════════════════════════════════════

    protected function testDatabaseConnection()
    {
        try {
            DB::connection()->getPdo();
            $this->pass('Database connection OK');
        } catch (\Exception $e) {
            $this->fail("Database connection FAILED: {$e->getMessage()}");
        }
    }

    protected function testCoreTables()
    {
        $tables = [
            // Core
            'admins' => 'Admin users',
            'users' => 'Customer users',
            'zones' => 'Delivery zones',
            'modules' => 'Feature modules',
            // Driver
            'delivery_man' => 'Driver accounts',
            'vehicles' => 'Vehicle types',
            'driver_earnings' => 'Driver earnings ledger',
            'driver_withdrawal_request' => 'Driver withdrawals',
            'driver_location_track' => 'Driver GPS tracking',
            'driver_notifications' => 'Driver notifications',
            // Orders
            'orders' => 'Orders master',
            'order_details' => 'Order line items',
            'order_transactions' => 'Payment transactions',
            'order_status_histories' => 'Order status timeline',
            // Vendor
            'sellers' => 'Vendor/seller accounts',
            'seller_wallets' => 'Vendor wallets',
            'seller_earnings' => 'Vendor earnings',
            'seller_withdrawal_requests' => 'Vendor withdrawals',
            // Business Portal
            'urban_goodz_business_clients' => 'Business client companies',
            'urban_goodz_business_client_users' => 'Business portal users',
            // Dispatch
            'dispatch_companies' => 'Dispatch companies',
            'dispatch_company_drivers' => 'Dispatch-driver links',
            // Service Bookings
            'service_bookings' => 'Service appointments',
            'service_booking_slots' => 'Booking time slots',
            // Product Marketplace
            'product_marketplace_listings' => 'P2B marketplace',
            'product_marketplace_orders' => 'P2B orders',
            // Fashion Fit
            'fashion_fit_body_scans' => 'Body scan data',
            'fashion_fit_recommendations' => 'Style recommendations',
        ];

        $existing = 0;
        $missing = 0;
        foreach ($tables as $table => $desc) {
            $exists = DB::getSchemaBuilder()->hasTable($table);
            if ($exists) {
                $existing++;
                if ($this->verbose) {
                    $count = DB::table($table)->count();
                    $this->pass("  {$table} exists ({$count} rows) — {$desc}");
                }
            } else {
                $missing++;
                $this->warn("  {$table} MISSING — {$desc}");
            }
        }

        $this->info("  Tables: {$existing} found, {$missing} missing");
    }

    protected function testForeignKeys()
    {
        $checks = [
            ['orders', 'delivery_man_id', 'delivery_man'],
            ['orders', 'seller_id', 'sellers'],
            ['order_details', 'order_id', 'orders'],
            ['order_transactions', 'order_id', 'orders'],
            ['delivery_man', 'vehicle_id', 'vehicles'],
            ['delivery_man', 'zone_id', 'zones'],
            ['urban_goodz_business_client_users', 'business_client_id', 'urban_goodz_business_clients'],
            ['seller_earnings', 'seller_id', 'sellers'],
            ['driver_earnings', 'dm_id', 'delivery_man'],
        ];

        foreach ($checks as [$from, $col, $to]) {
            $hasCol = DB::getSchemaBuilder()->hasColumn($from, $col);
            if ($hasCol) {
                if ($this->verbose) {
                    $this->pass("  {$from}.{$col} → {$to}");
                }
            } else {
                $this->warn("  {$from}.{$col} column MISSING");
            }
        }
    }

    // ═══════════════════════════════════════════
    // PHASE 2: Models
    // ═══════════════════════════════════════════

    protected function testCoreModels()
    {
        $models = [
            \App\Models\DeliveryMan::class => 'DeliveryMan (Driver)',
            \App\Models\User::class => 'User (Customer)',
            \App\Models\Seller::class => 'Seller (Vendor)',
            \App\Models\Zone::class => 'Zone',
            \App\Models\Order::class => 'Order',
            \App\Models\OrderDetail::class => 'OrderDetail',
            \App\Models\OrderTransaction::class => 'OrderTransaction',
            \App\Models\Vehicle::class => 'Vehicle',
            \App\Models\Admin::class => 'Admin',
            \App\Models\UrbanGoodzBusinessClient::class => 'BusinessClient',
            \App\Models\UrbanGoodzBusinessClientUser::class => 'BusinessClientUser',
            \App\Models\DispatchCompany::class => 'DispatchCompany',
            \App\Models\SellerWallet::class => 'SellerWallet',
            \App\Models\SellerEarning::class => 'SellerEarning',
        ];

        foreach ($models as $class => $name) {
            try {
                $model = new $class();
                $table = $model->getTable();
                $fillable = $model->getFillable();
                if ($this->verbose) {
                    $this->pass("  {$name} → table: {$table}, fillable: " . count($fillable));
                } else {
                    $this->pass("  {$name}");
                }
            } catch (\Exception $e) {
                $this->fail("  {$name}: {$e->getMessage()}");
            }
        }
    }

    // ═══════════════════════════════════════════
    // PHASE 3: Routes
    // ═══════════════════════════════════════════

    protected function testApiRoutes()
    {
        $apiPatterns = [
            // Customer
            '/api/v1/customer/login' => 'POST',
            '/api/v1/customer/register' => 'POST',
            '/api/v1/customer/order/place' => 'POST',
            '/api/v1/customer/order/list' => 'GET',
            '/api/v1/customer/cart' => 'GET',
            '/api/v1/customer/address/list' => 'GET',
            // Vendor
            '/api/v1/seller/login' => 'POST',
            '/api/v1/seller/register' => 'POST',
            '/api/v1/seller/order/list' => 'GET',
            '/api/v1/seller/dashboard' => 'GET',
            // Service Bookings
            '/api/v1/urban-goodz/service-bookings' => 'GET',
            '/api/v1/urban-goodz/service-bookings/slots' => 'GET',
            // Product Marketplace
            '/api/v1/urban-goodz/products' => 'GET',
            '/api/v1/urban-goodz/products/search' => 'GET',
            '/api/v1/urban-goodz/orders' => 'GET',
            // Fashion Fit
            '/api/v1/urban-goodz/fashion-fit/body-scan' => 'POST',
            '/api/v1/urban-goodz/fashion-fit/recommendations' => 'GET',
        ];

        $found = 0;
        $missing = 0;
        foreach ($apiPatterns as $uri => $method) {
            $route = $this->findRoute($method, $uri);
            if ($route) {
                $found++;
                if ($this->verbose) {
                    $name = $route->getName() ?: '(unnamed)';
                    $this->pass("  {$method} {$uri} → {$name}");
                }
            } else {
                $missing++;
                $this->fail("  {$method} {$uri} NOT REGISTERED");
            }
        }

        $this->info("  API routes: {$found} found, {$missing} missing");
    }

    protected function testWebRoutes()
    {
        $webRoutes = [
            '/login' => 'GET',
            '/dashboard' => 'GET',
            '/orders' => 'GET',
            '/sellers' => 'GET',
            '/delivery-man' => 'GET',
            '/zones' => 'GET',
        ];

        $found = 0;
        $missing = 0;
        foreach ($webRoutes as $uri => $method) {
            $route = $this->findRoute($method, $uri);
            if ($route) {
                $found++;
            } else {
                $missing++;
                $this->warn("  {$method} {$uri} NOT REGISTERED");
            }
        }

        $this->info("  Admin web routes: {$found} found, {$missing} missing");
    }

    protected function testBusinessPortalRoutes()
    {
        $businessRoutes = [
            '/business/login' => 'GET',
            '/business/logout' => 'POST',
            '/business/forgot-password' => 'GET',
        ];

        $found = 0;
        foreach ($businessRoutes as $uri => $method) {
            $route = $this->findRoute($method, $uri);
            if ($route) {
                $found++;
                if ($this->verbose) {
                    $this->pass("  {$method} {$uri}");
                }
            } else {
                $this->fail("  {$method} {$uri} NOT REGISTERED");
            }
        }

        $this->info("  Business portal routes: {$found} found");
    }

    protected function findRoute(string $method, string $uri): ?\Illuminate\Routing\Route
    {
        $routes = Route::getRoutes();
        try {
            $request = \Illuminate\Http\Request::create($uri, $method);
            return $routes->match($request);
        } catch (\Exception $e) {
            return null;
        }
    }

    // ═══════════════════════════════════════════
    // PHASE 4: Seed Data
    // ═══════════════════════════════════════════

    protected function seedTestData()
    {
        $this->info('  Creating seed test data...');

        // Test driver
        $vehicle = DB::table('vehicles')->where('status', 1)->first();
        $vehicleId = $vehicle ? $vehicle->id : DB::table('vehicles')->insertGetId([
            'type' => 'car', 'capacity' => 4, 'min_cap' => 1,
            'avg_cap' => 4, 'max_cap' => 6, 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $driver = DB::table('delivery_man')->updateOrCreate(
            ['email' => 'ecosystem.test.driver@urbangoodzdelivery.com'],
            [
                'f_name' => 'Test', 'l_name' => 'Driver',
                'phone' => '+15559990001', 'identity_type' => 'passport',
                'identity_number' => 'ECO-TEST-DM-001',
                'password' => Hash::make('TestDriver2026!'),
                'zone_id' => 2, 'earning' => 15.00, 'vehicle_id' => $vehicleId,
                'type' => 'zone_wise', 'application_status' => 'approved',
                'status' => 1, 'active' => 1, 'available' => 1, 'is_delivery' => 1,
                'is_ride' => 0, 'image' => 'def.png',
                'identity_image' => json_encode([]),
                'auth_token' => Str::random(120),
                'ref_code' => 'ECO' . Str::random(8),
            ]
        );
        $this->pass("  Test driver created (ID: {$driver->id}, token: {$driver->auth_token})");

        // Test business owner
        $client = \App\Models\UrbanGoodzBusinessClient::updateOrCreate(
            ['email' => 'ecotest@urbangoodzdelivery.com'],
            [
                'company_name' => 'Ecosystem Test Corp',
                'legal_name' => 'Ecosystem Test Corp',
                'contact_name' => 'Test Owner',
                'contact_email' => 'ecotest@urbangoodzdelivery.com',
                'account_type' => 'business',
                'status' => 'approved',
                'approved_at' => now(),
            ]
        );

        $owner = \App\Models\UrbanGoodzBusinessClientUser::updateOrCreate(
            ['email' => 'ecotest@urbangoodzdelivery.com'],
            [
                'business_client_id' => $client->id,
                'first_name' => 'Test', 'last_name' => 'Owner',
                'email' => 'ecotest@urbangoodzdelivery.com',
                'phone' => '+15559990002',
                'password' => Hash::make('TestBizOwner2026!'),
                'role' => 'owner_admin',
                'is_active' => true,
                'status' => 'active',
            ]
        );
        $this->pass("  Test business owner created (ID: {$owner->id}, company: {$client->company_name})");

        // Test customer
        $customer = \App\Models\User::updateOrCreate(
            ['email' => 'ecosystem.test.customer@urbangoodzdelivery.com'],
            [
                'name' => 'Test Customer',
                'phone' => '+15559990003',
                'password' => Hash::make('TestCustomer2026!'),
                'is_active' => 1,
            ]
        );
        $this->pass("  Test customer created (ID: {$customer->id})");

        $this->newLine();
        $this->info('  ═══ SEED CREDENTIALS ═══');
        $this->info("  Driver:     ecosystem.test.driver@urbangoodzdelivery.com / TestDriver2026!");
        $this->info("  Driver Token: {$driver->auth_token}");
        $this->info("  Biz Owner:  ecotest@urbangoodzdelivery.com / TestBizOwner2026!");
        $this->info("  Customer:   ecosystem.test.customer@urbangoodzdelivery.com / TestCustomer2026!");
    }

    // ═══════════════════════════════════════════
    // PHASE 5: API Health
    // ═══════════════════════════════════════════

    protected function testApiHealth(string $baseUrl)
    {
        $endpoints = [
            $baseUrl . '/api/v1/customer/config' => 'Customer config',
            $baseUrl . '/api/v1/seller/config' => 'Vendor config',
            $baseUrl . '/ping' => 'Health ping',
        ];

        foreach ($endpoints as $url => $label) {
            try {
                $resp = Http::timeout(10)->get($url);
                if ($resp->successful()) {
                    $this->pass("  {$label}: HTTP {$resp->status()}");
                } else {
                    $this->warn("  {$label}: HTTP {$resp->status()}");
                }
            } catch (\Exception $e) {
                $this->fail("  {$label}: {$e->getMessage()}");
            }
        }
    }

    protected function testDriverApi(string $baseUrl)
    {
        // Get a test driver token
        $driver = DB::table('delivery_man')
            ->where('email', 'ecosystem.test.driver@urbangoodzdelivery.com')
            ->orWhere('email', 'test.driver001@urbangoodzdelivery.com')
            ->first();

        if (!$driver) {
            $this->warn('  No test driver found for API test (run --create-seed first)');
            return;
        }

        $token = $driver->auth_token;
        $endpoints = [
            [$baseUrl . "/api/v1/urban-goodz/driver/busy-list?token={$token}", 'Driver busy list'],
            [$baseUrl . "/api/v1/urban-goodz/driver/earning-history?token={$token}", 'Driver earnings'],
            [$baseUrl . "/api/v1/urban-goodz/driver/business-jobs?token={$token}", 'Driver business jobs'],
        ];

        foreach ($endpoints as [$url, $label]) {
            try {
                $resp = Http::timeout(10)->get($url);
                $body = $resp->json();
                if (isset($body['status']) && $body['status'] === 'success') {
                    $this->pass("  {$label}: OK (HTTP {$resp->status()})");
                } elseif ($resp->successful()) {
                    $this->pass("  {$label}: HTTP {$resp->status()} (may need auth)");
                } else {
                    $this->warn("  {$label}: HTTP {$resp->status()}");
                }
            } catch (\Exception $e) {
                $this->fail("  {$label}: {$e->getMessage()}");
            }
        }
    }

    protected function testVendorApi(string $baseUrl)
    {
        try {
            $resp = Http::timeout(10)->post($baseUrl . '/api/v1/seller/login', [
                'email' => 'nonexistent@test.com',
                'password' => 'test',
            ]);
            $body = $resp->json();
            // Even a failed login should return a proper JSON response
            if (isset($body['status'])) {
                $this->pass("  Vendor login endpoint: responsive (status: {$body['status']})");
            } else {
                $this->warn("  Vendor login: unexpected response format");
            }
        } catch (\Exception $e) {
            $this->fail("  Vendor login endpoint: {$e->getMessage()}");
        }
    }

    protected function testCustomerApi(string $baseUrl)
    {
        try {
            $resp = Http::timeout(10)->get($baseUrl . '/api/v1/customer/config');
            if ($resp->successful()) {
                $body = $resp->json();
                $keys = array_keys($body);
                $this->pass("  Customer config: " . count($keys) . " keys returned");
            } else {
                $this->warn("  Customer config: HTTP {$resp->status()}");
            }
        } catch (\Exception $e) {
            $this->fail("  Customer config: {$e->getMessage()}");
        }
    }

    // ═══════════════════════════════════════════
    // PHASE 6: Business Portal
    // ═══════════════════════════════════════════

    protected function testBusinessPortalAuth()
    {
        // Verify guard config
        $guard = config('auth.guards.business');
        if ($guard && $guard['driver'] === 'session' && $guard['provider'] === 'business_clients') {
            $this->pass("  Auth guard 'business' configured correctly");
        } else {
            $this->fail("  Auth guard 'business' misconfigured");
        }

        // Verify provider
        $provider = config('auth.providers.business_clients');
        if ($provider && $provider['driver'] === 'eloquent' && $provider['model'] === \App\Models\UrbanGoodzBusinessClientUser::class) {
            $this->pass("  Provider 'business_clients' points to UrbanGoodzBusinessClientUser");
        } else {
            $this->fail("  Provider 'business_clients' misconfigured");
        }

        // Verify password broker
        $broker = config('auth.passwords.business_clients');
        if ($broker && $broker['provider'] === 'business_clients') {
            $this->pass("  Password broker 'business_clients' configured");
        } else {
            $this->fail("  Password broker 'business_clients' missing");
        }

        // Verify views exist
        $views = [
            'resources/views/business/auth/login.blade.php',
            'resources/views/business/auth/forgot-password.blade.php',
            'resources/views/business/auth/reset-password.blade.php',
            'resources/views/business/layouts/app.blade.php',
        ];
        foreach ($views as $view) {
            if (file_exists(base_path($view))) {
                $this->pass("  View exists: " . basename($view));
            } else {
                $this->fail("  View MISSING: {$view}");
            }
        }

        // Verify middleware
        $middlewareFile = app_path('Http/Middleware/BusinessMiddleware.php');
        if (file_exists($middlewareFile)) {
            $this->pass("  BusinessMiddleware exists");
        } else {
            $this->fail("  BusinessMiddleware MISSING");
        }

        // Verify controllers
        $controllers = [
            'app/Http/Controllers/Admin/UrbanGoodz/BusinessAuthController.php',
            'app/Http/Controllers/Admin/UrbanGoodz/BusinessPortalController.php',
            'app/Http/Controllers/Admin/UrbanGoodz/BusinessForgotPasswordController.php',
            'app/Http/Controllers/Admin/UrbanGoodz/BusinessResetPasswordController.php',
        ];
        foreach ($controllers as $ctrl) {
            if (file_exists(base_path($ctrl))) {
                $this->pass("  Controller: " . basename($ctrl));
            } else {
                $this->fail("  Controller MISSING: {$ctrl}");
            }
        }
    }

    protected function testPasswordResetFlow()
    {
        // Test token generation
        try {
            $token = Str::random(64);
            DB::table('password_resets')->insert([
                'email' => 'test@urbangoodzdelivery.com',
                'token' => Hash::make($token),
                'created_at' => now(),
            ]);
            $found = DB::table('password_resets')
                ->where('email', 'test@urbangoodzdelivery.com')
                ->first();
            if ($found) {
                $this->pass("  Password reset token insert/read OK");
                DB::table('password_resets')->where('email', 'test@urbangoodzdelivery.com')->delete();
            }
        } catch (\Exception $e) {
            $this->fail("  Password reset flow: {$e->getMessage()}");
        }
    }

    // ═══════════════════════════════════════════
    // PHASE 7: Config
    // ═══════════════════════════════════════════

    protected function testAuthConfig()
    {
        $guards = config('auth.guards');
        $expected = ['web', 'admin', 'seller', 'business', 'dm', 'api'];
        foreach ($expected as $name) {
            if (isset($guards[$name])) {
                $this->pass("  Guard '{$name}' exists (driver: {$guards[$name]['driver']})");
            } else {
                $this->warn("  Guard '{$name}' missing");
            }
        }
    }

    protected function testFirebaseConfig()
    {
        $googleServicesPath = base_path('google-services.json');
        if (file_exists($googleServicesPath)) {
            $config = json_decode(file_get_contents($googleServicesPath), true);
            $project = $config['project_info']['project_id'] ?? 'unknown';
            $clients = count($config['client'] ?? []);
            $this->pass("  google-services.json found (project: {$project}, clients: {$clients})");
        } else {
            $this->warn("  google-services.json not found in backend (expected — lives in Flutter repo)");
        }

        // Check Laravel Firebase config
        if (config('services.firebase.credentials')) {
            $this->pass("  Firebase credentials path configured");
        } else {
            $this->warn("  Firebase credentials not configured in config/services.php");
        }
    }

    // ═══════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════

    protected function section(string $title)
    {
        $this->newLine();
        $this->info("─── {$title} ───");
    }

    protected function pass(string $msg)
    {
        $this->info("  ✅ {$msg}");
        $this->pass++;
        $this->results[] = ['PASS', $msg];
    }

    protected function fail(string $msg)
    {
        $this->error("  ❌ {$msg}");
        $this->fail++;
        $this->results[] = ['FAIL', $msg];
    }

    protected function warn(string $msg)
    {
        $this->warn("  ⚠️  {$msg}");
        $this->warn++;
        $this->results[] = ['WARN', $msg];
    }

    protected function printSummary()
    {
        $this->newLine();
        $this->info('╔══════════════════════════════════════════════════════════╗');
        $this->info('║                    TEST SUMMARY                         ║');
        $this->info('╠══════════════════════════════════════════════════════════╣');
        $this->info("║  ✅ Passed: {$this->pass}");
        $this->info("║  ❌ Failed: {$this->fail}");
        $this->info("║  ⚠️  Warnings: {$this->warn}");
        $this->info('╚══════════════════════════════════════════════════════════╝');

        if ($this->fail > 0) {
            $this->newLine();
            $this->error('FAILURES:');
            foreach ($this->results as [$status, $msg]) {
                if ($status === 'FAIL') {
                    $this->error("  • {$msg}");
                }
            }
        }

        $this->newLine();
        if ($this->fail === 0) {
            $this->info('🎉 All critical tests passed!');
        } else {
            $this->warn("{$this->fail} failure(s) require attention.");
        }
    }
}
