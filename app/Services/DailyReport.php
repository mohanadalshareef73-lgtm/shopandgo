<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\PaidHistory;
use App\Models\Purchase;
use App\Http\Controllers\Sg\OrdersController;
use Illuminate\Support\Facades\Schema;

/** التقرير اليومي الذي يمشي على الواتساب لأرقام الإدارة */
class DailyReport
{
    public static function data(?\Carbon\Carbon $day = null): array
    {
        $day = $day ?: now();
        $from = $day->copy()->startOfDay();
        $to = $day->copy()->endOfDay();
        $new = Order::whereBetween('created_at', [$from, $to])->where('status', '!=', 'canceld')->get();
        $delivered = Order::where('delivered', true)->whereDate('delivered_at', $day->toDateString())->count();
        $paids = PaidHistory::with('paymentMethod')->whereBetween('created_at', [$from, $to])->get();
        $expenses = (float) Purchase::whereDate('date', $day->toDateString())->where(fn ($q) => $q->whereNull('currency')->orWhere('currency', 'dinar'))->sum('cost');
        $ready = Order::where('arrived', true)->where('delivered', false)->where('status', '!=', 'canceld')->get();
        $oldShelf = $ready->filter(fn ($o) => $o->shelfDays() !== null && $o->shelfDays() > (int) AppSettings::get('shelf_reminder_days', 5))->count();
        $debts = Order::where('status', '!=', 'canceld')->whereRaw(OrdersController::debtSql())->get()->sum(fn ($o) => max(0, $o->remaining()));
        $couriers = 0.0;
        $problems = 0;
        if (Schema::hasTable('deliveries')) {
            foreach (CourierService::couriers() as $c) {
                $couriers += CourierService::balance($c)['expected'];
            }
            $problems = Delivery::where('status', 'problem')->count();
        }
        $returns = Schema::hasTable('order_returns') ? OrderReturn::whereBetween('created_at', [$from, $to])->get() : collect();

        return [
            'day' => $day, 'newCount' => $new->count(), 'newValue' => $new->sum(fn ($o) => $o->grandTotal()),
            'delivered' => $delivered, 'collected' => $paids->sum('paid_price'),
            'byPayment' => $paids->groupBy(fn ($p) => optional($p->paymentMethod)->name ?: '—')->map->sum('paid_price'),
            'expenses' => $expenses, 'balances' => FinanceReport::balances(),
            'shelf' => $ready->count(), 'oldShelf' => $oldShelf, 'debts' => $debts, 'couriers' => $couriers, 'problems' => $problems,
            'returns' => $returns->count(), 'returnsValue' => $returns->sum('amount'),
        ];
    }

    public static function text(?\Carbon\Carbon $day = null): string
    {
        $d = static::data($day);
        $f = fn ($n) => TreasuryService::fmt($n);
        $days = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
        $L = [];
        $L[] = '📊 تقرير اليوم · ' . $days[$d['day']->dayOfWeek] . ' ' . $d['day']->format('j/n');
        $L[] = '';
        $L[] = '🧾 إيصالات جديدة: ' . $d['newCount'] . ' (' . $f($d['newValue']) . ' د.ل)';
        $L[] = '✅ تسلّموا: ' . $d['delivered'];
        $L[] = '💵 قبضنا: ' . $f($d['collected']) . ' د.ل';
        foreach ($d['byPayment'] as $name => $sum) {
            $L[] = '   • ' . $name . ': ' . $f($sum);
        }
        if ($d['expenses'] > 0) {
            $L[] = '🧾 مصروفات: ' . $f($d['expenses']) . ' د.ل';
        }
        if ($d['returns']) {
            $L[] = '↩ مرتجعات: ' . $d['returns'] . ' (' . $f($d['returnsValue']) . ' د.ل)';
        }
        $L[] = '';
        $L[] = '🏦 الخزائن:';
        foreach ($d['balances'] as $b) {
            $parts = [];
            foreach (['dinar' => 'د.ل', 'dollar' => '$', 'euro' => '€'] as $k => $s) {
                if (abs($b[$k] ?? 0) > 0.009) {
                    $parts[] = $f($b[$k]) . ' ' . $s;
                }
            }
            if ($parts) {
                $L[] = '   • ' . $b['name'] . ': ' . implode(' · ', $parts);
            }
        }
        if ($d['couriers'] > 0.009) {
            $L[] = '🛵 عند المندوبين: ' . $f($d['couriers']) . ' د.ل';
        }
        $L[] = '';
        $L[] = '📦 في الرف: ' . $d['shelf'] . ($d['oldShelf'] ? ' (' . $d['oldShelf'] . ' طولوا)' : '');
        $L[] = '💰 ديون العملاء: ' . $f($d['debts']) . ' د.ل';
        if ($d['problems']) {
            $L[] = '⚠ مشاكل توصيل مفتوحة: ' . $d['problems'];
        }
        $L[] = '';
        $L[] = AppSettings::get('shop_name', 'Shop & Go');

        return implode("\n", $L);
    }
}
