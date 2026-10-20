<?php

namespace Tests\Feature;

use App\Models\CartQuote;
use App\Models\Setting;
use App\Models\WhatsappMessages;
use App\Services\CartQuoteException;
use App\Services\SheinQuotationCalculator;
use App\Services\SheinQuotationPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SheinQuotationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Http::preventStrayRequests();
        Setting::findOrFail(1)->update(['usd_to_lyd_rate' => '7.125', 'shein_company_percentage' => '12.5']);
    }

    private function cart(array $items = [], array $result = []): CartQuote
    {
        $incoming = WhatsappMessages::create(['message_id' => 'quotation-test', 'from' => '218916655677',
            'phone' => '218920703777', 'name' => 'عميل تجريبي', 'type' => 'text',
            'message' => 'https://shein.com/cart', 'timestamp' => now(), 'is_reply' => true]);
        $items = $items ?: [['name' => 'فستان Test dress', 'variant' => 'Black / M', 'qty' => 2,
            'qtySource' => 'shein', 'availability' => 'available',
            'outputLine' => ['amount' => '20.02', 'currency' => 'USD']],
            ['name' => 'Unavailable item', 'qty' => 1, 'qtySource' => 'assumed_one',
                'availability' => 'unavailable', 'outputLine' => null]];
        return CartQuote::create(['whatsapp_messages_id' => $incoming->id, 'source_url' => 'https://shein.com/cart',
            'idempotency_key' => 'quotation-test', 'status' => 'succeeded',
            'payload' => ['status' => 'succeeded', 'storefront' => 'AE',
                'result' => array_merge(['itemCount' => 3, 'total' => ['amount' => '20.02', 'currency' => 'USD'],
                    'items' => $items], $result)]]);
    }

    public function test_preserves_item_precision_and_rounds_only_the_final_lyd_total(): void
    {
        $q = app(SheinQuotationCalculator::class)->calculate($this->cart(), Setting::findOrFail(1));
        foreach (['unit_usd' => '10.01', 'unit_base_lyd' => '71.32125', 'unit_lyd' => '80.23640625',
            'unit_company_lyd' => '8.91515625', 'line_lyd' => '160.4728125'] as $key => $expected) {
            $this->assertTrue(\Brick\Math\BigDecimal::of($q['items'][0][$key])->isEqualTo($expected), $key);
        }
        $this->assertSame('160', $q['total']['amount']);
        $this->assertTrue(\Brick\Math\BigDecimal::of($q['company_lyd_total'])->isEqualTo('17.8303125'));
        $this->assertNull($q['items'][1]['line_lyd']);
        $this->assertSame(2, $q['available_quantity']);
    }

    public function test_line_totals_are_not_built_from_rounded_display_prices(): void
    {
        Setting::findOrFail(1)->update(['usd_to_lyd_rate' => '1', 'shein_company_percentage' => '0']);
        $q = app(SheinQuotationCalculator::class)->calculate($this->cart([
            ['name' => 'Precision case', 'qty' => 1000, 'availability' => 'available',
                'outputLine' => ['amount' => '1499.49', 'currency' => 'USD']],
        ]), Setting::findOrFail(1));
        $display = app(SheinQuotationCalculator::class)->presentation($q);
        $this->assertSame('1.50', $display['items'][0]['unit_lyd']);
        $this->assertSame('1499.49', $display['items'][0]['line_lyd']);
        $this->assertSame('1499.49', $display['total_usd']);
        $this->assertSame('1499', $display['total_lyd']);
    }

    public function test_legacy_pdf_uses_frozen_source_prices_with_the_new_rounding_policy(): void
    {
        $calculator = app(SheinQuotationCalculator::class);
        $q = $calculator->calculate($this->cart(), Setting::findOrFail(1));
        $q['rounding'] = 'unit_lyd_half_up_3';
        $q['items'][0]['unit_lyd'] = '80.236';
        $q['items'][0]['line_lyd'] = '160.472';
        $q['total']['amount'] = '160.472';
        unset($q['unrounded_total_lyd']);
        $saved = $q;
        Setting::findOrFail(1)->update(['usd_to_lyd_rate' => '10']);
        $effective = $calculator->applyPdfOverrides($q, null);
        $this->assertTrue(\Brick\Math\BigDecimal::of($effective['unrounded_total_lyd'])->isEqualTo('160.4728125'));
        $this->assertSame('160', $effective['total']['amount']);
        $this->assertSame($saved, $q);
        $this->assertSame('22.52', $calculator->presentation($effective)['total_usd']);
    }

    public function test_native_usd_basis_uses_provider_conversion_without_treating_lyd_as_usd(): void
    {
        $cart = $this->cart([['name' => 'Test item', 'qty' => 1, 'availability' => 'available',
            'outputLine' => ['amount' => '50', 'currency' => 'LYD']]],
            ['total' => ['amount' => '50', 'currency' => 'LYD'],
                'native' => ['sale' => ['amount' => '10', 'currency' => 'USD']]]);
        $q = app(SheinQuotationCalculator::class)->calculate($cart, Setting::findOrFail(1));
        $this->assertTrue(\Brick\Math\BigDecimal::of($q['items'][0]['unit_usd'])->isEqualTo('10'));
        $this->assertSame('80', $q['total']['amount']);
    }

    public function test_snapshot_and_pdf_stay_unchanged_after_settings_change(): void
    {
        $pdf = app(SheinQuotationPdf::class);
        $cart = $pdf->prepare($this->cart());
        $snapshot = $cart->quotation;
        $bytes = Storage::disk('local')->get($cart->pdf_path);
        $this->assertStringStartsWith('%PDF-', $bytes);
        Setting::findOrFail(1)->update(['usd_to_lyd_rate' => '10', 'shein_company_percentage' => '50',
            'shein_completed_message' => 'Changed message']);
        $again = $pdf->prepare($cart);
        $this->assertSame($snapshot, $again->quotation);
        $this->assertSame($bytes, Storage::disk('local')->get($again->pdf_path));
    }

    public function test_original_and_sale_prices_and_both_final_currencies_use_saved_pricing(): void
    {
        $cart = $this->cart([['name' => 'Discounted product', 'qty' => 2, 'availability' => 'available',
            'unitOriginal' => ['amount' => '15.00', 'currency' => 'USD'],
            'unitSale' => ['amount' => '10.01', 'currency' => 'USD'],
            'outputLine' => ['amount' => '20.02', 'currency' => 'USD']]]);
        $calculator = app(SheinQuotationCalculator::class);
        $snapshot = $calculator->calculate($cart, Setting::findOrFail(1));
        Setting::findOrFail(1)->update(['usd_to_lyd_rate' => '10', 'shein_company_percentage' => '50']);
        $display = $calculator->presentation($snapshot);
        $this->assertSame('16.88', $display['items'][0]['original_usd']);
        $this->assertSame('11.26', $display['items'][0]['sale_usd']);
        $this->assertSame('33.75', $display['original_usd_total']);
        $this->assertSame('22.52', $display['sale_usd_total']);
        $this->assertArrayNotHasKey('company_usd_total', $display);
        $this->assertSame('22.52', $display['total_usd']);
        $this->assertSame('160', $display['total_lyd']);
        $this->assertSame('80.24', $display['items'][0]['unit_lyd']);
        $this->assertSame('7.13', $display['exchange_rate']);
        $this->assertSame('160', $snapshot['total']['amount']);
    }

    public function test_native_original_price_is_converted_using_the_provider_line_ratio(): void
    {
        $cart = $this->cart([['name' => 'Native AED product', 'qty' => 2, 'availability' => 'available',
            'unitOriginal' => ['amount' => '59.00', 'currency' => 'AED'],
            'unitSale' => ['amount' => '45.00', 'currency' => 'AED'],
            'line' => ['amount' => '90.00', 'currency' => 'AED'],
            'outputLine' => ['amount' => '24.51', 'currency' => 'USD']]]);
        $calculator = app(SheinQuotationCalculator::class);
        $display = $calculator->presentation($calculator->calculate($cart, Setting::findOrFail(1)));
        $this->assertSame('18.08', $display['items'][0]['original_usd']);
        $this->assertSame('13.79', $display['items'][0]['sale_usd']);
        $this->assertSame('36.15', $display['original_usd_total']);
        $this->assertSame('27.57', $display['total_usd']);
    }

    /** @dataProvider integerRoundingCases */
    public function test_pdf_amounts_use_requested_precision_without_changing_saved_values(string $amount, string $rounded, string $decimal): void
    {
        $calculator = app(SheinQuotationCalculator::class);
        $quotation = $calculator->calculate($this->cart(), Setting::findOrFail(1));
        $quotation['company_percentage'] = '0';
        $quotation['base_usd_total'] = $amount;
        $quotation['exchange_rate'] = $amount;
        $quotation['total']['amount'] = $amount;
        $quotation['unrounded_total_lyd'] = $amount;
        $quotation['items'][0]['qty'] = 1;
        $quotation['items'][0]['unit_usd'] = $amount;
        $quotation['items'][0]['base_original_usd'] = $amount;
        $quotation['items'][0]['unit_lyd'] = $amount;
        $quotation['items'][0]['line_lyd'] = $amount;
        $quotation['calculation_note'] = Setting::CALCULATION_NOTE;
        $saved = $quotation;
        $display = $calculator->presentation($quotation);
        foreach (['original_usd', 'sale_usd', 'unit_lyd', 'line_lyd'] as $key) {
            $this->assertSame($decimal, $display['items'][0][$key]);
        }
        foreach (['original_usd_total', 'sale_usd_total', 'total_usd'] as $key) {
            $this->assertSame($decimal, $display[$key]);
        }
        $this->assertSame($rounded, $display['total_lyd']);
        $this->assertSame($decimal, $display['exchange_rate']);
        $this->assertSame($saved, $quotation);
        $this->assertStringContainsString('أقرب دينار صحيح', $display['customer_note']);
        $this->assertStringNotContainsString('منازل عشرية', $display['customer_note']);
    }

    public static function integerRoundingCases(): array
    {
        return [['1.49999999', '1', '1.50'], ['1.5', '2', '1.50'], ['0.49', '0', '0.49'],
            ['0.5', '1', '0.50'], ['12.00000000', '12', '12.00']];
    }

    public function test_missing_original_prices_are_not_invented_and_unavailable_items_are_excluded(): void
    {
        $calculator = app(SheinQuotationCalculator::class);
        $display = $calculator->presentation($calculator->calculate($this->cart(), Setting::findOrFail(1)));
        $this->assertNull($display['items'][0]['original_usd']);
        $this->assertNull($display['original_usd_total']);
        $this->assertNull($display['items'][1]['sale_usd']);
        $this->assertSame('22.52', $display['total_usd']);
    }

    public function test_discount_labels_follow_customer_prices_and_do_not_invent_missing_discounts(): void
    {
        $cart = $this->cart([
            ['name' => 'Discounted', 'qty' => 1, 'availability' => 'available',
                'unitOriginal' => ['amount' => '4.08', 'currency' => 'USD'],
                'outputLine' => ['amount' => '3.26', 'currency' => 'USD']],
            ['name' => 'Same price', 'qty' => 1, 'availability' => 'available',
                'unitOriginal' => ['amount' => '3.26', 'currency' => 'USD'],
                'outputLine' => ['amount' => '3.26', 'currency' => 'USD']],
            ['name' => 'Unknown original', 'qty' => 1, 'availability' => 'available',
                'outputLine' => ['amount' => '1', 'currency' => 'USD']],
            ['name' => 'Unavailable', 'qty' => 1, 'availability' => 'unavailable',
                'unitOriginal' => ['amount' => '20', 'currency' => 'AED'],
                'unitSale' => ['amount' => '15', 'currency' => 'AED']],
        ]);
        $calculator = app(SheinQuotationCalculator::class);
        $quotation = $calculator->calculate($cart, Setting::findOrFail(1));
        $display = $calculator->presentation($quotation);
        $this->assertSame(['20', null, null, '25'], array_column($display['items'], 'discount_percent'));
        $this->assertNull($display['items'][3]['original_usd']);
        $this->assertNull($display['items'][3]['sale_usd']);
        $this->assertNull($display['items'][3]['line_lyd']);
        $quotation['items'][0]['customer_unit_usd'] = '4.59';
        $this->assertNull($calculator->presentation($quotation)['items'][0]['discount_percent']);
        $quotation['items'][0]['customer_unit_usd'] = '2.295';
        $this->assertSame('50', $calculator->presentation($quotation)['items'][0]['discount_percent']);
        $quotation['items'][3]['provider']['unitSale']['currency'] = 'USD';
        $this->assertNull($calculator->presentation($quotation)['items'][3]['discount_percent']);
    }

    public function test_unavailable_section_preserves_item_indexes_and_shein_notes_are_escaped(): void
    {
        $cart = $this->cart([
            ['name' => 'Unavailable first', 'qty' => 1, 'availability' => 'unavailable'],
            ['name' => 'Available second', 'qty' => 1, 'availability' => 'available',
                'unitOriginal' => ['amount' => '10', 'currency' => 'USD'],
                'outputLine' => ['amount' => '7', 'currency' => 'USD'],
                'notes' => ['Lowest in all sellers', '50+ sold', '<script>unsafe</script>']],
        ]);
        $calculator = app(SheinQuotationCalculator::class);
        $quotation = $calculator->calculate($cart, Setting::findOrFail(1));
        $display = $calculator->presentation($quotation);
        $html = view('pdf.shein-quotation', compact('cart', 'quotation', 'display') + ['images' => []])->render();
        $this->assertLessThan(strpos($html, 'Unavailable first'), strpos($html, 'Available second'));
        $this->assertStringContainsString('غير متوفر في هذا الموقع', $html);
        $this->assertStringContainsString('-30%', $html);
        $this->assertStringContainsString('7.88 USD', $html);
        $this->assertStringContainsString('Lowest in all sellers', $html);
        $this->assertStringContainsString('50+ sold', $html);
        $this->assertStringContainsString('&lt;script&gt;unsafe&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>unsafe</script>', $html);
        $this->assertSame('7.88', $display['total_usd']);
        $this->assertNull($display['items'][0]['sale_usd']);
    }

    public function test_previous_layout_is_rebuilt_without_repricing_the_saved_quotation(): void
    {
        $cart = $this->cart();
        $snapshot = app(SheinQuotationCalculator::class)->calculate($cart, Setting::findOrFail(1));
        $oldPath = 'shein-quotations/cart-'.$cart->id.'-old-layout.pdf';
        Storage::disk('local')->put($oldPath, '%PDF-old-layout');
        $cart->update(['quotation' => $snapshot, 'pdf_path' => $oldPath]);
        Setting::findOrFail(1)->update(['usd_to_lyd_rate' => '10', 'shein_company_percentage' => '50']);
        $cart = app(SheinQuotationPdf::class)->prepare($cart);
        $this->assertSame($snapshot, $cart->quotation);
        $this->assertNotSame($oldPath, $cart->pdf_path);
        $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get($cart->pdf_path));
    }

    public function test_unavailable_thumbnails_are_faded_without_modifying_available_images(): void
    {
        $url = 'https://img.ltwebstatic.com/test-product.jpg';
        $image = imagecreatetruecolor(40, 40);
        imagefill($image, 0, 0, imagecolorallocate($image, 180, 20, 40));
        ob_start();
        imagejpeg($image);
        $bytes = ob_get_clean();
        imagedestroy($image);
        Http::fake([$url => Http::response($bytes, 200)]);
        app(SheinQuotationPdf::class)->prepare($this->cart([
            ['name' => 'Available', 'qty' => 1, 'availability' => 'available', 'imageUrl' => $url,
                'outputLine' => ['amount' => '7', 'currency' => 'USD']],
            ['name' => 'Unavailable', 'qty' => 1, 'availability' => 'unavailable', 'imageUrl' => $url],
        ]));
        Http::assertSentCount(1);
        $source = Storage::disk('local')->path('shein-quotations/images/'.hash('sha256', $url).'.jpg');
        $faded = Storage::disk('local')->path('shein-quotations/images/'.hash('sha256', $source).'-unavailable-v12.jpg');
        $originalImage = imagecreatefromjpeg($source);
        $fadedImage = imagecreatefromjpeg($faded);
        $originalColor = imagecolorsforindex($originalImage, imagecolorat($originalImage, 0, 0));
        $fadedColor = imagecolorsforindex($fadedImage, imagecolorat($fadedImage, 0, 0));
        $this->assertGreaterThan($originalColor['green'] + 100, $originalColor['red']);
        $this->assertEqualsWithDelta($fadedColor['red'], $fadedColor['green'], 1);
        $this->assertEqualsWithDelta($fadedColor['red'], $fadedColor['blue'], 1);
        $this->assertGreaterThan(180, $fadedColor['red']);
        imagedestroy($originalImage);
        imagedestroy($fadedImage);
    }

    public function test_customer_pdf_includes_percentage_in_prices_without_disclosing_it_in_legacy_notes(): void
    {
        $cart = $this->cart();
        $calculator = app(SheinQuotationCalculator::class);
        $quotation = $calculator->calculate($cart, Setting::findOrFail(1));
        $quotation['calculation_note'] = 'يحسب السعر بعد إضافة نسبة الشركة 12.5%. راجع الكميات قبل الشراء.'
            ."\nCompany percentage: 12.5%.\nMarkup is included.\nالأسعار قابلة للتغيير.";
        $display = $calculator->presentation($quotation);
        $html = view('pdf.shein-quotation', compact('cart', 'quotation', 'display') + ['images' => []])->render();
        $this->assertStringContainsString('22.52 USD', $html);
        $this->assertStringContainsString('160 LYD', $html);
        $this->assertStringContainsString('راجع الكميات قبل الشراء.', $html);
        $this->assertStringContainsString('الأسعار قابلة للتغيير.', $html);
        foreach (['نسبة الشركة', 'إضافة الشركة', '12.5%', 'Company percentage', 'Markup', '20.02 USD'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $html);
        }
    }

    public function test_missing_rate_or_usd_basis_blocks_pdf_instead_of_mispricing(): void
    {
        $cart = $this->cart();
        Setting::findOrFail(1)->update(['usd_to_lyd_rate' => null]);
        try {
            app(SheinQuotationPdf::class)->prepare($cart);
            $this->fail('Missing rate must block the quote');
        } catch (CartQuoteException $e) {
            $this->assertStringContainsString('exchange rate', $e->getMessage());
        }
        $this->assertNull($cart->fresh()->quotation);
        Setting::findOrFail(1)->update(['usd_to_lyd_rate' => '7']);
        $payload = $cart->payload;
        $payload['result']['items'][0]['outputLine']['currency'] = 'AED';
        $cart->update(['payload' => $payload]);
        $this->expectException(CartQuoteException::class);
        app(SheinQuotationPdf::class)->prepare($cart);
    }

    public function test_private_pdf_cannot_be_downloaded_through_a_public_route(): void
    {
        $cart = app(SheinQuotationPdf::class)->prepare($this->cart());
        $this->get('/hooks/cart-quotes/documents/'.str_repeat('x', 64))->assertRedirect(route('home'));
        $this->get('/storage/'.$cart->pdf_path)->assertRedirect(route('home'));
    }

    public function test_operator_pdf_download_requires_authentication(): void
    {
        $cart = $this->cart();
        $this->get(route('carts.quotation', $cart))->assertRedirect();
        $this->get(route('carts.quotation', ['cart' => $cart, 'preview' => 1]))->assertRedirect();
    }

    public function test_remote_or_unapproved_product_images_are_not_fetched(): void
    {
        $cart = $this->cart([['name' => '<script>unsafe</script>', 'qty' => 1, 'availability' => 'available',
            'outputLine' => ['amount' => '10', 'currency' => 'USD'], 'imageUrl' => 'http://127.0.0.1/private']]);
        app(SheinQuotationPdf::class)->prepare($cart);
        Http::assertNothingSent();
        $quotation = $cart->fresh()->quotation;
        $this->assertStringNotContainsString('<script>unsafe</script>', view('pdf.shein-quotation',
            ['cart' => $cart, 'quotation' => $quotation, 'images' => [],
                'display' => app(SheinQuotationCalculator::class)->presentation($quotation)])->render());
    }
}
