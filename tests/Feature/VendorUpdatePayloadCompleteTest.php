<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InsertsRows;
use Tests\TestCase;

/**
 * Payload-complete tenant isolation for the vendor UPDATE endpoints.
 *
 * VendorTenantIsolationTest attacks with thin payloads. Several update handlers validate (or even
 * json_decode / count) their body before they look up the record, so a thin attack can be rejected
 * -- or crash -- before the ownership check ever runs, and "nothing changed" proves nothing.
 *
 * Here each case sends a COMPLETE, valid payload. Vendor A must be able to apply it (so the payload
 * is demonstrably good, and a "404 for everyone" bug cannot pass), and the identical payload from
 * vendor B must change nothing and must be refused for ownership, not for being malformed.
 */
class VendorUpdatePayloadCompleteTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRows;

    private const API = '/api/v1/vendor';

    private object $a;
    private object $b;
    private array $ids;
    private int $moduleId;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        // Every real store belongs to a module; the item handler reads its module_type.
        $this->moduleId = $this->insertRow('modules', ['module_name' => 'VPC Module '.uniqid(), 'module_type' => 'ecommerce', 'status' => 1, 'theme_id' => 1, 'stores_count' => 0, 'all_zone_service' => 1]);
        $this->a = $this->tenant('a');
        $this->b = $this->tenant('b');
        $s = $this->a->store;

        $this->ids = [
            'order' => $this->insertRow('orders', ['store_id' => $s, 'user_id' => 1, 'order_status' => 'pending', 'payment_status' => 'unpaid', 'order_type' => 'delivery', 'order_amount' => 30, 'is_guest' => 0, 'module_id' => 1]),
            'item' => $this->insertRow('items', ['store_id' => $s, 'name' => 'ORIG-ITEM', 'status' => 1, 'price' => 10, 'module_id' => 1, 'images' => '[]', 'organic' => 0, 'recommended' => 0, 'category_ids' => '[]', 'variations' => '[]', 'add_ons' => '[]', 'attributes' => '[]', 'choice_options' => '[]']),
            'coupon' => $this->insertRow('coupons', ['store_id' => $s, 'created_by' => 'vendor', 'title' => 'ORIG-COUPON', 'status' => 1, 'code' => 'ORIGCODE', 'coupon_type' => 'default', 'discount' => 5, 'discount_type' => 'amount']),
            'banner' => $this->insertRow('banners', ['data' => (string) $s, 'title' => 'ORIG-BANNER', 'status' => 1, 'type' => 'store_wise', 'created_by' => 'store']),
            'advertisement' => $this->insertRow('advertisements', ['store_id' => $s, 'title' => 'ORIG-AD', 'description' => 'orig', 'status' => 'approved', 'add_type' => 'store_promotion']),
            'addon' => $this->insertRow('add_ons', ['store_id' => $s, 'name' => 'ORIG-ADDON', 'price' => 2, 'status' => 1, 'addon_category_id' => 1]),
            'driver' => $this->insertRow('delivery_men', [
                'store_id' => $s, 'f_name' => 'ORIG-DRIVER', 'l_name' => 'x', 'phone' => '+1555'.random_int(1000000, 9999999), 'email' => 'orig-drv-'.uniqid().'@urbangoodz.test',
                'password' => 'x', 'status' => 1, 'application_status' => 'approved', 'type' => 'restaurant_wise', 'identity_image' => '[]',
            ]),
        ];
    }

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    private function tenant(string $tag): object
    {
        $token = 'vpc-'.$tag.'-'.bin2hex(random_bytes(12));
        $vendor = $this->insertRow('vendors', [
            'f_name' => 'V', 'l_name' => $tag, 'phone' => '+1555'.random_int(1000000, 9999999), 'email' => "vpc-{$tag}-".uniqid().'@urbangoodz.test',
            'password' => bcrypt('x'), 'auth_token' => $token, 'status' => 1,
        ]);
        $store = $this->insertRow('stores', [
            'name' => 'Store '.$tag, 'vendor_id' => $vendor, 'status' => 1, 'module_id' => $this->moduleId, 'zone_id' => 1,
            'phone' => '+1555'.random_int(1000000, 9999999), 'email' => "vpc-store-{$tag}-".uniqid().'@urbangoodz.test', 'item_section' => 1, 'self_delivery_system' => 1,
        ]);

        return (object) ['vendor' => $vendor, 'store' => $store, 'token' => $token];
    }

    private function as(object $t): array
    {
        return ['Authorization' => 'Bearer '.$t->token, 'vendorType' => 'owner', 'Accept' => 'application/json'];
    }

    /** One complete, valid request per update endpoint, plus how to observe that it took effect. */
    private function cases(): array
    {
        $i = $this->ids;
        $tr = fn (array $rows) => json_encode($rows);

        return [
            'item' => [
                'PUT', '/item/update',
                ['id' => $i['item'], 'category_id' => 1, 'price' => 77.5, 'discount' => 0, 'discount_type' => 'percent', 'veg' => 0,
                 'translations' => $tr([['locale' => 'en', 'key' => 'name', 'value' => 'NEW-ITEM'], ['locale' => 'en', 'key' => 'description', 'value' => 'new desc']])],
                fn () => (float) DB::table('items')->where('id', $i['item'])->value('price'), 77.5,
            ],
            'coupon' => [
                'POST', '/coupon/update',
                ['coupon_id' => $i['coupon'], 'code' => 'NEWCODE', 'start_date' => '2030-01-01', 'expire_date' => '2030-12-31', 'coupon_type' => 'default',
                 'discount' => 9, 'discount_type' => 'amount', 'limit' => 5, 'translations' => $tr([['locale' => 'en', 'key' => 'title', 'value' => 'NEW-COUPON']])],
                fn () => DB::table('coupons')->where('id', $i['coupon'])->value('code'), 'NEWCODE',
            ],
            'banner' => [
                'PUT', '/banner/update',
                ['id' => $i['banner'], 'default_link' => 'https://example.test/new', 'translations' => $tr([['locale' => 'en', 'key' => 'title', 'value' => 'NEW-BANNER']])],
                fn () => DB::table('banners')->where('id', $i['banner'])->value('title'), 'NEW-BANNER',
            ],
            'advertisement' => [
                'POST', '/advertisement/update/'.$i['advertisement'],
                ['id' => $i['advertisement'], 'dates' => '01/01/2030 - 01/31/2030', 'advertisement_type' => 'store_promotion', 'title' => 'NEW-AD', 'description' => 'new',
                 'translations' => $tr([['locale' => 'en', 'key' => 'title', 'value' => 'NEW-AD'], ['locale' => 'en', 'key' => 'description', 'value' => 'new description']])],
                fn () => DB::table('advertisements')->where('id', $i['advertisement'])->value('title'), 'NEW-AD',
            ],
            'addon' => [
                'PUT', '/addon/update',
                ['id' => $i['addon'], 'name' => 'NEW-ADDON', 'price' => 4.5, 'addon_category_id' => 1,
                 'translations' => [['locale' => 'en', 'key' => 'name', 'value' => 'NEW-ADDON']]],
                fn () => DB::table('add_ons')->where('id', $i['addon'])->value('name'), 'NEW-ADDON',
            ],
            'driver' => [
                'POST', '/delivery-man/update/'.$i['driver'],
                ['f_name' => 'NEW-DRIVER', 'l_name' => 'y', 'email' => 'new-drv-'.uniqid().'@urbangoodz.test', 'phone' => '+1555'.random_int(1000000, 9999999)],
                fn () => DB::table('delivery_men')->where('id', $i['driver'])->value('f_name'), 'NEW-DRIVER',
            ],
            'order status' => [
                'PUT', '/update-order-status',
                ['order_id' => $i['order'], 'status' => 'confirmed'],
                fn () => DB::table('orders')->where('id', $i['order'])->value('order_status'), 'confirmed',
            ],
        ];
    }

    public function test_the_owner_can_apply_every_complete_update_payload(): void
    {
        $failures = [];
        foreach ($this->cases() as $label => [$method, $uri, $body, $observe, $expected]) {
            $res = $this->json($method, self::API.$uri, $body, $this->as($this->a));
            if ($observe() != $expected) {
                $failures[] = "{$label}: HTTP {$res->getStatusCode()} did not apply -> ".substr($res->getContent(), 0, 300);
            }
        }

        $this->assertSame([], $failures, "The payload must be good for the owner, or the attacker test below proves nothing:\n".implode("\n", $failures));
    }

    public function test_the_same_complete_payload_from_another_vendor_changes_nothing_and_is_refused_for_ownership(): void
    {
        $leaks = [];
        foreach ($this->cases() as $label => [$method, $uri, $body, $observe, $expected]) {
            $before = $observe();
            $res = $this->json($method, self::API.$uri, $body, $this->as($this->b));

            if ($observe() == $expected) {
                $leaks[] = "{$label}: vendor B applied vendor A's record (HTTP {$res->getStatusCode()})";
            }
            $this->assertSame($before, $observe(), "{$label} changed");
            // Refused as not-theirs: a not-found / forbidden, never a 200 and never a server error.
            if (! in_array($res->getStatusCode(), [403, 404], true)) {
                $leaks[] = "{$label}: expected 403/404 for the wrong tenant, got {$res->getStatusCode()}: ".substr($res->getContent(), 0, 200);
            }
        }

        $this->assertSame([], $leaks, implode("\n", $leaks));
    }

    public function test_a_body_without_translations_is_a_validation_error_never_a_server_error(): void
    {
        $i = $this->ids;
        $crashes = [];

        foreach ([
            'item store' => ['POST', '/item/store', ['category_id' => 1, 'price' => 5]],
            'item update' => ['PUT', '/item/update', ['id' => $i['item'], 'category_id' => 1, 'price' => 5]],
            'coupon store' => ['POST', '/coupon/store', ['code' => 'ZZTEST', 'start_date' => '2030-01-01', 'expire_date' => '2030-12-31', 'coupon_type' => 'default', 'discount' => 5]],
            'coupon update' => ['POST', '/coupon/update', ['coupon_id' => $i['coupon'], 'code' => 'ZZTEST2', 'start_date' => '2030-01-01', 'expire_date' => '2030-12-31', 'coupon_type' => 'default', 'discount' => 5]],
            'addon store' => ['POST', '/addon/store', ['name' => 'x', 'price' => 1, 'addon_category_id' => 1]],
            'addon update' => ['PUT', '/addon/update', ['id' => $i['addon'], 'name' => 'x', 'price' => 1, 'addon_category_id' => 1]],
            'banner store' => ['POST', '/banner/store', []],
            'banner update' => ['PUT', '/banner/update', ['id' => $i['banner']]],
            'advertisement update' => ['POST', '/advertisement/update/'.$i['advertisement'], ['id' => $i['advertisement'], 'dates' => '01/01/2030 - 01/31/2030', 'advertisement_type' => 'store_promotion']],
        ] as $label => [$method, $uri, $body]) {
            $res = $this->json($method, self::API.$uri, $body, $this->as($this->a));
            if ($res->getStatusCode() >= 500) {
                $crashes[] = "{$label}: HTTP {$res->getStatusCode()} ".substr(str_replace("\n", ' ', $res->getContent()), 0, 140);
            }
        }

        $this->assertSame([], $crashes, "Malformed bodies must be rejected cleanly:\n".implode("\n", $crashes));
    }
}
