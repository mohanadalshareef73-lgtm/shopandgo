<?php

namespace App\Http\Controllers\Sg;

use App\Http\Controllers\Controller;
use App\Models\SgAlert;
use App\Services\Period;
use Illuminate\Http\Request;

/** مركز التنبيهات: كل التنبيهات مع فلاتر حسب القسم والحالة والفترة */
class AlertsController extends Controller
{
    /** القسم => [الاسم، الأيقونة، أنواع التنبيه] */
    public const GROUPS = [
        'orders' => ['الطلبات', '📦', ['free_orders', 'shelf', 'ship_expected']],
        'trips' => ['الرحلات', '✈', ['trip_late']],
        'customers' => ['العملاء', '👤', ['order_issue', 'ticket', 'request', 'signup', 'debt']],
        'couriers' => ['المندوبين', '🛵', ['problem', 'courier_close', 'delivered']],
        'staff' => ['الموظفين والأمان', '💼', ['shift_diff', 'security']],
        'system' => ['النظام', '⚙', ['backup']],
    ];

    public static function groupOf(string $type): string
    {
        foreach (self::GROUPS as $k => $g) {
            if (in_array($type, $g[2], true)) {
                return $k;
            }
        }

        return 'system';
    }

    public function index(Request $request)
    {
        $p = Period::make($request->input('p', 'month'), $request->from, $request->to);
        $g = isset(self::GROUPS[$request->g]) ? $request->g : null;
        $q = SgAlert::whereBetween('created_at', [$p->from, $p->to])->latest('id');
        if ($g) {
            $known = collect(self::GROUPS)->flatMap(fn ($x) => $x[2])->all();
            $g === 'system' ? $q->where(fn ($w) => $w->whereIn('type', self::GROUPS['system'][2])->orWhereNotIn('type', $known)) : $q->whereIn('type', self::GROUPS[$g][2]);
        }
        if ($request->boolean('unread')) {
            $q->whereNull('read_at');
        }
        if ($request->filled('level')) {
            $q->where('level', $request->level);
        }
        if ($request->filled('q')) {
            $t = trim($request->q);
            $q->where(fn ($w) => $w->where('title', 'like', "%$t%")->orWhere('body', 'like', "%$t%"));
        }
        $all = SgAlert::whereBetween('created_at', [$p->from, $p->to])->get(['type', 'read_at']);
        $counts = [];
        foreach (self::GROUPS as $k => $_) {
            $counts[$k] = ['all' => 0, 'unread' => 0];
        }
        foreach ($all as $a) {
            $k = self::groupOf($a->type);
            $counts[$k]['all']++;
            $counts[$k]['unread'] += $a->read_at ? 0 : 1;
        }
        // الحالة الآن (مشاكل مفتوحة)
        $now = [
            ['📦 طلبات بدون رحلة', \App\Http\Controllers\TripPurchaseController::freeOrdersQuery()->where('created_at', '<=', now()->subDays((int) \App\Services\AppSettings::get('free_order_alert_days', 2)))->count(), route('finance.trips.create')],
            ['✈ رحلات متأخرة', \App\Models\TripPurchase::alertable()->with('orders')->where('purchased_at', '<=', now()->subDays((int) \App\Services\AppSettings::get('trip_late_days', 10))->toDateString())->get()->filter(fn ($t) => ($o = $t->orders->where('status', '!=', 'canceld'))->count() && $o->where('arrived', false)->count() === $o->count())->count(), route('sg.trips.index')],
            ['⚠ مشاكل طلبات مفتوحة', \Illuminate\Support\Facades\Schema::hasTable('order_issues') ? \App\Models\OrderIssue::where('status', 'open')->count() : 0, route('sg.issues')],
            ['💬 بلاغات عملاء جديدة', \Illuminate\Support\Facades\Schema::hasTable('support_tickets') ? \Illuminate\Support\Facades\DB::table('support_tickets')->where('status', 'open')->count() : 0, route('sg.tickets')],
            ['🛵 مشاكل المندوبين', \App\Models\Delivery::where('status', 'problem')->count(), route('sg.couriers')],
            ['👤 طلبات تسجيل', \App\Models\Customer::withoutGlobalScope('approved')->where('signup_status', 'pending')->count(), route('sg.signups')],
            ['🚫 منتجات نفدت بانتظار الرد', \Illuminate\Support\Facades\Schema::hasTable('order_soldouts') ? \App\Models\OrderSoldout::where('status', 'pending')->count() : 0, route('sg.soldouts')],
        ];

        return view('sg.alerts.index', [
            'nav' => 'alerts', 'rows' => $q->paginate(40)->withQueryString(), 'g' => $g, 'counts' => $counts, 'period' => $p, 'now' => $now,
        ]);
    }

    public function read(Request $request)
    {
        $q = SgAlert::whereNull('read_at');
        if ($request->filled('id')) {
            $q->where('id', (int) $request->id);
        } elseif (isset(self::GROUPS[$request->g])) {
            $q->whereIn('type', self::GROUPS[$request->g][2]);
        }
        $n = $q->update(['read_at' => now()]);

        return back()->with('success', 'عُلّم ' . $n . ' تنبيه كمقروء');
    }

