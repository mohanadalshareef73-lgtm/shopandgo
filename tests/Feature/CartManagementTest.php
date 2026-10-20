<?php

namespace Tests\Feature;

use App\Models\CartQuote;
use App\Models\User;
use App\Models\Setting;
use App\Models\WhatsappMessages;
use Database\Seeders\CartPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CartManagementTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private function operator(array $permissions = ['cart-list', 'cart-edit']): User
    {
        DB::table('types')->updateOrInsert(['id' => 2], ['type' => 'موظف', 'created_at' => now(), 'updated_at' => now()]);
        $user = User::factory()->create(['name' => 'موظف المتابعة', 'type_id' => 2, 'status' => true]);
        foreach ($permissions as $name) {
            $user->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['ar_name' => $name]));
        }
        return $user;
    }

    private function cart(array $attributes = [], string $name = 'Ahmed', string $number = '218911234567'): CartQuote
    {
        $id = ++$this->sequence;
        $incoming = WhatsappMessages::create([
            'message_id' => 'wamid.cart-'.$id, 'from' => $number, 'phone' => '218921000000',
            'name' => $name, 'type' => 'text', 'message' => 'رابط سلة شي إن رقم '.$id,
            'timestamp' => now(), 'is_reply' => true,
        ]);
        return CartQuote::create(array_merge([
            'whatsapp_messages_id' => $incoming->id,
            'source_url' => 'https://api-shein.shein.com/h5/sharejump/cart-'.$id,
            'idempotency_key' => 'test-cart-'.$id, 'quote_id' => 'quote-'.$id,
            'status' => 'succeeded', 'operator_status' => 'new', 'next_attempt_at' => now(),
            'payload' => ['result' => ['itemCount' => 7, 'total' => ['amount' => '447.821', 'currency' => 'LYD'],
                'items' => [['name' => 'Ribbed Knit Top', 'variant' => 'Black / M', 'qty' => 2,
                    'availability' => 'available', 'outputLine' => ['amount' => '24.51', 'currency' => 'USD']]]]],
        ], $attributes));
    }

    public function test_lists_carts_with_the_sender_number_and_safe_whatsapp_link(): void
    {
        $cart = $this->cart();
        $this->actingAs($this->operator())->get('/carts')->assertOk()
            ->assertSee('السلات')->assertSee('Ahmed')->assertSee('218911234567')
            ->assertSee('https://wa.me/218911234567')->assertDontSee('https://wa.me/218921000000')
            ->assertSee('447.821')->assertSee('LYD')->assertSee(route('carts.show', $cart));
        $this->get(route('carts.show', $cart))->assertOk()->assertSee('Ribbed Knit Top')
            ->assertSee('Black / M')->assertSee('24.51 USD')->assertSee('حفظ المتابعة')->assertDontSee('@else');
        Http::assertNothingSent();
    }

    public function test_product_photos_are_listed_served_as_safe_cached_jpegs_and_require_permission(): void
    {
        Storage::fake('local');
        $url = 'https://img.ltwebstatic.com/images/top.jpg';
        $cart = $this->cart(['payload' => ['result' => ['items' => [
            ['name' => 'Black top', 'imageUrl' => $url],
        ]]]]);
        $image = imagecreatetruecolor(600, 800);
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);
        Http::fake([$url => Http::response($bytes, 200, ['Content-Type' => 'image/png'])]);
        $endpoint = route('carts.products.image', [$cart, 0]);
        $this->get($endpoint)->assertRedirect('/login');
        $this->actingAs($this->operator([]))->get($endpoint)->assertForbidden();
        $this->actingAs($this->operator(['cart-list']))->get('/carts')->assertOk()
            ->assertSee($endpoint)->assertSee('alt="Black top"', false);
        $response = $this->get($endpoint)->assertOk()->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $path = $response->baseResponse->getFile()->getPathname();
        $this->assertSame([480, 640], array_slice(getimagesize($path), 0, 2));
        $this->get($endpoint)->assertOk();
        Http::assertSentCount(1);
    }

    public function test_missing_unsafe_and_failed_product_photos_do_not_break_the_cart(): void
    {
        Storage::fake('local');
        $urls = [null, 'http://img.ltwebstatic.com/a.jpg', 'https://evil.ltwebstatic.com.attacker.test/a.jpg',
            'https://user:pass@img.ltwebstatic.com/a.jpg', 'https://img.ltwebstatic.com:8443/a.jpg',
            'https://127.0.0.1/a.jpg', 'https://img.ltwebstatic.com/broken.jpg'];
        $cart = $this->cart(['payload' => ['result' => ['items' => array_map(
            fn ($url) => ['name' => 'Top', 'imageUrl' => $url], $urls)]]]);
        Http::fake(['https://img.ltwebstatic.com/broken.jpg' => Http::response('not an image', 200)]);
        $this->actingAs($this->operator(['cart-list']))->get('/carts')->assertOk();
        foreach (array_keys($urls) as $index) {
            $this->get(route('carts.products.image', [$cart, $index]))->assertNotFound();
        }
        $this->get(route('carts.products.image', [$cart, 999]))->assertNotFound();
        Http::assertSentCount(1);
    }

    public function test_linked_customer_chat_button_uses_the_resolved_number_and_can_be_searched(): void
    {
        $cart = $this->cart(number: '191156327592003@lid');
        $this->actingAs($this->operator())->get('/carts')->assertOk()->assertDontSee('https://wa.me/191156327592003');
        $cart->incomingMessage->update(['contact_phone' => '218912345678']);
        $response = $this->get('/carts?search=218912345678')->assertOk()
            ->assertSee('https://wa.me/218912345678')->assertSee('218912345678');
        $this->assertSame([$cart->id], $response->viewData('carts')->pluck('id')->all());
        $this->get(route('carts.show', $cart))->assertOk()->assertSee('https://wa.me/218912345678')->assertSee('فتح واتساب');
        $this->assertSame('191156327592003@lid', $cart->incomingMessage->fresh()->from);
        Http::assertNothingSent();
    }

    public function test_linked_customer_without_a_number_shows_unavailable_and_no_chat_link(): void
    {
        $cart = $this->cart(number: '191156327592003@lid');
        $this->actingAs($this->operator())->get(route('carts.show', $cart))->assertOk()
            ->assertSee('رقم الهاتف غير متاح من واتساب.')->assertDontSee('فتح واتساب')
            ->assertDontSee('https://wa.me/191156327592003');
        Http::assertNothingSent();
    }

    public function test_receiving_number_and_session_are_shown_and_filters_keep_the_original_session(): void
    {
        $first = 'cf2859dd56474dd69db06308afde6d92';
        $second = 'd8e21afbe77f4d8bbaed65f2c6c2edf7';
        $cart = $this->cart(['operator_status' => 'confirmed']);
        $cart->incomingMessage->update(['session_uuid' => $first, 'session_name' => 'shop2go10267', 'receiving_phone' => '218920703777']);
        $other = $this->cart(['operator_status' => 'confirmed']);
        $other->incomingMessage->update(['session_uuid' => $second, 'session_name' => 'shop2go43908', 'receiving_phone' => '218911111111']);
        Setting::findOrFail(1)->update(['whatsapp_main_session_uuid' => $second]);
        $response = $this->actingAs($this->operator())->get('/carts?session_uuid='.$first.'&per_page=10&operator_status=confirmed')
            ->assertOk()->assertSee('رقم الاستقبال')->assertSee('218920703777')->assertSee('shop2go10267');
        $this->assertSame([$cart->id], $response->viewData('carts')->pluck('id')->all());
        $this->assertSame(1, (int) $response->viewData('counts')['confirmed']);
        $this->assertStringContainsString('session_uuid='.$first, $response->viewData('carts')->url(2));
        $response->assertSee('session_uuid='.$first.'&amp;per_page=10', false);
        $this->get(route('carts.show', $cart))->assertOk()->assertSee('shop2go10267')->assertSee('218920703777')->assertSee($first)
            ->assertDontSee('shop2go43908');
        $this->assertSame($first, $cart->incomingMessage->fresh()->session_uuid);
        Http::assertNothingSent();
    }

    public function test_manual_carts_are_not_assigned_to_the_main_whatsapp_session(): void
    {
        $cart = $this->cart(['origin' => 'manual']);
        $this->actingAs($this->operator())->get(route('carts.show', $cart))->assertOk()->assertDontSee('رقم الاستقبال');
        $this->get('/carts?session_uuid=not-a-session')->assertSessionHasErrors('session_uuid');
        Http::assertNothingSent();
    }

    public function test_search_and_status_filters_return_matching_carts_and_counts(): void
    {
        $match = $this->cart(['operator_status' => 'buying'], 'Salma', '218911111111');
        $this->cart(['operator_status' => 'dismissed'], 'Salma', '218922222222');
        $this->cart(['operator_status' => 'buying'], 'Omar', '218933333333');
        $response = $this->actingAs($this->operator())->get('/carts?search=Salma&operator_status=buying')->assertOk();
        $this->assertSame([$match->id], $response->viewData('carts')->pluck('id')->all());
        $this->assertEquals(1, $response->viewData('counts')['buying']);
        $this->assertEquals(1, $response->viewData('counts')['dismissed']);
        $response = $this->get('/carts?search=218911111111')->assertOk();
        $this->assertSame([$match->id], $response->viewData('carts')->pluck('id')->all());
        $response = $this->get('/carts?search=%23'.$match->id)->assertOk();
        $this->assertSame([$match->id], $response->viewData('carts')->pluck('id')->all());
    }

    public function test_paginates_without_dropping_filters(): void
    {
        for ($i = 0; $i < 26; $i++) {
            $this->cart(['operator_status' => 'buying']);
        }
        $response = $this->actingAs($this->operator())->get('/carts?operator_status=buying&page=2')->assertOk();
        $this->assertCount(1, $response->viewData('carts'));
        $this->assertSame(26, $response->viewData('carts')->total());
        $this->assertStringContainsString('operator_status=buying', $response->viewData('carts')->url(1));
    }

    public function test_per_page_choice_is_used_and_preserved_with_filters(): void
    {
        for ($i = 0; $i < 11; $i++) {
            $this->cart(['operator_status' => 'confirmed']);
        }
        $response = $this->actingAs($this->operator())->get('/carts?per_page=10&operator_status=confirmed&page=2')->assertOk();
        $this->assertCount(1, $response->viewData('carts'));
        $this->assertSame(10, $response->viewData('carts')->perPage());
        $this->assertStringContainsString('per_page=10', $response->viewData('carts')->url(1));
        $response->assertSee('عدد السلات في الصفحة')->assertSee('shein-status-nav');
        $this->get('/carts?per_page=500')->assertSessionHasErrors('per_page');
    }

    public function test_saves_followup_status_notes_and_operator_without_editing_api_fields(): void
    {
        $cart = $this->cart();
        $operator = $this->operator();
        $this->actingAs($operator)->put(route('carts.update', $cart), [
            'operator_status' => 'buying', 'operator_notes' => 'العميل وافق على الشراء.',
            'status' => 'failed', 'payload' => [], 'quote_id' => 'hijacked', 'whatsapp_messages_id' => 999,
        ])->assertRedirect(route('carts.show', $cart))->assertSessionHas('success');
        $cart->refresh();
        $this->assertSame('buying', $cart->operator_status);
        $this->assertSame('العميل وافق على الشراء.', $cart->operator_notes);
        $this->assertEquals($operator->id, $cart->handled_by);
        $this->assertNotNull($cart->operator_updated_at);
        $this->assertSame('succeeded', $cart->status);
        $this->assertSame('quote-1', $cart->quote_id);
        $this->assertSame('447.821', $cart->payload['result']['total']['amount']);
        $this->assertDatabaseHas('logs', ['user_id' => $operator->id, 'type' => 'cart', 'title' => 'متابعة سلة شي إن #'.$cart->id]);
        $this->assertDatabaseHas('cart_status_audits', ['cart_quote_id' => $cart->id,
            'previous_status' => 'new', 'new_status' => 'buying',
            'actor_type' => 'operator', 'actor_user_id' => $operator->id, 'actor_name' => $operator->name,
            'previous_notes' => null, 'new_notes' => 'العميل وافق على الشراء.']);
        $this->get(route('carts.show', $cart))->assertOk()->assertSee('العميل وافق على الشراء.')->assertSee('موظف المتابعة');
        Http::assertNothingSent();
    }

    public function test_cart_can_be_dismissed_and_reopened_and_unchanged_saves_do_not_duplicate_logs(): void
    {
        $cart = $this->cart();
        $this->actingAs($this->operator());
        foreach (['following_up', 'buying', 'purchased', 'dismissed', 'new'] as $status) {
            $this->put(route('carts.update', $cart), ['operator_status' => $status])->assertRedirect();
            $this->assertSame($status, $cart->fresh()->operator_status);
        }
        $this->assertDatabaseCount('logs', 5);
        $this->assertDatabaseCount('cart_status_audits', 5);
        $this->put(route('carts.update', $cart), ['operator_status' => 'new'])->assertRedirect();
        $this->assertDatabaseCount('logs', 5);
        $this->assertDatabaseCount('cart_status_audits', 5);
    }

    public function test_operator_can_use_customer_action_statuses_and_reopen_a_cart(): void
    {
        $cart = $this->cart();
        $this->actingAs($this->operator());
        foreach (['editing' => 'editing', 'confirmed' => 'confirmed', 'cancelled' => 'cancelled', 'new' => 'open'] as $status => $customerStatus) {
            $this->put(route('carts.update', $cart), ['operator_status' => $status])->assertRedirect();
            $this->assertSame($status, $cart->fresh()->operator_status);
            $this->assertSame($customerStatus, $cart->fresh()->customer_status);
        }
        $this->assertDatabaseCount('cart_status_audits', 4);
        $this->get('/carts?operator_status=confirmed')->assertOk()->assertSee('مؤكدة');
    }

    public function test_notes_only_edits_are_audited_and_displayed_with_the_change_time(): void
    {
        $this->freezeTime();
        $cart = $this->cart();
        $operator = $this->operator();
        $this->actingAs($operator)->put(route('carts.update', $cart), [
            'operator_status' => 'new', 'operator_notes' => 'اتصل بالعميل غداً.',
        ])->assertRedirect();
        $audit = $cart->statusAudits()->first();
        $this->assertSame('new', $audit->previous_status);
        $this->assertSame('new', $audit->new_status);
        $this->assertSame('operator', $audit->actor_type);
        $this->assertTrue($audit->changed_at->equalTo(now()->startOfSecond()));
        $this->get(route('carts.show', $cart))->assertOk()->assertSee('سجل تغييرات الحالة')
            ->assertSee('تم تعديل ملاحظات المتابعة')->assertSee($operator->name)
            ->assertSee(now()->format('Y-m-d H:i:s'))->assertSee('Africa/Tripoli');
        $this->put(route('carts.update', $cart), ['operator_status' => 'new', 'operator_notes' => 'اتصل بالعميل غداً.'])->assertRedirect();
        $this->assertDatabaseCount('cart_status_audits', 1);
    }

    public function test_audit_keeps_the_actor_name_when_the_operator_profile_changes(): void
    {
        $cart = $this->cart();
        $operator = $this->operator();
        $this->actingAs($operator)->put(route('carts.update', $cart), ['operator_status' => 'confirmed'])->assertRedirect();
        $operator->update(['name' => 'اسم جديد']);
        $this->assertSame('موظف المتابعة', $cart->statusAudits()->first()->actor_name);
        $this->get(route('carts.show', $cart))->assertOk()->assertSee('موظف المتابعة')->assertSee('موظف النظام');
    }

    public function test_audit_history_is_paginated_scoped_and_read_only(): void
    {
        $cart = $this->cart();
        $other = $this->cart();
        $operator = $this->operator();
        $this->actingAs($operator);
        for ($i = 0; $i < 21; $i++) {
            $this->put(route('carts.update', $cart), ['operator_status' => $i % 2 ? 'new' : 'following_up'])->assertRedirect();
        }
        $this->put(route('carts.update', $other), ['operator_status' => 'cancelled'])->assertRedirect();
        $reader = $this->operator(['cart-list']);
        $response = $this->actingAs($reader)->get(route('carts.show', $cart).'?history_page=2')->assertOk();
        $history = $response->viewData('statusAudits');
        $this->assertCount(1, $history);
        $this->assertSame(21, $history->total());
        $this->assertSame($cart->id, $history->first()->cart_quote_id);
        $this->assertSame($cart->statusAudits()->first()->id, $response->viewData('latestStatusAudit')->id);
        $this->put(route('carts.update', $cart), ['operator_status' => 'cancelled'])->assertForbidden();
        $this->assertDatabaseCount('cart_status_audits', 22);
    }

    public function test_provider_completion_does_not_overwrite_followup_status_or_notes(): void
    {
        $cart = $this->cart(['status' => 'submitted', 'payload' => null]);
        $this->actingAs($this->operator())->put(route('carts.update', $cart), [
            'operator_status' => 'dismissed', 'operator_notes' => 'العميل لا يرغب في الشراء.',
        ])->assertRedirect();
        \App\Models\Setting::findOrFail(1)->update(['usd_to_lyd_rate' => '7', 'shein_pause_on_empty_wallet' => false]);
        \Illuminate\Support\Facades\Storage::fake('local');
        config(['services.cart_quotes.base_url' => 'https://quotes.test', 'services.cart_quotes.token' => 'test']);
        Http::fake(['quotes.test/*' => Http::response(['id' => $cart->quote_id, 'status' => 'succeeded',
            'result' => ['itemCount' => 2, 'total' => ['amount' => '41.37', 'currency' => 'USD'],
                'items' => [['name' => 'Test item', 'qty' => 2, 'availability' => 'available',
                    'outputLine' => ['amount' => '41.37', 'currency' => 'USD']]]]] )]);
        $this->mock(\App\Services\WhatsAppService::class, fn ($mock) => $mock->shouldReceive('sendDocument')->once()->andReturn(true));
        $this->artisan('cart-quotes:process')->assertExitCode(0);
        $cart->refresh();
        $this->assertSame('succeeded', $cart->status);
        $this->assertSame('dismissed', $cart->operator_status);
        $this->assertSame('العميل لا يرغب في الشراء.', $cart->operator_notes);
    }

    /** @dataProvider invalidUpdates */
    public function test_rejects_invalid_followup_updates(array $payload, string $field): void
    {
        $cart = $this->cart();
        $this->actingAs($this->operator())->from(route('carts.show', $cart))->put(route('carts.update', $cart), $payload)
            ->assertSessionHasErrors($field);
        $this->assertSame('new', $cart->fresh()->operator_status);
        $this->assertDatabaseCount('logs', 0);
    }

    public static function invalidUpdates(): array
    {
        return [
            [[], 'operator_status'],
            [['operator_status' => 'succeeded'], 'operator_status'],
            [['operator_status' => ['buying']], 'operator_status'],
            [['operator_status' => 'buying', 'operator_notes' => str_repeat('a', 3001)], 'operator_notes'],
        ];
    }

    public function test_permissions_protect_listing_details_and_updates(): void
    {
        $cart = $this->cart();
        $this->get('/carts')->assertRedirect();
        $this->put(route('carts.update', $cart), ['operator_status' => 'buying'])->assertRedirect();
        $this->actingAs($this->operator(['cart-list']))->get(route('carts.show', $cart))->assertOk()->assertDontSee('حفظ المتابعة');
        $this->put(route('carts.update', $cart), ['operator_status' => 'buying'])->assertForbidden();
        $this->actingAs($this->operator(['cart-edit']))->get('/carts')->assertForbidden();
        $this->get(route('carts.show', $cart))->assertForbidden();
        $this->put(route('carts.update', $cart), ['operator_status' => 'buying'])->assertForbidden();
        $this->assertSame('new', $cart->fresh()->operator_status);
    }

    public function test_escapes_customer_content_and_omits_invalid_whatsapp_links(): void
    {
        $cart = $this->cart([], '<script>alert(1)</script>', 'not-a-phone');
        $cart->incomingMessage->update(['message' => '<img src=x onerror=alert(1)>']);
        $this->actingAs($this->operator())->get(route('carts.show', $cart))->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('https://wa.me/');
    }

    public function test_cart_permissions_seeder_is_repeatable_and_preserves_existing_admin_permissions(): void
    {
        $role = Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        $permission = Permission::create(['name' => 'system-edit', 'guard_name' => 'web', 'ar_name' => 'إعدادات النظام']);
        $role->givePermissionTo($permission);
        $this->seed(CartPermissionsSeeder::class);
        $this->seed(CartPermissionsSeeder::class);
        $this->assertTrue($role->fresh()->hasPermissionTo('system-edit'));
        $this->assertTrue($role->fresh()->hasPermissionTo('cart-list'));
        $this->assertTrue($role->fresh()->hasPermissionTo('cart-edit'));
        $this->assertTrue($role->fresh()->hasPermissionTo('cart-create'));
        $this->assertSame(3, Permission::where('name', 'like', 'cart-%')->count());
    }

    public function test_tracking_migration_can_be_reversed_on_sqlite_without_losing_the_cart(): void
    {
        $cart = $this->cart();
        $migration = require database_path('migrations/2026_10_04_000003_add_operator_tracking_to_cart_quotes.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('cart_quotes', 'operator_status'));
        $this->assertSame('succeeded', CartQuote::findOrFail($cart->id)->status);
        $migration->up();
        $this->assertSame('new', CartQuote::findOrFail($cart->id)->operator_status);
    }
}
