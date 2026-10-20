<?php

namespace Tests\Feature;

use App\Models\CartQuote;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use App\Services\CartQuoteService;
use App\Services\GeminiService;
use App\Services\SheinUrlExtractor;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CartQuoteIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private string $url = 'https://api-shein.shein.com/h5/sharejump/appsharejump?cart=123';
    private string $quoteId = '0192f3a1-7c2e-7b4d-9e10-5a6b7c8d9e0f';
    private array $sent = [];
    private array $sessions = [];
    private array $sendResults = [];
    private array $readPayload = [];

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.cart_quotes.base_url' => 'https://quotes.test',
            'services.cart_quotes.token' => 'test-api-key',
            'services.cart_quotes.webhook_secret' => 'whsec_test_secret',
            'services.cart_quotes.webhook_key_version' => '1',
            'services.cart_quotes.shein_hosts' => ['shein.com'],
        ]);
        // Existing delivery tests exercise the administrator's opt-out policy.
        Setting::findOrFail(1)->update(['usd_to_lyd_rate' => '1', 'shein_pause_on_empty_wallet' => false]);
        Storage::fake('local');
        Http::preventStrayRequests();
        $this->mock(GeminiService::class, fn ($mock) => $mock->shouldReceive('respond')->never());
        $this->mock(WhatsAppService::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->andReturnUsing(function ($recipient, $message, $sessionUuid) {
                $this->sent[] = [$recipient, $message];
                $this->sessions[] = $sessionUuid;
                return array_shift($this->sendResults) ?? true;
            });
            $mock->shouldReceive('sendDocument')->andReturnUsing(function ($recipient, $path, $filename, $message, $sessionUuid, $buttons) {
                $this->assertFileExists($path);
                $this->assertStringStartsWith('%PDF-', file_get_contents($path));
                $this->assertMatchesRegularExpression('/^shein-quotation-\d+\.pdf$/', $filename);
                $cart = CartQuote::findOrFail((int) substr($filename, strlen('shein-quotation-')));
                $this->assertSame(Storage::disk('local')->path($cart->pdf_path), $path);
                $this->assertSame($cart->whatsappReplyButtons(), $buttons);
                $this->assertSame('CART-'.$cart->id, $cart->whatsapp_cart_reference);
                $this->assertSame(ltrim($recipient, '+'), $cart->whatsapp_customer_phone);
                $this->assertSame($sessionUuid, $cart->whatsapp_session_uuid);
                $this->sent[] = [$recipient, $message];
                $this->sessions[] = $sessionUuid;
                return array_shift($this->sendResults) ?? true;
            });
        });
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function incoming(?string $text = null, string $id = 'wamid.shein',
        string $from = '218911234567', string $businessPhone = '218921000000',
        string $sessionUuid = 'd8e21afbe77f4d8bbaed65f2c6c2edf7', array $context = [], int $expectedStatus = 200): void
    {
        $this->postJson('/api/webhook/whatsapp?token='.config('services.whatsapp.webhook_token'), [
            'entry' => [['id' => $sessionUuid, 'changes' => [['value' => [
                'metadata' => ['display_phone_number' => $businessPhone],
                'contacts' => [['profile' => ['name' => 'Ahmed']]],
                'messages' => [array_merge(['id' => $id, 'from' => $from, 'type' => 'text',
                    'text' => ['body' => $text ?? 'جهز عرض سعر '.$this->url]], $context)],
            ]]]]],
        ])->assertStatus($expectedStatus);
    }

    private function pending(): array
    {
        return ['id' => $this->quoteId, 'status' => 'pending', 'billingStatus' => 'reserved', 'result' => null];
    }

    private function succeeded(): array
    {
        return ['id' => $this->quoteId, 'status' => 'succeeded', 'billingStatus' => 'captured',
            'fee' => ['amount' => '0.25', 'currency' => 'USD'],
            'result' => ['itemCount' => 7, 'total' => ['amount' => '447.821', 'currency' => 'LYD'],
                'native' => ['sale' => ['amount' => '447.821', 'currency' => 'USD']],
                'items' => array_map(fn ($amount) => ['name' => 'Test item', 'variant' => 'Black / M',
                    'qty' => 1, 'qtySource' => 'shein', 'availability' => 'available',
                    'line' => ['amount' => $amount, 'currency' => 'USD']],
                    ['100', '100', '100', '100', '20', '20', '7.821'])]];
    }

    private function completionMessage(): string
    {
        return Setting::COMPLETED_MESSAGE."\nعدد القطع: 7، الإجمالي: 448 LYD.";
    }

    private function prepare(): void
    {
        Http::fake(['quotes.test/*' => fn (Request $request) => Http::response(
            $request->method() === 'POST' ? $this->pending() : $this->readPayload,
            $request->method() === 'POST' ? 202 : 200
        )]);
        $this->incoming();
    }

    private function walletPayload(string $available): array
    {
        return ['balance' => ['amount' => '10.00', 'currency' => 'USD'],
            'held' => ['amount' => '0.00', 'currency' => 'USD'],
            'available' => ['amount' => $available, 'currency' => 'USD']];
    }

    public function test_empty_wallet_saves_carts_silently_and_resumes_after_recharge(): void
    {
        Setting::findOrFail(1)->update(['shein_pause_on_empty_wallet' => true]);
        $available = '0.000000000000000000';
        // Capture the balance by reference so a recharge is visible to the retry.
        Http::fake(['quotes.test/v1/wallet' => function () use (&$available) {
            return Http::response($this->walletPayload($available));
        }, 'quotes.test/v1/quotes*' => fn (Request $request) => Http::response(
            $request->method() === 'POST' ? $this->pending() : $this->succeeded())]);
        $this->incoming();
        $this->incoming();
        $cart = CartQuote::firstOrFail();
        $key = $cart->idempotency_key;
        $this->assertSame('pending', $cart->status);
        $this->assertSame(0, $cart->attempts);
        $this->assertNull($cart->quote_id);
        $this->assertSame(\App\Services\CartWalletPausedException::EMPTY, $cart->last_error);
        $this->assertSame([], $this->sent);
        $this->assertDatabaseCount('cart_quotes', 1);
        $this->assertDatabaseCount('replay_messages', 0);
        Http::assertNotSent(fn (Request $request) => $request->method() === 'POST');

        $this->travel(61)->seconds();
        $this->artisan('cart-quotes:process')->assertExitCode(0);
        $this->assertSame([], $this->sent);
        $available = '0.000000000000000001';
        $this->travel(61)->seconds();
        $this->artisan('cart-quotes:process')->assertExitCode(0);
        $this->assertCount(1, $this->sent);
        $this->assertSame($key, $cart->fresh()->idempotency_key);
        $this->travel(15)->minutes();
        $this->artisan('cart-quotes:process')->assertExitCode(0);
        $this->assertNotNull($cart->fresh()->notified_at);
        $this->assertCount(2, $this->sent);
        $this->assertCount(1, Http::recorded(fn (Request $request) => $request->method() === 'POST'));
    }

    public function test_replies_pause_if_last_credit_is_reserved_by_submission(): void
    {
        Setting::findOrFail(1)->update(['shein_pause_on_empty_wallet' => true]);
        $available = '0.25';
        Http::fake(['quotes.test/v1/wallet' => function () use (&$available) {
            return Http::response($this->walletPayload($available));
        }, 'quotes.test/v1/quotes*' => function (Request $request) use (&$available) {
            if ($request->method() === 'POST') {
                $available = '0.00';
                return Http::response($this->pending(), 202);
            }
            return Http::response($this->succeeded());
        }]);
        $this->incoming();
        $this->assertSame([], $this->sent);
        $this->assertSame($this->quoteId, CartQuote::firstOrFail()->quote_id);
        $available = '1.00';
        $this->travel(61)->seconds();
        $this->artisan('cart-quotes:process')->assertExitCode(0);
        $this->assertCount(2, $this->sent);
        $this->assertNotNull(CartQuote::firstOrFail()->notified_at);
        $this->assertCount(1, Http::recorded(fn (Request $request) => $request->method() === 'POST'));
    }

    public function test_completed_pdf_waits_for_recharge_without_resubmitting_or_repolling(): void
    {
        Setting::findOrFail(1)->update(['shein_pause_on_empty_wallet' => true]);
        $available = '1.00';
        Http::fake(['quotes.test/v1/wallet' => function () use (&$available) {
            return Http::response($this->walletPayload($available));
        }, 'quotes.test/v1/quotes*' => fn (Request $request) => Http::response(
            $request->method() === 'POST' ? $this->pending() : $this->succeeded())]);
        $this->incoming();
        $available = '0.00';
        $this->travel(15)->minutes();
        $this->artisan('cart-quotes:process')->assertExitCode(0);
        $cart = CartQuote::firstOrFail();
        $snapshot = $cart->quotation;
        $this->assertSame('succeeded', $cart->status);
        $this->assertNotNull($cart->pdf_path);
        $this->assertNull($cart->notified_at);
        $this->assertCount(1, $this->sent);
        $available = '2.00';
        $this->travel(61)->seconds();
        $this->artisan('cart-quotes:process')->assertExitCode(0);
        $this->artisan('cart-quotes:process')->assertExitCode(0);
        $this->assertSame($snapshot, $cart->fresh()->quotation);
        $this->assertNotNull($cart->fresh()->notified_at);
        $this->assertCount(2, $this->sent);
        $this->assertCount(2, Http::recorded(fn (Request $request) => ! str_ends_with($request->url(), '/wallet')));
    }

    /** @dataProvider unavailableWallets */
    public function test_unverifiable_wallet_silently_pauses_without_submitting(array $payload, int $status): void
    {
        Setting::findOrFail(1)->update(['shein_pause_on_empty_wallet' => true]);
        Http::fake(['quotes.test/v1/wallet' => Http::response($payload, $status)]);
        $this->incoming();
        $this->assertSame(\App\Services\CartWalletPausedException::UNAVAILABLE, CartQuote::firstOrFail()->last_error);
        $this->assertSame([], $this->sent);
        Http::assertNotSent(fn (Request $request) => $request->method() === 'POST');
    }

    public static function unavailableWallets(): array
    {
        return [[[], 503], [[], 200]];
    }

    public function test_wallet_timeout_pauses_without_exposing_provider_credentials(): void
    {
        Setting::findOrFail(1)->update(['shein_pause_on_empty_wallet' => true]);
        Http::fake(['quotes.test/v1/wallet' => fn () => throw new ConnectionException('private-test-api-key')]);
        $this->incoming();
        $this->assertSame(\App\Services\CartWalletPausedException::UNAVAILABLE, CartQuote::firstOrFail()->last_error);
        $this->assertSame([], $this->sent);
        $this->assertSame(0, CartQuote::firstOrFail()->attempts);
    }

    public function test_provider_insufficient_credit_pauses_even_when_wallet_reported_positive(): void
    {
        Setting::findOrFail(1)->update(['shein_pause_on_empty_wallet' => true]);
        Http::fake(['quotes.test/v1/wallet' => Http::response($this->walletPayload('0.01')),
            'quotes.test/v1/quotes' => Http::response(['error' => 'INSUFFICIENT_FUNDS'], 402)]);
        $this->incoming();
        $cart = CartQuote::firstOrFail();
        $this->assertSame('pending', $cart->status);
        $this->assertSame(\App\Services\CartWalletPausedException::INSUFFICIENT, $cart->last_error);
        $this->assertSame(0, $cart->attempts);
        $this->assertSame([], $this->sent);
    }

    public function test_delayed_quotation_keeps_receiving_session_after_main_number_changes(): void
    {
        $this->prepare();
        $session = 'd8e21afbe77f4d8bbaed65f2c6c2edf7';
        $this->assertSame([$session], $this->sessions);
        $this->assertSame($session, CartQuote::firstOrFail()->incomingMessage->session_uuid);
        Setting::findOrFail(1)->update(['whatsapp_main_session_uuid' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa']);
        $this->readPayload = $this->succeeded();
        $this->travel(15)->minutes();
        $this->artisan('cart-quotes:process')->assertExitCode(0);
        $this->assertSame([$session, $session], $this->sessions);
        $this->assertNotNull(CartQuote::firstOrFail()->notified_at);
    }

    public static function groupSenders(): array
    {
        return [['120363123456789012@g.us'], ['218911234567-1234567890@g.us']];
    }

    /** @dataProvider groupSenders */
    public function test_group_shein_links_are_ignored_without_replies_or_provider_requests(string $sender): void
    {
        $this->incoming(null, 'wamid.group', $sender);
        $this->incoming(null, 'wamid.group', $sender); // Duplicate delivery stays ignored.

        $this->assertDatabaseCount('whatsapp_messagess', 0);
        $this->assertDatabaseCount('cart_quotes', 0);
        $this->assertDatabaseCount('replay_messages', 0);
        $this->assertSame([], $this->sent);
        Http::assertNothingSent();
    }

    public function test_existing_group_messages_cannot_create_quotes_directly(): void
    {
        $incoming = \App\Models\WhatsappMessages::create([
            'message_id' => 'wamid.saved-group',
            'session_uuid' => 'd8e21afbe77f4d8bbaed65f2c6c2edf7',
            'from' => '120363123456789012@g.us',
            'phone' => '218921000000',
            'name' => 'Group',
            'type' => 'text',
            'message' => $this->url,
            'timestamp' => now(),
            'is_reply' => false,
        ]);

        app(CartQuoteService::class)->handleIncoming($incoming, [$this->url]);

        $this->assertDatabaseCount('cart_quotes', 0);
        $this->assertSame([], $this->sent);
        Http::assertNothingSent();
    }

    public static function participantGroupMessages(): array
    {
        return [
            'Hassan production group ID' => ['false_120363201978066889@g.us_3A7EDDC9DD6389F2CB07_184654183321652@lid', []],
            'Hassan second production group' => ['false_120363153461442333@g.us_3A19C770519524B3812F_184654183321652@lid', []],
            'legacy group ID' => ['false_218911234567-1234567890@g.us_3EB08E3AA6B62592B29E73_184654183321652@lid', []],
            'short ID with group chat' => ['3EB08E3AA6B62592B29E73', ['chat_id' => '120363385304666225@g.us']],
            'short ID with group flag' => ['3EB08E3AA6B62592B29E73', ['is_group' => true]],
            'group flag wins over direct chat' => ['3EB08E3AA6B62592B29E73', ['is_group' => true, 'chat_id' => '184654183321652@lid']],
            'group chat wins over false flag' => ['3EB08E3AA6B62592B29E73', ['is_group' => false, 'chat_id' => '120363385304666225@g.us']],
        ];
    }

    /** @dataProvider participantGroupMessages */
    public function test_group_links_from_a_participant_lid_are_ignored(string $id, array $context): void
    {
        foreach ([false, true] as $restricted) {
            config([
                'services.cart_quotes.whatsapp_test_number' => $restricted ? '218916655677' : '',
                'services.cart_quotes.whatsapp_test_sender_id' => '184654183321652',
            ]);
            $this->incoming(id: $id, from: '184654183321652@lid', context: $context);
            $this->incoming(id: $id, from: '184654183321652@lid', context: $context);
        }

        $this->assertDatabaseCount('whatsapp_messagess', 0);
        $this->assertDatabaseCount('cart_quotes', 0);
        $this->assertDatabaseCount('replay_messages', 0);
        $this->assertSame([], $this->sent);
        Http::assertNothingSent();
    }

    public function test_a_direct_lid_cart_with_explicit_chat_context_still_receives_a_reply(): void
    {
        Http::fake(['quotes.test/*' => Http::response($this->pending(), 202)]);
        $this->incoming(id: 'false_184654183321652@lid_3EB08E3AA6B62592B29E73',
            from: '184654183321652@lid', context: ['is_group' => false, 'chat_id' => '184654183321652@lid']);

        $this->assertDatabaseCount('cart_quotes', 1);
        $this->assertCount(1, $this->sent);
        $this->assertSame('184654183321652@lid', $this->sent[0][0]);
        Http::assertSentCount(1);
    }

    public function test_completed_lid_cart_sends_pdf_buttons_and_saves_the_exact_customer_address(): void
    {
        Http::fake(['quotes.test/*' => Http::response($this->succeeded())]);
        $lid = '184654183321652@lid';
        $this->incoming(id: 'wamid.lid-completed', from: $lid,
            context: ['is_group' => false, 'chat_id' => $lid]);
        $cart = CartQuote::firstOrFail();
        $this->assertNotNull($cart->notified_at);
        $this->assertNotNull($cart->whatsapp_queued_at);
        $this->assertNull($cart->last_error);
        $this->assertSame($lid, $cart->whatsapp_customer_phone);
        $this->assertSame($lid, $this->sent[1][0]);
        $this->assertSame($cart->incomingMessage->session_uuid, $cart->whatsapp_session_uuid);
        $this->assertNull($cart->whatsapp_url);
    }

    public function test_existing_lid_cart_recovers_on_scheduled_retry_without_buying_another_quote(): void
    {
        $this->prepare();
        $cart = CartQuote::firstOrFail();
        $cart->incomingMessage->update(['from' => '184654183321652@lid']);
        $cart->update(['last_error' => 'Quotation reply buttons require a valid customer phone and sending session']);
        $this->readPayload = $this->succeeded();
        $this->travel(15)->minutes();
        $this->artisan('cart-quotes:process')->assertExitCode(0);
        $this->assertNotNull($cart->fresh()->notified_at);
        $this->assertNull($cart->fresh()->last_error);
        $this->assertSame('184654183321652@lid', $this->sent[1][0]);
        Http::assertSentCount(2);
    }

    public function test_saved_participant_lid_group_carts_cannot_submit_retry_or_deliver(): void
    {
        $this->prepare();
        $cart = CartQuote::firstOrFail();
        $incoming = $cart->incomingMessage;
        $incoming->update(['from' => '184654183321652@lid', 'is_reply' => false,
            'message_id' => 'false_120363385304666225@g.us_3EB08E3AA6B62592B29E73_184654183321652@lid']);
        $this->sent = [];
        Http::fake();
        $this->travel(61)->seconds();

        app(CartQuoteService::class)->handleIncoming($incoming, [$this->url.'456']);
        $this->artisan('cart-quotes:process')->assertExitCode(0);
        $cart->update(['status' => 'succeeded', 'payload' => $this->succeeded()]);
        $this->artisan('cart-quotes:process')->assertExitCode(0);

        $this->assertDatabaseCount('cart_quotes', 1);
        $this->assertSame([], $this->sent);
        $this->assertNull($cart->fresh()->notified_at);
        $this->assertNull($cart->fresh()->pdf_path);
        $this->assertFalse($incoming->fresh()->is_reply);
        Http::assertNothingSent();
    }

    public function test_existing_group_carts_are_excluded_from_retries_and_final_replies(): void
    {
        $this->prepare();
        $cart = CartQuote::firstOrFail();
        $cart->incomingMessage->update(['from' => '120363123456789012@g.us', 'is_reply' => false]);
        $this->sent = [];
        Http::fake();
        $this->travel(61)->seconds();

        $this->incoming(null, $cart->incomingMessage->message_id, '120363123456789012@g.us');
        $this->artisan('cart-quotes:process')->assertExitCode(0);
        $this->assertSame('submitted', $cart->fresh()->status);
        $cart->update(['status' => 'succeeded', 'payload' => $this->succeeded()]);
        $this->artisan('cart-quotes:process')->assertExitCode(0);

        $this->assertSame([], $this->sent);
        $this->assertNull($cart->fresh()->notified_at);
        $this->assertFalse($cart->incomingMessage->fresh()->is_reply);
        $this->assertNull($cart->fresh()->pdf_path);
        Http::assertNothingSent();
    }

    public static function restrictedSenders(): array
    {
        return [['218916655677'], ['+218916655677']];
    }

    /** @dataProvider restrictedSenders */
    public function test_restricted_trial_delivers_only_to_the_test_sender(string $sender): void
    {
        config(['services.cart_quotes.whatsapp_test_number' => '+218916655677']);
        Http::fake(['quotes.test/*' => fn (Request $request) => Http::response(
            $request->method() === 'POST' ? $this->pending() : $this->succeeded(),
            $request->method() === 'POST' ? 202 : 200
        )]);
        $this->incoming(null, 'wamid.allowed', $sender);
        $this->assertSame([[$sender, CartQuoteService::ACKNOWLEDGEMENT]], $this->sent);
        $this->travel(15)->minutes();
        $this->artisan('cart-quotes:process')->assertExitCode(0);
        $this->assertSame([ltrim($sender, '+'), $this->completionMessage()], $this->sent[1]);
        $this->assertNotNull(CartQuote::first()->notified_at);
        Http::assertSentCount(2);
    }

    public function test_restricted_trial_ignores_other_senders_even_if_business_number_matches(): void
    {
        config(['services.cart_quotes.whatsapp_test_number' => '218916655677']);
        $this->incoming(null, 'wamid.other', '218911234567', '218916655677');
        $this->incoming('Hello', 'wamid.other-text', '218911234567');
        $this->assertDatabaseCount('cart_quotes', 0);
        $this->assertDatabaseCount('whatsapp_messagess', 0);
        $this->assertSame([], $this->sent);
        Http::assertNothingSent();
    }

    public function test_restricted_trial_ignores_non_shein_text_from_test_sender(): void
    {
        config(['services.cart_quotes.whatsapp_test_number' => '218916655677']);
        $this->incoming('Hello https://example.com/cart', 'wamid.not-shein', '218916655677');
        $this->assertDatabaseCount('cart_quotes', 0);
        $this->assertDatabaseCount('whatsapp_messagess', 0);
        $this->assertSame([], $this->sent);
        Http::assertNothingSent();
    }

    public static function senderIds(): array
    {
        return [['71184955744465'], ['71184955744465@lid']];
    }

    /** @dataProvider senderIds */
    public function test_test_sender_id_is_linked_to_phone_for_quotes_and_replies(string $sender): void
    {
        config(['services.cart_quotes.whatsapp_test_number' => '+218916655677',
            'services.cart_quotes.whatsapp_test_sender_id' => '71184955744465']);
        Http::fake(['quotes.test/*' => fn (Request $request) => Http::response(
            $request->method() === 'POST' ? $this->pending() : $this->succeeded(),
            $request->method() === 'POST' ? 202 : 200
        )]);
        $this->incoming(null, 'wamid.lid', $sender);
        $cart = CartQuote::firstOrFail();
        $this->assertSame('218916655677', $cart->incomingMessage->from);
        $this->assertSame('https://wa.me/218916655677', $cart->whatsapp_url);
        $this->assertSame([['218916655677', CartQuoteService::ACKNOWLEDGEMENT]], $this->sent);
        $this->incoming(null, 'wamid.lid', '218916655677');
        $this->assertDatabaseCount('cart_quotes', 1);
        Http::assertSentCount(1);
        $this->travel(15)->minutes();
        $this->artisan('cart-quotes:process')->assertExitCode(0);
        $this->assertSame(['218916655677', $this->completionMessage()], $this->sent[1]);
        $this->assertNotNull($cart->fresh()->notified_at);
    }

    public function test_unrelated_or_malformed_sender_ids_remain_blocked(): void
    {
        config(['services.cart_quotes.whatsapp_test_number' => '218916655677',
            'services.cart_quotes.whatsapp_test_sender_id' => '71184955744465']);
        foreach (['71184955744466@lid', '71184955744465@other', '+71184955744465', 'prefix71184955744465@lid'] as $sender) {
            $this->incoming(null, 'wamid.blocked.'.$sender, $sender);
        }
        $this->incoming('Hello', 'wamid.lid-not-shein', '71184955744465@lid');
        $this->assertDatabaseCount('cart_quotes', 0);
        $this->assertDatabaseCount('whatsapp_messagess', 0);
        $this->assertSame([], $this->sent);
        Http::assertNothingSent();
    }

    public function test_existing_sender_id_cart_is_delivered_to_the_configured_phone(): void
    {
        $this->prepare();
        $cart = CartQuote::firstOrFail();
        $cart->incomingMessage->update(['from' => '71184955744465@lid']);
        config(['services.cart_quotes.whatsapp_test_number' => '218916655677',
            'services.cart_quotes.whatsapp_test_sender_id' => '71184955744465']);
        $this->readPayload = $this->succeeded();
        $this->travel(15)->minutes();
        $this->artisan('cart-quotes:process')->assertExitCode(0);
        $this->assertSame(['218916655677', $this->completionMessage()], $this->sent[1]);
        $this->assertNotNull($cart->fresh()->notified_at);
    }

    public function test_restricted_trial_blocks_existing_other_sender_retries_and_final_replies(): void
    {
        $this->prepare();
        $cart = CartQuote::firstOrFail();
        config(['services.cart_quotes.whatsapp_test_number' => '218916655677']);
        $this->incoming(); // Duplicate delivery cannot bypass the new restriction.
        $this->travel(61)->seconds();
        $this->artisan('cart-quotes:process')->assertExitCode(0);
        Http::assertSentCount(1);
        $this->assertCount(1, $this->sent);
        $cart->update(['status' => 'succeeded', 'payload' => $this->succeeded()]);
        $this->artisan('cart-quotes:process')->assertExitCode(0);
        Http::assertSentCount(1);
        $this->assertCount(1, $this->sent);
        $this->assertNull($cart->fresh()->notified_at);
    }

    private function signedCallback(string $type = 'quote.succeeded', array $headers = [], ?string $body = null)
    {
        $body ??= json_encode(['id' => 'event-1', 'type' => $type, 'data' => ['quoteId' => $this->quoteId, 'status' => substr($type, 6), 'billingStatus' => $type === 'quote.succeeded' ? 'captured' : 'released', 'errorCode' => null]]);
        $timestamp = (string) now()->timestamp;
        $defaults = [
            'HTTP_WEBHOOK_ID' => 'event-1',
            'HTTP_WEBHOOK_TIMESTAMP' => $timestamp,
            'HTTP_WEBHOOK_KEY_VERSION' => '1',
            'HTTP_WEBHOOK_SIGNATURE' => 'v1='.hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test_secret'),
            'CONTENT_TYPE' => 'application/json',
        ];
        return $this->call('POST', '/hooks/cart-quotes', [], [], [], array_merge($defaults, $headers), $body);
    }

    public function test_acknowledges_submits_fetches_and_delivers_exact_lyd_total_once(): void
    {
        $this->prepare();
        $this->assertSame([['218911234567', CartQuoteService::ACKNOWLEDGEMENT]], $this->sent);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'https://quotes.test/v1/quotes'
            && $request->hasHeader('Authorization', 'Bearer test-api-key')
            && $request['sourceUrl'] === $this->url
            && $request->header('Idempotency-Key')[0] === CartQuote::first()->idempotency_key);

        $this->signedCallback()->assertOk();
        Http::assertSentCount(1); // Callback only saves work; no network or delivery.
        $this->assertCount(1, $this->sent);
        $this->readPayload = $this->succeeded();
        $this->artisan('cart-quotes:process')->assertExitCode(0);
        $this->assertSame(['218911234567', $this->completionMessage()], $this->sent[1]);
        $this->assertNotNull(CartQuote::first()->notified_at);
        $this->assertDatabaseHas('replay_messages', ['message' => CartQuoteService::ACKNOWLEDGEMENT."\n\n".$this->sent[1][1]]);
        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && $request->url() === 'https://quotes.test/v1/quotes/'.$this->quoteId);

        $this->incoming();
        $this->signedCallback()->assertOk();
        $this->artisan('cart-quotes:process')->assertExitCode(0);
        $this->assertCount(2, $this->sent);
        $this->assertDatabaseCount('cart_quotes', 1);
        $this->assertDatabaseCount('cart_quote_webhooks', 1);
        Http::assertSentCount(2);
    }

    public function test_submission_retries_keep_the_same_idempotency_key(): void
    {
        Http::fake(['quotes.test/*' => Http::sequence()->push([], 503)->push($this->pending(), 202)]);
        $this->incoming();
        $this->assertSame('pending', CartQuote::first()->status);
        $key = CartQuote::first()->idempotency_key;
        $this->travel(61)->seconds();
        $this->artisan('cart-quotes:process')->assertExitCode(0);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => $request->header('Idempotency-Key')[0] === $key);
        foreach (Http::recorded() as [$request, $response]) {
            $this->assertSame($key, $request->header('Idempotency-Key')[0]);
        }
        $this->assertSame($this->quoteId, CartQuote::first()->quote_id);
        $this->assertCount(1, $this->sent);
    }

    public function test_acknowledgement_uses_the_editable_shein_setting_once(): void
    {
        Setting::findOrFail(1)->update(['shein_received_message' => 'استلمنا سلتك وسنجهز السعر']);
        $this->prepare();
        $this->incoming();
        $this->assertSame([['218911234567', 'استلمنا سلتك وسنجهز السعر']], $this->sent);
        $this->assertDatabaseHas('replay_messages', ['message' => 'استلمنا سلتك وسنجهز السعر']);
        Http::assertSentCount(1);
    }

    public function test_timeout_after_submission_reuses_the_key_and_does_not_repeat_acknowledgement(): void
    {
        $calls = [];
        Http::fake(function (Request $request) use (&$calls) {
            $calls[] = [$request->header('Idempotency-Key')[0], $request['sourceUrl']];
            if (count($calls) === 1) {
                throw new ConnectionException('Simulated timeout after the provider accepted the cart');
            }
            return Http::response($this->pending(), 202);
        });
        $this->incoming();
        $this->incoming(); // A webhook replay during backoff must not retry early.
        $this->assertCount(1, $calls);
        $this->travel(31)->seconds();
        $this->artisan('cart-quotes:process')->assertExitCode(0);
        $this->assertCount(2, $calls);
        $this->assertSame($calls[0], $calls[1]);
        $this->assertCount(1, $this->sent);
        $this->assertDatabaseCount('cart_quotes', 1);
    }

    public function test_capacity_retry_after_is_honored_and_missing_id_is_retryable(): void
    {
        Http::fake(['quotes.test/*' => Http::sequence()
            ->push([], 503, ['Retry-After' => '120'])
            ->push(['status' => 'pending'], 202)
            ->push($this->pending(), 202)]);
        $this->incoming();
        $this->travel(61)->seconds();
        $this->artisan('cart-quotes:process')->assertExitCode(0);
        Http::assertSentCount(1);
        $this->travel(60)->seconds();
        $this->artisan('cart-quotes:process')->assertExitCode(0);
        $this->assertNull(CartQuote::first()->quote_id);
        $this->assertSame('Quote API response is missing its quote ID', CartQuote::first()->last_error);
        $this->travel(61)->seconds();
        $this->artisan('cart-quotes:process')->assertExitCode(0);
        $this->assertSame($this->quoteId, CartQuote::first()->quote_id);
        foreach (Http::recorded() as [$request]) {
            $this->assertSame(CartQuote::first()->idempotency_key, $request->header('Idempotency-Key')[0]);
        }
    }

    public function test_balance_failure_is_retried_and_cart_validation_failure_is_terminal(): void
    {
        Http::fake(['quotes.test/*' => Http::sequence()->push(['error' => 'INSUFFICIENT_FUNDS'], 402)
            ->push(['error' => 'INVALID_URL'], 400)]);
        $this->incoming();
        $this->assertSame('pending', CartQuote::first()->status);
        $this->assertSame([], $this->sent);
        $this->travel(31)->seconds();
        $this->artisan('cart-quotes:process')->assertExitCode(0);
        $this->assertSame('failed', CartQuote::first()->status);
        $this->assertNull(CartQuote::first()->notified_at);
        $this->assertNull(CartQuote::first()->next_attempt_at);
        $this->assertSame([], $this->sent);
        $this->incoming();
        $this->artisan('cart-quotes:process')->assertExitCode(0);
        Http::assertSentCount(2);
        $this->assertSame([], $this->sent);
    }

    public function test_failed_acknowledgement_is_retried_without_resubmission(): void
    {
        $this->sendResults = [false, true];
        Http::fake(['quotes.test/*' => Http::response($this->pending(), 202)]);
        $this->incoming();
        Http::assertSentCount(1);
        $this->assertDatabaseHas('whatsapp_messagess', ['is_reply' => false]);
        $this->travel(31)->seconds();
        $this->artisan('cart-quotes:process')->assertExitCode(0);
        Http::assertSentCount(2);
        $this->assertCount(1, Http::recorded(fn (Request $request) => $request->method() === 'POST'));
        $this->assertSame($this->quoteId, CartQuote::first()->quote_id);
        $this->assertCount(2, $this->sent);
    }

    public function test_failed_final_delivery_preserves_result_and_retries_without_resubmission(): void
    {
        $this->prepare();
        $this->signedCallback()->assertOk();
        $this->sendResults = [false, true];
        $this->readPayload = $this->succeeded();
        $this->artisan('cart-quotes:process')->assertExitCode(0);
        $this->assertSame('succeeded', CartQuote::first()->status);
        $this->assertNull(CartQuote::first()->notified_at);
        $snapshot = CartQuote::first()->quotation;
        $this->assertNotNull(CartQuote::first()->pdf_path);
        Setting::findOrFail(1)->update(['usd_to_lyd_rate' => '10', 'shein_company_percentage' => '50',
            'shein_completed_message' => 'Changed after the failed delivery']);
        $this->travel(31)->seconds();
        $this->artisan('cart-quotes:process')->assertExitCode(0);
        $this->assertNotNull(CartQuote::first()->notified_at);
        Http::assertSentCount(2);
        $this->assertCount(3, $this->sent);
        $this->assertSame($this->sent[1], $this->sent[2]);
        $this->assertSame($snapshot, CartQuote::first()->quotation);
    }

    public function test_polls_if_callback_is_lost(): void
    {
        $this->prepare();
        $this->readPayload = $this->succeeded();
        $this->travel(15)->minutes();
        $this->artisan('cart-quotes:process')->assertExitCode(0);
        $this->assertCount(2, $this->sent);
        $this->assertNotNull(CartQuote::first()->notified_at);
    }

    /** @dataProvider terminalStatuses */
    public function test_terminal_events_notify_failures_and_expiry_but_leave_cancellation_silent(string $status): void
    {
        $this->prepare();
        $this->signedCallback('quote.'.$status)->assertOk();
        $this->readPayload = ['id' => $this->quoteId, 'status' => $status, 'result' => null];
        $this->artisan('cart-quotes:process')->assertExitCode(0);
        $this->assertCount($status === 'cancelled' ? 1 : 2, $this->sent);
        $this->assertSame($status, CartQuote::first()->status);
        $this->assertNotNull(CartQuote::first()->notified_at);
        $this->assertNull(CartQuote::first()->next_attempt_at);
    }

    public static function terminalStatuses(): array
    {
        return [['failed'], ['cancelled'], ['expired']];
    }

    /** @dataProvider badHeaders */
    public function test_rejects_invalid_signature_timestamp_or_missing_key_version(array $headers): void
    {
        $this->prepare();
        $this->signedCallback(headers: $headers)->assertStatus(401);
        $this->assertDatabaseCount('cart_quote_webhooks', 0);
        Http::assertSentCount(1);
    }

    public static function badHeaders(): array
    {
        return [
            [['HTTP_WEBHOOK_SIGNATURE' => 'v1='.str_repeat('0', 64)]],
            [['HTTP_WEBHOOK_TIMESTAMP' => '1000000000']],
            [['HTTP_WEBHOOK_TIMESTAMP' => '9999999999']],
            [['HTTP_WEBHOOK_KEY_VERSION' => '']],
            [['HTTP_WEBHOOK_SIGNATURE' => '']],
        ];
    }

    public function test_signature_uses_raw_bytes_not_reserialized_json(): void
    {
        $this->prepare();
        $body = "{\n \"type\": \"quote.succeeded\", \"data\": {\"quoteId\": \"{$this->quoteId}\"}\n}";
        $this->signedCallback(body: $body)->assertOk();
        $this->assertDatabaseCount('cart_quote_webhooks', 1);
        $wrongSignature = 'v1='.hash_hmac('sha256', now()->timestamp.'.'.json_encode(json_decode($body, true)), 'whsec_test_secret');
        $this->signedCallback(headers: ['HTTP_WEBHOOK_SIGNATURE' => $wrongSignature], body: $body)->assertStatus(401);
    }

    public function test_rejects_valid_hmac_outside_the_five_minute_clock_window(): void
    {
        $this->prepare();
        $body = json_encode(['type' => 'quote.succeeded', 'data' => ['quoteId' => $this->quoteId]]);
        foreach ([-301, 301] as $offset) {
            $timestamp = (string) (now()->timestamp + $offset);
            $this->signedCallback(headers: [
                'HTTP_WEBHOOK_TIMESTAMP' => $timestamp,
                'HTTP_WEBHOOK_SIGNATURE' => 'v1='.hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test_secret'),
            ], body: $body)->assertStatus(401);
        }
        $this->assertDatabaseCount('cart_quote_webhooks', 0);
    }

    public function test_duplicate_notification_does_not_reset_retry_backoff(): void
    {
        $this->prepare();
        $this->signedCallback()->assertOk();
        $this->readPayload = $this->pending();
        $this->artisan('cart-quotes:process')->assertExitCode(0);
        $next = CartQuote::first()->next_attempt_at->toISOString();
        $this->assertDatabaseMissing('cart_quote_webhooks', ['processed_at' => null]);
        $this->signedCallback()->assertOk();
        $this->artisan('cart-quotes:process')->assertExitCode(0);
        $this->assertSame($next, CartQuote::first()->next_attempt_at->toISOString());
        Http::assertSentCount(2);
    }

    public function test_unknown_quote_is_acknowledged_and_reused_event_id_cannot_replace_event(): void
    {
        $this->signedCallback()->assertOk();
        $this->prepare();
        $this->signedCallback()->assertOk();
        $this->signedCallback('quote.failed')->assertOk();
        $this->assertDatabaseCount('cart_quote_webhooks', 1);
        $this->assertSame('submitted', CartQuote::first()->status);
    }

    public function test_rejects_missing_secret_and_malformed_signed_payload(): void
    {
        config(['services.cart_quotes.webhook_secret' => null]);
        $this->signedCallback()->assertStatus(401);
        config(['services.cart_quotes.webhook_secret' => 'whsec_test_secret']);
        $this->signedCallback(body: '{bad json')->assertStatus(422);
        $this->signedCallback(body: '{"type":"quote.succeeded","data":{"id":"wrong-field"}}')->assertStatus(422);
    }

    public function test_does_not_send_incomplete_or_float_money_results(): void
    {
        $this->prepare();
        $this->signedCallback()->assertOk();
        $result = $this->succeeded();
        $result['result']['total']['amount'] = 447.821;
        $this->readPayload = $result;
        $this->artisan('cart-quotes:process')->assertExitCode(0);
        $this->assertNull(CartQuote::first()->notified_at);
        $this->assertCount(1, $this->sent);
    }

    public function test_handles_multiple_unique_urls_with_one_acknowledgement(): void
    {
        Http::fake(['quotes.test/*' => Http::sequence()
            ->push($this->pending(), 202)
            ->push(array_merge($this->pending(), ['id' => 'second-quote']), 202)]);
        $this->incoming($this->url.' '.$this->url.' https://www.shein.com/cart?x=2');
        $this->assertCount(1, $this->sent);
        $this->assertDatabaseCount('cart_quotes', 2);
        Http::assertSentCount(2);
    }

    private function longProductMessage(): string
    {
        return <<<'MESSAGE'
Jin Bei Qi Women's Gold Fan-Shaped Sequin Beaded Elegant High-End Ball Stiletto Heels, Luxury Style Open Toe Wedding Shoes I discovered amazing products on SHEIN.com, come check them out! https://onelink.shein.com/54/63jymmvlnyo0?shc=2_Rvx57M0o70S
1pc Vintage Metal Coin Pendant Long Sweater Necklace, Party/Wedding Decoration, Holiday Gift I discovered amazing products on SHEIN.com, come check them out! https://onelink.shein.com/54/63jyr4o4ucui?shc=2_Rvx5rJ9pFIh
MESSAGE;
    }

    public function test_product_share_links_get_one_editable_arabic_reply_without_provider_submissions(): void
    {
        $message = 'أضف المنتجات إلى سلة شي إن وأرسل لنا رابط السلة.';
        Setting::findOrFail(1)->update(['shein_product_message' => $message]);
        $destination = 'sheinlink://applink/goods?data='.rawurlencode('{"goods_id":"524567449"}');
        Http::fake(['https://onelink.shein.com/54/*' => fn () => Http::response('<input id="deeplink" value="'.e($destination).'">')]);
        $text = $this->longProductMessage();
        $this->incoming($text);
        $this->assertDatabaseCount('cart_quotes', 0);
        $this->assertDatabaseHas('whatsapp_messagess', ['message' => $text]);
        $this->assertSame([['218911234567', $message]], $this->sent);
        $this->assertDatabaseHas('replay_messages', ['message' => $message]);
        $this->assertNotNull(\App\Models\WhatsappMessages::first()->shein_product_reply_sent_at);
        $this->incoming($text);
        Http::assertSentCount(2);
        Http::assertNotSent(fn (Request $request) => str_starts_with($request->url(), 'https://quotes.test'));
        $this->assertCount(1, $this->sent);
    }

    private function longCartMessage(): string
    {
        return str_replace([
            'https://onelink.shein.com/54/63jymmvlnyo0?shc=2_Rvx57M0o70S',
            'https://onelink.shein.com/54/63jyr4o4ucui?shc=2_Rvx5rJ9pFIh',
        ], ['https://m.shein.com/cart/share/landing?group_id=1',
            'https://m.shein.com/cart/share/landing?group_id=2'], $this->longProductMessage());
    }

    public function test_mixed_message_sends_only_the_cart_to_the_provider_and_replies_to_products_once(): void
    {
        Http::fake(['quotes.test/*' => Http::response($this->pending(), 202)]);
        $text = $this->url.' https://shein.com/Dress-p-12345.html';
        $this->incoming($text);
        $this->incoming($text);
        $this->assertDatabaseCount('cart_quotes', 1);
        $this->assertSame($this->url, CartQuote::first()->source_url);
        Http::assertSentCount(1);
        $this->assertSame([
            ['218911234567', CartQuoteService::ACKNOWLEDGEMENT],
            ['218911234567', Setting::PRODUCT_MESSAGE],
        ], $this->sent);
        $this->assertSame(['d8e21afbe77f4d8bbaed65f2c6c2edf7', 'd8e21afbe77f4d8bbaed65f2c6c2edf7'], $this->sessions);
        $this->assertDatabaseHas('replay_messages', ['message' => CartQuoteService::ACKNOWLEDGEMENT."\n\n".Setting::PRODUCT_MESSAGE]);
    }

    public function test_mixed_message_keeps_product_guidance_when_cart_acknowledgement_is_retried(): void
    {
        Http::fake(['quotes.test/*' => Http::sequence()->push('', 503)->push($this->pending(), 202)]);
        $text = $this->url.' https://shein.com/Dress-p-12345.html';
        $this->incoming($text);
        $this->assertSame([['218911234567', Setting::PRODUCT_MESSAGE]], $this->sent);
        $this->assertFalse(\App\Models\WhatsappMessages::first()->is_reply);
        $this->travel(61)->seconds();
        $this->artisan('cart-quotes:process')->assertExitCode(0);
        $this->incoming($text);
        $this->assertSame([
            ['218911234567', Setting::PRODUCT_MESSAGE],
            ['218911234567', CartQuoteService::ACKNOWLEDGEMENT],
        ], $this->sent);
        $this->assertDatabaseHas('replay_messages', ['message' => Setting::PRODUCT_MESSAGE."\n\n".CartQuoteService::ACKNOWLEDGEMENT]);
    }

    public function test_product_reply_does_not_depend_on_the_paid_provider_wallet(): void
    {
        Setting::findOrFail(1)->update(['shein_pause_on_empty_wallet' => true]);
        $this->incoming('https://shein.com/Dress-p-12345.html');
        $this->assertSame([['218911234567', Setting::PRODUCT_MESSAGE]], $this->sent);
        Http::assertNothingSent();
        $this->assertDatabaseCount('cart_quotes', 0);
    }

    public function test_failed_product_reply_is_retried_on_webhook_redelivery(): void
    {
        $this->sendResults = [false, true];
        $this->incoming('https://shein.com/Dress-p-12345.html', expectedStatus: 500);
        $this->assertNull(\App\Models\WhatsappMessages::first()->shein_product_reply_sent_at);
        $this->incoming('https://shein.com/Dress-p-12345.html');
        $this->incoming('https://shein.com/Dress-p-12345.html');
        $this->assertCount(2, $this->sent);
        $this->assertDatabaseCount('replay_messages', 1);
        $this->assertNotNull(\App\Models\WhatsappMessages::first()->shein_product_reply_sent_at);
        Http::assertNothingSent();
    }

    public function test_unresolved_short_link_never_reaches_the_paid_provider(): void
    {
        Http::fake(['https://onelink.shein.com/54/unknown' => Http::response('', 503)]);
        $this->incoming('https://onelink.shein.com/54/unknown');
        $this->assertSame([['218911234567', Setting::PRODUCT_MESSAGE]], $this->sent);
        $this->assertDatabaseCount('cart_quotes', 0);
        Http::assertNotSent(fn (Request $request) => str_starts_with($request->url(), 'https://quotes.test'));
    }

    public function test_scheduled_pending_product_records_cannot_bypass_cart_classification(): void
    {
        $this->incoming('https://shein.com/Dress-p-12345.html');
        $cart = CartQuote::create(['whatsapp_messages_id' => \App\Models\WhatsappMessages::first()->id,
            'source_url' => 'https://shein.com/Dress-p-12345.html', 'idempotency_key' => 'legacy-product',
            'status' => 'pending', 'next_attempt_at' => now()]);
        $this->artisan('cart-quotes:process')->assertExitCode(0);
        $this->assertSame('failed', $cart->fresh()->status);
        $this->assertNull($cart->fresh()->next_attempt_at);
        $this->assertCount(1, $this->sent);
        Http::assertNothingSent();
    }

    public static function invalidLinkPositions(): array
    {
        return [[400, 0], [422, 0], [400, 1], [422, 1]];
    }

    /** @dataProvider invalidLinkPositions */
    public function test_long_message_skips_invalid_link_and_delivers_valid_link(int $status, int $position): void
    {
        $sequence = Http::sequence();
        for ($index = 0; $index < 2; $index++) {
            $index === $position
                ? $sequence->push(['error' => 'INVALID_URL'], $status)
                : $sequence->push($this->succeeded(), 200);
        }
        Http::fake(['quotes.test/*' => $sequence]);
        $text = $this->longCartMessage().' https://example.com/cart https://shein.com.evil.test/cart';
        $this->incoming($text);

        $this->assertDatabaseCount('cart_quotes', 2);
        $this->assertSame(1, CartQuote::where('status', 'succeeded')->whereNotNull('notified_at')->count());
        $rejected = CartQuote::where('status', 'failed')->firstOrFail();
        $this->assertNull($rejected->notified_at);
        $this->assertNull($rejected->next_attempt_at);
        $this->assertSame([
            ['218911234567', CartQuoteService::ACKNOWLEDGEMENT],
            ['218911234567', $this->completionMessage()],
        ], $this->sent);

        $this->incoming($text);
        $this->artisan('cart-quotes:process')->assertExitCode(0);
        Http::assertSentCount(2);
        $this->assertCount(2, $this->sent);
    }

    public function test_long_message_with_only_rejected_links_gets_no_reply(): void
    {
        Http::fake(['quotes.test/*' => Http::sequence()
            ->push(['error' => 'INVALID_URL'], 400)
            ->push(['error' => 'INVALID_URL'], 422)]);
        $this->incoming($this->longCartMessage());
        $this->incoming($this->longCartMessage());
        $this->artisan('cart-quotes:process')->assertExitCode(0);

        $this->assertSame([], $this->sent);
        $this->assertDatabaseCount('replay_messages', 0);
        $this->assertSame(2, CartQuote::where('status', 'failed')->whereNull('next_attempt_at')->count());
        Http::assertSentCount(2);
    }

    public function test_only_accepts_allowed_shein_hosts_and_trims_chat_punctuation(): void
    {
        $extractor = app(SheinUrlExtractor::class);
        $this->assertSame([$this->url], $extractor->extract('('.$this->url.'), '.$this->url.'، '
            .'https://shein.com.evil.test/cart https://fake-shein.com/cart '
            .'https://shein.com@evil.test/cart https://user@shein.com/cart https://shein.com:8443/cart'));
        config(['services.cart_quotes.shein_hosts' => ['shein.com', 'shein.top', 'shein.co.uk']]);
        $this->assertSame(['https://shein.top/cart123', 'https://www.shein.co.uk/cart', 'https://shein.com/cart?name=عربي'],
            $extractor->extract('https://shein.top/cart123 https://www.shein.co.uk/cart https://shein.com/cart?name=عربي،'));
    }
}
