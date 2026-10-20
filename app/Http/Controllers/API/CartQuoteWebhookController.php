<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CartQuote;
use App\Services\CartQuoteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CartQuoteWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        $raw = $request->getContent();
        $timestamp = $request->header('Webhook-Timestamp', '');
        $signature = $request->header('Webhook-Signature', '');
        $webhookId = $request->header('Webhook-Id', '');
        $keyVersion = $request->header('Webhook-Key-Version', '');
        $secret = config('services.cart_quotes.webhook_secret');
        if (! is_string($secret) || $secret === ''
            || ! preg_match('/^[0-9]+$/D', $timestamp)
            || abs(now()->timestamp - (int) $timestamp) > 300
            || ! preg_match('/^[A-Za-z0-9_.:-]{1,191}$/D', $webhookId)
            || $keyVersion === ''
            || ! preg_match('/^v1=[a-f0-9]{64}$/D', $signature)) {
            return response()->json(['error' => 'Invalid webhook authentication'], 401);
        }

        $valid = hash_equals('v1='.hash_hmac('sha256', $timestamp.'.'.$raw, $secret), $signature);
        $previous = config('services.cart_quotes.webhook_secret_previous');
        if (! $valid && is_string($previous) && $previous !== '') {
            $valid = hash_equals('v1='.hash_hmac('sha256', $timestamp.'.'.$raw, $previous), $signature);
        }
        if (! $valid) {
            return response()->json(['error' => 'Invalid webhook authentication'], 401);
        }

        // Authenticate even replays, then acknowledge duplicates without changing
        // their original payload or resetting the background worker's backoff.
        if (DB::table('cart_quote_webhooks')->where('webhook_id', $webhookId)->exists()) {
            return response()->json(['status' => 'accepted']);
        }
        $event = json_decode($raw, true);
        if (! is_array($event) || ! is_string($event['type'] ?? null) || ! is_array($event['data'] ?? null)) {
            return response()->json(['error' => 'Invalid event'], 422);
        }
        $type = $event['type'];
        $isQuote = in_array($type, array_map(fn ($status) => 'quote.'.$status, CartQuoteService::TERMINAL_STATUSES), true);
        $quote = null;
        if ($isQuote) {
            $quoteId = $event['data']['quoteId'] ?? null;
            if (! is_string($quoteId) || $quoteId === '' || strlen($quoteId) > 255) {
                return response()->json(['error' => 'Invalid quote ID'], 422);
            }
            // No row lock: the worker may be holding the quote during an API call.
            $quote = CartQuote::where('quote_id', $quoteId)->first();
        }
        $inserted = DB::table('cart_quote_webhooks')->insertOrIgnore([
            'webhook_id' => $webhookId,
            'body_hash' => hash('sha256', $raw),
            // The legacy mapping is non-nullable and has no foreign key.
            // Zero marks test/ignored/unknown events, processed immediately.
            'cart_quote_id' => $quote?->id ?? 0,
            'payload' => json_encode($event, JSON_THROW_ON_ERROR),
            'processed_at' => $quote ? null : now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($inserted && $isQuote && ! $quote) {
            Log::warning('Cart Quotes webhook for unknown quote', ['webhook_id' => $webhookId, 'quote_id' => $quoteId]);
        } elseif ($inserted && $type === 'webhook.test') {
            Log::info('Cart Quotes webhook test received', ['webhook_id' => $webhookId]);
        }

        return response()->json(['status' => 'accepted']);
    }
}
