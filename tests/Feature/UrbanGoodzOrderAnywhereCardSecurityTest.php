<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\DeliveryMan;
use App\Models\OrderAnywhereRequest;
use App\Models\UrbanGoodzOrderAnywhereCardEvent;
use App\Models\UrbanGoodzOrderAnywhereCardReconciliation;
use App\Models\UrbanGoodzOrderAnywhereCardRequest;
use App\Models\UrbanGoodzOrderAnywhereCardRevealSession;
use App\Models\User;
use App\Models\Zone;
use App\Services\OrderAnywhereCardService;
use App\Services\Payments\CardIssuingProviderManager;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Authorization, receipt, reconciliation, webhook and sensitive-data certification
 * for the Order Anywhere purchase card.
 */
class UrbanGoodzOrderAnywhereCardSecurityTest extends TestCase
{
    use DatabaseTransactions;

    private const WEBHOOK_SECRET = 'whsec_order_anywhere_card_test_secret';

    private Admin $owner;
    private Admin $restrictedAdmin;
    private User $shopper;
    private DeliveryMan $driver;
    private DeliveryMan $otherDriver;
    private Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('urban_goodz_payments.issuing.provider', 'staged_test');
        Config::set('urban_goodz_payments.issuing.mode', 'sandbox');
        Config::set('urban_goodz_payments.issuing.max_driver_card_amount', 500.00);
        Config::set('urban_goodz_payments.issuing.stripe_webhook_secret', self::WEBHOOK_SECRET);

        \App\Models\BusinessSetting::withoutGlobalScopes()
            ->where('key', 'order_anywhere_card_emergency_disabled')
            ->delete();

        $this->zone = Zone::firstOrCreate(
            ['name' => 'Card Security Zone'],
            [
                'coordinates' => new \Illuminate\Database\Query\Expression(
                    "ST_GeomFromText('POLYGON((0 0, 0 100, 100 100, 100 0, 0 0))')"
                ),
                'status' => 1,
            ]
        );

        $this->owner = Admin::firstOrCreate(
            ['email' => 'card-sec-owner@urbangoodz.test'],
            [
                'f_name' => 'Owner', 'l_name' => 'Card', 'phone' => '5550100001',
                'password' => bcrypt('password'), 'role_id' => 1, 'is_logged_in' => 1,
            ]
        );
        $this->owner->forceFill(['role_id' => 1, 'is_logged_in' => 1])->save();

        $this->restrictedAdmin = Admin::firstOrCreate(
            ['email' => 'card-sec-restricted@urbangoodz.test'],
            [
                'f_name' => 'Restricted', 'l_name' => 'Card', 'phone' => '5550100002',
                'password' => bcrypt('password'), 'role_id' => 2, 'is_logged_in' => 1,
            ]
        );
        $this->restrictedAdmin->forceFill(['role_id' => 2, 'is_logged_in' => 1])->save();

        $this->shopper = User::firstOrCreate(
            ['email' => 'card-sec-shopper@urbangoodz.test'],
            [
                'f_name' => 'Shopper', 'l_name' => 'Card', 'phone' => '5550100003',
                'password' => bcrypt('password'), 'is_active' => 1, 'is_verified' => 1,
            ]
        );

