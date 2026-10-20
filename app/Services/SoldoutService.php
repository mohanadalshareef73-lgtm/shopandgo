<?php

namespace App\Services;

use App\Models\Log;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderSoldout;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * منتجات نفدت أثناء الشراء:
 *  - تُسجَّل بقيمتها ويُبلَّغ العميل عبر واتساب ليقرر.
 *  - إلغاء: قيمتها تُخصم من الإيصال. إن كان العميل دفع أكثر من الإيصال الجديد،
 *    الزيادة تصبح رصيداً لديه (افتراضياً) أو تُرجع نقداً من الخزينة.
 *  - استبدال: نفس الخصم من الإيصال الأصلي + إيصال جديد بالمنتج البديل وسعره (يُشترى وحده).
 */
class SoldoutService
{
    public static function add(Order $order, array $rows): Collection
    {
        if ($order->status === 'canceld') {
            throw new RuntimeException('الإيصال ملغي');
        }
        $made = collect();
        foreach ($rows as $r) {
            $item = trim((string) ($r['item'] ?? ''));
            $price = round((float) ($r['price'] ?? 0), 2);
            if ($item === '') {
                continue;
            }
            if ($price <= 0) {
                throw new RuntimeException('اكتب قيمة المنتج: ' . $item);
            }
            $made->push(OrderSoldout::create([
                'order_id' => $order->id, 'customer_id' => $order->customer_id, 'item' => mb_substr($item, 0, 200),
                'qty' => max(1, (int) ($r['qty'] ?? 1)), 'price' => $price, 'status' => 'pending', 'created_id' => auth('web')->id(),
            ]));
        }
        if ($made->isEmpty()) {
            throw new RuntimeException('أضف منتجاً واحداً على الأقل');
        }
        $pendingTotal = OrderSoldout::where('order_id', $order->id)->where('status', 'pending')->sum('price');
        if ($pendingTotal > $order->grandTotal() + 0.01) {
            throw new RuntimeException('مجموع المنتجات النافدة (' . TreasuryService::fmt($pendingTotal) . ') أكبر من قيمة الإيصال (' . TreasuryService::fmt($order->grandTotal()) . ')');
        }
        Log::create(['user_id' => auth('web')->id(), 'type' => 'order', 'title' => ' منتجات نفدت (' . $order->code . ')',
            'log' => ' ' . $made->map(fn ($s) => $s->item . ' × ' . $s->qty . ' = ' . $s->price)->implode('، ')]);

        return $made;
    }

    public static function messageText(Order $order, Collection $items): string
    {
        $f = fn ($n) => TreasuryService::fmt($n);
        $list = $items->map(fn ($s) => '• ' . $s->item . ($s->qty > 1 ? ' × ' . $s->qty : '') . ' — ' . $f($s->price) . ' د.ل')->implode("\n");
        $vars = MessageService::orderVars($order, ['{المنتجات}' => $list, '{المجموع}' => $f($items->sum('price'))]);
        $tpl = MessageService::template('soldout_notice');

        return $tpl ? MessageService::render($tpl->message, $vars)
            : "مرحباً {$vars['{الاسم}']}\nنفدت المنتجات التالية من طلبك {$order->code}:\n{$list}\nهل تريد إلغاءها وخصم قيمتها أو استبدالها؟";
    }

    public static function notify(Order $order, Collection $items, ?string $text = null): bool
    {
        $c = $order->customer;
        $text = trim((string) $text) !== '' ? $text : static::messageText($order, $items);
        $ok = MessageService::sendText('soldout_notice', $text, array_values(array_filter([optional($c)->phone_1, optional($c)->phone_2])), $c, $order);
        if ($ok) {
            OrderSoldout::whereIn('id', $items->pluck('id'))->update(['notified_at' => now()]);
        }

        return $ok;
    }

    /** خصم قيمة المنتج من الإيصال، ومعالجة الزيادة إن كان العميل دفع أكثر */
    protected static function deduct(OrderSoldout $s, string $excess, ?int $paymentId): void
    {
        $order = $s->order()->lockForUpdate()->first();
        $order->discount = (float) $order->discount + (float) $s->price;
        $order->discount_reason = trim(($order->discount_reason ? $order->discount_reason . ' + ' : '') . 'منتج نفد');
        $order->qty = max(0, (int) $order->qty - (int) $s->qty);
        $over = round((float) $order->paid_price - $order->grandTotal(), 2);
        if ($over > 0.009) {
            $over = min($over, (float) $s->price);
            if ($excess === 'cash') {
                if (!$paymentId) {
                    throw new RuntimeException('اختر الخزينة التي يُصرف منها المبلغ');
                }
                $order->paid_price = (float) $order->paid_price - $over;
                TreasuryService::withdraw($over, $order->currency ?: 'dinar', $paymentId, 'order', $order->id, 'إرجاع قيمة منتج نفد ' . $order->code);
                $s->refunded = $over;
                $s->method = 'cash';
            } else {
                // المبلغ الزائد يبقى في الخزينة ويصبح رصيداً للعميل
                $order->paid_price = (float) $order->paid_price - $over;
                $c = $order->customer;
                $c->credit = (float) $c->credit + $over;
                $c->save();
                $s->credited = $over;
                $s->method = 'credit';
            }
        } else {
            $s->method = 'deduct';
        }
        $order->refreshPaymentStatus();
        $order->save();
    }

