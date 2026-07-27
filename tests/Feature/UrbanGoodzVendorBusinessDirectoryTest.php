<?php

namespace Tests\Feature;

use App\Http\Middleware\ActivationCheckMiddleware;
use App\Models\Admin;
use App\Services\UrbanGoodz\VendorBusinessDirectoryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UrbanGoodzVendorBusinessDirectoryTest extends TestCase
{
    use DatabaseTransactions;

    private VendorBusinessDirectoryService $directory;
    private int $activeVendorId;
    private int $incompleteVendorId;
    private int $orphanVendorId;
    private int $demoVendorId;
    private int $activeModuleId;
    private int $zoneId;
    private int $baselineBusinessClients;
    private int $baselineServiceProviders;
    private int $baselineCreators;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ActivationCheckMiddleware::class);

        $suffix = (string) random_int(100000, 999999);
        $now = now();
        $this->baselineBusinessClients = DB::table('urban_goodz_business_clients')->whereNull('deleted_at')->count();
        $this->baselineServiceProviders = DB::table('urban_goodz_service_providers')->count();
        $this->baselineCreators = DB::table('urban_goodz_creator_applications')->count();

        $this->activeModuleId = DB::table('modules')->insertGetId([
            'module_name' => "Directory Active {$suffix}",
            'module_type' => 'food',
            'status' => 1,
            'stores_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $inactiveModuleId = DB::table('modules')->insertGetId([
            'module_name' => "Directory Demo {$suffix}",
            'module_type' => 'grocery',
            'status' => 0,
            'stores_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->zoneId = DB::table('zones')->insertGetId([
            'name' => "Directory Zone {$suffix}",
            'coordinates' => DB::raw("ST_GeomFromText('POLYGON((0 0, 0 1, 1 1, 1 0, 0 0))')"),
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('module_zone')->insert([
            ['module_id' => $this->activeModuleId, 'zone_id' => $this->zoneId],
            ['module_id' => $inactiveModuleId, 'zone_id' => $this->zoneId],
        ]);

        $this->activeVendorId = $this->createVendor("active-{$suffix}", 1);
        $this->incompleteVendorId = $this->createVendor("incomplete-{$suffix}", 1);
        $this->orphanVendorId = $this->createVendor("orphan-{$suffix}", 1);
        $this->demoVendorId = $this->createVendor("demo-{$suffix}", 1);

        $activeStoreId = $this->createStore($this->activeVendorId, $this->activeModuleId, "Active Store {$suffix}");
        $this->createStore($this->incompleteVendorId, $this->activeModuleId, "Incomplete Store {$suffix}");
        $this->createStore($this->demoVendorId, $inactiveModuleId, "Demo Store {$suffix}");

        DB::table('items')->insert([
            'name' => "Approved Product {$suffix}",
            'price' => 12.50,
            'tax' => 0,
            'tax_type' => 'percent',
            'discount' => 0,
            'discount_type' => 'percent',
            'status' => 1,
            'is_approved' => 1,
            'store_id' => $activeStoreId,
            'module_id' => $this->activeModuleId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('urban_goodz_business_clients')->insert([
            'company_name' => "Directory Client {$suffix}",
            'email' => "client-{$suffix}@directory.test",
            'status' => 'approved',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('urban_goodz_service_providers')->insert([
            'business_name' => "Directory Provider {$suffix}",
            'slug' => "directory-provider-{$suffix}",
            'email' => "provider-{$suffix}@directory.test",
            'is_verified' => 1,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('urban_goodz_creator_applications')->insert([
            'creator_name' => "Directory Creator {$suffix}",
            'email' => "creator-{$suffix}@directory.test",
            'status' => 'approved',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->directory = app(VendorBusinessDirectoryService::class);
    }

    public function test_summary_distinguishes_accounts_stores_offerings_and_data_issues(): void
    {
        $summary = $this->directory->summary();

        $this->assertSame(4, $summary['vendor_accounts']);
        $this->assertSame(1, $summary['active_vendors']);
        $this->assertSame(3, $summary['stores']);
        $this->assertSame(1, $summary['active_stores']);
        $this->assertSame(1, $summary['pending_vendors']);
        $this->assertSame(1, $summary['orphaned_vendors']);
        $this->assertSame(1, $summary['imported_demo']);
        $this->assertSame($this->baselineBusinessClients + 1, $summary['business_clients']);
        $this->assertSame($this->baselineServiceProviders + 1, $summary['service_providers']);
        $this->assertSame($this->baselineCreators + 1, $summary['creators']);
        $this->assertSame(1, $summary['eligible_without_offerings']);
        $this->assertSame(3, $summary['unverified_lifecycle']);
        $this->assertSame(4, $summary['data_issues']);
    }

    public function test_tabs_and_search_return_truthful_classifications(): void
    {
        $accounts = $this->directory->paginate(['tab' => 'accounts', 'per_page' => 25]);
        $this->assertSame(4, $accounts->total());
        $this->assertSame(
            'active_store_with_offering',
            $accounts->firstWhere('vendor_id', $this->activeVendorId)->classification
        );
        $this->assertSame(
            'no_active_offering',
            $accounts->firstWhere('vendor_id', $this->incompleteVendorId)->classification
        );
        $this->assertSame(
            'missing_store',
            $accounts->firstWhere('vendor_id', $this->orphanVendorId)->classification
        );

        $this->assertSame(1, $this->directory->paginate(['tab' => 'active-stores'])->total());
        $this->assertSame(1, $this->directory->paginate(['tab' => 'missing-store'])->total());
        $this->assertSame(1, $this->directory->paginate(['tab' => 'imported-demo'])->total());
        $this->assertSame(4, $this->directory->paginate(['tab' => 'data-issues'])->total());
        $this->assertSame($this->baselineBusinessClients + 1, $this->directory->paginate(['tab' => 'business-clients'])->total());
        $this->assertSame($this->baselineServiceProviders + 1, $this->directory->paginate(['tab' => 'service-providers'])->total());
        $this->assertSame($this->baselineCreators + 1, $this->directory->paginate(['tab' => 'creators'])->total());
        $this->assertSame(1, $this->directory->paginate([
            'tab' => 'accounts',
            'search' => 'active-'.$this->fixtureSuffixFromVendor($this->activeVendorId),
        ])->total());
    }

    public function test_primary_admin_can_open_read_only_directory_and_legacy_store_link_remains(): void
    {
        $admin = $this->createAdmin(1, 'directory-owner');

        $response = $this->actingAs($admin, 'admin')
            ->withSession(['login_remember_token' => $admin->login_remember_token])
            ->get(route('admin.urban-goodz.vendors.index'));

        $response->assertOk()
            ->assertSee('Vendors &amp; Businesses', false)
            ->assertSee('Vendor Accounts')
            ->assertSee('Active Stores')
            ->assertSee('Marketplace Stores')
            ->assertSee(route('admin.store.list'), false);
    }

    public function test_admin_without_urban_goodz_permission_receives_403(): void
    {
        DB::table('admin_roles')->insert([
            'id' => 901,
            'name' => 'Directory Test Placeholder',
            'modules' => json_encode([]),
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('admin_roles')->insert([
            'id' => 902,
            'name' => 'Directory Support',
            'modules' => json_encode(['order_management']),
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $admin = $this->createAdmin(902, 'directory-support');

        $this->actingAs($admin, 'admin')
            ->withSession(['login_remember_token' => $admin->login_remember_token])
            ->get(route('admin.urban-goodz.vendors.index'))
            ->assertForbidden();
    }

    private function createVendor(string $key, int $status): int
    {
        return DB::table('vendors')->insertGetId([
            'f_name' => ucfirst($key),
            'l_name' => 'Owner',
            'phone' => '+1555'.random_int(1000000, 9999999),
            'email' => "{$key}@directory.test",
            'password' => bcrypt('test-password'),
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createStore(int $vendorId, int $moduleId, string $name): int
    {
        return DB::table('stores')->insertGetId([
            'name' => $name,
            'phone' => '+1666'.random_int(1000000, 9999999),
            'email' => 'store-'.$vendorId.'@directory.test',
            'address' => 'Controlled test address',
            'latitude' => '29.7604',
            'longitude' => '-95.3698',
            'vendor_id' => $vendorId,
            'zone_id' => $this->zoneId,
            'module_id' => $moduleId,
            'status' => 1,
            'active' => 1,
            'store_business_model' => 'commission',
            'admin_approval_status' => 'approved',
            'business_status' => 'active_partner',
            'is_claimed' => 1,
            'is_public_sourced' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createAdmin(int $roleId, string $key): Admin
    {
        return Admin::create([
            'f_name' => 'Directory',
            'l_name' => 'Admin',
            'email' => $key.'@directory.test',
            'phone' => '+1777'.random_int(1000000, 9999999),
            'password' => bcrypt('test-password'),
            'role_id' => $roleId,
            'is_logged_in' => 1,
            'login_remember_token' => 'directory-token-'.$key,
        ]);
    }

    private function fixtureSuffixFromVendor(int $vendorId): string
    {
        $email = (string) DB::table('vendors')->where('id', $vendorId)->value('email');
        preg_match('/active-(\d+)@/', $email, $match);

        return $match[1] ?? '';
    }
}
