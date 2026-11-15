<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ProductLinkParser;
use App\Services\SafeUrlFetcher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Paste a product link, get the item filled in, without ever letting a customer point the server inward. */
class OrderAnywhereLinkPreviewTest extends TestCase
{
    use DatabaseTransactions;

    private function fetcher(array $dns = []): SafeUrlFetcher
    {
        return new SafeUrlFetcher(fn (string $host) => $dns[$host] ?? ['93.184.216.34']);
    }

    #[DataProvider('unsafeUrls')]
    public function test_addresses_that_would_reach_inside_our_network_are_refused(string $url): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->fetcher(['internal.example' => ['10.0.0.5'], 'meta.example' => ['169.254.169.254'], 'rebind.example' => ['93.184.216.34', '127.0.0.1']])->assertSafe($url);
    }

    public static function unsafeUrls(): array
    {
        return [
            'loopback ip' => ['http://127.0.0.1/admin'],
            'localhost' => ['http://localhost/'],
            'private 10.x' => ['http://10.1.2.3/'],
            'private 192.168' => ['https://192.168.0.1/'],
            'cloud metadata' => ['http://169.254.169.254/latest/meta-data/'],
            'ipv6 loopback' => ['http://[::1]/'],
            'name that resolves to a private ip' => ['http://internal.example/'],
            'name that resolves to cloud metadata' => ['https://meta.example/'],
            'one good and one private answer (rebinding)' => ['https://rebind.example/'],
            'file scheme' => ['file:///etc/passwd'],
            'ftp scheme' => ['ftp://example.com/'],
            'odd port' => ['http://example.com:22/'],
            'credentials in the url' => ['http://user:pass@example.com/'],
            '.internal name' => ['http://db.internal/'],
            'not a url' => ['hello'],
        ];
    }

    public function test_a_normal_public_site_is_allowed(): void
    {
        $t = $this->fetcher()->assertSafe('https://shop.example.com/item/1?x=2');
        $this->assertSame(['https', 'shop.example.com', 443], [$t['scheme'], $t['host'], $t['port']]);
    }

    public function test_a_redirect_into_the_private_network_is_blocked(): void
    {
        Http::fake(['shop.example.com/*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data/'])]);

        $this->expectException(\InvalidArgumentException::class);
        $this->fetcher()->fetch('https://shop.example.com/go');
    }

    public function test_redirects_are_followed_a_few_times_but_not_forever(): void
    {
        Http::fake(['a.example.com/*' => Http::response('', 302, ['Location' => 'https://a.example.com/loop'])]);

        $this->expectException(\RuntimeException::class);
        $this->fetcher()->fetch('https://a.example.com/loop');
    }

    public function test_non_html_content_is_rejected(): void
    {
        Http::fake(['*' => Http::response('{"x":1}', 200, ['Content-Type' => 'application/json'])]);

        $this->expectException(\RuntimeException::class);
        $this->fetcher()->fetch('https://shop.example.com/api');
    }

    public function test_the_parser_reads_schema_org_product_data(): void
    {
        $html = '<html><head><script type="application/ld+json">{"@context":"https://schema.org","@graph":[{"@type":"WebSite"},'
            .'{"@type":"Product","name":"Trail Runner 2 &amp; Sons","image":["/img/a.jpg"],"offers":{"@type":"Offer","price":"1,299.99","priceCurrency":"usd"}}]}</script></head></html>';

        $r = (new ProductLinkParser)->parse($html, 'https://shop.example.com/p/1');

        $this->assertSame('Trail Runner 2 & Sons', $r['title']);
        $this->assertSame(1299.99, $r['price']);
        $this->assertSame('USD', $r['currency']);
        $this->assertSame('https://shop.example.com/img/a.jpg', $r['image']);
        $this->assertSame('json_ld', $r['source']);
    }

    public function test_the_parser_falls_back_to_open_graph_then_the_title(): void
    {
        $og = '<html><head><meta property="og:title" content="Blue Hoodie"><meta property="og:image" content="//cdn.x.com/h.png">'
            .'<meta property="product:price:amount" content="59,00"><meta property="product:price:currency" content="EUR"><meta property="og:site_name" content="Hoodie Co"></head></html>';
        $r = (new ProductLinkParser)->parse($og, 'https://hoodie.co/p');
        $this->assertSame(['Blue Hoodie', 59.0, 'EUR', 'https://cdn.x.com/h.png', 'Hoodie Co', 'opengraph'], [$r['title'], $r['price'], $r['currency'], $r['image'], $r['site_name'], $r['source']]);

        $t = (new ProductLinkParser)->parse('<html><head><title>  Some   Page </title></head></html>', 'https://x.com');
        $this->assertSame(['Some Page', null, 'title'], [$t['title'], $t['price'], $t['source']]);
        $this->assertSame('none', (new ProductLinkParser)->parse('', 'https://x.com')['source']);
    }

    public function test_absurd_or_unsafe_values_are_dropped(): void
    {
        $html = '<html><head><meta property="og:title" content="<script>alert(1)</script>Cap"><meta property="product:price:amount" content="99999999">'
            .'<meta property="og:image" content="javascript:alert(1)"></head></html>';

        $r = (new ProductLinkParser)->parse($html, 'https://x.com');

        $this->assertStringNotContainsString('<', (string) $r['title']);
        $this->assertNull($r['price'], 'a price that cannot be real is not offered');
        $this->assertNull($r['image'], 'only http(s) images');
    }

    private function asCustomer(): User
    {
        return User::create(['f_name' => 'L', 'l_name' => 'P', 'email' => 'lp-'.uniqid().'@urbangoodz.test', 'phone' => '+1555'.random_int(1000000, 9999999), 'password' => bcrypt('x'), 'is_active' => 1]);
    }

    public function test_the_endpoint_needs_a_signed_in_customer_and_a_link(): void
    {
        $this->postJson('/api/v1/order-anywhere/requests/link-preview', ['url' => 'https://a.com'])->assertUnauthorized();
        $this->actingAs($this->asCustomer(), 'api')->postJson('/api/v1/order-anywhere/requests/link-preview', [])->assertStatus(422);
    }

    public function test_the_endpoint_refuses_internal_addresses_with_a_plain_message(): void
    {
        $r = $this->actingAs($this->asCustomer(), 'api')->postJson('/api/v1/order-anywhere/requests/link-preview', ['url' => 'http://169.254.169.254/latest/meta-data/']);

        $r->assertStatus(422)->assertJsonPath('success', false);
        $this->assertStringNotContainsString('169.254', (string) $r->json('message'), 'do not echo back or explain the internal address');
    }

    public function test_a_real_looking_page_fills_in_the_item_and_an_estimate_when_a_markup_is_configured(): void
    {
        config(['urban_goodz_payments.order_anywhere.markup_percent' => 15]);
        Http::fake(['*' => Http::response('<html><head><meta property="og:title" content="Blue Hoodie"><meta property="product:price:amount" content="40.00"></head></html>', 200, ['Content-Type' => 'text/html'])]);
        // The default resolver would hit real DNS: pin the host to a public address.
        $this->app->bind(SafeUrlFetcher::class, fn () => new SafeUrlFetcher(fn ($h) => ['93.184.216.34']));

        $r = $this->actingAs($this->asCustomer(), 'api')->postJson('/api/v1/order-anywhere/requests/link-preview', ['url' => 'https://www.hoodie.example/p/9']);

        $r->assertOk()->assertJsonPath('data.item_title', 'Blue Hoodie')->assertJsonPath('data.item_price', 40)
            ->assertJsonPath('data.merchant_host', 'hoodie.example')->assertJsonPath('data.found', true);
        $this->assertEquals(40.0, $r->json('data.estimate.item_subtotal'));
        $this->assertEquals(6.0, $r->json('data.estimate.service_fee'), '15% markup on 40.00');
    }

    public function test_without_a_markup_the_item_is_shown_but_no_total_is_invented(): void
    {
        config(['urban_goodz_payments.order_anywhere.markup_percent' => null]);
        Http::fake(['*' => Http::response('<html><head><meta property="og:title" content="Cap"><meta property="product:price:amount" content="20"></head></html>', 200, ['Content-Type' => 'text/html'])]);
        $this->app->bind(SafeUrlFetcher::class, fn () => new SafeUrlFetcher(fn ($h) => ['93.184.216.34']));

        $r = $this->actingAs($this->asCustomer(), 'api')->postJson('/api/v1/order-anywhere/requests/link-preview', ['url' => 'https://cap.example/p']);

        $r->assertOk()->assertJsonPath('data.item_price', 20)->assertJsonPath('data.estimate', null);
    }
}
