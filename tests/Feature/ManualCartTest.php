<?php

namespace Tests\Feature;

use App\Models\CartQuote;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ManualCartTest extends TestCase
{
    use RefreshDatabase;

    private string $url = 'https://api-shein.shein.com/h5/sharejump/appsharejump?cart=123';

    protected function setUp(): void
    {
        parent::setUp();
        \App\Models\Setting::findOrFail(1)->update(['shein_pause_on_empty_wallet' => false]);
        config([
            'services.cart_quotes.base_url' => 'https://quotes.test',
            'services.cart_quotes.token' => 'test-api-key',
            'services.cart_quotes.shein_hosts' => ['shein.com', 'shein.top'],
        ]);
        Http::preventStrayRequests();
        // Manual checks are for operator review, not automated client messaging.
        $this->mock(WhatsAppService::class, fn ($mock) => $mock->shouldReceive('sendMessage')->never());
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function operator(array $permissions = ['cart-list', 'cart-create']): User
    {
        DB::table('types')->updateOrInsert(['id' => 2], ['type' => 'موظف', 'created_at' => now(), 'updated_at' => now()]);
        $user = User::factory()->create(['type_id' => 2, 'status' => true]);
        foreach ($permissions as $name) {
            $user->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['ar_name' => $name]));
        }
        return $user;
    }

    public function test_manual_cart_is_saved_and_waits_silently_when_wallet_is_empty(): void
    {
        \App\Models\Setting::findOrFail(1)->update(['shein_pause_on_empty_wallet' => true]);
        Http::fake(['quotes.test/v1/wallet' => Http::response([
            'balance' => ['amount' => '0.00', 'currency' => 'USD'],
            'held' => ['amount' => '0.00', 'currency' => 'USD'],
            'available' => ['amount' => '0.00', 'currency' => 'USD'],
        ])]);
        $this->actingAs($this->operator())->post(route('carts.store'), $this->payload())
            ->assertRedirect()->assertSessionHas('warning', 'تم حفظ السلة وستتم معالجتها تلقائياً بعد شحن المحفظة.');
        $cart = CartQuote::firstOrFail();
        $this->assertSame('pending', $cart->status);
        $this->assertSame('manual', $cart->origin);
        $this->assertNull($cart->quote_id);
        $this->assertNotNull($cart->next_attempt_at);
        Http::assertNotSent(fn (Request $request) => $request->method() === 'POST');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge(['source_url' => $this->url, 'phone' => '+218 91 1234567',
            'submission_key' => (string) Str::uuid()], $overrides);
    }

    private function pending(): array
    {
        return ['id' => 'manual-provider-quote', 'status' => 'pending', 'result' => null];
    }

    public function test_form_creates_cart_with_normalized_phone_and_submits_to_provider(): void
    {
        config(['services.cart_quotes.whatsapp_test_number' => '218916655677']);
        $operator = $this->operator();
        $this->actingAs($operator)->get('/carts')->assertOk()->assertSee('فحص سلة شي إن');
        $form = $this->get('/carts/create')->assertOk()->assertSee('رابط سلة شي إن')->assertSee('رقم واتساب العميل');
        $payload = $this->payload(['submission_key' => $form->viewData('submissionKey')]);
        Http::fake(['quotes.test/*' => Http::response($this->pending(), 202)]);
        $response = $this->post('/carts', $payload);
        $cart = CartQuote::firstOrFail();
        $response->assertRedirect(route('carts.show', $cart))->assertSessionHas('success');
        $this->assertSame('manual', $cart->origin);
        $this->assertSame('new', $cart->operator_status);
        $this->assertSame('submitted', $cart->status);
        $this->assertSame('manual-provider-quote', $cart->quote_id);
        $this->assertSame('218911234567', $cart->incomingMessage->from);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'https://quotes.test/v1/quotes'
            && $request['sourceUrl'] === $this->url
            && $request->hasHeader('Authorization', 'Bearer test-api-key')
            && $request->header('Idempotency-Key')[0] === $cart->idempotency_key);
        $this->get('/carts')->assertOk()->assertSee('218911234567')->assertSee('إضافة يدوية');
        $this->get(route('carts.show', $cart))->assertOk()->assertSee('https://wa.me/218911234567');
        $this->assertDatabaseHas('logs', ['type' => 'cart', 'user_id' => $operator->id, 'title' => 'إضافة سلة شي إن يدوياً #'.$cart->id]);
        $this->assertDatabaseCount('replay_messages', 0);
    }

    public function test_repeated_form_submission_reuses_cart_and_provider_request(): void
    {
        Http::fake(['quotes.test/*' => Http::response($this->pending(), 202)]);
        $payload = $this->payload();
        $this->actingAs($this->operator())->post('/carts', $payload)->assertRedirect();
        $payload['phone'] = '00218911234567';
        $this->post('/carts', $payload)->assertRedirect(route('carts.show', CartQuote::first()));
        Http::assertSentCount(1);
        $this->assertDatabaseCount('cart_quotes', 1);
        $this->assertDatabaseCount('whatsapp_messagess', 1);
        $this->assertDatabaseCount('logs', 1);
    }

    public function test_reused_submission_key_cannot_change_cart_or_recipient(): void
    {
        Http::fake(['quotes.test/*' => Http::response($this->pending(), 202)]);
        $payload = $this->payload();
        $this->actingAs($this->operator())->post('/carts', $payload)->assertRedirect();
        $this->post('/carts', array_merge($payload, ['phone' => '218922222222']))->assertSessionHasErrors('source_url');
        $this->post('/carts', array_merge($payload, ['source_url' => 'https://shein.top/different-cart']))->assertSessionHasErrors('source_url');
        $this->assertSame('218911234567', CartQuote::first()->incomingMessage->from);
        $this->assertSame($this->url, CartQuote::first()->source_url);
        $this->assertDatabaseCount('cart_quotes', 1);
        Http::assertSentCount(1);
    }

    public function test_provider_retries_and_polling_preserve_manual_origin_and_exact_result(): void
    {
        Http::fake(['quotes.test/*' => Http::sequence()->push([], 503, ['Retry-After' => '60'])
            ->push($this->pending(), 202)
            ->push(['id' => 'manual-provider-quote', 'status' => 'succeeded',
                'result' => ['itemCount' => 7, 'total' => ['amount' => '447.821', 'currency' => 'LYD']]])]);
        $this->actingAs($this->operator())->post('/carts', $this->payload())->assertRedirect()->assertSessionHas('warning');
        $key = CartQuote::first()->idempotency_key;
        $this->travel(61)->seconds();
        $this->artisan('cart-quotes:process')->assertExitCode(0);
        $this->travel(15)->minutes();
        $this->artisan('cart-quotes:process')->assertExitCode(0);
        $cart = CartQuote::first();
        $this->assertSame('manual', $cart->origin);
        $this->assertSame('succeeded', $cart->status);
        $this->assertSame('447.821', $cart->payload['result']['total']['amount']);
        $this->assertNull($cart->next_attempt_at);
        $this->assertNull($cart->notified_at);
        Http::assertSentCount(3);
        foreach (Http::recorded() as [$request]) {
            if ($request->method() === 'POST') {
                $this->assertSame($key, $request->header('Idempotency-Key')[0]);
            }
        }
        $this->artisan('cart-quotes:process')->expectsOutput('Processed 0 cart quote request(s).')->assertExitCode(0);
    }

    public function test_terminal_rejection_is_saved_for_operator_review(): void
    {
        Http::fake(['quotes.test/*' => Http::response(['error' => 'INVALID_URL'], 400)]);
        $this->actingAs($this->operator())->post('/carts', $this->payload())->assertRedirect()->assertSessionHas('error');
        $cart = CartQuote::first();
        $this->assertSame('failed', $cart->status);
        $this->assertNull($cart->next_attempt_at);
        $this->assertSame('Cart rejected (HTTP 400)', $cart->last_error);
        $this->assertDatabaseCount('cart_quotes', 1);
    }

    /** @dataProvider invalidInputs */
    public function test_invalid_input_does_not_create_cart_or_contact_provider(array $overrides, string $field): void
    {
        $this->actingAs($this->operator())->from('/carts/create')->post('/carts', $this->payload($overrides))
            ->assertRedirect('/carts/create')->assertSessionHasErrors($field);
        $this->assertDatabaseCount('cart_quotes', 0);
        $this->assertDatabaseCount('whatsapp_messagess', 0);
        Http::assertNothingSent();
    }

    public static function invalidInputs(): array
    {
        return [
            [['source_url' => ''], 'source_url'],
            [['source_url' => 'https://shein.com.evil.test/cart'], 'source_url'],
            [['source_url' => 'https://user@shein.com/cart'], 'source_url'],
            [['source_url' => 'https://shein.com:8443/cart'], 'source_url'],
            [['source_url' => 'ftp://shein.com/cart'], 'source_url'],
            [['source_url' => 'https://shein.com/Dress-p-12345.html'], 'source_url'],
            [['phone' => ''], 'phone'],
            [['phone' => '0911234567'], 'phone'],
            [['phone' => '+218abc911234567'], 'phone'],
            [['phone' => str_repeat('1', 16)], 'phone'],
            [['submission_key' => 'invalid'], 'submission_key'],
        ];
    }

    public function test_requires_creation_permission_before_any_provider_request(): void
    {
        $this->get('/carts/create')->assertRedirect();
        $this->post('/carts', $this->payload())->assertRedirect();
        $this->actingAs($this->operator(['cart-list']))->get('/carts')->assertOk()->assertDontSee('فحص سلة شي إن');
        $this->get('/carts/create')->assertForbidden();
        $this->post('/carts', $this->payload())->assertForbidden();
        $this->assertDatabaseCount('cart_quotes', 0);
        Http::assertNothingSent();
    }
}
