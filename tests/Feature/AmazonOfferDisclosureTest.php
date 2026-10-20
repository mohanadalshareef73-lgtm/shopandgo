<?php

namespace Tests\Feature;

use App\Models\CartQuote;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhatsappMessages;
use App\Services\AmazonQuotationCalculator;
use App\Services\AmazonQuoteReply;
use App\Support\AmazonOfferDetails;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AmazonOfferDisclosureTest extends TestCase
{
    use RefreshDatabase;

    private const NOTICE = 'Notice: not the same offer as your link. Amazon has no main seller for this product, so this is another seller\'s price';

    private function quote(): CartQuote
    {
        $quote = new CartQuote(['retailer' => 'amazon', 'requested_quantity' => 1,
            'payload' => ['result' => ['items' => [['name' => 'Perfume', 'notes' => [self::NOTICE,
                'ASIN B0CT5SYY3Z', 'Delivery AED10', 'Sold by Gulf Orchid'],
                'usd' => ['unitSale' => ['amount' => '80', 'currency' => 'USD']]]]]]]);
        $settings = Setting::findOrFail(1);
        $settings->update(['usd_to_lyd_rate' => '7.125']);
        $quote->amazon_pricing = app(AmazonQuotationCalculator::class)->calculate($quote, $settings);

        return $quote;
    }

    public static function captions(): array
    {
        return [[null], ['{product_name}\nPrice: {amount} {currency}'],
            ['Your quote: {amount} {currency}'], ['{product_name}{offer_details}\nPrice: {amount} {currency}']];
    }

    /** @dataProvider captions */
    public function test_default_and_existing_custom_captions_disclose_the_offer_once(?string $template): void
    {
        if ($template !== null) {
            Setting::findOrFail(1)->update(['amazon_messages' => ['succeeded' => $template]]);
        }
        $quote = $this->quote();
        $caption = app(AmazonQuoteReply::class)->success($quote);
        $this->assertSame(1, substr_count($caption, self::NOTICE));
        $this->assertStringContainsString('Sold by Gulf Orchid', $caption);
        $this->assertStringContainsString('Delivery AED10', $caption);
        $this->assertStringContainsString(AmazonOfferDetails::DELIVERY_EXCLUSION, $caption);
        $this->assertStringContainsString('677 LYD', $caption);
        $this->assertStringNotContainsString('{offer_details}', $caption);
        $this->assertSame('677', $quote->amazon_pricing['total']['amount']);
    }

    public function test_only_the_first_note_is_the_notice_and_malformed_notes_are_ignored(): void
    {
        $this->assertNull(AmazonOfferDetails::fromItem(['notes' => ['ASIN B0CT5SYY3Z', self::NOTICE]])['notice']);
        foreach ([null, 'unexpected', [null, ['invalid'], 12, '', 'Sold by Gulf Orchid']] as $notes) {
            $details = AmazonOfferDetails::fromItem(['notes' => $notes]);
            $this->assertNull($details['notice']);
            $this->assertNull($details['delivery']);
            $this->assertSame([], AmazonOfferDetails::captionLines(['notes' => $notes]));
        }
        $this->assertSame([], AmazonOfferDetails::captionLines([]));
    }

    public function test_pdf_escapes_provider_notice_and_seller_text(): void
    {
        $quote = $this->quote();
        $quote->id = 1;
        $payload = $quote->payload;
        $payload['result']['items'][0]['notes'] = ['Notice: <script>alert(1)</script>', 'Sold by <b>Seller</b>'];
        $quote->payload = $payload;
        $html = view('pdf.amazon-quotation', ['cart' => $quote,
            'quotation' => $quote->amazon_pricing + ['company_name' => 'SHOP AND GO'], 'images' => []])->render();
        $this->assertStringContainsString('Notice: &lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringContainsString('Sold by &lt;b&gt;Seller&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function test_operator_can_see_the_same_offer_details_under_the_amazon_product(): void
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        DB::table('types')->updateOrInsert(['id' => 2], ['type' => 'موظف']);
        $operator = User::factory()->create(['type_id' => 2, 'status' => true]);
        $operator->givePermissionTo(Permission::firstOrCreate(['name' => 'cart-list', 'guard_name' => 'web'], ['ar_name' => 'cart-list']));
        $incoming = WhatsappMessages::create(['message_id' => 'wamid.offer', 'from' => '218911234567',
            'phone' => '218921000000', 'name' => 'Customer', 'type' => 'text', 'message' => 'link', 'timestamp' => now()]);
        $quote = $this->quote();
        $quote->forceFill(['whatsapp_messages_id' => $incoming->id, 'status' => 'succeeded', 'operator_status' => 'new',
            'source_url' => 'https://amazon.ae/dp/B0CT5SYY3Z', 'idempotency_key' => 'offer-details']);
        $quote->save();
        $this->actingAs($operator)->get(route('carts.show', $quote))->assertOk()
            ->assertSee(self::NOTICE)->assertSee('Sold by Gulf Orchid')->assertSee('Delivery AED10')
            ->assertSee(AmazonOfferDetails::DELIVERY_EXCLUSION);
        Http::assertNothingSent();
    }
}
