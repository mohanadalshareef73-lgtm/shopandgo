<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonPeriod;

/** الفترة المختارة (اليوم / الأسبوع / الشهر / السنة) والفترة التي قبلها للمقارنة */
class Period
{
    public string $preset;
    public Carbon $from;
    public Carbon $to;
    public Carbon $prevFrom;
    public Carbon $prevTo;
    public string $label;
    public string $prevLabel;
    public string $cmpLabel;
    public string $unit; // hour | day | month

    public const PRESETS = ['day' => 'اليوم', 'week' => 'الأسبوع', 'month' => 'الشهر', 'year' => 'السنة'];

    public const MONTHS = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];

    public const DAYS = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];

    public static function make(?string $preset, ?string $from = null, ?string $to = null): self
    {
        $p = new self;
        $now = now();
        $p->preset = array_key_exists((string) $preset, self::PRESETS) || $preset === 'custom' ? $preset : 'day';
        switch ($p->preset) {
            case 'week':
                $p->from = $now->copy()->startOfWeek(Carbon::SATURDAY);
                $p->to = $now->copy()->endOfDay();
                $p->prevFrom = $p->from->copy()->subWeek();
                $p->prevTo = $p->prevFrom->copy()->addDays($p->from->diffInDays($p->to))->endOfDay();
                $p->label = 'هذا الأسبوع';
                $p->prevLabel = 'الأسبوع السابق';
                $p->unit = 'day';
                break;
            case 'month':
                $p->from = $now->copy()->startOfMonth();
                $p->to = $now->copy()->endOfDay();
                $p->prevFrom = $p->from->copy()->subMonthNoOverflow()->startOfMonth();
                $p->prevTo = $p->prevFrom->copy()->addDays($p->from->diffInDays($p->to))->endOfDay();
                $p->label = self::MONTHS[$now->month - 1];
                $p->prevLabel = self::MONTHS[$p->prevFrom->month - 1];
                $p->unit = 'day';
                break;
            case 'year':
                $p->from = $now->copy()->startOfYear();
                $p->to = $now->copy()->endOfDay();
                $p->prevFrom = $p->from->copy()->subYear();
                $p->prevTo = $now->copy()->subYear()->endOfDay();
                $p->label = (string) $now->year;
                $p->prevLabel = (string) ($now->year - 1);
                $p->unit = 'month';
                break;
            case 'custom':
                $p->from = Carbon::parse($from ?: $now->toDateString())->startOfDay();
                $p->to = Carbon::parse($to ?: $p->from->toDateString())->endOfDay();
                $days = $p->from->diffInDays($p->to) + 1;
                $p->prevTo = $p->from->copy()->subDay()->endOfDay();
                $p->prevFrom = $p->prevTo->copy()->subDays($days - 1)->startOfDay();
                $p->label = $p->from->format('Y-m-d') . ' ← ' . $p->to->format('Y-m-d');
                $p->prevLabel = 'الفترة التي قبلها';
                $p->unit = $days <= 1 ? 'hour' : ($days > 62 ? 'month' : 'day');
                break;
            default:
                $p->from = $now->copy()->startOfDay();
                $p->to = $now->copy()->endOfDay();
                $p->prevFrom = $p->from->copy()->subDay();
                $p->prevTo = $p->prevFrom->copy()->endOfDay();
                $p->label = 'اليوم';
                $p->prevLabel = 'أمس';
                $p->unit = 'hour';
        }
        $p->cmpLabel = 'مقارنة بـ' . ($p->preset === 'day' ? 'أمس' : $p->prevLabel);

        return $p;
    }

    public function dateLabel(): string
    {
        $n = now();
        return match ($this->preset) {
            'day' => self::DAYS[$n->dayOfWeek] . ' ' . $n->day . ' ' . self::MONTHS[$n->month - 1] . ' ' . $n->year,
            'month' => self::MONTHS[$n->month - 1] . ' ' . $n->year,
            'year' => 'سنة ' . $n->year,
            default => $this->from->format('Y-m-d') . ' ← ' . $this->to->format('Y-m-d'),
        };
    }

    /** مفاتيح نقاط الرسم (ساعة / يوم / شهر) */
    public function buckets(bool $prev = false): array
    {
        $from = $prev ? $this->prevFrom : $this->from;
        $to = $prev ? $this->prevTo : $this->to;
        if ($this->unit === 'hour') {
            return array_map(fn ($h) => $from->format('Y-m-d') . ' ' . str_pad($h, 2, '0', STR_PAD_LEFT), range(0, 23));
        }
        if ($this->unit === 'month') {
            $out = [];
            for ($m = 1; $m <= 12; $m++) {
                $out[] = $from->year . '-' . str_pad($m, 2, '0', STR_PAD_LEFT);
            }

            return $out;
        }
        $end = $this->preset === 'month' && !$prev ? $from->copy()->endOfMonth() : $to;
        if ($this->preset === 'week' && !$prev) {
            $end = $from->copy()->addDays(6);
        }

        return array_map(fn ($d) => $d->format('Y-m-d'), iterator_to_array(CarbonPeriod::create($from->copy()->startOfDay(), $end->copy()->startOfDay())));
    }

    public function bucketLabels(): array
    {
        return array_map(function ($k) {
            if ($this->unit === 'hour') {
                $h = (int) substr($k, -2);
                return (string) ($h % 12 === 0 ? 12 : $h % 12);
            }
            if ($this->unit === 'month') {
                return mb_substr(self::MONTHS[(int) substr($k, 5, 2) - 1], 0, 3);
            }
            if ($this->preset === 'week') {
                return mb_substr(self::DAYS[Carbon::parse($k)->dayOfWeek], 2);
            }

            return (string) (int) substr($k, 8, 2);
        }, $this->buckets());
    }

    /** تعبير SQL يجمّع التاريخ حسب الوحدة (يعمل على MySQL و SQLite) */
    public function sqlBucket(string $col): string
    {
        $driver = \Illuminate\Support\Facades\DB::getDriverName();
        if ($driver === 'sqlite') {
            return match ($this->unit) {
                'hour' => "strftime('%Y-%m-%d %H', $col)",
                'month' => "strftime('%Y-%m', $col)",
                default => "strftime('%Y-%m-%d', $col)",
            };
        }

        return match ($this->unit) {
            'hour' => "DATE_FORMAT($col, '%Y-%m-%d %H')",
            'month' => "DATE_FORMAT($col, '%Y-%m')",
            default => "DATE_FORMAT($col, '%Y-%m-%d')",
        };
    }

    public static function pct($cur, $prev): ?float
    {
        if (!$prev) {
            return $cur ? 100.0 : 0.0;
        }

        return round(($cur - $prev) / abs($prev) * 100, 1);
    }
}
