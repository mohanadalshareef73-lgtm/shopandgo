<?php

namespace App\Services;

use RuntimeException;

/** Only internally generated, credential-free messages belong in this exception. */
class CartQuoteException extends RuntimeException
{
    public function __construct(string $message, public ?int $retryAfter = null)
    {
        parent::__construct($message);
    }
}
