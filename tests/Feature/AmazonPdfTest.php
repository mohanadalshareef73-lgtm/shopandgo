<?php

namespace Tests\Feature;

use App\Models\CartQuote;
use App\Models\Setting;
use App\Models\WhatsappMessages;
use App\Services\SheinQuotationPdf;
use App\Support\AmazonOfferDetails;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AmazonPdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Http::preventStrayRequests();
        Setting::findOrFail(1)->update(['usd_to_lyd_rate' => '7.125']);
    }

    private function quote(?string $image = null): CartQuote
    {
        $incoming = WhatsappMessages::create(['message_id' => 'wamid.amazon-pdf', 'from' => '218911234567',
            'phone' => '218921000000', 'name' => 'Test customer', 'type' => 'text', 'message' => 'Amazon link', 'timestamp' => now()]);
        return CartQuote::create(['whatsapp_messages_id' => $incoming->id, 'retailer' => 'amazon', 'status' => 'succeeded',
            'idempotency_key' => 'amazon-pdf-test', 'source_url' => 'https://amazon.ae/dp/B0DF4NM7CY', 'requested_quantity' => 3,
            'payload' => ['storefront' => 'AMZ-AE', 'result' => ['itemCount' => 1,
                'total' => ['amount' => '9999', 'currency' => 'LYD'], 'items' => [['name' => 'Test shoes', 'variant' => null,
                    'notes' => ['ASIN B0DF4NM7CY', 'Sold by Example'], 'imageUrl' => $image,
                    'usd' => ['unitSale' => ['amount' => '80', 'currency' => 'USD']]]]]]]);
    }

    public function test_private_amazon_pdf_uses_saved_customer_total_and_survives_settings_changes(): void
    {
        $pdf = app(SheinQuotationPdf::class);
        $quote = $pdf->prepare($this->quote());
        $path = $quote->pdf_path;
        $bytes = Storage::disk('local')->get($path);
        $this->assertStringStartsWith('amazon-quotations/', $path);
        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->assertNull($quote->quotation);
        $this->assertSame('2031', $quote->amazon_pricing['total']['amount']);
        $html = view('pdf.amazon-quotation', ['cart' => $quote,
            'quotation' => $quote->amazon_pricing + ['company_name' => 'SHOP AND GO'], 'images' => []])->render();
        $this->assertStringContainsString('2031 LYD', $html);
        $this->assertStringContainsString('amazon.ae', $html);
        $this->assertStringContainsString('Test shoes', $html);
        $this->assertStringNotContainsString('USD', $html);
        $this->assertStringNotContainsString('9999', $html);
        $this->assertStringNotContainsString('()', $html);
        Setting::findOrFail(1)->update(['usd_to_lyd_rate' => '10', 'amazon_percentage' => '40', 'amazon_fixed_fee_usd' => '15']);
        $prepared = $pdf->prepare($quote);
        $this->assertSame($path, $prepared->pdf_path);
        $this->assertSame($bytes, Storage::disk('local')->get($path));
        $quote->update(['operator_status' => 'confirmed']);
        $prepared = $pdf->prepare($quote);
        $this->assertNotSame($path, $prepared->pdf_path);
        $this->assertSame('2031', $prepared->amazon_pricing['total']['amount']);
        $this->assertNull($prepared->notified_at);
        Http::assertNothingSent();
    }

    public function test_amazon_image_is_cached_privately_and_embedded(): void
    {
        $url = 'https://m.media-amazon.com/images/I/test.jpg';
        $image = imagecreatetruecolor(20, 20);
        ob_start();
        imagejpeg($image);
        $bytes = ob_get_clean();
        imagedestroy($image);
        Http::fake([$url => Http::response($bytes, 200, ['Content-Type' => 'image/jpeg'])]);
        $quote = $this->quote($url);
        $pdf = app(SheinQuotationPdf::class);
        $quote = $pdf->prepare($quote);
        $imagePath = $pdf->productImage($quote, 0);
        $this->assertNotNull($imagePath);
        $this->assertStringContainsString('amazon-quotations/images/', $imagePath);
        $this->assertTrue(is_file($imagePath));
        Http::assertSentCount(1);
        $this->assertStringContainsString('/Subtype /Image', Storage::disk('local')->get($quote->pdf_path));
    }

    public function test_alternative_offer_displays_notice_seller_and_excluded_delivery_without_changing_price(): void
    {
        $quote = $this->quote();
        $payload = $quote->payload;
        $notice = 'Notice: not the same offer as your link. Amazon has no main seller for this product, so this is another seller\'s price';
        $payload['result']['items'][0]['notes'] = [$notice, 'ASIN B0CT5SYY3Z', 'Delivery AED10', 'Sold by Gulf Orchid'];
        $quote->update(['payload' => $payload]);
        $quote = app(SheinQuotationPdf::class)->prepare($quote);
        $this->assertSame('2031', $quote->amazon_pricing['total']['amount']);
        $html = view('pdf.amazon-quotation', ['cart' => $quote,
            'quotation' => $quote->amazon_pricing + ['company_name' => 'SHOP AND GO'], 'images' => []])->render();
        $this->assertStringContainsString(e($notice), $html);
        $this->assertStringContainsString('Sold by Gulf Orchid', $html);
        $this->assertStringContainsString('Delivery AED10', $html);
        $this->assertStringContainsString(AmazonOfferDetails::DELIVERY_EXCLUSION, $html);
        $this->assertStringContainsString('إجمالي المنتجات، دون رسوم توصيل البائع', $html);
        $this->assertLessThan(strpos($html, e($notice)), strpos($html, 'Test shoes'));
        $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get($quote->pdf_path));
        Http::assertNothingSent();
    }

    public function test_old_amazon_pdf_is_refreshed_with_new_layout_without_repricing_or_notifying(): void
    {
        $pdf = app(SheinQuotationPdf::class);
        $quote = $pdf->prepare($this->quote());
        $pricing = $quote->amazon_pricing;
        $oldPath = str_replace('-v2-', '-v1-', $quote->pdf_path);
        Storage::disk('local')->put($oldPath, '%PDF-old-layout');
        $quote->update(['pdf_path' => $oldPath, 'notified_at' => now()]);
        $notified = $quote->notified_at;
        Setting::findOrFail(1)->update(['usd_to_lyd_rate' => '10']);
        $quote = $pdf->prepare($quote);
        $this->assertStringContainsString('-v2-', $quote->pdf_path);
        $this->assertSame($pricing, $quote->amazon_pricing);
        $this->assertTrue($notified->equalTo($quote->notified_at));
        $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get($quote->pdf_path));
        Http::assertNothingSent();
    }

    /** @dataProvider untrustedImages */
    public function test_optional_images_cannot_fetch_untrusted_hosts(string $url): void
    {
        $pdf = app(SheinQuotationPdf::class);
        $quote = $this->quote($url);
        $this->assertNull($pdf->productImage($quote, 0));
        $this->assertNotNull($pdf->prepare($quote)->pdf_path);
        Http::assertNothingSent();
    }

    public static function untrustedImages(): array
    {
        return [['http://m.media-amazon.com/a.jpg'], ['https://m.media-amazon.com.attacker.test/a.jpg'],
            ['https://127.0.0.1/a.jpg'], ['https://m.media-amazon.com:8443/a.jpg'],
            ['https://user@m.media-amazon.com/a.jpg']];
    }
}
