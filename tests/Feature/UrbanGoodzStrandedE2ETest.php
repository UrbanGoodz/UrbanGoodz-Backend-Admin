<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use App\Models\Vendor;
use App\Models\UrbanGoodzStrandedService;
use App\Models\UrbanGoodzStrandedRequest;
use App\Models\UrbanGoodzStrandedResponder;
use App\Models\UrbanGoodzStrandedOffer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class UrbanGoodzStrandedE2ETest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_and_vendor_stranded_live_routes_are_registered(): void
    {
        $adminRoute = Route::getRoutes()->getByName('admin.urban-goodz.stranded.live-feed');
        $this->assertNotNull($adminRoute, 'Admin stranded live feed route is not registered.');
        $this->assertSame('admin/urban-goodz/stranded/live-feed', $adminRoute->uri());

        $vendorRoute = Route::getRoutes()->getByName('vendor.urban-goodz.stranded.live-feed');
        $this->assertNotNull($vendorRoute, 'Vendor stranded live feed route is not registered.');
        $this->assertSame('vendor-panel/urban-goodz/stranded/live-feed', $vendorRoute->uri());
    }

    public function test_stranded_public_services_catalogue_endpoint(): void
    {
        $response = $this->getJson('/api/v1/urban-goodz/stranded/services');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'help_request_fee_minor',
                'help_request_fee_enabled',
                'currency',
                'services',
            ]);
    }

    public function test_admin_stranded_live_feed_endpoint(): void
    {
        $admin = Admin::firstOrCreate(
            ['email' => 'admin-stranded-test@urbangoodz.com'],
            [
                'f_name' => 'Admin',
                'l_name' => 'Test',
                'phone' => '1234567890',
                'password' => bcrypt('password'),
                'role_id' => 1,
                'is_logged_in' => 1,
            ]
        );
        $admin->forceFill(['role_id' => 1, 'is_logged_in' => 1])->save();

        $response = $this->actingAs($admin, 'admin')
            ->getJson(route('admin.urban-goodz.stranded.live-feed'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'generated_at',
                'summary' => [
                    'active_requests',
                    'emergencies',
                    'awaiting_responder',
                    'in_progress',
                    'samaritans_online',
                    'professionals_online',
                    'responders_busy',
                    'stale_fixes',
                ],
                'requests',
                'responders',
            ]);
    }

    public function test_vendor_stranded_live_feed_endpoint(): void
    {
        $token = 'test_token_' . uniqid();
        $vendor = Vendor::firstOrCreate(
            ['email' => 'vendor-stranded-test@urbangoodz.com'],
            [
                'f_name' => 'Vendor',
                'l_name' => 'Test',
                'phone' => '1234567891',
                'password' => bcrypt('password'),
                'status' => 1,
                'login_remember_token' => $token,
            ]
        );
        $vendor->forceFill(['status' => 1, 'login_remember_token' => $token])->save();

        $response = $this->withSession(['login_remember_token' => $token])
            ->actingAs($vendor, 'vendor')
            ->getJson(route('vendor.urban-goodz.stranded.live-feed'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'generated_at',
                'summary' => [
                    'active_requests',
                    'emergencies',
                    'awaiting_responder',
                    'in_progress',
                    'samaritans_online',
                    'professionals_online',
                    'responders_busy',
                    'stale_fixes',
                ],
                'requests',
                'responders',
            ]);
    }
}
