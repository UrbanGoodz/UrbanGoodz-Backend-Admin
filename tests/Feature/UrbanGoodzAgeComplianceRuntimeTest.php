<?php

namespace Tests\Feature;

use App\Models\Admin;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class UrbanGoodzAgeComplianceRuntimeTest extends TestCase
{
    use DatabaseTransactions;

    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'f_name' => 'Test',
            'l_name' => 'Admin',
            'email' => 'test@admin.com',
            'phone' => '1234567890',
            'password' => bcrypt('password'),
            'role_id' => 1,
            'image' => 'def.png',
            'is_logged_in' => 1,
        ]);

        $this->actingAs($this->admin, 'admin');
    }

    public function test_1_age_compliance_index_page_loads()
    {
        $response = $this->get(route('admin.urban-goodz.age-compliance.index'));
        $response->assertStatus(200);
    }

    public function test_2_age_compliance_packages_page_loads()
    {
        $response = $this->get(route('admin.urban-goodz.age-compliance.packages'));
        $response->assertStatus(200);
    }

    public function test_3_age_compliance_orders_page_loads()
    {
        $response = $this->get(route('admin.urban-goodz.age-compliance.orders'));
        $response->assertStatus(200);
    }

    public function test_4_age_compliance_items_page_loads()
    {
        $response = $this->get(route('admin.urban-goodz.age-compliance.items'));
        $response->assertStatus(200);
    }

    public function test_5_admin_dashboard_still_loads()
    {
        $response = $this->get(route('admin.dashboard'));
        $response->assertStatus(200);
    }

    public function test_6_manifests_index_still_loads()
    {
        $response = $this->get(route('admin.urban-goodz.manifests.index'));
        $response->assertStatus(200);
    }

    public function test_7_courier_routes_index_still_loads()
    {
        $response = $this->get(route('admin.urban-goodz.dedicated-routes.index'));
        $response->assertStatus(200);
    }
}
