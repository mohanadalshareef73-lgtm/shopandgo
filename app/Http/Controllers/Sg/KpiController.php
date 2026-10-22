<?php

namespace App\Http\Controllers\Sg;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** 📊 أرقام نتابعوها كل أسبوع: الطلبيات، الزبائن اللي رجعو، التأخير، النواقص، سرعة الرد، التقييمات، مصدر الزبائن */
class KpiController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->hasRole('Admin') || auth()->user()->can('reports-list'), 403);
        $n = in_array((int) $request->w, [4, 8, 12], true) ? (int) $request->w : 8;
        $late = (int) \App\Services\AppSettings::get('kpi_late_days', 21);
        if ($request->boolean('fresh')) {
            Cache::forget('sg.kpi.' . $n . '.' . $late);
        }
        $weeks = Cache::remember('sg.kpi.' . $n . '.' . $late, 600, function () use ($n, $late) {
            // الأسبوع يبدا السبت
            $start = Carbon::now()->startOfWeek(Carbon::SATURDAY);
            $out = [];
            for ($i = 0; $i < $n; $i++) {
                $from = $start->copy()->subWeeks($i);
                $to = $from->copy()->endOfWeek(Carbon::FRIDAY);
                $out[] = $this->week($from, $to, $late);
            }

            return $out;
        });

        return view('sg.kpi.index', ['nav' => 'kpi', 'weeks' => $weeks, 'n' => $n, 'late' => $late]);
    }

    protected function week(Carbon $from, Carbon $to, int $late): array
    {
        $f = $from->toDateTimeString();
        $t = $to->toDateTimeString();
        $live = fn () => Order::where('status', '!=', 'canceld');
        $r = ['label' => $from->format('j/n') . ' – ' . $to->format('j/n'), 'from' => $from->toDateString(), 'cur' => $to->isFuture()];

        $r['orders'] = $live()->whereBetween('created_at', [$f, $t])->count();
        $r['value'] = round((float) $live()->whereBetween('created_at', [$f, $t])->sum(DB::raw('total_price - COALESCE(discount,0)')), 0);
        $r['delivered'] = $live()->where('delivered', true)->whereBetween('delivered_at', [$from->toDateString(), $to->toDateString()])->count();

        // 👥 زبائن جدد (أول طلبية هذا الأسبوع) ورجعو (عندهم طلبية قبل الأسبوع)
        $buyers = $live()->whereBetween('created_at', [$f, $t])->distinct()->pluck('customer_id')->filter();
        $returning = $buyers->isEmpty() ? 0 : $live()->whereIn('customer_id', $buyers)->where('created_at', '<', $f)->distinct()->count('customer_id');
        $r['buyers'] = $buyers->count();
        $r['returning'] = $returning;
        $r['new_buyers'] = $buyers->count() - $returning;
        $r['returning_pct'] = $buyers->count() ? round($returning / $buyers->count() * 100) : 0;

        // ⏱ متوسط أيام التوصيل (من الإيصال لين التسليم) + نسبة التأخير (وصلت بعد أكثر من X يوم)
        $avgDays = $live()->where('delivered', true)->whereBetween('delivered_at', [$from->toDateString(), $to->toDateString()])->whereNotNull('delivered_at')
            ->selectRaw('AVG(DATEDIFF(delivered_at, created_at)) d')->value('d');
        $r['avg_days'] = $avgDays !== null ? round((float) $avgDays, 1) : null;
        $arrived = $live()->whereNotNull('arrived_at')->whereBetween('arrived_at', [$from->toDateString(), $to->toDateString()]);
        $arrN = (clone $arrived)->count();
        $lateN = (clone $arrived)->whereRaw('DATEDIFF(arrived_at, created_at) > ?', [$late])->count();
        $r['late_pct'] = $arrN ? round($lateN / $arrN * 100) : null;
        $r['late_n'] = $lateN;

        // 📉 النواقص والمشاكل
        $r['missing'] = Schema::hasTable('order_issues') ? DB::table('order_issues')->where('type', 'missing')->whereBetween('created_at', [$f, $t])->count() : 0;
        $r['missing_pct'] = $r['delivered'] ? round($r['missing'] / $r['delivered'] * 100, 1) : null;
        $r['issues'] = Schema::hasTable('order_issues') ? DB::table('order_issues')->whereBetween('created_at', [$f, $t])->count() : 0;

        // 💬 سرعة الرد على السلات في الواتساب (دقايق، من وصول السلة لين الرد بالسعر)
        $r['reply_min'] = null;
        $r['carts'] = 0;
        if (Schema::hasTable('cart_quotes')) {
            $r['carts'] = DB::table('cart_quotes')->whereBetween('created_at', [$f, $t])->count();
            $m = DB::table('cart_quotes')->whereBetween('created_at', [$f, $t])->whereNotNull('notified_at')
                ->whereRaw('TIMESTAMPDIFF(MINUTE, created_at, notified_at) BETWEEN 0 AND 1440')->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, notified_at)) s')->value('s');
            $r['reply_min'] = $m !== null ? round((float) $m / 60, 1) : null;
            $r['carts_booked'] = Schema::hasColumn('cart_quotes', 'order_id') ? DB::table('cart_quotes')->whereBetween('created_at', [$f, $t])->whereNotNull('order_id')->count() : 0;
        }

        // ⭐ التقييمات
        $r['reviews'] = 0;
        $r['rating'] = null;
        if (Schema::hasTable('company_reviews')) {
            $rv = DB::table('company_reviews')->whereBetween('created_at', [$f, $t]);
            $r['reviews'] = (clone $rv)->count();
            $r['rating'] = $r['reviews'] ? round((float) (clone $rv)->avg('rating'), 1) : null;
        }

        // 🧭 مصدر الزبائن الجدد (اللي تسجلو هذا الأسبوع)
        $newC = Customer::withoutGlobalScope('approved')->whereBetween('created_at', [$f, $t]);
        $r['new_customers'] = (clone $newC)->count();
        $r['src_referral'] = Schema::hasColumn('customers', 'referred_by') ? (clone $newC)->whereNotNull('referred_by')->count() : 0;
        $r['src_signup'] = Schema::hasColumn('customers', 'password') ? (clone $newC)->when(Schema::hasColumn('customers', 'referred_by'), fn ($q) => $q->whereNull('referred_by'))->whereNotNull('password')->count() : 0;
        $r['src_staff'] = max(0, $r['new_customers'] - $r['src_referral'] - $r['src_signup']);

        return $r;
    }
}
