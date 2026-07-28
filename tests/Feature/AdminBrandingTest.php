<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\BusinessSetting;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Fluent;
use Tests\TestCase;

class AdminBrandingTest extends TestCase
{
    use DatabaseTransactions;

    private Admin $owner;

    protected function setUp(): void
    {
        parent::setUp();

        BusinessSetting::updateOrCreate(['key' => 'business_name'], ['value' => '6amMart']);
        BusinessSetting::updateOrCreate(['key' => 'footer_text'], ['value' => 'Powered by 6am Tech']);

        $this->owner = Admin::firstOrCreate(
            ['email' => 'owner-branding-test@urbangoodz.com'],
            [
                'f_name' => 'Owner',
                'l_name' => 'Branding',
                'phone' => '5550000099',
                'password' => bcrypt('password'),
                'role_id' => 1,
            ]
        );
        $this->owner->forceFill([
            'is_logged_in' => 1,
            'login_remember_token' => 'branding-owner-session',
        ])->save();
    }

    private function asOwner(): static
    {
        return $this->actingAs($this->owner, 'admin')
            ->withSession(['login_remember_token' => $this->owner->login_remember_token]);
    }

    public function test_owner_page_uses_urban_goodz_brand_even_when_legacy_business_settings_remain(): void
    {
        $response = $this->asOwner()
            ->get(route('admin.urban-goodz.payment-center.index'));

        $response->assertOk();
        $response->assertSee('<title>Urban Goodz |', false);
        $response->assertSee('Payment Center - Owner Controls');
        $response->assertSee('public/assets/admin/svg/logos/urban-goodz.svg', false);
        $response->assertSee('Urban Goodz');
        $response->assertSee('Commerce, delivery, logistics, and growth.');
        $response->assertDontSee('6amMart');
        $response->assertDontSee('6am Tech');
        $response->assertDontSee('sixam');
    }

    public function test_admin_login_uses_static_urban_goodz_identity(): void
    {
        $response = $this->get('/login/admin');

        $response->assertOk();
        $response->assertSee('<title>Urban Goodz |', false);
        $response->assertSee('Admin Login');
        $response->assertSee('public/assets/admin/svg/logos/urban-goodz.svg', false);
        $response->assertDontSee('6amMart');
        $response->assertDontSee('6am Tech');
        $response->assertDontSee('sixam');
    }

    public function test_platform_mail_view_composer_overrides_legacy_company_name(): void
    {
        $view = view('email-templates.new-email-format-1', [
            'company_name' => '6amMart',
            'data' => new Fluent([
                'logo_full_url' => asset('public/assets/admin/svg/logos/urban-goodz.svg'),
                'image_full_url' => asset('public/assets/admin/img/blank2.png'),
                'button_url' => null,
            ]),
            'title' => 'Test',
            'body' => 'Body',
            'footer_text' => '',
            'copyright_text' => '',
        ]);

        $rendered = $view->render();
        $data = $view->getData();
        $this->assertSame('Urban Goodz', $data['company_name']);
        $this->assertStringContainsString('Urban Goodz', $rendered);
        $this->assertStringNotContainsString('6amMart', $rendered);
    }

    public function test_official_palette_is_configured_exactly(): void
    {
        $this->assertSame('Urban Goodz', config('urban_goodz.brand_name'));
        $this->assertSame([
            'seasoning_orange' => '#ED9914',
            'canvas' => '#E2D3BF',
            'dijon' => '#E5E276',
            'ug_black' => '#161616',
            'white' => '#FFFFFF',
        ], config('urban_goodz.brand_colors'));
    }

    public function test_owner_facing_sources_contain_no_legacy_brand_literals(): void
    {
        $roots = [
            resource_path('views/layouts/admin'),
            resource_path('views/auth'),
            resource_path('views/admin-views/external-configuration'),
        ];

        foreach ($roots as $root) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
            foreach ($files as $file) {
                if (!$file->isFile() || !str_ends_with($file->getFilename(), '.blade.php')) {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());
                $this->assertDoesNotMatchRegularExpression(
                    '/6amMart|6am Tech|sixam/i',
                    $contents,
                    'Legacy branding remains in ' . $file->getPathname()
                );
            }
        }
    }
}
