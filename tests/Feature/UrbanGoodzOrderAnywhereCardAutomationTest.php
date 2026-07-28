<?php

namespace Tests\Feature;

use App\Console\Commands\RecoverOrderAnywhereCardIssuance;
use App\Jobs\IssueOrderAnywherePurchaseCard;
use App\Models\Admin;
use App\Models\BusinessSetting;
use App\Models\DeliveryMan;
use App\Models\OrderAnywhereRequest;
use App\Models\UrbanGoodzOrderAnywhereCardRequest;
use App\Models\User;
use App\Models\Zone;
use App\Observers\OrderAnywhereCardSafetyObserver;
use App\Services\OrderAnywhereCardService;
use App\Services\Payments\CardIssuingProviderManager;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Certifies the automatic Order Anywhere purchase-card lifecycle while the issuing
 * provider is deliberately NOT configured.
 */
class UrbanGoodzOrderAnywhereCardAutomationTest extends TestCase
{
    use DatabaseTransactions;

    private Admin $owner;
    private User $customer;
    private DeliveryMan $driver;
    private DeliveryMan $otherDriver;
    private Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();

        // Owner decision: the real issuing provider is configured later.
        Config::set('urban_goodz_payments.issuing.provider', 'disabled');
        Config::set('urban_goodz_payments.issuing.mode', 'sandbox');
        Config::set('urban_goodz_payments.issuing.max_driver_card_amount', 500.00);

        BusinessSetting::withoutGlobalScopes()
            ->where('key', 'order_anywhere_card_emergency_disabled')
            ->delete();

        $this->zone = Zone::firstOrCreate(
            ['name' => 'Card Automation Zone'],
            [
                'coordinates' => new \Illuminate\Database\Query\Expression(
                    "ST_GeomFromText('POLYGON((0 0, 0 100, 100 100, 100 0, 0 0))')"
                ),
                'status' => 1,
            ]
        );

        $this->owner = Admin::firstOrCreate(
            ['email' => 'card-owner@urbangoodz.com'],
            [
                'f_name' => 'Card',
                'l_name' => 'Owner',
                'phone' => '1230000001',
                'password' => bcrypt('password'),
                'role_id' => 1,
                'is_logged_in' => 1,
            ]
        );
        $this->owner->forceFill(['role_id' => 1, 'is_logged_in' => 1])->save();

        $this->customer = User::firstOrCreate(
            ['email' => 'card-customer@urbangoodz.com'],
            [
                'f_name' => 'Card',
                'l_name' => 'Customer',
                'phone' => '1230000002',
                'password' => bcrypt('password'),
                'is_active' => 1,
                'is_verified' => 1,
            ]
        );

