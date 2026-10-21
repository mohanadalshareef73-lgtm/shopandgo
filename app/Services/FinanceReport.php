<?php

namespace App\Services;

use App\Models\CurrencyExchange;
use App\Models\Order;
use App\Models\PaidHistory;
use App\Models\PartnerTransaction;
use App\Models\PaymentExpenses;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\ShippingCharges;
use App\Models\TripPurchase;
use Illuminate\Support\Facades\DB;

/**
 * حسابات لوحة المالية. كل الأرقام مقسومة حسب العملة.
 *
 * الربح الصافي = ربح رحلات الشراء (الإيصالات - المدفوع بالدولار × سعر الصرف)
 *              + دخل الشحن من العملاء - المدفوع لشركة الشحن
 *              - مصاريف الشركة - مصروفات الدفع
 */
class FinanceReport
{
    public static function empty(): array
    {
        return array_fill_keys(array_keys(TreasuryService::CURRENCIES), 0.0);
    }

    protected static function byCurrency($rows, string $currencyKey = 'currency', string $valueKey = 'total'): array
    {
        $out = self::empty();
        foreach ($rows as $r) {
            $cur = $r->{$currencyKey} ?: 'dinar';
            if (!array_key_exists($cur, $out)) {
                $cur = 'dinar';
            }
            $out[$cur] += (float) $r->{$valueKey};
        }

        return $out;
    }

