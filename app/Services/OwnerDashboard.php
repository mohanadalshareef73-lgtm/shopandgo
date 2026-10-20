<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Profit;
use App\Models\Treasury;
use App\Models\TreasuryDollar;
use App\Models\TreasuryEuro;
use App\Models\TreasuryHistory;
use App\Models\Trip;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class OwnerDashboard
{
    public function data(User $user, string $period, string $requestedQueue): array
    {
        $now = Carbon::now();
        $start = match ($period) {
            'today' => $now->copy()->startOfDay(),
            'week' => $now->copy()->subDays(6)->startOfDay(),
            default => $now->copy()->startOfMonth(),
        };
        $currencies = [
            'dinar' => ['name' => 'دينار ليبي', 'code' => 'LYD', 'symbol' => 'د.ل', 'decimals' => 3],
            'dollar' => ['name' => 'دولار أمريكي', 'code' => 'USD', 'symbol' => '$', 'decimals' => 2],
            'euro' => ['name' => 'يورو', 'code' => 'EUR', 'symbol' => '€', 'decimals' => 2],
        ];
        $ready = Order::where('arrived', true)->where('delivered', false);
        $unpaid = Order::whereIn('status', ['unpaid', 'rest']);
        $unassigned = Order::whereNull('trip_id')->where('updated_at', '<', $now->copy()->subHours(12));
        $lateTrips = Trip::where('arrive_date', '<', $now->toDateString())->where('arrived', false);
        $queues = [];
        if ($user->can('order-list')) {
            $queues = [
                'ready' => ['label' => 'جاهزة للتسليم', 'icon' => 'ti-package', 'count' => (clone $ready)->count(), 'route' => 'order.ready.index'],
                'unpaid' => ['label' => 'غير مدفوعة بالكامل', 'icon' => 'ti-receipt', 'count' => (clone $unpaid)->count(), 'route' => 'order.unpaid.index'],
                'no_trip' => ['label' => 'بدون رحلة · أكثر من ١٢ ساعة', 'icon' => 'ti-truck', 'count' => (clone $unassigned)->count(), 'route' => 'order.no_trip.index'],
            ];
        }
        if ($user->can('trip-list')) {
            $queues['late_trips'] = ['label' => 'رحلات متأخرة', 'icon' => 'ti-ship', 'count' => (clone $lateTrips)->count(), 'route' => 'trip.delay.index'];
        }
        $queue = array_key_exists($requestedQueue, $queues) ? $requestedQueue : (array_key_first($queues) ?? 'ready');
        $queueOrders = collect();
        $queueTrips = collect();
        if (isset($queues[$queue])) {
            if ($queue === 'late_trips') {
                $queueTrips = (clone $lateTrips)->oldest('arrive_date')->limit(6)->get();
            } else {
                $query = match ($queue) {
                    'unpaid' => clone $unpaid,
                    'no_trip' => clone $unassigned,
                    default => clone $ready,
                };
                $query->with('customer');
                if ($queue === 'ready') {
                    $query->orderByRaw('COALESCE(arrived_at, updated_at) ASC');
                } else {
                    $query->oldest('updated_at');
                }
                $queueOrders = $query->orderBy('id')->limit(6)->get();
            }
        }
        $dailyCounts = Order::whereBetween('created_at', [$start, $now])
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')->groupBy('day')->pluck('total', 'day');
        $days = collect(CarbonPeriod::create($start->copy()->startOfDay(), $now->copy()->startOfDay()))
            ->map(fn ($date) => ['label' => $date->format('d/m'), 'count' => (int) ($dailyCounts[$date->toDateString()] ?? 0)]);

        $finance = null;
        // Preserve the existing dashboard's owner-only financial access.
        if ($user->id === 1) {
            $receivables = (clone $unpaid)->selectRaw(
                'currency, COALESCE(SUM(COALESCE(total_price, 0) + COALESCE(ship_price, 0) - COALESCE(paid_price, 0)), 0) as amount'
            )->groupBy('currency')->pluck('amount', 'currency');
            $collections = TreasuryHistory::where('type', 'order')->where('status', 'pay')
                ->whereBetween('created_at', [$start, $now])->selectRaw('currency, SUM(price) as amount')
                ->groupBy('currency')->pluck('amount', 'currency');
            $orderValues = Order::where('status', '!=', 'canceld')->whereBetween('created_at', [$start, $now])
                ->selectRaw('currency, SUM(COALESCE(total_price, 0) + COALESCE(ship_price, 0)) as amount')
                ->groupBy('currency')->pluck('amount', 'currency');
            $profits = $user->can('profits-list')
                ? Profit::whereBetween('date', [$start->toDateString(), $now->toDateString()])
                    ->selectRaw('currency, SUM(cost) as amount')->groupBy('currency')->pluck('amount', 'currency')
                : null;
            $balances = ['dinar' => Treasury::sum('total'), 'dollar' => TreasuryDollar::sum('total'), 'euro' => TreasuryEuro::sum('total')];
            $finance = [];
            foreach ($currencies as $currency => $meta) {
                $finance[$currency] = [
                    'cash' => $balances[$currency], 'debt' => $receivables[$currency] ?? 0,
                    'collections' => $collections[$currency] ?? 0, 'orderValue' => $orderValues[$currency] ?? 0,
                    'recordedProfit' => $profits === null ? null : ($profits[$currency] ?? 0),
                ];
            }
        }

        return [
            'period' => $period, 'queue' => $queue, 'queues' => $queues, 'currencies' => $currencies,
            'periodLabel' => ['today' => 'اليوم', 'week' => 'آخر ٧ أيام', 'month' => 'هذا الشهر'][$period],
            'start' => $start, 'updatedAt' => $now, 'finance' => $finance,
            'newOrders' => Order::whereBetween('created_at', [$start, $now])->count(),
            'deliveries' => Order::where('delivered', true)->whereBetween('delivered_at', [$start->toDateString(), $now->toDateString()])->count(),
            'newCustomers' => Customer::whereBetween('created_at', [$start, $now])->count(),
            'activeOrders' => $user->can('order-list') ? Order::where('delivered', false)->where('status', '!=', 'canceld')->count() : null,
            'queueOrders' => $queueOrders, 'queueTrips' => $queueTrips,
            'days' => $days, 'chartMax' => max(1, $days->max('count')),
            'chartTickInterval' => max(1, (int) ceil($days->count() / 6)),
        ];
    }
}
