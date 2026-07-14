<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Store;
use App\Models\StoreWallet;
use App\Models\Vendor;
use App\Models\WithdrawalMethod;
use App\Models\WithdrawRequest;
use App\Models\Zone;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class UrbanGoodzWithdrawalSecurityTest extends TestCase
{
    use DatabaseTransactions;

    private Vendor $vendor;
    private Vendor $otherVendor;
    private WithdrawalMethod $method;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('mail.status', false);
        $this->withoutMiddleware();

        $module = Module::firstOrCreate(
            ['module_name' => 'Session 9 Wallet'],
            ['module_type' => 'food', 'status' => 1]
        );
        $zone = Zone::firstOrCreate(
            ['name' => 'Session 9 Wallet Zone'],
            [
                'coordinates' => new \Illuminate\Database\Query\Expression(
                    "ST_GeomFromText('POLYGON((0 0, 0 100, 100 100, 100 0, 0 0))')"
                ),
                'status' => 1,
            ]
        );

        $this->vendor = Vendor::firstOrCreate(
            ['email' => 'session9-wallet-owner@urbangoodz.test'],
            [
                'f_name' => 'Wallet',
                'l_name' => 'Owner',
                'phone' => '3125552901',
                'password' => bcrypt('password'),
                'status' => 1,
            ]
        );
        $this->otherVendor = Vendor::firstOrCreate(
            ['email' => 'session9-wallet-other@urbangoodz.test'],
            [
                'f_name' => 'Other',
                'l_name' => 'Vendor',
                'phone' => '3125552902',
                'password' => bcrypt('password'),
                'status' => 1,
            ]
        );

        Store::firstOrCreate(
            ['vendor_id' => $this->vendor->id],
            [
                'name' => 'Session 9 Wallet Store',
                'phone' => '3125552911',
                'logo' => 'store.png',
                'address' => '100 Wallet Way',
                'module_id' => $module->id,
                'zone_id' => $zone->id,
                'status' => 1,
            ]
        );

        StoreWallet::updateOrCreate(
            ['vendor_id' => $this->vendor->id],
            [
                'total_earning' => 100.00,
                'total_withdrawn' => 0.00,
                'pending_withdraw' => 0.00,
                'collected_cash' => 0.00,
            ]
        );
        StoreWallet::updateOrCreate(
            ['vendor_id' => $this->otherVendor->id],
            [
                'total_earning' => 500.00,
                'total_withdrawn' => 0.00,
                'pending_withdraw' => 0.00,
                'collected_cash' => 0.00,
            ]
        );

        $this->method = WithdrawalMethod::firstOrCreate(
            ['method_name' => 'Session 9 Test Bank'],
            [
                'method_fields' => [],
                'is_default' => 0,
                'is_active' => 1,
            ]
        );

        $this->actingAs($this->vendor, 'vendor');
    }

    public function test_withdrawal_validates_balance_and_reserves_funds_atomically(): void
    {
        $this->from('/vendor-panel/wallet')->post('/vendor-panel/wallet/request', [
            'withdraw_method' => $this->method->id,
            'amount' => 60.00,
        ])->assertRedirect('/vendor-panel/wallet');

        $this->assertDatabaseHas('withdraw_requests', [
            'vendor_id' => $this->vendor->id,
            'amount' => 60.00,
            'approved' => 0,
        ]);
        $this->assertEquals(60.00, StoreWallet::where('vendor_id', $this->vendor->id)->value('pending_withdraw'));

        $this->from('/vendor-panel/wallet')->post('/vendor-panel/wallet/request', [
            'withdraw_method' => $this->method->id,
            'amount' => 50.00,
        ])->assertRedirect('/vendor-panel/wallet')
            ->assertSessionHasErrors('amount');

        $this->assertSame(1, WithdrawRequest::where('vendor_id', $this->vendor->id)->count());
        $this->assertEquals(60.00, StoreWallet::where('vendor_id', $this->vendor->id)->value('pending_withdraw'));
    }

    public function test_withdrawal_rejects_missing_or_inactive_method(): void
    {
        $inactive = WithdrawalMethod::create([
            'method_name' => 'Session 9 Inactive Bank',
            'method_fields' => [],
            'is_default' => 0,
            'is_active' => 0,
        ]);

        $this->from('/vendor-panel/wallet')->post('/vendor-panel/wallet/request', [
            'withdraw_method' => $inactive->id,
            'amount' => 10.00,
        ])->assertNotFound();

        $this->from('/vendor-panel/wallet')->post('/vendor-panel/wallet/request', [
            'amount' => 10.00,
        ])->assertSessionHasErrors('withdraw_method');
    }

    public function test_vendor_cannot_close_another_vendors_withdrawal(): void
    {
        $otherRequest = WithdrawRequest::create([
            'vendor_id' => $this->otherVendor->id,
            'amount' => 25.00,
            'withdrawal_method_id' => $this->method->id,
            'withdrawal_method_fields' => json_encode([]),
            'approved' => 0,
        ]);

        $this->delete("/vendor-panel/wallet/close/{$otherRequest->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('withdraw_requests', [
            'id' => $otherRequest->id,
            'vendor_id' => $this->otherVendor->id,
        ]);
        $this->assertEquals(0.00, StoreWallet::where('vendor_id', $this->vendor->id)->value('pending_withdraw'));
    }
}
