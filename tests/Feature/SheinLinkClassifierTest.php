<?php

namespace Tests\Feature;

use App\Services\SheinLinkClassifier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SheinLinkClassifierTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.cart_quotes.shein_hosts' => ['shein.com', 'shein.top']]);
        Cache::flush();
        Http::preventStrayRequests();
    }

    /** @dataProvider directLinks */
    public function test_recognizes_direct_links_without_network_requests(string $url, string $type): void
    {
        $this->assertSame($type, app(SheinLinkClassifier::class)->classify($url));
        Http::assertNothingSent();
    }

    public static function directLinks(): array
    {
        return [
            ['https://m.shein.com/cart/share/landing?group_id=123', 'cart'],
            ['https://api-shein.shein.com/h5/sharejump/appsharejump?cart=123', 'cart'],
            ['https://www.shein.com/cart', 'cart'],
            ['https://m.shein.com/us/cart/share/landing?group_id=123', 'cart'],
            ['https://us.shein.com/Red-Dress-p-12345.html', 'product'],
            ['https://m.shein.com/goods?goods_id=12345', 'product'],
            ['https://shein.com/Red-Dress-p-12345.html?cart=123', 'product'],
            ['https://shein.com/blog/cart?cart_share=1&group_id=123', 'unknown'],
            ['https://shein.com.evil.test/cart', 'unknown'],
            ['https://user@shein.com/cart', 'unknown'],
            ['https://shein.com:8443/cart', 'unknown'],
        ];
    }

    /** @dataProvider shareDestinations */
    public function test_short_links_use_the_embedded_destination_and_cache_the_result(string $destination, string $type): void
    {
        $url = 'https://onelink.shein.com/54/short';
        Http::fake([$url => Http::response('<input id="deeplink" value="'.e($destination).'">')]);
        $this->assertSame($type, app(SheinLinkClassifier::class)->classify($url));
        $this->assertSame($type, app(SheinLinkClassifier::class)->classify($url));
        Http::assertSentCount(1);
    }

    public static function shareDestinations(): array
    {
        return [
            ['sheinlink://applink/share_receiver?data='.rawurlencode('{"group_id":"123","cart_share":"1"}'), 'cart'],
            ['sheinlink://applink/goods?data='.rawurlencode('{"goods_id":"456"}'), 'product'],
        ];
    }

    public function test_long_share_links_use_share_info_instead_of_paths_in_the_script_library(): void
    {
        $url = 'https://api-shein.shein.com/h5/sharejump/appjump?link=example';
        Http::fake([$url => Http::response('<script>var library="sheinlink://applink/share_receiver";'
            .'var shareInfo = {"share_type":"goods","id":"123"};</script>')]);
        $this->assertSame('product', app(SheinLinkClassifier::class)->classify($url));
    }

    public function test_follows_safe_redirects_and_html_fallbacks(): void
    {
        Http::fake([
            'https://shein.top/short' => Http::response('', 302, ['Location' => 'https://onelink.shein.com/55/cart']),
            'https://onelink.shein.com/55/cart' => Http::response('<input id="url" value="https://api-shein.shein.com/h5/sharejump/appjump?link=cart">'),
            'https://api-shein.shein.com/h5/sharejump/appjump?link=cart' => Http::response('<script>var shareInfo = {"share_type":"cart","cart_share":1,"id":"123"};</script>'),
        ]);
        $this->assertSame('cart', app(SheinLinkClassifier::class)->classify('https://shein.top/short'));
        Http::assertSentCount(3);
    }

    /** @dataProvider unapprovedDestinations */
    public function test_never_follows_redirects_to_unapproved_destinations(string $destination): void
    {
        Http::fake(['https://onelink.shein.com/short' => Http::response('', 302, ['Location' => $destination])]);
        $this->assertSame('unknown', app(SheinLinkClassifier::class)->classify('https://onelink.shein.com/short'));
        Http::assertSentCount(1);
    }

    public static function unapprovedDestinations(): array
    {
        return [['http://127.0.0.1/cart'], ['https://shein.com.evil.test/cart'],
            ['https://user@shein.com/cart'], ['https://shein.com:8443/cart']];
    }

    public function test_resolution_failure_is_not_cached_as_a_product_or_a_cart(): void
    {
        $url = 'https://onelink.shein.com/short';
        Http::fake([$url => Http::sequence()->push('', 503)
            ->push('<script>var shareInfo = {"share_type":"cart","cart_share":1,"id":"123"};</script>')]);
        $this->assertSame('unknown', app(SheinLinkClassifier::class)->classify($url));
        $this->assertSame('cart', app(SheinLinkClassifier::class)->classify($url));
        Http::assertSentCount(2);
    }

    public function test_redirect_loops_and_oversized_pages_are_not_submitted_as_carts(): void
    {
        Http::fake([
            'https://onelink.shein.com/loop' => Http::response('', 302, ['Location' => '/loop']),
            'https://onelink.shein.com/large' => Http::response(str_repeat('x', 262145)),
        ]);
        $this->assertSame('unknown', app(SheinLinkClassifier::class)->classify('https://onelink.shein.com/loop'));
        $this->assertSame('unknown', app(SheinLinkClassifier::class)->classify('https://onelink.shein.com/large'));
        Http::assertSentCount(2);
    }
}
