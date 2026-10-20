<?php

namespace App\Services;

use App\Models\CartQuote;
use App\Models\Log;
use App\Models\WhatsappMessages;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManualCartService
{
    public function create(string $url, string $phone, string $submissionKey, int $operatorId,
        string $retailer = 'shein', int $quantity = 1): CartQuote
    {
        $key = 'manual-'.hash('sha256', $operatorId."\n".$submissionKey);
        try {
            $cart = DB::transaction(function () use ($url, $phone, $key, $operatorId, $retailer, $quantity) {
                $existing = CartQuote::where('idempotency_key', $key)->lockForUpdate()->first();
                if ($existing) {
                    return $existing;
                }
                // Keep the same customer/message relation used by WhatsApp carts.
                // Manual origin explicitly distinguishes this from a webhook message.
                $incoming = WhatsappMessages::create([
                    'message_id' => 'manual-'.Str::uuid(),
                    'from' => $phone,
                    'phone' => '',
                    'name' => 'عميل واتساب',
                    'type' => 'text',
                    'message' => $url,
                    'timestamp' => now(),
                    'is_reply' => false,
                ]);
                $cart = CartQuote::create([
                    'whatsapp_messages_id' => $incoming->id,
                    'source_url' => $url,
                    'idempotency_key' => $key,
                    'origin' => 'manual',
                    'retailer' => $retailer,
                    'requested_quantity' => $retailer === 'amazon' ? $quantity : 1,
                    'status' => 'pending',
                    'operator_status' => 'new',
                    'next_attempt_at' => now(),
                ]);
                Log::create([
                    'user_id' => $operatorId,
                    'type' => 'cart',
                    'title' => ($retailer === 'amazon' ? 'إضافة منتج أمازون يدوياً #' : 'إضافة سلة '.$cart->retailer_label.' يدوياً #').$cart->id,
                    'log' => 'تمت إضافة السلة لفحصها ومتابعة العميل على رقم واتساب '.$phone.'.',
                ]);
                return $cart;
            }, 3);
        } catch (QueryException $e) {
            // If another copy of the form won the insert race, reuse its cart.
            $cart = CartQuote::where('idempotency_key', $key)->first() ?? throw $e;
        }
        if ($cart->origin !== 'manual' || $cart->source_url !== $url || $cart->incomingMessage->from !== $phone
            || $cart->retailer !== $retailer || ($retailer === 'amazon' && $cart->requested_quantity !== $quantity)) {
            throw ValidationException::withMessages([
                'source_url' => 'تم استخدام هذا الطلب لسلة أو رقم آخر. افتح فحص سلة جديد لإضافة بيانات مختلفة.',
            ]);
        }
        return $cart;
    }
}