    /** مسح تنبيه (للإدارة بس — ما يتبعت حتى شي للعميل) */
    public function destroy(Request $request, SgAlert $alert)
    {
        $alert->delete();

        return $request->expectsJson() || $request->ajax() ? response()->json(['ok' => true]) : back()->with('success', 'تمسح التنبيه');
    }

    /** المحددين: نعلموهم مقروء أو نمسحوهم */
    public function bulk(Request $request)
    {
        $ids = array_map('intval', (array) $request->input('ids', []));
        if (!$ids) {
            return back()->with('error', 'ما حددتش حتى تنبيه');
        }
        if ($request->input('do') === 'delete') {
            $n = SgAlert::whereIn('id', $ids)->delete();

            return back()->with('success', 'تمسح ' . $n . ' تنبيه');
        }
        $n = SgAlert::whereIn('id', $ids)->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('success', 'تعلّم ' . $n . ' تنبيه مقروء');
    }

    /** مسح كل التنبيهات المقروءة (أو المقروءة في قسم معين) */
    public function clear(Request $request)
    {
        $q = SgAlert::whereNotNull('read_at');
        if (isset(self::GROUPS[$request->g])) {
            $q->whereIn('type', self::GROUPS[$request->g][2]);
        }
        $n = $q->delete();

        return back()->with('success', 'تمسح ' . $n . ' تنبيه مقروء');
    }

    /** فتح تنبيه من مركز التنبيهات: يتعلّم مقروء ويفتح الرابط */
    public function open(SgAlert $alert)
    {
        $alert->read_at ??= now();
        $alert->save();

        return redirect($alert->url ?: route('sg.alerts.center'));
    }

    /* ---------- الحسابات المقفلة بسبب دخول فاشل ---------- */
    protected function canFix(): void
    {
        abort_unless(auth()->user()->hasRole('Admin') || auth()->user()->can('customer-edit') || auth()->user()->can('customer-unlock'), 403);
    }

    protected function customerOf(string $login): ?\App\Models\Customer
    {
        return \App\Models\Customer::withoutGlobalScope('approved')->where('phone_1', $login)->first()
            ?? \App\Models\Customer::withoutGlobalScope('approved')->where('code', $login)->first();
    }

    public function locks()
    {
        $this->canFix();
        $rows = collect(\App\Services\LoginGuard::list())->map(function ($x) {
            $x['customer'] = $x['scope'] === 'customer' ? $this->customerOf($x['login']) : null;
            $x['user'] = $x['scope'] === 'admin' ? \App\Models\User::where('email', $x['login'])->orWhere('name', $x['login'])->orWhere('phone', $x['login'])->first() : null;
            $x['left'] = \App\Services\LoginGuard::lockedFor($x['scope'], $x['login']);

            return $x;
        });

        // عملاء موقوفين من السلات (كثرة الطلبات)
        $bans = \Illuminate\Support\Facades\Schema::hasColumn('customers', 'cart_ban_until')
            ? \App\Models\Customer::withoutGlobalScopes()->where(fn ($q) => $q->where('cart_ban_until', '>', now())->orWhere('cart_probation_until', '>', now()))->get()
            : collect();

        return view('sg.alerts.locks', ['nav' => 'alerts', 'rows' => $rows, 'bans' => $bans]);
    }

