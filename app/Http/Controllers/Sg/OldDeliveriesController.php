<?php

namespace App\Http\Controllers\Sg;

use App\Http\Controllers\Controller;
use App\Models\Log;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * تنظيف الطلبات القديمة: طلبات «وصلت ولم تُسلَّم» من المنظومة القديمة، تسلمت فعلاً لكنها لم تُعلَّم.
 * تُعلَّم «تم التسليم» فقط — لا تتحرك أي فلوس في الخزينة، والباقي على العميل يبقى ديناً كما هو.
 * للمدير فقط.
 */
class OldDeliveriesController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            abort_unless(auth()->user() && auth()->user()->hasRole('Admin'), 403, 'للمدير فقط');

            return $next($request);
        });
    }

    /** طلبات «جاهزة» (وصلت ولم تُسلَّم ولا عند مندوب) */
    protected function base()
    {
        return Order::where('arrived', true)->where('delivered', false)->where('status', '!=', 'canceld')->whereNull('delivery_id');
    }

    /** تاريخ الوصول، وإن لم يوجد فآخر تعديل */
    protected function dateExpr(): string
    {
        return 'COALESCE(arrived_at, DATE(updated_at))';
    }

    protected function query(string $before, string $which)
    {
        $q = $this->base()->whereRaw($this->dateExpr() . ' < ?', [$before]);

        return $q;
    }

    protected function filter($orders, string $which)
    {
        return $which === 'paid' ? $orders->filter(fn ($o) => $o->remaining() <= 0.009) : $orders;
    }

    public function index(Request $request)
    {
        $before = $request->input('before', now()->subDays(30)->toDateString());
        $which = $request->input('which') === 'paid' ? 'paid' : 'all';
        $all = $this->base()->get();
        $d = fn ($o) => Carbon::parse($o->arrived_at ?: $o->updated_at);
        $buckets = [
            'أكثر من 6 أشهر' => $all->filter(fn ($o) => $d($o)->lt(now()->subMonths(6)))->count(),
            'من 3 إلى 6 أشهر' => $all->filter(fn ($o) => $d($o)->lt(now()->subMonths(3)) && $d($o)->gte(now()->subMonths(6)))->count(),
            'من شهر إلى 3 أشهر' => $all->filter(fn ($o) => $d($o)->lt(now()->subMonth()) && $d($o)->gte(now()->subMonths(3)))->count(),
            'آخر شهر' => $all->filter(fn ($o) => $d($o)->gte(now()->subMonth()))->count(),
        ];
        $sel = $this->filter($this->query($before, $which)->with('customer')->orderByRaw($this->dateExpr())->get(), $which);

        return view('sg.delivery.old', [
            'nav' => 'del', 'before' => $before, 'which' => $which, 'total' => $all->count(), 'buckets' => $buckets,
            'sel' => $sel, 'selDebt' => $sel->sum(fn ($o) => max(0, $o->remaining())), 'selUnpaid' => $sel->filter(fn ($o) => $o->remaining() > 0.009)->count(),
        ]);
    }

    /** طلبات قديمة بدون رحلة ولم تُسلَّم: عمرها بين from و to يوم */
    protected function noTrip(int $from, int $to)
    {
        return Order::where('delivered', false)->where('status', '!=', 'canceld')->whereNull('delivery_id')
            ->where(fn ($w) => $w->whereNull('trip_details')->orWhere('trip_details', ''))
            ->where('created_at', '<=', now()->subDays($from))->where('created_at', '>=', now()->subDays($to)->startOfDay());
    }

    public function noTripIndex(Request $request)
    {
        $from = max(0, (int) $request->input('from', 30));
        $to = max($from, (int) $request->input('to', 9999));
        $sel = $this->noTrip($from, $to)->with('customer')->orderBy('created_at')->get();

        return view('sg.delivery.old', [
            'nav' => 'del', 'mode' => 'notrip', 'from' => $from, 'to' => $to, 'sel' => $sel, 'total' => $sel->count(), 'buckets' => [],
            'selDebt' => $sel->sum(fn ($o) => max(0, $o->remaining())), 'selUnpaid' => $sel->filter(fn ($o) => $o->remaining() > 0.009)->count(),
            'before' => null, 'which' => 'all',
        ]);
    }

    /** نجمعهم في رحلة واحدة «OLD-NO-TRIP» (قديمة، بدون أي حركة مالية)، الرحلة وصلت، والطلبات تتعلّم «تم التسليم». الديون تبقى. */
    public function noTripStore(Request $request)
    {
        $data = $request->validate(['from' => 'required|integer|min:0', 'to' => 'required|integer|min:0', 'expected' => 'required|integer|min:1', 'confirm' => 'accepted']);
        $orders = $this->noTrip((int) $data['from'], max((int) $data['from'], (int) $data['to']))->get();
        if ($orders->count() !== (int) $data['expected']) {
            return back()->with('error', 'العدد تغيّر منذ عرض الصفحة (' . $orders->count() . ' بدل ' . $data['expected'] . ') — راجع القائمة واضغط مرة أخرى');
        }
        $code = 'OLD-NO-TRIP';
        $status = \App\Models\Status::where('stage', 'arrived')->orderBy('sort')->value('name') ?: 'جاهز للاستلام';
        DB::transaction(function () use ($orders, $code, $status) {
            if (!\App\Models\TripPurchase::where('trip_code', $code)->exists()) {
                \App\Models\TripPurchase::create([
                    'trip_code' => $code, 'purchased_at' => substr((string) $orders->min('created_at'), 0, 10) ?: now()->toDateString(),
                    'cost' => 0, 'cost_currency' => 'dollar', 'payment_id' => \App\Services\AppSettings::cashPaymentId(),
                    'rate' => (float) (\App\Models\CurrencyExchange::latestRate() ?: 1), 'coupon_used' => false, 'legacy' => true,
                    'note' => 'طلبات قديمة من المنظومة السابقة بدون رحلة — جُمعت وتعلّمت تم التسليم', 'created_id' => auth()->id(),
                ]);
            }
            foreach ($orders as $o) {
                $d = optional($o->created_at)->toDateString() ?: now()->toDateString();
                $o->trip_details = $code;
                $o->trip_status = $status;
                $o->arrived = true;
                $o->arrived_at = $o->arrived_at ?: $d;
                $o->arrived_notified_at = $o->arrived_notified_at ?: now();
                $o->delivered = true;
                $o->delivered_at = $o->delivered_at ?: $o->arrived_at;
                $o->delivered_by = auth()->id();
                $o->save();
            }
        });
        \Illuminate\Support\Facades\Cache::forget('sg.badge.ready');
        Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ' طلبات قديمة بدون رحلة → ' . $code, 'log' => ' ' . $orders->count() . ' طلب (من ' . $data['from'] . ' إلى ' . $data['to'] . ' يوم) تعلّمت تم التسليم — بدون أي حركة مالية: ' . $orders->pluck('code')->implode('، ')]);

        return redirect()->route('sg.delivery.old.notrip', ['from' => $data['from'], 'to' => $data['to']])
            ->with('success', 'تم: ' . $orders->count() . ' طلب في رحلة ' . $code . ' وتعلّمت «تم التسليم» · ما تحركت أي فلوس، والديون باقية');
    }

    public function store(Request $request)
    {
        $data = $request->validate(['before' => 'required|date', 'which' => 'required|in:all,paid', 'expected' => 'required|integer|min:1', 'confirm' => 'accepted']);
        $orders = $this->filter($this->query($data['before'], $data['which'])->get(), $data['which']);
        if ($orders->count() !== (int) $data['expected']) {
            return back()->with('error', 'العدد تغيّر منذ عرض الصفحة (' . $orders->count() . ' بدل ' . $data['expected'] . ') — راجع القائمة واضغط مرة أخرى');
        }
        DB::transaction(function () use ($orders) {
            foreach ($orders as $o) {
                $o->delivered = true;
                $o->delivered_at = $o->arrived_at ?: optional($o->updated_at)->toDateString() ?: now()->toDateString();
                $o->delivered_by = auth()->id();
                $o->save(); // يمر على سجل التعديلات الأوتوماتيكي
            }
        });
        \Illuminate\Support\Facades\Cache::forget('sg.badge.ready');
        Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ' تعليم طلبات قديمة كمُسلَّمة', 'log' => ' ' . $orders->count() . ' طلب وصل قبل ' . $data['before'] . ' (' . ($data['which'] === 'paid' ? 'المدفوعة فقط' : 'الكل') . ') — بدون أي حركة مالية: ' . $orders->pluck('code')->implode('، ')]);

        return redirect()->route('sg.delivery.old', ['before' => $data['before'], 'which' => $data['which']])
            ->with('success', 'تم تعليم ' . $orders->count() . ' طلب «تم التسليم» · ما تحركت أي فلوس، والديون باقية على أصحابها');
    }
}
