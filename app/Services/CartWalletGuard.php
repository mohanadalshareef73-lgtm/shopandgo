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
            throw new CartWalletPausedException(CartWalletPausedException::UNAVAILABLE);
        }
        if (! self::hasCredit($wallet)) {
            throw new CartWalletPausedException(CartWalletPausedException::EMPTY);
        }
    }

    public static function hasCredit(array $wallet): bool
    {
        return BigDecimal::of($wallet['available']['amount'])->isGreaterThan(0);
    }
}
