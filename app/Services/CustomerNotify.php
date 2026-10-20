<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerMessage;
use App\Models\Order;

/**
 * إشعار في حساب العميل (يظهر في الصفحة الرئيسية لحسابه مع عداد غير المقروء).
 * كل رسالة واتساب تُرسل لعميل تُسجَّل هنا تلقائياً، ومعها أحداث لا تُرسل واتساب (الدفعات…).
 */
class CustomerNotify
{
    /** يمكن إيقافه مؤقتاً عند إنشاء الإشعار يدوياً لتجنب التكرار */
    public static bool $enabled = true;

    public static function inbox(?Customer $c, string $body, ?Order $order = null, ?string $kind = null): void
    {
        if (!static::$enabled || !$c || trim($body) === '') {
            return;
        }
        try {
            // منع التكرار: نفس النص لنفس العميل خلال دقيقتين
            $dup = CustomerMessage::where('customer_id', $c->id)->where('body', $body)->where('created_at', '>=', now()->subMinutes(2))->exists();
            if ($dup) {
                return;
            }
            $row = ['customer_id' => $c->id, 'body' => mb_substr($body, 0, 2000), 'created_id' => auth('web')->id()];
            if (\Illuminate\Support\Facades\Schema::hasColumn('customer_messages', 'kind')) {
                $row['kind'] = $kind;
                $row['order_id'] = optional($order)->id;
            }
            CustomerMessage::create($row);
            PushService::notify($c); // إشعار على جواله (مجاني)
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
