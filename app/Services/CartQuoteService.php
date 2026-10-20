<?php

namespace App\Services;

use App\Models\CartQuote;
use App\Models\ReplayMessages;
use App\Models\WhatsappMessages;
use App\Models\Setting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CartQuoteService
{
    public const TERMINAL_STATUSES = ['succeeded', 'failed', 'cancelled', 'expired'];
    public const ACKNOWLEDGEMENT = 'استلمنا رابط سلة شي إن. سنجهز عرض السعر ونرسله لك قريباً.';
    public const STORE_DISABLED = 'Store is disabled; submission paused';

    public function __construct(private CartQuoteClient $client, private WhatsAppService $whatsApp,
        private SheinQuotationPdf $pdf, private CartWalletGuard $wallet, private SheinLinkClassifier $links,
        private QuoteUrlExtractor $quoteUrls, private AmazonQuoteReply $amazonReplies,
        private AmazonQuotationCalculator $amazonCalculator, private IherbQuoteReply $iherbReplies)
    {
    }

    public function whatsappTestMode(): bool
    {
        return config('services.cart_quotes.whatsapp_test_number', '') !== '';
    }

    public function allowsWhatsAppSender(string $sender): bool
    {
        // Groups must stay excluded even when the restricted trial is disabled.
        if (str_ends_with($sender, '@g.us')) {
            return false;
        }
        if (! $this->whatsappTestMode()) {
            return true;
        }
        $allowed = config('services.cart_quotes.whatsapp_test_number');
        return is_string($allowed)
            && preg_match('/^\+?[1-9]\d{6,14}$/D', $allowed) === 1
            && ((preg_match('/^\+?[1-9]\d{6,14}$/D', $sender) === 1
                && ltrim($allowed, '+') === ltrim($sender, '+'))
                || $this->isWhatsAppTestSenderId($sender));
    }

    private function isWhatsAppTestSenderId(string $sender): bool
    {
        $id = config('services.cart_quotes.whatsapp_test_sender_id', '');
        return is_string($id) && preg_match('/^[1-9]\d{0,31}$/D', $id) === 1
            && in_array($sender, [$id, $id.'@lid'], true);
    }

    public function whatsAppRecipient(string $sender): string
    {
        // This alias is explicitly configured; a LID is never treated as a phone.
        if ($this->whatsappTestMode() && $this->isWhatsAppTestSenderId($sender)
            && $this->allowsWhatsAppSender($sender)) {
            return ltrim(config('services.cart_quotes.whatsapp_test_number'), '+');
        }
        return $sender;
    }

    public function handleIncoming(WhatsappMessages $incoming, array $urls): void
    {
        if (! $this->allowsWhatsAppMessage($incoming)) {
            return;
        }
        // Save quote requests before any provider request. A retry uses the same key,
        // including when the provider accepted a POST but our connection timed out.
        $quotes = [];
        $hasProducts = false;
        $urls = array_values(array_unique($urls));
        $multiple = count($urls) > 1;
        $firstAmazon = true;
        $settings = Setting::find(1);
        foreach ($urls as $url) {
            $amazon = $this->quoteUrls->isAmazonUrl($url);
            $iherb = $this->quoteUrls->isIherbUrl($url);
            $retailer = $amazon ? 'amazon' : ($iherb ? 'iherb' : 'shein');
            if ($settings && ! $settings->isCartStoreEnabled($retailer)) {
                continue;
            }
            if (! $amazon && ! $iherb && $this->links->classify($url) !== 'cart') {
                $hasProducts = true;
                continue;
            }
            // Retain the original first-Amazon key so redelivered old messages
            // reuse their saved quote. Additional links need independent keys.
            $sourceKey = $amazon ? ($firstAmazon ? 'amazon' : 'amazon'."\n".$url) : $url;
            $key = 'whatsapp-'.hash('sha256', $incoming->message_id."\n".$sourceKey);
            if ($amazon) {
                $firstAmazon = false;
            }
            try {
                $quotes[] = CartQuote::firstOrCreate(['idempotency_key' => $key], [
                    'whatsapp_messages_id' => $incoming->id,
                    'source_url' => $url,
                    'retailer' => $retailer,
                    'provider_idempotency_key' => $multiple
                        ? 'whatsapp-'.hash('sha256', $incoming->message_id."\n".$url)
                        : ($amazon ? $incoming->message_id : null),
                    'requested_quantity' => $amazon ? $this->amazonReplies->quantity($incoming->message) : 1,
                    'next_attempt_at' => now(),
                ]);
            } catch (QueryException $e) {
                // Another webhook delivery may have inserted the same request.
                $quotes[] = CartQuote::where('idempotency_key', $key)->first() ?? throw $e;
            }
        }
        foreach ($quotes as $quote) {
            $this->process($quote->id);
        }
        if ($hasProducts) {
            $this->replyToProducts($incoming->id, count($quotes) > 0);
        }
    }

    public function process(int $id): void
    {
        try {
            $quote = CartQuote::findOrFail($id);
            if ($quote->notified_at) {
                return;
            }
            // Exclude groups and apply trial restrictions to retries and final replies too.
            if ($quote->origin !== 'manual' && ! $this->allowsWhatsAppMessage($quote->incomingMessage)) {
                return;
            }
            $amazon = $quote->retailer === 'amazon';
            if ($quote->quote_id && $quote->status === 'submitted'
                && ! $quote->next_attempt_at) {
                $quote = DB::transaction(function () use ($id) {
                    $record = CartQuote::lockForUpdate()->findOrFail($id);
                    if ($record->status === 'submitted' && ! $record->next_attempt_at && ! $record->notified_at) {
                        $deadline = data_get($record->payload, 'deadlineAt')
                            ?? ($record->submitted_at ?? $record->created_at)->copy()->addMinutes(15)->toIso8601String();
                        $record->update(['payload' => array_merge($record->payload ?? [], ['deadlineAt' => $deadline]),
                            'next_attempt_at' => \Illuminate\Support\Carbon::parse($deadline)]);
                    }
                    return $record;
                });
            }
            if (in_array($quote->status, self::TERMINAL_STATUSES, true)
                && (($quote->origin === 'manual' && (! $amazon || $quote->status !== 'succeeded'))
                    || (! $quote->quote_id && ! in_array($quote->retailer, ['amazon', 'iherb'], true) && $quote->status !== 'succeeded'))) {
                $quote->update(['next_attempt_at' => null]);
                return;
            }
            if ($quote->next_attempt_at && $quote->next_attempt_at->isFuture()) {
                return;
            }
            if (! $quote->quote_id) {
                if (Setting::find(1)?->isCartStoreEnabled($quote->retailer ?? 'shein') === false) {
                    $quote->update(['last_error' => self::STORE_DISABLED, 'next_attempt_at' => now()->addMinute()]);
                    return;
                }
                // Protect scheduled retries and manually created records too.
                if ($quote->retailer === 'shein' && $this->links->classify($quote->source_url) !== 'cart') {
                    if ($quote->origin !== 'manual') {
                        $this->replyToProducts($quote->whatsapp_messages_id, false);
                    }
                    $quote->update(['status' => 'failed', 'next_attempt_at' => null,
                        'last_error' => 'SHEIN link is not a confirmed shared cart']);
                    return;
                }
                $this->wallet->assertAvailable();
            }

            // The row lock serializes callbacks, scheduled retries and duplicate
            // WhatsApp deliveries. Only the configured quote server is contacted.
            DB::transaction(function () use ($id, $amazon) {
                $quote = CartQuote::lockForUpdate()->findOrFail($id);
                if ($quote->notified_at) {
                    return;
                }
                if (in_array($quote->status, ['pending', 'submitted'], true)
                    && (! $quote->next_attempt_at || $quote->next_attempt_at->lessThanOrEqualTo(now()))) {
                    $response = $quote->quote_id
                        ? $this->client->read($quote->quote_id)
                        : $this->client->submit($quote->source_url, $quote->provider_idempotency_key ?? $quote->idempotency_key);

                    if (! $response->successful()) {
                        if ($response->status() === 402 && $this->wallet->enabled()) {
                            throw new CartWalletPausedException(CartWalletPausedException::INSUFFICIENT);
                        }
                        if (in_array($quote->retailer, ['amazon', 'iherb'], true)) {
                            $this->handleRetailerApiError($quote, $response);
                            return;
                        }
                        // Only explicit cart validation failures are terminal.
                        if (! $quote->quote_id && in_array($response->status(), [400, 422], true)) {
                            $quote->status = 'failed';
                            $quote->last_error = 'Cart rejected (HTTP '.$response->status().')';
                        } else {
                            $retryAfter = $response->header('Retry-After');
                            throw new CartQuoteException('Quote API HTTP '.$response->status(),
                                ctype_digit((string) $retryAfter) ? min(3600, (int) $retryAfter) : null);
                        }
                    } else {
                        $payload = $this->client->payload($response);
                        $remoteId = $this->client->quoteId($payload);
                        if (! $quote->quote_id && ! $remoteId) {
                            throw new CartQuoteException('Quote API response is missing its quote ID');
                        }
                        if ($remoteId && $quote->quote_id && $remoteId !== $quote->quote_id) {
                            throw new CartQuoteException('Quote API returned a different quote ID');
                        }
                        $quote->quote_id = $quote->quote_id ?? $remoteId;
                        $quote->submitted_at = $quote->submitted_at ?? now();
                        $status = $payload['status'] ?? 'pending';
                        if ($status === 'succeeded' && ! ($amazon ? $this->amazonReplies->validResult($payload) : $this->validResult($payload))) {
                            throw new CartQuoteException('Quote API returned an incomplete result');
                        }
                        if (! in_array($status, array_merge(self::TERMINAL_STATUSES, ['pending', 'queued', 'processing', 'running', 'accepted']), true)) {
                            throw new CartQuoteException('Quote API returned an unsupported status');
                        }
                        // Some GET responses omit the submission deadline.
                        if (! isset($payload['deadlineAt']) && isset($quote->payload['deadlineAt'])) {
                            $payload['deadlineAt'] = $quote->payload['deadlineAt'];
                        }
                        $quote->payload = $payload;
                        $quote->status = in_array($status, self::TERMINAL_STATUSES, true) ? $status : 'submitted';
                        $quote->last_error = null;
                    }
                    $quote->attempts = 0;
                    if ($quote->status === 'submitted') {
                        $deadline = data_get($quote->payload, 'deadlineAt');
                        if (! is_string($deadline)) {
                            $deadline = ($quote->submitted_at ?? $quote->created_at)->copy()->addMinutes(15)->toIso8601String();
                            $quote->payload = array_merge($quote->payload, ['deadlineAt' => $deadline]);
                        }
                        $checkAt = \Illuminate\Support\Carbon::parse($deadline);
                        if ($checkAt->lessThanOrEqualTo(now())) {
                            $quote->deadline_checked_at = now();
                        }
                        // A provider still running at its deadline is checked again.
                        $quote->next_attempt_at = $checkAt->isFuture() ? $checkAt : now()->addMinute();
                    } else {
                        $quote->next_attempt_at = now()->addMinute();
                    }
                    $quote->save();
                }
            });
            $completed = CartQuote::findOrFail($id);
            // Reply only after the provider accepts a link. Invalid links must
            // not produce an acknowledgement or prevent processing other URLs.
            if ($completed->origin !== 'manual' && $completed->quote_id
                && in_array($completed->status, ['submitted', 'succeeded'], true)) {
                $this->acknowledge($completed->whatsapp_messages_id, $completed->retailer, (int) $completed->id);
            }
            // Commit the file and its pricing snapshot before attempting delivery.
            if ($completed->status === 'succeeded' && $completed->origin !== 'manual' && $completed->retailer !== 'amazon') {
                $this->pdf->prepare($completed);
            }
            if ($completed->status === 'succeeded' && $completed->retailer === 'amazon') {
                $this->amazonCalculator->prepare($completed);
                $this->pdf->prepare($completed);
            }
            // Commit the provider ID/result before trying WhatsApp delivery.
            DB::transaction(function () use ($id) {
                $quote = CartQuote::lockForUpdate()->findOrFail($id);
                if (! $quote->notified_at && in_array($quote->status, self::TERMINAL_STATUSES, true)) {
                    if ($quote->retailer === 'amazon' && $quote->origin !== 'manual') {
                        $this->deliverAmazon($quote);
                    } elseif ($quote->retailer === 'iherb' && $quote->origin !== 'manual' && $quote->status !== 'succeeded') {
                        $this->deliverFailure($quote);
                    } elseif ($quote->origin === 'manual' || (! $quote->quote_id && $quote->status !== 'succeeded')) {
                        $quote->update(['next_attempt_at' => null]);
                    } elseif ($quote->status !== 'succeeded') {
                        $this->deliverFailure($quote);
                    } else {
                        $this->deliver($quote);
                    }
                }
            });
        } catch (CartWalletPausedException $e) {
            DB::transaction(function () use ($id, $e) {
                $quote = CartQuote::lockForUpdate()->find($id);
                if ($quote && ! $quote->notified_at) {
                    $quote->update(['last_error' => $e->getMessage(), 'next_attempt_at' => now()->addMinute()]);
                }
            });
        } catch (\Throwable $e) {
            // Persist only our own credential-free errors, never HTTP exception text.
            DB::transaction(function () use ($id, $e) {
                $quote = CartQuote::lockForUpdate()->find($id);
                if (! $quote || $quote->notified_at) {
                    return;
                }
                $attempts = $quote->attempts + 1;
                $delay = min(300, 30 * (2 ** min($attempts - 1, 4)));
                $delay = max($delay, $e instanceof CartQuoteException ? ($e->retryAfter ?? 0) : 0);
                $quote->update([
                    'attempts' => $attempts,
                    'last_error' => $e instanceof CartQuoteException ? $e->getMessage() : 'Processing or delivery failed; retry scheduled',
                    'next_attempt_at' => now()->addSeconds($delay),
                ]);
            });
            Log::warning('Cart quote retry scheduled', ['cart_quote_id' => $id, 'exception' => get_class($e)]);
        }
    }

    public function processNotifications(): void
    {
        DB::table('cart_quote_webhooks')->whereNull('processed_at')->chunkById(50, function ($receipts) {
            foreach ($receipts as $receipt) {
                DB::transaction(function () use ($receipt) {
                    $event = DB::table('cart_quote_webhooks')->where('id', $receipt->id)->lockForUpdate()->first();
                    if (! $event || $event->processed_at) {
                        return;
                    }
                    $quote = CartQuote::lockForUpdate()->find($event->cart_quote_id);
                    if ($quote && ! $quote->notified_at && ! in_array($quote->status, self::TERMINAL_STATUSES, true)) {
                        $payload = $event->payload ? json_decode($event->payload, true) : null;
                        $type = $payload['type'] ?? null;
                        if (in_array($type, ['quote.failed', 'quote.expired', 'quote.cancelled'], true)) {
                            // Failure events already contain everything needed; no GET.
                            $quote->payload = array_merge($quote->payload ?? [], $payload['data']);
                            $quote->status = substr($type, strlen('quote.'));
                        }
                        // Success still requires the full authoritative result/items.
                        $quote->next_attempt_at = now();
                        $quote->save();
                    }
                    DB::table('cart_quote_webhooks')->where('id', $event->id)->update(['processed_at' => now()]);
                });
            }
        });
    }

    private function handleRetailerApiError(CartQuote $quote, \Illuminate\Http\Client\Response $response): void
    {
        $error = $response->json('error');
        $error = is_array($error) ? $error : [];
        $requestId = $error['request_id'] ?? null;
        Log::warning('Cart quote API rejected request', ['cart_quote_id' => $quote->id,
            'status' => $response->status(), 'request_id' => is_string($requestId) ? $requestId : null]);
        if ($response->status() === 503 && ! $quote->quote_id && $quote->capacity_retries < 3) {
            $retryAfter = $response->header('Retry-After');
            $delay = ctype_digit((string) $retryAfter) ? (int) $retryAfter : 60;
            $quote->update(['capacity_retries' => $quote->capacity_retries + 1,
                'last_error' => 'Capacity unavailable; retry scheduled', 'next_attempt_at' => now()->addSeconds($delay)]);
            return;
        }
        // Failed reads can be retried without creating another provider quote.
        if ($quote->quote_id && ! in_array($response->status(), [401, 403], true)) {
            throw new CartQuoteException('Quote API read failed (HTTP '.$response->status().')');
        }
        $code = match ($response->status()) {
            401, 403 => 'ACCOUNT_PROBLEM',
            402 => 'INSUFFICIENT_FUNDS',
            409 => 'IDEMPOTENCY_CONFLICT',
            503 => 'CAPACITY_UNAVAILABLE',
            400, 422 => in_array($error['code'] ?? null, ['INVALID_URL', 'PRODUCT_UNAVAILABLE', 'SHARED_CART_UNAVAILABLE', 'IDEMPOTENCY_KEY_REQUIRED', 'REGION_NOT_ALLOWED', 'REGION_UNVERIFIED'], true)
                ? $error['code'] : ($response->status() === 400 ? 'INVALID_URL' : 'ENGINE_FAILED'),
            default => 'ENGINE_FAILED',
        };
        $payload = ['errorCode' => $code];
        if (in_array($code, ['INVALID_URL', 'REGION_NOT_ALLOWED'], true) && is_string($error['message'] ?? null)
            && trim($error['message']) !== '') {
            $payload['customerMessage'] = $error['message'];
        }
        $quote->fill(['status' => 'failed', 'payload' => $payload, 'last_error' => $code,
            'next_attempt_at' => now()])->save();
        if (in_array($code, ['ACCOUNT_PROBLEM', 'INSUFFICIENT_FUNDS', 'IDEMPOTENCY_KEY_REQUIRED', 'IDEMPOTENCY_CONFLICT'], true)) {
            $notificationId = DB::table('notifications')->insertGetId([
                'typeOf' => 'some', 'title' => $quote->retailer_name.' quotation needs staff attention',
                'disc' => 'Quote #'.$quote->id.': '.$code.($code === 'INSUFFICIENT_FUNDS' ? '. Top up the Cart Quotes wallet.' : '. Check the Cart Quotes account/integration.'),
                'sender' => 'system', 'created_at' => now(), 'updated_at' => now(),
            ]);
            // Administrator/employee account types; customers never receive these alerts.
            foreach (\App\Models\User::whereIn('type_id', [1, 2])->pluck('id') as $userId) {
                DB::table('notif_orgs')->insert(['notification_id' => $notificationId, 'user_id' => $userId,
                    'created_at' => now(), 'updated_at' => now()]);
            }
            Log::error('Cart quote requires staff attention', ['cart_quote_id' => $quote->id, 'code' => $code,
                'request_id' => is_string($requestId) ? $requestId : null]);
        }
    }

    private function deliverAmazon(CartQuote $quote): void
    {
        if ($quote->status !== 'succeeded' && $quote->quote_id) {
            $this->deliverFailure($quote);
            return;
        }
        $incoming = WhatsappMessages::lockForUpdate()->findOrFail($quote->whatsapp_messages_id);
        $this->wallet->assertAvailable();
        if ($quote->status === 'succeeded') {
            if (! $this->amazonReplies->validResult($quote->payload ?? [])) {
                throw new CartQuoteException('Cannot deliver an incomplete Amazon quote');
            }
            $message = $this->amazonReplies->success($quote);
        } else {
            $message = $this->amazonReplies->failure($quote->payload['errorCode'] ?? null,
                $quote->payload['customerMessage'] ?? null, $this->quoteUrls->isUnsupportedAmazonSite($quote->source_url));
        }
        if ($quote->status === 'succeeded') {
            $this->sendQuotationDocument($quote, $incoming, $message);
        } elseif (! $this->whatsApp->sendMessage($this->whatsAppRecipient($incoming->from), $message, $incoming->session_uuid, [],
            Setting::find(1)?->replyImagePath('amazon_'.$this->amazonReplies->failureStatus($quote->payload['errorCode'] ?? null,
                $this->quoteUrls->isUnsupportedAmazonSite($quote->source_url))))) {
            throw new CartQuoteException('WhatsApp Amazon quote delivery failed');
        }
        $reply = ReplayMessages::firstOrNew(['whatsapp_messages_id' => $incoming->id]);
        $reply->message = ($reply->message ? $reply->message."\n\n" : '').$message;
        $reply->save();
        $incoming->update(['is_reply' => true]);
        $quote->update(['notified_at' => now(), 'next_attempt_at' => null,
            'whatsapp_queued_at' => $quote->status === 'succeeded' ? now() : null, 'last_error' => null]);
    }

    private function deliverFailure(CartQuote $quote): void
    {
        if ($quote->status === 'cancelled') {
            $quote->update(['notified_at' => now(), 'next_attempt_at' => null, 'last_error' => null]);
            return;
        }
        $incoming = WhatsappMessages::lockForUpdate()->findOrFail($quote->whatsapp_messages_id);
        $this->wallet->assertAvailable();
        $ar = $quote->retailer === 'shein' ? $this->amazonReplies->arabic($incoming) : false;
        $imageKey = null;
        if ($quote->retailer === 'amazon') {
            $message = $this->amazonReplies->terminalFailure($quote->status, $quote->payload['errorCode'] ?? null);
            $imageKey = 'amazon_'.$this->amazonReplies->terminalFailureStatus($quote->status, $quote->payload['errorCode'] ?? null);
        } elseif ($quote->retailer === 'iherb') {
            $message = $quote->quote_id
                ? $this->iherbReplies->terminalFailure($quote->status, $quote->payload['errorCode'] ?? null, $quote->payload['customerMessage'] ?? null)
                : $this->iherbReplies->failure($quote->payload['errorCode'] ?? null, $quote->payload['customerMessage'] ?? null);
            $imageKey = 'iherb_'.($quote->quote_id
                ? $this->iherbReplies->terminalFailureStatus($quote->status, $quote->payload['errorCode'] ?? null)
                : $this->iherbReplies->failureStatus($quote->payload['errorCode'] ?? null));
        } elseif ($quote->status === 'expired') {
            $message = $ar ? 'انتهت مهلة تسعير الرابط. أرسل الرابط مرة أخرى.'
                : 'The quote timed out. Please send the link again.';
        } elseif (in_array($quote->payload['errorCode'] ?? null, ['SHARED_CART_UNAVAILABLE', 'PRODUCT_UNAVAILABLE'], true)) {
            $message = $ar ? 'المنتجات غير متوفرة حالياً. أرسل رابطاً جديداً.'
                : 'The items are unavailable. Please send a new link.';
        } else {
            $message = $ar ? 'حدثت مشكلة مؤقتة في التسعير. أرسل الرابط مرة أخرى بعد دقائق.'
                : 'There is a temporary pricing problem. Please send the link again in a few minutes.';
        }
        if (! $this->whatsApp->sendMessage($this->whatsAppRecipient($incoming->from), $message, $incoming->session_uuid, [],
            $imageKey ? Setting::find(1)?->replyImagePath($imageKey) : null)) {
            throw new CartQuoteException('WhatsApp quote failure delivery failed');
        }
        $reply = ReplayMessages::firstOrNew(['whatsapp_messages_id' => $incoming->id]);
        $reply->message = ($reply->message ? $reply->message."\n\n" : '').$message;
        $reply->save();
        $incoming->update(['is_reply' => true]);
        $quote->update(['notified_at' => now(), 'next_attempt_at' => null, 'last_error' => null]);
    }

    private function allowsWhatsAppMessage(WhatsappMessages $incoming): bool
    {
        return ! WhatsAppMessageContext::isGroup(['from' => $incoming->from, 'id' => $incoming->message_id])
            && $this->allowsWhatsAppSender($incoming->from);
    }

    private function acknowledge(int $incomingId, string $retailer = 'shein', ?int $cartId = null): void
    {
        DB::transaction(function () use ($incomingId, $retailer, $cartId) {
            $incoming = WhatsappMessages::lockForUpdate()->findOrFail($incomingId);
            if ($incoming->is_reply) {
                return;
            }
            $this->wallet->assertAvailable();
            // أول رد لما يبعت رابط (يتعدل من الإعدادات)
            $message = trim((string) \App\Services\AppSettings::get('wa_ack_text', '')) ?: (\App\Services\AppSettings::DEFAULTS['wa_ack_text'] ?? '') ?: match ($retailer) {
                'amazon' => $this->amazonReplies->received(),
                'iherb' => $this->iherbReplies->received(),
                default => Setting::find(1)?->receivedMessage() ?? self::ACKNOWLEDGEMENT,
            };
            // أول رد: ندعوه يحط الرابط في حسابه ويحجز أسرع
            $promo = trim((string) \App\Services\AppSettings::get('wa_ack_promo', \App\Services\AppSettings::DEFAULTS['wa_ack_promo'] ?? ''));
            // لو السعر يتبعت كرابط، ما نحتاجوش الدعوة في أول رد (الرابط جاي في رسالة السعر)
            if (false && $promo !== '' && \App\Services\AppSettings::get('wa_footer_enabled', true) && ! \App\Services\AppSettings::get('wa_quote_link_mode', false)) {
                try {
                    $link = route('portal.carts');
                } catch (\Throwable $e) {
                    $link = rtrim((string) config('app.url'), '/') . '/login';
                }
                $message .= "\n\n" . str_replace('{رابط}', $link, $promo);
            }
            // ⚡ رابط يحسب السلة طول في منظومتنا (يشوف السعر ويحجز من غير حساب)
            if ($cartId) {
                $tplL = trim((string) \App\Services\AppSettings::get('wa_ack_link', '')) ?: (\App\Services\AppSettings::DEFAULTS['wa_ack_link'] ?? '');
                if (trim($tplL) !== '') {
                    try {
                        $message .= "\n\n" . str_replace('{رابط}', \App\Http\Controllers\Portal\CartsController::publicUrl($cartId), $tplL);
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }
            }
            $imageKey = $retailer === 'shein' ? 'shein_received_message' : $retailer.'_received';
            if (! $this->whatsApp->sendMessage($this->whatsAppRecipient($incoming->from), $message, $incoming->session_uuid, [],
                Setting::find(1)?->replyImagePath($imageKey))) {
                throw new CartQuoteException('WhatsApp acknowledgement delivery failed');
            }
            $reply = ReplayMessages::firstOrNew(['whatsapp_messages_id' => $incoming->id]);
            $reply->message = ($reply->message ? $reply->message."\n\n" : '').$message;
            $reply->save();
            $incoming->update(['is_reply' => true]);
        });
    }

    private function replyToProducts(int $incomingId, bool $hasCarts): void
    {
        DB::transaction(function () use ($incomingId, $hasCarts) {
            $incoming = WhatsappMessages::lockForUpdate()->findOrFail($incomingId);
            if ($incoming->shein_product_reply_sent_at || ! $this->allowsWhatsAppMessage($incoming)) {
                return;
            }
            $message = Setting::find(1)?->productMessage() ?? Setting::PRODUCT_MESSAGE;
            if (! $this->whatsApp->sendMessage($this->whatsAppRecipient($incoming->from), $message, $incoming->session_uuid, [],
                Setting::find(1)?->replyImagePath('shein_product_message'))) {
                throw new CartQuoteException('WhatsApp product-link reply delivery failed');
            }
            $reply = ReplayMessages::firstOrNew(['whatsapp_messages_id' => $incoming->id]);
            $reply->message = ($reply->message ? $reply->message."\n\n" : '').$message;
            $reply->save();
            $values = ['shein_product_reply_sent_at' => now()];
            if (! $hasCarts) {
                $values['is_reply'] = true;
            }
            $incoming->update($values);
        });
    }

    private function deliver(CartQuote $quote): void
    {
        $incoming = WhatsappMessages::lockForUpdate()->findOrFail($quote->whatsapp_messages_id);
        if ($quote->status !== 'succeeded' || ! $this->validResult($quote->payload ?? [])) {
            throw new CartQuoteException('Cannot deliver an incomplete quote');
        }
        if (! $quote->quotation || ! $quote->pdf_path) {
            throw new CartQuoteException('Quotation PDF is not ready');
        }
        $this->wallet->assertAvailable();
        $quotation = $this->pdf->quotationForPdf($quote);
        $message = $quote->retailer === 'iherb' ? $this->iherbReplies->success($quote, $quotation)
            : $quotation['completed_message']."\n"
                .'عدد القطع: '.$quotation['item_count'].'، الإجمالي: '.$quotation['total']['amount'].' LYD.';
        if (\App\Services\AppSettings::get('wa_quote_link_mode', false)) {
            // بدل السعر والملف: رابط يفتح السلة في المنظومة (يشوف السعر كزائر، ويسجّل لما يبي يأكد)
            $message = $this->sendQuoteLink($quote, $incoming);
        } else {
            $this->sendQuotationDocument($quote, $incoming, $message);
        }
        // The existing history schema allows one reply per incoming message.
        // Keep acknowledgement and quotations together in that history entry.
        $reply = ReplayMessages::firstOrNew(['whatsapp_messages_id' => $incoming->id]);
        $reply->message = ($reply->message ? $reply->message."\n\n" : '').$message;
        $reply->save();
        $quote->update(['notified_at' => now(), 'whatsapp_queued_at' => now(), 'next_attempt_at' => null, 'last_error' => null]);
    }

    private function sendQuoteLink(CartQuote $quote, WhatsappMessages $incoming): string
    {
        $recipient = WhatsAppMessageContext::recipient($this->whatsAppRecipient($incoming->from));
        $sessionUuid = $incoming->session_uuid ?? Setting::find(1)?->whatsappMainSessionUuid() ?? config('services.whatsapp.main_session_uuid');
        if ($recipient === null) {
            throw new CartQuoteException('تعذر إرسال الرابط: رقم العميل غير صالح.');
        }
        $tpl = (string) \App\Services\AppSettings::get('wa_quote_link_text', \App\Services\AppSettings::DEFAULTS['wa_quote_link_text'] ?? '');
        $message = str_replace('{رابط}', \App\Http\Controllers\Portal\CartsController::publicUrl($quote->id), $tpl);
        $quote->fill(['whatsapp_customer_phone' => $recipient, 'whatsapp_session_uuid' => $sessionUuid])->save();
        $body = ['phone' => [ltrim($recipient, '+')], 'message' => $message];
        if ($btns = \App\Services\SgButtons::for('quote_link', $quote->id)) {
            $body['buttons'] = $btns;
        }
        try {
            $ok = $this->whatsApp->send($body, $sessionUuid, 30, 1, 'quote-link-'.$quote->id)->successful();
        } catch (\Throwable $e) {
            report($e);
            $ok = false;
        }
        if (! $ok && ! empty($body['buttons'])) {
            // لو الأزرار ما مشتش، نبعتو الرسالة بدونها
            $ok = $this->whatsApp->sendMessage($recipient, $message, $sessionUuid, [], null, 'quote-link-nb-'.$quote->id);
        }
        if (! $ok) {
            throw new CartQuoteException('WhatsApp quote link delivery failed');
        }

        return $message;
    }

    private function sendQuotationDocument(CartQuote $quote, WhatsappMessages $incoming, string $message): void
    {
        if (! $quote->pdf_path) {
            throw new CartQuoteException('Quotation PDF is not ready');
        }
        $recipient = WhatsAppMessageContext::recipient($this->whatsAppRecipient($incoming->from));
        $sessionUuid = $incoming->session_uuid ?? Setting::find(1)?->whatsappMainSessionUuid()
            ?? config('services.whatsapp.main_session_uuid');
        if (! is_string($sessionUuid) || preg_match('/^[a-fA-F0-9]{32}$/D', $sessionUuid) !== 1) {
            throw new CartQuoteException('تعذر إرسال عرض السعر: جلسة واتساب للإرسال غير صالحة. راجع إعدادات واتساب.');
        }
        if ($recipient === null) {
            throw new CartQuoteException('تعذر إرسال عرض السعر: رقم العميل أو معرّف واتساب غير صالح.');
        }
        // This legacy-named column stores the actual phone or linked-device ID.
        $quote->fill(['whatsapp_cart_reference' => $quote->whatsappCartReference(),
            'whatsapp_customer_phone' => $recipient, 'whatsapp_session_uuid' => $sessionUuid])->save();
        $imageKey = $quote->retailer === 'shein' ? 'shein_completed_message' : $quote->retailer.'_succeeded';
        $imagePath = Setting::find(1)?->replyImagePath($imageKey);
        if ($imagePath !== null && ! $this->whatsApp->sendMessage($recipient, $message, $sessionUuid, [], $imagePath,
            'quotation-image-'.hash('sha256', $quote->id."\n".$imagePath."\n".$message))) {
            throw new CartQuoteException('WhatsApp quotation image delivery failed');
        }
        $sent = $this->whatsApp->sendDocument($recipient, \Illuminate\Support\Facades\Storage::disk('local')->path($quote->pdf_path),
            $quote->retailer.'-quotation-'.$quote->id.'.pdf', $message.\App\Http\Controllers\Portal\CartsController::linkLine((int) $quote->id, $message), $sessionUuid, $quote->whatsappReplyButtons());
        if (! $sent) {
            throw new CartQuoteException('WhatsApp quotation delivery failed');
        }
    }

    public function validResult(array $payload): bool
    {
        $count = data_get($payload, 'result.itemCount');
        $amount = data_get($payload, 'result.total.amount');
        $currency = data_get($payload, 'result.total.currency');
        return (is_int($count) && $count >= 0)
            && is_string($amount)
            && preg_match('/^\d{1,18}(\.\d{1,8})?$/D', $amount) === 1
            && is_string($currency) && preg_match('/^[A-Z]{3}$/D', $currency) === 1;
    }
}
