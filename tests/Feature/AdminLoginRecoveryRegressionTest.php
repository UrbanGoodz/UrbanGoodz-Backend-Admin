<?php

namespace Tests\Feature;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Middleware\ActivationCheckMiddleware;
use App\Models\Admin;
use App\Models\AdminRole;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminLoginRecoveryRegressionTest extends TestCase
{
    private const CSRF_TOKEN = 'regression-csrf-token';

    private function bootSqliteAdminSchema(): void
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.url', null);
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        DB::connection('sqlite')->setReadPdo(DB::connection('sqlite')->getPdo());

        Schema::connection('sqlite')->create('admins', function (Blueprint $table): void {
            $table->id();
            $table->string('f_name')->nullable();
            $table->string('l_name')->nullable();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('password')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('image')->nullable();
            $table->boolean('is_logged_in')->default(false);
            $table->string('login_remember_token')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('admin_roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('modules')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('translations', function (Blueprint $table): void {
            $table->id();
            $table->string('translationable_type');
            $table->unsignedBigInteger('translationable_id');
            $table->string('locale')->nullable();
            $table->string('key')->nullable();
            $table->text('value')->nullable();
        });

        Schema::connection('sqlite')->create('data_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->nullable();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('storages', function (Blueprint $table): void {
            $table->id();
            $table->string('data_type')->nullable();
            $table->unsignedBigInteger('data_id')->nullable();
            $table->string('key')->nullable();
            $table->text('value')->nullable();
        });

        $this->withoutMiddleware(ActivationCheckMiddleware::class);
    }

    private function disableGoogleRecaptcha(): void
    {
        config()->set('recaptcha_conf', [
            'value' => json_encode(['status' => 0], JSON_THROW_ON_ERROR),
        ]);
    }

    private function enableGoogleRecaptcha(): void
    {
        config()->set('recaptcha_conf', [
            'value' => json_encode([
                'status' => 1,
                'secret_key' => 'test-secret-key',
                'site_key' => 'test-site-key',
            ], JSON_THROW_ON_ERROR),
        ]);
    }

    private function enableGoogleRecaptchaWithoutSecret(): void
    {
        config()->set('recaptcha_conf', [
            'value' => json_encode([
                'status' => 1,
                'secret_key' => '',
                'site_key' => 'test-site-key',
            ], JSON_THROW_ON_ERROR),
        ]);
    }

    private function postGoogleRecaptchaLogin(Admin $admin, array $extra = [])
    {
        return $this
            ->from('/login/admin')
            ->withSession(['_token' => self::CSRF_TOKEN])
            ->post('/login_submit', array_merge([
                '_token' => self::CSRF_TOKEN,
                'email' => $admin->email,
                'password' => 'valid-password',
                'role' => 'admin',
                'g-recaptcha-response' => 'fake-token',
            ], $extra));
    }

    private function createAdmin(array $overrides = []): Admin
    {
        return Admin::create(array_merge([
            'f_name' => 'Regression',
            'l_name' => 'Admin',
            'email' => 'regression-admin@urban-goodz.test',
            'phone' => '15555550199',
            'password' => bcrypt('valid-password'),
            'role_id' => 1,
            'is_logged_in' => 0,
        ], $overrides));
    }

    public function test_omitted_custom_captcha_is_rejected(): void
    {
        $this->bootSqliteAdminSchema();
        $this->disableGoogleRecaptcha();
        $admin = $this->createAdmin();

        $response = $this
            ->from('/login/admin')
            ->withSession(['_token' => self::CSRF_TOKEN, 'six_captcha' => 'CORRECT'])
            ->post('/login_submit', [
                '_token' => self::CSRF_TOKEN,
                'email' => $admin->email,
                'password' => 'valid-password',
                'role' => 'admin',
            ]);

        $response->assertRedirect('/login/admin');
        $response->assertSessionHasErrors();
        $this->assertGuest('admin');
    }

    public function test_missing_custom_captcha_session_phrase_is_rejected(): void
    {
        $this->bootSqliteAdminSchema();
        $this->disableGoogleRecaptcha();
        $admin = $this->createAdmin();

        $response = $this
            ->from('/login/admin')
            ->withSession(['_token' => self::CSRF_TOKEN])
            ->post('/login_submit', [
                '_token' => self::CSRF_TOKEN,
                'email' => $admin->email,
                'password' => 'valid-password',
                'role' => 'admin',
                'custome_recaptcha' => 'ANYTHING',
            ]);

        $response->assertRedirect('/login/admin');
        $response->assertSessionHasErrors();
        $this->assertGuest('admin');
    }

    public function test_missing_google_recaptcha_token_is_rejected(): void
    {
        $this->bootSqliteAdminSchema();
        $this->enableGoogleRecaptcha();
        $admin = $this->createAdmin();
        Http::fake();

        $response = $this
            ->from('/login/admin')
            ->withSession(['_token' => self::CSRF_TOKEN])
            ->post('/login_submit', [
                '_token' => self::CSRF_TOKEN,
                'email' => $admin->email,
                'password' => 'valid-password',
                'role' => 'admin',
            ]);

        $response->assertRedirect('/login/admin');
        $response->assertSessionHasErrors();
        $this->assertGuest('admin');
        Http::assertNothingSent();
    }

    public function test_failed_google_recaptcha_verification_is_rejected(): void
    {
        $this->bootSqliteAdminSchema();
        $this->enableGoogleRecaptcha();
        $admin = $this->createAdmin();
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response(['success' => false], 200),
        ]);

        $response = $this
            ->from('/login/admin')
            ->withSession(['_token' => self::CSRF_TOKEN])
            ->post('/login_submit', [
                '_token' => self::CSRF_TOKEN,
                'email' => $admin->email,
                'password' => 'valid-password',
                'role' => 'admin',
                'g-recaptcha-response' => 'fake-token',
            ]);

        $response->assertRedirect('/login/admin');
        $response->assertSessionHasErrors();
        $this->assertGuest('admin');
    }

    public function test_google_recaptcha_verification_timeout_is_rejected(): void
    {
        $this->bootSqliteAdminSchema();
        $this->enableGoogleRecaptcha();
        $admin = $this->createAdmin();
        Http::fake(function () {
            throw new ConnectionException('Connection timed out');
        });

        $response = $this
            ->from('/login/admin')
            ->withSession(['_token' => self::CSRF_TOKEN])
            ->post('/login_submit', [
                '_token' => self::CSRF_TOKEN,
                'email' => $admin->email,
                'password' => 'valid-password',
                'role' => 'admin',
                'g-recaptcha-response' => 'fake-token',
            ]);

        $response->assertRedirect('/login/admin');
        $response->assertSessionHasErrors();
        $this->assertGuest('admin');
    }

    public function test_google_recaptcha_missing_secret_is_rejected(): void
    {
        $this->bootSqliteAdminSchema();
        $this->enableGoogleRecaptchaWithoutSecret();
        $admin = $this->createAdmin();
        Http::fake();

        $response = $this->postGoogleRecaptchaLogin($admin);

        $response->assertRedirect('/login/admin');
        $response->assertSessionHasErrors();
        $this->assertGuest('admin');
        Http::assertNothingSent();
    }

    public function test_google_recaptcha_non_success_http_status_is_rejected(): void
    {
        $this->bootSqliteAdminSchema();
        $this->enableGoogleRecaptcha();
        $admin = $this->createAdmin();
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true, 'score' => 0.9, 'action' => 'submit'], 500),
        ]);

        $response = $this->postGoogleRecaptchaLogin($admin);

        $response->assertRedirect('/login/admin');
        $response->assertSessionHasErrors();
        $this->assertGuest('admin');
    }

    public function test_google_recaptcha_generic_exception_is_rejected(): void
    {
        $this->bootSqliteAdminSchema();
        $this->enableGoogleRecaptcha();
        $admin = $this->createAdmin();
        Http::fake(function () {
            throw new \RuntimeException('unexpected transport failure');
        });

        $response = $this->postGoogleRecaptchaLogin($admin);

        $response->assertRedirect('/login/admin');
        $response->assertSessionHasErrors();
        $this->assertGuest('admin');
    }

    public function test_google_recaptcha_missing_score_is_rejected(): void
    {
        $this->bootSqliteAdminSchema();
        $this->enableGoogleRecaptcha();
        $admin = $this->createAdmin();
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true, 'action' => 'submit'], 200),
        ]);

        $response = $this->postGoogleRecaptchaLogin($admin);

        $response->assertRedirect('/login/admin');
        $response->assertSessionHasErrors();
        $this->assertGuest('admin');
    }

    public function test_google_recaptcha_non_numeric_score_is_rejected(): void
    {
        $this->bootSqliteAdminSchema();
        $this->enableGoogleRecaptcha();
        $admin = $this->createAdmin();
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true, 'score' => 'not-a-number', 'action' => 'submit'], 200),
        ]);

        $response = $this->postGoogleRecaptchaLogin($admin);

        $response->assertRedirect('/login/admin');
        $response->assertSessionHasErrors();
        $this->assertGuest('admin');
    }

    public function test_google_recaptcha_low_score_is_rejected(): void
    {
        $this->bootSqliteAdminSchema();
        $this->enableGoogleRecaptcha();
        $admin = $this->createAdmin();
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true, 'score' => 0.49, 'action' => 'submit'], 200),
        ]);

        $response = $this->postGoogleRecaptchaLogin($admin);

        $response->assertRedirect('/login/admin');
        $response->assertSessionHasErrors();
        $this->assertGuest('admin');
    }

    public function test_google_recaptcha_missing_action_is_rejected(): void
    {
        $this->bootSqliteAdminSchema();
        $this->enableGoogleRecaptcha();
        $admin = $this->createAdmin();
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true, 'score' => 0.9], 200),
        ]);

        $response = $this->postGoogleRecaptchaLogin($admin);

        $response->assertRedirect('/login/admin');
        $response->assertSessionHasErrors();
        $this->assertGuest('admin');
    }

    public function test_google_recaptcha_wrong_action_is_rejected(): void
    {
        $this->bootSqliteAdminSchema();
        $this->enableGoogleRecaptcha();
        $admin = $this->createAdmin();
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true, 'score' => 0.9, 'action' => 'homepage'], 200),
        ]);

        $response = $this->postGoogleRecaptchaLogin($admin);

        $response->assertRedirect('/login/admin');
        $response->assertSessionHasErrors();
        $this->assertGuest('admin');
    }

    public function test_google_recaptcha_valid_response_at_score_threshold_succeeds(): void
    {
        $this->bootSqliteAdminSchema();
        $this->enableGoogleRecaptcha();
        $admin = $this->createAdmin();
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true, 'score' => 0.5, 'action' => 'submit'], 200),
        ]);

        $response = $this->postGoogleRecaptchaLogin($admin);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin->fresh(), 'admin');
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $this->bootSqliteAdminSchema();
        $this->disableGoogleRecaptcha();
        $admin = $this->createAdmin();

        $response = $this
            ->from('/login/admin')
            ->withSession(['_token' => self::CSRF_TOKEN, 'six_captcha' => 'CORRECT'])
            ->post('/login_submit', [
                '_token' => self::CSRF_TOKEN,
                'email' => $admin->email,
                'password' => 'wrong-password',
                'role' => 'admin',
                'custome_recaptcha' => 'CORRECT',
            ]);

        $response->assertRedirect('/login/admin');
        $response->assertSessionHasErrors();
        $this->assertGuest('admin');
    }

    /**
     * Perform one isolated failed-login attempt and snapshot every
     * externally-observable outcome immediately afterwards.
     *
     * Rate limiter and session are cleared first so each case is measured
     * from the same starting state and the limiter count reflects only
     * this attempt.
     */
    private function snapshotFailedAdminLogin(string $email, string $password): array
    {
        $limiterKey = 'login-attempts:127.0.0.1';
        RateLimiter::clear($limiterKey);
        $this->flushSession();
        auth('admin')->logout();

        $response = $this
            ->from('/login/admin')
            ->withSession(['_token' => self::CSRF_TOKEN, 'six_captcha' => 'CORRECT'])
            ->post('/login_submit', [
                '_token' => self::CSRF_TOKEN,
                'email' => $email,
                'password' => $password,
                'role' => 'admin',
                'custome_recaptcha' => 'CORRECT',
            ]);

        $oldInput = session()->getOldInput();
        ksort($oldInput);

        return [
            'status' => $response->getStatusCode(),
            'redirect' => $response->headers->get('Location'),
            'errors' => session('errors')->getBag('default')->all(),
            'is_guest' => !auth('admin')->check(),
            'old_input_keys' => array_keys($oldInput),
            'old_input_has_password' => array_key_exists('password', $oldInput),
            'limiter_attempts' => RateLimiter::attempts($limiterKey),
        ];
    }

    public function test_unknown_email_and_wrong_password_produce_identical_responses(): void
    {
        $this->bootSqliteAdminSchema();
        $this->disableGoogleRecaptcha();
        $admin = $this->createAdmin();

        $unknownEmail = $this->snapshotFailedAdminLogin('no-such-admin@urban-goodz.test', 'whatever-password');
        $wrongPassword = $this->snapshotFailedAdminLogin($admin->email, 'wrong-password');

        // Every observable dimension must match, not just the error text:
        // status, redirect target, error bag, auth state, which fields were
        // re-flashed, and how many limiter hits the attempt cost.
        $this->assertSame($unknownEmail, $wrongPassword);

        // And each dimension must independently be the safe value.
        $this->assertSame(302, $unknownEmail['status']);
        $this->assertStringEndsWith('/login/admin', $unknownEmail['redirect']);
        $this->assertSame(['Invalid email or password.'], $unknownEmail['errors']);
        $this->assertTrue($unknownEmail['is_guest']);
        $this->assertFalse($unknownEmail['old_input_has_password']);
        $this->assertSame(1, $unknownEmail['limiter_attempts']);
        $this->assertSame(1, $wrongPassword['limiter_attempts']);
    }

    public function test_successful_admin_login_reaches_the_dashboard_redirect(): void
    {
        $this->bootSqliteAdminSchema();
        $this->disableGoogleRecaptcha();
        $admin = $this->createAdmin();

        $response = $this
            ->withSession(['_token' => self::CSRF_TOKEN, 'six_captcha' => 'CORRECT'])
            ->post('/login_submit', [
                '_token' => self::CSRF_TOKEN,
                'email' => $admin->email,
                'password' => 'valid-password',
                'role' => 'admin',
                'custome_recaptcha' => 'CORRECT',
            ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin->fresh(), 'admin');
    }

    public function test_logout_invalidates_the_admin_session(): void
    {
        $this->bootSqliteAdminSchema();
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'admin');
        $this->assertAuthenticated('admin');

        $response = $this->get('/logout');

        $response->assertRedirect();
        $this->assertGuest('admin');
    }

    public function test_repeated_failed_attempts_are_rate_limited(): void
    {
        $this->bootSqliteAdminSchema();
        $this->disableGoogleRecaptcha();

        for ($i = 0; $i < 5; $i++) {
            $response = $this
                ->withSession(['_token' => self::CSRF_TOKEN, 'six_captcha' => 'CORRECT'])
                ->post('/login_submit', [
                    '_token' => self::CSRF_TOKEN,
                    'email' => 'nobody@urban-goodz.test',
                    'password' => 'irrelevant',
                    'role' => 'admin',
                    'custome_recaptcha' => 'CORRECT',
                ]);
            $response->assertSessionHasErrors();
        }

        $response = $this
            ->withSession(['_token' => self::CSRF_TOKEN, 'six_captcha' => 'CORRECT'])
            ->post('/login_submit', [
                '_token' => self::CSRF_TOKEN,
                'email' => 'nobody@urban-goodz.test',
                'password' => 'irrelevant',
                'role' => 'admin',
                'custome_recaptcha' => 'CORRECT',
            ]);

        $response->assertSessionHasErrors();
        $errors = session('errors')->getBag('default')->all();
        $this->assertNotEmpty(array_filter($errors, fn ($message) => str_contains($message, 'Too many login attempts')));
        $this->assertGuest('admin');
    }

    public function test_module_permission_check_grants_urban_goodz_view_to_the_primary_admin(): void
    {
        $this->bootSqliteAdminSchema();
        $admin = $this->createAdmin(['role_id' => 1]);
        $this->actingAs($admin, 'admin');

        $this->assertTrue(Helpers::module_permission_check('urban_goodz_view'));
    }

    public function test_module_permission_check_grants_urban_goodz_view_when_role_includes_it(): void
    {
        $this->bootSqliteAdminSchema();
        AdminRole::create(['name' => 'placeholder-role-1', 'modules' => json_encode([]), 'status' => true]);
        $role = AdminRole::create([
            'name' => 'Urban Goodz Operator',
            'modules' => json_encode(['urban_goodz_view', 'order_management']),
            'status' => true,
        ]);
        $admin = $this->createAdmin([
            'email' => 'ug-operator@urban-goodz.test',
            'role_id' => $role->id,
        ]);
        $this->actingAs($admin, 'admin');

        $this->assertTrue(Helpers::module_permission_check('urban_goodz_view'));
    }

    public function test_module_permission_check_denies_urban_goodz_view_when_role_excludes_it(): void
    {
        $this->bootSqliteAdminSchema();
        AdminRole::create(['name' => 'placeholder-role-1', 'modules' => json_encode([]), 'status' => true]);
        $role = AdminRole::create([
            'name' => 'Support Staff',
            'modules' => json_encode(['order_management']),
            'status' => true,
        ]);
        $admin = $this->createAdmin([
            'email' => 'support-staff@urban-goodz.test',
            'role_id' => $role->id,
        ]);
        $this->actingAs($admin, 'admin');

        $this->assertFalse(Helpers::module_permission_check('urban_goodz_view'));
    }

    public function test_urban_goodz_dashboard_data_is_empty_for_a_guest(): void
    {
        $this->bootSqliteAdminSchema();

        $data = DashboardController::urban_goodz_dashboard_data();

        $this->assertSame([], $data);
    }

    public function test_urban_goodz_dashboard_data_is_empty_for_an_admin_without_the_permission(): void
    {
        $this->bootSqliteAdminSchema();
        AdminRole::create(['name' => 'placeholder-role-1', 'modules' => json_encode([]), 'status' => true]);
        $role = AdminRole::create([
            'name' => 'Support Staff',
            'modules' => json_encode(['order_management']),
            'status' => true,
        ]);
        $admin = $this->createAdmin([
            'email' => 'support-staff-dashboard@urban-goodz.test',
            'role_id' => $role->id,
        ]);
        $this->actingAs($admin, 'admin');

        $data = DashboardController::urban_goodz_dashboard_data();

        $this->assertSame([], $data);
    }

    public function test_urban_goodz_dashboard_data_is_populated_for_the_primary_admin(): void
    {
        $this->bootSqliteAdminSchema();
        $admin = $this->createAdmin(['role_id' => 1]);
        $this->actingAs($admin, 'admin');

        $data = DashboardController::urban_goodz_dashboard_data();

        $this->assertNotSame([], $data);
        $this->assertCount(25, $data);
        $this->assertArrayHasKey('order_anywhere_count', $data);
        $this->assertArrayHasKey('business_clients_count', $data);
        $this->assertSame(0, $data['order_anywhere_count']);
    }

    public function test_urban_goodz_dashboard_data_is_populated_for_a_role_with_the_permission(): void
    {
        $this->bootSqliteAdminSchema();
        AdminRole::create(['name' => 'placeholder-role-1', 'modules' => json_encode([]), 'status' => true]);
        $role = AdminRole::create([
            'name' => 'Urban Goodz Operator',
            'modules' => json_encode(['urban_goodz_view']),
            'status' => true,
        ]);
        $admin = $this->createAdmin([
            'email' => 'ug-operator-dashboard@urban-goodz.test',
            'role_id' => $role->id,
        ]);
        $this->actingAs($admin, 'admin');

        $data = DashboardController::urban_goodz_dashboard_data();

        $this->assertNotSame([], $data);
        $this->assertCount(25, $data);
    }

    public function test_dashboard_controller_gates_urban_goodz_data_behind_module_permission_check(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/DashboardController.php'));

        $this->assertStringContainsString(
            "!auth('admin')->check() || !Helpers::module_permission_check('urban_goodz_view')",
            $controller
        );
        $this->assertStringContainsString('self::urban_goodz_dashboard_data()', $controller);
    }

    public function test_settings_dashboard_is_restricted_to_the_primary_admin_role(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/DashboardController.php'));

        $this->assertStringContainsString(
            "auth('admin')->check() && auth('admin')->user()->role_id == 1",
            $controller
        );
        $this->assertStringContainsString(
            "redirect()->route('admin.business-settings.business-setup')",
            $controller
        );
    }

    public function test_valid_admin_credentials_with_invalid_custom_captcha_are_rejected_cleanly(): void
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.url', null);
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        DB::connection('sqlite')->setReadPdo(DB::connection('sqlite')->getPdo());
        Schema::connection('sqlite')->create('admins', function (Blueprint $table): void {
            $table->id();
            $table->string('f_name')->nullable();
            $table->string('l_name')->nullable();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('password')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('image')->nullable();
            $table->boolean('is_logged_in')->default(false);
            $table->rememberToken();
            $table->timestamps();
        });
        $this->withoutMiddleware(ActivationCheckMiddleware::class);
        config()->set('recaptcha_conf', [
            'value' => json_encode(['status' => 0], JSON_THROW_ON_ERROR),
        ]);

        $admin = Admin::create([
            'f_name' => 'Captcha',
            'l_name' => 'Regression',
            'email' => 'captcha-regression@urban-goodz.test',
            'phone' => '15555550101',
            'password' => bcrypt('valid-password'),
            'role_id' => 1,
            'is_logged_in' => 0,
        ]);

        $response = $this
            ->from('/login/admin')
            ->withSession([
                '_token' => 'captcha-regression-csrf-token',
                'six_captcha' => 'CORRECT',
            ])
            ->post('/login_submit', [
                '_token' => 'captcha-regression-csrf-token',
                'email' => $admin->email,
                'password' => 'valid-password',
                'role' => 'admin',
                'custome_recaptcha' => 'WRONG',
            ]);

        $response->assertRedirect('/login/admin');
        $response->assertSessionHasErrors();
        $this->assertGuest('admin');
        $this->assertSame($admin->email, session()->getOldInput('email'));
        $this->assertNull(session()->getOldInput('password'));
    }

    public function test_admin_hostname_root_redirects_to_the_registered_admin_login_route(): void
    {
        $this->assertTrue(Route::has('login'));
        $this->assertFalse(Route::has('admin.auth.login'));

        $this->get('https://admin.urbangoodzdelivery.com/')
            ->assertRedirect('https://admin.urbangoodzdelivery.com/login/admin');
    }

    public function test_dashboard_links_use_registered_route_names(): void
    {
        $dashboard = file_get_contents(resource_path('views/admin-views/dashboard.blade.php'));

        $this->assertTrue(Route::has('admin.users.delivery-man.list'));
        $this->assertFalse(Route::has('admin.delivery-man.list'));
        $this->assertSame(2, substr_count($dashboard, "route('admin.users.delivery-man.list')"));
        $this->assertStringNotContainsString("route('admin.delivery-man.list')", $dashboard);
        $this->assertTrue(Route::has('admin.transactions.report.item-wise-report'));
        $this->assertFalse(Route::has('admin.report.item-wise-report'));
        $this->assertSame(2, substr_count($dashboard, "route('admin.transactions.report.item-wise-report')"));
        $this->assertStringNotContainsString("route('admin.report.item-wise-report')", $dashboard);
    }

    public function test_admin_login_uses_the_approved_command_center_assets(): void
    {
        $login = file_get_contents(resource_path('views/auth/login.blade.php'));

        $this->assertStringContainsString('public/assets/admin/img/admin-command-center-reference.png', $login);
        $this->assertStringContainsString('public/assets/admin/svg/logos/urban-goodz.svg', $login);
        $this->assertStringContainsString('Admin Login', $login);
        $this->assertStringContainsString('Security Verification', $login);
        $this->assertStringNotContainsString('public/assets/admin/img/favicon.png', $login);
        $this->assertStringNotContainsString('6amMart', $login);
        $this->assertFileExists(public_path('assets/admin/img/admin-command-center-reference.png'));
        $this->assertFileExists(public_path('assets/admin/svg/logos/urban-goodz.svg'));
    }

    public function test_business_login_uses_the_approved_operations_hub_assets(): void
    {
        $login = file_get_contents(resource_path('views/business/auth/login.blade.php'));

        $this->assertStringContainsString('public/assets/admin/img/business-operations-hub-reference.png', $login);
        $this->assertStringContainsString('Business Portal Login', $login);
        $this->assertStringContainsString("route('business.login.submit')", $login);
        $this->assertStringContainsString('@csrf', $login);
        $this->assertFileExists(public_path('assets/admin/img/business-operations-hub-reference.png'));
    }
}
