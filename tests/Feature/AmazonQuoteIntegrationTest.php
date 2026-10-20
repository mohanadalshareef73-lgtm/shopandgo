<?php

namespace Tests\Feature;

use App\Models\CartQuote;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhatsappMessages;
use App\Services\AmazonQuoteReply;
use App\Services\CartQuoteService;
use App\Services\GeminiService;
use App\Services\QuoteUrlExtractor;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AmazonQuoteIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private string $url = 'https://www.amazon.ae/-/en/Some-Name/dp/B0DF4NM7CY?th=1';

    private string $session = 'd8e21afbe77f4d8bbaed65f2c6c2edf7';

    private array $sent = [];

    private array $sendResults = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.cart_quotes.base_url' => 'https://quotes.test',
            'services.cart_quotes.token' => 'test-api-key',
            'services.cart_quotes.webhook_secret' => 'whsec_test_secret',
            'services.cart_quotes.webhook_key_version' => '1', 'settings.storefronts.amazon' => 'AMZ-AE']);
        Setting::findOrFail(1)->update(['usd_to_lyd_rate' => '7.125', 'shein_pause_on_empty_wallet' => false]);
        // Operators can choose any language for their single saved templates.
        Setting::findOrFail(1)->update(['amazon_messages' => json_decode(file_get_contents(base_path('tests/Fixtures/amazon-messages.json')), true)]);
        Storage::fake('local');
        Http::preventStrayRequests();
        $this->mock(GeminiService::class, fn ($mock) => $mock->shouldReceive('respond')->never());
        $this->mock(WhatsAppService::class, function ($mock) {
            $mock->shouldReceive('sendDocument')->andReturnUsing(function ($recipient, $path, $filename, $caption, $session, $buttons) {
                preg_match('/amazon-quotation-(\d+)\.pdf/', $filename, $match);
                $quote = CartQuote::findOrFail($match[1]);
                $this->assertSame('amazon-quotation-'.$quote->id.'.pdf', $filename);
                $this->assertStringStartsWith('%PDF-', file_get_contents($path));
                $this->assertSame($quote->whatsappReplyButtons(), $buttons);
                $this->assertSame($recipient, $quote->whatsapp_customer_phone);
                $this->assertSame($session, $quote->whatsapp_session_uuid);
                $this->sent[] = [$recipient, $caption, $session];
                return array_shift($this->sendResults) ?? true;
            });
            $mock->shouldReceive('sendMessage')->andReturnUsing(function ($recipient, $message, $session) {
                $this->sent[] = [$recipient, $message, $session];

                return array_shift($this->sendResults) ?? true;
            });
        });
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function incoming(?string $text = null, string $id = 'wamid.amazon', array $context = []): void
    {
        $this->postJson('/api/webhook/whatsapp?token='.config('services.whatsapp.webhook_token'), [
            'entry' => [['id' => $this->session, 'changes' => [['value' => [
                'metadata' => ['display_phone_number' => '218921000000'],
                'messages' => [array_merge(['id' => $id, 'from' => '218911234567', 'type' => 'text',
                    'text' => ['body' => $text ?? 'Price please '.$this->url]], $context)],
            ]]]]],
        ])->assertOk();
    }

    private function pending(): array
    {
        return ['id' => 'amazon-quote', 'status' => 'pending', 'billingStatus' => 'reserved',
            'retailer' => 'amazon', 'storefront' => 'AMZ-AE', 'stage' => 'queued', 'attempts' => 0,
            'deadlineAt' => now()->addMinutes(15)->toIso8601String()];
    }

    private function result(?string $variant = 'Grey / 41 EU', bool $discount = false): array
    {
        return ['id' => 'amazon-quote', 'status' => 'succeeded', 'billingStatus' => 'captured',
            'retailer' => 'amazon', 'storefront' => 'AMZ-AE',
            'fee' => ['amount' => '0.25', 'currency' => 'USD'],
            'result' => ['itemCount' => 1, 'total' => ['amount' => '123.456789', 'currency' => 'LYD'],
                'native' => ['sale' => ['amount' => '10.000000000000000001', 'currency' => 'AED'],
                    'original' => ['amount' => $discount ? '10.000000000000000002' : '10.000000000000000001', 'currency' => 'AED'],
                    'bagSale' => null],
                'usd' => ['sale' => ['amount' => '2.72', 'currency' => 'USD']],
                'items' => [['name' => 'Test shoes', 'variant' => $variant, 'qty' => 1, 'qtySource' => 'assumed_one',
                    'availability' => 'available', 'priceSource' => 'product_page',
                    'imageUrl' => 'https://images.test/shoes.jpg', 'notes' => ['ASIN B0DF4NM7CY', 'Sold by Seller']]]]];
    }

    private function signedCallback(string $type = 'quote.succeeded', string $webhookId = 'amazon-event', bool $valid = true, ?array $data = null): void
    {
        // Webhook deliberately has no items: the worker must fetch the quote.
        $body = json_encode(['type' => $type, 'data' => $data ?? ['quoteId' => 'amazon-quote', 'status' => substr($type, 6), 'billingStatus' => $type === 'quote.succeeded' ? 'captured' : 'released', 'errorCode' => null]]);
        $timestamp = (string) now()->timestamp;
        $this->call('POST', '/hooks/cart-quotes', [], [], [], [
            'HTTP_WEBHOOK_ID' => $webhookId, 'HTTP_WEBHOOK_TIMESTAMP' => $timestamp,
            'HTTP_WEBHOOK_KEY_VERSION' => '1', 'CONTENT_TYPE' => 'application/json',
            'HTTP_WEBHOOK_SIGNATURE' => 'v1='.hash_hmac('sha256', $timestamp.'.'.$body, $valid ? 'whsec_test_secret' : 'wrong'),
        ], $body)->assertStatus($valid ? 200 : 401);
    }

    private function runWorker(): void
    {
        $this->artisan('cart-quotes:process')->assertExitCode(0);
    }

    public static function amazonLinks(): array
    {
        $links = [];
        foreach (['amazon.ae', 'www.amazon.ae', 'amazon.sa', 'www.amazon.sa', 'amazon.com', 'www.amazon.com'] as $host) {
            foreach (['dp', 'gp/product', 'gp/aw/d'] as $shape) {
                $links[$host.'/'.$shape] = ["https://{$host}/{$shape}/B0DF4NM7CY?th=1&psc=1"];
            }
        }
        foreach (['amzn.eu', 'amzn.to', 'a.co', 'amzn.asia'] as $host) {
            $links[$host] = ["https://{$host}/d/xxxx"];
        }
        foreach (['hz/wishlist/ls/xxx', 'gp/cart/view.html', 's?k=shoes', 'stores/SomeStore/page/xxx'] as $path) {
            $links[$path] = ['https://amazon.ae/'.$path];
        }

        return $links;
    }

    /** @dataProvider amazonLinks */
    public function test_each_host_and_shape_is_submitted_unchanged_for_provider_validation(string $url): void
    {
        Http::fake(['quotes.test/v1/quotes' => Http::response($this->pending(), 202)]);
        $this->incoming('Price please '.$url, str_repeat('x', 120));
        Http::assertSent(fn (Request $request) => $request->url() === 'https://quotes.test/v1/quotes'
            && $request->data() === ['sourceUrl' => $url]
            && $request->hasHeader('Authorization', 'Bearer test-api-key')
            && $request->header('Idempotency-Key') === [str_repeat('x', 120)]);
        Http::assertSentCount(1); // No product-page fetch, wallet call, or local repricing.
        $quote = CartQuote::firstOrFail();
        $this->assertSame('amazon', $quote->retailer);
        $this->assertSame('amazon-quote', $quote->quote_id);
        $this->assertSame('218911234567', $quote->incomingMessage->from);
        $this->assertSame('reserved', $quote->payload['billingStatus']);
        $this->assertSame([['218911234567', 'Got your Amazon link, checking the price now...', $this->session]], $this->sent);
    }

    public function test_unsupported_urls_and_no_link_never_submit_a_quote(): void
    {
        foreach (['hello', 'https://example.com/product', 'https://amazon.ae.evil.test/dp/B0DF4NM7CY',
            'https://fakeamazon.ae/dp/B0DF4NM7CY', 'https://user@amazon.ae/dp/B0DF4NM7CY',
            'https://amazon.ae:8443/dp/B0DF4NM7CY', 'https://amazon.fr.evil.test/dp/B0DF4NM7CY',
            'https://www.amazon.fr.evil.test/dp/B0DF4NM7CY', 'https://user@amazon.fr/dp/B0DF4NM7CY'] as $text) {
            $urls = app(QuoteUrlExtractor::class)->extract($text);
            $this->assertSame([], $urls);
            $incoming = WhatsappMessages::create(['message_id' => uniqid('no-link'), 'from' => '218911234567',
                'message' => $text, 'phone' => '218921000000', 'name' => 'Customer', 'type' => 'text', 'session_uuid' => $this->session, 'timestamp' => now()]);
            app(CartQuoteService::class)->handleIncoming($incoming, $urls);
        }
        $this->assertDatabaseCount('cart_quotes', 0);
        Http::assertNothingSent();
    }

    public static function mixedLinks(): array
    {
        return [[true], [false]];
    }

    /** @dataProvider unsupportedAmazonSites */
    public function test_unsupported_amazon_sites_receive_their_own_editable_reply_without_starting_a_quote(string $url, bool $ar, bool $custom): void
    {
        if ($ar && ! $custom) {
            Setting::findOrFail(1)->update(['amazon_messages' => []]);
        }
        if ($custom) {
            // A general invalid-link customization must not hide this handler.
            Setting::findOrFail(1)->update(['amazon_messages' => [
                'invalid_url' => 'Generic invalid link',
                'unsupported_site' => $ar ? 'اختر موقعاً من {supported_sites}.' : 'Choose a site from {supported_sites}.',
            ]]);
        }
        Http::fake(['quotes.test/v1/quotes' => Http::response(['error' => [
            'code' => 'INVALID_URL', 'message' => 'This Amazon site is not supported. Supported: amazon.ae, amazon.sa, amazon.com.',
        ]], 400)]);
        $this->incoming(($ar ? 'السعر ' : 'Price ').$url);
        $this->incoming(($ar ? 'السعر ' : 'Price ').$url);
        $this->runWorker();
        $expected = $custom
            ? ($ar ? 'اختر موقعاً من amazon.ae, amazon.sa, amazon.com.' : 'Choose a site from amazon.ae, amazon.sa, amazon.com.')
            : ($ar ? 'موقع أمازون هذا غير مدعوم. المواقع المدعومة: amazon.ae, amazon.sa, amazon.com. أرسل رابط المنتج من أحد هذه المواقع.'
                : 'This Amazon site is not supported. Supported: amazon.ae, amazon.sa, amazon.com. Please send a product link from one of these sites.');
        $this->assertSame([['218911234567', $expected, $this->session]], $this->sent);
        $quote = CartQuote::firstOrFail();
        $this->assertSame('amazon', $quote->retailer);
        $this->assertSame('INVALID_URL', $quote->payload['errorCode']);
        $this->assertNull($quote->quote_id);
        $this->assertNull($quote->amazon_pricing);
        $this->assertNull($quote->next_attempt_at);
        $this->assertNotNull($quote->notified_at);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST' && $request['sourceUrl'] === $url);
        Http::assertSentCount(1);
        $this->assertDatabaseCount('notifications', 0);
    }

    public static function unsupportedAmazonSites(): array
    {
        $cases = [];
        foreach (['https://amazon.fr/dp/B0DF4NM7CY', 'https://www.amazon.co.uk/dp/B0DF4NM7CY'] as $url) {
            foreach ([true, false] as $ar) {
                foreach ([true, false] as $custom) {
                    $cases[] = [$url, $ar, $custom];
                }
            }
        }

        return $cases;
    }

    /** @dataProvider accountRegionRestrictions */
    public function test_account_region_restriction_keeps_provider_allowed_regions_and_can_be_customized(bool $custom): void
    {
        $providerMessage = 'This account accepts Amazon links from amazon.sa, amazon.com only.';
        Setting::findOrFail(1)->update(['amazon_messages' => ['region_not_allowed' => '{provider_message}']]);
        if ($custom) {
            Setting::findOrFail(1)->update(['amazon_messages' => ['region_not_allowed' => 'Please check the link region. {provider_message}']]);
        }
        Http::fake(['quotes.test/v1/quotes' => Http::response(['error' => [
            'code' => 'REGION_NOT_ALLOWED', 'message' => $providerMessage,
        ]], 422)]);
        $this->incoming();
        $this->assertSame($custom ? 'Please check the link region. '.$providerMessage : $providerMessage, $this->sent[0][1]);
        $quote = CartQuote::firstOrFail();
        $this->assertSame('REGION_NOT_ALLOWED', $quote->payload['errorCode']);
        $this->assertNull($quote->quote_id);
        $this->assertNotNull($quote->notified_at);
        Http::assertSentCount(1);
    }

    public static function accountRegionRestrictions(): array
    {
        return [[true], [false]];
    }

    public function test_supported_cross_region_link_is_priced_by_the_provider_without_local_rejection_or_storefront_override(): void
    {
        $url = 'https://www.amazon.sa/dp/B0DF4NM7CY';
        Http::fake(['quotes.test/v1/quotes' => Http::response($this->pending(), 202),
            'quotes.test/v1/quotes/amazon-quote' => Http::response($this->result())]);
        $this->incoming('Price '.$url);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST' && $request->data() === ['sourceUrl' => $url]);
        $this->signedCallback();
        $this->runWorker();
        $quote = CartQuote::firstOrFail();
        $this->assertSame('succeeded', $quote->status);
        $this->assertSame('AMZ-AE', $quote->payload['storefront']);
        $this->assertSame('captured', $quote->payload['billingStatus']);
        $this->assertStringContainsString('Price: 126 LYD', $this->sent[1][1]);
    }

    public function test_unknown_validation_error_is_not_misreported_as_an_account_region_restriction(): void
    {
        Http::fake(['quotes.test/v1/quotes' => Http::response(['error' => ['code' => 'FUTURE_VALIDATION_ERROR']], 422)]);
        $this->incoming();
        $this->assertSame("We couldn't price this product right now. Please send the link again in a few minutes.", $this->sent[0][1]);
        $this->assertSame('ENGINE_FAILED', CartQuote::firstOrFail()->payload['errorCode']);
    }

    /** @dataProvider mixedLinks */
    public function test_mixed_messages_quote_both_retailers_with_independent_keys(bool $amazonFirst): void
    {
        Setting::find(1)->update(['shein_pause_on_empty_wallet' => false]);
        $shein = 'https://onelink.shein.com/55/cart';
        Http::fake(['quotes.test/v1/quotes' => fn (Request $request) => Http::response(array_replace($this->pending(), [
            'id' => $request['sourceUrl'] === $this->url ? 'amazon-quote' : 'shein-quote',
        ]), 202),
            $shein => Http::response('<input id="deeplink" value="sheinlink://applink/share_receiver?data=%7B%22cart_share%22%3A1%2C%22group_id%22%3A%22123%22%7D">')]);
        $text = 'Price please '.($amazonFirst ? $this->url.' '.$shein : $shein.' '.$this->url);
        $this->incoming($text);
        $this->incoming($text);
        $this->assertDatabaseCount('cart_quotes', 2);
        $posts = Http::recorded(fn (Request $request) => $request->method() === 'POST');
        $this->assertCount(2, $posts);
        $this->assertCount(2, array_unique($posts->map(fn ($pair) => $pair[0]->header('Idempotency-Key')[0])->all()));
        $this->assertSame(['amazon', 'shein'], CartQuote::orderBy('retailer')->pluck('retailer')->all());
        $this->assertCount(1, $this->sent);
        $this->assertStringNotContainsString('separately', $this->sent[0][1]);
    }

    public function test_later_amazon_links_are_submitted_even_if_first_is_rejected(): void
    {
        Http::fake(['quotes.test/v1/quotes' => fn (Request $request) => $request['sourceUrl'] === $this->url
            ? Http::response($this->pending(), 202)
            : Http::response(['error' => ['code' => 'INVALID_URL']], 400)]);
        $text = 'Price please https://amazon.ae/hz/wishlist/ls/xxx '.$this->url;
        $this->incoming($text);
        $this->incoming($text);
        Http::assertSentCount(2);
        $this->assertDatabaseCount('cart_quotes', 2);
        $this->assertSame('submitted', CartQuote::where('source_url', $this->url)->firstOrFail()->status);
        $this->assertCount(1, $this->sent);
        $this->assertStringContainsString('Please send the link of the Amazon product page', $this->sent[0][1]);
    }

    public function test_multiple_amazon_links_get_independent_pdfs_and_buttons_with_one_acknowledgement(): void
    {
        $second = 'https://amazon.sa/dp/B0SECOND01';
        Http::fake(['quotes.test/v1/quotes' => fn (Request $request) => Http::response(array_replace($this->pending(), [
            'id' => $request['sourceUrl'] === $this->url ? 'amazon-one' : 'amazon-two',
        ]), 202), 'quotes.test/v1/quotes/*' => fn (Request $request) => Http::response(array_replace($this->result(), [
            'id' => basename($request->url()),
        ]))]);
        $text = 'Price '.$this->url."\n".$second."\n".$this->url;
        $this->incoming($text);
        $this->incoming($text);
        $this->assertDatabaseCount('cart_quotes', 2);
        $this->assertCount(1, $this->sent);
        $quotes = CartQuote::orderBy('id')->get();
        $this->assertCount(2, $quotes->pluck('provider_idempotency_key')->unique());
        foreach ($quotes as $quote) {
            $this->signedCallback(webhookId: 'success-'.$quote->id, data: ['quoteId' => $quote->quote_id,
                'status' => 'succeeded', 'billingStatus' => 'captured', 'errorCode' => null]);
        }
        $this->runWorker();
        $this->assertCount(3, $this->sent);
        $this->assertSame(2, CartQuote::whereNotNull('notified_at')->count());
        $this->assertCount(2, $quotes->fresh()->pluck('pdf_path')->unique());
        $this->assertNotSame($quotes[0]->whatsappReplyButtons(), $quotes[1]->whatsappReplyButtons());
        foreach ($quotes as $quote) {
            $this->signedCallback(webhookId: 'duplicate-'.$quote->id, data: ['quoteId' => $quote->quote_id,
                'status' => 'succeeded', 'billingStatus' => 'captured', 'errorCode' => null]);
        }
        $this->incoming($text);
        $this->runWorker();
        $this->assertCount(3, $this->sent);
        Http::assertSentCount(4);
    }

    public function test_one_link_capacity_retry_reuses_its_key_without_resubmitting_other_links(): void
    {
        $second = 'https://amazon.ae/dp/B0SECOND01';
        $attempts = [];
        Http::fake(['quotes.test/v1/quotes' => function (Request $request) use ($second, &$attempts) {
            $url = $request['sourceUrl'];
            $attempts[$url][] = $request->header('Idempotency-Key')[0];
            if ($url === $second && count($attempts[$url]) === 1) {
                return Http::response(['error' => ['code' => 'CAPACITY_UNAVAILABLE']], 503, ['Retry-After' => '60']);
            }

            return Http::response(array_replace($this->pending(), ['id' => $url === $second ? 'amazon-two' : 'amazon-one']), 202);
        }]);
        $this->incoming('Price '.$this->url.' '.$second);
        $this->travel(61)->seconds();
        $this->runWorker();
        $this->assertCount(1, $attempts[$this->url]);
        $this->assertCount(2, $attempts[$second]);
        $this->assertSame($attempts[$second][0], $attempts[$second][1]);
        $this->assertNotSame($attempts[$this->url][0], $attempts[$second][0]);
        $this->assertCount(1, $this->sent);
        $this->assertSame(2, CartQuote::whereNotNull('quote_id')->count());
    }

    public function test_duplicate_links_and_message_punctuation_are_normalized_before_submission(): void
    {
        $second = 'https://www.amazon.sa/dp/B0SECOND01?th=1';
        $this->assertSame([$this->url, $second], app(QuoteUrlExtractor::class)->extract(
            $this->url.",\n".$second.') '.$this->url
        ));
    }

    public function test_old_first_link_quote_is_reused_when_remaining_links_are_processed(): void
    {
        $second = 'https://amazon.sa/dp/B0SECOND01';
        $incoming = WhatsappMessages::create(['message_id' => 'legacy-multi-link', 'from' => '218911234567',
            'phone' => '218921000000', 'name' => 'Customer', 'type' => 'text',
            'message' => 'Price '.$this->url.' '.$second, 'session_uuid' => $this->session, 'timestamp' => now()]);
        Http::fake(['quotes.test/v1/quotes' => fn (Request $request) => Http::response(array_replace($this->pending(), [
            'id' => $request['sourceUrl'] === $this->url ? 'amazon-one' : 'amazon-two',
        ]), 202)]);
        $service = app(CartQuoteService::class);
        $service->handleIncoming($incoming, [$this->url]);
        $first = CartQuote::firstOrFail();
        $this->assertSame('legacy-multi-link', $first->provider_idempotency_key);
        $service->handleIncoming($incoming->fresh(), app(QuoteUrlExtractor::class)->extract($incoming->message));
        $this->assertDatabaseCount('cart_quotes', 2);
        $this->assertSame('legacy-multi-link', $first->fresh()->provider_idempotency_key);
        $this->assertSame('amazon-one', $first->fresh()->quote_id);
        $this->assertCount(1, $this->sent);
        Http::assertSentCount(2);
    }

    public static function submitErrors(): array
    {
        return [
            [400, 'INVALID_URL', 'Please send the link of the Amazon product page: open the product, tap Share, and send that link.', false],
            [400, 'IDEMPOTENCY_KEY_REQUIRED', "Thanks! We'll get back to you with the price shortly.", true],
            [402, 'INSUFFICIENT_FUNDS', "Thanks! We'll get back to you with the price shortly.", true],
            [409, 'IDEMPOTENCY_CONFLICT', "Thanks! We'll get back to you with the price shortly.", true],
            [422, 'REGION_NOT_ALLOWED', 'This Amazon link is from a region our account does not allow. Please send a link from an allowed region.', false],
            [400, 'REGION_NOT_ALLOWED', 'This Amazon link is from a region our account does not allow. Please send a link from an allowed region.', false],
            [400, 'REGION_UNVERIFIED', "We couldn't verify this product's region. Please open the product on amazon.ae and send its link again.", false],
            [422, 'REGION_UNVERIFIED', "We couldn't verify this product's region. Please open the product on amazon.ae and send its link again.", false],
            [401, 'UNAUTHORIZED', "Thanks! We'll get back to you with the price shortly.", true],
            [403, 'FORBIDDEN', "Thanks! We'll get back to you with the price shortly.", true],
            [500, 'UNKNOWN', "We couldn't price this product right now. Please send the link again in a few minutes.", false],
        ];
    }

    /** @dataProvider submitErrors */
    public function test_submit_errors_notify_customer_and_alert_staff_when_required(int $status, string $code, string $reply, bool $alert): void
    {
        DB::table('types')->insert(['id' => 2, 'type' => 'موظف']);
        $staff = User::factory()->create(['type_id' => 2]);
        Log::spy();
        Http::fake(['quotes.test/v1/quotes' => Http::response(['error' => ['code' => $code,
            'retryable' => false, 'request_id' => 'request-for-support']], $status)]);
        $this->incoming();
        $this->incoming();
        $this->runWorker();
        Http::assertSentCount(1);
        $this->assertSame([['218911234567', $reply, $this->session]], $this->sent);
        $this->assertSame('failed', CartQuote::first()->status);
        $this->assertNotNull(CartQuote::first()->notified_at);
        $this->assertNull(CartQuote::first()->next_attempt_at);
        $this->assertDatabaseCount('notifications', $alert ? 1 : 0);
        if ($alert) {
            $this->assertDatabaseHas('notif_orgs', ['user_id' => $staff->id]);
        }
        Log::shouldHaveReceived('warning')->with('Cart quote API rejected request', \Mockery::on(
            fn ($context) => $context['request_id'] === 'request-for-support'));
    }

    public function test_invalid_url_submission_returns_customer_facing_provider_message(): void
    {
        Setting::findOrFail(1)->update(['amazon_messages' => ['invalid_url' => '{provider_message}']]);
        $message = 'Amazon wish lists are not supported yet. Send an Amazon product link.';
        Http::fake(['quotes.test/v1/quotes' => Http::response(['error' => ['code' => 'INVALID_URL', 'message' => $message]], 400)]);
        $this->incoming();
        $this->assertSame($message, $this->sent[0][1]);
    }

    public function test_saved_invalid_url_message_overrides_provider_guidance(): void
    {
        Setting::findOrFail(1)->update(['amazon_messages' => ['invalid_url' => 'Please share a product link with our team.']]);
        Http::fake(['quotes.test/v1/quotes' => Http::response(['error' => [
            'code' => 'INVALID_URL', 'message' => 'Provider guidance',
        ]], 400)]);
        $this->incoming();
        $this->assertSame('Please share a product link with our team.', $this->sent[0][1]);
    }

    /** @dataProvider regionSubmissionMessages */
    public function test_region_rejections_use_saved_messages_and_configured_site_once(int $httpStatus, string $code, string $messageStatus, bool $ar): void
    {
        config(['settings.storefronts.amazon' => 'AMZ-SA']);
        Setting::findOrFail(1)->update(['amazon_messages' => [$messageStatus => $ar ? 'أرسل رابطاً من {site}.' : 'Please share a link from {site}.']]);
        Http::fake(['quotes.test/v1/quotes' => Http::response(['error' => [
            'code' => $code, 'message' => 'Provider region error',
        ]], $httpStatus)]);
        $this->incoming(($ar ? 'السعر ' : 'Price ').$this->url);
        $this->incoming(($ar ? 'السعر ' : 'Price ').$this->url);
        $this->runWorker();
        $this->assertSame([['218911234567', $ar ? 'أرسل رابطاً من amazon.sa.' : 'Please share a link from amazon.sa.', $this->session]], $this->sent);
        $quote = CartQuote::firstOrFail();
        $this->assertSame($code, $quote->payload['errorCode']);
        $this->assertNotNull($quote->notified_at);
        $this->assertNull($quote->next_attempt_at);
        Http::assertSentCount(1);
    }

    public static function regionSubmissionMessages(): array
    {
        $cases = [];
        foreach ([400, 422] as $httpStatus) {
            foreach ([true, false] as $ar) {
                $cases[] = [$httpStatus, 'REGION_NOT_ALLOWED', 'region_not_allowed', $ar];
                $cases[] = [$httpStatus, 'REGION_UNVERIFIED', 'region_unverified', $ar];
            }
        }

        return $cases;
    }

    /** @dataProvider customMessageLanguages */
    public function test_saved_messages_are_used_in_acknowledgement_and_successful_pdf_caption(bool $ar): void
    {
        Setting::findOrFail(1)->update(['amazon_messages' => [
            'received' => 'Checking your item',
            'succeeded' => 'Ready: {product_name}, {amount} {currency}{quantity_line}{discount_line}',
            'quantity' => 'Requested units: {quantity}', 'discount' => 'On sale',
        ]]);
        Http::fake(['quotes.test/v1/quotes' => Http::response($this->pending(), 202),
            'quotes.test/v1/quotes/amazon-quote' => Http::response($this->result(null, true))]);
        $this->incoming(($ar ? 'السعر الكمية 3 ' : 'Price qty 3 ').$this->url);
        $this->assertSame('Checking your item', $this->sent[0][1]);
        $this->signedCallback();
        $this->runWorker();
        $this->assertSame("Ready: Test shoes, 379 LYD\nRequested units: 3\nOn sale", $this->sent[1][1]);
    }

    public static function customMessageLanguages(): array
    {
        return [[true], [false]];
    }

    /** @dataProvider customTerminalMessages */
    public function test_saved_terminal_messages_are_delivered_in_the_customers_language(string $status, ?string $code, string $messageStatus, bool $ar): void
    {
        Setting::findOrFail(1)->update(['amazon_messages' => [$messageStatus => 'Custom terminal message {site}']]);
        Http::fake(['quotes.test/v1/quotes' => Http::response($this->pending(), 202)]);
        $this->incoming(($ar ? 'السعر ' : 'Price ').$this->url);
        $this->signedCallback('quote.'.$status, data: ['quoteId' => 'amazon-quote', 'errorCode' => $code, 'billingStatus' => 'released']);
        $this->runWorker();
        $this->assertSame('Custom terminal message amazon.ae', $this->sent[1][1]);
        Http::assertSentCount(1);
    }

    public static function customTerminalMessages(): array
    {
        $cases = [];
        foreach ([true, false] as $ar) {
            $cases[] = ['failed', 'ENGINE_FAILED', 'temporary_problem', $ar];
            $cases[] = ['failed', 'PRODUCT_UNAVAILABLE', 'items_unavailable', $ar];
            $cases[] = ['failed', 'REGION_NOT_ALLOWED', 'region_not_allowed', $ar];
            $cases[] = ['failed', 'REGION_UNVERIFIED', 'region_unverified', $ar];
            $cases[] = ['expired', 'QUOTE_EXPIRED', 'expired', $ar];
        }

        return $cases;
    }

    public function test_capacity_retry_after_and_limit_reuse_exact_message_key(): void
    {
        Http::fake(['quotes.test/v1/quotes' => Http::response(['error' => ['code' => 'CAPACITY_UNAVAILABLE',
            'retryable' => true, 'request_id' => 'capacity-request']], 503, ['Retry-After' => '120'])]);
        $this->incoming();
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->travel(119)->seconds();
            $this->incoming();
            $this->runWorker();
            Http::assertSentCount($attempt);
            $this->travel(1)->seconds();
            $this->runWorker();
            Http::assertSentCount($attempt + 1);
        }
        $this->assertSame(3, CartQuote::first()->capacity_retries);
        $this->assertSame("We couldn't price this product right now. Please send the link again in a few minutes.", $this->sent[0][1]);
        $this->assertNotNull(CartQuote::first()->notified_at);
        foreach (Http::recorded() as [$request]) {
            $this->assertSame(['wamid.amazon'], $request->header('Idempotency-Key'));
        }
        $this->runWorker();
        Http::assertSentCount(4);
    }

    public function test_timeout_and_capacity_resubmissions_accept_same_quote_and_acknowledge_once(): void
    {
        $calls = 0;
        Http::fake(function (Request $request) use (&$calls) {
            $this->assertSame(['wamid.amazon'], $request->header('Idempotency-Key'));
            $this->assertSame(['sourceUrl' => $this->url], $request->data());
            $calls++;
            if ($calls === 1) {
                throw new ConnectionException('Simulated accepted request timeout');
            }
            if ($calls === 2) {
                return Http::response(['error' => ['code' => 'CAPACITY_UNAVAILABLE']], 503, ['Retry-After' => '60']);
            }

            return Http::response($this->pending(), 202);
        });
        $this->incoming();
        $this->incoming();
        $this->travel(31)->seconds();
        $this->runWorker();
        $this->travel(60)->seconds();
        $this->runWorker();
        $this->incoming();
        $this->assertSame(3, $calls);
        $this->assertDatabaseCount('cart_quotes', 1);
        $this->assertCount(1, $this->sent);
        $this->assertSame('amazon-quote', CartQuote::first()->quote_id);
    }

    public static function successReplies(): array
    {
        return [
            [false, 'Grey / 41 EU', false, 1, 'Test shoes (Grey / 41 EU)'."\nPrice: 126 LYD"],
            [false, null, true, 1, 'Test shoes'."\nPrice: 126 LYD\nDiscounted on Amazon right now."],
            [false, null, false, 1, 'Test shoes'."\nPrice: 126 LYD"],
            [true, 'Grey / 41 EU', true, 1, 'Test shoes (Grey / 41 EU)'."\nالسعر: 126 LYD\nعليه خصم في أمازون حالياً."],
            [false, null, false, 3, 'Test shoes'."\nPrice: 379 LYD\nQuantity: 3 (single-product price × quantity, as requested)."],
            [true, null, false, 3, 'Test shoes'."\nالسعر: 379 LYD\nالكمية: 3 (سعر القطعة × الكمية، حسب طلبك)."],
        ];
    }

    /** @dataProvider successReplies */
    public function test_webhook_fetches_result_then_replies_once_with_decimal_total(bool $ar, ?string $variant, bool $discount, int $qty, string $expected): void
    {
        if ($ar) {
            Setting::findOrFail(1)->update(['amazon_messages' => []]);
        }
        Http::fake(['quotes.test/v1/quotes' => Http::response($this->pending(), 202),
            'quotes.test/v1/quotes/amazon-quote' => Http::response($this->result($variant, $discount))]);
        $text = ($ar ? 'السعر لو سمحت'.($qty > 1 ? ' الكمية ٣ ' : ' ') : 'Price please'.($qty > 1 ? ' 3 pieces ' : ' ')).$this->url;
        $this->incoming($text);
        $this->assertSame($ar ? 'وصلنا رابط أمازون، جاري التحقق من السعر...' : 'Got your Amazon link, checking the price now...', $this->sent[0][1]);
        $this->signedCallback(valid: false);
        $this->signedCallback();
        Http::assertSentCount(1);
        $this->runWorker();
        $this->assertSame(['218911234567', $expected, $this->session], $this->sent[1]);
        $this->assertSame($qty, CartQuote::first()->requested_quantity);
        $this->assertSame(1, CartQuote::first()->payload['result']['items'][0]['qty']);
        $this->assertSame('captured', CartQuote::first()->payload['billingStatus']);
        $this->assertStringStartsWith('amazon-quotations/', CartQuote::first()->pdf_path);
        $this->assertNotNull(CartQuote::first()->whatsapp_queued_at);
        $this->assertStringNotContainsString('USD', $this->sent[1][1]);
        $this->assertStringNotContainsString('0.25', $this->sent[1][1]);
        $this->incoming($text);
        $this->signedCallback();
        $this->runWorker();
        Http::assertSentCount(2);
        $this->assertCount(2, $this->sent);
    }

    public function test_alternative_offer_without_options_succeeds_and_discloses_seller_in_webhook_pdf_caption(): void
    {
        $payload = $this->result();
        unset($payload['result']['items'][0]['variant']);
        $notice = 'Notice: not the same offer as your link. Amazon has no main seller for this product, so this is another seller\'s price';
        $payload['result']['items'][0]['notes'] = [$notice, 'ASIN B0CT5SYY3Z', 'Delivery AED10', 'Sold by Gulf Orchid'];
        Http::fake(['quotes.test/v1/quotes' => Http::response($this->pending(), 202),
            'quotes.test/v1/quotes/amazon-quote' => Http::response($payload)]);
        $this->incoming();
        $this->signedCallback();
        $this->runWorker();
        $quote = CartQuote::firstOrFail();
        $this->assertSame('succeeded', $quote->status);
        $this->assertSame('captured', $quote->payload['billingStatus']);
        $this->assertSame(['amount' => '0.25', 'currency' => 'USD'], $quote->payload['fee']);
        $this->assertSame('126', $quote->amazon_pricing['total']['amount']);
        $this->assertStringContainsString("Test shoes\n".$notice, $this->sent[1][1]);
        $this->assertStringContainsString('Sold by Gulf Orchid', $this->sent[1][1]);
        $this->assertStringContainsString('Delivery AED10', $this->sent[1][1]);
        $this->assertStringContainsString(\App\Support\AmazonOfferDetails::DELIVERY_EXCLUSION, $this->sent[1][1]);
        $this->assertStringNotContainsString('0.25', $this->sent[1][1]);
        $this->assertNotNull($quote->pdf_path);
        $this->signedCallback();
        $this->runWorker();
        $this->assertCount(2, $this->sent);
        Http::assertSentCount(2);
    }

    public function test_unavailable_used_only_offer_stays_failed_when_customer_resends_link_as_new_message(): void
    {
        $newResult = $this->result(null);
        $newResult['id'] = 'amazon-new-quote';
        Http::fake(['quotes.test/v1/quotes' => Http::sequence()->push($this->pending(), 202)->push($newResult),
            'quotes.test/v1/quotes/amazon-quote' => Http::response(['id' => 'amazon-quote', 'status' => 'failed',
                'billingStatus' => 'released', 'errorCode' => 'PRODUCT_UNAVAILABLE'])]);
        $this->incoming();
        $this->signedCallback('quote.failed', data: ['quoteId' => 'amazon-quote', 'errorCode' => 'PRODUCT_UNAVAILABLE', 'billingStatus' => 'released']);
        $this->runWorker();
        $failed = CartQuote::firstOrFail();
        $this->assertSame('failed', $failed->status);
        $this->assertSame('released', $failed->payload['billingStatus']);
        $this->assertNull($failed->amazon_pricing);
        $this->assertNull($failed->pdf_path);
        $this->incoming(id: 'wamid.amazon-resend');
        $this->assertDatabaseCount('cart_quotes', 2);
        $fresh = CartQuote::latest('id')->firstOrFail();
        $this->assertSame('succeeded', $fresh->status);
        $this->assertNotSame($failed->idempotency_key, $fresh->idempotency_key);
        $this->assertNotNull($fresh->pdf_path);
        $this->assertSame('failed', $failed->fresh()->status);
        $this->assertSame('released', $failed->fresh()->payload['billingStatus']);
        $this->runWorker();
        Http::assertSentCount(2);
    }

    /** @dataProvider realButtonActions */
    public function test_real_sender_posts_amazon_pdf_caption_and_buttons_then_processes_the_customer_action(string $action, string $status): void
    {
        $this->app->forgetInstance(WhatsAppService::class);
        config(['services.whatsapp.api_url' => 'https://whatsapp.test/api', 'services.whatsapp.api_token' => 'test-whatsy-key']);
        Http::fake(['quotes.test/v1/quotes' => Http::response($this->pending(), 202),
            'quotes.test/v1/quotes/amazon-quote' => Http::response($this->result()),
            'whatsapp.test/*' => Http::response([], 202)]);
        $this->incoming();
        $this->signedCallback();
        $this->runWorker();
        $quote = CartQuote::firstOrFail();
        $this->assertNotNull($quote->whatsapp_queued_at);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://whatsapp.test/api/message'
            && ($request->data()['file_name'] ?? null) === 'amazon-quotation-'.$quote->id.'.pdf'
            && $request['phone'] === ['218911234567'] && $request['session_uuid'] === $this->session
            && $request['buttons'] === $quote->whatsappReplyButtons()
            && str_starts_with(base64_decode($request['file_base64'], true), '%PDF-')
            && str_contains($request['message'], 'Price: 126 LYD'));
        $event = ['entry' => [['id' => $this->session, 'changes' => [['value' => ['messages' => [[
            'id' => 'wamid.amazon-button', 'from' => '218911234567', 'type' => 'interactive',
            'interactive' => ['type' => 'button_reply', 'button_reply' => [
                'id' => $action.':'.$quote->whatsappCartReference(), 'title' => 'Untrusted label']],
        ]]]]]]]];
        $this->postJson('/api/webhook/whatsapp?token='.config('services.whatsapp.webhook_token'), $event)->assertOk();
        $this->artisan('cart-quotes:process-replies')->assertExitCode(0);
        $this->assertSame($status, $quote->fresh()->customer_status);
        $this->assertSame($status, $quote->fresh()->operator_status);
        $this->assertDatabaseHas('cart_status_audits', ['cart_quote_id' => $quote->id, 'new_status' => $status,
            'actor_type' => 'customer', 'event_id' => 'wamid.amazon-button']);
        Http::assertSentCount(5);
        $this->postJson('/api/webhook/whatsapp?token='.config('services.whatsapp.webhook_token'), $event)->assertOk();
        $this->artisan('cart-quotes:process-replies')->assertExitCode(0);
        Http::assertSentCount(5);
    }

    public static function realButtonActions(): array
    {
        return [['confirm', 'confirmed'], ['edit', 'editing'], ['cancel', 'cancelled']];
    }

    public static function quoteFailures(): array
    {
        $tryLater = 'There is a temporary pricing problem. Please send the link again in a few minutes.';

        return [
            ['failed', 'PRODUCT_UNAVAILABLE', 'The items are unavailable. Please send a new link.'],
            ['failed', 'INVALID_URL', $tryLater],
            ['failed', 'RETAILER_BLOCKED', $tryLater], ['expired', 'QUOTE_EXPIRED', 'The quote timed out. Please send the link again.'],
            ['failed', 'REGION_NOT_ALLOWED', 'This Amazon link is from a region our account does not allow. Please send a link from an allowed region.'],
            ['failed', 'REGION_UNVERIFIED', "We couldn't verify this product's region. Please open the product on amazon.ae and send its link again."],
            ['failed', 'SCHEMA_CHANGED', $tryLater],
            ['failed', 'ENGINE_FAILED', $tryLater], ['failed', 'LEASE_EXPIRED', $tryLater],
            ['failed', 'VALIDATION_FAILED', $tryLater], ['failed', 'FUTURE_UNKNOWN_CODE', $tryLater],
            ['cancelled', null, null],
        ];
    }

    /** @dataProvider quoteFailures */
    public function test_terminal_quote_event_is_applied_and_replied_once(string $status, ?string $code, ?string $expected): void
    {
        Http::fake(['quotes.test/v1/quotes' => Http::response($this->pending(), 202),
            'quotes.test/v1/quotes/amazon-quote' => Http::response(['id' => 'amazon-quote', 'status' => $status,
                'billingStatus' => 'released', 'errorCode' => $code])]);
        $this->incoming();
        $this->signedCallback('quote.'.$status, data: ['quoteId' => 'amazon-quote', 'errorCode' => $code, 'billingStatus' => 'released']);
        $this->runWorker();
        if ($expected === null) {
            $this->assertCount(1, $this->sent);
        } else {
            $this->assertSame($expected, $this->sent[1][1]);
        }
        $this->assertSame('released', CartQuote::first()->payload['billingStatus']);
        $this->signedCallback('quote.'.$status, data: ['quoteId' => 'amazon-quote', 'errorCode' => $code, 'billingStatus' => 'released']);
        $this->runWorker();
        Http::assertSentCount(1);
        $this->assertCount($expected === null ? 1 : 2, $this->sent);
    }

    public function test_deadline_safety_net_rechecks_pending_and_late_webhook_still_delivers(): void
    {
        $read = $this->pending();
        Http::fake(['quotes.test/v1/quotes' => Http::response($this->pending(), 202),
            'quotes.test/v1/quotes/amazon-quote' => function () use (&$read) {
                return Http::response($read);
            }]);
        $this->incoming();
        $this->travel(14)->minutes();
        $this->runWorker();
        Http::assertSentCount(1);
        $this->travel(1)->minutes();
        $this->runWorker();
        Http::assertSentCount(2);
        $quote = CartQuote::first();
        $this->assertNotNull($quote->deadline_checked_at);
        $this->assertNotNull($quote->next_attempt_at);
        $this->travel(10)->minutes();
        $this->incoming();
        $this->runWorker();
        Http::assertSentCount(3);
        $read = $this->result();
        $this->signedCallback(webhookId: 'late-event');
        $this->runWorker();
        $this->assertSame('succeeded', $quote->fresh()->status);
        $this->assertNotNull($quote->fresh()->notified_at);
    }

    public function test_missed_webhook_deadline_read_delivers_success(): void
    {
        Http::fake(['quotes.test/v1/quotes' => Http::response($this->pending(), 202),
            'quotes.test/v1/quotes/amazon-quote' => Http::response($this->result())]);
        $this->incoming();
        $this->travel(16)->minutes();
        $this->runWorker();
        Http::assertSentCount(2);
        $this->assertNotNull(CartQuote::first()->notified_at);
    }

    public function test_failed_final_whatsapp_delivery_retries_saved_result_without_get_or_submit(): void
    {
        Http::fake(['quotes.test/v1/quotes' => Http::response($this->pending(), 202),
            'quotes.test/v1/quotes/amazon-quote' => Http::response($this->result())]);
        $this->incoming();
        $this->sendResults = [false, true];
        $this->signedCallback();
        $this->runWorker();
        $this->assertNull(CartQuote::first()->notified_at);
        $snapshot = CartQuote::first()->amazon_pricing;
        Setting::findOrFail(1)->update(['usd_to_lyd_rate' => '10']);
        $this->travel(31)->seconds();
        $this->runWorker();
        Http::assertSentCount(2);
        $this->assertSame($this->sent[1], $this->sent[2]);
        $this->assertSame($snapshot, CartQuote::first()->amazon_pricing);
        $this->assertNotNull(CartQuote::first()->notified_at);
    }

    public function test_saved_arabic_terminal_failure_uses_one_language(): void
    {
        Setting::findOrFail(1)->update(['amazon_messages' => []]);
        WhatsappMessages::create(['message_id' => 'previous', 'from' => '218911234567', 'message' => 'مرحبا', 'phone' => '218921000000', 'name' => 'Customer', 'type' => 'text',
            'session_uuid' => $this->session, 'timestamp' => now()]);
        Http::fake(['quotes.test/v1/quotes' => Http::response($this->pending(), 202),
            'quotes.test/v1/quotes/amazon-quote' => Http::response(['id' => 'amazon-quote', 'status' => 'failed',
                'billingStatus' => 'released', 'errorCode' => 'PRODUCT_UNAVAILABLE'])]);
        $this->incoming($this->url);
        $this->signedCallback('quote.failed', data: ['quoteId' => 'amazon-quote', 'errorCode' => 'PRODUCT_UNAVAILABLE', 'billingStatus' => 'released']);
        $this->runWorker();
        $this->assertSame('وصلنا رابط أمازون، جاري التحقق من السعر...', $this->sent[0][1]);
        $this->assertSame('المنتجات غير متوفرة حالياً. أرسل رابطاً جديداً.', $this->sent[1][1]);
    }

    public function test_group_and_trial_restrictions_apply_to_amazon(): void
    {
        $this->incoming(context: ['chat_id' => '123456@g.us']);
        config(['services.cart_quotes.whatsapp_test_number' => '218922222222']);
        $this->incoming();
        $this->assertDatabaseCount('cart_quotes', 0);
        Http::assertNothingSent();
        config(['services.cart_quotes.whatsapp_test_number' => '218911234567']);
        Http::fake(['quotes.test/v1/quotes' => Http::response($this->pending(), 202)]);
        $this->incoming();
        $this->assertDatabaseCount('cart_quotes', 1);
    }

    public static function arabicFailures(): array
    {
        $later = 'لم نتمكن من تسعير هذا المنتج الآن. أرسل الرابط مرة أخرى بعد دقائق.';
        $staff = 'شكراً! سنرد عليك بالسعر قريباً.';

        return [
            ['INVALID_URL', 'أرسل رابط صفحة المنتج في أمازون: افتح المنتج، اضغط مشاركة، وأرسل الرابط.'],
            ['REGION_NOT_ALLOWED', 'منطقة رابط أمازون هذا غير مسموحة لحسابنا. أرسل رابطاً من إحدى المناطق المسموحة.'],
            ['INSUFFICIENT_FUNDS', $staff], ['ACCOUNT_PROBLEM', $staff],
            ['IDEMPOTENCY_KEY_REQUIRED', $staff], ['IDEMPOTENCY_CONFLICT', $staff],
            ['RETAILER_BLOCKED', $later], ['QUOTE_EXPIRED', $later], ['CAPACITY_UNAVAILABLE', $later],
            ['REGION_UNVERIFIED', 'لم نتمكن من التحقق من منطقة هذا المنتج. افتح المنتج في amazon.ae وأرسل رابطه مرة أخرى.'],
            ['SCHEMA_CHANGED', $later], ['ENGINE_FAILED', $later],
            ['LEASE_EXPIRED', $later], ['VALIDATION_FAILED', $later], ['FUTURE_CODE', $later],
        ];
    }

    /** @dataProvider arabicFailures */
    public function test_arabic_error_copy(string $code, string $expected): void
    {
        Setting::findOrFail(1)->update(['amazon_messages' => []]);
        $this->assertSame($expected, app(AmazonQuoteReply::class)->failure($code));
    }

    public function test_non_decimal_or_incomplete_result_cannot_send_a_price_and_can_recover(): void
    {
        $read = $this->result();
        $read['result']['total']['amount'] = 123.456789;
        Http::fake(['quotes.test/v1/quotes' => Http::response($this->pending(), 202),
            'quotes.test/v1/quotes/amazon-quote' => function () use (&$read) {
                return Http::response($read);
            }]);
        $this->incoming();
        $this->signedCallback();
        $this->runWorker();
        $this->assertCount(1, $this->sent);
        $this->assertNull(CartQuote::first()->notified_at);
        $read = $this->result();
        $read['result']['items'] = null;
        $this->travel(31)->seconds();
        $this->runWorker();
        $this->assertCount(1, $this->sent);
        $read = $this->result();
        $this->travel(61)->seconds();
        $this->runWorker();
        $this->assertCount(2, $this->sent);
        $this->assertNotNull(CartQuote::first()->notified_at);
        $this->assertCount(1, Http::recorded(fn (Request $request) => $request->method() === 'POST'));
    }

    public function test_staff_alerts_reach_administrators_and_employees_only_and_survive_delivery_failure(): void
    {
        DB::table('types')->insert([['id' => 1, 'type' => 'مدير النظام'], ['id' => 2, 'type' => 'موظف'], ['id' => 3, 'type' => 'زبون']]);
        $admin = User::factory()->create(['type_id' => 1]);
        $employee = User::factory()->create(['type_id' => 2]);
        $customer = User::factory()->create(['type_id' => 3]);
        Http::fake(['quotes.test/v1/quotes' => Http::response(['error' => ['code' => 'INSUFFICIENT_FUNDS']], 402)]);
        $this->sendResults = [false, true];
        $this->incoming();
        $this->assertDatabaseHas('notif_orgs', ['user_id' => $admin->id]);
        $this->assertDatabaseHas('notif_orgs', ['user_id' => $employee->id]);
        $this->assertDatabaseMissing('notif_orgs', ['user_id' => $customer->id]);
        $this->assertNull(CartQuote::first()->notified_at);
        $this->travel(31)->seconds();
        $this->runWorker();
        Http::assertSentCount(1);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertNotNull(CartQuote::first()->notified_at);
    }

    public function test_admin_review_cannot_apply_shein_pricing_or_generate_shein_pdf_for_amazon(): void
    {
        Http::fake(['quotes.test/v1/quotes' => Http::response($this->pending(), 202),
            'quotes.test/v1/quotes/amazon-quote' => Http::response($this->result())]);
        $this->incoming();
        $this->signedCallback();
        $this->runWorker();
        DB::table('types')->insert(['id' => 2, 'type' => 'موظف']);
        $operator = User::factory()->create(['type_id' => 2, 'status' => true]);
        $operator->givePermissionTo(Permission::firstOrCreate(['name' => 'cart-list', 'guard_name' => 'web'], ['ar_name' => 'cart-list']));
        $quote = CartQuote::first();
        $this->actingAs($operator)->get(route('carts.show', $quote))->assertOk()->assertSee('126')->assertDontSee('123.456789');
        $this->get(route('carts.index'))->assertOk()->assertSee('126')->assertDontSee('123.456789');
        $this->get(route('carts.quotation', $quote))->assertOk()->assertDownload('amazon-quotation-'.$quote->id.'.pdf');
        $this->assertNull($quote->fresh()->quotation);
        $this->assertStringStartsWith('amazon-quotations/', $quote->fresh()->pdf_path);
    }

    public function test_unset_rate_defers_price_until_configured_without_another_provider_request(): void
    {
        Setting::findOrFail(1)->update(['usd_to_lyd_rate' => null]);
        Http::fake(['quotes.test/v1/quotes' => Http::response($this->pending(), 202),
            'quotes.test/v1/quotes/amazon-quote' => Http::response($this->result())]);
        $this->incoming();
        $this->signedCallback();
        $this->runWorker();
        $quote = CartQuote::first();
        $this->assertSame('succeeded', $quote->status);
        $this->assertNull($quote->amazon_pricing);
        $this->assertCount(1, $this->sent);
        Setting::findOrFail(1)->update(['usd_to_lyd_rate' => '7.125']);
        $this->travel(31)->seconds();
        $this->runWorker();
        Http::assertSentCount(2);
        $this->assertStringContainsString('Price: 126 LYD', $this->sent[1][1]);
        $this->assertNotNull($quote->fresh()->notified_at);
    }
}
