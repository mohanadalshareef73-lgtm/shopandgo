<?php

namespace App\Services;

use App\Models\CartQuote;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CartWhatsAppReplies
{
    private const TABLE = 'cart_whatsapp_button_events';

    public function __construct(private WhatsAppService $whatsApp) {}

    public function receive(array $message, string $sessionUuid, ?string $contactPhone = null): void
    {
        if (($message['type'] ?? null) !== 'interactive'
            || data_get($message, 'interactive.type') !== 'button_reply'
            || WhatsAppMessageContext::isGroup($message)) {
            return;
        }
        $eventId = $message['id'] ?? null;
        $from = $message['from'] ?? null;
        $recipient = is_string($from) ? WhatsAppMessageContext::recipient($from) : null;
        $buttonId = data_get($message, 'interactive.button_reply.id');
        if (! is_string($eventId) || $eventId === '' || strlen($eventId) > 255
            || $recipient === null
            || ! is_string($buttonId) || mb_strlen($buttonId) > 256
            || preg_match('/^[a-fA-F0-9]{32}$/D', $sessionUuid) !== 1) {
            Log::info('Cart WhatsApp button ignored', ['event_id' => is_string($eventId) ? substr($eventId, 0, 255) : null,
                'reason' => 'invalid_payload']);

            return;
        }
        [$action, $reference] = array_pad(explode(':', $buttonId, 2), 2, '');
        $context = ['event_id' => $eventId, 'action' => $action, 'cart_ref' => $reference,
            'customer' => $recipient, 'session' => $sessionUuid];
        if (! in_array($action, ['confirm', 'edit', 'cancel', 'cash', 'bank'], true) || $reference === '' || strlen($reference) > 255) {
            Log::info('Cart WhatsApp button ignored', $context + ['reason' => 'unknown_action_or_reference']);

            return;
        }
        $contextId = data_get($message, 'context.id');
        // A retry must not wait for the worker's event lock during an HTTP send.
        if (DB::table(self::TABLE)->where('event_id', $eventId)->exists()) {
            Log::info('Cart WhatsApp button duplicate', $context);

            return;
        }
        $inserted = DB::table(self::TABLE)->insertOrIgnore([
            'event_id' => $eventId, 'action' => $action, 'cart_reference' => $reference,
            'customer_phone' => $recipient, 'session_uuid' => $sessionUuid,
            'contact_phone' => WhatsAppContactNumbers::validPhone($contactPhone) ? $contactPhone : null,
            // A poll fallback has a different context ID. Ownership uses the saved addresses.
            'context_message_id' => is_string($contextId) ? substr($contextId, 0, 512) : null,
            'next_attempt_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        Log::info($inserted ? 'Cart WhatsApp button queued' : 'Cart WhatsApp button duplicate', $context);
    }

    public function processPending(): int
    {
        $processed = 0;
        DB::table(self::TABLE)->whereNull('processed_at')->where('next_attempt_at', '<=', now())
            ->orderBy('id')->chunkById(50, function ($events) use (&$processed) {
                foreach ($events as $event) {
                    try {
                        if ($event->contact_phone) {
                            app(WhatsAppContactNumbers::class)->remember($event->session_uuid, $event->customer_phone, $event->contact_phone);
                        } else {
                            app(WhatsAppContactNumbers::class)->retryAfterMessage($event->session_uuid, $event->customer_phone);
                        }
                        $this->process($event->id);
                        $processed++;
                    } catch (\Throwable $e) {
                        $this->retry($event->id, $e);
                    }
                }
            });

        return $processed;
    }

    private function process(int $id): void
    {
        // Commit the decision and its reply together before contacting Whatsy. A failed
        // send retries the saved reply, never applies the decision a second time.
        DB::transaction(function () use ($id) {
            $event = DB::table(self::TABLE)->where('id', $id)->lockForUpdate()->first();
            if (! $event || $event->processed_at || $event->state_applied_at
                || now()->lt($event->next_attempt_at)) {
                return;
            }
            $cart = CartQuote::where('whatsapp_cart_reference', $event->cart_reference)->lockForUpdate()->first();
            if (! $cart) {
                $this->ignore($event, 'unknown_cart');

                return;
            }
            if ($cart->whatsapp_customer_phone !== $event->customer_phone
                || $cart->whatsapp_session_uuid !== $event->session_uuid) {
                $this->ignore($event, 'address_mismatch');

                return;
            }
            if (! $cart->whatsapp_queued_at || $cart->status !== 'succeeded') {
                $this->ignore($event, 'quotation_not_sent');

                return;
            }
            $paymentAction = array_key_exists($event->action, CartQuote::PAYMENT_METHODS);
            if ($paymentAction && ($cart->customer_status !== 'confirmed'
                || ! in_array($cart->operator_status, ['confirmed', 'following_up', 'buying', 'purchased'], true)
                || $cart->customer_payment_method !== null)) {
                $this->ignore($event, 'state_locked');

                return;
            }
            if (! $paymentAction && (in_array($cart->customer_status, ['confirmed', 'cancelled'], true)
                || in_array($cart->operator_status, ['confirmed', 'cancelled', 'buying', 'purchased', 'dismissed'], true)
                || ($event->action === 'edit' && $cart->customer_status === 'editing'))) {
                $this->ignore($event, 'state_locked');

                return;
            }

            $reference = $event->cart_reference;
            if ($paymentAction) {
                $cart->customer_payment_method = $event->action;
                $cart->customer_payment_updated_at = now();
            } elseif ($event->action === 'confirm') {
                $cart->customer_status = 'confirmed';
                $cart->operator_status = 'confirmed';
                $cart->customer_payment_method = null;
                $cart->customer_payment_updated_at = null;
            } elseif ($event->action === 'edit') {
                $cart->customer_status = 'editing';
                $cart->operator_status = 'editing';
            } else {
                $cart->customer_status = 'cancelled';
                $cart->operator_status = 'cancelled';
            }
            $settings = Setting::find(1) ?? new Setting;
            if ($paymentAction) {
                $reply = $settings->cartPaymentReply($event->action, $reference);
                $cart->save();
            } else {
                $reply = $settings->cartActionReply($event->action, $reference, $cart->retailer ?? 'shein');
                $cart->customer_updated_at = now();
                $cart->operator_updated_at = now();
                $cart->handled_by = null;
                app(CartStatusAuditService::class)->saveForCustomer($cart, $event->customer_phone,
                    $event->session_uuid, $event->event_id);
            }
            DB::table(self::TABLE)->where('id', $id)->update([
                'state_applied_at' => now(), 'reply_text' => $reply,
                'reply_image_path' => $settings->replyImagePath($settings->cartReplyImageKey($event->action, $cart->retailer ?? 'shein')),
                'outcome' => 'applied', 'updated_at' => now(),
            ]);
            Log::info('Cart WhatsApp button applied', $this->context($event) + ['customer_status' => $cart->customer_status]);
        });

        // Serialize reply attempts per event, without locking the cart or blocking ingress.
        DB::transaction(function () use ($id) {
            $event = DB::table(self::TABLE)->where('id', $id)->lockForUpdate()->first();
            if (! $event || $event->processed_at || ! $event->reply_text || now()->lt($event->next_attempt_at)) {
                return;
            }
            $cart = CartQuote::where('whatsapp_cart_reference', $event->cart_reference)->first();
            $paymentAction = array_key_exists($event->action, CartQuote::PAYMENT_METHODS);
            $expectedState = ['confirm' => 'confirmed', 'edit' => 'editing', 'cancel' => 'cancelled',
                'cash' => 'confirmed', 'bank' => 'confirmed'][$event->action];
            if (! $cart || $cart->customer_status !== $expectedState
                || $cart->whatsapp_customer_phone !== $event->customer_phone
                || $cart->whatsapp_session_uuid !== $event->session_uuid
                || ($paymentAction && ($cart->customer_payment_method !== $event->action
                    || in_array($cart->operator_status, ['cancelled', 'dismissed'], true)))
                || ($event->action === 'confirm' && $cart->customer_payment_method !== null)) {
                $this->ignore($event, 'reply_superseded');

                return;
            }
            $buttons = $event->action === 'confirm' ? $cart->whatsappPaymentButtons() : [];
            if (! $this->whatsApp->sendMessage($event->customer_phone, $event->reply_text, $event->session_uuid, $buttons,
                $event->reply_image_path)) {
                throw new \RuntimeException('Whatsy did not accept the cart action reply');
            }
            DB::table(self::TABLE)->where('id', $id)->update([
                'reply_queued_at' => now(), 'processed_at' => now(),
                'outcome' => 'reply_queued', 'updated_at' => now(),
            ]);
            Log::info('Cart WhatsApp action reply queued', $this->context($event));
        });
    }

    private function ignore(object $event, string $reason): void
    {
        DB::table(self::TABLE)->where('id', $event->id)->update([
            'outcome' => $reason, 'processed_at' => now(), 'updated_at' => now(),
        ]);
        Log::info('Cart WhatsApp button ignored', $this->context($event) + ['reason' => $reason]);
    }

    private function retry(int $id, \Throwable $exception): void
    {
        DB::transaction(function () use ($id, $exception) {
            $event = DB::table(self::TABLE)->where('id', $id)->lockForUpdate()->first();
            if (! $event || $event->processed_at) {
                return;
            }
            $attempts = $event->attempts + 1;
            DB::table(self::TABLE)->where('id', $id)->update([
                'attempts' => $attempts, 'next_attempt_at' => now()->addSeconds(min(300, 30 * (2 ** min($attempts - 1, 4)))),
                'updated_at' => now(),
            ]);
            Log::warning('Cart WhatsApp action retry scheduled', $this->context($event)
                + ['attempts' => $attempts, 'exception' => get_class($exception)]);
        });
    }

    private function context(object $event): array
    {
        return ['event_id' => $event->event_id, 'action' => $event->action, 'cart_ref' => $event->cart_reference,
            'customer' => $event->customer_phone, 'session' => $event->session_uuid];
    }
}
