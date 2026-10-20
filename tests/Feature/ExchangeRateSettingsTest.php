<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ExchangeRateSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $permissions = ['system-list', 'system-edit']): User
    {
        DB::table('types')->updateOrInsert(['id' => 1], ['type' => 'مدير النظام', 'created_at' => now(), 'updated_at' => now()]);
        $admin = User::factory()->create(['type_id' => 1, 'status' => true]);
        foreach ($permissions as $name) {
            $admin->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['ar_name' => $name]));
        }
        return $admin;
    }

    public function test_rate_starts_unset_and_settings_are_visible_in_the_menu(): void
    {
        $this->assertNull(Setting::usdToLydRate());
        $this->actingAs($this->admin())->get('/settings')->assertOk()
            ->assertSee('لم يتم تحديد سعر الصرف بعد.')
            ->assertSee('سعر الصرف USD → LYD')
            ->assertSee('حفظ التغييرات');
    }

    public function test_saves_and_updates_one_rate_without_losing_decimal_precision(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->put('/settings', ['usd_to_lyd_rate' => '7.123456'])
            ->assertRedirect(route('settings.edit'))->assertSessionHas('success');
        $this->assertSame('7.123456', Setting::usdToLydRate());
        $this->get('/settings')->assertOk()->assertSee('1 USD = 7.123456 LYD');
        $this->put('/settings', ['usd_to_lyd_rate' => '8.5'])->assertRedirect(route('settings.edit'));
        $this->assertSame('8.500000', Setting::usdToLydRate());
        $this->assertDatabaseCount('settings', 1);
        $this->assertDatabaseHas('logs', ['type' => 'settings', 'user_id' => $admin->id,
            'log' => 'تم تعديل سعر الصرف من (7.123456) إلى (8.500000) دينار لكل دولار.']);
    }

    public function test_amazon_rules_show_defaults_and_save_with_permissions_and_audit(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/settings')->assertOk()->assertSee('name="amazon_fixed_fee_usd"', false)
            ->assertSee('name="amazon_flat_surcharge_usd"', false)->assertSee('name="amazon_threshold_usd"', false)
            ->assertSee('name="amazon_percentage"', false)->assertSee('إعدادات عروض شي إن');
        $rules = ['amazon_fixed_fee_usd' => '2.125', 'amazon_flat_surcharge_usd' => '7.25',
            'amazon_threshold_usd' => '80.125', 'amazon_percentage' => '12.5'];
        $this->put('/settings', ['usd_to_lyd_rate' => '7.123456'] + $rules)
            ->assertRedirect(route('settings.edit'))->assertSessionHasNoErrors();
        $settings = Setting::findOrFail(1);
        $this->assertSame('2.1250', $settings->amazon_fixed_fee_usd);
        $this->assertSame('7.2500', $settings->amazon_flat_surcharge_usd);
        $this->assertSame('80.1250', $settings->amazon_threshold_usd);
        $this->assertSame('12.5000', $settings->amazon_percentage);
        $this->assertSame('0.0000', $settings->shein_company_percentage);
        $this->assertDatabaseHas('logs', ['user_id' => $admin->id, 'title' => 'تعديل قواعد تسعير أمازون']);
        $this->put('/settings', ['usd_to_lyd_rate' => '8'])->assertSessionHasNoErrors();
        $this->assertSame('2.1250', $settings->fresh()->amazon_fixed_fee_usd);
        $this->actingAs($this->admin(['system-list']))->put('/settings', ['usd_to_lyd_rate' => '7'] + $rules)
            ->assertForbidden();
    }

    /** @dataProvider invalidAmazonRules */
    public function test_invalid_amazon_rules_do_not_partially_save(string $field, $value): void
    {
        $this->actingAs($this->admin())->put('/settings', ['usd_to_lyd_rate' => '7', $field => $value])
            ->assertSessionHasErrors($field);
        $this->assertNull(Setting::usdToLydRate());
        $this->assertSame('5.0000', Setting::findOrFail(1)->amazon_fixed_fee_usd);
        $this->assertDatabaseCount('logs', 0);
    }

    public static function invalidAmazonRules(): array
    {
        return [['amazon_fixed_fee_usd', '-1'], ['amazon_flat_surcharge_usd', '2.12345'],
            ['amazon_threshold_usd', '0'], ['amazon_threshold_usd', '0.0000'], ['amazon_percentage', '1000.0001'],
            ['amazon_percentage', '1e2'], ['amazon_fixed_fee_usd', ['10']], ['amazon_percentage', ''],
            ['amazon_threshold_usd', '1000000'], ['amazon_fixed_fee_usd', '9999999']];
    }

    public function test_saves_editable_shein_messages_percentage_and_calculation_note(): void
    {
        $this->actingAs($this->admin())->put('/settings', ['usd_to_lyd_rate' => '10.125678',
            'shein_company_percentage' => '12.3456', 'shein_received_message' => 'استلمنا سلتك',
            'shein_product_message' => 'أضف منتجاتك إلى السلة وأرسل رابطها',
            'shein_completed_message' => 'عرضك جاهز', 'shein_calculation_note' => 'تشمل الأسعار نسبة الشركة.'])
            ->assertRedirect(route('settings.edit'));
        $setting = Setting::findOrFail(1);
        $this->assertSame('12.3456', $setting->shein_company_percentage);
        $this->assertSame('استلمنا سلتك', $setting->receivedMessage());
        $this->assertSame('أضف منتجاتك إلى السلة وأرسل رابطها', $setting->productMessage());
        $this->assertSame('عرضك جاهز', $setting->completedMessage());
        $this->assertSame('تشمل الأسعار نسبة الشركة.', $setting->calculationNote());
        $this->get('/settings')->assertOk()->assertSee('12.3456')->assertSee('عرضك جاهز')
            ->assertSee('أضف منتجاتك إلى السلة وأرسل رابطها');
    }

    /** @dataProvider invalidPercentages */
    public function test_rejects_invalid_percentage_without_changing_rate($percentage): void
    {
        Setting::findOrFail(1)->update(['usd_to_lyd_rate' => '7']);
        $this->actingAs($this->admin())->put('/settings', ['usd_to_lyd_rate' => '10',
            'shein_company_percentage' => $percentage])->assertSessionHasErrors('shein_company_percentage');
        $this->assertSame('7.000000', Setting::usdToLydRate());
        $this->assertSame('0.0000', Setting::findOrFail(1)->shein_company_percentage);
    }

    public static function invalidPercentages(): array
    {
        return [['-1'], ['1000.0001'], ['1e2'], ['12.12345'], [['10']], ['']];
    }

    public function test_shein_messages_cannot_be_empty(): void
    {
        $this->actingAs($this->admin())->put('/settings', ['usd_to_lyd_rate' => '7',
            'shein_received_message' => '', 'shein_product_message' => '', 'shein_completed_message' => '', 'shein_calculation_note' => ''])
            ->assertSessionHasErrors(['shein_received_message', 'shein_product_message', 'shein_completed_message', 'shein_calculation_note']);
        $this->assertNull(Setting::usdToLydRate());
    }

    public function test_cart_button_replies_show_defaults_and_save_with_an_actor_audit(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/settings')->assertOk()
            ->assertSee('ردود أزرار السلة في واتساب')->assertSee('{cart_ref}')
            ->assertSee(Setting::CART_ACTION_REPLIES['confirm'])
            ->assertSee(Setting::CART_ACTION_REPLIES['edit'])
            ->assertSee(Setting::CART_ACTION_REPLIES['cancel']);
        $messages = ['shein_confirm_reply' => 'تم تأكيد {cart_ref}. شكراً لك!',
            'shein_edit_reply' => 'أرسل كمياتك الجديدة للطلب {cart_ref}.',
            'shein_cancel_reply' => 'تم إلغاء السلة.'];
        $this->put('/settings', ['usd_to_lyd_rate' => '7'] + $messages)
            ->assertRedirect(route('settings.edit'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('settings', ['id' => 1] + $messages);
        $this->assertDatabaseHas('logs', ['user_id' => $admin->id, 'type' => 'settings',
            'title' => 'تعديل ردود أزرار السلة في واتساب',
            'log' => 'تم تعديل رسائل الرد على: تأكيد الطلب، تعديل الكميات، إلغاء الطلب.']);
        $this->get('/settings')->assertOk()->assertSee($messages['shein_confirm_reply']);
        $this->put('/settings', ['usd_to_lyd_rate' => '8'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('settings', ['id' => 1] + $messages);
    }

    public function test_invalid_cart_button_replies_do_not_save_any_settings(): void
    {
        $this->actingAs($this->admin())->put('/settings', ['usd_to_lyd_rate' => '7',
            'shein_confirm_reply' => '   ', 'shein_edit_reply' => str_repeat('ع', 2001),
            'shein_cancel_reply' => ['invalid']])
            ->assertSessionHasErrors(['shein_confirm_reply', 'shein_edit_reply', 'shein_cancel_reply']);
        $this->assertNull(Setting::usdToLydRate());
        $this->assertNull(Setting::findOrFail(1)->shein_confirm_reply);
        $this->assertDatabaseCount('logs', 0);
    }

    public function test_cart_reply_settings_require_edit_permission_and_escape_message_html(): void
    {
        Setting::findOrFail(1)->update(['shein_confirm_reply' => '<script>alert(1)</script> {cart_ref}']);
        $this->actingAs($this->admin(['system-list']))->get('/settings')->assertOk()
            ->assertSee('<script>alert(1)</script> {cart_ref}')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('readonly')->assertDontSee('حفظ التغييرات');
        $this->put('/settings', ['usd_to_lyd_rate' => '7', 'shein_confirm_reply' => 'Changed'])
            ->assertForbidden();
        $this->assertSame('<script>alert(1)</script> {cart_ref}', Setting::findOrFail(1)->shein_confirm_reply);
    }

    public function test_wallet_pause_is_enabled_by_default_and_can_be_saved(): void
    {
        $this->assertTrue(Setting::findOrFail(1)->shein_pause_on_empty_wallet);
        $this->actingAs($this->admin())->get('/settings')->assertOk()
            ->assertSee('إيقاف ردود سلات شي إن عند نفاد رصيد المحفظة');
        foreach (['0' => false, '1' => true] as $value => $enabled) {
            $this->put('/settings', ['usd_to_lyd_rate' => '7', 'shein_pause_on_empty_wallet' => $value])
                ->assertRedirect(route('settings.edit'))->assertSessionHasNoErrors();
            $this->assertSame($enabled, Setting::findOrFail(1)->shein_pause_on_empty_wallet);
        }
        $this->put('/settings', ['usd_to_lyd_rate' => '7'])->assertSessionHasNoErrors();
        $this->assertTrue(Setting::findOrFail(1)->shein_pause_on_empty_wallet);
    }

    public function test_wallet_pause_rejects_invalid_values_and_requires_edit_permission(): void
    {
        $this->actingAs($this->admin())->put('/settings', [
            'usd_to_lyd_rate' => '7', 'shein_pause_on_empty_wallet' => 'invalid',
        ])->assertSessionHasErrors('shein_pause_on_empty_wallet');
        $this->assertTrue(Setting::findOrFail(1)->shein_pause_on_empty_wallet);
        $this->actingAs($this->admin(['system-list']))->put('/settings', [
            'usd_to_lyd_rate' => '7', 'shein_pause_on_empty_wallet' => '0',
        ])->assertForbidden();
        $this->assertTrue(Setting::findOrFail(1)->shein_pause_on_empty_wallet);
    }

    /** @dataProvider invalidRates */
    public function test_rejects_invalid_rates_without_changing_saved_rate($rate): void
    {
        Setting::findOrFail(1)->update(['usd_to_lyd_rate' => '7.125']);
        $this->actingAs($this->admin())->from('/settings')->put('/settings', ['usd_to_lyd_rate' => $rate])
            ->assertRedirect('/settings')->assertSessionHasErrors('usd_to_lyd_rate');
        $this->assertSame('7.125000', Setting::usdToLydRate());
        $this->assertDatabaseCount('logs', 0);
    }

    public static function invalidRates(): array
    {
        return [[''], ['0'], ['0.000000'], ['-5'], ['1e2'], ['abc'], ['7.1234567'], ['1000000'], [['7.5']]];
    }

    public function test_requires_authentication_and_edit_permission(): void
    {
        $this->get('/settings')->assertRedirect();
        $this->put('/settings', ['usd_to_lyd_rate' => '7'])->assertRedirect();
        $viewer = $this->admin(['system-list']);
        $this->actingAs($viewer)->get('/settings')->assertOk()->assertDontSee('حفظ التغييرات');
        $this->put('/settings', ['usd_to_lyd_rate' => '7'])->assertForbidden();
        $this->assertNull(Setting::usdToLydRate());
    }

    public function test_admin_without_settings_permissions_cannot_read_or_write_rate(): void
    {
        $this->actingAs($this->admin([]))->get('/settings')->assertForbidden();
        $this->put('/settings', ['usd_to_lyd_rate' => '7'])->assertForbidden();
        $this->assertNull(Setting::usdToLydRate());
    }
}
