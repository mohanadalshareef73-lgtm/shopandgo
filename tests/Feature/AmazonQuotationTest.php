<?php

namespace Tests\Feature;

use App\Models\CartQuote;
use App\Models\Setting;
use App\Models\WhatsappMessages;
use App\Services\AmazonQuotationCalculator;
use App\Services\CartQuoteException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AmazonQuotationTest extends TestCase
{
    use RefreshDatabase;

    private function quote(string $base, int $quantity = 1): CartQuote
    {
        $incoming = WhatsappMessages::create(['message_id' => 'amazon-pricing-test', 'from' => '218911234567',
            'phone' => '218921000000', 'name' => 'Customer', 'type' => 'text', 'message' => 'Amazon link', 'timestamp' => now()]);

        return CartQuote::create(['whatsapp_messages_id' => $incoming->id, 'source_url' => 'https://amazon.ae/dp/B0DF4NM7CY',
            'idempotency_key' => 'pricing-test', 'retailer' => 'amazon', 'status' => 'succeeded', 'requested_quantity' => $quantity,
            'payload' => ['result' => ['total' => ['amount' => '9999', 'currency' => 'LYD'],
                'usd' => ['sale' => ['amount' => $base, 'currency' => 'USD']],
                'items' => [['usd' => ['unitSale' => ['amount' => $base, 'currency' => 'USD']]]]]]]);
    }

    public static function prices(): array
    {
        return [
            ['0', '10', '15', '15'],
            ['80', '10', '95', '95'],
            ['99.99', '10', '114.99', '115'],
            ['99.999999999999999999', '10', '114.999999999999999999', '115'],
            ['100', '10.00', '115.00', '115'],
            ['100.00000001', '10.0000000010', '115.0000000110', '115'],
            ['150', '15.00', '170.00', '170'],
            ['200', '20.00', '225.00', '225'],
        ];
    }

    /** @dataProvider prices */
    public function test_base_usd_price_plus_five_plus_threshold_surcharge(string $base, string $surcharge, string $customerUsd, string $total): void
    {
        $settings = Setting::findOrFail(1);
        $settings->update(['usd_to_lyd_rate' => '1', 'shein_company_percentage' => '99']);
        $quote = $this->quote($base);
        $payload = $quote->payload;
        $pricing = app(AmazonQuotationCalculator::class)->prepare($quote)->amazon_pricing;
        $this->assertSame($surcharge, $pricing['unit_surcharge_usd']);
        $this->assertSame($customerUsd, $pricing['customer_unit_usd']);
        $this->assertSame(['amount' => $total, 'currency' => 'LYD'], $pricing['total']);
        $this->assertSame($payload, $quote->fresh()->payload);
        $this->assertNull($quote->fresh()->quotation);
    }

    public function test_quantity_multiplies_the_customer_unit_price_before_final_rounding(): void
    {
        Setting::findOrFail(1)->update(['usd_to_lyd_rate' => '7.125']);
        $pricing = app(AmazonQuotationCalculator::class)->prepare($this->quote('80.25', 3))->amazon_pricing;
        $this->assertSame('95.25', $pricing['customer_unit_usd']);
        $this->assertSame('2035.96875000', $pricing['unrounded_total_lyd']);
        $this->assertSame(['amount' => '2036', 'currency' => 'LYD'], $pricing['total']);
    }

    public function test_final_lyd_total_rounds_half_up(): void
    {
        Setting::findOrFail(1)->update(['usd_to_lyd_rate' => '0.1']);
        $pricing = app(AmazonQuotationCalculator::class)->prepare($this->quote('80'))->amazon_pricing;
        $this->assertSame(['amount' => '10', 'currency' => 'LYD'], $pricing['total']);
    }

    public function test_item_usd_page_price_takes_priority_over_aggregate_or_marked_up_provider_total(): void
    {
        Setting::findOrFail(1)->update(['usd_to_lyd_rate' => '1']);
        $quote = $this->quote('80');
        $payload = $quote->payload;
        $payload['result']['usd']['sale']['amount'] = '150';
        $quote->update(['payload' => $payload]);
        $this->assertSame('95', app(AmazonQuotationCalculator::class)->prepare($quote)->amazon_pricing['total']['amount']);
    }

    public function test_aggregate_usd_page_price_is_used_when_item_usd_is_absent(): void
    {
        Setting::findOrFail(1)->update(['usd_to_lyd_rate' => '1']);
        $quote = $this->quote('150');
        $payload = $quote->payload;
        unset($payload['result']['items'][0]['usd']);
        $quote->update(['payload' => $payload]);
        $this->assertSame('170', app(AmazonQuotationCalculator::class)->prepare($quote)->amazon_pricing['total']['amount']);
    }

    public function test_saved_price_is_stable_when_rate_or_requested_quantity_changes(): void
    {
        Setting::findOrFail(1)->update(['usd_to_lyd_rate' => '7.125']);
        $calculator = app(AmazonQuotationCalculator::class);
        $quote = $this->quote('80', 3);
        $snapshot = $calculator->prepare($quote)->amazon_pricing;
        Setting::findOrFail(1)->update(['usd_to_lyd_rate' => '10', 'amazon_fixed_fee_usd' => '99',
            'amazon_flat_surcharge_usd' => '20', 'amazon_threshold_usd' => '5', 'amazon_percentage' => '50']);
        $quote->update(['requested_quantity' => 5]);
        $this->assertSame($snapshot, $calculator->prepare($quote)->amazon_pricing);
    }

    /** @dataProvider configuredPrices */
    public function test_configurable_rule_uses_exact_decimal_threshold_and_percentage(string $base, string $expected): void
    {
        Setting::findOrFail(1)->update(['usd_to_lyd_rate' => '1', 'amazon_fixed_fee_usd' => '2.125',
            'amazon_flat_surcharge_usd' => '7.25', 'amazon_threshold_usd' => '80.125', 'amazon_percentage' => '12.5']);
        $pricing = app(AmazonQuotationCalculator::class)->prepare($this->quote($base))->amazon_pricing;
        $this->assertSame($expected, $pricing['customer_unit_usd']);
        $this->assertSame(['fixed_fee_usd' => '2.125', 'flat_surcharge_usd' => '7.25',
            'threshold_usd' => '80.125', 'percentage' => '12.5'], $pricing['rules']);
    }

    public static function configuredPrices(): array
    {
        return [['80.1249', '89.4999'], ['80.125', '92.265625'], ['200', '227.125']];
    }

    public function test_zero_additions_are_supported_and_new_quotes_use_new_rules(): void
    {
        $settings = Setting::findOrFail(1);
        $settings->update(['usd_to_lyd_rate' => '1', 'amazon_fixed_fee_usd' => '0',
            'amazon_flat_surcharge_usd' => '0', 'amazon_percentage' => '0']);
        $calculator = app(AmazonQuotationCalculator::class);
        $quote = $this->quote('150');
        $this->assertSame('150', $calculator->prepare($quote)->amazon_pricing['total']['amount']);
        $settings->update(['amazon_fixed_fee_usd' => '3', 'amazon_percentage' => '20']);
        $this->assertSame('183', $calculator->calculate($quote, $settings->fresh())['total']['amount']);
        $this->assertSame('150', $calculator->prepare($quote)->amazon_pricing['total']['amount']);
    }

    public function test_missing_exchange_rate_does_not_save_a_misleading_price(): void
    {
        Setting::findOrFail(1)->update(['usd_to_lyd_rate' => null]);
        $quote = $this->quote('80');
        try {
            app(AmazonQuotationCalculator::class)->prepare($quote);
            $this->fail('An exchange rate is required');
        } catch (CartQuoteException $e) {
            $this->assertNull($quote->fresh()->amazon_pricing);
        }
    }

    public function test_missing_usd_price_never_uses_provider_total_as_a_usd_basis(): void
    {
        Setting::findOrFail(1)->update(['usd_to_lyd_rate' => '1']);
        $quote = $this->quote('80');
        $payload = $quote->payload;
        unset($payload['result']['usd'], $payload['result']['items'][0]['usd']);
        $quote->update(['payload' => $payload]);
        $this->expectException(CartQuoteException::class);
        app(AmazonQuotationCalculator::class)->prepare($quote);
    }
}
