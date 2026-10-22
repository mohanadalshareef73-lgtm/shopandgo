<?php

namespace App\Services;

use App\Models\Setting;
use Brick\Math\BigDecimal;
use Illuminate\Http\Client\ConnectionException;

class CartWalletGuard
{
    public function __construct(private CartQuoteClient $client)
    {
    }

    public function enabled(): bool
    {
        return Setting::find(1)?->shein_pause_on_empty_wallet ?? true;
    }

    public function assertAvailable(): void
    {
        if (! $this->enabled()) {
            return;
        }
        try {
            $wallet = $this->client->wallet();
        } catch (CartQuoteException | ConnectionException $e) {
            UrgentAlert::send('cart-wallet-unavailable', '⛔ ردود أسعار السلات واقفة: ما قدرناش نوصلو لمحفظة التسعير (مشكلة تقنية في خدمة التسعير)', 30);
            throw new CartWalletPausedException(CartWalletPausedException::UNAVAILABLE);
        }
        UrgentAlert::checkCartWallet($wallet);
        if (! self::hasCredit($wallet)) {
            UrgentAlert::send('cart-wallet-empty', '⛔ رصيد السلات خلص! ردود الأسعار على الواتساب واقفة لين تشحن المحفظة', 30);
            throw new CartWalletPausedException(CartWalletPausedException::EMPTY);
        }
    }

    public static function hasCredit(array $wallet): bool
    {
        return BigDecimal::of($wallet['available']['amount'])->isGreaterThan(0);
    }
}
