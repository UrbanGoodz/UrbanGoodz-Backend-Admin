<?php

namespace Tests\Feature;

use App\Http\Middleware\ActivationCheckMiddleware;
use App\Models\Admin;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminLoginRecoveryRegressionTest extends TestCase
{
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

    public function test_dashboard_driver_links_use_the_registered_delivery_man_route(): void
    {
        $dashboard = file_get_contents(resource_path('views/admin-views/dashboard.blade.php'));

        $this->assertTrue(Route::has('admin.users.delivery-man.list'));
        $this->assertFalse(Route::has('admin.delivery-man.list'));
        $this->assertSame(2, substr_count($dashboard, "route('admin.users.delivery-man.list')"));
        $this->assertStringNotContainsString("route('admin.delivery-man.list')", $dashboard);
    }

    public function test_admin_login_uses_only_the_urban_goodz_logo_asset(): void
    {
        $login = file_get_contents(resource_path('views/auth/login.blade.php'));

        $this->assertSame(2, substr_count($login, 'public/assets/admin/svg/logos/urban-goodz.svg'));
        $this->assertStringNotContainsString('public/assets/admin/img/favicon.png', $login);
        $this->assertStringNotContainsString('6amMart', $login);
        $this->assertFileExists(public_path('assets/admin/svg/logos/urban-goodz.svg'));
    }
}
