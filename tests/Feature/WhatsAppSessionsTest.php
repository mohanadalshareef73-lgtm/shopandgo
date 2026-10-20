<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Setting;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WhatsAppSessionsTest extends TestCase
{
    use RefreshDatabase;

    private const MAIN = 'cf2859dd56474dd69db06308afde6d92';

    private const SECOND = 'd8e21afbe77f4d8bbaed65f2c6c2edf7';

    private array $sessionList = [];

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Storage::fake('local');
        config(['services.whatsapp.api_url' => 'https://whatsapp.test/api',
            'services.whatsapp.api_token' => 'test-secret',
            'services.whatsapp.main_session_uuid' => self::MAIN]);
        $this->sessionList = [
            ['uuid' => self::MAIN, 'name' => 'shop2go10267', 'status' => 'connected'],
            ['uuid' => self::SECOND, 'name' => 'shop2go43908', 'status' => 'disconnected'],
        ];
    }

    private function admin(array $permissions = ['system-list', 'system-edit']): User
    {
        DB::table('types')->updateOrInsert(['id' => 1], ['type' => 'مدير النظام', 'created_at' => now(), 'updated_at' => now()]);
        $admin = User::factory()->create(['type_id' => 1, 'status' => true]);
        foreach ($permissions as $name) {
            $admin->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['ar_name' => $name]));
        }

        return $admin;
    }

    public function test_separate_page_loads_current_numbers_and_selects_main_by_uuid(): void
    {
        Http::fake(['whatsapp.test/*' => fn (Request $request) => $request->method() === 'GET'
            ? Http::response(['sessions' => $this->sessionList]) : Http::response([], 202)]);
        $this->actingAs($this->admin())->get('/settings')->assertOk()
            ->assertDontSee('name="main_session_uuid"', false)->assertDontSee('whatsapp_numbers[');
        $this->get('/settings/whatsapp')->assertOk()->assertSee('shop2go10267')->assertSee('shop2go43908')
            ->assertSee('متصل')->assertSee('غير متصل')->assertDontSee('whatsapp-name-3');
        Http::assertSent(fn (Request $request) => $request->url() === 'https://whatsapp.test/api/sessions'
            && $request->method() === 'GET' && $request->hasHeader('X-API-Key', 'test-secret'));
        $this->put('/settings/whatsapp', ['main_session_uuid' => self::SECOND])
            ->assertRedirect(route('settings.whatsapp.edit'))->assertSessionHasNoErrors();
        $this->assertNull(Setting::usdToLydRate());
        $this->assertSame(self::SECOND, Setting::findOrFail(1)->whatsappMainSessionUuid());

        // Reordering and adding sessions must not change the chosen sending number.
        $this->sessionList = [
            ['uuid' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'name' => 'new-number', 'status' => 'connected'],
            $this->sessionList[1], $this->sessionList[0],
        ];
        $this->get('/settings/whatsapp')->assertOk()->assertSee('new-number');
        $job = new SendWhatsAppMessageJob(['0911234567', '+218911234567'], 'Order created');
        $this->assertTrue($job->handle());
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request['session_uuid'] === self::SECOND && $request['phone'] === ['218911234567']);
        $this->put('/settings/whatsapp', ['main_session_uuid' => self::MAIN])->assertSessionHasNoErrors();
        $this->assertTrue($job->handle());
        Http::assertSent(fn (Request $request) => $request->method() === 'POST' && $request['session_uuid'] === self::MAIN);
        $this->assertDatabaseHas('logs', ['type' => 'settings', 'title' => 'تعديل الرقم الرئيسي لواتساب']);
    }

    public function test_reply_and_pdf_use_explicit_session_despite_a_different_main_sender(): void
    {
        Setting::findOrFail(1)->update(['whatsapp_main_session_uuid' => self::MAIN]);
        Http::fake(['whatsapp.test/*' => Http::response([], 202)]);
        $service = app(WhatsAppService::class);
        $this->assertTrue($service->sendMessage('218911234567', 'Reply', self::SECOND));
        Storage::disk('local')->put('quote.pdf', '%PDF-1.7 test');
        $this->assertTrue($service->sendDocument('218911234567', Storage::disk('local')->path('quote.pdf'),
            'quote.pdf', 'Quotation', self::SECOND));
        Http::assertSentCount(2);
        foreach (Http::recorded() as [$request]) {
            $this->assertSame(self::SECOND, $request['session_uuid']);
        }
    }

    public function test_offline_session_fails_once_without_switching_numbers(): void
    {
        Http::fake(['whatsapp.test/*' => Http::response(['message' => 'Session is not connected. Please connect it first.'], 400)]);
        $service = app(WhatsAppService::class);
        $this->assertFalse($service->sendMessage('218911234567', 'Reply', self::SECOND));
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request['session_uuid'] === self::SECOND);
        Http::fake(['whatsapp.test/*' => Http::response([], 400)]);
        $this->assertFalse((new SendWhatsAppMessageJob('0911234567', 'Order created'))->handle());
        $this->assertFalse(session('whatsapp_status'));
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request['session_uuid'] === self::MAIN);
    }

    public function test_missing_or_invalid_session_never_sends_without_a_session_uuid(): void
    {
        config(['services.whatsapp.main_session_uuid' => null]);
        $this->assertFalse(app(WhatsAppService::class)->sendMessage('218911234567', 'Automatic message'));
        $this->assertFalse(app(WhatsAppService::class)->sendMessage('218911234567', 'Reply', 'shop2go10267'));
        Http::assertNothingSent();
    }

    public function test_order_retries_keep_same_session(): void
    {
        Http::fake(['whatsapp.test/*' => Http::sequence()->push([], 503)->push([], 202)]);
        $response = app(WhatsAppService::class)->send(['phone' => ['218911234567'], 'message' => 'Shipment'], attempts: 3);
        $this->assertSame(202, $response->status());
        Http::assertSentCount(2);
        foreach (Http::recorded() as [$request]) {
            $this->assertSame(self::MAIN, $request['session_uuid']);
        }
    }

    public function test_order_sends_do_not_retry_offline_numbers(): void
    {
        Http::fake(['whatsapp.test/*' => Http::response([], 400)]);
        $response = app(WhatsAppService::class)->send(['phone' => ['218911234567'], 'message' => 'Shipment'], attempts: 3);
        $this->assertSame(400, $response->status());
        Http::assertSentCount(1);
    }

    public function test_refresh_removes_deleted_numbers_without_silently_changing_main(): void
    {
        Setting::findOrFail(1)->update(['whatsapp_main_session_uuid' => self::SECOND]);
        Http::fake(['whatsapp.test/*' => fn () => Http::response(['sessions' => $this->sessionList])]);
        $this->actingAs($this->admin())->get('/settings/whatsapp')->assertSee('shop2go43908');
        $this->sessionList = [$this->sessionList[0]];
        $this->get('/settings/whatsapp')->assertOk()->assertDontSee('shop2go43908')
            ->assertSee('الرقم الرئيسي المحفوظ لم يعد موجوداً');
        $this->assertSame(self::SECOND, Setting::findOrFail(1)->whatsappMainSessionUuid());
        $this->put('/settings/whatsapp', ['main_session_uuid' => self::SECOND])->assertSessionHasErrors('main_session_uuid');
        $this->assertSame(self::SECOND, Setting::findOrFail(1)->whatsappMainSessionUuid());
    }

    public function test_provider_failure_preserves_saved_sender_and_disables_selection(): void
    {
        Setting::findOrFail(1)->update(['whatsapp_main_session_uuid' => self::SECOND]);
        Http::fake(['whatsapp.test/*' => Http::response([], 503)]);
        $this->actingAs($this->admin())->get('/settings/whatsapp')->assertOk()
            ->assertSee('تعذر جلب أرقام واتساب')->assertDontSee('حفظ الرقم الرئيسي');
        $this->put('/settings/whatsapp', ['main_session_uuid' => self::MAIN])
            ->assertRedirect(route('settings.whatsapp.edit'))->assertSessionHas('error');
        $this->assertSame(self::SECOND, Setting::findOrFail(1)->whatsappMainSessionUuid());
    }

    public function test_empty_account_has_no_static_number_fields(): void
    {
        Http::fake(['whatsapp.test/*' => Http::response(['sessions' => []])]);
        $this->actingAs($this->admin())->get('/settings/whatsapp')->assertOk()
            ->assertSee('لا توجد أرقام مرتبطة')->assertDontSee('حفظ الرقم الرئيسي')->assertDontSee('name="main_session_uuid"', false);
    }

    /** @dataProvider invalidInventories */
    public function test_malformed_provider_inventory_cannot_change_sender($sessions): void
    {
        Setting::findOrFail(1)->update(['whatsapp_main_session_uuid' => self::SECOND]);
        Http::fake(['whatsapp.test/*' => Http::response(['sessions' => $sessions])]);
        $this->actingAs($this->admin())->put('/settings/whatsapp', ['main_session_uuid' => self::MAIN])
            ->assertSessionHas('error');
        $this->assertSame(self::SECOND, Setting::findOrFail(1)->whatsappMainSessionUuid());
    }

    public static function invalidInventories(): array
    {
        $valid = ['uuid' => self::MAIN, 'name' => 'main', 'status' => 'connected'];

        return [[null], [[['name' => 'main']]], [[$valid, $valid]], [['unexpected' => $valid]]];
    }

    public function test_rejects_session_names_and_numbers_outside_the_account(): void
    {
        Http::fake(['whatsapp.test/*' => Http::response(['sessions' => $this->sessionList])]);
        $this->actingAs($this->admin())->put('/settings/whatsapp', ['main_session_uuid' => 'shop2go10267'])
            ->assertSessionHasErrors('main_session_uuid');
        Http::assertNothingSent();
        $this->put('/settings/whatsapp', ['main_session_uuid' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'])
            ->assertSessionHasErrors('main_session_uuid');
        $this->assertNull(Setting::findOrFail(1)->whatsapp_main_session_uuid);
    }

    public function test_requires_authentication_and_settings_edit_permission(): void
    {
        $this->get('/settings/whatsapp')->assertRedirect();
        $this->put('/settings/whatsapp', ['main_session_uuid' => self::SECOND])->assertRedirect();
        Http::assertNothingSent();
        Http::fake(['whatsapp.test/*' => Http::response(['sessions' => $this->sessionList])]);
        $this->actingAs($this->admin(['system-list']))->get('/settings/whatsapp')->assertOk()
            ->assertDontSee('حفظ الرقم الرئيسي')->assertSee('disabled');
        $this->put('/settings/whatsapp', ['main_session_uuid' => self::SECOND])->assertForbidden();
        $this->assertNull(Setting::findOrFail(1)->whatsapp_main_session_uuid);
    }

    public function test_pricing_updates_cannot_change_whatsapp_sender(): void
    {
        Setting::findOrFail(1)->update(['whatsapp_main_session_uuid' => self::SECOND]);
        $this->actingAs($this->admin())->put('/settings', ['usd_to_lyd_rate' => '10',
            'main_session_uuid' => self::MAIN, 'whatsapp_main_number' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertSame(self::SECOND, Setting::findOrFail(1)->whatsappMainSessionUuid());
        Http::assertNothingSent();
    }

    public function test_migration_preserves_sender_selected_in_old_number_slots(): void
    {
        $migration = require base_path('database/migrations/2026_10_04_000009_add_whatsapp_main_session_uuid.php');
        $migration->down();
        Setting::findOrFail(1)->update(['whatsapp_numbers' => [
            1 => ['name' => 'main', 'session_uuid' => self::MAIN],
            2 => ['name' => 'second', 'session_uuid' => self::SECOND],
        ], 'whatsapp_main_number' => '2']);
        $migration->up();
        $this->assertSame(self::SECOND, Setting::findOrFail(1)->whatsapp_main_session_uuid);
    }
}
