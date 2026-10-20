<?php

namespace App\Http\Controllers\Sg;

use App\Http\Controllers\Controller;
use App\Models\CartQuote;
use App\Models\Customer;
use App\Services\Period;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** مؤشرات الزيارات وحركة المنظومة */
class VisitsController extends Controller
{
    public const PAGES = [
        'customer.dashboard:home' => '🏠 حساب العميل — الرئيسية', 'customer.dashboard' => '🏠 حساب العميل — الرئيسية',
        'customer.dashboard:orders' => '📦 طلباتي', 'customer.dashboard:delivery' => '🛵 طلب توصيل', 'customer.dashboard:account' => '👤 حسابي',
        'customer.dashboard:problem' => '⚠ بلّغ عن مشكلة', 'customer.dashboard:points' => '⭐ النقاط',
        'portal.carts' => '🛒 السلات (حساب السعر)', 'portal.cart' => '🛒 تفاصيل سلة', 'customer.login' => '🔑 صفحة الدخول', 'customer.register' => '📝 التسجيل',
        'order.invoice' => '🧾 الفاتورة', 'courier.home' => '🛵 واجهة المندوب', 'admin.home' => '📊 الرئيسية (الإدارة)',
        'sg.orders.index' => 'الإيصالات', 'sg.delivery' => 'التسليم', 'sg.trips.index' => 'الرحلات', 'sg.clients' => 'العملاء', 'sg.pcarts' => 'تأكيد الحجوزات',
    ];

    public function __construct()
    {
        $this->middleware(fn ($r, $n) => auth()->user()->hasRole('Admin') || auth()->user()->can('reports-list') ? $n($r) : abort(403));
    }

    public static function label(string $page): string
    {
        return self::PAGES[$page] ?? self::PAGES[explode(':', $page)[0]] ?? $page;
    }

    public function index(Request $request)
    {
        abort_unless(Schema::hasTable('site_visits'), 404, 'شغّل system-update الأول');
        $p = Period::make($request->input('p', 'month'), $request->from, $request->to);
        $from = $p->from->toDateString();
        $to = $p->to->toDateString();
        $base = fn () => DB::table('site_visits')->whereBetween('date', [$from, $to]);

        $kpi = [
            'hits' => (int) $base()->sum('hits'),
            'cust' => (int) $base()->where('actor', 'customer')->distinct()->count('actor_id'),
            'custHits' => (int) $base()->where('actor', 'customer')->sum('hits'),
            'guest' => (int) $base()->where('actor', 'guest')->sum('hits'),
            'staff' => (int) $base()->where('actor', 'staff')->distinct()->count('actor_id'),
            'courier' => (int) $base()->where('actor', 'courier')->distinct()->count('actor_id'),
            'today' => (int) DB::table('site_visits')->where('date', now()->toDateString())->where('actor', 'customer')->distinct()->count('actor_id'),
            'mobile' => (int) $base()->where('actor', 'customer')->whereIn('device', ['mobile', 'app'])->sum('hits'),
        ];
        $totalCust = Customer::count();
        // كل يوم: كم عميل دخل
        $days = DB::table('site_visits')->where('date', '>=', now()->subDays(13)->toDateString())->selectRaw("date, actor, COUNT(DISTINCT actor_id) n, SUM(hits) h")
            ->groupBy('date', 'actor')->get()->groupBy('date');
        $chart = collect(range(13, 0))->map(function ($i) use ($days) {
            $d = now()->subDays($i)->toDateString();
            $g = collect($days[$d] ?? []);

            return ['d' => $d, 'cust' => (int) optional($g->firstWhere('actor', 'customer'))->n, 'guest' => (int) optional($g->firstWhere('actor', 'guest'))->h, 'staff' => (int) optional($g->firstWhere('actor', 'staff'))->n];
        });
        $pages = $base()->whereIn('actor', ['customer', 'guest'])->selectRaw('page, SUM(hits) h, COUNT(DISTINCT CASE WHEN actor = \'customer\' THEN actor_id END) u')->groupBy('page')->orderByDesc('h')->take(12)->get();
        $topCust = $base()->where('actor', 'customer')->selectRaw('actor_id, SUM(hits) h, COUNT(DISTINCT date) d, MAX(last_at) l')->groupBy('actor_id')->orderByDesc('d')->orderByDesc('h')->take(15)->get();
        $names = Customer::withoutGlobalScopes()->whereIn('id', $topCust->pluck('actor_id'))->get(['id', 'name', 'code'])->keyBy('id');
        $staff = $base()->whereIn('actor', ['staff', 'courier'])->selectRaw('actor, actor_id, SUM(hits) h, COUNT(DISTINCT date) d, MAX(last_at) l')->groupBy('actor', 'actor_id')->orderByDesc('h')->get();
        $users = \App\Models\User::whereIn('id', $staff->pluck('actor_id'))->pluck('name', 'id');
        // السلات: من الحساب ولا من الواتساب
        $carts = Schema::hasColumn('cart_quotes', 'portal_status') ? [
            'portal' => CartQuote::whereBetween('created_at', [$p->from, $p->to])->whereNotNull('portal_status')->count(),
            'wa' => CartQuote::whereBetween('created_at', [$p->from, $p->to])->whereNull('portal_status')->count(),
            'booked' => CartQuote::whereBetween('created_at', [$p->from, $p->to])->whereIn('portal_status', ['ordered', 'confirmed'])->count(),
        ] : null;

        return view('sg.visits.index', compact('p', 'kpi', 'chart', 'pages', 'topCust', 'names', 'staff', 'users', 'carts', 'totalCust') + ['nav' => 'visits', 'period' => $p]);
    }
}