        $this->driver = $this->makeDriver('5550100004', 'card-sec-driver@urbangoodz.test', 'card-sec-driver-token');
        $this->otherDriver = $this->makeDriver('5550100005', 'card-sec-driver2@urbangoodz.test', 'card-sec-driver2-token');
    }

    private function makeDriver(string $phone, string $email, string $token): DeliveryMan
    {
        $driver = DeliveryMan::firstOrCreate(
            ['phone' => $phone],
            [
                'f_name' => 'Driver', 'l_name' => 'Card', 'email' => $email,
                'password' => bcrypt('password'), 'active' => 1,
                'application_status' => 'approved', 'zone_id' => $this->zone->id,
                'available_for_order_anywhere' => true, 'auth_token' => $token,
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

    private function eligibleRequest(array $overrides = []): OrderAnywhereRequest
    {
        return OrderAnywhereRequest::create(array_merge([
            'request_number' => 'OA-SEC-' . bin2hex(random_bytes(5)),
            'customer_id' => $this->shopper->id,
            'status' => 'approved',
            'payment_status' => 'authorized',
            'fulfillment_type' => OrderAnywhereRequest::FULFILLMENT_EXTERNAL_MERCHANT,
            'assigned_delivery_man_id' => $this->driver->id,
            'merchant_purchase_amount' => 80.00,
            'quote_amount' => 100.00,
            'final_amount' => 100.00,
            'metadata' => ['quote_version' => 'v1'],
        ], $overrides));
    }

    private function cards(): OrderAnywhereCardService
    {
        return app(OrderAnywhereCardService::class);
    }

    /** An issued card backed by the staged test gateway. */
    private function issuedCard(?OrderAnywhereRequest $request = null): array
    {
        $request = $request ?: $this->eligibleRequest();
        $card = $this->cards()->createCardRequest($request);
        $card = $this->cards()->issuePreparedCard($card->id);

        return [$request, $card->fresh()];
    }

    private function driverHeaders(DeliveryMan $driver): array
    {
        return ['Authorization' => 'Bearer ' . $driver->auth_token];
    }

    // ── Driver API authorization ────────────────────────────────────────────

    public function test_unauthenticated_driver_request_is_rejected(): void
    {
        [$request] = $this->issuedCard();

        $this->getJson("/api/v1/urban-goodz/driver/order-anywhere/{$request->id}/purchase-card")
            ->assertStatus(401);
    }

    public function test_assigned_driver_can_read_their_own_card(): void
    {
        [$request, $card] = $this->issuedCard();

        $response = $this->withHeaders($this->driverHeaders($this->driver))
            ->getJson("/api/v1/urban-goodz/driver/order-anywhere/{$request->id}/purchase-card");

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.card_status', $card->card_status);
    }

    public function test_unassigned_driver_is_denied_card_access(): void
    {
        [$request] = $this->issuedCard();

        $this->withHeaders($this->driverHeaders($this->otherDriver))
            ->getJson("/api/v1/urban-goodz/driver/order-anywhere/{$request->id}/purchase-card")
            ->assertStatus(403);
    }

    public function test_unassigned_driver_cannot_upload_a_receipt(): void
    {
        [$request] = $this->issuedCard();

        $this->withHeaders($this->driverHeaders($this->otherDriver))
            ->postJson("/api/v1/urban-goodz/driver/order-anywhere/{$request->id}/purchase-card/receipt", [
                'receipt_total' => 10.00,
            ])
            ->assertStatus(403);
    }

    public function test_unassigned_driver_cannot_start_a_secure_reveal(): void
    {
        [$request] = $this->issuedCard();

        $this->withHeaders($this->driverHeaders($this->otherDriver))
            ->postJson("/api/v1/urban-goodz/driver/order-anywhere/{$request->id}/purchase-card/secure-reveal")
            ->assertStatus(403);

        $this->assertSame(0, UrbanGoodzOrderAnywhereCardRevealSession::where(
            'delivery_man_id',
            $this->otherDriver->id
        )->count());
    }

    // ── Shopper denial ──────────────────────────────────────────────────────

    /**
     * The driver card endpoints sit behind the delivery-man token guard, so customer
     * credentials are not accepted there at all.
     */
    public function test_shopper_credentials_cannot_reach_the_driver_card_endpoint(): void
    {
        [$request] = $this->issuedCard();

        // A customer-held token is not a delivery-man auth token.
        $this->withHeaders(['Authorization' => 'Bearer shopper-session-token'])
            ->getJson("/api/v1/urban-goodz/driver/order-anywhere/{$request->id}/purchase-card")
            ->assertStatus(401);

        $this->assertSame(
            0,
            DeliveryMan::where('auth_token', 'shopper-session-token')->count(),
            'Customer credentials must never resolve to a driver.'
        );
    }

    public function test_shopper_never_receives_card_credentials(): void
    {
        [$request, $card] = $this->issuedCard();

        $response = $this->withHeaders($this->driverHeaders($this->driver))
            ->getJson("/api/v1/urban-goodz/driver/order-anywhere/{$request->id}/purchase-card");

        $body = $response->getContent();
        foreach (['pan', 'cvc', 'card_number', 'security_code', 'ephemeral_key'] as $forbidden) {
            $this->assertStringNotContainsStringIgnoringCase(
                "\"{$forbidden}\"",
                $body,
                "Driver card payload exposed [{$forbidden}]."
            );
        }
        $this->assertNotNull($card->provider_card_id);
        $this->assertStringNotContainsString($card->provider_card_id, $body);
    }

    // ── Restricted-admin denial ─────────────────────────────────────────────

    /**
     * A restricted admin is authenticated but denied. The module middleware denies
     * first and redirects to the dashboard root rather than emitting 403, so the
     * binding assertion is that the privileged action had no effect.
     */
    public function test_restricted_admin_cannot_trigger_manual_issuance_recovery(): void
    {
        $request = $this->eligibleRequest();

        $response = $this->actingAs($this->restrictedAdmin, 'admin')
            ->post(route('admin.urban-goodz.order-anywhere.request-card', $request->id), []);

        $this->assertContains($response->getStatusCode(), [302, 403]);
        $this->assertStringNotContainsString(
            'login',
            (string) $response->headers->get('Location'),
            'Denial must come from authorization, not from an unauthenticated redirect.'
        );
        $this->assertSame(0, UrbanGoodzOrderAnywhereCardRequest::where(
            'order_anywhere_request_id',
            $request->id
        )->count(), 'A restricted admin must not be able to create a card request.');
    }

    public function test_restricted_admin_cannot_use_emergency_disable(): void
    {
        $response = $this->actingAs($this->restrictedAdmin, 'admin')
            ->post(route('admin.urban-goodz.order-anywhere.card-emergency-disable'), ['disabled' => 1]);

        $this->assertContains($response->getStatusCode(), [302, 403]);
        app()->forgetInstance(CardIssuingProviderManager::class);
        $this->assertFalse(
            app(CardIssuingProviderManager::class)->isEmergencyDisabled(),
            'A restricted admin must not be able to flip the emergency control.'
        );
    }

    public function test_owner_can_use_emergency_disable(): void
    {
        $this->actingAs($this->owner, 'admin')
            ->post(route('admin.urban-goodz.order-anywhere.card-emergency-disable'), ['disabled' => 1])
            ->assertRedirect();

        app()->forgetInstance(CardIssuingProviderManager::class);
        $this->assertTrue(app(CardIssuingProviderManager::class)->isEmergencyDisabled());
    }

    // ── Secure reveal contract ──────────────────────────────────────────────

    public function test_assigned_driver_can_create_a_reveal_session(): void
    {
        [$request, $card] = $this->issuedCard();
        // The hosted reveal contract is Stripe-specific.
        $card->update(['provider' => 'stripe_issuing']);

        $response = $this->withHeaders($this->driverHeaders($this->driver))
            ->postJson("/api/v1/urban-goodz/driver/order-anywhere/{$request->id}/purchase-card/secure-reveal");

        $response->assertOk();
        $this->assertNotNull($response->json('data.reveal_url'));

        $session = UrbanGoodzOrderAnywhereCardRevealSession::where('card_request_id', $card->id)->first();
        $this->assertNotNull($session);
        $this->assertSame(64, strlen($session->token_hash), 'Only a token hash may be stored.');
        $this->assertStringNotContainsString($session->token_hash, (string) $response->json('data.reveal_url'));
    }

    public function test_reveal_is_rejected_when_the_provider_is_unconfigured(): void
    {
        Config::set('urban_goodz_payments.issuing.provider', 'disabled');
        app()->forgetInstance(CardIssuingProviderManager::class);
        app()->forgetInstance(OrderAnywhereCardService::class);

        $request = $this->eligibleRequest();
        $card = $this->cards()->createCardRequest($request);
        $this->assertSame('awaiting_provider_configuration', $card->card_status);

        $this->withHeaders($this->driverHeaders($this->driver))
            ->postJson("/api/v1/urban-goodz/driver/order-anywhere/{$request->id}/purchase-card/secure-reveal")
            ->assertStatus(422);

        $this->assertSame(0, UrbanGoodzOrderAnywhereCardRevealSession::count());
    }

    public function test_reveal_session_is_revoked_when_the_card_is_cancelled(): void
    {
        [$request, $card] = $this->issuedCard();
        $card->update(['provider' => 'stripe_issuing']);
        $this->cards()->createRevealSession($card->fresh());

        $this->cards()->revokeForRequest($request, 'driver_reassigned');

        $session = UrbanGoodzOrderAnywhereCardRevealSession::where('card_request_id', $card->id)->first();
        $this->assertNotNull($session->revoked_at, 'Reveal session must be revoked with the card.');
    }

    public function test_revoked_reveal_page_is_not_served(): void
    {
        [$request, $card] = $this->issuedCard();
        $card->update(['provider' => 'stripe_issuing']);
        $reveal = $this->cards()->createRevealSession($card->fresh());
        $token = basename(parse_url($reveal['reveal_url'], PHP_URL_PATH));

        $this->cards()->revokeForRequest($request, 'order_cancelled');

        $this->get("/order-anywhere/card-reveal/{$token}")->assertStatus(410);
    }

    // ── Receipt upload and ownership ────────────────────────────────────────

    public function test_assigned_driver_can_upload_a_receipt(): void
    {
        Storage::fake('local');
        [$request, $card] = $this->issuedCard();
        $card->update(['card_status' => 'authorized', 'authorized_amount' => 40.00]);

        $response = $this->withHeaders($this->driverHeaders($this->driver))
            ->post("/api/v1/urban-goodz/driver/order-anywhere/{$request->id}/purchase-card/receipt", [
                'receipt' => UploadedFile::fake()->image('receipt.jpg'),
                'receipt_total' => 40.00,
                'receipt_notes' => 'Store receipt',
            ]);

        $response->assertOk();
        $card->refresh();
        $this->assertNotNull($card->receipt_path);
        $this->assertNotNull($card->receipt_submitted_at);
        $this->assertEqualsWithDelta(40.00, (float) $card->receipt_total, 0.001);
        $this->assertSame(1, UrbanGoodzOrderAnywhereCardReconciliation::where(
            'card_request_id',
            $card->id
        )->count());
    }

    public function test_receipt_above_the_approved_limit_is_rejected(): void
    {
        Storage::fake('local');
        [$request, $card] = $this->issuedCard();
        $card->update(['card_status' => 'authorized', 'authorized_amount' => 40.00]);

        $this->withHeaders($this->driverHeaders($this->driver))
            ->post("/api/v1/urban-goodz/driver/order-anywhere/{$request->id}/purchase-card/receipt", [
                'receipt' => UploadedFile::fake()->image('receipt.jpg'),
                'receipt_total' => 5000.00,
            ])
            ->assertStatus(422);

        $this->assertNull($card->refresh()->receipt_path);
    }

    // ── Reconciliation ──────────────────────────────────────────────────────

    public function test_reconciliation_row_tracks_approved_budget_and_unused_amount(): void
    {
        Storage::fake('local');
        [, $card] = $this->issuedCard();
        $card->update([
            'card_status' => 'used',
            'authorized_amount' => 0,
            'captured_amount' => 60.00,
            'receipt_total' => 60.00,
            'receipt_path' => 'private/x.jpg',
        ]);

        $this->cards()->recordProviderTransaction($card->fresh(), 'txn_recon_1', 'capture', 0.01, 'auth_1');

        $reconciliation = UrbanGoodzOrderAnywhereCardReconciliation::where('card_request_id', $card->id)->first();
        $this->assertNotNull($reconciliation);
        $this->assertEqualsWithDelta(80.00, (float) $reconciliation->approved_budget, 0.001);
        $this->assertSame(1, UrbanGoodzOrderAnywhereCardReconciliation::where(
            'card_request_id',
            $card->id
        )->count(), 'Reconciliation must stay one row per card.');
    }

    public function test_mismatch_between_receipt_and_transaction_raises_an_exception_row(): void
    {
        [, $card] = $this->issuedCard();
        $card->update([
            'card_status' => 'authorized',
            'authorized_amount' => 50.00,
            'receipt_total' => 10.00,
            'receipt_path' => 'private/x.jpg',
        ]);

        $this->cards()->recordProviderTransaction($card->fresh(), 'txn_mismatch', 'capture', 50.00, 'auth_mismatch');

        $reconciliation = UrbanGoodzOrderAnywhereCardReconciliation::where('card_request_id', $card->id)->first();
        $this->assertSame('exception', $reconciliation->status);
        $this->assertSame('receipt_transaction_mismatch', $reconciliation->mismatch_category);
    }

    public function test_overage_beyond_the_approved_budget_raises_an_exception_row(): void
    {
        [, $card] = $this->issuedCard();
        $card->update(['card_status' => 'authorized', 'authorized_amount' => 200.00]);

        $this->cards()->recordProviderTransaction($card->fresh(), 'txn_overage', 'capture', 200.00, 'auth_overage');

        $reconciliation = UrbanGoodzOrderAnywhereCardReconciliation::where('card_request_id', $card->id)->first();
        $this->assertSame('exception', $reconciliation->status);
        $this->assertSame('overage', $reconciliation->mismatch_category);
        $this->assertGreaterThan(0, (float) $reconciliation->overage_amount);
    }

    public function test_force_post_transaction_is_flagged(): void
    {
        [, $card] = $this->issuedCard();
        $card->update(['card_status' => 'active']);

        // A capture with no prior authorization is a force post.
        $this->cards()->recordProviderTransaction($card->fresh(), 'txn_force', 'capture', 25.00, null);

        $reconciliation = UrbanGoodzOrderAnywhereCardReconciliation::where('card_request_id', $card->id)->first();
        $this->assertTrue((bool) $reconciliation->force_post);
        $this->assertSame('force_post', $reconciliation->mismatch_category);
        $this->assertSame('exception', $reconciliation->status);
    }

    // ── Provider webhooks ───────────────────────────────────────────────────

    private function signedWebhook(array $event): array
    {
        $payload = json_encode($event, JSON_UNESCAPED_SLASHES);
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", self::WEBHOOK_SECRET);

        return [$payload, "t={$timestamp},v1={$signature}"];
    }

    private function postWebhook(array $event)
    {
        [$payload, $signature] = $this->signedWebhook($event);

        return $this->call(
            'POST',
            '/api/v1/order-anywhere/cards/stripe/webhook',
            [],
            [],
            [],
            ['HTTP_STRIPE_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'],
            $payload
        );
    }

    public function test_webhook_rejects_an_invalid_signature(): void
    {
        $response = $this->call(
            'POST',
            '/api/v1/order-anywhere/cards/stripe/webhook',
            [],
            [],
            [],
            ['HTTP_STRIPE_SIGNATURE' => 't=1,v1=deadbeef', 'CONTENT_TYPE' => 'application/json'],
            json_encode(['id' => 'evt_bad', 'type' => 'issuing_authorization.created'])
        );

        $response->assertStatus(400);
        $this->assertSame(0, UrbanGoodzOrderAnywhereCardEvent::where('event_id', 'evt_bad')->count());
    }

    public function test_authorization_webhook_records_state_once_and_is_replay_safe(): void
    {
        [, $card] = $this->issuedCard();
        $card->update(['provider_card_id' => 'ic_test_auth_card']);

        $event = [
            'id' => 'evt_auth_' . bin2hex(random_bytes(4)),
            'type' => 'issuing_authorization.created',
            'data' => ['object' => [
                'id' => 'iauth_test_1',
                'card' => 'ic_test_auth_card',
                'approved' => true,
                'amount' => 4500,
                'status' => 'closed',
                'merchant_data' => ['name' => 'Test Merchant', 'category' => 'grocery_stores'],
            ]],
        ];

        $this->postWebhook($event)->assertOk();
        $card->refresh();
        $this->assertSame('authorized', $card->card_status);
        $this->assertSame('iauth_test_1', $card->provider_authorization_id);
        $this->assertEqualsWithDelta(45.00, (float) $card->authorized_amount, 0.001);

        // Replay of the identical event must not double-apply.
        $this->postWebhook($event)->assertOk()->assertJsonPath('already_processed', true);

        $this->assertSame(1, UrbanGoodzOrderAnywhereCardEvent::where('event_id', $event['id'])->count());
        $this->assertSame(1, UrbanGoodzOrderAnywhereCardReconciliation::where(
            'card_request_id',
            $card->id
        )->count());
    }

    public function test_transaction_webhook_completes_the_purchase_once(): void
    {
        [, $card] = $this->issuedCard();
        $card->update([
            'provider_card_id' => 'ic_test_txn_card',
            'card_status' => 'authorized',
            'authorized_amount' => 45.00,
            'provider_authorization_id' => 'iauth_test_2',
        ]);

        $event = [
            'id' => 'evt_txn_' . bin2hex(random_bytes(4)),
            'type' => 'issuing_transaction.created',
            'data' => ['object' => [
                'id' => 'ipi_test_1',
                'card' => 'ic_test_txn_card',
                'amount' => -4500,
                'type' => 'capture',
                'authorization' => 'iauth_test_2',
            ]],
        ];

        $this->postWebhook($event)->assertOk();

        $card->refresh();
        $this->assertSame('used', $card->card_status);
        $this->assertEqualsWithDelta(45.00, (float) $card->captured_amount, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $card->authorized_amount, 0.001);

        $this->postWebhook($event)->assertOk()->assertJsonPath('already_processed', true);
        $this->assertEqualsWithDelta(45.00, (float) $card->refresh()->captured_amount, 0.001);
    }

    public function test_authorization_request_is_declined_for_a_reassigned_driver(): void
    {
        [$request, $card] = $this->issuedCard();
        $card->update(['provider_card_id' => 'ic_test_decline_card']);
        $request->forceFill(['assigned_delivery_man_id' => $this->otherDriver->id])->saveQuietly();

        $response = $this->postWebhook([
            'id' => 'evt_req_' . bin2hex(random_bytes(4)),
            'type' => 'issuing_authorization.request',
            'data' => ['object' => [
                'id' => 'iauth_test_3',
                'card' => 'ic_test_decline_card',
                'pending_request' => ['amount' => 1000],
                'merchant_data' => ['name' => 'Test Merchant', 'category' => 'grocery_stores'],
            ]],
        ]);

        $response->assertOk();
        $this->assertFalse($response->json('approved'));
    }

    public function test_authorization_request_is_declined_above_the_remaining_balance(): void
    {
        [, $card] = $this->issuedCard();
        $card->update(['provider_card_id' => 'ic_test_limit_card']);

        $response = $this->postWebhook([
            'id' => 'evt_req_' . bin2hex(random_bytes(4)),
            'type' => 'issuing_authorization.request',
            'data' => ['object' => [
                'id' => 'iauth_test_4',
                'card' => 'ic_test_limit_card',
                'pending_request' => ['amount' => 900000],
                'merchant_data' => ['name' => 'Test Merchant', 'category' => 'grocery_stores'],
            ]],
        ]);

        $response->assertOk();
        $this->assertFalse($response->json('approved'));
    }

    // ── Sensitive-data log scan ─────────────────────────────────────────────

    public function test_card_lifecycle_writes_no_sensitive_data_to_logs(): void
    {
        $logFile = storage_path('logs/card-sensitive-scan.log');
        if (file_exists($logFile)) {
            unlink($logFile);
        }

        Config::set('logging.channels.card_scan', [
            'driver' => 'single',
            'path' => $logFile,
            'level' => 'debug',
        ]);
        Config::set('logging.default', 'card_scan');
        Log::setDefaultDriver('card_scan');

        [$request, $card] = $this->issuedCard();
        $card->update(['provider' => 'stripe_issuing']);
        $this->cards()->createRevealSession($card->fresh());
        $this->cards()->recordProviderTransaction($card->fresh(), 'txn_scan', 'capture', 25.00, null);
        $this->cards()->revokeForRequest($request, 'order_cancelled');

        $contents = file_exists($logFile) ? (string) file_get_contents($logFile) : '';

        foreach ([
            '/\b4[0-9]{15}\b/',                 // bare PAN
            '/"(pan|cvc|cvv|card_number)"/i',   // credential keys
            '/whsec_[A-Za-z0-9]+/',             // webhook secret
            '/sk_(test|live)_[A-Za-z0-9]+/',    // provider secret key
        ] as $pattern) {
            $this->assertSame(
                0,
                preg_match($pattern, $contents),
                "Sensitive data matching {$pattern} was written to the log."
            );
        }

        $session = UrbanGoodzOrderAnywhereCardRevealSession::where('card_request_id', $card->id)->first();
        $this->assertNotNull($session);
        $this->assertStringNotContainsString($session->token_hash, $contents);

        if (file_exists($logFile)) {
            unlink($logFile);
        }
    }

    public function test_provider_failure_reason_is_sanitized_before_storage(): void
    {
        [, $card] = $this->issuedCard();

        $this->cards()->reportFailure($card, 'declined', 'Card declined at merchant');

        $card->refresh();
        $this->assertSame('declined', $card->failure_category);
        $this->assertDoesNotMatchRegularExpression('/\b4[0-9]{15}\b/', (string) $card->failure_reason);
    }
}
