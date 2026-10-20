<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Log;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Platform;
use App\Models\SheinCard;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * بيع بطاقات SHEIN للعملاء عبر إيصال:
 *  - تظهر البطاقات الجديدة فقط (غير مستخدمة وغير مباعة)
 *  - الإيصال نوعه card_sale، يُسلَّم فوراً (البطاقة رقمية)، والدفع مثل أي إيصال (أو يبقى ديناً)
 *  - البطاقة تُعلَّم «مباعة» مع العميل والإيصال وسعر البيع، ويُرسل رقمها ورمزها للعميل عبر واتساب
 *  - الربح = سعر البيع − تكلفة البطاقة (يظهر في المالية)
 */
class CardSaleService
{
    public const PLATFORM = 'بيع بطاقات SHEIN';

    public static function platformId(): int
    {
        $p = Platform::where('name', static::PLATFORM)->first();
        if (!$p) {
            $p = new Platform;
            $p->name = static::PLATFORM;
            $p->save();
        }

        return (int) $p->id;
    }

    /** البطاقات المتاحة للبيع: جديدة بكامل رصيدها، غير منتهية، وغير مباعة */
    public static function available()
    {
        return SheinCard::where('status', 'new')->whereNull('sold_order_id')->whereColumn('balance', 'face_value')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhereDate('expires_at', '>=', now()->toDateString()));
    }

    /** سعر الدولار للبيع (د.ل): من الإعدادات، وإلا آخر سعر صرف */
    public static function rate(): float
    {
        $r = (float) AppSettings::get('card_sale_rate', 0);

        return $r > 0 ? $r : round((float) (\App\Models\CurrencyExchange::latestRate() ?: 0), 2);
    }

    /** سعر الدولار في السوق (آخر شراء) لتحويل التكلفة والبيع بين الدينار والدولار */
    public static function marketRate(): float
    {
        return (float) (\App\Models\CurrencyExchange::latestRate() ?: 0);
    }

    /** تكلفة البطاقة بالدولار: إن اشتُريت بالدولار فتكلفتها الفعلية، وإلا تكلفتها بالدينار ÷ سعر الدولار */
    public static function costUsd(SheinCard $c, ?float $rate = null): ?float
    {
        if ($c->cost_currency === 'dollar') {
            return round((float) $c->cost, 2);
        }
        $rate = $rate ?? static::marketRate();

        return $rate > 0 ? round((float) $c->cost_dinar / $rate, 2) : null;
    }

    public static function sell(Customer $c, array $prices, float $paid, ?int $paymentId, ?string $note = null): Order
    {
        $ids = array_map('intval', array_keys($prices));
        if (!$ids) {
            throw new RuntimeException('اختر بطاقة واحدة على الأقل');
        }
        $cards = static::available()->whereIn('id', $ids)->lockForUpdate()->get();
        if ($cards->count() !== count($ids)) {
            throw new RuntimeException('بعض البطاقات المختارة لم تعد متاحة (بيعت أو استُخدمت)، حدّث الصفحة');
        }
        $total = 0.0;
        foreach ($cards as $card) {
            $p = round((float) $prices[$card->id], 2);
            if ($p <= 0) {
                throw new RuntimeException('اكتب سعر البطاقة ' . $card->masked());
            }
            $total += $p;
        }
        if ($paid > $total + 0.01) {
            throw new RuntimeException('المدفوع أكبر من قيمة الإيصال');
        }
        if ($paid > 0 && !$paymentId) {
            throw new RuntimeException('اختر طريقة الدفع');
        }
        $o = new Order;
        $o->code = OrderNumber::next();
        $o->kind = 'card_sale';
        $o->customer_id = $c->id;
        $o->platform_id = static::platformId();
        $o->qty = $cards->count();
        $o->total_price = round($total, 2);
        $o->paid_price = 0;
        $o->status = 'unpaid';
        $o->currency = 'dinar';
        $o->currency_sign = 'د.ل';
        $o->payment_id = $paymentId;
        $o->note = $note;
        $o->created_id = auth('web')->id();
        // البطاقة رقمية: تُعتبر مستلمة ومسلّمة فوراً
        $o->arrived = true;
        $o->arrived_at = now()->toDateString();
        $o->received_at = now();
        $o->received_by = auth('web')->id();
        $o->received_qty = $cards->count();
        $o->delivered = true;
        $o->delivered_time = now();
        $o->delivered_by = auth('web')->id();
        $o->arrived_notified_at = now();
        $o->save();
        $mr = static::marketRate();
        foreach ($cards as $card) {
            $p = round((float) $prices[$card->id], 2);
            $card->sale_usd = $mr > 0 ? round($p / $mr, 2) : null;
            $card->sale_rate = $mr ?: null;
            OrderItem::create(['order_id' => $o->id, 'name' => 'بطاقة SHEIN ' . TreasuryService::fmt($card->face_value) . '$ ' . $card->masked(), 'quantity' => 1, 'price' => $p]);
            $card->status = 'sold';
            $card->balance = 0;
            $card->sold_order_id = $o->id;
            $card->sold_customer_id = $c->id;
            $card->sale_price = $p;
            $card->sold_at = now();
            $card->sold_by = auth('web')->id();
            $card->save();
        }
        if ($paid > 0) {
            OrderPaymentService::pay($o, $paid, (int) $paymentId, 'ثمن بطاقة SHEIN');
        } else {
            $o->refreshPaymentStatus();
            $o->save();
        }
        Log::create(['user_id' => auth('web')->id(), 'type' => 'order', 'title' => ' بيع بطاقات SHEIN (' . $o->code . ')',
            'log' => ' ' . $c->name . ' · ' . $cards->map(fn ($x) => $x->masked() . ' ' . $x->face_value . '$')->implode('، ') . ' · ' . $total . ' د.ل']);

        return $o;
    }

    public static function cardsOf(Order $o): Collection
    {
        return SheinCard::where('sold_order_id', $o->id)->orderBy('id')->get();
    }

    protected static function text(Order $o, Collection $cards, bool $masked): string
    {
        $f = fn ($n) => TreasuryService::fmt($n);
        $lines = $cards->map(function ($c) use ($masked, $f) {
            $no = $masked ? $c->masked() : trim(chunk_split((string) $c->card_no, 4, ' '));
            $pin = $masked ? '••••' : (string) $c->pin;

            return "💳 رقم البطاقة: {$no}\n🔑 الرمز: {$pin}\n💵 القيمة: " . $f($c->face_value) . ' $';
        })->implode("\n\n");
        $tpl = MessageService::template('card_sold');
        $vars = MessageService::orderVars($o, ['{البطاقات}' => $lines]);

        $body = $tpl ? MessageService::render($tpl->message, $vars) : "مرحباً {$vars['{الاسم}']}\nبطاقة SHEIN الخاصة بك:\n{$lines}";

        return $body . "\n\n" . static::receipt($o, $cards);
    }

    /** ملخص الإيصال: نوع الكرت + الإجمالي + المدفوع + العملة */
    public static function receipt(Order $o, Collection $cards): string
    {
        $o = $o->fresh() ?? $o;
        $f = fn ($n) => TreasuryService::fmt($n);
        $sign = $o->currency_sign ?: 'د.ل';
        $types = $cards->groupBy(fn ($c) => (string) (float) $c->face_value)
            ->map(fn ($g, $v) => 'بطاقة SHEIN ' . $f($v) . ' $' . ($g->count() > 1 ? ' × ' . $g->count() : ''))->implode(' + ');
        $total = (float) $o->total_price;
        $paid = (float) $o->paid_price;

        return "🧾 *إيصال {$o->code}*\n"
            . "💳 النوع: {$types}\n"
            . '💰 الإجمالي: *' . $f($total) . " {$sign}*\n"
            . '✅ المدفوع: ' . $f($paid) . " {$sign}\n"
            . ($total - $paid > 0.009 ? '⏳ الباقي: ' . $f($total - $paid) . " {$sign}\n" : "✔️ مدفوع بالكامل\n")
            . '💱 العملة: ' . ($o->currency === 'dollar' ? 'دولار ($)' : 'دينار ليبي (د.ل)');
    }

    /** يرسل الرقم والرمز للعميل؛ سجل الواتساب يحفظ نسخة مخفية */
    public static function send(Order $o): bool
    {
        $cards = static::cardsOf($o);
        if ($cards->isEmpty()) {
            return false;
        }
        $c = $o->customer;
        $ok = MessageService::sendText('card_sold', static::text($o, $cards, false), array_values(array_filter([optional($c)->phone_1, optional($c)->phone_2])), $c, $o, static::text($o, $cards, true));
        if ($ok) {
            SheinCard::whereIn('id', $cards->pluck('id'))->update(['sent_at' => now()]);
        }

        return $ok;
    }

    /** نص للنسخ اليدوي (يظهر للموظف المخوّل فقط) */
    public static function plainText(Order $o): string
    {
        return static::text($o, static::cardsOf($o), false);
    }
}
