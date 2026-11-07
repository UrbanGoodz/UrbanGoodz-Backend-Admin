<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InsertsRows;
use Tests\TestCase;

/**
 * Vendor (tenant) isolation on the vendor API.
 *
 * Vendor B authenticates normally and then attacks every id-taking endpoint using
 * vendor A's record ids. For each attack the test asserts that nothing belonging to A
 * changed (row snapshot) and that A's data was not disclosed. A legitimate request by
 * the owner is also exercised, so "rejects everyone" cannot pass.
 */
class VendorTenantIsolationTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRows;

    private const API = '/api/v1/vendor';

    private object $a;   // vendor A: owner of the records under attack
    private object $b;   // vendor B: the attacker
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $this->a = $this->tenant('a');
        $this->b = $this->tenant('b');

        $a = $this->a->store;
        $this->ids = [
            'order' => $this->insertRow('orders', [
                'store_id' => $a, 'user_id' => 1, 'order_status' => 'pending', 'payment_status' => 'unpaid',
                'order_type' => 'delivery', 'order_amount' => 30, 'is_guest' => 0, 'module_id' => 1,
            ]),
            'item' => $this->insertRow('items', ['store_id' => $a, 'name' => 'A-SECRET-ITEM', 'status' => 1, 'price' => 10, 'module_id' => 1, 'images' => '[]', 'organic' => 0, 'recommended' => 0]),
            'coupon' => $this->insertRow('coupons', ['store_id' => $a, 'created_by' => 'vendor', 'title' => 'A-SECRET-COUPON', 'status' => 1, 'code' => 'ACODE1']),
            // A vendor banner is type=store_wise with the owning store id in `data`.
            'banner' => $this->insertRow('banners', ['data' => (string) $a, 'title' => 'A-SECRET-BANNER', 'status' => 1, 'type' => 'store_wise', 'created_by' => 'store']),
            'advertisement' => $this->insertRow('advertisements', ['store_id' => $a, 'title' => 'A-SECRET-AD', 'status' => 'approved']),
            'addon' => $this->insertRow('add_ons', ['store_id' => $a, 'name' => 'A-SECRET-ADDON', 'price' => 2, 'status' => 1]),
            'driver' => $this->insertRow('delivery_men', [
                'store_id' => $a, 'f_name' => 'A-SECRET-DRIVER', 'l_name' => 'x', 'phone' => '+1555'.random_int(1000000, 9999999),
                'email' => 'a-drv-'.uniqid().'@urbangoodz.test', 'password' => 'x', 'status' => 1, 'application_status' => 'approved',
                'type' => 'restaurant_wise',
            ]),
            'schedule' => $this->insertRow('store_schedule', ['store_id' => $a, 'day' => 1]),
            'withdraw_method' => $this->insertRow('disbursement_withdrawal_methods', ['store_id' => $a, 'method_name' => 'A-SECRET-METHOD', 'is_default' => 0]),
            'review' => $this->insertRow('reviews', ['item_id' => 0, 'store_id' => $a, 'user_id' => 1, 'order_id' => 0, 'rating' => 5, 'comment' => 'A-REVIEW', 'status' => 1]),
        ];
    }

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    // ---------------------------------------------------------------- fixtures

    private function tenant(string $tag): object
    {
        $token = 'vtok-'.$tag.'-'.bin2hex(random_bytes(12));
        $vendor = $this->insertRow('vendors', [
            'f_name' => 'V', 'l_name' => $tag, 'phone' => '+1555'.random_int(1000000, 9999999),
            'email' => "vendor-iso-{$tag}-".uniqid().'@urbangoodz.test', 'password' => bcrypt('x'),
            'auth_token' => $token, 'status' => 1,
        ]);
        $store = $this->insertRow('stores', [
            'name' => 'Store '.$tag, 'vendor_id' => $vendor, 'status' => 1, 'module_id' => 1, 'zone_id' => 1,
            'phone' => '+1555'.random_int(1000000, 9999999), 'email' => "store-{$tag}-".uniqid().'@urbangoodz.test',
            // Open the feature gates (item section, self delivery -> sub_self_delivery accessor)
            // so the attacks reach the handlers instead of the permission middleware.
            'item_section' => 1, 'self_delivery_system' => 1,
        ]);

        return (object) ['vendor' => $vendor, 'store' => $store, 'token' => $token];
    }

    private function as(object $tenant): array
    {
        return ['Authorization' => 'Bearer '.$tenant->token, 'vendorType' => 'owner', 'Accept' => 'application/json'];
    }

    private function snapshot(): array
    {
        $snap = [];
        foreach ([
            'orders' => 'order', 'items' => 'item', 'coupons' => 'coupon', 'banners' => 'banner',
            'advertisements' => 'advertisement', 'add_ons' => 'addon', 'delivery_men' => 'driver',
            'store_schedule' => 'schedule', 'disbursement_withdrawal_methods' => 'withdraw_method', 'reviews' => 'review',
        ] as $table => $key) {
            $row = DB::table($table)->where('id', $this->ids[$key])->first();
            $snap[$table] = $row ? (array) $row : null;
            if (is_array($snap[$table])) {
                unset($snap[$table]['updated_at']);
            }
        }

        return $snap;
    }

    // ----------------------------------------------------------------- attacks

    /** Every id-taking mutating endpoint: [method, uri, body]. */
    private function attacks(): array
    {
        $i = $this->ids;

        return [
            'update order status' => ['PUT', '/update-order-status', ['order_id' => $i['order'], 'status' => 'canceled', 'reason' => 'x', 'order_status' => 'canceled']],
            'update order amount' => ['PUT', '/update-order-amount', ['order_id' => $i['order'], 'order_amount' => 1]],
            'send order otp' => ['PUT', '/send-order-otp', ['order_id' => $i['order']]],

            'update item' => ['PUT', '/item/update', ['id' => $i['item'], 'name' => ['HACKED'], 'price' => 1, 'status' => 0]],
            'delete item' => ['DELETE', '/item/delete', ['id' => $i['item']]],
            'item stock' => ['PUT', '/item/stock-update', ['item_id' => $i['item'], 'id' => $i['item'], 'stock' => 999]],
            'item reply' => ['PUT', '/item/reply-update', ['id' => $i['item'], 'reply' => 'HACKED']],

            'update coupon' => ['POST', '/coupon/update', ['coupon_id' => $i['coupon'], 'title' => 'HACKED']],
            'coupon status' => ['POST', '/coupon/status', ['coupon_id' => $i['coupon'], 'status' => 0]],
            'delete coupon' => ['POST', '/coupon/delete', ['coupon_id' => $i['coupon']]],

            'update banner' => ['PUT', '/banner/update', ['id' => $i['banner'], 'title' => 'HACKED']],
            'delete banner' => ['DELETE', '/banner/delete', ['id' => $i['banner']]],

            'update advertisement' => ['POST', '/advertisement/update/'.$i['advertisement'], ['title' => 'HACKED']],
            'advertisement status' => ['PUT', '/advertisement/status', ['id' => $i['advertisement'], 'status' => 'paused']],
            'delete advertisement' => ['DELETE', '/advertisement/delete/'.$i['advertisement'], []],

            'update addon' => ['PUT', '/addon/update', ['id' => $i['addon'], 'name' => 'HACKED', 'price' => 1]],
            'delete addon' => ['DELETE', '/addon/delete', ['id' => $i['addon']]],


            'reply to review' => ['PUT', '/item/reply-update', ['id' => $i['review'], 'reply' => 'HACKED-REPLY']],
            'item status toggle' => ['GET', '/item/status?id='.$i['item'].'&status=0', []],
            'item organic toggle' => ['GET', '/item/organic?id='.$i['item'].'&organic=1', []],
            'item recommended toggle' => ['GET', '/item/recommended?id='.$i['item'].'&status=1', []],
            'banner status toggle' => ['GET', '/banner/status?id='.$i['banner'].'&status=0', []],
            'addon status toggle' => ['GET', '/addon/status?id='.$i['addon'].'&status=0', []],
            'delete schedule' => ['DELETE', '/schedule/'.$i['schedule'], []],
            'delete withdraw method' => ['DELETE', '/withdraw-method/delete', ['id' => $i['withdraw_method']]],
            'default withdraw method' => ['POST', '/withdraw-method/make-default', ['id' => $i['withdraw_method'], 'is_default' => 1]],
        ];
    }

    public function test_vendor_b_cannot_modify_or_delete_vendor_a_records(): void
    {
        $before = $this->snapshot();
        $changedBy = [];

        foreach ($this->attacks() as $label => [$method, $uri, $body]) {
            $response = $this->json($method, self::API.$uri, $body, $this->as($this->b));
            $after = $this->snapshot();

            if ($after !== $before) {
                $diff = array_keys(array_filter($after, fn ($v, $k) => $v !== $before[$k], ARRAY_FILTER_USE_BOTH));
                $changedBy[] = "{$label} -> HTTP {$response->getStatusCode()} changed ".implode(',', $diff);
                $before = $after; // keep going so every hole is reported once
            }
        }

        $this->assertSame([], $changedBy, "Vendor B changed vendor A's data:\n".implode("\n", $changedBy));
    }

    public function test_the_owner_can_still_perform_every_protected_operation_on_their_own_records(): void
    {
        $i = $this->ids;
        $own = $this->as($this->a);

        $this->json('GET', self::API.'/item/status?id='.$i['item'].'&status=0', [], $own)->assertOk();
        $this->assertSame(0, (int) DB::table('items')->where('id', $i['item'])->value('status'));
        $this->json('GET', self::API.'/item/organic?id='.$i['item'].'&organic=1', [], $own)->assertOk();
        $this->assertSame(1, (int) DB::table('items')->where('id', $i['item'])->value('organic'));
        $this->json('GET', self::API.'/item/recommended?id='.$i['item'].'&status=1', [], $own)->assertOk();
        $this->assertSame(1, (int) DB::table('items')->where('id', $i['item'])->value('recommended'));

        $this->json('GET', self::API.'/banner/status?id='.$i['banner'].'&status=0', [], $own)->assertOk();
        $this->assertSame(0, (int) DB::table('banners')->where('id', $i['banner'])->value('status'));
        $this->json('GET', self::API.'/addon/status?id='.$i['addon'].'&status=0', [], $own)->assertOk();
        $this->assertSame(0, (int) DB::table('add_ons')->where('id', $i['addon'])->value('status'));

        $this->json('PUT', self::API.'/item/reply-update', ['id' => $i['review'], 'reply' => 'Thanks!'], $own)->assertOk();
        $this->assertSame('Thanks!', DB::table('reviews')->where('id', $i['review'])->value('reply'));

        $this->json('POST', self::API.'/withdraw-method/make-default', ['id' => $i['withdraw_method'], 'is_default' => 1], $own)->assertOk();
        $this->assertSame(1, (int) DB::table('disbursement_withdrawal_methods')->where('id', $i['withdraw_method'])->value('is_default'));

        // deletes last
        $this->json('POST', self::API.'/coupon/delete', ['coupon_id' => $i['coupon']], $own)->assertOk();
        $this->assertNull(DB::table('coupons')->where('id', $i['coupon'])->first());
        $this->json('DELETE', self::API.'/banner/delete', ['id' => $i['banner']], $own)->assertOk();
        $this->assertNull(DB::table('banners')->where('id', $i['banner'])->first());
        $this->json('DELETE', self::API.'/addon/delete', ['id' => $i['addon']], $own)->assertOk();
        $this->assertNull(DB::table('add_ons')->where('id', $i['addon'])->first());
        $this->json('DELETE', self::API.'/withdraw-method/delete', ['id' => $i['withdraw_method']], $own)->assertOk();
        $this->assertNull(DB::table('disbursement_withdrawal_methods')->where('id', $i['withdraw_method'])->first());
    }

    // ---------------------------------------------------------------- own drivers

    public function test_vendor_b_cannot_manage_vendor_a_drivers(): void
    {
        $id = $this->ids['driver'];
        $before = $this->snapshot();

        $this->json('GET', self::API.'/delivery-man/status?delivery_man_id='.$id.'&status=0', [], $this->as($this->b));
        $this->json('POST', self::API.'/delivery-man/update/'.$id, ['f_name' => 'HACKED', 'l_name' => 'x'], $this->as($this->b));
        $this->json('DELETE', self::API.'/delivery-man/delete', ['delivery_man_id' => $id, 'id' => $id], $this->as($this->b));

        $this->assertSame($before, $this->snapshot(), "Vendor B changed vendor A's driver");
        $this->assertSame(1, (int) DB::table('delivery_men')->where('id', $id)->value('status'));
        $this->assertNotNull(DB::table('delivery_men')->where('id', $id)->first());
    }

    public function test_vendor_can_manage_their_own_drivers(): void
    {
        $id = $this->ids['driver'];

        $this->json('GET', self::API.'/delivery-man/status?delivery_man_id='.$id.'&status=0', [], $this->as($this->a))->assertOk();
        $this->assertSame(0, (int) DB::table('delivery_men')->where('id', $id)->value('status'));
        $this->assertNull(DB::table('delivery_men')->where('id', $id)->value('auth_token'));
    }

    // -------------------------------------------------------------- disclosure

    public function test_vendor_b_cannot_read_vendor_a_records(): void
    {
        $reads = [
            'item details' => '/item/details/'.$this->ids['item'],
            'requested item' => '/item/requested/item/view/'.$this->ids['item'],
            'banner edit' => '/banner/edit/'.$this->ids['banner'],
            'advertisement details' => '/advertisement/details/'.$this->ids['advertisement'],
            'order details' => '/order-details?order_id='.$this->ids['order'],
            'order' => '/order?order_id='.$this->ids['order'],
            'coupon view' => '/coupon/view?coupon_id='.$this->ids['coupon'],
            'coupon view raw' => '/coupon/view-without-translate?coupon_id='.$this->ids['coupon'],
            'driver preview' => '/delivery-man/preview?id='.$this->ids['driver'].'&delivery_man_id='.$this->ids['driver'],
        ];
        $markers = ['A-SECRET-ITEM', 'A-SECRET-BANNER', 'A-SECRET-AD', 'A-SECRET-COUPON', 'A-SECRET-DRIVER', 'ACODE1'];

        $leaks = [];
        foreach ($reads as $label => $uri) {
            $response = $this->json('GET', self::API.$uri, [], $this->as($this->b));
            $body = $response->getContent();
            foreach ($markers as $marker) {
                if (str_contains($body, $marker)) {
                    $leaks[] = "{$label} -> HTTP {$response->getStatusCode()} leaked {$marker}";
                }
            }
            if ($label === 'order details' || $label === 'order') {
                if ($response->getStatusCode() === 200 && str_contains($body, '"order_amount"') && str_contains($body, (string) $this->ids['order'])) {
                    $leaks[] = "{$label} -> HTTP 200 returned order {$this->ids['order']} to the wrong store";
                }
            }
        }

        $this->assertSame([], $leaks, "Vendor B read vendor A's data:\n".implode("\n", $leaks));
    }

    public function test_lists_only_contain_the_vendors_own_records(): void
    {
        foreach (['/item/list?limit=50&offset=1&type=all' => 'A-SECRET-ITEM', '/coupon/list?limit=50&offset=1' => 'A-SECRET-COUPON', '/banner' => 'A-SECRET-BANNER', '/advertisement?limit=50&offset=1' => 'A-SECRET-AD', '/delivery-man/list' => 'A-SECRET-DRIVER', '/all-orders?limit=50&offset=1' => (string) '"id":'.$this->ids['order'].','] as $uri => $marker) {
            $body = $this->json('GET', self::API.$uri, [], $this->as($this->b))->getContent();
            $this->assertStringNotContainsString($marker, $body, "{$uri} exposed vendor A's data to vendor B");
        }
    }

    public function test_removing_someone_elses_record_is_a_404_not_a_false_success(): void
    {
        $this->json('DELETE', self::API.'/advertisement/delete/'.$this->ids['advertisement'], [], $this->as($this->b))
            ->assertNotFound();
        $this->assertNotNull(DB::table('advertisements')->where('id', $this->ids['advertisement'])->first());

        $this->json('DELETE', self::API.'/advertisement/delete/'.$this->ids['advertisement'], [], $this->as($this->a))
            ->assertOk();
        $this->assertNull(DB::table('advertisements')->where('id', $this->ids['advertisement'])->first());
    }

    // ----------------------------------------------------------------- account

    public function test_inactive_vendor_or_store_is_rejected(): void
    {
        $this->json('GET', self::API.'/current-orders', [], $this->as($this->a))->assertOk();

        DB::table('vendors')->where('id', $this->a->vendor)->update(['status' => 0]);
        $this->json('GET', self::API.'/current-orders', [], $this->as($this->a))->assertStatus(403);

        DB::table('vendors')->where('id', $this->a->vendor)->update(['status' => 1]);
        DB::table('stores')->where('id', $this->a->store)->update(['status' => 0]);
        $this->json('GET', self::API.'/current-orders', [], $this->as($this->a))->assertStatus(403);
    }

    public function test_missing_or_unknown_credentials_are_rejected(): void
    {
        $this->json('GET', self::API.'/current-orders')->assertUnauthorized();
        $this->json('GET', self::API.'/current-orders', [], ['Authorization' => 'Bearer nope', 'vendorType' => 'owner'])->assertUnauthorized();
        $this->json('GET', self::API.'/current-orders', [], ['Authorization' => 'Bearer '.$this->a->token])->assertStatus(403);
    }
}
