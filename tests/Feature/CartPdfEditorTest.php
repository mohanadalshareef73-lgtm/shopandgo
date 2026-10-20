<?php

namespace Tests\Feature;

use App\Models\CartQuote;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhatsappMessages;
use App\Services\SheinQuotationCalculator;
use App\Services\SheinQuotationPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CartPdfEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Storage::fake('local');
        Setting::findOrFail(1)->update(['usd_to_lyd_rate' => '7.125', 'shein_company_percentage' => '12.5']);
    }

    private function operator(array $permissions = ['cart-list', 'cart-edit']): User
    {
        DB::table('types')->updateOrInsert(['id' => 2], ['type' => 'موظف', 'created_at' => now(), 'updated_at' => now()]);
        $user = User::factory()->create(['type_id' => 2, 'status' => true]);
        foreach ($permissions as $name) {
            $user->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['ar_name' => $name]));
        }
        return $user;
    }

    private function cart(): CartQuote
    {
        $incoming = WhatsappMessages::create(['message_id' => 'pdf-editor', 'from' => '218916655677',
            'phone' => '218920703777', 'name' => 'عميل تجريبي', 'type' => 'text', 'message' => 'https://shein.com/cart',
            'timestamp' => now(), 'is_reply' => true]);
        $cart = CartQuote::create(['whatsapp_messages_id' => $incoming->id, 'source_url' => 'https://shein.com/cart',
            'idempotency_key' => 'pdf-editor', 'status' => 'succeeded', 'operator_status' => 'new', 'notified_at' => now(),
            'payload' => ['result' => ['itemCount' => 3, 'total' => ['amount' => '20.02', 'currency' => 'USD'],
                'items' => [['name' => 'Test dress', 'variant' => 'Black / M', 'qty' => 2, 'qtySource' => 'assumed_one',
                    'availability' => 'available', 'unitOriginal' => ['amount' => '15.00', 'currency' => 'USD'],
                    'outputLine' => ['amount' => '20.02', 'currency' => 'USD']],
                    ['name' => 'Unavailable', 'qty' => 1, 'availability' => 'unavailable']]]]]);
        return app(SheinQuotationPdf::class)->prepare($cart);
    }

    private function values(CartQuote $cart): array
    {
        return ['revision' => app(SheinQuotationPdf::class)->revision($cart), 'exchange_rate' => '7',
            'company_percentage' => '20', 'items' => [
                ['qty' => '3', 'original_usd' => '20', 'sale_usd' => '10', 'customer_usd' => null],
                ['qty' => '1', 'original_usd' => null, 'sale_usd' => null, 'customer_usd' => null],
            ]];
    }

    public function test_operator_can_preview_pdf_inline_and_download_the_same_file(): void
    {
        $cart = $this->cart();
        $this->actingAs($this->operator(['cart-list']));

        $preview = $this->get(route('carts.quotation', ['cart' => $cart, 'preview' => 1]))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename=shein-quotation-'.$cart->id.'.pdf');
        $download = $this->get(route('carts.quotation', $cart))
            ->assertOk()->assertDownload('shein-quotation-'.$cart->id.'.pdf');

        $this->assertSame($preview->baseResponse->getFile()->getPathname(), $download->baseResponse->getFile()->getPathname());
        Http::assertNothingSent();
    }

    public function test_edits_quantities_and_prices_only_for_pdf_without_network_or_repricing_source(): void
    {
        $cart = $this->cart();
        $source = $cart->payload;
        $snapshot = $cart->quotation;
        $path = $cart->pdf_path;
        $notified = $cart->notified_at->toIso8601String();
        $settings = Setting::findOrFail(1)->toArray();
        $this->actingAs($this->operator())->get(route('carts.show', $cart))->assertOk()->assertSee('حفظ وإنشاء PDF');
        $this->put(route('carts.pdf.update', $cart), $this->values($cart) + ['payload' => [], 'quotation' => [], 'status' => 'failed'])
            ->assertRedirect(route('carts.show', $cart))->assertSessionHas('success');
        $cart->refresh();
        $this->assertSame($source, $cart->payload);
        $this->assertSame($snapshot, $cart->quotation);
        $this->assertSame($settings, Setting::findOrFail(1)->toArray());
        $this->assertSame($notified, $cart->notified_at->toIso8601String());
        $this->assertNotSame($path, $cart->pdf_path);
        $effective = app(SheinQuotationPdf::class)->quotationForPdf($cart);
        $display = app(SheinQuotationCalculator::class)->presentation($effective);
        $this->assertSame(3, $effective['items'][0]['qty']);
        $this->assertSame('operator', $effective['items'][0]['qty_source']);
        $this->assertTrue(\Brick\Math\BigDecimal::of($effective['items'][0]['unit_lyd'])->isEqualTo('84'));
        $this->assertSame('252', $effective['total']['amount']);
        $this->assertSame(4, $effective['item_count']);
        $this->assertSame(3, $effective['available_quantity']);
        $this->assertSame('24.00', $display['items'][0]['original_usd']);
        $this->assertSame('12.00', $display['items'][0]['sale_usd']);
        $this->assertSame('36.00', $display['total_usd']);
        $this->assertNull($effective['items'][1]['line_lyd']);
        $this->assertDatabaseCount('cart_quotes', 1);
        $this->assertDatabaseHas('logs', ['title' => 'تعديل PDF سلة شي إن #'.$cart->id]);
        Http::assertNothingSent();
    }

    public function test_final_unit_price_override_replaces_percentage_and_reset_restores_original_snapshot(): void
    {
        $cart = $this->cart();
        $snapshot = $cart->quotation;
        $values = $this->values($cart);
        $values['items'][0]['customer_usd'] = '8.50';
        $this->actingAs($this->operator())->put(route('carts.pdf.update', $cart), $values)->assertRedirect()->assertSessionHasNoErrors();
        $cart->refresh();
        $pdf = app(SheinQuotationPdf::class);
        $q = $pdf->quotationForPdf($cart);
        $this->assertSame('179', $q['total']['amount']);
        $this->assertSame('8.50', app(SheinQuotationCalculator::class)->presentation($q)['items'][0]['sale_usd']);
        $this->assertSame('25.50', app(SheinQuotationCalculator::class)->presentation($q)['total_usd']);
        $this->delete(route('carts.pdf.reset', $cart), ['revision' => $pdf->revision($cart)])->assertRedirect()->assertSessionHasNoErrors();
        $cart->refresh();
        $this->assertNull($cart->pdf_overrides);
        $this->assertSame($snapshot, $pdf->quotationForPdf($cart));
        Http::assertNothingSent();
    }

    public function test_stale_editor_cannot_overwrite_newer_changes_or_reset_them(): void
    {
        $cart = $this->cart();
        $values = $this->values($cart);
        $this->actingAs($this->operator())->put(route('carts.pdf.update', $cart), $values)->assertSessionHasNoErrors();
        $overrides = $cart->fresh()->pdf_overrides;
        $values['items'][0]['qty'] = 99;
        $this->put(route('carts.pdf.update', $cart), $values)->assertSessionHasErrors('revision');
        $this->delete(route('carts.pdf.reset', $cart), ['revision' => $values['revision']])->assertSessionHasErrors('revision');
        $this->assertSame($overrides, $cart->fresh()->pdf_overrides);
    }

    public function test_default_editor_values_retain_precision_and_can_be_saved(): void
    {
        $cart = $this->cart();
        $pdf = app(SheinQuotationPdf::class);
        $values = $pdf->editorValues($cart) + ['revision' => $pdf->revision($cart)];
        $this->actingAs($this->operator())->put(route('carts.pdf.update', $cart), $values)
            ->assertRedirect()->assertSessionHasNoErrors();
        $effective = $pdf->quotationForPdf($cart->fresh());
        $this->assertSame('160', $effective['total']['amount']);
        $this->assertSame('80.24', app(SheinQuotationCalculator::class)->presentation($effective)['items'][0]['unit_lyd']);
        Http::assertNothingSent();
    }

    /** @dataProvider invalidEdits */
    public function test_rejects_invalid_or_incomplete_edits(string $field, $value): void
    {
        $cart = $this->cart();
        $values = $this->values($cart);
        data_set($values, $field, $value);
        $this->actingAs($this->operator())->put(route('carts.pdf.update', $cart), $values)->assertSessionHasErrors();
        $this->assertNull($cart->fresh()->pdf_overrides);
        Http::assertNothingSent();
    }

    public static function invalidEdits(): array
    {
        return [['items.0.qty', 0], ['items.0.qty', '1.5'], ['items.0.sale_usd', '-2'],
            ['items.0.sale_usd', '1e5'], ['items.0.sale_usd', null], ['items.0.customer_usd', '1.23456789123456789'],
            ['items.1.sale_usd', '3'], ['items', []], ['items', [['qty' => 2, 'sale_usd' => '10']]],
            ['exchange_rate', '0'], ['company_percentage', '1001']];
    }

    public function test_edit_permissions_and_readonly_operator(): void
    {
        $cart = $this->cart();
        $values = $this->values($cart);
        $this->put(route('carts.pdf.update', $cart), $values)->assertRedirect();
        $this->actingAs($this->operator(['cart-list']))->get(route('carts.show', $cart))->assertOk()->assertDontSee('حفظ وإنشاء PDF');
        $this->put(route('carts.pdf.update', $cart), $values)->assertForbidden();
        $this->delete(route('carts.pdf.reset', $cart), ['revision' => $values['revision']])->assertForbidden();
        $this->assertNull($cart->fresh()->pdf_overrides);
    }

    public function test_stamp_tracks_status_and_invalidates_cached_pdf_without_repricing(): void
    {
        $cart = $this->cart();
        $original = $cart->quotation;
        $path = $cart->pdf_path;
        $this->assertSame('عرض سعر', $cart->pdf_stamp);
        $this->actingAs($this->operator())->put(route('carts.update', $cart), ['operator_status' => 'purchased'])->assertRedirect();
        $cart = app(SheinQuotationPdf::class)->prepare($cart);
        $this->assertNotSame($path, $cart->pdf_path);
        $this->assertSame('تم الشراء', $cart->pdf_stamp);
        $this->assertSame($original, $cart->quotation);
        $display = app(SheinQuotationCalculator::class)->presentation($original);
        $html = view('pdf.shein-quotation', ['cart' => $cart, 'quotation' => $original, 'display' => $display, 'images' => []])->render();
        $this->assertStringContainsString('تم الشراء', $html);
        $this->assertStringNotContainsString('بانتظار تأكيد العميل', $html);
    }
}
