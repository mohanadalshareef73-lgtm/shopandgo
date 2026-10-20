<?php

namespace Tests\Feature;

use App\Models\CartQuote;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhatsappMessages;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AmazonAdminTest extends TestCase
{
    use RefreshDatabase;

    private string $url = 'https://www.amazon.ae/dp/B0DF4NM7CY?th=1';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.cart_quotes.base_url' => 'https://quotes.test', 'services.cart_quotes.token' => 'test-key']);
        Setting::findOrFail(1)->update(['usd_to_lyd_rate' => '1']);
        Storage::fake('local');
        Http::preventStrayRequests();
        $this->mock(WhatsAppService::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->never();
            $mock->shouldReceive('sendDocument')->never();
        });
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function operator(array $permissions = ['cart-list', 'cart-create']): User
    {
        DB::table('types')->updateOrInsert(['id' => 2], ['type' => 'موظف']);
        $user = User::factory()->create(['type_id' => 2, 'status' => true]);
        foreach ($permissions as $name) {
            $user->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['ar_name' => $name]));
        }

        return $user;
    }

    private function quote(string $retailer, string $status = 'new'): CartQuote
    {
        $incoming = WhatsappMessages::create(['message_id' => (string) Str::uuid(), 'from' => '218911234567',
            'phone' => '218921000000', 'name' => 'Customer', 'type' => 'text', 'message' => 'link', 'timestamp' => now()]);

        return CartQuote::create(['whatsapp_messages_id' => $incoming->id, 'source_url' => $retailer === 'amazon' ? $this->url : 'https://shein.com/cart',
            'idempotency_key' => (string) Str::uuid(), 'retailer' => $retailer, 'status' => 'submitted', 'operator_status' => $status]);
    }

    private function form(array $overrides = []): array
    {
        return array_merge(['retailer' => 'amazon', 'source_url' => $this->url, 'phone' => '218911234567',
            'requested_quantity' => 3, 'submission_key' => (string) Str::uuid()], $overrides);
    }

    private function pending(): array
    {
        return ['id' => 'manual-amazon', 'status' => 'pending', 'billingStatus' => 'reserved',
            'retailer' => 'amazon', 'storefront' => 'AMZ-AE', 'deadlineAt' => now()->addMinutes(15)->toIso8601String()];
    }

    private function success(): array
    {
        return ['id' => 'manual-amazon', 'status' => 'succeeded', 'retailer' => 'amazon', 'storefront' => 'AMZ-AE',
            'result' => ['itemCount' => 1, 'total' => ['amount' => '9999', 'currency' => 'LYD'],
                'items' => [['name' => 'Amazon test shoes', 'variant' => null, 'qty' => 1, 'qtySource' => 'assumed_one',
                    'availability' => 'available', 'priceSource' => 'product_page',
                    'usd' => ['unitSale' => ['amount' => '80', 'currency' => 'USD']]]]]];
    }

    public function test_amazon_menu_filter_empty_state_and_manual_check_are_visible_without_existing_quotes(): void
    {
        $this->actingAs($this->operator())->get('/carts?retailer=amazon')->assertOk()
            ->assertSee('السلات')->assertSee('فحص منتج أمازون')->assertSee('لا توجد عروض أمازون')
            ->assertSee('amazon.ae')->assertSee('هامش 10 دولارات')->assertSee('name="retailer"', false);
        $this->get('/carts/create?retailer=amazon')->assertOk()->assertSee('رابط منتج أمازون')
            ->assertSee('name="requested_quantity"', false)->assertSee('فحص وإضافة المنتج');
        Http::assertNothingSent();
    }

    public function test_retailer_filter_counts_and_pagination_do_not_mix_shein_and_amazon(): void
    {
        $shein = $this->quote('shein', 'confirmed');
        for ($i = 0; $i < 11; $i++) {
            $this->quote('amazon', 'confirmed');
        }
        $this->quote('amazon', 'new');
        $response = $this->actingAs($this->operator())->get('/carts?retailer=amazon&operator_status=confirmed&per_page=10&page=2')->assertOk();
        $this->assertSame(11, $response->viewData('carts')->total());
        $this->assertCount(1, $response->viewData('carts'));
        $this->assertSame(11, $response->viewData('counts')['confirmed']);
        $this->assertStringContainsString('retailer=amazon', $response->viewData('carts')->url(1));
        $this->assertStringContainsString('retailer=amazon', $response->getContent());
        $sheinOnly = $this->get('/carts?retailer=shein')->assertOk();
        $this->assertSame([$shein->id], $sheinOnly->viewData('carts')->pluck('id')->all());
        $this->get('/carts?retailer=unknown')->assertSessionHasErrors('retailer');
    }

    public function test_one_carts_page_shows_both_retailers_with_a_shared_filter(): void
    {
        $shein = $this->quote('shein');
        $amazon = $this->quote('amazon');
        $response = $this->actingAs($this->operator())->get('/carts')->assertOk()
            ->assertSee('<title>السلات | Carts</title>', false)
            ->assertSee('كل المتاجر')->assertSee('فحص سلة شي إن')->assertSee('فحص منتج أمازون');
        $this->assertSame([$amazon->id, $shein->id], $response->viewData('carts')->pluck('id')->all());
        $this->assertSame(1, substr_count($response->getContent(), '<span class="pc-mtext">السلات</span>'));
        $response->assertDontSee('<span class="pc-mtext">سلات شي إن</span>', false)
            ->assertDontSee('<span class="pc-mtext">منتجات أمازون</span>', false);
        foreach (['shein', 'amazon'] as $retailer) {
            $filtered = $this->get('/carts?retailer='.$retailer)->assertOk()
                ->assertSee('<title>السلات | Carts</title>', false);
            $this->assertSame([$retailer], $filtered->viewData('carts')->pluck('retailer')->all());
        }
    }

    public function test_manual_amazon_quote_uses_shared_client_and_saved_pricing_without_customer_messages(): void
    {
        Http::fake(['quotes.test/v1/quotes' => Http::response($this->pending(), 202),
            'quotes.test/v1/quotes/manual-amazon' => Http::response($this->success())]);
        $form = $this->form();
        $this->actingAs($this->operator())->post('/carts', $form)->assertRedirect()->assertSessionHas('success');
        $this->post('/carts', $form)->assertRedirect();
        $quote = CartQuote::firstOrFail();
        $this->assertSame('amazon', $quote->retailer);
        $this->assertSame('manual', $quote->origin);
        $this->assertSame(3, $quote->requested_quantity);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->data() === ['sourceUrl' => $this->url]
            && $request->header('Idempotency-Key') === [$quote->idempotency_key]);
        $this->travel(16)->minutes();
        $this->artisan('cart-quotes:process')->assertExitCode(0);
        $this->assertSame('succeeded', $quote->fresh()->status);
        $this->assertSame(['amount' => '285', 'currency' => 'LYD'], $quote->fresh()->amazon_pricing['total']);
        $this->assertNull($quote->fresh()->next_attempt_at);
        $this->assertStringStartsWith('amazon-quotations/', $quote->fresh()->pdf_path);
        $this->get(route('carts.show', $quote))->assertOk()->assertSee('فتح المنتج في أمازون')
            ->assertSee('سعر المنتج الأساسي')->assertSee('Amazon test shoes')->assertSee('285');
        $this->assertDatabaseCount('replay_messages', 0);
    }

    public function test_manual_amazon_quantity_cannot_change_on_reused_submission_key(): void
    {
        Http::fake(['quotes.test/v1/quotes' => Http::response($this->pending(), 202)]);
        $form = $this->form();
        $this->actingAs($this->operator())->post('/carts', $form)->assertRedirect();
        $this->post('/carts', array_merge($form, ['requested_quantity' => 5]))->assertSessionHasErrors('source_url');
        $this->assertSame(3, CartQuote::first()->requested_quantity);
        Http::assertSentCount(1);
    }

    public static function invalidForms(): array
    {
        return [[['source_url' => 'https://amazon.ae.evil.test/dp/test'], 'source_url'],
            [['source_url' => 'https://shein.com/cart'], 'source_url'],
            [['requested_quantity' => 0], 'requested_quantity'], [['requested_quantity' => '2.5'], 'requested_quantity'],
            [['requested_quantity' => 1000000], 'requested_quantity'], [['retailer' => 'other'], 'retailer']];
    }

    /** @dataProvider invalidForms */
    public function test_invalid_manual_amazon_inputs_do_not_call_provider(array $changes, string $field): void
    {
        $this->actingAs($this->operator())->post('/carts', $this->form($changes))->assertSessionHasErrors($field);
        Http::assertNothingSent();
        $this->assertDatabaseCount('cart_quotes', 0);
    }

    public function test_missing_rate_on_manual_success_can_resume_without_fetching_again(): void
    {
        Setting::findOrFail(1)->update(['usd_to_lyd_rate' => null]);
        Http::fake(['quotes.test/v1/quotes' => Http::response($this->pending(), 202),
            'quotes.test/v1/quotes/manual-amazon' => Http::response($this->success())]);
        $this->actingAs($this->operator())->post('/carts', $this->form())->assertRedirect();
        $this->travel(16)->minutes();
        $this->artisan('cart-quotes:process')->assertExitCode(0);
        $this->assertNull(CartQuote::first()->amazon_pricing);
        Setting::findOrFail(1)->update(['usd_to_lyd_rate' => '1']);
        $this->travel(31)->seconds();
        $this->artisan('cart-quotes:process')->assertExitCode(0);
        $this->assertSame('285', CartQuote::first()->amazon_pricing['total']['amount']);
        Http::assertSentCount(2);
    }

    public function test_amazon_pricing_is_visible_in_settings_without_exposing_credentials(): void
    {
        $this->actingAs($this->operator(['system-list']))->get('/settings')->assertOk()
            ->assertSee('إعدادات تسعير أمازون')->assertSee('amazon.ae')->assertSee('5 دولارات')
            ->assertDontSee('test-key');
    }

    public function test_amazon_manual_form_obeys_existing_creation_permissions(): void
    {
        $this->actingAs($this->operator(['cart-list']))->get('/carts/create?retailer=amazon')->assertForbidden();
        $this->post('/carts', $this->form())->assertForbidden();
        Http::assertNothingSent();
    }
}
