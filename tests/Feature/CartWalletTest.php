<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CartWalletTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.cart_quotes.base_url' => 'https://quotes.test/', 'services.cart_quotes.token' => 'private-wallet-token']);
        Http::preventStrayRequests();
    }

    private function operator(bool $canList = true): User
    {
        DB::table('types')->updateOrInsert(['id' => 2], ['type' => 'موظف', 'created_at' => now(), 'updated_at' => now()]);
        $user = User::factory()->create(['type_id' => 2, 'status' => true]);
        Permission::firstOrCreate(['name' => 'cart-list', 'guard_name' => 'web'], ['ar_name' => 'سلات شي إن']);
        if ($canList) {
            $user->givePermissionTo('cart-list');
        }
        return $user;
    }

    private function wallet(): array
    {
        return ['balance' => ['amount' => '8.75', 'currency' => 'USD'],
            'held' => ['amount' => '0.25', 'currency' => 'USD'],
            'available' => ['amount' => '8.50', 'currency' => 'USD']];
    }

    public function test_reads_all_three_balances_using_the_server_side_key_without_exposing_it(): void
    {
        Http::fake(['https://quotes.test/v1/wallet' => Http::response(['status' => 'ok'] + $this->wallet())]);
        $this->actingAs($this->operator())->getJson(route('carts.wallet'))->assertOk()
            ->assertJsonPath('wallet', $this->wallet())->assertJsonStructure(['checked_at'])
            ->assertHeader('Cache-Control', 'no-store, private')->assertDontSee('private-wallet-token');
        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && $request->url() === 'https://quotes.test/v1/wallet'
            && $request->hasHeader('Authorization', 'Bearer private-wallet-token'));
        Http::assertSentCount(1);
    }

    public function test_preserves_money_strings_without_float_conversion(): void
    {
        $wallet = $this->wallet();
        $wallet['balance']['amount'] = '999999999999999999.123456789';
        $wallet['held']['amount'] = '0.123456789';
        $wallet['available']['amount'] = '999999999999999999.000000000';
        Http::fake(['https://quotes.test/v1/wallet' => Http::response($wallet)]);
        $this->actingAs($this->operator())->getJson(route('carts.wallet'))->assertOk()->assertJsonPath('wallet', $wallet);
    }

    public function test_cart_list_displays_wallet_controls_without_waiting_for_the_provider(): void
    {
        $this->actingAs($this->operator())->get(route('carts.index'))->assertOk()
            ->assertSee('الرصيد الإجمالي')->assertSee('الرصيد المحجوز')->assertSee('الرصيد المتاح')
            ->assertSee('تحديث الرصيد')->assertSee('/carts/wallet')->assertDontSee('private-wallet-token');
        Http::assertNothingSent();
    }

    public function test_provider_failure_is_unavailable_instead_of_a_zero_balance(): void
    {
        Http::fake(['https://quotes.test/v1/wallet' => Http::response(['debug' => 'private-wallet-token'], 401)]);
        $this->actingAs($this->operator())->getJson(route('carts.wallet'))->assertStatus(503)
            ->assertJsonPath('wallet', null)->assertJsonStructure(['message'])
            ->assertHeader('Cache-Control', 'no-store, private')->assertDontSee('private-wallet-token');
    }

    public function test_connection_failure_does_not_break_the_cart_page(): void
    {
        Http::fake(fn () => throw new ConnectionException('Provider connection failed'));
        $this->actingAs($this->operator())->getJson(route('carts.wallet'))->assertStatus(503)->assertJsonPath('wallet', null);
        $this->get(route('carts.index'))->assertOk();
    }

    public function test_missing_key_never_sends_an_unauthenticated_provider_request(): void
    {
        config(['services.cart_quotes.token' => '']);
        $this->actingAs($this->operator())->getJson(route('carts.wallet'))->assertStatus(503)->assertJsonPath('wallet', null);
        Http::assertNothingSent();
    }

    public function test_wallet_requires_login_and_cart_list_permission(): void
    {
        $this->getJson(route('carts.wallet'))->assertUnauthorized();
        $this->actingAs($this->operator(false))->getJson(route('carts.wallet'))->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_wallet_reports_cart_replies_paused_when_available_credit_is_zero(): void
    {
        $wallet = $this->wallet();
        $wallet['available']['amount'] = '0.00';
        Http::fake(['https://quotes.test/v1/wallet' => Http::response($wallet)]);
        $this->actingAs($this->operator())->getJson(route('carts.wallet'))->assertOk()
            ->assertJsonPath('cart_replies_paused', true);
        \App\Models\Setting::findOrFail(1)->update(['shein_pause_on_empty_wallet' => false]);
        $this->getJson(route('carts.wallet'))->assertOk()->assertJsonPath('cart_replies_paused', false);
    }

    public function test_unavailable_wallet_reports_paused_without_fabricating_zero_credit(): void
    {
        Http::fake(['https://quotes.test/v1/wallet' => Http::response([], 503)]);
        $this->actingAs($this->operator())->getJson(route('carts.wallet'))->assertStatus(503)
            ->assertJsonPath('wallet', null)->assertJsonPath('cart_replies_paused', true);
    }

    /** @dataProvider invalidWallets */
    public function test_incomplete_or_invalid_money_is_not_displayed_as_a_wallet(array $wallet): void
    {
        Http::fake(['https://quotes.test/v1/wallet' => Http::response($wallet)]);
        $this->actingAs($this->operator())->getJson(route('carts.wallet'))->assertStatus(503)->assertJsonPath('wallet', null);
    }

    public static function invalidWallets(): array
    {
        $money = ['amount' => '8.75', 'currency' => 'USD'];
        $wallet = ['balance' => $money, 'held' => ['amount' => '0.00', 'currency' => 'USD'], 'available' => $money];
        return [
            'missing amounts' => [[]],
            'float amount' => [array_replace($wallet, ['balance' => ['amount' => 8.75, 'currency' => 'USD']])],
            'negative amount' => [array_replace($wallet, ['held' => ['amount' => '-1.00', 'currency' => 'USD']])],
            'mixed currencies' => [array_replace($wallet, ['available' => ['amount' => '8.75', 'currency' => 'LYD']])],
        ];
    }
}