    public function unlock(Request $request)
    {
        $this->canFix();
        $x = \App\Services\LoginGuard::list()[$request->id] ?? null;
        if (!$x && $request->filled('login')) {
            // فك يدوي: نكتب رقم الهاتف أو الكود (حتى لو تقفل قبل التحديث)
            $x = ['scope' => $request->input('scope') === 'admin' ? 'admin' : 'customer', 'login' => trim($request->login), 'ip' => null];
        }
        if (!$x) {
            return back()->with('error', 'اكتب رقم الهاتف أو الكود');
        }
        $c = $x['scope'] === 'customer' ? $this->customerOf($x['login']) : null;
        // نمسح القفل على الرقم والكود الاثنين، وعلى الجهاز (IP) اللي حاول منه
        $logins = array_unique(array_filter([$x['login'], optional($c)->phone_1, optional($c)->code]));
        $ips = array_filter([$x['ip'] ?? null]);
        foreach (SgAlert::where('type', 'security')->where('created_at', '>=', now()->subDays(2))->get() as $al) {
            foreach ($logins as $lg) {
                if (str_contains((string) $al->body, (string) $lg) && preg_match('/IP: ([0-9a-fA-F.:]+)/', (string) $al->body, $m)) {
                    $ips[] = $m[1];
                }
            }
        }
        foreach ($logins as $lg) {
            \App\Services\LoginGuard::clear($x['scope'], (string) $lg);
        }
        foreach (array_unique($ips) as $ip) {
            foreach (['login-ip:', 'login-ip-h:'] as $pre) {
                \Illuminate\Support\Facades\RateLimiter::clear(md5('login' . $pre . $ip));
                \Illuminate\Support\Facades\RateLimiter::clear($pre . $ip);
            }
        }
        $reset = $request->boolean('reset') && $c;
        if ($reset) {
            $c->password = null; // يرجع لأول دخول: كلمة المرور = رقم هاتفه، وبعدين يختار جديدة
            $c->save();
        }
        if ($c && $request->boolean('notify')) {
            $msg = "مرحباً {$c->name} 👋\nتم فك القفل عن حسابك في Shop and Go ✓"
                . ($reset ? "\nأعدنا ضبط كلمة المرور: ادخل برقم هاتفك ({$c->phone_1}) ككلمة مرور، وبعدها اختار كلمة مرور جديدة." : "\nتقدر تدخل توا بكلمة المرور متاعك.")
                . "\n" . route('customer.login');
            try {
                \App\Services\MessageService::sendText('account_unlock', $msg, array_filter([$c->phone_1]), $c);
            } catch (\Throwable $e) {
                report($e);
            }
        }
        foreach ($logins as $lg) {
            SgAlert::where('type', 'security')->whereNull('read_at')->where('body', 'like', '%' . $lg . '%')->update(['read_at' => now()]);
        }
        if (!$c && $x['scope'] === 'customer') {
            return back()->with('warning', 'تمسح القفل، بس ما لقيناش عميل بهذا الرقم/الكود');
        }

        return back()->with('success', 'تم فك القفل' . ($c ? ' عن ' . $c->name : '') . ($reset ? ' وإعادة ضبط كلمة المرور' : '') . ($c && $request->boolean('notify') ? ' وتبعتله إشعار' : ''));
    }

    /** فك قفل بالجملة (اللي علّمت عليهم) + رسالة لكل واحد */
    public function unlockBulk(Request $request)
    {
        $this->canFix();
        $notify = $request->boolean('notify');
        $n = 0;
        $all = \App\Services\LoginGuard::list();
        foreach ((array) $request->input('items', []) as $item) {
            [$kind, $id] = array_pad(explode(':', (string) $item, 2), 2, '');
            $c = null;
            $why = '';
            if ($kind === 'login' && isset($all[$id])) {
                $x = $all[$id];
                \App\Services\LoginGuard::clear($x['scope'], $x['login']);
                $c = $x['scope'] === 'customer' ? $this->customerOf($x['login']) : null;
                if ($c) {
                    \App\Services\LoginGuard::clear('customer', (string) $c->phone_1);
                    \App\Services\LoginGuard::clear('customer', (string) $c->code);
                }
                $why = 'تقدر تدخل لحسابك توا بكلمة المرور متاعك.';
                $n++;
            } elseif ($kind === 'cart' && ($c = \App\Models\Customer::withoutGlobalScopes()->find((int) $id))) {
                $c->cart_ban_until = null;
                $c->cart_probation_until = null;
                $c->save();
                $why = 'ترجع تقدر تحسب سلاتك وتحجز من حسابك عادي 🛒';
                $n++;
            }
            if ($c && $notify) {
                try {
                    \App\Services\MessageService::sendText('account_unlock', "مرحباً {$c->name} 👋\nتم فك القفل عن حسابك في Shop and Go ✓\n{$why}\n" . route('customer.login'), array_filter([$c->phone_1]), $c);
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        return back()->with($n ? 'success' : 'error', $n ? 'انفك القفل عن ' . $n . ' حساب' . ($notify ? ' وتبعتلهم رسالة' : '') : 'ما علّمتش على حتى حساب');
    }

    /* ---------- رسائل واتساب للإدارة: نشغل/نوقف كل نوع لكل رقم ---------- */
    /** 🔕 تشغيل/إيقاف كل إشعارات الجوال للمسؤول (واتساب + إشعارات الشاشة) */
    public function mobile(Request $request)
    {
        abort_unless(auth()->user()->hasRole('Admin'), 403);
        $off = $request->boolean('off');
        \App\Services\AppSettings::put(['admin_mobile_off' => $off]);
        \App\Models\Log::create(['user_id' => auth()->id(), 'type' => 'user', 'title' => ' إشعارات الجوال', 'log' => $off ? ' توقفت' : ' اشتغلت']);

        return back()->with('success', $off ? '🔕 وقفت كل إشعارات الجوال — التنبيهات تقعد هنا في المنظومة' : '🔔 رجعت إشعارات الجوال');
    }

    public function waSave(Request $request)
    {
        abort_unless(auth()->user()->hasRole('Admin'), 403);
        $on = (array) $request->input('on', []);
        $mute = [];
        foreach (\App\Services\AppSettings::reportPhones() as $ph) {
            $mute[$ph] = array_values(array_diff(array_keys(\App\Services\AppSettings::WA_KINDS), (array) ($on[$ph] ?? [])));
        }
        \App\Services\AppSettings::put(['wa_mute' => $mute, 'courier_alert_whatsapp' => $request->boolean('alerts_on')]);

        return back()->with('success', 'تم حفظ إعدادات رسائل واتساب');
    }
}
