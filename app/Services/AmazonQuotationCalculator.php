<?php

namespace App\Services;

use App\Models\CartQuote;
use App\Models\Setting;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

class AmazonQuotationCalculator
{
    /** Freeze our pricing separately from the provider payload and SHEIN PDFs. */
    public function prepare(CartQuote $quote): CartQuote
    {
        return DB::transaction(function () use ($quote) {
            $record = CartQuote::lockForUpdate()->findOrFail($quote->id);
            if ($record->retailer !== 'amazon' || $record->status !== 'succeeded') {
                throw new CartQuoteException('Amazon quotation is not ready');
            }
            if (! $record->amazon_pricing) {
                $record->amazon_pricing = $this->calculate($record, Setting::findOrFail(1));
                $record->save();
            }

            return $record;
        });
    }

    public function calculate(CartQuote $quote, Setting $settings): array
    {
        // Use the page price before provider markup, never result.total.
        $base = $this->usdAmount(data_get($quote->payload, 'result.items.0.usd.unitSale'))
            ?? $this->usdAmount(data_get($quote->payload, 'result.usd.sale'));
        if ($base === null) {
            throw new CartQuoteException('Amazon quote is missing a decimal USD page price');
        }
        $rate = $settings->usd_to_lyd_rate;
        if (! is_string($rate) || ! preg_match('/^\d{1,18}(?:\.\d{1,18})?$/D', $rate)
            || BigDecimal::of($rate)->isLessThanOrEqualTo('0')) {
            throw new CartQuoteException('Set the USD to LYD exchange rate before Amazon quote delivery');
        }
        $quantity = $quote->requested_quantity;
        if ($quantity < 1) {
            throw new CartQuoteException('Amazon quotation quantity must be positive');
        }
        $rules = [];
        foreach (['fixed_fee_usd', 'flat_surcharge_usd', 'threshold_usd', 'percentage'] as $field) {
            $value = $settings->getAttribute('amazon_'.$field);
            if (! is_string($value) || ! preg_match('/^\d{1,8}(?:\.\d{1,4})?$/D', $value)
                || BigDecimal::of($value)->isLessThan('0')
                || ($field === 'threshold_usd' && BigDecimal::of($value)->isZero())) {
                throw new CartQuoteException('Invalid Amazon pricing settings');
            }
            $rules[$field] = (string) BigDecimal::of($value)->stripTrailingZeros();
        }
        $percentage = BigDecimal::of($rules['percentage']);
        $fraction = $percentage->dividedBy('100', max(0, $percentage->getScale()) + 2);
        $surcharge = $base->isLessThan($rules['threshold_usd'])
            ? BigDecimal::of($rules['flat_surcharge_usd']) : $base->multipliedBy($fraction);
        $customerUnitUsd = $base->plus($rules['fixed_fee_usd'])->plus($surcharge);
        $totalLyd = $customerUnitUsd->multipliedBy((string) $quantity)->multipliedBy($rate);

        return [
            'version' => 2,
            'prepared_at' => now()->toIso8601String(),
            'rule' => 'base_usd_plus_fixed_fee_plus_threshold_surcharge',
            'rules' => $rules,
            'base_unit_usd' => (string) $base,
            'fixed_unit_fee_usd' => $rules['fixed_fee_usd'],
            'unit_surcharge_usd' => (string) $surcharge,
            'customer_unit_usd' => (string) $customerUnitUsd,
            'quantity' => $quantity,
            'exchange_rate' => $rate,
            'unrounded_total_lyd' => (string) $totalLyd,
            // Same final LYD rounding policy as the existing app quotations.
            'total' => ['amount' => (string) $totalLyd->toScale(0, RoundingMode::HALF_UP), 'currency' => 'LYD'],
        ];
    }

    private function usdAmount($money): ?BigDecimal
    {
        if (! is_array($money) || ($money['currency'] ?? null) !== 'USD'
            || ! is_string($money['amount'] ?? null)
            || ! preg_match('/^\d{1,18}(?:\.\d{1,18})?$/D', $money['amount'])) {
            return null;
        }

        return BigDecimal::of($money['amount']);
    }
}
