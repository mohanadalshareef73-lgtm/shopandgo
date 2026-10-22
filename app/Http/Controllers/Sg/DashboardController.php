<?php

namespace App\Http\Controllers\Sg;

use App\Http\Controllers\Controller;
use App\Models\CurrencyExchange;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PaidHistory;
use App\Models\PartnerTransaction;
use App\Models\PaymentMethod;
use App\Models\TreasuryHistory;
use App\Models\TripPurchase;
use App\Services\AppSettings;
use App\Services\FinanceReport;
use App\Services\Period;
use App\Services\Series;
use App\Services\TreasuryService;
use Illuminate\Http\Request;

/** الواجهة الرئيسية: تقرير بنظام البورصة */
class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $p = Period::make($request->input('p', 'day'));
        // ⚡ أرقام التقرير تتحسب مرة كل دقيقتين لنفس الفترة بدل كل فتحة (الصفحة كانت تقيلة)
        $compute = function () use ($p, $request) {
            $orders = Order::query()->where('status', '!=', 'canceld')->where(fn ($q) => $q->whereNull('currency')->orWhere('currency', 'dinar'));

            // ---------------- الطلبات
            $ordCount = (clone $orders)->whereBetween('created_at', [$p->from, $p->to])->count();
            $ordCountPrev = (clone $orders)->whereBetween('created_at', [$p->prevFrom, $p->prevTo])->count();
            $ordValue = (float) (clone $orders)->whereBetween('created_at', [$p->from, $p->to])->sum('total_price');
            $ordValuePrev = (float) (clone $orders)->whereBetween('created_at', [$p->prevFrom, $p->prevTo])->sum('total_price');
            $valueSeries = Series::of($orders, 'created_at', 'SUM(total_price)', $p);
            $valueSeriesPrev = Series::of($orders, 'created_at', 'SUM(total_price)', $p, true);
            $countSeries = Series::of($orders, 'created_at', 'COUNT(*)', $p);
            $paidQ = PaidHistory::query();
            $collected = (float) (clone $paidQ)->whereBetween('created_at', [$p->from, $p->to])->sum('paid_price');
            $collectedPrev = (float) (clone $paidQ)->whereBetween('created_at', [$p->prevFrom, $p->prevTo])->sum('paid_price');
            $collectedSeries = Series::of($paidQ, 'created_at', 'SUM(paid_price)', $p);
            $avg = $ordCount ? $ordValue / $ordCount : 0;
            $avgPrev = $ordCountPrev ? $ordValuePrev / $ordCountPrev : 0;

            // ---------------- رأس المال
            $cashId = AppSettings::cashPaymentId();
            $cash = TreasuryService::balance('dinar', $cashId);
            $bank = 0.0;
            foreach (PaymentMethod::where('id', '!=', $cashId)->get() as $pm) {
                $bank += TreasuryService::balance('dinar', $pm->id);
            }
            $visa = 0.0;
            foreach (PaymentMethod::all() as $pm) {
                $visa += TreasuryService::balance('dollar', $pm->id);
            }
            $rate = CurrencyExchange::latestRate() ?: (float) (\App\Models\Setting::query()->value('usd_to_lyd_rate') ?: 0);
            $flowSince = function ($filter) use ($p) {
                $q = TreasuryHistory::where('created_at', '>=', $p->from)->whereIn('status', ['pay', 'pull']);
                $filter($q);

                return (float) $q->selectRaw("SUM(CASE WHEN status = 'pay' THEN price ELSE -price END) as v")->value('v');
            };
            $cashFilter = fn ($q) => $q->where('currency', 'dinar')->where('payment_id', $cashId);
            $bankFilter = fn ($q) => $q->where('currency', 'dinar')->where('payment_id', '!=', $cashId);
            $visaFilter = fn ($q) => $q->where('currency', 'dollar');
            $cashPrev = $cash - $flowSince($cashFilter);
            $bankPrev = $bank - $flowSince($bankFilter);
            $visaPrev = $visa - $flowSince($visaFilter);
            $rec = FinanceReport::receivables();
            $debts = $rec['amounts']['dinar'];
            $debtCustomers = Order::where('status', '!=', 'canceld')
                ->whereRaw('total_price + COALESCE(ship_price,0) - COALESCE(discount,0) - paid_price > 0.009')->distinct('customer_id')->count('customer_id');
            $newSales = (float) Order::where('status', '!=', 'canceld')->where('created_at', '>=', $p->from)->sum('total_price');
            $debtsPrev = max(0, $debts - $newSales + $collected);
            $capital = $cash + $bank + $visa * $rate + $debts;
            $capitalPrev = $cashPrev + $bankPrev + $visaPrev * $rate + $debtsPrev;

            // ---------------- الحالة المالية
            $fin = FinanceReport::period($p->from->toDateString(), $p->to->toDateTimeString());
            $partnersOut = (float) PartnerTransaction::whereIn('type', ['withdrawal', 'distribution'])->where('currency', 'dinar')
                ->whereBetween('date', [$p->from->toDateString(), $p->to->toDateTimeString()])->sum('amount');
            $net = $fin['net']['dinar'];
            $finState = $net > $partnersOut ? ['✓ جيدة', 'u'] : ($net > 0 ? ['! انتبه', 'w'] : ['✕ خسارة', 'd']);

            // ---------------- النشاط
            $cust = Customer::query();
            $act = [];
            $act['customers'] = [(clone $cust)->whereBetween('created_at', [$p->from, $p->to])->count(), (clone $cust)->whereBetween('created_at', [$p->prevFrom, $p->prevTo])->count(), Series::of($cust, 'created_at', 'COUNT(*)', $p)];
            $act['orders'] = [$ordCount, $ordCountPrev, $countSeries];
            $delQ = Order::where('delivered', true);
            $act['delivered'] = [(clone $delQ)->whereBetween('delivered_at', [$p->from->toDateString(), $p->to->toDateTimeString()])->count(), (clone $delQ)->whereBetween('delivered_at', [$p->prevFrom->toDateString(), $p->prevTo->toDateTimeString()])->count(), Series::of($delQ, 'delivered_at', 'COUNT(*)', $p)];
            $arrQ = Order::where('arrived', true);
            $act['arrived'] = [(clone $arrQ)->whereBetween('arrived_at', [$p->from->toDateString(), $p->to->toDateTimeString()])->count(), (clone $arrQ)->whereBetween('arrived_at', [$p->prevFrom->toDateString(), $p->prevTo->toDateTimeString()])->count(), Series::of($arrQ, 'arrived_at', 'COUNT(*)', $p)];
            $tq = TripPurchase::query();
            $act['trips'] = [(clone $tq)->whereBetween('purchased_at', [$p->from->toDateString(), $p->to->toDateTimeString()])->count(), (clone $tq)->whereBetween('purchased_at', [$p->prevFrom->toDateString(), $p->prevTo->toDateTimeString()])->count(), Series::of($tq, 'purchased_at', 'COUNT(*)', $p)];

            $chart = [
                'labels' => $p->bucketLabels(),
                'cur' => $valueSeries,
                'prev' => $valueSeriesPrev,
                'curCount' => Series::elapsed($p),
                'curName' => $p->label,
                'prevName' => $p->prevLabel,
            ];
            $sparkCount = array_slice($countSeries, 0, Series::elapsed($p));
            $sparks = [
                'cash' => Series::balanceDays($cashFilter, $cash),
                'bank' => Series::balanceDays($bankFilter, $bank),
                'visa' => Series::balanceDays($visaFilter, $visa),
            ];

            return ['ordCount' => $ordCount, 'ordCountPrev' => $ordCountPrev, 'ordValue' => $ordValue, 'ordValuePrev' => $ordValuePrev,
                'avg' => $avg, 'avgPrev' => $avgPrev, 'collected' => $collected, 'collectedPrev' => $collectedPrev,
                'chart' => $chart, 'sparkCount' => $sparkCount, 'collectedSeries' => array_slice($collectedSeries, 0, Series::elapsed($p)),
                'cash' => $cash, 'cashPrev' => $cashPrev, 'bank' => $bank, 'bankPrev' => $bankPrev, 'visa' => $visa, 'visaPrev' => $visaPrev, 'rate' => $rate,
                'debts' => $debts, 'debtsPrev' => $debtsPrev, 'debtCustomers' => $debtCustomers, 'capital' => $capital, 'capitalPrev' => $capitalPrev,
                'fin' => $fin, 'net' => $net, 'partnersOut' => $partnersOut, 'finState' => $finState, 'act' => $act, 'sparks' => $sparks];
        };
        try {
            $D = \Illuminate\Support\Facades\Cache::remember('sg.dash.' . md5($p->from . '|' . $p->to . '|' . $request->input('p', 'day')), 120, $compute);
        } catch (\Throwable $e) {
            report($e);
            $D = $compute(); // لو الكاش ما مشاش نحسبو عادي
        }
        extract($D);
        return view('sg.dashboard', [
            'nav' => 'home', 'period' => $p,
            'ordCount' => $ordCount, 'ordCountPrev' => $ordCountPrev, 'ordValue' => $ordValue, 'ordValuePrev' => $ordValuePrev,
            'avg' => $avg, 'avgPrev' => $avgPrev, 'collected' => $collected, 'collectedPrev' => $collectedPrev,
            'chart' => $chart, 'sparkCount' => $sparkCount, 'collectedSeries' => $collectedSeries,
            'cash' => $cash, 'cashPrev' => $cashPrev, 'bank' => $bank, 'bankPrev' => $bankPrev, 'visa' => $visa, 'visaPrev' => $visaPrev, 'rate' => $rate,
            'debts' => $debts, 'debtsPrev' => $debtsPrev, 'debtCustomers' => $debtCustomers, 'capital' => $capital, 'capitalPrev' => $capitalPrev,
            'staffAct' => ($isAdmin = auth()->user()->hasRole('Admin')) ? \App\Support\StaffActivity::for($p->from, $p->to) : [],
            'myAct' => $isAdmin ? null : collect(\App\Support\StaffActivity::for($p->from, $p->to))->first(fn ($r) => $r['user']->id === auth()->id()),
            'myShift' => $isAdmin ? null : (function () { try { return \App\Services\ShiftService::summary(auth()->user()); } catch (\Throwable $e) { return null; } })(),
            'waiting' => $isAdmin ? [] : [
                'pcart' => \App\Models\CartQuote::where('portal_status', 'ordered')->count(),
                'bqueue' => \App\Support\Fast::hasColumn('cart_quotes', 'booked_done_at') ? \App\Models\CartQuote::whereNotNull('customer_id')->where('portal_status', 'confirmed')->whereNull('booked_done_at')->count() : 0,
                'ready' => Order::where('arrived', true)->where('delivered', false)->where('status', '!=', 'canceld')->count(),
                'tickets' => \Illuminate\Support\Facades\Schema::hasTable('support_tickets') ? \App\Models\SupportTicket::where('status', 'open')->count() : 0,
            ],
            'fin' => $fin, 'net' => $net, 'partnersOut' => $partnersOut, 'finState' => $finState, 'act' => $act, 'sparks' => $sparks,
        ]);
    }
}
