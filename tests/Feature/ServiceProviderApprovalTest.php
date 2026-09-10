<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\UrbanGoodzServiceProvider;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Approving a service provider must not be blocked by incomplete setup.
 *
 * Providers are onboarded the other way round in practice: an admin approves
 * the barber or lawn-care operator first, and the price list and schedule get
 * filled in afterwards. The endpoint used to 422 in that situation, which made
 * it impossible to approve anyone before they had finished configuring.
 *
 * Approval now succeeds regardless, and reports what is still outstanding
 * instead of refusing.
 */
class ServiceProviderApprovalTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // The endpoint sits behind auth:admin.
        $admin = Admin::firstOrCreate(
            ['email' => 'svc-approval-e2e@urbangoodz.com'],
            [
                'f_name' => 'Service',
                'l_name' => 'Admin',
                'phone' => '5557770001',
                'password' => bcrypt('password'),
                'role_id' => 1,
            ]
        );

        $this->actingAs($admin, 'admin');
    }

    private function vendor(): Vendor
    {
        return Vendor::firstOrCreate(
            ['email' => 'svc-provider-vendor-e2e@urbangoodz.com'],
            [
                'f_name' => 'Service',
                'l_name' => 'Vendor',
                'phone' => '5557770002',
                'password' => bcrypt('password'),
                'auth_token' => 'svc-provider-vendor-token',
                'status' => 1,
            ]
        );
    }

    private function provider(array $overrides = []): UrbanGoodzServiceProvider
    {
        return UrbanGoodzServiceProvider::create(array_merge([
            'business_name' => 'Fades by Marcus',
            'slug' => 'fades-by-marcus-' . uniqid(),
            'contact_name' => 'Marcus',
            'email' => 'marcus-' . uniqid() . '@example.com',
            'phone' => '5551230000',
            'service_category' => 'barber',
            'description' => 'Barber shop',
            'is_verified' => 0,
            'is_active' => 1,
            'vendor_id' => $this->vendor()->id,
            'approval_status' => 'pending',
        ], $overrides));
    }

    private function approve(UrbanGoodzServiceProvider $provider, string $status = 'approved')
    {
        return $this->putJson(
            "/api/v1/admin/service-bookings/providers/{$provider->id}/status",
            ['status' => $status]
        );
    }

    public function test_a_bare_provider_can_be_approved_without_any_setup(): void
    {
        $provider = $this->provider();

        $response = $this->approve($provider);

        $response->assertOk();
        $response->assertJsonPath('data.approval_status', 'approved');
        $response->assertJsonPath('data.is_verified', true);

        $provider->refresh();
        $this->assertSame('approved', $provider->approval_status);
        $this->assertNotNull($provider->approved_at);
    }

    public function test_approval_reports_what_is_still_outstanding(): void
    {
        $response = $this->approve($this->provider());

        $response->assertOk();

        // Not bookable yet, but the admin is told exactly why rather than blocked.
        $response->assertJsonPath('readiness.bookable', false);

        $outstanding = $response->json('readiness.outstanding_setup');
        $this->assertContains('onboarding_not_submitted', $outstanding);
        $this->assertContains('no_active_services', $outstanding);
        $this->assertContains('no_active_availability', $outstanding);
    }

    public function test_a_mobile_provider_is_told_it_lacks_a_service_area(): void
    {
        $provider = $this->provider(['location_modes' => ['mobile']]);

        $response = $this->approve($provider);

        $response->assertOk();
        $this->assertContains('no_active_service_area', $response->json('readiness.outstanding_setup'));
    }

    public function test_a_non_mobile_provider_is_not_asked_for_a_service_area(): void
    {
        $provider = $this->provider(['location_modes' => ['salon']]);

        $response = $this->approve($provider);

        $response->assertOk();
        $this->assertNotContains('no_active_service_area', $response->json('readiness.outstanding_setup'));
    }

    public function test_suspending_deactivates_without_verifying(): void
    {
        $provider = $this->provider();
        $this->approve($provider)->assertOk();

        $this->approve($provider, 'suspended')->assertOk();

        $provider->refresh();
        $this->assertSame('suspended', $provider->approval_status);
        $this->assertEquals(0, (int) $provider->is_active);
        $this->assertEquals(0, (int) $provider->is_verified);
        $this->assertNull($provider->approved_at);
    }

    public function test_an_invalid_status_is_still_rejected(): void
    {
        // Relaxing setup prerequisites must not relax input validation.
        $this->approve($this->provider(), 'whatever')->assertStatus(422);
    }
}
