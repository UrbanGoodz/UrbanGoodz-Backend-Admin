<?php

namespace Tests\Feature;

use App\Models\OrderAnywhereRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The Flutter vendor app calls POST /api/v1/order-anywhere/requests/{id}/cancel
 * and POST /api/v1/order-anywhere/requests/{id}/test-payment. Both routes were
 * absent on the backend, so the app surfaced "Backend pending" errors.
 * These tests lock the two newly wired routes.
 */
class OrderAnywhereVendorRoutesTest extends TestCase
{
    use DatabaseTransactions;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::firstOrCreate(
            ['email' => 'order-anywhere-vendor-test@urbangoodz.com'],
            [
                'f_name' => 'Order',
                'l_name' => 'Customer',
                'phone' => '5550000123',
                'password' => bcrypt('password'),
                'status' => 1,
            ]
        );
    }

    private function makeRequest(array $overrides = []): OrderAnywhereRequest
    {
        return OrderAnywhereRequest::create(array_merge([
            'request_number' => 'OA-VT-'.uniqid(),
            'customer_id' => $this->customer->id,
            'customer_name' => 'Test Customer',
            'customer_phone' => '5550000123',
            'store_vendor_name' => 'Test Store',
            'item_details' => 'Two boxes of nitrile gloves.',
            'quantity' => 2,
            'status' => 'awaiting_payment',
            'payment_status' => 'awaiting_payment',
            'quote_amount' => 142.50,
            'item_subtotal' => 100.00,
            'service_fee' => 15.00,
            'delivery_fee' => 7.99,
            'tax' => 8.30,
            'tip' => 0,
        ], $overrides));
    }

    public function test_cancel_route_accepts_a_valid_cancellation(): void
    {
        $record = $this->makeRequest(['status' => 'approved']);

        $response = $this->actingAs($this->customer, 'api')
            ->postJson("/api/v1/order-anywhere/requests/{$record->id}/cancel", ['reason' => 'Changed my mind']);

        $response->assertOk();

        $record->refresh();
        $this->assertSame('cancelled', $record->status);
    }

    public function test_cancel_rejects_terminal_state_requests(): void
    {
        $record = $this->makeRequest(['status' => 'completed']);

        $this->actingAs($this->customer, 'api')
            ->postJson("/api/v1/order-anywhere/requests/{$record->id}/cancel")
            ->assertStatus(422);
    }

    public function test_cancel_requires_authenticated_customer(): void
    {
        $record = $this->makeRequest(['status' => 'approved']);

        $this->postJson("/api/v1/order-anywhere/requests/{$record->id}/cancel")
            ->assertUnauthorized();
    }

    public function test_test_payment_route_exists_in_non_production(): void
    {
        $record = $this->makeRequest();

        $response = $this->actingAs($this->customer, 'api')
            ->postJson("/api/v1/order-anywhere/requests/{$record->id}/test-payment");
        $response->assertOk();

        $record->refresh();
        $this->assertSame('captured', $record->payment_status);
        $this->assertNotNull($record->payment_captured_at);
    }
}