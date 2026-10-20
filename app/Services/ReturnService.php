<?php

namespace App\Services;

use App\Models\Log;
use App\Models\Order;
use App\Models\OrderReturn;
use RuntimeException;

/**
 * المرتجع والاستبدال:
 *  - مرتجع: قيمة التي رجع تنقص من الإيصال (خصم)، وبعدها حسب طريقة المبلغ:
 *      deduct: ينقص من الذي عليه فقط
 *      cash:   إذا دفع أكثر من الجديد، الزيادة ترجعله من الخزينة
 *      credit: الإيصال لا يتغير، القيمة تصبح رصيداً عند العميل
 *      none:   يُسجَّل فقط (بدون أموال)
 *  - استبدال: نفس المرتجع للقطعة القديمة + إذا الجديدة أغلى، الفرق يتزاد على الإيصال (extra)
 */
class ReturnService
{
    public static function create(Order $order, array $d): OrderReturn
    {
        if ($order->status === 'canceld') {
            throw new RuntimeException('الإيصال ملغي');
        }
        $type = $d['type'] ?? 'return';
        $amount = round((float) ($d['amount'] ?? 0), 2);
        $extra = $type === 'exchange' ? round((float) ($d['extra'] ?? 0), 2) : 0;
        $method = $d['method'] ?? 'deduct';
        if ($amount <= 0 && $extra <= 0 && $method !== 'none') {
            throw new RuntimeException('اكتب قيمة المرتجع');
        }
        if ($amount > $order->grandTotal() + 0.01) {
            throw new RuntimeException('قيمة المرتجع (' . TreasuryService::fmt($amount) . ') أكبر من قيمة الإيصال (' . TreasuryService::fmt($order->grandTotal()) . ')');
        }
        if ($method === 'cash' && empty($d['payment_id'])) {
            throw new RuntimeException('اختر الخزينة التي تظهر منها المبلغ');
        }

        $refunded = 0.0;
        $customer = $order->customer;
        $label = $type === 'exchange' ? 'استبدال' : 'مرتجع';

        if ($amount > 0 && $method !== 'none') {
            if ($method === 'credit') {
                $customer->credit = (float) $customer->credit + $amount;
                $customer->save();
            } else {
                $order->discount = (float) $order->discount + $amount;
                $order->discount_reason = trim(($order->discount_reason ? $order->discount_reason . ' + ' : '') . $label);
                if ($method === 'cash') {
                    $refunded = round(min($amount, max(0, (float) $order->paid_price - $order->grandTotal() - $extra)), 2);
                    if ($refunded > 0) {
                        $order->paid_price = (float) $order->paid_price - $refunded;
                        TreasuryService::withdraw($refunded, $order->currency ?: 'dinar', (int) $d['payment_id'], 'order', $order->id, 'إرجاع أموال ' . $label . ' ' . $order->code);
                    }
                }
            }
        }
        if ($extra > 0) {
            $order->total_price = (float) $order->total_price + $extra;
        }
        $order->returned_value = (float) $order->returned_value + $amount;
        $order->refreshPaymentStatus();
        $order->save();

        $r = OrderReturn::create([
            'order_id' => $order->id, 'customer_id' => $order->customer_id, 'type' => $type,
            'qty' => (int) ($d['qty'] ?? 0), 'items' => $d['items'] ?? null, 'amount' => $amount, 'extra' => $extra,
            'method' => $method, 'payment_id' => $method === 'cash' ? (int) $d['payment_id'] : null, 'refunded' => $refunded,
            'goods' => $d['goods'] ?? 'shop', 'reason' => $d['reason'] ?? null, 'new_items' => $type === 'exchange' ? ($d['new_items'] ?? null) : null,
            'created_id' => auth('web')->id(),
        ]);

        Log::create(['user_id' => auth('web')->id(), 'type' => 'order', 'title' => ' ' . $label . ' (' . $order->code . ')',
            'log' => ' ' . ($r->qty ? $r->qty . ' قطعة · ' : '') . 'قيمة ' . $amount . ' · ' . $r->methodLabel() . ($refunded ? ' · صُرف من الخزينة ' . $refunded : '') . ($extra ? ' · زيادة ' . $extra : '')]);

        return $r;
    }

    /** نص يُرسل للعميل */
    public static function customerText(OrderReturn $r): string
    {
        $f = fn ($n) => TreasuryService::fmt($n);
        $lines = [];
        $lines[] = ($r->type === 'exchange' ? 'استبدال' : 'مرتجع') . ($r->items ? ': ' . $r->items : '') . ($r->qty ? ' (' . $r->qty . ' قطعة)' : '');
        if ($r->amount > 0) {
            $lines[] = match ($r->method) {
                'cash' => 'رجعنالك ' . $f($r->refunded ?: $r->amount) . ' د.ل',
                'credit' => 'تحطلك رصيد ' . $f($r->amount) . ' د.ل للطلبية القادمة',
                'deduct' => 'خصمنا ' . $f($r->amount) . ' د.ل من الذي عليك',
                default => '',
            };
        }
        if ($r->extra > 0) {
            $lines[] = 'فرق سعر البديل: ' . $f($r->extra) . ' د.ل';
        }
        $lines[] = 'الباقي على الإيصال: ' . $f(max(0, $r->order->remaining())) . ' د.ل';

        return implode("\n", array_filter($lines));
    }
}
