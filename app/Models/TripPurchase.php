<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * رحلة شراء = مجموعة إيصالات (طلبات) نشريهم مع بعض تحت رقم رحلة واحد.
 * الطلبات مربوطة بالرحلة عن طريق orders.trip_details = trip_code.
 *
 * ربح الرحلة بالدينار = مجموع الإيصالات بالدينار − (المدفوع بالدولار × سعر الصرف)
 * ربح الرحلة بالدولار = ربح الدينار ÷ سعر الصرف
 */
class TripPurchase extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'purchased_at' => 'date',
        'coupon_used' => 'boolean',
        'legacy' => 'boolean',
    ];

    protected ?array $summaryCache = null;

    public function orders()
    {
        return $this->hasMany(Order::class, 'trip_details', 'trip_code');
    }

    public function shippingCharges()
    {
        return $this->hasMany(ShippingCharges::class, 'trip_purchase_id');
    }

    /** بطاقات SHEIN اللي انخلص بيها جزء من الرحلة */
    public function cardUses()
    {
        return $this->hasMany(SheinCardUse::class, 'trip_purchase_id');
    }

    /** الجزء اللي انخلص من الخزينة (التكلفة − اللي انخلص بالبطاقات) */
    /** رحلة من المنظومة القديمة لم تُسجَّل تكلفتها بعد: لا تدخل في تقارير الأرباح */
    public function unknownCost(): bool
    {
        return (bool) $this->legacy && (float) $this->cost <= 0;
    }

    /** الرحلات المحسوبة في الأرباح (يستبعد الرحلات القديمة بدون تكلفة) */
    public function scopeCountable($q)
    {
        static $has;
        $has ??= \Illuminate\Support\Facades\Schema::hasColumn('trip_purchases', 'legacy');

        if ($has) {
            $q->where(fn ($w) => $w->where('legacy', false)->orWhereNull('legacy')->orWhere('cost', '>', 0));
        }
        // الأرباح تبدا من رحلة معينة (افتراضياً B-0393): اللي قبلها قديمة/وهمية ما تدخلش الميزانية ولا الهامش
        static $anchor = false;
        if ($anchor === false) {
            $code = (string) \App\Services\AppSettings::get('profit_from_trip', 'B-0393');
            $anchor = $code !== '' ? static::query()->where('trip_code', $code)->first(['id', 'purchased_at']) : null;
        }
        if ($anchor) {
            // الرحلات القديمة المستوردة (زي a1) ما تدخلش حتى لو تاريخ استيرادها جديد
            if ($has) {
                $q->where(fn ($w) => $w->where('legacy', false)->orWhereNull('legacy'));
            }
            $d = \Carbon\Carbon::parse($anchor->purchased_at)->toDateString();
            $q->where(fn ($w) => $w->whereDate('purchased_at', '>', $d)->orWhere(fn ($x) => $x->whereDate('purchased_at', $d)->where('id', '>=', $anchor->id)));
        }

        return $q;
    }

    /** للتنبيهات: الرحلات القديمة المستوردة تُحسب فقط لو آخر 3 أشهر (القديمة جداً ما تزعجش) */
    public function scopeAlertable($q)
    {
        static $has;
        $has ??= \Illuminate\Support\Facades\Schema::hasColumn('trip_purchases', 'legacy');

        return $has ? $q->where(fn ($w) => $w->where('legacy', false)->orWhereNull('legacy')->orWhere('purchased_at', '>=', now()->subMonths(3)->toDateString())) : $q;
    }

    public function cashCost(): float
    {
        if (($this->cost_currency ?: 'dinar') !== 'dollar') {
            return (float) $this->cost;
        }

        return max(0, round((float) $this->cost - (float) $this->cards_amount, 2));
    }

    /** المبلغ والعملة اللذان يتحركان في الخزينة لجزء نقدي من الرحلة (قيمة بعملة الرحلة) */
    public function treasuryCharge(?float $amount = null): array
    {
        $amount = $amount ?? $this->cashCost();
        if ($this->pay_currency === 'dinar' && ($this->cost_currency ?: 'dinar') === 'dollar') {
            return [round($amount * (float) $this->rate, 2), 'dinar'];
        }

        return [round($amount, 2), $this->cost_currency ?: 'dinar'];
    }

    public function payment()
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_id');
    }

    public function createdUser()
    {
        return $this->belongsTo(User::class, 'created_id');
    }

    /** يحول مبلغ لدينار بسعر صرف الرحلة */
    public function toDinar(float $amount, ?string $currency): float
    {
        return ($currency ?: 'dinar') === 'dinar' ? $amount : $amount * (float) $this->rate;
    }

    public function summary(): array
    {
        if ($this->summaryCache !== null) {
            return $this->summaryCache;
        }

        $orders = $this->relationLoaded('orders') ? $this->orders : $this->orders()->get();
        $orders = $orders->where('status', '!=', 'canceld');
        $rate = (float) $this->rate ?: 1;

        $salesDinar = 0.0;
        $paidDinar = 0.0;
        foreach ($orders as $o) {
            $salesDinar += $this->toDinar((float) $o->total_price, $o->currency);
            $paidDinar += $this->toDinar((float) $o->paid_price, $o->currency);
        }
        // الجزء المدفوع ببطاقات SHEIN يتحسب بتكلفة البطاقات الحقيقية، والباقي بسعر صرف الرحلة
        $costDinar = (float) $this->cards_cost_dinar + $this->toDinar($this->cashCost(), $this->cost_currency);
        $profitDinar = $salesDinar - $costDinar;

        return $this->summaryCache = [
            'orders' => $orders->count(),
            'pieces' => (int) $orders->sum('qty'),
            'sales_dinar' => round($salesDinar, 2),
            'sales_dollar' => round($salesDinar / $rate, 2),
            'cost_dinar' => round($costDinar, 2),
            'cost_dollar' => round($this->cost_currency === 'dollar' ? (float) $this->cost : $costDinar / $rate, 2),
            'profit_dinar' => round($profitDinar, 2),
            'profit_dollar' => round($profitDinar / $rate, 2),
            'margin' => $salesDinar > 0 ? round($profitDinar / $salesDinar * 100, 1) : 0,
            'deposits_dinar' => round($paidDinar, 2),
            'status' => optional($orders->first())->trip_status,
        ];
    }
}
