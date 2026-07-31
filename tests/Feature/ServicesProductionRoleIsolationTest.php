<?php

namespace Tests\Feature;

use App\Models\UrbanGoodzProviderPortfolioItem;
use App\Models\UrbanGoodzServiceProvider;
use App\Models\UrbanGoodzServiceRequest;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ServicesProductionRoleIsolationTest extends TestCase
{
    use DatabaseTransactions;

    private function customer(string $tag): User
    {
        return User::firstOrCreate(
            ['email' => "services-iso-{$tag}@urbangoodz.test"],
            [
                'f_name' => 'Services',
                'l_name' => ucfirst($tag),
                'phone' => '+1713000'.random_int(1000, 9999),
                'password' => bcrypt('password'),
            ]
        );
    }

    private function vendor(string $tag): Vendor
    {
        return Vendor::firstOrCreate(
            ['email' => "services-iso-vendor-{$tag}@urbangoodz.test"],
            [
                'f_name' => 'Isolation',
                'l_name' => 'Vendor '.$tag,
                'phone' => '+1281000'.random_int(1000, 9999),
                'password' => bcrypt('password'),
                'status' => 1,
            ]
        );
    }

    private function provider(string $tag): UrbanGoodzServiceProvider
    {
        return UrbanGoodzServiceProvider::firstOrCreate(
            ['slug' => "services-iso-provider-{$tag}"],
            [
                'vendor_id' => $this->vendor($tag)->id,
                'business_name' => "Isolation Provider {$tag}",
                'approval_status' => 'approved',
                'is_verified' => true,
                'is_active' => true,
                'location_modes' => ['in_person'],
            ]
        );
    }

    private function booking(User $owner, UrbanGoodzServiceProvider $provider): UrbanGoodzServiceRequest
    {
        return UrbanGoodzServiceRequest::create([
            'user_id' => $owner->id,
            'customer_name' => trim($owner->f_name.' '.$owner->l_name),
            'customer_email' => $owner->email,
            'service_type' => 'barber',
            'status' => 'confirmed',
            'provider_id' => $provider->id,
            'assigned_vendor_id' => $provider->vendor_id,
            'currency' => 'USD',
            'quoted_amount_minor' => 5000,
            'amount_paid_minor' => 5000,
            'refunded_amount_minor' => 0,
            'payment_status' => 'paid',
        ]);
    }

    public function test_service_categories_are_publicly_readable(): void
    {
        $response = $this->getJson('/api/v1/customer/service-bookings/categories');

        $response->assertOk();
        $slugs = collect($response->json('data'))->pluck('slug');
        foreach (['barber', 'hair_stylist', 'braider', 'nail_technician', 'makeup_artist', 'mobile_mechanic', 'photographer', 'dj', 'contractor', 'tax_professional', 'home_health_provider', 'personal_trainer'] as $required) {
            $this->assertContains($required, $slugs->all(), "Missing required category: {$required}");
        }
    }

    public function test_provider_search_rejects_a_coordinate_without_its_pair(): void
    {
        $this->getJson('/api/v1/customer/service-bookings/providers?latitude=29.7604')
            ->assertStatus(422);
    }

    public function test_booking_list_requires_authentication(): void
    {
        $this->getJson('/api/v1/customer/service-bookings')
            ->assertStatus(401);
    }

    public function test_a_customer_cannot_read_another_customers_booking(): void
    {
        $owner = $this->customer('owner');
        $intruder = $this->customer('intruder');
        $booking = $this->booking($owner, $this->provider('a'));

        $this->actingAs($intruder, 'api')
            ->getJson("/api/v1/customer/service-bookings/{$booking->id}")
            ->assertNotFound();
    }

    public function test_a_customer_cannot_open_a_refund_request_on_another_customers_booking(): void
    {
        $owner = $this->customer('owner');
        $intruder = $this->customer('intruder');
        $booking = $this->booking($owner, $this->provider('b'));

        $this->actingAs($intruder, 'api')
            ->postJson("/api/v1/customer/service-bookings/{$booking->id}/refund-request", [
                'reason' => 'quality',
                'details' => 'Attempting to dispute a booking I do not own.',
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('urban_goodz_service_disputes', [
            'service_request_id' => $booking->id,
            'user_id' => $intruder->id,
        ]);
    }

    public function test_the_owning_customer_can_open_exactly_one_refund_request(): void
    {
        $owner = $this->customer('owner');
        $booking = $this->booking($owner, $this->provider('c'));

        $this->actingAs($owner, 'api')
            ->postJson("/api/v1/customer/service-bookings/{$booking->id}/refund-request", [
                'reason' => 'late',
                'details' => 'The provider never arrived for the appointment.',
            ])
            ->assertCreated();

        // A second open dispute on the same booking must be refused.
        $this->actingAs($owner, 'api')
            ->postJson("/api/v1/customer/service-bookings/{$booking->id}/refund-request", [
                'reason' => 'quality',
                'details' => 'Trying to open a second dispute.',
            ])
            ->assertStatus(409);

        $this->assertSame(
            1,
            \App\Models\UrbanGoodzServiceDispute::where('service_request_id', $booking->id)->count()
        );
    }

    public function test_a_refund_request_cannot_exceed_the_refundable_balance(): void
    {
        $owner = $this->customer('owner');
        $booking = $this->booking($owner, $this->provider('d'));

        $this->actingAs($owner, 'api')
            ->postJson("/api/v1/customer/service-bookings/{$booking->id}/refund-request", [
                'reason' => 'billing',
                'details' => 'Requesting more than was ever paid.',
                'requested_amount_minor' => 999999,
            ])
            ->assertStatus(422);
    }

    public function test_a_customer_only_sees_their_own_disputes(): void
    {
        $owner = $this->customer('owner');
        $intruder = $this->customer('intruder');
        $booking = $this->booking($owner, $this->provider('e'));

        $this->actingAs($owner, 'api')->postJson(
            "/api/v1/customer/service-bookings/{$booking->id}/refund-request",
            ['reason' => 'damage', 'details' => 'Garment was damaged during the service.']
        )->assertCreated();

        $seen = $this->actingAs($intruder, 'api')
            ->getJson('/api/v1/customer/service-bookings/disputes/mine')
            ->assertOk()
            ->json('data');

        $this->assertSame([], collect($seen)->pluck('service_request_id')->all());
    }

    public function test_portfolio_items_are_scoped_to_their_owning_provider(): void
    {
        $providerA = $this->provider('portfolio-a');
        $providerB = $this->provider('portfolio-b');

        $item = UrbanGoodzProviderPortfolioItem::create([
            'provider_id' => $providerA->id,
            'title' => 'Owned by provider A',
            'media_path' => 'urban-goodz/service-portfolio/a.jpg',
            'media_type' => 'image',
            'is_active' => true,
        ]);

        $this->assertNotSame((int) $providerA->id, (int) $providerB->id);
        $this->assertSame(
            0,
            UrbanGoodzProviderPortfolioItem::where('provider_id', $providerB->id)->whereKey($item->id)->count(),
            'A portfolio item must never resolve under a different provider.'
        );
    }
}