    public static function cancel(OrderSoldout $s, string $excess = 'credit', ?int $paymentId = null): void
    {
        if ($s->status !== 'pending') {
            throw new RuntimeException('تم اتخاذ قرار في هذا المنتج مسبقاً');
        }
        static::deduct($s, $excess, $paymentId);
        $s->status = 'cancelled';
        $s->decided_at = now();
        $s->decided_by = auth('web')->id();
        $s->save();
        Log::create(['user_id' => auth('web')->id(), 'type' => 'order', 'title' => ' إلغاء منتج نفد (' . $s->order->code . ')', 'log' => ' ' . $s->item . ' · خُصم ' . $s->price . ($s->credited ? ' · رصيد ' . $s->credited : '') . ($s->refunded ? ' · إرجاع ' . $s->refunded : '')]);
    }

    public static function replace(OrderSoldout $s, string $newItem, float $newPrice, int $qty, string $excess = 'credit', ?int $paymentId = null, ?int $platformId = null): Order
    {
        if ($s->status !== 'pending') {
            throw new RuntimeException('تم اتخاذ قرار في هذا المنتج مسبقاً');
        }
        if (trim($newItem) === '' || $newPrice <= 0) {
            throw new RuntimeException('اكتب المنتج البديل وسعره');
        }
        static::deduct($s, $excess, $paymentId);
        $old = $s->order;
        $o = new Order;
        $o->code = OrderNumber::next();
        $o->customer_id = $old->customer_id;
        $o->platform_id = $platformId ?: $old->platform_id;
        $o->qty = max(1, $qty);
        $o->total_price = round($newPrice, 2);
        $o->paid_price = 0;
        $o->status = 'unpaid';
        $o->currency = $old->currency ?: 'dinar';
        $o->currency_sign = $old->currency_sign ?: 'د.ل';
        $o->payment_id = $old->payment_id;
        $o->note = 'بديل لمنتج نفد من الإيصال ' . $old->code . ' (' . $s->item . ')';
        $o->created_id = auth('web')->id();
        $o->save();
        OrderItem::create(['order_id' => $o->id, 'name' => mb_substr(trim($newItem), 0, 200), 'quantity' => max(1, $qty), 'price' => round($newPrice, 2)]);
        $s->status = 'replaced';
        $s->replacement = mb_substr(trim($newItem), 0, 200);
        $s->replacement_price = round($newPrice, 2);
        $s->new_order_id = $o->id;
        $s->decided_at = now();
        $s->decided_by = auth('web')->id();
        $s->save();
        Log::create(['user_id' => auth('web')->id(), 'type' => 'order', 'title' => ' استبدال منتج نفد (' . $old->code . ' → ' . $o->code . ')', 'log' => ' ' . $s->item . ' (' . $s->price . ') ← ' . $s->replacement . ' (' . $s->replacement_price . ')']);

        return $o;
    }

    /** نص تأكيد للعميل بعد القرار */
    public static function decisionText(OrderSoldout $s): string
    {
        $f = fn ($n) => TreasuryService::fmt($n);
        $o = $s->order->fresh();
        $lines = ['مرحباً ' . optional($o->customer)->name];
        if ($s->status === 'cancelled') {
            $lines[] = 'تم إلغاء «' . $s->item . '» من طلبك ' . $o->code . ' وخصم قيمته ' . $f($s->price) . ' د.ل ✅';
        } else {
            $lines[] = 'تم استبدال «' . $s->item . '» بـ «' . $s->replacement . '» بسعر ' . $f($s->replacement_price) . ' د.ل ✅';
            $lines[] = 'رقم الإيصال الجديد: ' . optional($s->newOrder)->code;
            $lines[] = 'وخُصمت قيمة المنتج الأصلي (' . $f($s->price) . ' د.ل) من الإيصال ' . $o->code;
        }
        if ($s->credited > 0) {
            $lines[] = 'أُضيف ' . $f($s->credited) . ' د.ل رصيداً في حسابك، يُستخدم في طلبك القادم أو عند الاستلام';
        }
        if ($s->refunded > 0) {
            $lines[] = 'أُرجع لك ' . $f($s->refunded) . ' د.ل';
        }
        $lines[] = 'المتبقي على الإيصال ' . $o->code . ': ' . $f(max(0, $o->remaining())) . ' د.ل';

        return implode("\n", $lines);
    }
}
