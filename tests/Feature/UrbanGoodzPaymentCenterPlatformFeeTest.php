<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\UrbanGoodzPaymentSetting;
use App\Models\UrbanGoodzPaymentSettingAudit;
use App\Services\UrbanGoodzPaymentSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class UrbanGoodzPaymentCenterPlatformFeeTest extends TestCase
{
    use DatabaseTransactions;

    private Admin $owner;
    private Admin $restrictedAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('urban_goodz_payments.mode', 'sandbox');
        Config::set('urban_goodz_payments.default_platform_fee_percent', null);
        Config::set('urban_goodz_payments.safe_non_live_platform_fee_percent', 10);

        $this->owner = Admin::firstOrCreate(
            ['email' => 'payment-center-owner@urbangoodz.test'],
            [
                'f_name' => 'Payment',
                'l_name' => 'Owner',
                'phone' => '5559871001',
                'password' => bcrypt('password'),
                'role_id' => 1,
                'is_logged_in' => 1,
            ]
        );
        $this->owner->forceFill(['is_logged_in' => 1])->save();

        $this->restrictedAdmin = Admin::firstOrCreate(
            ['email' => 'payment-center-restricted@urbangoodz.test'],
            [
                'f_name' => 'Restricted',
                'l_name' => 'Admin',
                'phone' => '5559871002',
                'password' => bcrypt('password'),
                'role_id' => 2,
                'is_logged_in' => 1,
            ]
        );
        $this->restrictedAdmin->forceFill(['is_logged_in' => 1])->save();
    }

    public function test_safe_fallback_is_explicit_and_non_live_only(): void
    {
        UrbanGoodzPaymentSetting::where('setting_key', 'platform_fee_percent')->delete();

        $effective = app(UrbanGoodzPaymentSettings::class)->platformFee();

        $this->assertSame(10.0, $effective['effective_percent']);
        $this->assertSame('safe_non_live_fallback', $effective['source']);
        $this->assertFalse($effective['configured']);
        $this->assertFalse($effective['owner_configured']);

        Config::set('urban_goodz_payments.mode', 'live_controlled');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('owner-configured platform fee');
        app(UrbanGoodzPaymentSettings::class)->platformFee();
    }

    public function test_owner_can_store_platform_fee_with_audit_identity(): void
    {
        $response = $this->actingAs($this->owner, 'admin')
            ->withSession(['login_remember_token' => $this->owner->login_remember_token])
            ->patch(route('admin.urban-goodz.payments.platform-fee.update'), [
                'platform_fee_percent' => 12.5,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $setting = UrbanGoodzPaymentSetting::where('setting_key', 'platform_fee_percent')->firstOrFail();
        $this->assertSame('12.5', $setting->value);
        $this->assertSame('owner_payment_center', $setting->source);
        $this->assertSame($this->owner->id, $setting->last_changed_by_admin_id);

        $audit = UrbanGoodzPaymentSettingAudit::where('setting_key', 'platform_fee_percent')
            ->latest('id')
            ->firstOrFail();
        $this->assertSame('12.5', $audit->new_value);
        $this->assertSame($this->owner->id, $audit->admin_id);
        $this->assertSame('payment_center', $audit->metadata['control']);
    }

    public function test_restricted_admin_cannot_change_platform_fee(): void
    {
        $this->actingAs($this->restrictedAdmin, 'admin')
            ->withSession(['login_remember_token' => $this->restrictedAdmin->login_remember_token])
            ->patch(route('admin.urban-goodz.payments.platform-fee.update'), [
                'platform_fee_percent' => 15,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('urban_goodz_payment_settings', [
            'setting_key' => 'platform_fee_percent',
            'value' => '15',
        ]);
    }

    public function test_payment_center_displays_effective_percentage_and_database_source(): void
    {
        UrbanGoodzPaymentSetting::setPlatformFeePercent(12.5, $this->owner->id);

        $response = $this->actingAs($this->owner, 'admin')
            ->withSession(['login_remember_token' => $this->owner->login_remember_token])
            ->get(route('admin.urban-goodz.payments.index'));

        $response->assertOk();
        $response->assertSee('12.5%');
        $response->assertSee('Owner-configured database setting');
        $response->assertSee('Save platform fee');
    }

    public function test_platform_fee_update_has_no_get_route(): void
    {
        $route = Route::getRoutes()->getByName('admin.urban-goodz.payments.platform-fee.update');

        $this->assertNotNull($route);
        $this->assertSame(['PATCH'], $route->methods());
    }
}
