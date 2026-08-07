<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\UrbanGoodzWaitlist;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class UrbanGoodzWaitlistTest extends TestCase
{
    use DatabaseTransactions;

    public function test_waitlist_routes_are_registered(): void
    {
        $adminRoute = Route::getRoutes()->getByName('admin.urban-goodz.waitlist');
        $this->assertNotNull($adminRoute, 'Admin waitlist route is not registered.');
        $this->assertSame('admin/urban-goodz/waitlist', $adminRoute->uri());

        $statusRoute = Route::getRoutes()->getByName('admin.urban-goodz.waitlist.status');
        $this->assertNotNull($statusRoute, 'Admin waitlist status route is not registered.');
    }

    public function test_public_waitlist_endpoint_creates_entry(): void
    {
        $response = $this->postJson('/api/v1/urban-goodz/waitlist', [
            'full_name' => 'Jane Doe',
            'email' => 'jane.doe@example.com',
            'phone' => '1234567890',
            'city' => 'Atlanta',
            'interest' => 'business',
            'message' => 'Delivery for my restaurant',
            'consent' => true,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('urban_goodz_waitlist', [
            'email' => 'jane.doe@example.com',
            'interest' => 'business',
            'status' => 'new',
        ]);
    }

    public function test_public_waitlist_endpoint_validates(): void
    {
        $response = $this->postJson('/api/v1/urban-goodz/waitlist', [
            'full_name' => '',
            'email' => 'not-an-email',
            'interest' => 'spam',
            'consent' => false,
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, UrbanGoodzWaitlist::count());
    }

    public function test_honeypot_submission_is_ignored(): void
    {
        $response = $this->postJson('/api/v1/urban-goodz/waitlist', [
            'company' => 'bot',
            'full_name' => 'Spam Bot',
            'email' => 'spam@bot.example',
            'interest' => 'other',
            'consent' => true,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('urban_goodz_waitlist', ['email' => 'spam@bot.example']);
    }

    public function test_admin_waitlist_page_loads(): void
    {
        $admin = Admin::firstOrCreate(
            ['email' => 'admin-waitlist-test@urbangoodz.com'],
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
            ->get(route('admin.urban-goodz.waitlist'));

        $response->assertStatus(200);
    }
}
