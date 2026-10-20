<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Log;
use App\Models\Order;
use App\Models\PaidHistory;
use App\Models\PartnerTransaction;
use App\Models\TripPurchase;
use App\Models\User;
use App\Services\TreasuryService as TS;

/** حسابات التقرير الشامل (اليومي/الشهري) — تستخدمه صفحة التقارير والإرسال الشهري */
class ReportService
{
    public static function summary(Period $p): array
    {
        $from = $p->from->toDateString();
        $to = $p->to->toDateTimeString();
        $r = FinanceReport::period($from, $to);
        $prev = FinanceReport::period($p->prevFrom->toDateString(), $p->prevTo->toDateTimeString());
        $live = fn () => Order::where('status', '!=', 'canceld');

        $orders = $live()->whereBetween('created_at', [$p->from, $p->to]);
        $ordersN = (clone $orders)->count();
        $ordersPrev = $live()->whereBetween('created_at', [$p->prevFrom, $p->prevTo])->count();
        $ordersValue = (float) (clone $orders)->sum('total_price');

        $collected = (float) PaidHistory::whereBetween('created_at', [$p->from, $p->to])->sum('paid_price');
        $usdBuy = $r['exchange'];
        $partners = (float) PartnerTransaction::whereIn('type', ['withdrawal', 'distribution'])->where('currency', 'dinar')->whereBetween('date', [$from, $to])->sum('amount');
        $moneyDiff = $collected - (float) $usdBuy->paid - $r['shipCost']['dinar'] - $r['expenses']['dinar'] - $partners;

        $delivered = Order::where('delivered', true)->whereBetween('delivered_at', [$from, $to])->get(['received_at', 'delivered_time', 'delivered_at']);
        $shelf = $delivered->filter(fn ($o) => $o->received_at && $o->delivered_time)->map(fn ($o) => $o->received_at->diffInHours($o->delivered_time) / 24);

        $trips = TripPurchase::countable()->with('orders')->whereBetween('purchased_at', [$from, $to])->get();
        $best = $trips->sortByDesc(fn ($t) => $t->summary()['profit_dinar'])->first();
        $plat = (clone $orders)->with('platform')->get()->groupBy(fn ($o) => optional($o->platform)->name ?: '—')->map->count()->sortDesc();
        $topUser = Log::whereBetween('created_at', [$p->from, $p->to])->whereNotNull('user_id')->selectRaw('user_id, COUNT(*) n')->groupBy('user_id')->orderByDesc('n')->first();
        $topCust = (clone $orders)->selectRaw('customer_id, SUM(total_price) s')->groupBy('customer_id')->orderByDesc('s')->first();

        $recv = FinanceReport::receivables()['amounts']['dinar'];
        $oldDebt = $live()->whereRaw(\App\Http\Controllers\Sg\OrdersController::debtSql())->where('created_at', '<', now()->subDays(30))->get()->sum(fn ($o) => $o->remaining());
        $surplus = FinanceReport::undistributedSurplus()['dinar']['surplus'];
        $usdAvg = $r['cost']['dinar'] && $trips->where('cost_currency', 'dollar')->sum('cost') ? $r['cost']['dinar'] / max(1, $trips->sum(fn ($t) => $t->cost_currency === 'dollar' ? $t->cost : 0)) : null;

        $out = [
            'period' => $p, 'r' => $r, 'prev' => $prev,
            'orders' => $ordersN, 'ordersPrev' => $ordersPrev, 'ordersValue' => $ordersValue,
            'net' => $r['net']['dinar'], 'netPrev' => $prev['net']['dinar'],
            'receivables' => $recv, 'oldDebt' => $oldDebt, 'surplus' => $surplus,
            'collected' => $collected, 'usdPaid' => (float) $usdBuy->paid, 'usdGot' => (float) $usdBuy->got, 'partners' => $partners, 'moneyDiff' => $moneyDiff,
            'usdSpent' => $trips->sum(fn ($t) => $t->cost_currency === 'dollar' ? $t->cost : 0), 'usdAvg' => $usdAvg,
            'newCustomers' => Customer::whereBetween('created_at', [$p->from, $p->to])->count(),
            'trips' => $trips->count(),
            'arrived' => Order::whereBetween('received_at', [$p->from, $p->to])->count(),
            'delivered' => $delivered->count(),
            'avgShelf' => $shelf->count() ? round($shelf->avg(), 1) : null,
            'bestTrip' => $best ? [$best->trip_code, $best->summary()['profit_dinar']] : null,
            'topPlatform' => $plat->count() ? [$plat->keys()->first(), $ordersN ? round($plat->first() / $ordersN * 100) : 0] : null,
            'topUser' => $topUser ? [optional(User::find($topUser->user_id))->name, $topUser->n] : null,
            'topCustomer' => $topCust ? [optional(Customer::find($topCust->customer_id))->name, $topCust->s] : null,
            'margin' => $r['sales']['dinar'] ? round($r['goodsProfit']['dinar'] / $r['sales']['dinar'] * 100, 1) : 0,
            'marginPrev' => $prev['sales']['dinar'] ? round($prev['goodsProfit']['dinar'] / $prev['sales']['dinar'] * 100, 1) : 0,
        ];
        $out['explain'] = self::explain($out);

        return $out;
    }

    /** شرح التقرير بالكلام */
    public static function explain(array $s): array
    {
        $p = $s['period'];
        $lines = [];
        $o = Period::pct($s['orders'], $s['ordersPrev']);
        $n = Period::pct($s['net'], $s['netPrev']);
        $lines[] = $p->label . ' الطلبات <b>' . ($o >= 0 ? 'زادت ' : 'نقصت ') . number_format(abs($o), 1) . '%</b> عن ' . $p->prevLabel
            . '، و<b>الربح ' . ($n >= 0 ? 'زاد ' : 'نقص ') . number_format(abs($n), 1) . '%</b>'
            . ($s['margin'] && $s['marginPrev'] ? ' (نسبة ربح الرحلات ' . $s['margin'] . '% بدل ' . $s['marginPrev'] . '%).' : '.');
        if ($s['receivables'] > 0) {
            $lines[] = 'الديون عند العملاء <b>' . TS::fmt($s['receivables']) . ' د.ل</b>' . ($s['oldDebt'] > 0 ? '، منها <b>' . TS::fmt($s['oldDebt']) . ' د.ل عمرها أكثر من شهر</b>. يستاهل ترسل تذكير.' : '.');
        }
        if ($s['r']['shipIncome']['dinar'] - $s['r']['shipCost']['dinar'] < 0) {
            $lines[] = '⚠ الشحن خسران: دفعنا لشركة الشحن أكثر من الذي قبضناه من العملاء.';
        }
        $lines[] = 'الفائض الذي لم يُقسّم بين الشركاء <b>' . TS::fmt($s['surplus']) . ' د.ل</b>.';

        return $lines;
    }

    /** نص قصير للواتساب */
    public static function whatsappText(array $s): string
    {
        $tpl = MessageService::template('monthly_report');
        $vars = [
            '{الشهر}' => $s['period']->label, '{الطلبات}' => number_format($s['orders']), '{صافي_الربح}' => TS::fmt($s['net']),
            '{الديون}' => TS::fmt($s['receivables']), '{الفائض}' => TS::fmt($s['surplus']),
        ];
        $text = $tpl ? MessageService::render($tpl->message, $vars) : "التقرير — {$vars['{الشهر}']}\nالطلبات: {$vars['{الطلبات}']}\nصافي الربح: {$vars['{صافي_الربح}']} د.ل";

        return $text . "\n\n" . strip_tags(implode("\n", $s['explain']));
    }
}
