<?php

namespace App\Services;

use App\Models\CartQuote;
use App\Models\Setting;

class IherbQuoteReply
{
    public function received(): string
    {
        return $this->message('received');
    }

    public function failure(?string $code, ?string $providerMessage = null): string
    {
        return $this->message($this->failureStatus($code), ['{provider_message}' => $providerMessage ?? '']);
    }

    public function failureStatus(?string $code): string
    {
        return match ($code) {
            'INVALID_URL' => 'invalid_url',
            'PRODUCT_UNAVAILABLE' => 'product_unavailable',
            'SHARED_CART_UNAVAILABLE' => 'items_unavailable',
            'REGION_NOT_ALLOWED' => 'region_not_allowed',
            'REGION_UNVERIFIED' => 'region_unverified',
            'INSUFFICIENT_FUNDS', 'ACCOUNT_PROBLEM', 'IDEMPOTENCY_KEY_REQUIRED', 'IDEMPOTENCY_CONFLICT' => 'staff_follow_up',
            default => 'pricing_failed',
        };
    }

    public function terminalFailure(string $status, ?string $code, ?string $providerMessage = null): string
    {
        return $this->message($this->terminalFailureStatus($status, $code), ['{provider_message}' => $providerMessage ?? '']);
    }

    public function terminalFailureStatus(string $status, ?string $code): string
    {
        return $status === 'expired' ? 'expired' : match ($code) {
            'INVALID_URL' => 'invalid_url',
            'REGION_NOT_ALLOWED' => 'region_not_allowed',
            'REGION_UNVERIFIED' => 'region_unverified',
            'SHARED_CART_UNAVAILABLE', 'PRODUCT_UNAVAILABLE' => 'items_unavailable',
            default => 'temporary_problem',
        };
    }

    public function success(CartQuote $quote, array $quotation): string
    {
        $items = $quotation['items'];
        return $this->message('succeeded', [
            '{cart_ref}' => $quote->whatsappCartReference(),
            '{product_name}' => count($items) === 1 ? $items[0]['name'] : 'منتجات آي هيرب',
            '{item_count}' => (string) $quotation['item_count'],
            '{quantity}' => (string) $quotation['item_count'],
            '{amount}' => $quotation['total']['amount'],
            '{currency}' => $quotation['total']['currency'],
        ]);
    }

    private function message(string $status, array $variables = []): string
    {
        $settings = Setting::find(1) ?? new Setting;
        return strtr($settings->iherbMessageTemplate($status), $variables + ['{provider_message}' => '']);
    }
}
