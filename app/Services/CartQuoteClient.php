<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class CartQuoteClient
{
    private function request()
    {
        $token = config('services.cart_quotes.token');
        if (! is_string($token) || $token === '') {
            throw new CartQuoteException('Quote API token is not configured');
        }
        return Http::withToken($token)->acceptJson()->asJson()
            ->connectTimeout(5)->timeout(20);
    }

    public function submit(string $url, string $key): Response
    {
        return $this->request()->withHeaders(['Idempotency-Key' => $key])
            ->post(rtrim(config('services.cart_quotes.base_url'), '/').'/v1/quotes', ['sourceUrl' => $url]);
    }

    public function read(string $id): Response
    {
        return $this->request()->get(rtrim(config('services.cart_quotes.base_url'), '/').'/v1/quotes/'.rawurlencode($id));
    }

    public function wallet(): array
    {
        $response = $this->request()->connectTimeout(3)->timeout(8)
            ->get(rtrim(config('services.cart_quotes.base_url'), '/').'/v1/wallet');
        if (! $response->successful()) {
            throw new CartQuoteException('Wallet API is unavailable');
        }
        $payload = $this->payload($response);
        $wallet = [];
        $currency = null;
        foreach (['balance', 'held', 'available'] as $field) {
            $money = $payload[$field] ?? null;
            if (! is_array($money) || ! is_string($money['amount'] ?? null)
                || ! preg_match('/^\d{1,18}(?:\.\d{1,18})?$/D', $money['amount'])
                || ! is_string($money['currency'] ?? null)
                || ! preg_match('/^[A-Z]{3}$/D', $money['currency'])
                || ($currency !== null && $currency !== $money['currency'])) {
                throw new CartQuoteException('Wallet API returned invalid amounts');
            }
            $currency = $money['currency'];
            $wallet[$field] = ['amount' => $money['amount'], 'currency' => $currency];
        }
        return $wallet;
    }

    public function payload(Response $response): array
    {
        $body = $response->json();
        if (! is_array($body)) {
            throw new CartQuoteException('Quote API returned invalid JSON');
        }
        return isset($body['data']) && is_array($body['data']) ? $body['data'] : $body;
    }

    public function quoteId(array $payload): ?string
    {
        $id = $payload['id'] ?? null;
        return (is_string($id) || is_int($id)) && strlen((string) $id) > 0
            && strlen((string) $id) <= 255 ? (string) $id : null;
    }
}
