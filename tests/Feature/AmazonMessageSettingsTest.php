<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\AmazonMessageTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AmazonMessageSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function operator(bool $canEdit = true): User
    {
        DB::table('types')->updateOrInsert(['id' => 2], ['type' => 'موظف']);
        $user = User::factory()->create(['type_id' => 2, 'status' => true]);
        foreach ($canEdit ? ['system-list', 'system-edit'] : ['system-list'] as $name) {
            $user->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['ar_name' => $name]));
        }

        return $user;
    }

    public function test_amazon_has_one_editable_message_per_status_and_saves_with_an_audit(): void
    {
        $user = $this->operator();
        $response = $this->actingAs($user)->get('/settings')->assertOk();
        $messages = [];
        foreach (AmazonMessageTemplates::DEFINITIONS as $status => $definition) {
            $response->assertSee('name="amazon_messages['.$status.']"', false)
                ->assertSee($definition['message'])
                ->assertDontSee('amazon_messages['.$status.'][ar]', false)
                ->assertDontSee('amazon_messages['.$status.'][en]', false);
            $messages[$status] = 'رسالة مخصصة '.$status;
        }
        $this->put('/settings', ['usd_to_lyd_rate' => '7', 'amazon_messages' => $messages])
            ->assertRedirect(route('settings.edit'))->assertSessionHasNoErrors();
        $this->assertSame($messages, Setting::findOrFail(1)->amazon_messages);
        $this->assertDatabaseHas('logs', ['user_id' => $user->id, 'type' => 'settings', 'title' => 'تعديل رسائل حالات أمازون']);
        $this->get('/settings')->assertOk()->assertSee('رسالة مخصصة received')->assertSee('رسالة مخصصة temporary_problem');

        $this->put('/settings', ['usd_to_lyd_rate' => '8', 'amazon_messages' => ['received' => 'Updated receipt']])
            ->assertSessionHasNoErrors();
        $messages['received'] = 'Updated receipt';
        $this->assertSame($messages, Setting::findOrFail(1)->amazon_messages);
        $this->put('/settings', ['usd_to_lyd_rate' => '9'])->assertSessionHasNoErrors();
        $this->assertSame($messages, Setting::findOrFail(1)->amazon_messages);
    }

    public function test_read_only_operator_can_view_messages_but_cannot_change_them(): void
    {
        $response = $this->actingAs($this->operator(false))->get('/settings')->assertOk();
        $this->assertMatchesRegularExpression('/id="amazon-message-received"[^>]*readonly/s', $response->getContent());
        $this->put('/settings', ['usd_to_lyd_rate' => '7', 'amazon_messages' => ['received' => 'Unauthorized']])
            ->assertForbidden();
        $this->assertNull(Setting::findOrFail(1)->amazon_messages);
    }

    /** @dataProvider invalidMessages */
    public function test_invalid_messages_cannot_partially_update_settings($messages, string $error): void
    {
        $this->actingAs($this->operator())->from('/settings')->put('/settings', [
            'usd_to_lyd_rate' => '7', 'amazon_messages' => $messages,
        ])->assertRedirect('/settings')->assertSessionHasErrors($error);
        $this->assertNull(Setting::usdToLydRate());
        $this->assertNull(Setting::findOrFail(1)->amazon_messages);
        $this->assertDatabaseCount('logs', 0);
        $this->get('/settings')->assertOk();
    }

    public static function invalidMessages(): array
    {
        return [
            ['not an array', 'amazon_messages'],
            [[], 'amazon_messages'],
            [['unknown_status' => 'No'], 'amazon_messages'],
            [['received' => ['ar' => 'No', 'en' => 'No']], 'amazon_messages.received'],
            [['received' => []], 'amazon_messages.received'],
            [['received' => ''], 'amazon_messages.received'],
            [['received' => '   '], 'amazon_messages.received'],
            [['received' => ['No']], 'amazon_messages.received'],
            [['received' => str_repeat('x', 2001)], 'amazon_messages.received'],
        ];
    }

    public function test_validation_keeps_unsaved_amazon_text_and_marks_the_field(): void
    {
        $this->actingAs($this->operator())->from('/settings')->put('/settings', [
            'usd_to_lyd_rate' => '0', 'amazon_messages' => ['received' => 'Unsaved receipt', 'expired' => ''],
        ])->assertSessionHasErrors(['usd_to_lyd_rate', 'amazon_messages.expired']);
        $response = $this->get('/settings')->assertOk()->assertSee('Unsaved receipt');
        $this->assertMatchesRegularExpression('/id="amazon-message-expired"[^>]*aria-invalid="true"/s', $response->getContent());
    }

    public function test_form_preserves_saved_arabic_text_from_bilingual_settings_and_accepts_one_replacement(): void
    {
        Setting::findOrFail(1)->update(['amazon_messages' => [
            'received' => ['ar' => 'الرسالة المحفوظة', 'en' => 'Old English receipt'],
            'expired' => ['en' => 'Saved English-only timeout'],
        ]]);
        $this->actingAs($this->operator())->get('/settings')->assertOk()
            ->assertSee('الرسالة المحفوظة')->assertDontSee('Old English receipt')
            ->assertSee('Saved English-only timeout');
        $this->assertSame('الرسالة المحفوظة', app(\App\Services\AmazonQuoteReply::class)->received());
        $this->put('/settings', ['usd_to_lyd_rate' => '7', 'amazon_messages' => ['received' => 'One replacement']])
            ->assertSessionHasNoErrors();
        $this->assertSame('One replacement', app(\App\Services\AmazonQuoteReply::class)->received());
        $this->assertSame('Saved English-only timeout', Setting::findOrFail(1)->amazonMessageTemplate('expired'));
    }
}
