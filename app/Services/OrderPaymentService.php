<?php

namespace App\Services;

use App\Models\Log;
use App\Models\Order;
use App\Models\PaidHistory;

/** قبض أموال على إيصال: سجل الدفع + الخزينة + حالة الإيصال */
class OrderPaymentService
{
    public static function pay(Order $order, float $amount, int $paymentId, string $label = 'دفعة'): void
    {
        if ($amount <= 0) {
            return;
        }
        $pay = new PaidHistory;
        $pay->customer_id = $order->customer_id;
        $pay->order_id = $order->id;
        $pay->paid_price = $amount;
        $pay->payment_id = $paymentId;
        if (\Illuminate\Support\Facades\Schema::hasColumn('paid_histories', 'note')) {
            $pay->note = $label;
        }
        $pay->save();

        $order->paid_price = (float) $order->paid_price + $amount;
        $order->refreshPaymentStatus();
        $order->save();

        if (!empty($order->trade_invoice_id)) {
            TradeService::orderPaid($order, $amount, $paymentId, $label); // 🌍 طلبية تجارة خارجية: الفلوس لخزينة التجارة
        } else {
            TreasuryService::deposit($amount, $order->currency ?: 'dinar', $paymentId, 'order', $order->id, $label . ' ' . $order->code);
        }
        CustomerNotify::inbox($order->customer, '✅ استلمنا منك ' . TreasuryService::fmt($amount) . ' ' . ($order->currency_sign ?: 'د.ل') . ' على الإيصال ' . $order->code . ' · المتبقي ' . TreasuryService::fmt(max(0, $order->remaining())) . ' ' . ($order->currency_sign ?: 'د.ل'), $order, 'payment');

        Log::create([
            'user_id' => auth('web')->id(),
            'type' => 'customer',
            'title' => ' مستحقات العميل (' . optional($order->customer)->name . ')',
            'log' => ' ' . $label . ' ' . $amount . ' د.ل للطلب ' . $order->code,
        ]);
    }

    /** ✎ تعديل دفعة مسجلة: القيمة أو طريقة الدفع (0 = حذف) — الخزينة تتعدل بالفرق */
    public static function edit(PaidHistory $p, float $amount, int $paymentId): void
    {
        $order = Order::findOrFail($p->order_id);
        $old = (float) $p->paid_price;
        $oldPid = (int) $p->payment_id;
        $cur = $order->currency ?: 'dinar';
        $amount = round(max(0, $amount), 2);
        if (!empty($order->trade_invoice_id)) {
            // 🌍 طلبية تجارة خارجية: التعديل على خزينة التجارة
            if ($old > 0.009) {
                TradeService::orderPaid($order, -$old, $oldPid, 'تعديل دفعة');
            }
            if ($amount > 0.009) {
                TradeService::orderPaid($order, $amount, $paymentId, 'تعديل دفعة');
            }
        } elseif ($oldPid === $paymentId) {
            $d = round($amount - $old, 2);
            if ($d > 0.009) {
                TreasuryService::deposit($d, $cur, $paymentId, 'order', $order->id, 'تعديل دفعة ' . $order->code . ' (+' . $d . ')');
            } elseif ($d < -0.009) {
                TreasuryService::withdraw(-$d, $cur, $paymentId, 'order', $order->id, 'تعديل دفعة ' . $order->code . ' (' . $d . ')', true);
            }
        } else {
            if ($old > 0.009) {
                TreasuryService::withdraw($old, $cur, $oldPid, 'order', $order->id, 'تعديل دفعة ' . $order->code . ' (تغيير الخزينة)', true);
            }
            if ($amount > 0.009) {
                TreasuryService::deposit($amount, $cur, $paymentId, 'order', $order->id, 'تعديل دفعة ' . $order->code . ' (تغيير الخزينة)');
            }
        }
        $order->paid_price = max(0, round((float) $order->paid_price - $old + $amount, 2));
        $order->refreshPaymentStatus();
        $order->save();
        if ($amount <= 0.009) {
            $p->delete();
        } else {
            $p->paid_price = $amount;
            $p->payment_id = $paymentId;
            $p->save();
        }
        Log::create([
            'user_id' => auth('web')->id(), 'type' => 'order',
            'title' => ' تعديل دفعة (' . $order->code . ')',
            'log' => ' من ' . $old . ' إلى ' . $amount . ($oldPid !== $paymentId ? ' · تغيرت طريقة الدفع' : '') . ($amount <= 0.009 ? ' · انحذفت' : ''),
        ]);
    }
}
