<?php

namespace App\Services;

use App\Models\CartQuote;
use App\Models\Setting;
use App\Models\WhatsappMessages;
use App\Support\AmazonOfferDetails;
use Brick\Math\BigDecimal;

/** Customer copy and decimal arithmetic only; transport stays in CartQuoteService. */
class AmazonQuoteReply
{
    /** Language detection for the existing SHEIN failure replies only. */
    public function arabic(WhatsappMessages $incoming): bool
    {
        $text = preg_replace('~https?://\S+~iu', '', $incoming->message);
        if (trim($text) === '') {
            $previous = WhatsappMessages::where('from', $incoming->from)
                ->where('session_uuid', $incoming->session_uuid)->where('id', '<', $incoming->id)
                ->latest('id')->limit(15)->pluck('message');
            foreach ($previous as $message) {
                $text = preg_replace('~https?://\S+~iu', '', $message);
                if (trim($text) !== '') {
                    break;
                }
            }
        }

        return trim($text) === '' || preg_match('/\p{Arabic}/u', $text) === 1;
    }

    public function quantity(string $text): int
    {
        $text = strtr(preg_replace('~https?://\S+~iu', '', $text), array_combine(
            preg_split('//u', '٠١٢٣٤٥٦٧٨٩۰۱۲۳۴۵۶۷۸۹', -1, PREG_SPLIT_NO_EMPTY), str_split('01234567890123456789')));
        if (preg_match('/(?:quantity|qty|الكمية)\s*[:=]?\s*(\d{1,6})\b|\b(\d{1,6})\s*(?:pieces?|pcs|units?|قطع(?:ة)?|حبات?|حبة)/iu', $text, $match)) {
            return max(1, (int) ($match[1] ?: $match[2]));
        }

        return 1;
    }

    public function received(): string
    {
        return $this->message('received');
    }

    public function failure(?string $code, ?string $customerMessage = null, bool $unsupportedSite = false): string
    {
        $status = $this->failureStatus($code, $unsupportedSite);
        $settings = Setting::find(1) ?? new Setting;
        return $this->message($status, ['{provider_message}' => $customerMessage ?? ''], $settings);
    }

    public function failureStatus(?string $code, bool $unsupportedSite = false): string
    {
        return match ($code) {
            'INVALID_URL' => $unsupportedSite ? 'unsupported_site' : 'invalid_url',
            'PRODUCT_UNAVAILABLE' => 'product_unavailable',
            'REGION_NOT_ALLOWED' => 'region_not_allowed',
            'REGION_UNVERIFIED' => 'region_unverified',
            'INSUFFICIENT_FUNDS', 'ACCOUNT_PROBLEM', 'IDEMPOTENCY_KEY_REQUIRED', 'IDEMPOTENCY_CONFLICT' => 'staff_follow_up',
            default => 'pricing_failed',
        };
    }

    public function terminalFailure(string $status, ?string $code): string
    {
        return $this->message($this->terminalFailureStatus($status, $code));
    }

    public function terminalFailureStatus(string $status, ?string $code): string
    {
        return $status === 'expired' ? 'expired' : match ($code) {
            'REGION_NOT_ALLOWED' => 'region_not_allowed',
            'REGION_UNVERIFIED' => 'region_unverified',
            'SHARED_CART_UNAVAILABLE', 'PRODUCT_UNAVAILABLE' => 'items_unavailable',
            default => 'temporary_problem',
        };
    }

    private function message(string $status, array $variables = [], ?Setting $settings = null): string
    {
        $settings = $settings ?? Setting::find(1) ?? new Setting;

        return strtr($settings->amazonMessageTemplate($status), $variables + [
            '{site}' => CartQuote::amazonSite(), '{supported_sites}' => implode(', ', QuoteUrlExtractor::AMAZON_SUPPORTED_SITES),
            '{provider_message}' => '',
        ]);
    }

    public function success(CartQuote $quote): string
    {
        $result = $quote->payload['result'];
        $item = $result['items'][0];
        $name = $item['name'].(! empty($item['variant']) ? ' ('.$item['variant'].')' : '');
        $pricing = $quote->amazon_pricing;
        if (! is_array($pricing) || ! $this->money($pricing['total'] ?? null)) {
            throw new CartQuoteException('Amazon customer pricing is not ready');
        }
        $settings = Setting::find(1) ?? new Setting;
        $offerLines = AmazonOfferDetails::captionLines($item);
        $offerDetails = $offerLines === [] ? '' : "\n".implode("\n", $offerLines);
        $template = $settings->amazonMessageTemplate('succeeded');
        $variables = ['{product_name}' => $name, '{amount}' => $pricing['total']['amount'],
            '{currency}' => $pricing['total']['currency'], '{quantity}' => (string) $pricing['quantity']];
        $quantityLine = '';
        if ($pricing['quantity'] > 1) {
            $quantityLine = "\n".$this->message('quantity', $variables, $settings);
        }
        $discountLine = '';
        $sale = $result['native']['sale'] ?? null;
        $original = $result['native']['original'] ?? null;
        if ($this->money($sale) && $this->money($original) && $sale['currency'] === $original['currency']
            && BigDecimal::of($original['amount'])->isGreaterThan(BigDecimal::of($sale['amount']))) {
            $discountLine = "\n".$this->message('discount', $variables, $settings);
        }

        $appendDetails = '';
        // Existing custom captions must still disclose an alternative offer.
        if ($offerDetails !== '' && ! str_contains($template, '{offer_details}')) {
            if (str_contains($template, '{product_name}')) {
                $variables['{product_name}'] .= $offerDetails;
            } else {
                $appendDetails = $offerDetails;
            }
        }

        return $this->message('succeeded', $variables + [
            '{quantity_line}' => $quantityLine, '{discount_line}' => $discountLine, '{offer_details}' => $offerDetails,
        ], $settings).$appendDetails;
    }

    public function validResult(array $payload): bool
    {
        $item = data_get($payload, 'result.items.0');
        $items = data_get($payload, 'result.items');

        return data_get($payload, 'result.itemCount') === 1
            && is_array($items) && count($items) === 1
            && $this->money(data_get($payload, 'result.total'))
            && is_string($item['name'] ?? null) && trim($item['name']) !== ''
            && (! isset($item['variant']) || is_string($item['variant']))
            && ($item['qty'] ?? null) === 1 && ($item['qtySource'] ?? null) === 'assumed_one'
            && ($item['availability'] ?? null) === 'available' && ($item['priceSource'] ?? null) === 'product_page';
    }

    private function money($money): bool
    {
        return is_array($money) && is_string($money['amount'] ?? null)
            && preg_match('/^\d{1,18}(?:\.\d{1,18})?$/D', $money['amount']) === 1
            && is_string($money['currency'] ?? null) && preg_match('/^[A-Z]{3}$/D', $money['currency']) === 1;
    }
}
