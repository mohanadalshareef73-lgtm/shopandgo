<?php

namespace App\Http;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class CartQuoteWebhookRequest extends Request
{
    public static function matches(SymfonyRequest $request): bool
    {
        return in_array(trim($request->getPathInfo(), '/'), [
            'webhooks/cart-quotes', 'hooks/cart-quotes', 'hooks/cartquotes',
        ], true);
    }

    public static function createFromBase(SymfonyRequest $request)
    {
        if (! self::matches($request)) {
            return parent::createFromBase($request);
        }

        // Laravel normally decodes JSON during capture(), before middleware.
        // Keep this route's input raw until the controller authenticates it.
        $raw = $request->getContent();
        $newRequest = (new static)->duplicate(
            $request->query->all(), $request->request->all(), $request->attributes->all(),
            $request->cookies->all(), $request->files->all(), $request->server->all()
        );
        $newRequest->headers->replace($request->headers->all());
        $newRequest->content = $raw;

        return $newRequest;
    }
}
