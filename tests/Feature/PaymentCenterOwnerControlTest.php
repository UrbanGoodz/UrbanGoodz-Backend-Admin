<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\UrbanGoodzPaymentSetting;
use App\Models\UrbanGoodzPaymentSettingAudit;
use App\Models\UrbanGoodzPaymentTransaction;
use App\Services\Payments\PaymentSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PaymentCenterOwnerControlTest extends TestCase
{
    use DatabaseTransactions;

    private Admin $owner;
    private Admin $restricted;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('urban_goodz_payments.mode', 'sandbox');
        Config::set('urban_goodz_payments.provider', 'staged_test');

        $this->owner = Admin::firstOrCreate(
            ['email' => 'owner-pc-test@urbangoodz.com'],
            [
                'f_name' => 'Owner',
                'l_name' => 'Test',
                'phone' => '5550000001',
                'password' => bcrypt('password'),
                'role_id' => 1,
            ]
        );
        $this->owner->forceFill([
            'is_logged_in' => 1,
            'login_remember_token' => 'payment-center-owner-session',
        ])->save();

        $this->restricted = Admin::firstOrCreate(
            ['email' => 'restricted-pc-test@urbangoodz.com'],
            [
                'f_name' => 'Restricted',
                'l_name' => 'Test',
                'phone' => '5550000002',
                'password' => bcrypt('password'),
                'role_id' => 2,
            ]
        );
        $this->restricted->forceFill([
            'is_logged_in' => 1,
            'login_remember_token' => 'payment-center-restricted-session',
        ])->save();
    }

    private function asAdmin(Admin $admin): static
    {
        return $this->actingAs($admin, 'admin')
            ->withSession(['login_remember_token' => $admin->login_remember_token]);
    }

    public function test_owner_can_open_payment_center(): void
    {
        $response = $this->asAdmin($this->owner)
            ->get(route('admin.urban-goodz.payment-center.index'));

        $response->assertStatus(200);
        $response->assertSee('Payment Center');
        $response->assertSee('Emergency Disable');
        $response->assertSee('Switch to Sandbox');
        $response->assertSee('Test Webhook');
        $response->assertSee('Run Reconciliation');
        $response->assertSee(route('admin.urban-goodz.payment-center.index'), false);
    }

    public function test_restricted_admin_receives_403(): void
    {
        $response = $this->asAdmin($this->restricted)
            ->get(route('admin.urban-goodz.payment-center.index'));

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_redirects(): void
    {
        $response = $this->get(route('admin.urban-goodz.payment-center.index'));

        $response->assertStatus(302);
        $response->assertRedirectContains('login');
    }

    public function test_disabled_mode_persists(): void
    {
        $response = $this->asAdmin($this->owner)
            ->patch(route('admin.urban-goodz.payment-center.settings.update'), [
                'payment_mode' => 'disabled',
            ]);

        $response->assertStatus(302);
        $response->assertSessionHas('success');

        $setting = UrbanGoodzPaymentSetting::where('setting_key', 'payment_mode')->first();
        $this->assertNotNull($setting);
        $this->assertEquals('disabled', $setting->value);
        $this->assertEquals('owner', $setting->source);
        $this->assertEquals($this->owner->id, $setting->last_changed_by_admin_id);
    }

    public function test_sandbox_mode_persists(): void
    {
        $response = $this->asAdmin($this->owner)
            ->patch(route('admin.urban-goodz.payment-center.settings.update'), [
                'payment_mode' => 'sandbox',
            ]);

        $response->assertStatus(302);

        $setting = UrbanGoodzPaymentSetting::where('setting_key', 'payment_mode')->first();
        $this->assertNotNull($setting);
        $this->assertEquals('sandbox', $setting->value);
    }

    public function test_emergency_disable_and_sandbox_controls_are_audited_posts(): void
    {
        $this->asAdmin($this->owner)
            ->post(route('admin.urban-goodz.payment-center.emergency-disable'))
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertSame('disabled', app(PaymentSettings::class)->mode());

        $this->asAdmin($this->owner)
            ->post(route('admin.urban-goodz.payment-center.switch-sandbox'))
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertSame('sandbox', app(PaymentSettings::class)->mode());

        $this->assertSame(2, UrbanGoodzPaymentSettingAudit::where('setting_key', 'payment_mode')->count());
    }

    public function test_live_controlled_remains_locked(): void
    {
        $response = $this->asAdmin($this->owner)
            ->patch(route('admin.urban-goodz.payment-center.settings.update'), [
                'payment_mode' => 'live_controlled',
            ]);

        // Validation rejects live_controlled
        $response->assertStatus(302);
        $response->assertSessionHasErrors('payment_mode');
    }

    public function test_platform_fee_persists(): void
    {
        $response = $this->asAdmin($this->owner)
            ->patch(route('admin.urban-goodz.payment-center.settings.update'), [
                'platform_fee_percent' => 12.5,
            ]);

        $response->assertStatus(302);

        $setting = UrbanGoodzPaymentSetting::where('setting_key', 'platform_fee_percent')->first();
        $this->assertNotNull($setting);
        $this->assertEquals('12.5', $setting->value);
        $this->assertSame(12.5, app(PaymentSettings::class)->platformFeePercent());
    }

    public function test_audit_row_is_created(): void
    {
        $this->asAdmin($this->owner)
            ->patch(route('admin.urban-goodz.payment-center.settings.update'), [
                'platform_fee_percent' => 15.0,
            ]);

        $audit = UrbanGoodzPaymentSettingAudit::latest()->first();
        $this->assertNotNull($audit);
        $this->assertEquals('platform_fee_percent', $audit->setting_key);
        $this->assertEquals($this->owner->id, $audit->admin_id);
        $this->assertNotNull($audit->new_value);
    }

    public function test_setting_source_is_displayed(): void
    {
        UrbanGoodzPaymentSetting::setValue('platform_fee_percent', 10.0, 'owner', $this->owner->id);

        $response = $this->asAdmin($this->owner)
            ->get(route('admin.urban-goodz.payment-center.index'));

        $response->assertStatus(200);
        $response->assertSee('Effective Values Reference');
        $response->assertSee('owner');
    }

    public function test_secrets_are_never_rendered(): void
    {
        Config::set('urban_goodz_payments.stripe.publishable_key', 'pk_test_payment_center_secret');
        Config::set('urban_goodz_payments.stripe.secret_key', 'sk_test_payment_center_secret');
        Config::set('urban_goodz_payments.stripe.webhook_secret', 'whsec_payment_center_secret');

        $response = $this->asAdmin($this->owner)
            ->get(route('admin.urban-goodz.payment-center.index'));

        $response->assertStatus(200);
        // Should never contain actual secret key values
        $response->assertDontSee('sk_test_');
        $response->assertDontSee('sk_live_');
        $response->assertDontSee('pk_test_');
        $response->assertDontSee('pk_live_');
        $response->assertDontSee('whsec_');
        // Should only show YES/NO
        $response->assertSee('YES');
        $response->assertSee('NO');
    }

    public function test_webhook_diagnostic_is_real_audited_and_secret_safe(): void
    {
        Config::set('urban_goodz_payments.stripe.webhook_secret', 'whsec_payment_center_diagnostic');

        $this->asAdmin($this->owner)
            ->post(route('admin.urban-goodz.payment-center.test-webhook'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $audit = UrbanGoodzPaymentSettingAudit::where('action', 'webhook_diagnostic')->latest()->firstOrFail();
        $this->assertSame('passed', $audit->new_value);
        $this->assertTrue($audit->metadata['route_registered']);
        $this->assertTrue($audit->metadata['receipt_storage_ready']);
        $this->assertTrue($audit->metadata['stripe_webhook_secret_configured']);
        $this->assertStringNotContainsString('whsec_', json_encode($audit->metadata));
    }

    public function test_webhook_health_renders(): void
    {
        $response = $this->asAdmin($this->owner)
            ->get(route('admin.urban-goodz.payment-center.index'));

        $response->assertStatus(200);
        $response->assertSee('Webhook Health');
        $response->assertSee('Endpoint URL');
        $response->assertSee('Duplicate/Replay Count');
        $response->assertSee('Signature Status');
    }

    public function test_transaction_detail_renders(): void
    {
        $response = $this->asAdmin($this->owner)
            ->get(route('admin.urban-goodz.payment-center.index'));

        $response->assertStatus(200);
        $response->assertSee('Ledger & Reconciliation');
        $response->assertSee('Captured');
        $response->assertSee('Pending');
        $response->assertSee('Failed');
        $response->assertSee('Refunded');
        $response->assertSee('Disputed');
    }

    public function test_transaction_metrics_query_the_physical_schema_without_a_deleted_at_column(): void
    {
        UrbanGoodzPaymentTransaction::create([
            'payable_type' => 'payment-center-test',
            'payable_id' => 1,
            'provider' => 'stripe',
            'environment' => 'sandbox',
            'transaction_type' => 'capture',
            'internal_status' => 'pending',
            'amount_minor' => 500,
            'currency' => 'USD',
            'idempotency_key' => 'payment-center-schema-' . uniqid(),
        ]);

        $this->asAdmin($this->owner)
            ->get(route('admin.urban-goodz.payment-center.index'))
            ->assertOk()
            ->assertSee('$5.00');
    }

    public function test_no_state_changing_get(): void
    {
        // GET routes should only read, never write
        $getRoutes = [
            route('admin.urban-goodz.payment-center.index'),
            route('admin.urban-goodz.payment-center.audit-history'),
            route('admin.urban-goodz.payment-center.reconciliation'),
        ];

        foreach ($getRoutes as $url) {
            $settingsCountBefore = UrbanGoodzPaymentSetting::count();
            $auditsCountBefore = UrbanGoodzPaymentSettingAudit::count();

            $this->asAdmin($this->owner)->get($url);

            $this->assertEquals($settingsCountBefore, UrbanGoodzPaymentSetting::count(), "GET to {$url} should not create settings");
            $this->assertEquals($auditsCountBefore, UrbanGoodzPaymentSettingAudit::count(), "GET to {$url} should not create audit records");
        }
    }

    public function test_state_changing_controls_reject_get_and_do_not_mutate(): void
    {
        $postOnlyRoutes = [
            route('admin.urban-goodz.payment-center.emergency-disable'),
            route('admin.urban-goodz.payment-center.switch-sandbox'),
            route('admin.urban-goodz.payment-center.test-webhook'),
            route('admin.urban-goodz.payment-center.reconciliation.run'),
        ];

        foreach ($postOnlyRoutes as $url) {
            $settingsBefore = UrbanGoodzPaymentSetting::count();
            $auditsBefore = UrbanGoodzPaymentSettingAudit::count();

            $response = $this->asAdmin($this->owner)->get($url);
            $this->assertContains($response->getStatusCode(), [302, 404, 405]);

            $this->assertSame($settingsBefore, UrbanGoodzPaymentSetting::count());
            $this->assertSame($auditsBefore, UrbanGoodzPaymentSettingAudit::count());
        }
    }

    public function test_restricted_admin_cannot_mutate_owner_settings(): void
    {
        $before = UrbanGoodzPaymentSettingAudit::count();

        $this->asAdmin($this->restricted)
            ->patch(route('admin.urban-goodz.payment-center.settings.update'), [
                'platform_fee_percent' => 30,
            ])
            ->assertForbidden();

        $this->assertNull(UrbanGoodzPaymentSetting::where('setting_key', 'platform_fee_percent')->first());
        $this->assertSame($before, UrbanGoodzPaymentSettingAudit::count());
    }

    public function test_payment_center_route_methods_are_explicit(): void
    {
        $methods = collect(Route::getRoutes())
            ->filter(fn ($route) => str_starts_with((string) $route->getName(), 'admin.urban-goodz.payment-center.'))
            ->mapWithKeys(fn ($route) => [$route->getName() => $route->methods()]);

        $this->assertSame(['PATCH'], $methods['admin.urban-goodz.payment-center.settings.update']);
        $this->assertSame(['POST'], $methods['admin.urban-goodz.payment-center.emergency-disable']);
        $this->assertSame(['POST'], $methods['admin.urban-goodz.payment-center.reconciliation.run']);
        $this->assertSame(['POST'], $methods['admin.urban-goodz.payment-center.webhook.retry']);
    }

    public function test_no_6ammart_in_owner_pages(): void
    {
        $response = $this->asAdmin($this->owner)
            ->get(route('admin.urban-goodz.payment-center.index'));

        $response->assertStatus(200);
        $response->assertDontSee('6amMart');
        $response->assertDontSee('6am Tech');
        $response->assertDontSee('sixam');
    }

    public function test_urban_goodz_title_appears(): void
    {
        $response = $this->asAdmin($this->owner)
            ->get(route('admin.urban-goodz.payment-center.index'));

        $response->assertStatus(200);
        $response->assertSee('Urban Goodz');
        $response->assertSee('Payment Center');
    }

    public function test_no_tracked_language_php_file_writes(): void
    {
        $langDir = resource_path('lang/en');
        $filesBefore = glob($langDir . '/*.php');

        $this->asAdmin($this->owner)
            ->get(route('admin.urban-goodz.payment-center.index'));

        $filesAfter = glob($langDir . '/*.php');
        $this->assertEquals(count($filesBefore), count($filesAfter), 'No language PHP files should be written during page render');
    }
}
