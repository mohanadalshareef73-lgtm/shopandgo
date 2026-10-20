<?php

namespace App\Support;

use App\Models\Order;

/** 🛵 رابط «اطلب مندوب» للعميل من رسالة الواتساب (من غير دخول) — رابط قصير بتوقيع سري */
class DeliveryLink
{
    public static function token(int $orderId): string
    {
        return substr(hash_hmac('sha256', 'deliv|' . $orderId, (string) config('app.key')), 0, 16);
    }

    public static function url(Order $o): string
    {
        return route('deliv.req', ['order' => $o->id, 't' => static::token((int) $o->id)]);
    }

    public static function check(int $orderId, string $t): bool
    {
        return hash_equals(static::token($orderId), $t);
    }
}