    public static function period(?string $from, ?string $to): array
    {
        $range = fn ($q, $col) => $q->when($from, fn ($q) => $q->whereDate($col, '>=', $from))
            ->when($to, fn ($q) => $q->whereDate($col, '<=', $to));

        // ربح البضاعة: رحلات الشراء المسجلة في الفترة (كلها محسوبة بالدينار بسعر صرف كل رحلة)
        $trips = $range(TripPurchase::query()->countable()->with('orders'), 'purchased_at')->get();
        $sales = self::empty();
        $cost = self::empty();
        $goodsProfit = self::empty();
        $goodsProfitDollar = 0.0;
        $tripOrders = 0;
        foreach ($trips as $t) {
            $sm = $t->summary();
            $sales['dinar'] += $sm['sales_dinar'];
            $cost['dinar'] += $sm['cost_dinar'];
            $goodsProfit['dinar'] += $sm['profit_dinar'];
            $goodsProfitDollar += $sm['profit_dollar'];
            $tripOrders += $sm['orders'];
        }

        // دخل الشحن: الطلبات التي وصلت في الفترة
        $shipIncome = self::byCurrency(
            Order::query()->where('status', '!=', 'canceld')->where('ship_price', '>', 0)
                ->where(function ($q) use ($from, $to) {
                    $q->where(function ($q) use ($from, $to) {
                        $q->whereNotNull('received_at')
                            ->when($from, fn ($q) => $q->whereDate('received_at', '>=', $from))
                            ->when($to, fn ($q) => $q->whereDate('received_at', '<=', $to));
                    })->orWhere(function ($q) use ($from, $to) {
                        $q->whereNull('received_at')->whereNotNull('arrived_at')
                            ->when($from, fn ($q) => $q->whereDate('arrived_at', '>=', $from))
                            ->when($to, fn ($q) => $q->whereDate('arrived_at', '<=', $to));
                    });
                })
                ->selectRaw("COALESCE(currency,'dinar') as currency, SUM(ship_price) as total")
                ->groupBy(DB::raw("COALESCE(currency,'dinar')"))->get()
        );

        $shipCost = self::byCurrency(
            $range(ShippingCharges::query(), 'date')
                ->selectRaw("COALESCE(currency,'dinar') as currency, SUM(cost) as total")
                ->groupBy(DB::raw("COALESCE(currency,'dinar')"))->get()
        );
        $shipWeight = (float) $range(ShippingCharges::query(), 'date')->sum('weight');

        $expenses = self::byCurrency(
            $range(Purchase::query(), 'date')
                ->selectRaw("COALESCE(currency,'dinar') as currency, SUM(cost) as total")
                ->groupBy(DB::raw("COALESCE(currency,'dinar')"))->get()
        );
        $expensesByDesc = $range(Purchase::query()->leftJoin('purchase_descriptions', 'purchase_descriptions.id', '=', 'purchases.desc_id'), 'purchases.date')
            ->selectRaw("COALESCE(purchase_descriptions.name,'أخرى') as name, COALESCE(purchases.currency,'dinar') as currency, SUM(purchases.cost) as total")
            ->groupBy('purchase_descriptions.name', DB::raw("COALESCE(purchases.currency,'dinar')"))
            ->orderByDesc('total')->get();

        $paymentExpenses = self::empty();
        $paymentExpenses['dinar'] = (float) $range(PaymentExpenses::query()->where('status', true), 'date')->sum('cost');

        // كسور تلغت مع المندوبين (تُحسب مصروف)
        $courierLoss = \Illuminate\Support\Facades\Schema::hasTable('courier_settlements')
            ? (float) $range(\App\Models\CourierSettlement::query(), 'created_at')->sum('rounding') : 0.0;
        $paymentExpenses['dinar'] += $courierLoss;

        // أجرة توصيل تحمّلتها الشركة (لم تُطلب من العميل) = مصروف
        $companyFees = \Illuminate\Support\Facades\Schema::hasColumn('deliveries', 'fee_payer')
            ? (float) $range(\App\Models\Delivery::query()->where('status', 'delivered')->where('fee_payer', 'company'), 'delivered_at')->sum('fee') : 0.0;
        $paymentExpenses['dinar'] += $companyFees;

        // بطاقات SHEIN: خسارة البطاقات التي انتهت/خطأ + بطاقات استعملناها على إيصالات بدون رحلة
        $cardsCost = 0.0;
        if (\Illuminate\Support\Facades\Schema::hasTable('shein_cards')) {
            $cardsCost = (float) $range(\App\Models\SheinCard::query()->whereNotNull('lost_at'), 'lost_at')->sum('lost_dinar')
                + (float) $range(\App\Models\SheinCardUse::query()->whereNull('trip_purchase_id'), 'created_at')->sum('cost_dinar');
        }
        $paymentExpenses['dinar'] += $cardsCost;

        // بيع بطاقات SHEIN للعملاء: الربح = سعر البيع − تكلفة البطاقة
        $cardSales = ['count' => 0, 'sales' => 0.0, 'cost' => 0.0, 'profit' => 0.0, 'usd' => 0.0];
        if (\Illuminate\Support\Facades\Schema::hasColumn('shein_cards', 'sold_order_id')) {
            $sold = $range(\App\Models\SheinCard::query()->whereNotNull('sold_order_id')
                ->whereHas('soldOrder', fn ($q) => $q->where('status', '!=', 'canceld')), 'sold_at')->get(['sale_price', 'cost_dinar', 'face_value']);
            $cardSales = ['count' => $sold->count(), 'sales' => (float) $sold->sum('sale_price'), 'cost' => (float) $sold->sum('cost_dinar'),
                'profit' => (float) $sold->sum('sale_price') - (float) $sold->sum('cost_dinar'), 'usd' => (float) $sold->sum('face_value')];
        }

        $net = self::empty();
        // 🚚 ربح الشحن ما يدخلش في صافي الربح إلا لو المدير شغّله (إعداد profit_with_ship)
        $withShip = (bool) \App\Services\AppSettings::get('profit_with_ship', false);
        foreach ($net as $c => $_) {
            $net[$c] = $goodsProfit[$c] + ($withShip ? $shipIncome[$c] - $shipCost[$c] : 0) - $expenses[$c] - $paymentExpenses[$c];
        }
        $net['dinar'] += $cardSales['profit'];

        // المقبوض من العملاء في الفترة (نقداً/مصرف...)
        $collected = PaidHistory::query()
            ->join('orders', 'orders.id', '=', 'paid_histories.order_id')
            ->leftJoin('payment_methods', 'payment_methods.id', '=', 'paid_histories.payment_id')
            ->when($from, fn ($q) => $q->whereDate('paid_histories.created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('paid_histories.created_at', '<=', $to))
            ->selectRaw("COALESCE(payment_methods.name,'-') as method, COALESCE(orders.currency,'dinar') as currency, SUM(paid_histories.paid_price) as total")
            ->groupBy('payment_methods.name', DB::raw("COALESCE(orders.currency,'dinar')"))->get();

        $partners = $range(PartnerTransaction::query()->with('partner'), 'date')
            ->selectRaw('partner_id, type, currency, SUM(amount) as total')
            ->groupBy('partner_id', 'type', 'currency')->get();

        $exchange = $range(CurrencyExchange::query()->where('to_currency', 'dollar'), 'date')
            ->selectRaw('COALESCE(SUM(from_amount),0) as paid, COALESCE(SUM(to_amount),0) as got, COUNT(*) as cnt')->first();

        return [
            'counts' => [
                'trips' => $trips->count(),
                'orders' => $tripOrders,
                'coupons' => $trips->where('coupon_used', true)->count(),
            ],
            'goodsProfitDollar' => $goodsProfitDollar,
            'sales' => $sales,
            'cost' => $cost,
            'goodsProfit' => $goodsProfit,
            'withShip' => $withShip,
            'shipIncome' => $shipIncome,
            'shipCost' => $shipCost,
            'shipWeight' => $shipWeight,
            'expenses' => $expenses,
            'expensesByDesc' => $expensesByDesc,
            'paymentExpenses' => $paymentExpenses,
            'courierLoss' => $courierLoss,
            'companyFees' => $companyFees,
            'cardsCost' => $cardsCost,
            'cardSales' => $cardSales,
            'net' => $net,
            'collected' => $collected,
            'partners' => $partners,
            'exchange' => $exchange,
        ];
    }

    /** أرصدة كل الخزائن: [payment_id => ['name'=>..., 'dinar'=>.., 'dollar'=>.., 'euro'=>..]] */
    public static function balances(): array
    {
        $out = [];
        foreach (PaymentMethod::orderBy('id')->get() as $p) {
            $row = ['name' => $p->name];
            foreach (array_keys(TreasuryService::CURRENCIES) as $c) {
                $row[$c] = TreasuryService::balance($c, $p->id);
            }
            $out[$p->id] = $row;
        }

        return $out;
    }

    /** الديون على العملاء (المتبقي مع الشحن) */
    public static function receivables(): array
    {
        $rows = Order::query()->where('status', '!=', 'canceld')
            ->selectRaw("COALESCE(currency,'dinar') as currency, SUM(total_price + COALESCE(ship_price,0) - COALESCE(discount,0) - paid_price) as total, COUNT(*) as cnt")
            ->whereRaw('total_price + COALESCE(ship_price,0) - COALESCE(discount,0) - paid_price > 0.009')
            ->groupBy(DB::raw("COALESCE(currency,'dinar')"))->get();

        return ['amounts' => self::byCurrency($rows), 'count' => (int) $rows->sum('cnt')];
    }

    /** إيصالات لم دخلتش في رحلة شراء */
    public static function pendingPurchases()
    {
        $codes = TripPurchase::pluck('trip_code');

        return Order::with('customer')->where('status', '!=', 'canceld')->where('delivered', false)
            ->where(function ($q) use ($codes) {
                $q->whereNull('trip_details')->orWhere('trip_details', '')->orWhereNotIn('trip_details', $codes);
            })
            ->latest()->take(15)->get();
    }

    /** طلبات وصلت وما تسلمتش وعليها أموال */
    public static function arrivedUnpaid()
    {
        return Order::with('customer')->where('arrived', true)->where('delivered', false)
            ->where('status', '!=', 'canceld')
            ->whereRaw('total_price + COALESCE(ship_price,0) - COALESCE(discount,0) - paid_price > 0.009')
            ->latest('arrived_at')->take(15)->get();
    }

    /** الفائض الذي لم يُقسّم: الربح الصافي من البداية - سحوبات وتوزيعات الشركاء */
    public static function undistributedSurplus(): array
    {
        // الفائض يتحسب من تاريخ بداية (افتراضياً تاريخ رحلة B-0393) + رصيد افتتاحي يحطه المدير
        $from = self::surplusFrom();
        $all = self::period($from, null);
        $drawn = self::byCurrency(
            PartnerTransaction::where(fn ($q) => $q->whereIn('type', ['distribution'])->orWhere(fn ($w) => $w->where('type', 'settle')->where('via', 'distribution'))) // السحب اليومي صار دين يتخصم من النصيب (ما ينقصش الفائض مرتين)
                ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
                ->selectRaw('currency, SUM(amount) as total')->groupBy('currency')->get()
        );
        $opening = (float) \App\Services\AppSettings::get('surplus_opening', 0);
        $out = [];
        foreach ($all['net'] as $c => $v) {
            $open = $c === 'dinar' ? $opening : 0;
            $out[$c] = ['net' => $v + $open, 'drawn' => $drawn[$c], 'surplus' => $v + $open - $drawn[$c], 'from' => $from, 'opening' => $open];
        }

        return $out;
    }

    /** تاريخ بداية حساب الفائض */
    public static function surplusFrom(): ?string
    {
        $d = \App\Services\AppSettings::get('surplus_from');
        if ($d) {
            return (string) $d;
        }
        $anchor = TripPurchase::where('trip_code', \App\Services\AppSettings::get('profit_from_trip', 'B-0393'))->value('purchased_at');

        return $anchor ? \Carbon\Carbon::parse($anchor)->toDateString() : null;
    }
}
