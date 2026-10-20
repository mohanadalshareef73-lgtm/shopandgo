<?php

namespace Tests\Feature;

use App\Models\CartQuote;
use App\Models\CartStatusAudit;
use App\Models\Setting;
use App\Models\WhatsappMessages;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class CartWhatsAppButtonsTest extends TestCase
{
    use RefreshDatabase;

    private const SESSION = 'cf2859dd56474dd69db06308afde6d92';

    private const PHONE = '218916655677';

    private const TOKEN = 'test-webhook-token-long-secret-32-characters';

    private array $responseStatuses = [];

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Http::fake(['whatsapp.test/*' => fn () => Http::response([], array_shift($this->responseStatuses) ?? 202)]);
        config(['services.whatsapp.api_url' => 'https://whatsapp.test/api',
            'services.whatsapp.api_token' => 'api-secret', 'services.whatsapp.webhook_token' => self::TOKEN]);
        $this->mock(GeminiService::class, fn ($mock) => $mock->shouldReceive('respond')->never());
    }

    private function cart(array $attributes = []): CartQuote
    {
        $incoming = WhatsappMessages::create(['message_id' => 'wamid.source', 'from' => self::PHONE,
            'session_uuid' => self::SESSION, 'phone' => '218920703777', 'name' => 'Test customer',
            'type' => 'text', 'message' => 'Shared cart', 'timestamp' => now(), 'is_reply' => true]);

        return CartQuote::create(array_merge(['whatsapp_messages_id' => $incoming->id,
            'source_url' => 'https://shein.com/cart', 'idempotency_key' => 'button-test-cart',
            'status' => 'succeeded', 'whatsapp_cart_reference' => 'CART-1042',
            'whatsapp_customer_phone' => self::PHONE, 'whatsapp_session_uuid' => self::SESSION,
            'whatsapp_queued_at' => now(), 'notified_at' => now()], $attributes));
    }

    private function payload(string $action = 'confirm', string $event = 'wamid.tap', string $reference = 'CART-1042',
        string $phone = self::PHONE, string $session = self::SESSION, string $context = 'wamid.pdf'): array
    {
        return ['entry' => [['id' => $session, 'changes' => [['value' => [
            'metadata' => ['phone_number_id' => 'shop2go10267'],
            'messages' => [['id' => $event, 'from' => $phone, 'type' => 'interactive',
                'context' => ['id' => $context], 'interactive' => ['type' => 'button_reply',
                    'button_reply' => ['id' => $action.':'.$reference, 'title' => 'Untrusted label']]]],
        ]]]]]];
    }

    private function tap(string $action, string $event = 'wamid.tap', string $reference = 'CART-1042',
        string $phone = self::PHONE, string $session = self::SESSION, string $context = 'wamid.pdf'): void
    {
        $this->postJson('/api/webhook/whatsapp?token='.self::TOKEN,
            $this->payload($action, $event, $reference, $phone, $session, $context))->assertOk();
    }

    private function process(): void
    {
        $this->artisan('cart-quotes:process-replies')->assertExitCode(0);
    }

    /** @dataProvider amazonActions */
    public function test_amazon_buttons_use_the_same_receipts_security_audits_and_terminal_states(string $action, string $status): void
    {
        $cart = $this->cart(['retailer' => 'amazon', 'source_url' => 'https://amazon.ae/dp/B0DF4NM7CY']);
        $this->tap($action, 'wrong-customer', phone: '218912222222');
        $this->tap($action, 'wrong-session', session: 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');
        $this->process();
        $this->assertSame('open', $cart->fresh()->customer_status);
        Http::assertNothingSent();
        $this->tap($action);
        $this->process();
        $this->assertSame($status, $cart->fresh()->customer_status);
        $this->assertSame($status, $cart->fresh()->operator_status);
        $this->assertDatabaseHas('cart_status_audits', ['cart_quote_id' => $cart->id, 'new_status' => $status,
            'actor_type' => 'customer', 'actor_phone' => self::PHONE, 'session_uuid' => self::SESSION]);
        Http::assertSentCount(1);
        $this->tap($action);
        $this->process();
        Http::assertSentCount(1);
        $this->assertDatabaseCount('cart_status_audits', 1);
        if ($action !== 'edit') {
            $this->tap('edit', 'late-edit');
            $this->process();
            $this->assertSame($status, $cart->fresh()->customer_status);
            Http::assertSentCount(1);
        }
    }

    public static function amazonActions(): array
    {
        return [['confirm', 'confirmed'], ['edit', 'editing'], ['cancel', 'cancelled']];
    }

    public function test_acknowledges_before_processing_and_confirms_via_the_original_session(): void
    {
        $this->freezeTime();
        $cart = $this->cart();
        $this->tap('confirm');
        Http::assertNothingSent();
        $this->assertSame('open', $cart->fresh()->customer_status);
        $this->assertDatabaseHas('cart_whatsapp_button_events', ['event_id' => 'wamid.tap', 'processed_at' => null]);
        $this->process();
        $this->assertSame('confirmed', $cart->fresh()->customer_status);
        $this->assertSame('confirmed', $cart->fresh()->operator_status);
        $this->assertSame('مؤكدة', $cart->fresh()->pdf_stamp);
        $this->assertDatabaseHas('cart_status_audits', ['cart_quote_id' => $cart->id,
            'previous_status' => 'new', 'new_status' => 'confirmed',
            'previous_customer_status' => 'open', 'new_customer_status' => 'confirmed',
            'actor_type' => 'customer', 'actor_name' => 'Test customer', 'actor_user_id' => null,
            'actor_phone' => self::PHONE, 'session_uuid' => self::SESSION, 'event_id' => 'wamid.tap']);
        $this->assertTrue($cart->statusAudits()->first()->changed_at->equalTo(now()->startOfSecond()));
        $this->assertSame('succeeded', $cart->fresh()->status);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://whatsapp.test/api/message'
            && $request->hasHeader('X-API-Key', 'api-secret') && $request['session_uuid'] === self::SESSION
            && $request['phone'] === [self::PHONE] && str_contains($request['message'], 'تم تأكيد طلبك CART-1042'));
        $this->assertDatabaseHas('cart_whatsapp_button_events', ['event_id' => 'wamid.tap', 'outcome' => 'reply_queued']);
    }

    public function test_edit_starts_quantity_change_followup_and_sends_instructions(): void
    {
        $cart = $this->cart();
        $this->tap('edit');
        $this->process();
        $this->assertSame('editing', $cart->fresh()->customer_status);
        $this->assertSame('editing', $cart->fresh()->operator_status);
        $this->assertDatabaseHas('cart_status_audits', ['new_status' => 'editing', 'actor_type' => 'customer']);
        Http::assertSent(fn (Request $request) => str_contains($request['message'], 'رقم كل قطعة من ملف PDF')
            && $request['session_uuid'] === self::SESSION);
    }

    public function test_cancel_records_customer_cancellation_without_destroying_the_quote(): void
    {
        $cart = $this->cart();
        $this->tap('cancel');
        $this->process();
        $this->assertSame('cancelled', $cart->fresh()->customer_status);
        $this->assertSame('cancelled', $cart->fresh()->operator_status);
        $this->assertDatabaseHas('cart_status_audits', ['new_status' => 'cancelled', 'actor_type' => 'customer']);
        $this->assertSame('succeeded', $cart->fresh()->status);
        Http::assertSent(fn (Request $request) => str_contains($request['message'], 'تم إلغاء طلبك CART-1042'));
    }

    /** @dataProvider customReplies */
    public function test_button_actions_use_saved_replies_and_still_update_status_and_audit(string $action, string $template,
        string $expected, string $status): void
    {
        Setting::findOrFail(1)->update(['shein_'.$action.'_reply' => $template]);
        $cart = $this->cart();
        $this->tap($action);
        $this->process();
        $this->assertSame($status, $cart->fresh()->customer_status);
        $this->assertSame($status, $cart->fresh()->operator_status);
        $this->assertDatabaseHas('cart_status_audits', ['cart_quote_id' => $cart->id,
            'new_status' => $status, 'actor_type' => 'customer', 'event_id' => 'wamid.tap']);
        $this->assertDatabaseHas('cart_whatsapp_button_events', ['event_id' => 'wamid.tap', 'reply_text' => $expected]);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request['message'] === $expected
            && $request['phone'] === [self::PHONE] && $request['session_uuid'] === self::SESSION);
        $this->tap($action);
        $this->process();
        Http::assertSentCount(1);
        $this->assertDatabaseCount('cart_status_audits', 1);
    }

    public static function customReplies(): array
    {
        return [
            ['confirm', "شكراً! تم تأكيد {cart_ref}.\nرقم الطلب: {cart_ref}", "شكراً! تم تأكيد CART-1042.\nرقم الطلب: CART-1042", 'confirmed'],
            ['edit', 'أرسل الكميات الجديدة للطلب {cart_ref}.', 'أرسل الكميات الجديدة للطلب CART-1042.', 'editing'],
            ['cancel', 'تم الإلغاء. يمكنك بدء طلب جديد.', 'تم الإلغاء. يمكنك بدء طلب جديد.', 'cancelled'],
        ];
    }

    public function test_duplicate_event_is_saved_and_replied_to_only_once(): void
    {
        $this->cart();
        $this->tap('confirm');
        $this->tap('confirm');
        $this->process();
        $this->tap('confirm');
        $this->process();
        $this->assertDatabaseCount('cart_whatsapp_button_events', 1);
        $this->assertDatabaseCount('cart_status_audits', 1);
        Http::assertSentCount(1);
    }

    public function test_unknown_cart_and_action_are_ignored(): void
    {
        $cart = $this->cart();
        $this->tap('buy', 'unknown-action');
        $this->tap('confirm', 'unknown-cart', 'CART-9999');
        $this->process();
        $this->assertDatabaseCount('cart_whatsapp_button_events', 1);
        $this->assertDatabaseHas('cart_whatsapp_button_events', ['event_id' => 'unknown-cart', 'outcome' => 'unknown_cart']);
        $this->assertDatabaseCount('cart_status_audits', 0);
        $this->assertSame('open', $cart->fresh()->customer_status);
        Http::assertNothingSent();
    }

    public function test_wrong_customer_or_session_cannot_change_a_cart(): void
    {
        $cart = $this->cart();
        $this->tap('cancel', 'other-customer', phone: '218911111111');
        $this->tap('confirm', 'other-session', session: 'd8e21afbe77f4d8bbaed65f2c6c2edf7');
        $this->process();
        $this->assertSame('open', $cart->fresh()->customer_status);
        $this->assertSame(2, DB::table('cart_whatsapp_button_events')->where('outcome', 'address_mismatch')->count());
        $this->assertDatabaseCount('cart_status_audits', 0);
        Http::assertNothingSent();
    }

    public function test_linked_customer_ids_can_confirm_edit_or_cancel_and_are_retained_in_the_audit(): void
    {
        $lid = '184654183321652@lid';
        $cart = $this->cart(['whatsapp_customer_phone' => $lid]);
        foreach (['edit' => 'editing', 'confirm' => 'confirmed'] as $action => $state) {
            $this->tap($action, 'lid-'.$action, phone: $lid);
            $this->process();
            $this->assertSame($state, $cart->fresh()->operator_status);
            $this->assertDatabaseHas('cart_status_audits', ['event_id' => 'lid-'.$action, 'actor_phone' => $lid]);
        }
        Http::assertSent(fn (Request $request) => $request['phone'] === [$lid]
            && $request['session_uuid'] === self::SESSION);
        $cart->update(['operator_status' => 'new', 'customer_status' => 'open']);
        $this->tap('cancel', 'lid-cancel', phone: $lid);
        $this->process();
        $this->assertSame('cancelled', $cart->fresh()->operator_status);
        $this->assertDatabaseHas('cart_status_audits', ['event_id' => 'lid-cancel', 'actor_phone' => $lid]);
    }

    public function test_linked_customer_ownership_never_matches_the_digits_as_a_phone_or_another_lid(): void
    {
        $lid = '184654183321652@lid';
        $cart = $this->cart(['whatsapp_customer_phone' => $lid]);
        foreach (['184654183321652', '184654183321653@lid'] as $index => $other) {
            $this->tap('confirm', 'lid-mismatch-'.$index, phone: $other);
        }
        $this->tap('confirm', 'lid-session-mismatch', phone: $lid, session: 'd8e21afbe77f4d8bbaed65f2c6c2edf7');
        $this->process();
        $this->assertSame('open', $cart->fresh()->customer_status);
        $this->assertSame(3, DB::table('cart_whatsapp_button_events')->where('outcome', 'address_mismatch')->count());
        Http::assertNothingSent();
    }

    public function test_malformed_and_group_linked_customer_ids_are_ignored(): void
    {
        $this->cart();
        foreach (['184654183321652@lid@lid', '0@lid', '+184654183321652@lid',
            str_repeat('1', 33).'@lid', '184654183321652@g.us'] as $index => $from) {
            $this->tap('confirm', 'bad-lid-'.$index, phone: $from);
        }
        $this->assertDatabaseCount('cart_whatsapp_button_events', 0);
        Http::assertNothingSent();
    }

    public function test_edit_cancel_and_repeated_confirm_are_ignored_after_confirmation(): void
    {
        $cart = $this->cart();
        $this->tap('confirm', 'first');
        $this->process();
        foreach (['edit', 'cancel', 'confirm'] as $action) {
            $this->tap($action, 'after-confirm-'.$action);
        }
        $this->process();
        $this->assertSame('confirmed', $cart->fresh()->customer_status);
        $this->assertSame(3, DB::table('cart_whatsapp_button_events')->where('outcome', 'state_locked')->count());
        $this->assertDatabaseCount('cart_status_audits', 1);
        Http::assertSentCount(1);
    }

    public function test_poll_context_and_a_new_vote_event_use_the_same_state_machine(): void
    {
        $cart = $this->cart();
        $this->tap('edit', 'vote-1', context: 'poll-id');
        $this->process();
        $this->tap('edit', 'vote-2', context: 'poll-id');
        $this->tap('confirm', 'vote-3', context: 'poll-id');
        $this->process();
        $this->assertSame('confirmed', $cart->fresh()->customer_status);
        $this->assertDatabaseCount('cart_whatsapp_button_events', 3);
        Http::assertSentCount(2);
    }

    public function test_cancelled_cart_cannot_be_reopened_by_old_buttons(): void
    {
        $cart = $this->cart();
        $this->tap('cancel', 'first');
        $this->process();
        $this->tap('confirm', 'later');
        $this->tap('edit', 'edit-later');
        $this->process();
        $this->assertSame('cancelled', $cart->fresh()->customer_status);
        Http::assertSentCount(1);
    }

    public function test_retry_sends_the_saved_reply_without_reapplying_the_decision(): void
    {
        Setting::findOrFail(1)->update(['shein_confirm_reply' => 'تأكد الطلب {cart_ref}.']);
        $cart = $this->cart();
        $this->responseStatuses = [403, 202];
        $this->tap('confirm');
        $this->process();
        $decidedAt = $cart->fresh()->customer_updated_at;
        $this->assertSame('confirmed', $cart->fresh()->customer_status);
        $this->assertDatabaseHas('cart_whatsapp_button_events', ['attempts' => 1, 'processed_at' => null]);
        Setting::findOrFail(1)->update(['shein_confirm_reply' => 'New reply for future taps']);
        $this->tap('cancel', 'after-confirm');
        $this->process();
        $this->travel(31)->seconds();
        $this->process();
        $this->assertTrue($decidedAt->equalTo($cart->fresh()->customer_updated_at));
        $this->assertDatabaseHas('cart_whatsapp_button_events', ['event_id' => 'wamid.tap', 'outcome' => 'reply_queued']);
        $this->assertDatabaseCount('cart_status_audits', 1);
        Http::assertSentCount(2);
        foreach (Http::recorded() as [$request, $response]) {
            $this->assertSame('تأكد الطلب CART-1042.', $request['message']);
        }
    }

    public function test_late_edit_reply_is_suppressed_when_the_customer_has_confirmed(): void
    {
        $cart = $this->cart();
        $this->responseStatuses = [500, 202];
        $this->tap('edit', 'edit-first');
        $this->process();
        $this->tap('confirm', 'confirm-later');
        $this->process();
        $this->travel(31)->seconds();
        $this->process();
        $this->assertSame('confirmed', $cart->fresh()->customer_status);
        $this->assertDatabaseHas('cart_whatsapp_button_events', ['event_id' => 'edit-first', 'outcome' => 'reply_superseded']);
        Http::assertSentCount(2);
    }

    public function test_rejects_missing_incorrect_and_short_secrets(): void
    {
        $payload = $this->payload();
        $this->postJson('/api/webhook/whatsapp', $payload)->assertUnauthorized();
        $this->postJson('/api/webhook/whatsapp?token=wrong', $payload)->assertUnauthorized();
        config(['services.whatsapp.webhook_token' => 'short']);
        $this->postJson('/api/webhook/whatsapp?token=short', $payload)->assertUnauthorized();
        $this->assertDatabaseCount('cart_whatsapp_button_events', 0);
    }

    public function test_ignores_other_interactive_types_and_malformed_button_ids(): void
    {
        $this->cart();
        $payload = $this->payload();
        $payload['entry'][0]['changes'][0]['value']['messages'][0]['interactive']['type'] = 'list_reply';
        $this->postJson('/api/webhook/whatsapp?token='.self::TOKEN, $payload)->assertOk();
        $this->tap('confirm', 'empty-ref', '');
        $this->tap('edit', 'oversized', str_repeat('x', 256));
        $this->assertDatabaseCount('cart_whatsapp_button_events', 0);
        Http::assertNothingSent();
    }

    public function test_splits_only_the_first_colon_and_ignores_the_button_title(): void
    {
        $cart = $this->cart(['whatsapp_cart_reference' => 'CART:1042']);
        $this->tap('confirm', reference: 'CART:1042');
        $this->process();
        $this->assertSame('confirmed', $cart->fresh()->customer_status);
        Http::assertSentCount(1);
    }

    /** @dataProvider blockedCartStates */
    public function test_unsent_or_operator_locked_carts_cannot_be_changed(array $attributes, string $outcome): void
    {
        $cart = $this->cart($attributes);
        $this->tap('edit');
        $this->process();
        $this->assertSame('open', $cart->fresh()->customer_status);
        $this->assertDatabaseHas('cart_whatsapp_button_events', ['outcome' => $outcome]);
        Http::assertNothingSent();
    }

    public static function blockedCartStates(): array
    {
        return [
            'not queued' => [['whatsapp_queued_at' => null], 'quotation_not_sent'],
            'quote failed' => [['status' => 'failed'], 'quotation_not_sent'],
            'buying' => [['operator_status' => 'buying'], 'state_locked'],
            'purchased' => [['operator_status' => 'purchased'], 'state_locked'],
            'dismissed' => [['operator_status' => 'dismissed'], 'state_locked'],
            'confirmed by staff' => [['operator_status' => 'confirmed'], 'state_locked'],
            'cancelled by staff' => [['operator_status' => 'cancelled'], 'state_locked'],
        ];
    }

    public function test_logs_event_action_reference_customer_and_session(): void
    {
        $this->cart();
        Log::spy();
        $this->tap('confirm');
        $this->process();
        $context = ['event_id' => 'wamid.tap', 'action' => 'confirm', 'cart_ref' => 'CART-1042',
            'customer' => self::PHONE, 'session' => self::SESSION];
        Log::shouldHaveReceived('info')->with('Cart WhatsApp button queued', $context)->once();
        Log::shouldHaveReceived('info')->with('Cart WhatsApp button applied', $context + ['customer_status' => 'confirmed'])->once();
        Log::shouldHaveReceived('info')->with('Cart WhatsApp action reply queued', $context)->once();
    }

    public function test_audit_failure_rolls_back_the_status_and_retries_safely(): void
    {
        $cart = $this->cart();
        CartStatusAudit::creating(function () {
            throw new \RuntimeException('Audit storage unavailable');
        });
        try {
            $this->tap('confirm');
            $this->process();
            $this->assertSame('open', $cart->fresh()->customer_status);
            $this->assertSame('new', $cart->fresh()->operator_status);
            $this->assertDatabaseCount('cart_status_audits', 0);
            $this->assertDatabaseHas('cart_whatsapp_button_events', ['state_applied_at' => null, 'attempts' => 1]);
            Http::assertNothingSent();
        } finally {
            CartStatusAudit::flushEventListeners();
        }
        $this->travel(31)->seconds();
        $this->process();
        $this->assertSame('confirmed', $cart->fresh()->operator_status);
        $this->assertDatabaseCount('cart_status_audits', 1);
        Http::assertSentCount(1);
    }
}
