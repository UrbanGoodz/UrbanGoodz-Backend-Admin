<?php

namespace Tests\Feature;

use App\Models\UrbanGoodzPaymentLedger;
use App\Models\User;
use App\Models\UrbanGoodzRentalAsset;
use App\Models\UrbanGoodzRentalBooking;
use App\Services\UrbanGoodzRentalPaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Payment lifecycle for rentals - the first vertical besides Order Anywhere
 * that can actually take a card payment.
 *
 * Rentals carry two independent money flows, and the deposit is the one worth
 * guarding: it is a hold that is normally voided, not a charge. A bug that
 * captures it instead of releasing it takes real money from customers who
 * returned the asset intact.
 */
class RentalPaymentLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    private UrbanGoodzRentalPaymentService $payments;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('urban_goodz_payments.mode', 'sandbox');
        Config::set('urban_goodz_payments.provider', 'staged_test');
        Config::set('urban_goodz_payments.staged_test.enabled', true);

        $this->payments = app(UrbanGoodzRentalPaymentService::class);
    }

    /**
     * Car rental and equipment rental share one asset model, separated by
     * asset_type - so the payment lifecycle must behave identically for both.
     */
    private function asset(string $assetType = 'vehicle'): UrbanGoodzRentalAsset
    {
        return UrbanGoodzRentalAsset::firstOrCreate(
            ['title' => 'Test Rental Asset (' . $assetType . ')'],
            [
                'business_type_slug' => 'urban_goodz_rentals',
                'asset_type' => $assetType,
                'status' => 'available',
                'daily_rate' => 120.00,
                'deposit_amount' => 200.00,
                'is_active' => 1,
            ]
        );
    }

    private function customer(): User
    {
        return User::firstOrCreate(
            ['email' => 'rental-payments-e2e@urbangoodz.com'],
            [
                'f_name' => 'Rental',
                'l_name' => 'Customer',
                'phone' => '5550001111',
                'password' => bcrypt('password'),
                'is_active' => 1,
                'is_verified' => 1,
            ]
        );
    }

    private function booking(array $overrides = []): UrbanGoodzRentalBooking
    {
        $assetType = $overrides['asset_type'] ?? 'vehicle';
        unset($overrides['asset_type']);

        return UrbanGoodzRentalBooking::create(array_merge([
            'rental_asset_id' => $this->asset($assetType)->id,
            'customer_id' => $this->customer()->id,
            'customer_name' => 'Rental Customer',
            'customer_phone' => '5550001111',
            'start_at' => now()->addDay(),
            'end_at' => now()->addDays(3),
            'status' => 'confirmed',
            'payment_status' => 'pending',
            'deposit_status' => 'pending',
            'total_amount' => 360.00,
            'deposit_amount' => 200.00,
        ], $overrides));
    }

    private function ledgerCount(UrbanGoodzRentalBooking $b, string $event): int
    {
        return UrbanGoodzPaymentLedger::where('payable_type', UrbanGoodzRentalBooking::class)
            ->where('payable_id', $b->id)
            ->where('event_type', $event)
            ->count();
    }

    // ------------------------------------------------------------- rent

    public function test_rent_authorize_then_capture_records_a_polymorphic_ledger(): void
    {
        $booking = $this->booking();

        $authorized = $this->payments->authorizeRent($booking);
        $this->assertSame('authorized', $authorized->payment_status);
        $this->assertSame('360.00', (string) $authorized->authorized_amount);
        $this->assertNotNull($authorized->provider_reference);
        $this->assertNotNull($authorized->payment_authorized_at);

        $captured = $this->payments->captureRent($authorized);
        $this->assertSame('captured', $captured->payment_status);
        $this->assertSame('360.00', (string) $captured->captured_amount);

        $this->assertSame(1, $this->ledgerCount($booking, 'rent_authorization'));
        $this->assertSame(1, $this->ledgerCount($booking, 'rent_capture'));

        // The ledger is shared with Order Anywhere, so the discriminator has
        // to identify the rental correctly.
        $ledger = UrbanGoodzPaymentLedger::where('payable_type', UrbanGoodzRentalBooking::class)
            ->where('payable_id', $booking->id)
            ->where('event_type', 'rent_capture')
            ->first();
        $this->assertSame('rental', $ledger->feature);
        $this->assertSame('debit', $ledger->direction);
    }

    public function test_capture_cannot_exceed_the_authorized_amount(): void
    {
        $booking = $this->payments->authorizeRent($this->booking());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds the authorized');
        $this->payments->captureRent($booking, ['amount' => 500.00]);
    }

    public function test_capture_requires_an_authorization_first(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Must be authorized');
        $this->payments->captureRent($this->booking());
    }

    public function test_authorize_is_idempotent_for_a_repeated_reference(): void
    {
        $booking = $this->booking();

        $this->payments->authorizeRent($booking, ['reference' => 'fixed-auth-ref']);
        $this->payments->authorizeRent($booking, ['reference' => 'fixed-auth-ref']);

        $this->assertSame(1, $this->ledgerCount($booking, 'rent_authorization'));
    }

    // ----------------------------------------------------------- refund

    public function test_full_refund_reverts_a_capture(): void
    {
        $booking = $this->payments->captureRent($this->payments->authorizeRent($this->booking()));

        $refunded = $this->payments->refundRent($booking, ['reason' => 'cancelled late']);

        $this->assertSame('refunded', $refunded->payment_status);
        $this->assertSame('360.00', (string) $refunded->refunded_amount);
        $this->assertSame(1, $this->ledgerCount($booking, 'rent_refund'));
    }

    public function test_partial_refund_leaves_the_booking_partially_refunded(): void
    {
        $booking = $this->payments->captureRent($this->payments->authorizeRent($this->booking()));

        $refunded = $this->payments->refundRent($booking, ['amount' => 60.00]);

        $this->assertSame('partially_refunded', $refunded->payment_status);
        $this->assertSame('60.00', (string) $refunded->refunded_amount);
    }

    public function test_refund_cannot_exceed_the_captured_amount(): void
    {
        $booking = $this->payments->captureRent($this->payments->authorizeRent($this->booking()));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds the remaining refundable');
        $this->payments->refundRent($booking, ['amount' => 900.00]);
    }

    /**
     * The distinction that caused a real bug on the Order Anywhere path: a
     * redelivered provider confirmation must replay cleanly, while an
     * operator refunding twice must still fail loudly.
     */
    public function test_webhook_refund_replay_is_idempotent(): void
    {
        $booking = $this->payments->captureRent($this->payments->authorizeRent($this->booking()));

        $first = $this->payments->refundRent($booking, [
            'reference' => 'PSP_REFUND_RENT_1',
            'source' => 'webhook',
        ]);
        $this->assertSame('refunded', $first->payment_status);

        // Same refund redelivered by the provider.
        $replay = $this->payments->refundRent($booking, [
            'reference' => 'PSP_REFUND_RENT_1',
            'source' => 'webhook',
        ]);

        $this->assertSame('refunded', $replay->payment_status);
        $this->assertSame('360.00', (string) $replay->refunded_amount);
        $this->assertSame(1, $this->ledgerCount($booking, 'rent_refund'), 'A replay must not book a second refund.');
    }

    public function test_operator_duplicate_refund_still_fails_loudly(): void
    {
        $booking = $this->payments->captureRent($this->payments->authorizeRent($this->booking()));

        $this->payments->refundRent($booking, ['reason' => 'first']);

        // No provider evidence, so this must hit the state guard rather than
        // silently returning success.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Must be captured');
        $this->payments->refundRent($booking->fresh(), ['reason' => 'duplicate']);
    }

    // ---------------------------------------------------------- deposit

    public function test_deposit_hold_does_not_capture_money(): void
    {
        $booking = $this->payments->holdDeposit($this->booking());

        $this->assertSame('held', $booking->deposit_status);
        $this->assertSame('200.00', (string) $booking->deposit_authorized_amount);
        $this->assertNull($booking->deposit_captured_amount, 'A hold must not capture anything.');
        $this->assertSame(1, $this->ledgerCount($booking, 'deposit_authorization'));

        $ledger = UrbanGoodzPaymentLedger::where('payable_id', $booking->id)
            ->where('event_type', 'deposit_authorization')->first();
        $this->assertSame('hold', $ledger->direction, 'A deposit is reserved, never debited.');
    }

    public function test_clean_return_releases_the_hold_without_capturing(): void
    {
        $booking = $this->payments->releaseDeposit($this->payments->holdDeposit($this->booking()));

        $this->assertSame('released', $booking->deposit_status);
        $this->assertNull($booking->deposit_captured_amount, 'A clean return must never take the deposit.');
        $this->assertNotNull($booking->deposit_released_at);
        $this->assertSame(1, $this->ledgerCount($booking, 'deposit_release'));
    }

    public function test_damage_claims_only_part_of_the_deposit(): void
    {
        $booking = $this->payments->claimDeposit($this->payments->holdDeposit($this->booking()), ['amount' => 75.00]);

        $this->assertSame('partially_claimed', $booking->deposit_status);
        $this->assertSame('75.00', (string) $booking->deposit_captured_amount);

        $ledger = UrbanGoodzPaymentLedger::where('payable_id', $booking->id)
            ->where('event_type', 'deposit_claim')->first();
        $this->assertSame('debit', $ledger->direction);
        $this->assertSame(125.0, (float) $ledger->metadata['released_remainder']);
    }

    public function test_claim_cannot_exceed_the_held_deposit(): void
    {
        $booking = $this->payments->holdDeposit($this->booking());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds the held deposit');
        $this->payments->claimDeposit($booking, ['amount' => 400.00]);
    }

    public function test_a_released_deposit_cannot_then_be_claimed(): void
    {
        $booking = $this->payments->releaseDeposit($this->payments->holdDeposit($this->booking()));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Must be held');
        $this->payments->claimDeposit($booking, ['amount' => 50.00]);
    }

    public function test_releasing_twice_is_harmless(): void
    {
        $booking = $this->payments->releaseDeposit($this->payments->holdDeposit($this->booking()));

        $again = $this->payments->releaseDeposit($booking);

        $this->assertSame('released', $again->deposit_status);
        $this->assertSame(1, $this->ledgerCount($booking, 'deposit_release'));
    }

    /**
     * Equipment rental and car rental are the same model with a different
     * asset_type, so neither may diverge in how money is taken or held.
     */
    public function test_equipment_rental_follows_the_same_lifecycle_as_a_vehicle(): void
    {
        $equipment = $this->booking(['asset_type' => 'equipment', 'total_amount' => 90.00]);

        $captured = $this->payments->captureRent($this->payments->authorizeRent($equipment));
        $held = $this->payments->holdDeposit($captured);

        $this->assertSame('captured', $held->payment_status);
        $this->assertSame('90.00', (string) $held->captured_amount);
        $this->assertSame('held', $held->deposit_status);
        $this->assertNull($held->deposit_captured_amount);

        $released = $this->payments->releaseDeposit($held);
        $this->assertSame('released', $released->deposit_status);
        $this->assertNull($released->deposit_captured_amount);
    }

    public function test_rent_and_deposit_are_independent_flows(): void
    {
        $booking = $this->booking();

        $booking = $this->payments->holdDeposit($booking);
        $booking = $this->payments->captureRent($this->payments->authorizeRent($booking));

        // Charging the rent must leave the deposit hold untouched.
        $this->assertSame('captured', $booking->payment_status);
        $this->assertSame('held', $booking->deposit_status);
        $this->assertNull($booking->deposit_captured_amount);

        $booking = $this->payments->releaseDeposit($booking);

        // Releasing the deposit must not disturb the captured rent.
        $this->assertSame('released', $booking->deposit_status);
        $this->assertSame('captured', $booking->payment_status);
        $this->assertSame('360.00', (string) $booking->captured_amount);
    }
}
