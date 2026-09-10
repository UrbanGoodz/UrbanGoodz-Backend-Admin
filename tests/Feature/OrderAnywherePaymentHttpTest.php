<?php

namespace Tests\Feature;

use App\Models\OrderAnywhereRequest;
use App\Models\User;
use App\Models\UrbanGoodzPaymentLedger;
use App\Models\UrbanGoodzWebhookEvent;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Laravel\Passport\Passport;
use Tests\TestCase;

class OrderAnywherePaymentHttpTest extends TestCase
{
    use DatabaseTransactions;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('urban_goodz_payments.mode', 'sandbox');
        Config::set('urban_goodz_payments.provider', 'staged_test');
        Config::set('urban_goodz_payments.staged_test.enabled', true);

        $this->customer = User::firstOrCreate(
            ['email' => 'payment-e2e@urbangoodz.com'],
            [
                'f_name' => 'Payment',
                'l_name' => 'E2E',
                'phone' => '4445556666',
                'password' => bcrypt('password'),
                'is_active' => 1,
                'is_verified' => 1,
            ]
        );
    }

    private function makeRequest(array $overrides = []): OrderAnywhereRequest
    {
        return OrderAnywhereRequest::create(array_merge([
            'request_number' => 'OA-PAY-e2e-' . \Illuminate\Support\Str::random(8),
            'customer_id' => $this->customer->id,
            'status' => 'shopping',
            'quote_amount' => 50.00,
            'final_amount' => 50.00,
            'payment_status' => 'authorized',
            'authorized_amount' => 50.00,
            'psp_reference' => 'PSP_AUTH_e2e',
            'authorization_reference' => 'PSP_AUTH_e2e',
            'fulfillment_type' => 'external_merchant',
        ], $overrides));
    }

    private function captureEventPayload(OrderAnywhereRequest $request, string $pspReference, string $eventId): array
    {
        return [
            'event_code' => 'CAPTURE',
            'event_id' => $eventId,
            'merchant_reference' => $request->request_number,
            'provider_reference' => $pspReference,
            'amount_minor' => 5000,
            'currency' => 'USD',
            'success' => true,
        ];
    }

    private function refundEventPayload(OrderAnywhereRequest $request, string $pspReference, string $eventId): array
    {
        return [
            'event_code' => 'REFUND',
            'event_id' => $eventId,
            'merchant_reference' => $request->request_number,
            'provider_reference' => $pspReference,
            'amount_minor' => 5000,
            'currency' => 'USD',
            'success' => true,
        ];
    }

    private function ledgerCount(OrderAnywhereRequest $request, string $eventType): int
    {
        return UrbanGoodzPaymentLedger::where('payable_type', OrderAnywhereRequest::class)
            ->where('payable_id', $request->id)
            ->where('event_type', $eventType)
            ->count();
    }

    public function test_capture_webhook_replay_is_idempotent(): void
    {
        $request = $this->makeRequest();

        // First delivery: captures and finalizes exactly once.
        $response = $this->postJson('/api/v1/payments/webhooks/staged_test', $this->captureEventPayload($request, 'PSP_CAPTURE_e2e', 'evt_capture_1'));
        $response->assertOk()->assertSee('OK');

        // Same provider event id redelivered: ignored at the event level.
        $response = $this->postJson('/api/v1/payments/webhooks/staged_test', $this->captureEventPayload($request, 'PSP_CAPTURE_e2e', 'evt_capture_1'));
        $response->assertOk()->assertSee('OK');

        // Different event id for the same PaymentIntent/provided reference (Stripe fans out
        // charge.succeeded + payment_intent.succeeded): collapses on the finalization identity.
        $response = $this->postJson('/api/v1/payments/webhooks/staged_test', $this->captureEventPayload($request, 'PSP_CAPTURE_e2e', 'evt_capture_2'));
        $response->assertOk()->assertSee('OK');

        $request->refresh();

        $this->assertSame('captured', $request->payment_status);
        $this->assertEquals(50.00, (float) $request->captured_amount);
        $this->assertEquals(1, $this->ledgerCount($request, 'capture'));

        $this->assertDatabaseHas('urban_goodz_webhook_events', [
            'event_id' => 'evt_capture_1',
            'event_type' => 'CAPTURE',
            'provider' => 'staged_test',
            'payable_id' => $request->id,
            'status' => 'succeeded',
            'duplicate_count' => 1,
        ]);

        $this->assertDatabaseHas('urban_goodz_webhook_events', [
            'event_id' => 'evt_capture_2',
            'event_type' => 'CAPTURE',
            'provider' => 'staged_test',
            'payable_id' => $request->id,
            'status' => 'succeeded',
        ]);

        $this->assertSame(1, UrbanGoodzWebhookEvent::where('event_id', 'evt_capture_1')->where('provider', 'staged_test')->count());
        $this->assertSame(1, UrbanGoodzWebhookEvent::where('event_id', 'evt_capture_2')->where('provider', 'staged_test')->count());

        $this->assertSame(
            1,
            UrbanGoodzWebhookEvent::where('event_type', 'payment_finalization')
                ->where('operation', 'capture')
                ->where('payable_id', $request->id)
                ->count()
        );
    }

    public function test_capture_webhook_reconstructs_missing_authorization_state(): void
    {
        $request = $this->makeRequest(['payment_status' => 'unpaid']);

        $response = $this->postJson('/api/v1/payments/webhooks/staged_test', $this->captureEventPayload($request, 'PSP_CAPTURE_e2e', 'evt_capture_recovery'));
        $response->assertOk()->assertSee('OK');

        $request->refresh();
        $this->assertSame('captured', $request->payment_status);
        $this->assertEquals(50.00, (float) $request->captured_amount);
        $this->assertSame('PSP_CAPTURE_e2e', $request->psp_reference);
        $this->assertEquals(1, $this->ledgerCount($request, 'capture'));
    }

    public function test_customer_cancel_voids_authorization_and_blocks_terminal_repeat(): void
    {
        $request = $this->makeRequest([
            'psp_reference' => 'PSP_CANCEL_e2e',
            'authorization_reference' => 'PSP_CANCEL_e2e',
        ]);

        Passport::actingAs($this->customer);

        $response = $this->postJson("/api/v1/order-anywhere/requests/{$request->id}/cancel", [
            'reason' => 'Customer no longer needs the item.',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Order Anywhere request cancelled.',
            ]);

        $request->refresh();
        $this->assertSame('cancelled', $request->status);
        $this->assertSame('cancelled', $request->payment_status);

        $this->assertEquals(1, $this->ledgerCount($request, 'authorization_voided'));

        $this->assertDatabaseHas('urban_goodz_payment_ledgers', [
            'payable_type' => OrderAnywhereRequest::class,
            'payable_id' => $request->id,
            'event_type' => 'authorization_voided',
            'reference' => 'PSP_CANCEL_e2e',
        ]);

        $retry = $this->postJson("/api/v1/order-anywhere/requests/{$request->id}/cancel", [
            'reason' => 'Trying to cancel again.',
        ]);

        $retry->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_refund_webhook_is_idempotent_and_reverts_capture(): void
    {
        $request = $this->makeRequest([
            'status' => 'completed',
            'payment_status' => 'captured',
            'captured_amount' => 50.00,
            'psp_reference' => 'PSP_REFUND_HOLD_e2e',
        ]);

        $response = $this->postJson('/api/v1/payments/webhooks/staged_test', $this->refundEventPayload($request, 'PSP_REFUND_e2e', 'evt_refund_1'));
        $response->assertOk()->assertSee('OK');

        $request->refresh();
        $this->assertSame('refunded', $request->payment_status);
        $this->assertEquals(50.00, (float) $request->refunded_amount);
        $this->assertSame('PSP_REFUND_e2e', $request->refund_reference);
        $this->assertEquals(1, $this->ledgerCount($request, 'refund'));

        // Same provider event id redelivered: ignored.
        $response = $this->postJson('/api/v1/payments/webhooks/staged_test', $this->refundEventPayload($request, 'PSP_REFUND_e2e', 'evt_refund_1'));
        $response->assertOk()->assertSee('OK');

        // Different event id, same refund reference: collapses on the refund idempotency key.
        $response = $this->postJson('/api/v1/payments/webhooks/staged_test', $this->refundEventPayload($request, 'PSP_REFUND_e2e', 'evt_refund_2'));
        $response->assertOk()->assertSee('OK');

        $request->refresh();
        $this->assertSame('refunded', $request->payment_status);
        $this->assertEquals(50.00, (float) $request->refunded_amount);
        $this->assertEquals(1, $this->ledgerCount($request, 'refund'));

        $this->assertDatabaseHas('urban_goodz_webhook_events', [
            'event_id' => 'evt_refund_1',
            'event_type' => 'REFUND',
            'provider' => 'staged_test',
            'payable_id' => $request->id,
            'status' => 'succeeded',
            'duplicate_count' => 1,
        ]);

        $this->assertDatabaseHas('urban_goodz_webhook_events', [
            'event_id' => 'evt_refund_2',
            'event_type' => 'REFUND',
            'provider' => 'staged_test',
            'payable_id' => $request->id,
            'status' => 'succeeded',
        ]);
    }

    public function test_customer_cannot_cancel_another_customers_request(): void
    {
        $request = $this->makeRequest();
        $other = User::firstOrCreate(
            ['email' => 'payment-other@urbangoodz.com'],
            [
                'f_name' => 'Other',
                'l_name' => 'Customer',
                'phone' => '4445556667',
                'password' => bcrypt('password'),
                'is_active' => 1,
                'is_verified' => 1,
            ]
        );

        Passport::actingAs($other);

        $this->postJson("/api/v1/order-anywhere/requests/{$request->id}/cancel", [
            'reason' => 'Not mine to cancel.',
        ])->assertStatus(404);

        $request->refresh();
        $this->assertNotSame('cancelled', $request->status);
    }
}