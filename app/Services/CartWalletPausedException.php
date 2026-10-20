<?php

namespace App\Services;

class CartWalletPausedException extends CartQuoteException
{
    public const EMPTY = 'Wallet has no available credit; waiting for recharge';
    public const UNAVAILABLE = 'Wallet balance could not be verified; cart replies paused';
    public const INSUFFICIENT = 'Quote provider reported insufficient credit; waiting for recharge';
}
