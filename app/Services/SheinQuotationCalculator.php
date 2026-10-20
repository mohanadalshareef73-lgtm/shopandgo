<?php

namespace App\Services;

use App\Models\CartQuote;
use App\Models\Setting;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class SheinQuotationCalculator
{
    /** Display prices from the saved quotation, never from today's settings. */
    public function presentation(array $quotation): array
    {
        $base = BigDecimal::of($quotation['base_usd_total']);
        $factor = BigDecimal::one()->plus(BigDecimal::of($quotation['company_percentage'])->dividedBy('100', 8));
        $total = isset($quotation['customer_usd_total']) ? BigDecimal::of($quotation['customer_usd_total']) : $base->multipliedBy($factor);
        $rows = [];
        $originalTotal = BigDecimal::zero();
        $allOriginalPricesKnown = true;
        foreach ($quotation['items'] as $item) {
            $original = null;
            $sale = $item['unit_usd'] === null ? null : BigDecimal::of($item['unit_usd']);
            if ($item['availability'] === 'available' && $sale !== null) {
                $original = $this->originalUnitUsd($item);
                if ($original === null) {
                    $allOriginalPricesKnown = false;
                } else {
                    $originalTotal = $originalTotal->plus($original->multipliedBy((string) $item['qty']));
                }
            }
            $customerOriginal = $original?->multipliedBy($factor);
            $customerSale = $sale === null ? null : (isset($item['customer_unit_usd'])
                ? BigDecimal::of($item['customer_unit_usd']) : $sale->multipliedBy($factor));
            $discountOriginal = $customerOriginal;
            $discountSale = $customerSale;
            if ($item['availability'] === 'unavailable') {
                // Preserve a native discount label without assigning a quotation price.
                $currency = $item['provider']['unitOriginal']['currency'] ?? null;
                $discountOriginal = is_string($currency) ? $this->amount($item['provider']['unitOriginal'] ?? null, $currency) : null;
                $discountSale = is_string($currency) ? $this->amount($item['provider']['unitSale'] ?? null, $currency) : null;
            }
            $discount = $discountOriginal !== null && $discountSale !== null
                && $discountOriginal->isGreaterThan($discountSale)
                ? (string) $discountOriginal->minus($discountSale)->multipliedBy('100')
                    ->dividedBy($discountOriginal, 0, RoundingMode::HALF_UP) : null;
            $rows[] = ['original_usd' => $this->displayAmount($customerOriginal?->__toString()),
                'sale_usd' => $this->displayAmount($customerSale?->__toString()),
                'discount_percent' => $discount === '0' ? null : $discount,
                'unit_lyd' => $this->displayAmount($item['unit_lyd']),
                'line_lyd' => $this->displayAmount($item['line_lyd'])];
        }
        return ['items' => $rows,
            'original_usd_total' => $allOriginalPricesKnown ? $this->displayAmount((string) $originalTotal->multipliedBy($factor)) : null,
            'sale_usd_total' => $this->displayAmount((string) $total),
            'total_usd' => $this->displayAmount((string) $total),
            'total_lyd' => $this->displayAmount($quotation['unrounded_total_lyd'] ?? $quotation['total']['amount'], 0),
            'exchange_rate' => $this->displayAmount($quotation['exchange_rate'], 2),
            'customer_note' => $this->customerNote($quotation['calculation_note'] ?? '')];
    }

    private function displayAmount(?string $amount, int $scale = 2): ?string
    {
        return $amount === null ? null : (string) BigDecimal::of($amount)->toScale($scale, RoundingMode::HALF_UP);
    }

    private function originalUnitUsd(array $item): ?BigDecimal
    {
        if (array_key_exists('base_original_usd', $item)) {
            return $item['base_original_usd'] === null ? null : BigDecimal::of($item['base_original_usd']);
        }
        $provider = $item['provider'];
        $original = $this->amount($provider['unitOriginal'] ?? null, 'USD');
        if ($original !== null || $item['unit_usd'] === null) {
            return $original;
        }
        $currency = $provider['unitOriginal']['currency'] ?? null;
        $nativeOriginal = $this->amount($provider['unitOriginal'] ?? null);
        $nativeLine = $currency ? $this->amount($provider['line'] ?? null, $currency) : null;
        $nativeUnit = $nativeLine?->dividedBy((string) $item['qty'], 16, RoundingMode::HALF_UP)
            ?? ($currency ? $this->amount($provider['unitSale'] ?? null, $currency) : null);
        return $nativeOriginal !== null && $nativeUnit !== null && ! $nativeUnit->isZero()
            ? $nativeOriginal->multipliedBy($item['unit_usd'])->dividedBy($nativeUnit, 16, RoundingMode::HALF_UP) : null;
    }

    public function editorValues(array $quotation, ?array $overrides): array
    {
        $rows = [];
        foreach ($quotation['items'] as $index => $item) {
            $rows[$index] = ['qty' => $item['qty'], 'sale_usd' => $item['unit_usd'],
                'original_usd' => $item['availability'] === 'available'
                    ? $this->originalUnitUsd($item)?->stripTrailingZeros()->__toString() : null,
                'customer_usd' => null];
            $rows[$index] = array_merge($rows[$index], $overrides['items'][$index] ?? []);
        }
        return ['exchange_rate' => $overrides['exchange_rate'] ?? $quotation['exchange_rate'],
            'company_percentage' => $overrides['company_percentage'] ?? $quotation['company_percentage'], 'items' => $rows];
    }

    public function applyPdfOverrides(array $quotation, ?array $overrides): array
    {
        if (! $overrides && ($quotation['rounding'] ?? null) === 'total_lyd_half_up_0') {
            return $quotation;
        }
        // Legacy PDFs use their frozen provider prices/rate, never today's settings.
        $values = $overrides ?? $this->editorValues($quotation, null);
        $rate = BigDecimal::of($values['exchange_rate']);
        $percentage = BigDecimal::of($values['company_percentage']);
        $factor = BigDecimal::one()->plus($percentage->dividedBy('100', 8));
        $baseUsd = $baseLyd = $customerUsd = $customerLyd = BigDecimal::zero();
        $quantity = $availableQuantity = 0;
        foreach ($quotation['items'] as $index => &$item) {
            $edit = $values['items'][$index];
            $item['qty'] = (int) $edit['qty'];
            if ($overrides) {
                $item['qty_source'] = 'operator';
            }
            $quantity += $item['qty'];
            if ($item['availability'] !== 'available') {
                continue;
            }
            $sale = BigDecimal::of($edit['sale_usd']);
            $saleLine = $overrides ? $sale->multipliedBy($item['qty'])
                : $this->savedUsdLine($item, $quotation) ?? $sale->multipliedBy($item['qty']);
            if (! $overrides) {
                $sale = $saleLine->dividedBy((string) $item['qty'], 16, RoundingMode::HALF_UP);
            }
            $customer = isset($edit['customer_usd']) ? BigDecimal::of($edit['customer_usd']) : $sale->multipliedBy($factor);
            $customerLine = isset($edit['customer_usd']) ? $customer->multipliedBy($item['qty']) : $saleLine->multipliedBy($factor);
            $unitBase = $sale->multipliedBy($rate);
            $unitCustomer = $customer->multipliedBy($rate);
            if ($overrides) {
                $item['base_original_usd'] = $edit['original_usd'] ?? null;
            }
            $item['unit_usd'] = (string) $sale;
            $item['customer_unit_usd'] = (string) $customer;
            $item['unit_base_lyd'] = (string) $unitBase;
            $item['unit_company_lyd'] = (string) $unitCustomer->minus($unitBase);
            $item['unit_lyd'] = (string) $unitCustomer;
            $item['line_lyd'] = (string) $customerLine->multipliedBy($rate);
            $baseUsd = $baseUsd->plus($saleLine);
            $baseLyd = $baseLyd->plus($saleLine->multipliedBy($rate));
            $customerUsd = $customerUsd->plus($customerLine);
            $customerLyd = $customerLyd->plus($item['line_lyd']);
            $availableQuantity += $item['qty'];
        }
        unset($item);
        return array_merge($quotation, ['exchange_rate' => (string) $rate, 'company_percentage' => (string) $percentage,
            'item_count' => $quantity, 'available_quantity' => $availableQuantity,
            'rounding' => 'total_lyd_half_up_0',
            'base_usd_total' => (string) $baseUsd, 'customer_usd_total' => (string) $customerUsd,
            'base_lyd_total' => (string) $baseLyd,
            'company_lyd_total' => (string) $customerLyd->minus($baseLyd),
            'unrounded_total_lyd' => (string) $customerLyd,
            'total' => ['amount' => (string) $customerLyd->toScale(0, RoundingMode::HALF_UP), 'currency' => 'LYD']]);
    }

    private function savedUsdLine(array $item, array $quotation): ?BigDecimal
    {
        $provider = $item['provider'];
        $line = $this->amount($provider['outputLine'] ?? null, 'USD') ?? $this->amount($provider['line'] ?? null, 'USD');
        if ($line !== null) {
            return $line;
        }
        $nativeUsd = $this->amount($quotation['provider_native']['sale'] ?? null, 'USD');
        $providerTotal = $this->amount($quotation['provider_total'] ?? null);
        if ($nativeUsd && $providerTotal && ! $providerTotal->isZero()
            && ($provider['outputLine']['currency'] ?? null) === ($quotation['provider_total']['currency'] ?? null)) {
            return $this->amount($provider['outputLine'] ?? null)?->multipliedBy($nativeUsd)
                ->dividedBy($providerTotal, 16, RoundingMode::HALF_UP);
        }
        return null;
    }

    private function customerNote(string $note): string
    {
        // Old frozen quotations and editable notes can contain internal pricing explanations.
        $sentences = preg_split('/(?<=[.!?؟؛])\s+|\R/u', $note) ?: [];
        $sentences = array_filter($sentences, fn ($sentence) => ! preg_match(
            '/(?:نسب[ةه]|إضاف[ةه]|اضاف[ةه]|عمول[ةه]|هامش|ربح|أرباح|ارباح)|company\s+(?:percentage|fee)|mark[ -]?up|commission|margin|profit|percent|[%٪]/iu', $sentence));
        $sentences = array_filter($sentences, fn ($sentence) => ! preg_match('/تقرب\s+أسعار|تُقرّب\s+أسعار|round(?:ed|ing)?\s+(?:unit|item|prices)/iu', $sentence));
        $note = trim(implode("\n", $sentences));
        $roundingNote = 'تعرض الأسعار بمنزلتين عشريتين. يقرب الإجمالي النهائي بالدينار فقط إلى أقرب دينار صحيح.';
        return str_contains($note, $roundingNote) ? $note : trim($note."\n".$roundingNote);
    }

    private function amount($money, ?string $currency = null): ?BigDecimal
    {
        if (! is_array($money) || ($currency && ($money['currency'] ?? null) !== $currency)
            || ! is_string($money['amount'] ?? null)
            || preg_match('/^\d{1,18}(?:\.\d{1,8})?$/D', $money['amount']) !== 1) {
            return null;
        }
        return BigDecimal::of($money['amount']);
    }

    public function calculate(CartQuote $cart, Setting $settings): array
    {
        if ($cart->status !== 'succeeded') {
            throw new CartQuoteException('Quotation is not ready');
        }
        $rate = $this->amount(['amount' => $settings->usd_to_lyd_rate, 'currency' => 'LYD']);
        if (! $rate || $rate->isZero()) {
            throw new CartQuoteException('Set the USD to LYD exchange rate in SHEIN settings');
        }
        $percentage = BigDecimal::of($settings->shein_company_percentage ?? '0');
        $factor = BigDecimal::one()->plus($percentage->dividedBy('100', 8));
        $result = $cart->payload['result'] ?? [];
        $items = $result['items'] ?? null;
        if (! is_array($items) || ! array_is_list($items) || count($items) === 0 || count($items) > 1000) {
            throw new CartQuoteException('Quote API result is missing item details');
        }
        $providerTotal = $this->amount($result['total'] ?? null);
        $usdTotal = $this->amount($result['total'] ?? null, 'USD')
            ?? $this->amount($result['native']['sale'] ?? null, 'USD');
        $baseTotal = BigDecimal::zero();
        $customerTotal = BigDecimal::zero();
        $usdSum = BigDecimal::zero();
        $quotedItems = [];
        $availableQuantity = 0;
        foreach ($items as $index => $item) {
            if (! is_array($item) || ! is_int($item['qty'] ?? null) || $item['qty'] < 1 || $item['qty'] > 100000
                || ! in_array($item['availability'] ?? null, ['available', 'unavailable'], true)) {
                throw new CartQuoteException('Quote API returned invalid item details');
            }
            $row = ['line_no' => $index + 1, 'name' => is_string($item['name'] ?? null) ? $item['name'] : 'منتج',
                'variant' => is_string($item['variant'] ?? null) ? $item['variant'] : '', 'qty' => $item['qty'],
                'qty_source' => $item['qtySource'] ?? 'assumed_one', 'availability' => $item['availability'],
                'provider' => $item, 'unit_usd' => null, 'unit_base_lyd' => null,
                'unit_company_lyd' => null, 'unit_lyd' => null, 'line_lyd' => null];
            if ($item['availability'] === 'available') {
                $usdLine = $this->amount($item['outputLine'] ?? null, 'USD') ?? $this->amount($item['line'] ?? null, 'USD');
                // Some providers return an LYD output with a native USD total.
                // Recover each USD line using that provider's own conversion ratio.
                if (! $usdLine && $usdTotal && $providerTotal && ! $providerTotal->isZero()
                    && ($item['outputLine']['currency'] ?? null) === ($result['total']['currency'] ?? null)) {
                    $output = $this->amount($item['outputLine'] ?? null);
                    $usdLine = $output?->multipliedBy($usdTotal)->dividedBy($providerTotal, 16, RoundingMode::HALF_UP);
                }
                if (! $usdLine) {
                    throw new CartQuoteException('Quote API item is missing a USD price basis');
                }
                $unitUsd = $usdLine->dividedBy((string) $item['qty'], 16, RoundingMode::HALF_UP);
                $unitBase = $unitUsd->multipliedBy($rate);
                $unitCustomer = $unitUsd->multipliedBy($factor)->multipliedBy($rate);
                // Totals use the source line directly, not an already rounded unit price.
                $line = $usdLine->multipliedBy($factor)->multipliedBy($rate);
                $row = array_merge($row, ['unit_usd' => (string) $unitUsd,
                    'unit_base_lyd' => (string) $unitBase, 'unit_company_lyd' => (string) $unitCustomer->minus($unitBase),
                    'unit_lyd' => (string) $unitCustomer, 'line_lyd' => (string) $line]);
                $usdSum = $usdSum->plus($usdLine);
                $baseTotal = $baseTotal->plus($usdLine->multipliedBy($rate));
                $customerTotal = $customerTotal->plus($line);
                $availableQuantity += $item['qty'];
            }
            $quotedItems[] = $row;
        }
        return ['version' => 2, 'prepared_at' => now()->toIso8601String(),
            'company_name' => config('app.company_name', 'SHOP AND GO'),
            'customer_name' => $cart->incomingMessage?->name ?? '', 'customer_phone' => $cart->incomingMessage?->from ?? '',
            'exchange_rate' => (string) $rate, 'company_percentage' => (string) $percentage,
            'completed_message' => $cart->retailer === 'iherb'
                ? $settings->iherbMessageTemplate('succeeded') : $settings->completedMessage(),
            'calculation_note' => $settings->calculationNote(),
            'rounding' => 'total_lyd_half_up_0', 'items' => $quotedItems,
            'item_count' => $result['itemCount'] ?? count($items), 'available_quantity' => $availableQuantity,
            'base_usd_total' => (string) $usdSum,
            'base_lyd_total' => (string) $baseTotal,
            'company_lyd_total' => (string) $customerTotal->minus($baseTotal),
            'unrounded_total_lyd' => (string) $customerTotal,
            'total' => ['amount' => (string) $customerTotal->toScale(0, RoundingMode::HALF_UP), 'currency' => 'LYD'],
            'provider_total' => $result['total'] ?? null, 'provider_native' => $result['native'] ?? null];
    }
}
