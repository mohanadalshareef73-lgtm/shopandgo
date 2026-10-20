<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\Log;
use App\Models\Order;

/** إيصال مكرر؟ نفس العميل + نفس القيمة + نفس الكمية، اليوم ولا أمس */
class DupCheck
{
    public static function find(?int $customerId, ?string $phone, float $total, int $qty): ?Order
    {
        if (!$customerId && $phone) {
            $tail = substr(preg_replace('/\D/', '', $phone), -9);
            $customerId = $tail !== '' ? Customer::where('phone_1', 'like', '%' . $tail)->value('id') : null;
        }
        if (!$customerId || $total <= 0) {
            return null;
        }

        return Order::where('customer_id', $customerId)->where('status', '!=', 'canceld')
            ->whereBetween('total_price', [$total - 0.01, $total + 0.01])->where('qty', $qty)
            ->where('created_at', '>=', now()->subDay()->startOfDay())->latest('id')->first();
    }

    /** يرجع رسالة التنبيه (HTML بسيط) */
    public static function message(Order $o): string
    {
        return '⚠ ممكن إيصال مكرر: نفس العميل عنده إيصال <a class="code" target="_blank" href="' . route('sg.orders.show', $o) . '">' . e($o->code) . '</a> بنفس القيمة والكمية (' . $o->created_at->format('Y-m-d g:i a') . '). لو متأكد إنه مش مكرر علّم «سجّله على أي حال» وعاود احفظ.';
    }

    public static function logOverride(Order $new, Order $dup): void
    {
        Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ' إيصال رغم التكرار (' . $new->code . ')', 'log' => ' يشبه ' . $dup->code . ' · ' . $new->total_price . ' · ' . $new->qty . ' قطعة']);
    }
}
