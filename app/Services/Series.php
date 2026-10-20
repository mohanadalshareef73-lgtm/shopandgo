<?php

namespace App\Services;

use App\Models\TreasuryHistory;
use Illuminate\Database\Eloquent\Builder;

/** سلاسل أرقام للرسوم (حسب الساعة / اليوم / الشهر) */
class Series
{
    public static function of(Builder $q, string $dateCol, string $agg, Period $p, bool $prev = false): array
    {
        $keys = $p->buckets($prev);
        $from = $prev ? $p->prevFrom : $p->from;
        $to = $prev ? $p->prevTo : $p->to;
        $rows = (clone $q)->whereBetween($dateCol, [$from, $to])
            ->selectRaw($p->sqlBucket($dateCol) . ' as k, ' . $agg . ' as v')
            ->groupByRaw($p->sqlBucket($dateCol))->pluck('v', 'k');

        return array_map(fn ($k) => round((float) ($rows[$k] ?? 0), 2), $keys);
    }

    /** كم نقطة من الفترة الحالية فاتت (لكي الخط ما يكملش للمستقبل) */
    public static function elapsed(Period $p): int
    {
        $keys = $p->buckets();
        $nowKey = match ($p->unit) {
            'hour' => now()->format('Y-m-d H'),
            'month' => now()->format('Y-m'),
            default => now()->format('Y-m-d'),
        };
        $i = array_search($nowKey, $keys, true);

        return $i === false ? count($keys) : $i + 1;
    }

    /** رصيد خزينة آخر $n يوم (يرجع من الرصيد الحالي للخلف) */
    public static function balanceDays(callable $filter, float $current, int $n = 14): array
    {
        $from = now()->subDays($n - 1)->startOfDay();
        $q = TreasuryHistory::query()->where('created_at', '>=', $from)->whereIn('status', ['pay', 'pull']);
        $filter($q);
        $driver = \Illuminate\Support\Facades\DB::getDriverName();
        $day = $driver === 'sqlite' ? "strftime('%Y-%m-%d', created_at)" : "DATE(created_at)";
        $net = $q->selectRaw("$day as d, SUM(CASE WHEN status = 'pay' THEN price ELSE -price END) as v")->groupByRaw($day)->pluck('v', 'd');
        $out = [];
        $bal = $current;
        for ($i = 0; $i < $n; $i++) {
            $d = now()->subDays($i)->format('Y-m-d');
            array_unshift($out, round($bal, 2));
            $bal -= (float) ($net[$d] ?? 0);
        }

        return $out;
    }
}
