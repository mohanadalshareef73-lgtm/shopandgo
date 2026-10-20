<?php

namespace Tests\Feature;

use App\Models\CartQuote;
use App\Models\WhatsappMessages;
use App\Services\GeminiService;
use App\Services\WhatsAppContactNumbers;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppContactNumbersTest extends TestCase
{
    use RefreshDatabase;

    private const SESSION = 'cf2859dd56474dd69db06308afde6d92';
    private const SECOND = 'd8e21afbe77f4d8bbaed65f2c6c2edf7';
    private const LID = '191156327592003@lid';
    private const PHONE = '218912345678';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['services.whatsapp.api_url' => 'https://whatsapp.test/api', 'services.whatsapp.api_token' => 'secret']);
        $this->mock(GeminiService::class, fn ($mock) => $mock->shouldReceive('respond')->andReturn(null));
    }

    private function message(string $lid = self::LID, string $session = self::SESSION): WhatsappMessages
    {
        return WhatsappMessages::create(['message_id' => 'contact-'.uniqid(), 'from' => $lid,
            'session_uuid' => $session, 'phone' => '218921000000', 'name' => 'Customer',
            'type' => 'text', 'message' => 'Cart', 'timestamp' => now(), 'contact_lookup_after' => now()]);
    }

    private function cart(WhatsappMessages $incoming): CartQuote
    {
        return CartQuote::create(['whatsapp_messages_id' => $incoming->id, 'idempotency_key' => 'contact-cart-'.$incoming->id,
            'source_url' => 'https://shein.com/cart', 'status' => 'pending']);
    }

    private function webhook(?string $phone, string $type = 'text', string $contactId = self::LID): void
    {
        $contact = ['wa_id' => $contactId, 'profile' => ['name' => 'Customer']];
        if ($phone !== null) $contact['phone_number'] = $phone;
        $message = ['id' => 'wamid.contact', 'from' => self::LID, 'type' => $type, 'text' => ['body' => 'Hello']];
        if ($type === 'interactive') {
            $message['interactive'] = ['type' => 'button_reply', 'button_reply' => ['id' => 'confirm:CART-42']];
        }
        $this->postJson('/api/webhook/whatsapp?token='.config('services.whatsapp.webhook_token'), ['entry' => [[
            'id' => self::SESSION, 'changes' => [['value' => ['contacts' => [$contact],
                'metadata' => ['display_phone_number' => '+218 92 1000000', 'phone_number_id' => 'shop2go10267'], 'messages' => [$message]]]],
        ]]])->assertOk();
    }

    public function test_webhook_stores_number_for_navigation_without_replacing_the_linked_delivery_id(): void
    {
        $old = $this->message();
        $other = $this->message(session: self::SECOND);
        $cart = $this->cart($old);
        $this->webhook(self::PHONE);
        $this->assertSame(self::PHONE, $old->fresh()->contact_phone);
        $this->assertSame(self::LID, $old->fresh()->from);
        $this->assertNull($other->fresh()->contact_phone);
        $this->assertSame('https://wa.me/'.self::PHONE, $cart->fresh()->whatsapp_url);
        $this->assertSame(self::LID, WhatsappMessages::where('message_id', 'wamid.contact')->firstOrFail()->from);
        $incoming = WhatsappMessages::where('message_id', 'wamid.contact')->firstOrFail();
        $this->assertSame('218921000000', $incoming->receiving_phone);
        $this->assertSame('shop2go10267', $incoming->session_name);
        Http::assertNothingSent();
    }

    public function test_duplicate_webhook_preserves_the_receiving_number_and_session_snapshot(): void
    {
        $incoming = $this->message();
        $incoming->update(['message_id' => 'wamid.contact', 'receiving_phone' => '218911111111',
            'session_name' => 'Original business phone']);
        $this->webhook(self::PHONE);
        $this->assertSame('218911111111', $incoming->fresh()->receiving_phone);
        $this->assertSame('Original business phone', $incoming->fresh()->session_name);
        $this->assertSame(self::SESSION, $incoming->fresh()->session_uuid);
        Http::assertNothingSent();
    }

    public function test_missing_phone_does_not_erase_a_known_number_and_never_uses_business_phone(): void
    {
        $known = $this->message();
        $known->update(['contact_phone' => self::PHONE]);
        $this->webhook(null);
        $incoming = WhatsappMessages::where('message_id', 'wamid.contact')->firstOrFail();
        $this->assertSame(self::PHONE, $incoming->contact_phone);
        $this->assertSame(self::PHONE, $known->fresh()->contact_phone);
        Http::assertNothingSent();
    }

    public function test_unmatched_or_invalid_contact_number_is_not_attached_to_customer(): void
    {
        $old = $this->message();
        $cart = $this->cart($old);
        $this->webhook(self::PHONE, contactId: 'another@lid');
        $this->assertNull($old->fresh()->contact_phone);
        $this->assertNull($cart->fresh()->whatsapp_url);
        $this->assertNull(app(WhatsAppContactNumbers::class)->webhookPhone(['from' => self::LID], [
            'contacts' => [['wa_id' => self::LID, 'phone_number' => self::LID]],
        ]));
        Http::assertNothingSent();
    }

    public function test_button_phone_capture_is_deferred_without_contact_locks_or_remote_requests(): void
    {
        $incoming = $this->message();
        $this->webhook(self::PHONE, 'interactive');
        $this->assertNull($incoming->fresh()->contact_phone);
        $this->assertDatabaseHas('cart_whatsapp_button_events', ['event_id' => 'wamid.contact', 'contact_phone' => self::PHONE]);
        Http::assertNothingSent();
        $this->artisan('cart-quotes:process-replies')->assertExitCode(0);
        $this->assertSame(self::PHONE, $incoming->fresh()->contact_phone);
        Http::assertNothingSent();
    }

    public function test_resolver_batches_fifty_unique_ids_per_original_session_and_retains_unavailable_numbers(): void
    {
        $first = $this->message();
        $this->message(); // Same address is resolved only once.
        $other = $this->message(session: self::SECOND);
        for ($i = 1; $i <= 50; $i++) $this->message(lid: (100000000000000 + $i).'@lid');
        Http::fake(['whatsapp.test/api/contacts/resolve' => fn (Request $request) => Http::response([
            'contacts' => array_map(fn ($id) => ['id' => $id,
                'phone_number' => $id === self::LID && $request['session_uuid'] === self::SESSION ? self::PHONE : null], $request['ids']),
        ])]);
        $this->artisan('whatsapp:resolve-cart-contacts')->assertExitCode(0);
        $this->assertSame(self::PHONE, $first->fresh()->contact_phone);
        $this->assertNull($other->fresh()->contact_phone);
        $this->assertNull($other->fresh()->contact_lookup_after);
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request) => count($request['ids']) === 50 && $request->hasHeader('X-API-Key', 'secret'));
        $this->artisan('whatsapp:resolve-cart-contacts')->assertExitCode(0);
        Http::assertSentCount(3); // Nulls wait for a new message; no constant polling.
    }

    public function test_failed_lookup_retries_later_and_preserves_customer_identity(): void
    {
        $incoming = $this->message();
        Http::fake(['whatsapp.test/api/contacts/resolve' => Http::response([], 503)]);
        $this->artisan('whatsapp:resolve-cart-contacts')->assertExitCode(0);
        $this->assertTrue($incoming->fresh()->contact_lookup_after->isFuture());
        $this->assertSame(self::LID, $incoming->fresh()->from);
        $this->artisan('whatsapp:resolve-cart-contacts')->assertExitCode(0);
        Http::assertSentCount(1);
    }

    public function test_unexpected_api_identity_is_rejected_and_no_number_is_saved(): void
    {
        $incoming = $this->message();
        Http::fake(['whatsapp.test/api/contacts/resolve' => Http::response([
            'contacts' => [['id' => '999@lid', 'phone_number' => self::PHONE]],
        ])]);
        $counts = app(WhatsAppContactNumbers::class)->resolvePending();
        $this->assertSame(1, $counts['failed']);
        $this->assertNull($incoming->fresh()->contact_phone);
    }

    public function test_resolver_does_not_overwrite_a_number_received_while_lookup_is_running(): void
    {
        $incoming = $this->message();
        Http::fake(['whatsapp.test/api/contacts/resolve' => function () use ($incoming) {
            $incoming->update(['contact_phone' => self::PHONE, 'contact_lookup_after' => null]);
            return Http::response(['contacts' => [['id' => self::LID, 'phone_number' => null]]]);
        }]);
        app(WhatsAppContactNumbers::class)->resolvePending();
        $this->assertSame(self::PHONE, $incoming->fresh()->contact_phone);
    }

    public function test_a_new_message_retries_a_previously_unavailable_cart_number(): void
    {
        $old = $this->message();
        $this->cart($old);
        $old->update(['contact_lookup_after' => null]);
        $this->webhook(null);
        $this->assertNotNull(WhatsappMessages::where('message_id', 'wamid.contact')->firstOrFail()->contact_lookup_after);
        Http::assertNothingSent();
    }

    public function test_receiving_source_backfill_uses_legacy_metadata_without_guessing_a_main_session(): void
    {
        $migration = require base_path('database/migrations/2026_10_07_000005_add_whatsapp_receiving_source.php');
        $migration->down();
        $known = $this->message();
        $unknown = $this->message(lid: '218912345678');
        $unknown->update(['phone' => '218912345678', 'session_uuid' => null]);
        \App\Models\Setting::findOrFail(1)->update(['whatsapp_numbers' => [
            ['session_uuid' => self::SESSION, 'name' => 'shop2go10267'],
        ], 'whatsapp_main_session_uuid' => self::SECOND]);
        $migration->up();
        $this->assertSame('218921000000', $known->fresh()->receiving_phone);
        $this->assertSame('shop2go10267', $known->fresh()->session_name);
        $this->assertNull($unknown->fresh()->receiving_phone);
        $this->assertNull($unknown->fresh()->session_name);
        $this->assertNull($unknown->fresh()->session_uuid);
    }

    public function test_old_null_response_does_not_clear_lookup_for_a_message_arriving_during_lookup(): void
    {
        $old = $this->message();
        $new = null;
        Http::fake(['whatsapp.test/api/contacts/resolve' => function () use (&$new) {
            $new = $this->message();
            return Http::response(['contacts' => [['id' => self::LID, 'phone_number' => null]]]);
        }]);
        app(WhatsAppContactNumbers::class)->resolvePending();
        $this->assertNull($old->fresh()->contact_lookup_after);
        $this->assertNotNull($new->fresh()->contact_lookup_after);
    }
}
