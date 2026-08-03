<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\UrbanGoodzPaymentLedger;
use App\Models\UrbanGoodzPaymentSetting;
use App\Services\UrbanGoodzPaymentSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Regression coverage for the Admin Payments dashboard.
 *
 * Production returned HTTP 500 on /admin/urban-goodz/payments with
 * `Route [admin.urban-goodz.payments.platform-fee.update] not defined`. The
 * Payments view renders a Platform Economics card whose owner-only form posts
 * to that route, and the route was never registered — so a single unresolvable
 * route() call took down the entire page.
 *
 * These tests lock the route contract, the page's tolerance of null/legacy
 * payment data, and the owner-only authorization on the fee mutation.
 */
class AdminPaymentsPageTest extends TestCase
{
    use DatabaseTransactions;

    private const PAGE = 'admin.urban-goodz.payments.index';
    private const FEE_UPDATE = 'admin.urban-goodz.payments.platform-fee.update';

    private const FORBIDDEN = [
        'SQLSTATE', 'ErrorException', 'Stack trace', 'Whoops',
        'Trying to access', 'Attempt to read property', '<?php', '@endif', '{{',
    ];

    private Admin $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = Admin::firstOrCreate(
            ['email' => 'payments-test-owner@urbangoodz.com'],
            [
                'f_name' => 'Payments',
                'l_name' => 'Test Owner',
                'phone' => '1230000096',
                'password' => bcrypt('password'),
                'role_id' => 1,
                'is_logged_in' => 1,
            ]
        );
        $this->owner->forceFill(['role_id' => 1, 'is_logged_in' => 1])->save();
    }

    private function restrictedAdmin(): Admin
    {
        $admin = Admin::firstOrCreate(
            ['email' => 'payments-test-restricted@urbangoodz.com'],
            [
                'f_name' => 'Payments',
                'l_name' => 'Restricted',
                'phone' => '1230000095',
                'password' => bcrypt('password'),
                'role_id' => 2,
                'is_logged_in' => 1,
            ]
        );
        $admin->forceFill(['role_id' => 2, 'is_logged_in' => 1])->save();

        return $admin;
    }

    private function ledger(array $overrides = []): UrbanGoodzPaymentLedger
    {
        return UrbanGoodzPaymentLedger::create(array_merge([
            'ledger_number' => 'UGL-TEST-'.uniqid(),
            'feature' => 'order_anywhere',
            'payable_type' => 'App\Models\OrderAnywhereRequest',
            'payable_id' => 1,
            'event_type' => 'captured',
            'direction' => 'in',
            'amount' => 125.00,
            'currency' => 'USD',
            'payment_status' => 'captured',
            'idempotency_key' => 'test:'.uniqid(),
            'metadata' => [],
        ], $overrides));
    }

    private function assertCleanHtml(string $content): void
    {
        foreach (self::FORBIDDEN as $marker) {
            $this->assertStringNotContainsString($marker, $content, "Payments page leaked '{$marker}'.");
        }
    }

    // ── The exact production defect ────────────────────────────────────

    public function test_platform_fee_update_route_is_registered(): void
    {
        $route = Route::getRoutes()->getByName(self::FEE_UPDATE);

        $this->assertNotNull($route, 'The platform-fee update route is not registered; the Payments page will 500.');
        $this->assertSame('admin/urban-goodz/payments/platform-fee', $route->uri());
        $this->assertContains('PATCH', $route->methods());
    }

    /**
     * Structural guard: every named route the Payments view references must
     * resolve. An unresolvable route() call is what produced the live 500.
     */
    public function test_every_route_referenced_by_the_payments_view_resolves(): void
    {
        $view = dirname(__DIR__, 2).'/resources/views/admin-views/urban-goodz/payments/index.blade.php';
        preg_match_all("/route\('([^']+)'/", file_get_contents($view), $m);

        $missing = [];
        foreach (array_unique($m[1]) as $name) {
            if (! Route::getRoutes()->getByName($name)) {
                $missing[] = $name;
            }
        }

        $this->assertSame([], $missing, 'Payments view references unregistered routes: '.implode(', ', $missing));
    }

    // ── Page rendering ─────────────────────────────────────────────────

    public function test_authorized_admin_opens_payments_page(): void
    {
        $response = $this->actingAs($this->owner, 'admin')->get(route(self::PAGE));

        $response->assertOk();
        $this->assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));
        $this->assertCleanHtml($response->getContent());
    }

    public function test_empty_ledger_dataset_renders_safely(): void
    {
        UrbanGoodzPaymentLedger::query()->delete();

        $response = $this->actingAs($this->owner, 'admin')->get(route(self::PAGE));

        $response->assertOk();
        $this->assertCleanHtml($response->getContent());
    }

    /** @dataProvider paymentStates */
    public function test_payment_state_renders(string $status, string $event): void
    {
        $this->ledger(['payment_status' => $status, 'event_type' => $event]);

        $response = $this->actingAs($this->owner, 'admin')->get(route(self::PAGE));

        $response->assertOk();
        $this->assertCleanHtml($response->getContent());
    }

    public static function paymentStates(): array
    {
        return [
            'captured' => ['captured', 'captured'],
            'pending' => ['pending', 'authorized'],
            'failed' => ['failed', 'failed'],
            'refunded' => ['refunded', 'refunded'],
        ];
    }

    public function test_null_optional_relationships_render_safely(): void
    {
        // No vendor, no driver, no customer, no admin, no reference/provider id.
        $this->ledger([
            'vendor_id' => null,
            'delivery_man_id' => null,
            'customer_id' => null,
            'created_by_admin_id' => null,
            'reference' => null,
            'payment_method' => null,
        ]);

        $response = $this->actingAs($this->owner, 'admin')->get(route(self::PAGE));

        $response->assertOk();
        $this->assertCleanHtml($response->getContent());
    }

    public function test_legacy_record_with_null_metadata_renders_safely(): void
    {
        $legacy = $this->ledger();
        // Legacy rows predate structured metadata.
        UrbanGoodzPaymentLedger::where('id', $legacy->id)->update([
            'metadata' => null,
            'currency' => null,
        ]);

        $response = $this->actingAs($this->owner, 'admin')->get(route(self::PAGE));

        $response->assertOk();
        $this->assertCleanHtml($response->getContent());
    }

    public function test_page_renders_when_no_owner_configured_platform_fee_exists(): void
    {
        UrbanGoodzPaymentSetting::where('setting_key', 'platform_fee_percent')->delete();
        config(['urban_goodz_payments.default_platform_fee_percent' => null]);
        config(['urban_goodz_payments.mode' => 'sandbox']);

        $response = $this->actingAs($this->owner, 'admin')->get(route(self::PAGE));

        $response->assertOk();
        // Must be truthful that the value is not owner-configured, not fabricate one.
        $response->assertSee('NOT OWNER-CONFIGURED', false);
        $this->assertCleanHtml($response->getContent());
    }

    // ── Platform fee integrity ─────────────────────────────────────────

    public function test_owner_configured_fee_takes_precedence_over_environment(): void
    {
        config(['urban_goodz_payments.default_platform_fee_percent' => 42.0]);
        app(UrbanGoodzPaymentSettings::class)->savePlatformFee(7.5, $this->owner->id);

        $fee = app(UrbanGoodzPaymentSettings::class)->platformFee();

        $this->assertSame(7.5, $fee['effective_percent']);
        $this->assertSame('owner_database', $fee['source']);
        $this->assertTrue($fee['owner_configured']);
    }

    public function test_saving_the_fee_writes_an_audit_trail(): void
    {
        app(UrbanGoodzPaymentSettings::class)->savePlatformFee(9.25, $this->owner->id);

        $this->assertDatabaseHas('urban_goodz_payment_setting_audits', [
            'setting_key' => 'platform_fee_percent',
            'new_value' => '9.25',
            'admin_id' => $this->owner->id,
        ]);
    }

    public function test_fee_outside_zero_to_one_hundred_is_rejected(): void
    {
        $this->expectException(\LogicException::class);
        app(UrbanGoodzPaymentSettings::class)->savePlatformFee(150.0, $this->owner->id);
    }

    // ── Authorization ──────────────────────────────────────────────────

    public function test_owner_can_update_the_platform_fee(): void
    {
        $this->actingAs($this->owner, 'admin')
            ->patch(route(self::FEE_UPDATE), ['platform_fee_percent' => 12.5])
            ->assertRedirect();

        $this->assertSame(12.5, app(UrbanGoodzPaymentSettings::class)->platformFeePercent());
    }

    public function test_restricted_admin_cannot_update_the_platform_fee(): void
    {
        app(UrbanGoodzPaymentSettings::class)->savePlatformFee(10.0, $this->owner->id);

        $response = $this->actingAs($this->restrictedAdmin(), 'admin')
            ->patch(route(self::FEE_UPDATE), ['platform_fee_percent' => 99]);

        // Denial may be a 403 from the controller's owner check or an approved
        // access-denied redirect from the admin module middleware, whichever
        // fires first. It must never be a 500 and never a success.
        $this->assertContains(
            $response->getStatusCode(),
            [403, 302],
            'Restricted admin denial returned an unexpected status.'
        );
        if ($response->getStatusCode() === 302) {
            $this->assertStringNotContainsString(
                'login',
                (string) $response->headers->get('Location'),
                'An authenticated restricted admin must not be bounced to login.'
            );
        }

        // The security property that actually matters: the fee is unchanged.
        $this->assertSame(10.0, app(UrbanGoodzPaymentSettings::class)->platformFeePercent());
    }

    public function test_unauthenticated_actor_is_denied_page_and_mutation(): void
    {
        $this->get(route(self::PAGE))->assertRedirect();
        $this->patch(route(self::FEE_UPDATE), ['platform_fee_percent' => 50])->assertRedirect();
    }

    public function test_invalid_fee_payload_is_rejected_without_changing_the_value(): void
    {
        app(UrbanGoodzPaymentSettings::class)->savePlatformFee(10.0, $this->owner->id);

        $this->actingAs($this->owner, 'admin')
            ->patch(route(self::FEE_UPDATE), ['platform_fee_percent' => 'abc'])
            ->assertSessionHasErrors('platform_fee_percent');

        $this->assertSame(10.0, app(UrbanGoodzPaymentSettings::class)->platformFeePercent());
    }
}