        $this->driver = $this->makeDriver('1230000003', 'card-driver@urbangoodz.com', 'card-driver-token');
        $this->otherDriver = $this->makeDriver('1230000004', 'card-driver2@urbangoodz.com', 'card-driver2-token');
    }

    private function makeDriver(string $phone, string $email, string $token): DeliveryMan
    {
        $driver = DeliveryMan::firstOrCreate(
            ['phone' => $phone],
            [
                'f_name' => 'Card',
                'l_name' => 'Driver',
                'email' => $email,
                'password' => bcrypt('password'),
                'active' => 1,
                'application_status' => 'approved',
                'zone_id' => $this->zone->id,
                'available_for_order_anywhere' => true,
                'auth_token' => $token,
            ]
        );
        $driver->forceFill([
            'auth_token' => $token,
            'available_for_order_anywhere' => true,
            'application_status' => 'approved',
            'active' => 1,
        ])->save();

        return $driver;
    }

    /**
     * A fully eligible external-merchant request: approved, quote accepted, customer
     * payment authorized, driver assigned, merchant budget approved.
     */
    private function eligibleRequest(array $overrides = []): OrderAnywhereRequest
    {
        return OrderAnywhereRequest::create(array_merge([
            'request_number' => 'OA-CARD-' . bin2hex(random_bytes(5)),
            'customer_id' => $this->customer->id,
            'status' => 'approved',
            'payment_status' => 'authorized',
            'fulfillment_type' => OrderAnywhereRequest::FULFILLMENT_EXTERNAL_MERCHANT,
            'assigned_delivery_man_id' => $this->driver->id,
            'merchant_purchase_amount' => 80.00,
            'quote_amount' => 100.00,
            'final_amount' => 100.00,
            'store_vendor_name' => 'Test Merchant',
            'metadata' => ['quote_version' => 'v1'],
        ], $overrides));
    }

    private function cards(): OrderAnywhereCardService
    {
        return app(OrderAnywhereCardService::class);
    }

    // ── 1. Eligibility evaluator ────────────────────────────────────────────

    public function test_eligible_request_produces_exactly_one_card_request(): void
    {
        $request = $this->eligibleRequest();

        $card = $this->cards()->createCardRequest($request);

        $this->assertSame(1, UrbanGoodzOrderAnywhereCardRequest::where(
            'order_anywhere_request_id',
            $request->id
        )->count());
        $this->assertSame('awaiting_provider_configuration', $card->card_status);
        $this->assertNotNull($card->issuance_key);
        $this->assertNotNull($card->eligible_at);
    }

    public function test_evaluator_is_safe_to_invoke_repeatedly(): void
    {
        $request = $this->eligibleRequest();

        $first = $this->cards()->createCardRequest($request);
        $second = $this->cards()->createCardRequest($request->refresh());
        $third = $this->cards()->createCardRequest($request->refresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame($second->id, $third->id);
        $this->assertSame(1, UrbanGoodzOrderAnywhereCardRequest::where(
            'order_anywhere_request_id',
            $request->id
        )->count());
    }

    // ── 2. Automatic trigger ────────────────────────────────────────────────

    public function test_observer_is_registered_on_the_order_anywhere_model(): void
    {
        $observers = OrderAnywhereRequest::getEventDispatcher()
            ->getListeners('eloquent.updated: ' . OrderAnywhereRequest::class);

        $this->assertNotEmpty($observers, 'No updated observer registered for OrderAnywhereRequest.');
    }

    public function test_driver_assignment_change_plans_revocation_and_reevaluation(): void
    {
        $request = $this->eligibleRequest(['assigned_delivery_man_id' => null]);
        $request->update(['assigned_delivery_man_id' => $this->driver->id]);

        $plan = OrderAnywhereCardSafetyObserver::planFor($request);

        $this->assertSame('driver_reassigned', $plan['reason']);
        $this->assertTrue($plan['evaluate']);
    }

    public function test_payment_transition_plans_reevaluation_without_revocation(): void
    {
        $request = $this->eligibleRequest(['payment_status' => 'pending']);
        $request->update(['payment_status' => 'authorized']);

        $plan = OrderAnywhereCardSafetyObserver::planFor($request);

        $this->assertNull($plan['reason']);
        $this->assertTrue($plan['evaluate']);
    }

    public function test_lifecycle_plan_issues_card_without_any_admin_action(): void
    {
        $request = $this->eligibleRequest();
        $this->assertGuest('admin');

        $this->cards()->applyLifecyclePlan($request->id, null, true);

        $cards = UrbanGoodzOrderAnywhereCardRequest::where('order_anywhere_request_id', $request->id)->get();
        $this->assertCount(1, $cards);
        $this->assertSame('awaiting_provider_configuration', $cards->first()->card_status);
        $this->assertNull($cards->first()->created_by, 'Automatic issuance must not be attributed to an admin.');
    }

    public function test_replayed_lifecycle_events_create_no_duplicate(): void
    {
        $request = $this->eligibleRequest();

        $this->cards()->applyLifecyclePlan($request->id, null, true);
        $this->cards()->applyLifecyclePlan($request->id, null, true);
        $this->cards()->applyLifecyclePlan($request->id, null, true);

        $this->assertSame(1, UrbanGoodzOrderAnywhereCardRequest::where(
            'order_anywhere_request_id',
            $request->id
        )->count());
    }

    // ── 3. Provider-unconfigured state ──────────────────────────────────────

    public function test_unconfigured_provider_persists_truthful_pending_state(): void
    {
        $request = $this->eligibleRequest();

        $card = $this->cards()->createCardRequest($request);

        $this->assertSame('awaiting_provider_configuration', $card->card_status);
        $this->assertSame('unconfigured', $card->provider);
        $this->assertSame('not_configured', $card->provider_configuration_status);
        $this->assertSame('provider_not_configured', $card->failure_category);
        $this->assertSame($request->id, $card->order_anywhere_request_id);
        $this->assertSame($this->driver->id, $card->delivery_man_id);
        $this->assertEqualsWithDelta(80.00, (float) $card->approved_purchase_budget, 0.001);
        $this->assertSame('v1', $card->approved_quote_version);
        $this->assertNotNull($card->retry_eligible_at);
    }

    public function test_no_fake_card_credentials_are_generated(): void
    {
        $request = $this->eligibleRequest();

        $card = $this->cards()->createCardRequest($request);

        $this->assertNull($card->provider_card_id);
        $this->assertNull($card->provider_cardholder_id);
        $this->assertNull($card->last4);
        $this->assertNull($card->issued_at);
        $this->assertNull($card->activated_at);
        $this->assertFalse(Schema::hasColumn('urban_goodz_order_anywhere_card_requests', 'pan'));
        $this->assertFalse(Schema::hasColumn('urban_goodz_order_anywhere_card_requests', 'cvc'));
    }

    public function test_approved_budget_excludes_platform_fee_and_driver_compensation(): void
    {
        // Merchant budget 80.00 within a 100.00 customer quote that also carries the
        // 10% platform fee and driver compensation.
        $request = $this->eligibleRequest([
            'merchant_purchase_amount' => 80.00,
            'quote_amount' => 100.00,
            'final_amount' => 100.00,
        ]);

        $card = $this->cards()->createCardRequest($request);

        $this->assertEqualsWithDelta(80.00, (float) $card->approved_purchase_budget, 0.001);
        $this->assertEqualsWithDelta(80.00, (float) $card->spending_limit, 0.001);
        $this->assertNotEquals(100.00, (float) $card->spending_limit);
    }

    public function test_issue_prepared_card_never_invents_credentials_when_unconfigured(): void
    {
        $request = $this->eligibleRequest();
        $card = $this->cards()->createCardRequest($request);

        $result = $this->cards()->issuePreparedCard($card->id);

        $this->assertSame('awaiting_provider_configuration', $result->card_status);
        $this->assertNull($result->provider_card_id);
        $this->assertSame(0, (int) $result->issuance_attempts);
    }

    // ── 4. Canonical issuance identity / concurrency ─────────────────────────

    public function test_issuance_identity_is_deterministic_and_unique_in_the_database(): void
    {
        $request = $this->eligibleRequest();
        $card = $this->cards()->createCardRequest($request);

        $this->assertSame(
            $card->issuance_key,
            $this->cards()->createCardRequest($request->refresh())->issuance_key
        );

        $this->expectException(\Illuminate\Database\QueryException::class);
        UrbanGoodzOrderAnywhereCardRequest::create([
            'issuance_key' => $card->issuance_key,
            'order_anywhere_request_id' => $request->id,
            'delivery_man_id' => $this->driver->id,
            'provider' => 'unconfigured',
            'card_status' => 'awaiting_provider_configuration',
            'spending_limit' => 80.00,
        ]);
    }

    public function test_identity_changes_when_the_assigned_driver_changes(): void
    {
        $request = $this->eligibleRequest();
        $first = $this->cards()->createCardRequest($request);

        $request->forceFill(['assigned_delivery_man_id' => $this->otherDriver->id])->saveQuietly();
        $second = $this->cards()->createCardRequest($request->refresh());

        $this->assertNotSame($first->issuance_key, $second->issuance_key);
    }

    // ── 5. Scheduler recovery ───────────────────────────────────────────────

    public function test_scheduler_finds_pending_request_for_later_recovery(): void
    {
        $request = $this->eligibleRequest();
        $card = $this->cards()->createCardRequest($request);
        $this->assertSame('awaiting_provider_configuration', $card->card_status);

        Artisan::call('order-anywhere:recover-card-issuance');

        $card->refresh();
        $this->assertSame('awaiting_provider_configuration', $card->card_status);
        $this->assertSame(1, UrbanGoodzOrderAnywhereCardRequest::where(
            'order_anywhere_request_id',
            $request->id
        )->count(), 'Recovery must not create a duplicate card request.');
    }

    public function test_recovery_command_is_registered_and_scheduled(): void
    {
        $this->assertArrayHasKey(
            'order-anywhere:recover-card-issuance',
            Artisan::all()
        );

        // Feature tests never boot the console scheduler, so drive the kernel's
        // schedule definition directly against a fresh Schedule instance.
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
        $kernel = app(\App\Console\Kernel::class);
        $method = new \ReflectionMethod($kernel, 'schedule');
        $method->setAccessible(true);
        $method->invoke($kernel, $schedule);

        $events = collect($schedule->events())->filter(
            fn ($event) => str_contains($event->command ?? '', 'order-anywhere:recover-card-issuance')
        );

        $this->assertTrue($events->isNotEmpty(), 'Recovery command is not scheduled.');
    }

    public function test_recovery_resumes_issuance_after_provider_becomes_configured(): void
    {
        $request = $this->eligibleRequest();
        $card = $this->cards()->createCardRequest($request);
        $this->assertSame('awaiting_provider_configuration', $card->card_status);

        // Owner configures a provider later. The staged test gateway stands in for a
        // real provider; it is only ever reachable inside the testing environment.
        Config::set('urban_goodz_payments.issuing.provider', 'staged_test');
        app()->forgetInstance(CardIssuingProviderManager::class);
        app()->forgetInstance(OrderAnywhereCardService::class);

        $resumed = $this->cards()->issuePreparedCard($card->id);

        $this->assertContains($resumed->card_status, ['issued', 'active']);
        $this->assertNotNull($resumed->provider_card_id);
        $this->assertSame($card->id, $resumed->id, 'Recovery must reuse the pending card request.');
        $this->assertSame(1, UrbanGoodzOrderAnywhereCardRequest::where(
            'order_anywhere_request_id',
            $request->id
        )->count());
    }

    // ── 6. Queue retry ──────────────────────────────────────────────────────

    public function test_issuance_job_is_unique_and_bounded(): void
    {
        $job = new IssueOrderAnywherePurchaseCard(4242);

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldBeUnique::class, $job);
        $this->assertSame('order-anywhere-card:4242', $job->uniqueId());
        $this->assertSame(5, $job->tries);
        $this->assertSame([30, 120, 300, 900], $job->backoff());
    }

    public function test_exhausted_retries_mark_final_failure_for_owner_review(): void
    {
        $request = $this->eligibleRequest();
        $card = $this->cards()->createCardRequest($request);
        $card->update(['card_status' => 'issuance_retry_pending']);

        (new IssueOrderAnywherePurchaseCard($card->id))
            ->failed(new \RuntimeException('provider unreachable'));

        $card->refresh();
        $this->assertSame('failed', $card->card_status);
        $this->assertNotNull($card->final_failure_at);
        $this->assertNotNull($card->retry_eligible_at);
    }

    // ── 7. Negative eligibility gates ───────────────────────────────────────

    public function test_missing_payment_blocks_issuance(): void
    {
        $request = $this->eligibleRequest(['payment_status' => 'pending']);

        $this->expectException(HttpException::class);
        $this->cards()->createCardRequest($request);
    }

    public function test_missing_driver_blocks_issuance(): void
    {
        $request = $this->eligibleRequest(['assigned_delivery_man_id' => null]);

        $this->expectException(HttpException::class);
        $this->cards()->createCardRequest($request);
    }

    public function test_zero_merchant_budget_blocks_issuance(): void
    {
        $request = $this->eligibleRequest(['merchant_purchase_amount' => 0, 'item_subtotal' => 0]);

        $this->expectException(HttpException::class);
        $this->cards()->createCardRequest($request);
    }

    public function test_ineligible_driver_blocks_issuance(): void
    {
        $this->driver->forceFill(['available_for_order_anywhere' => false])->save();
        $request = $this->eligibleRequest();

        try {
            $this->expectException(HttpException::class);
            $this->cards()->createCardRequest($request);
        } finally {
            $this->driver->forceFill(['available_for_order_anywhere' => true])->save();
        }
    }

    public function test_cancelled_request_blocks_issuance(): void
    {
        $request = $this->eligibleRequest(['status' => 'cancelled']);

        $this->expectException(HttpException::class);
        $this->cards()->createCardRequest($request);
    }

    public function test_disputed_payment_blocks_issuance(): void
    {
        $request = $this->eligibleRequest(['payment_status' => 'disputed']);

        $this->expectException(HttpException::class);
        $this->cards()->createCardRequest($request);
    }

    public function test_budget_above_configured_cap_blocks_issuance(): void
    {
        Config::set('urban_goodz_payments.issuing.max_driver_card_amount', 50.00);
        app()->forgetInstance(CardIssuingProviderManager::class);
        app()->forgetInstance(OrderAnywhereCardService::class);
        $request = $this->eligibleRequest(['merchant_purchase_amount' => 400.00]);

        $this->expectException(HttpException::class);
        $this->cards()->createCardRequest($request);
    }

    // ── 8. Cancellation and reassignment revocation ─────────────────────────

    public function test_cancellation_closes_the_pending_card_request(): void
    {
        $request = $this->eligibleRequest();
        $card = $this->cards()->createCardRequest($request);

        $this->cards()->applyLifecyclePlan($request->id, 'order_cancelled', false);

        $card->refresh();
        $this->assertSame('cancelled', $card->card_status);
        $this->assertNotNull($card->cancelled_at);
        $this->assertSame('order_cancelled', $card->failure_category);
    }

    public function test_reassignment_revokes_old_driver_and_reevaluates_for_new_driver(): void
    {
        $request = $this->eligibleRequest();
        $original = $this->cards()->createCardRequest($request);
        $this->assertSame($this->driver->id, $original->delivery_man_id);

        $request->forceFill(['assigned_delivery_man_id' => $this->otherDriver->id])->saveQuietly();
        $this->cards()->applyLifecyclePlan($request->id, 'driver_reassigned', true);

        $original->refresh();
        $this->assertSame('cancelled', $original->card_status);
        $this->assertSame('driver_reassigned', $original->failure_category);

        $replacement = UrbanGoodzOrderAnywhereCardRequest::where('order_anywhere_request_id', $request->id)
            ->where('id', '!=', $original->id)
            ->first();
        $this->assertNotNull($replacement, 'A replacement card request must be evaluated for the new driver.');
        $this->assertSame($this->otherDriver->id, $replacement->delivery_man_id);
        $this->assertSame('awaiting_provider_configuration', $replacement->card_status);
    }

    public function test_refunded_payment_revokes_card_access(): void
    {
        $request = $this->eligibleRequest();
        $card = $this->cards()->createCardRequest($request);

        $this->cards()->applyLifecyclePlan($request->id, 'payment_refunded', false);

        $this->assertSame('cancelled', $card->refresh()->card_status);
    }

    public function test_expired_card_is_closed_by_the_recovery_sweep(): void
    {
        $request = $this->eligibleRequest();
        $card = $this->cards()->createCardRequest($request);
        $card->update([
            'card_status' => 'issued',
            'provider' => 'unconfigured',
            'expires_at' => now()->subMinute(),
        ]);

        Artisan::call('order-anywhere:recover-card-issuance');

        $card->refresh();
        $this->assertSame('expired', $card->card_status);
        $this->assertSame('card_window_expired', $card->failure_category);
    }

    // ── 9. Emergency disable ────────────────────────────────────────────────

    public function test_emergency_disable_records_a_truthful_reason(): void
    {
        BusinessSetting::withoutGlobalScopes()->updateOrCreate(
            ['key' => 'order_anywhere_card_emergency_disabled'],
            ['value' => '1']
        );
        app()->forgetInstance(CardIssuingProviderManager::class);
        app()->forgetInstance(OrderAnywhereCardService::class);

        $manager = app(CardIssuingProviderManager::class);
        $this->assertTrue($manager->isEmergencyDisabled());
        $this->assertSame('emergency_disabled', $manager->configurationStatus());
        $this->assertFalse($manager->isAvailable());

        $card = $this->cards()->createCardRequest($this->eligibleRequest());

        $this->assertSame('awaiting_provider_configuration', $card->card_status);
        $this->assertSame('emergency_disabled', $card->failure_category);
        $this->assertStringContainsString('emergency-disabled', $card->failure_reason);
        $this->assertNull($card->provider_card_id);
    }

    // ── 10. Owner manual recovery ───────────────────────────────────────────

    public function test_owner_manual_recovery_is_idempotent_and_creates_no_second_card(): void
    {
        $request = $this->eligibleRequest();
        $first = $this->cards()->createCardRequest($request);

        $response = $this->actingAs($this->owner, 'admin')
            ->withSession(['login_remember_token' => $this->owner->login_remember_token])
            ->post(route('admin.urban-goodz.order-anywhere.request-card', $request->id), []);

        $response->assertRedirect();
        $this->assertSame(1, UrbanGoodzOrderAnywhereCardRequest::where(
            'order_anywhere_request_id',
            $request->id
        )->count());
        $this->assertSame($first->id, UrbanGoodzOrderAnywhereCardRequest::where(
            'order_anywhere_request_id',
            $request->id
        )->first()->id);
    }

    public function test_owner_manual_recovery_cannot_bypass_payment_requirement(): void
    {
        $request = $this->eligibleRequest(['payment_status' => 'pending']);

        $this->actingAs($this->owner, 'admin')
            ->withSession(['login_remember_token' => $this->owner->login_remember_token])
            ->post(route('admin.urban-goodz.order-anywhere.request-card', $request->id), [])
            ->assertStatus(422);

        $this->assertSame(0, UrbanGoodzOrderAnywhereCardRequest::where(
            'order_anywhere_request_id',
            $request->id
        )->count());
    }

    // ── 11. Migration and route registration ────────────────────────────────

    public function test_hardening_migration_created_the_expected_schema(): void
    {
        $this->assertTrue(Schema::hasTable('urban_goodz_issuing_cardholders'));
        $this->assertTrue(Schema::hasTable('urban_goodz_order_anywhere_card_events'));
        $this->assertTrue(Schema::hasTable('urban_goodz_order_anywhere_card_reveal_sessions'));
        $this->assertTrue(Schema::hasTable('urban_goodz_order_anywhere_card_reconciliations'));

        foreach ([
            'issuance_key',
            'customer_payment_intent_id',
            'approved_purchase_budget',
            'approved_quote_version',
            'provider_configuration_status',
            'retry_eligible_at',
            'issuance_attempts',
            'final_failure_at',
            'receipt_path',
            'receipt_total',
            'failure_category',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('urban_goodz_order_anywhere_card_requests', $column),
                "Missing column {$column}"
            );
        }
    }

    public function test_card_routes_are_registered(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->map(fn ($route) => $route->uri())
            ->all();

        $this->assertContains('api/v1/urban-goodz/driver/order-anywhere/{requestId}/purchase-card', $routes);
        $this->assertContains('api/v1/urban-goodz/driver/order-anywhere/{requestId}/purchase-card/receipt', $routes);
        $this->assertContains('api/v1/urban-goodz/driver/order-anywhere/{requestId}/purchase-card/secure-reveal', $routes);
        $this->assertContains('api/v1/order-anywhere/cards/stripe/webhook', $routes);
        $this->assertNotNull(
            app('router')->getRoutes()->getByName('admin.urban-goodz.order-anywhere.card-emergency-disable')
        );
    }

    public function test_emergency_disable_route_is_post_only(): void
    {
        $route = app('router')->getRoutes()
            ->getByName('admin.urban-goodz.order-anywhere.card-emergency-disable');

        $this->assertNotNull($route);
        $this->assertContains('POST', $route->methods());
        $this->assertNotContains('GET', $route->methods());
    }
}
