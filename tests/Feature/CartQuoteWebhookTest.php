<?php

namespace Tests\Feature;

use App\Http\CartQuoteWebhookRequest;
use App\Models\CartQuote;
use App\Models\Setting;
use App\Models\WhatsappMessages;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Tests\TestCase;

class CartQuoteWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const VECTOR_BODY = '{"id":"evt_1","type":"webhook.test","createdAt":"2026-10-09T18:04:10+00:00","data":{"endpointId":"ep_1","sentAt":"2026-10-09T18:04:10+00:00"}}';

    private const VECTOR_SIGNATURE = 'v1=ac010e6675da6161b6c7ff5cbe2f94b93994f267863ce1e2595644f1c1cb8ba2';

    private array $messages = [];

    private array $documents = [];

    private bool $canSend = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTimestamp(1791227047));
        config(['services.cart_quotes.base_url' => 'https://quotes.test',
            'services.cart_quotes.token' => 'test-api-key',
            'services.cart_quotes.webhook_secret' => 'whsec_test_secret',
            'services.cart_quotes.webhook_secret_previous' => null]);
        Setting::findOrFail(1)->update(['usd_to_lyd_rate' => '1', 'shein_company_percentage' => '0',
            'shein_pause_on_empty_wallet' => false,
            'amazon_messages' => json_decode(file_get_contents(base_path('tests/Fixtures/amazon-messages.json')), true)]);
        Storage::fake('local');
        Http::preventStrayRequests();
        $this->mock(WhatsAppService::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->andReturnUsing(function ($recipient, $message) {
                $this->messages[] = [$recipient, $message];

                return $this->canSend;
            });
            $mock->shouldReceive('sendDocument')->andReturnUsing(function ($recipient, $path, $name, $caption) {
                $this->assertStringStartsWith('%PDF-', file_get_contents($path));
                $this->documents[] = [$recipient, $caption];

                return $this->canSend;
            });
        });
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function send(string $body, array $headers = [], string $secret = 'whsec_test_secret', string $path = '/webhooks/cart-quotes')
    {
        $timestamp = (string) now()->timestamp;

        return $this->call('POST', $path, [], [], [], array_merge([
            'CONTENT_TYPE' => 'application/json',
            'HTTP_WEBHOOK_ID' => 'evt_1', 'HTTP_WEBHOOK_TIMESTAMP' => $timestamp,
            'HTTP_WEBHOOK_KEY_VERSION' => '1',
            'HTTP_WEBHOOK_SIGNATURE' => 'v1='.hash_hmac('sha256', $timestamp.'.'.$body, $secret),
        ], $headers), $body);
    }

    private function quote(string $retailer = 'shein', string $id = 'quote-1'): CartQuote
    {
        $incoming = WhatsappMessages::create(['message_id' => 'wamid.'.$id, 'from' => '218911234567',
            'phone' => '218921000000', 'name' => 'Customer', 'message' => 'Price please', 'type' => 'text',
            'is_reply' => true, 'session_uuid' => str_repeat('a', 32), 'timestamp' => now()]);

        return CartQuote::create(['whatsapp_messages_id' => $incoming->id, 'source_url' => 'https://example.test/link',
            'retailer' => $retailer, 'idempotency_key' => $id, 'quote_id' => $id, 'status' => 'submitted',
            'submitted_at' => now(), 'payload' => ['id' => $id, 'status' => 'pending',
                'deadlineAt' => now()->addMinutes(15)->toIso8601String()], 'next_attempt_at' => now()->addMinutes(15)]);
    }

    private function event(string $type, string $id = 'quote-1', ?string $code = null): string
    {
        return json_encode(['id' => 'evt_1', 'type' => $type, 'createdAt' => now()->toIso8601String(),
            'data' => ['quoteId' => $id, 'status' => substr($type, 6),
                'billingStatus' => $type === 'quote.succeeded' ? 'captured' : 'released', 'errorCode' => $code,
                'fee' => ['amount' => '0.25', 'currency' => 'USD'],
                'total' => $type === 'quote.succeeded' ? ['amount' => '9999.999', 'currency' => 'LYD'] : null,
                'itemCount' => 2, 'completedAt' => now()->toIso8601String()]]);
    }

    private function result(string $retailer = 'shein', string $id = 'quote-1'): array
    {
        $items = [['name' => 'Shoes', 'variant' => 'Black / M', 'qty' => 1, 'availability' => 'available',
            'qtySource' => 'assumed_one', 'priceSource' => 'product_page',
            'outputLine' => ['amount' => '1056.571', 'currency' => 'LYD'],
            'usd' => ['unitSale' => ['amount' => '2.72', 'currency' => 'USD']],
            'imageUrl' => 'https://images.test/shoes.jpg', 'notes' => ['Test note']]];
        if ($retailer === 'shein') {
            $items[] = ['name' => 'Unavailable item', 'variant' => null, 'qty' => 1,
                'availability' => 'unavailable', 'outputLine' => null, 'imageUrl' => null, 'notes' => []];
        }

        return ['id' => $id, 'status' => 'succeeded', 'billingStatus' => 'captured',
            'fee' => ['amount' => '0.25', 'currency' => 'USD'],
            'result' => ['itemCount' => count($items), 'total' => ['amount' => '1056.571', 'currency' => 'LYD'],
                'native' => ['sale' => ['amount' => '1056.571', 'currency' => 'USD']], 'items' => $items]];
    }

    private function worker(): void
    {
        $this->artisan('cart-quotes:process')->assertExitCode(0);
    }

    public function test_provider_signature_vector_and_test_event_are_accepted_and_logged(): void
    {
        Log::spy();
        $this->send(self::VECTOR_BODY, ['HTTP_WEBHOOK_SIGNATURE' => self::VECTOR_SIGNATURE])->assertOk();
        $this->assertDatabaseHas('cart_quote_webhooks', ['webhook_id' => 'evt_1', 'cart_quote_id' => 0]);
        Log::shouldHaveReceived('info')->once()->with('Cart Quotes webhook test received', ['webhook_id' => 'evt_1']);
        $this->worker();
        Http::assertNothingSent();
        $this->assertSame([], $this->messages);
        $this->assertSame([], $this->documents);
    }

    /** @dataProvider authenticationFailures */
    public function test_authentication_failures_make_no_changes(string $case): void
    {
        $body = self::VECTOR_BODY;
        $headers = ['HTTP_WEBHOOK_SIGNATURE' => self::VECTOR_SIGNATURE];
        if ($case === 'wrong secret') {
            config(['services.cart_quotes.webhook_secret' => 'whsec_wrong']);
        } elseif ($case === 'changed byte') {
            $body = str_replace('ep_1', 'ep_2', $body);
        } elseif ($case === 'old timestamp' || $case === 'future timestamp') {
            $timestamp = (string) (now()->timestamp + ($case === 'old timestamp' ? -301 : 301));
            $headers['HTTP_WEBHOOK_TIMESTAMP'] = $timestamp;
            $headers['HTTP_WEBHOOK_SIGNATURE'] = 'v1='.hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test_secret');
        } elseif ($case === 'non digit timestamp') {
            $headers['HTTP_WEBHOOK_TIMESTAMP'] = '1791227047.0';
        } elseif ($case === 'uppercase signature') {
            $headers['HTTP_WEBHOOK_SIGNATURE'] = strtoupper(self::VECTOR_SIGNATURE);
        } elseif ($case === 'missing secret') {
            config(['services.cart_quotes.webhook_secret' => null]);
        } else {
            $headers[$case] = '';
        }
        $this->send($body, $headers)->assertUnauthorized();
        $this->assertDatabaseCount('cart_quote_webhooks', 0);
        Http::assertNothingSent();
        $this->assertSame([], $this->messages);
    }

    public static function authenticationFailures(): array
    {
        return array_map(fn ($case) => [$case], ['wrong secret', 'changed byte', 'old timestamp', 'future timestamp',
            'non digit timestamp', 'uppercase signature', 'missing secret', 'HTTP_WEBHOOK_ID',
            'HTTP_WEBHOOK_TIMESTAMP', 'HTTP_WEBHOOK_SIGNATURE', 'HTTP_WEBHOOK_KEY_VERSION']);
    }

    public function test_both_secrets_and_key_versions_work_during_rotation(): void
    {
        config(['services.cart_quotes.webhook_secret_previous' => 'whsec_previous']);
        $this->send(self::VECTOR_BODY, ['HTTP_WEBHOOK_ID' => 'old', 'HTTP_WEBHOOK_KEY_VERSION' => '41'], 'whsec_previous')->assertOk();
        $this->send(self::VECTOR_BODY, ['HTTP_WEBHOOK_ID' => 'new', 'HTTP_WEBHOOK_KEY_VERSION' => '42'])->assertOk();
        config(['services.cart_quotes.webhook_secret_previous' => null]);
        $this->send(self::VECTOR_BODY, ['HTTP_WEBHOOK_ID' => 'retired'], 'whsec_previous')->assertUnauthorized();
        $this->assertDatabaseCount('cart_quote_webhooks', 2);
    }

    public function test_timestamp_boundaries_and_digit_string_use_exact_signed_text(): void
    {
        foreach ([-300, 300] as $offset) {
            $timestamp = '0'.(now()->timestamp + $offset);
            $this->send(self::VECTOR_BODY, ['HTTP_WEBHOOK_ID' => 'boundary'.$offset,
                'HTTP_WEBHOOK_TIMESTAMP' => $timestamp,
                'HTTP_WEBHOOK_SIGNATURE' => 'v1='.hash_hmac('sha256', $timestamp.'.'.self::VECTOR_BODY, 'whsec_test_secret')])->assertOk();
        }
    }

    public function test_raw_capture_and_middleware_never_invoke_the_json_body_parser(): void
    {
        $body = "{\n \"type\": \"webhook.test\", \"data\": {\"endpointId\": \"  ep  \", \"sentAt\": \"\"}\n}";
        $base = SymfonyRequest::create('/webhooks/cart-quotes', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_WEBHOOK_ID' => 'raw-event',
            'HTTP_WEBHOOK_TIMESTAMP' => '1791227047', 'HTTP_WEBHOOK_KEY_VERSION' => '1',
            'HTTP_WEBHOOK_SIGNATURE' => 'v1='.hash_hmac('sha256', '1791227047.'.$body, 'whsec_test_secret'),
        ], $body);
        $requestClass = new class extends CartQuoteWebhookRequest
        {
            public function json($key = null, $default = null)
            {
                throw new \RuntimeException('JSON body parser must not run on the webhook route');
            }
        };
        $request = $requestClass::createFromBase($base);
        $this->assertSame($body, $request->getContent());
        $kernel = $this->app->make(Kernel::class);
        $response = $kernel->handle($request);
        $this->assertSame(200, $response->getStatusCode());
        $kernel->terminate($request, $response);
        $payload = json_decode(DB::table('cart_quote_webhooks')->first()->payload, true);
        $this->assertSame('  ep  ', $payload['data']['endpointId']);
        $this->assertSame('', $payload['data']['sentAt']);
    }

    public function test_duplicate_replays_do_not_overwrite_events_or_restart_retries(): void
    {
        $quote = $this->quote();
        Http::fake(['quotes.test/*' => Http::response([], 503)]);
        $body = $this->event('quote.succeeded');
        $this->send($body)->assertOk();
        $this->assertSame('submitted', $quote->fresh()->status);
        Http::assertNothingSent();
        $this->worker();
        $retryAt = $quote->fresh()->next_attempt_at->toIso8601String();
        $this->send($this->event('quote.failed'))->assertOk();
        $this->worker();
        $this->assertSame($retryAt, $quote->fresh()->next_attempt_at->toIso8601String());
        $this->assertSame('quote.succeeded', json_decode(DB::table('cart_quote_webhooks')->first()->payload, true)['type']);
        $this->assertDatabaseCount('cart_quote_webhooks', 1);
        Http::assertSentCount(1);
    }

    public function test_other_routes_keep_laravels_normal_json_capture(): void
    {
        $request = CartQuoteWebhookRequest::createFromBase(SymfonyRequest::create('/api/other', 'POST', [], [], [],
            ['CONTENT_TYPE' => 'application/json'], '{"name":"  Customer  "}'));
        $this->assertSame('  Customer  ', $request->request->get('name'));
        $this->assertSame('  Customer  ', $request->json('name'));
    }

    public function test_unknown_and_future_event_types_are_recorded_and_acknowledged(): void
    {
        Log::spy();
        $this->send($this->event('quote.succeeded', 'unknown'))->assertOk();
        $this->send(json_encode(['type' => 'future.event', 'data' => []]), ['HTTP_WEBHOOK_ID' => 'future'])->assertOk();
        $this->worker();
        $this->assertDatabaseCount('cart_quote_webhooks', 2);
        $this->assertDatabaseMissing('cart_quote_webhooks', ['processed_at' => null]);
        Log::shouldHaveReceived('warning')->once()->with('Cart Quotes webhook for unknown quote',
            ['webhook_id' => 'evt_1', 'quote_id' => 'unknown']);
        Http::assertNothingSent();
    }

    public function test_legitimate_bursts_and_existing_route_aliases_do_not_require_session_or_csrf(): void
    {
        for ($i = 0; $i < 65; $i++) {
            $this->send(self::VECTOR_BODY, ['HTTP_WEBHOOK_ID' => 'burst-'.$i])->assertOk()->assertHeaderMissing('Location');
        }
        foreach (['/hooks/cart-quotes', '/hooks/cartquotes'] as $i => $path) {
            $this->send(self::VECTOR_BODY, ['HTTP_WEBHOOK_ID' => 'alias-'.$i], 'whsec_test_secret', $path)->assertOk();
        }
        $this->assertDatabaseCount('cart_quote_webhooks', 67);
    }

    /** @dataProvider retailers */
    public function test_success_reads_authoritative_total_and_items_and_replies_once(string $retailer): void
    {
        $quote = $this->quote($retailer);
        $result = $this->result($retailer);
        Http::fake(['quotes.test/v1/quotes/quote-1' => Http::response($result)]);
        $body = $this->event('quote.succeeded');
        $this->send($body)->assertOk();
        Http::assertNothingSent();
        $this->assertSame([], $this->documents);
        $this->worker();
        $quote->refresh();
        $this->assertSame('succeeded', $quote->status);
        $this->assertSame('1056.571', $quote->payload['result']['total']['amount']);
        $this->assertSame($result['result']['items'], $quote->payload['result']['items']);
        $this->assertNotNull($quote->notified_at);
        $this->assertCount(1, $this->documents);
        if ($retailer === 'shein') {
            $this->assertSame('1057', $quote->quotation['total']['amount']);
            $this->assertSame('unavailable', $quote->quotation['items'][1]['availability']);
        } else {
            $this->assertSame('2.72', $quote->amazon_pricing['base_unit_usd']);
        }
        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && $request->url() === 'https://quotes.test/v1/quotes/quote-1'
            && $request->hasHeader('Authorization', 'Bearer test-api-key'));
        $this->send($body)->assertOk();
        $this->worker();
        Http::assertSentCount(1);
        $this->assertCount(1, $this->documents);
    }

    public static function retailers(): array
    {
        return [['shein'], ['amazon']];
    }

    /** @dataProvider terminalEvents */
    public function test_terminal_events_update_quote_and_send_the_required_reply(string $retailer, string $status, ?string $code, ?string $expected): void
    {
        $quote = $this->quote($retailer);
        $body = $this->event('quote.'.$status, 'quote-1', $code);
        $this->send($body)->assertOk();
        $this->assertSame('submitted', $quote->fresh()->status);
        $this->assertSame([], $this->messages);
        $this->worker();
        $quote->refresh();
        $this->assertSame($status, $quote->status);
        $this->assertSame('released', $quote->payload['billingStatus']);
        $this->assertSame('0.25', $quote->payload['fee']['amount']);
        $this->assertNotNull($quote->notified_at);
        $this->assertNull($quote->next_attempt_at);
        $this->assertSame($expected === null ? [] : [['218911234567', $expected]], $this->messages);
        $this->send($body)->assertOk();
        $this->worker();
        $this->assertCount($expected === null ? 0 : 1, $this->messages);
        Http::assertNothingSent();
    }

    public static function terminalEvents(): array
    {
        $cases = [
            ['failed', 'SHARED_CART_UNAVAILABLE', 'The items are unavailable. Please send a new link.'],
            ['failed', 'PRODUCT_UNAVAILABLE', 'The items are unavailable. Please send a new link.'],
            ['expired', null, 'The quote timed out. Please send the link again.'], ['cancelled', null, null],
        ];
        foreach (['RETAILER_BLOCKED', 'HUMAN_VERIFICATION_REQUIRED', 'ENGINE_FAILED', 'FUTURE_ERROR', null] as $code) {
            $cases[] = ['failed', $code, 'There is a temporary pricing problem. Please send the link again in a few minutes.'];
        }
        $rows = [];
        foreach (['shein', 'amazon'] as $retailer) {
            foreach ($cases as $case) {
                $rows[] = array_merge([$retailer], $case);
            }
        }

        return $rows;
    }

    public function test_delivery_failures_retry_saved_terminal_event_without_api_calls(): void
    {
        $quote = $this->quote();
        $this->canSend = false;
        $this->send($this->event('quote.failed', code: 'PRODUCT_UNAVAILABLE'))->assertOk();
        $this->worker();
        $this->assertNull($quote->fresh()->notified_at);
        $this->assertSame('failed', $quote->fresh()->status);
        $this->canSend = true;
        $this->travel(31)->seconds();
        $this->worker();
        $this->assertNotNull($quote->fresh()->notified_at);
        $this->assertCount(2, $this->messages);
        Http::assertNothingSent();
    }

    /** @dataProvider retailers */
    public function test_fallback_fetches_at_deadline_and_rechecks_a_still_pending_quote(string $retailer): void
    {
        $quote = $this->quote($retailer);
        $reads = 0;
        Http::fake(['quotes.test/v1/quotes/quote-1' => function () use ($retailer, &$reads) {
            return Http::response(++$reads === 1 ? ['id' => 'quote-1', 'status' => 'pending'] : $this->result($retailer));
        }]);
        $this->travel(899)->seconds();
        $this->worker();
        Http::assertNothingSent();
        $this->travel(1)->seconds();
        $this->worker();
        Http::assertSentCount(1);
        $this->assertNotNull($quote->fresh()->deadline_checked_at);
        $this->travel(60)->seconds();
        $this->worker();
        $this->assertNotNull($quote->fresh()->notified_at);
        $this->assertCount(1, $this->documents);
        Http::assertSentCount(2);
    }

    public function test_fallback_terminal_statuses_use_the_same_customer_event_rules(): void
    {
        foreach (['failed', 'expired', 'cancelled'] as $status) {
            $this->quote(id: $status);
        }
        Http::fake(['quotes.test/v1/quotes/*' => function (Request $request) {
            $status = basename($request->url());

            return Http::response(['id' => $status, 'status' => $status, 'billingStatus' => 'released',
                'errorCode' => 'SHARED_CART_UNAVAILABLE']);
        }]);
        $this->travel(15)->minutes();
        $this->worker();
        $this->assertCount(2, $this->messages);
        $this->assertSame('The items are unavailable. Please send a new link.', $this->messages[0][1]);
        $this->assertSame('The quote timed out. Please send the link again.', $this->messages[1][1]);
        $this->assertSame(3, CartQuote::whereNotNull('notified_at')->count());
        Http::assertSentCount(3);
    }

    public function test_events_for_different_quotes_can_arrive_in_any_order(): void
    {
        $first = $this->quote(id: 'first');
        $second = $this->quote(id: 'second');
        $this->send($this->event('quote.expired', 'second'), ['HTTP_WEBHOOK_ID' => 'second-event'])->assertOk();
        $this->send($this->event('quote.cancelled', 'first'), ['HTTP_WEBHOOK_ID' => 'first-event'])->assertOk();
        $this->worker();
        $this->assertSame('cancelled', $first->fresh()->status);
        $this->assertSame('expired', $second->fresh()->status);
        $this->assertCount(1, $this->messages);
        Http::assertNothingSent();
    }

    public function test_preexisting_pending_quotes_with_no_retry_time_recover_at_their_deadline(): void
    {
        $quote = $this->quote('amazon');
        $quote->update(['next_attempt_at' => null, 'submitted_at' => null]);
        Http::fake(['quotes.test/v1/quotes/quote-1' => Http::response($this->result('amazon'))]);
        $this->worker();
        Http::assertNothingSent();
        $this->travel(15)->minutes();
        $this->worker();
        Http::assertSentCount(1);
        $this->assertNotNull($quote->fresh()->notified_at);
    }

    public function test_legacy_receipts_without_a_payload_still_trigger_a_result_read(): void
    {
        $quote = $this->quote();
        DB::table('cart_quote_webhooks')->insert(['webhook_id' => 'legacy', 'body_hash' => str_repeat('0', 64),
            'cart_quote_id' => $quote->id, 'created_at' => now(), 'updated_at' => now()]);
        Http::fake(['quotes.test/v1/quotes/quote-1' => Http::response($this->result())]);
        $this->worker();
        Http::assertSentCount(1);
        $this->assertNotNull($quote->fresh()->notified_at);
        $this->assertDatabaseMissing('cart_quote_webhooks', ['processed_at' => null]);
    }
}
