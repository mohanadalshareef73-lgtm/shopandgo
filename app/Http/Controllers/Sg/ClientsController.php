<?php

namespace App\Http\Controllers\Sg;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Log;
use App\Models\Order;
use App\Services\Csv;
use App\Services\Period;
use Illuminate\Http\Request;

/** العملاء: القائمة + ملف العميل */
class ClientsController extends Controller
{
    public const VIP_ORDERS = 10;

    public function __construct()
    {
        $this->middleware('permission:customer-list', ['only' => ['index']]);
        $this->middleware('permission:customer-create', ['only' => ['store']]);
        $this->middleware('permission:customer-edit', ['only' => ['update', 'note', 'message', 'account', 'credit', 'bulkMessage']]);
        $this->middleware('permission:points-edit', ['only' => ['points']]);
        $this->middleware('permission:profit-view', ['only' => ['profit']]);
    }

    /** أعمدة محسوبة لكل عميل (طلبات، صرف، عليه، آخر طلب) */
    public static function sql(): array
    {
        $live = "o.customer_id = customers.id AND o.status != 'canceld'";

        return [
            'orders_n' => "(SELECT COUNT(*) FROM orders o WHERE $live)",
            'spent' => "(SELECT COALESCE(SUM(o.total_price - COALESCE(o.discount,0)),0) FROM orders o WHERE $live)",
            'due' => "(SELECT COALESCE(SUM(o.total_price + COALESCE(o.ship_price,0) - COALESCE(o.discount,0) - o.paid_price),0) FROM orders o WHERE $live AND o.total_price + COALESCE(o.ship_price,0) - COALESCE(o.discount,0) - o.paid_price > 0.009)",
            'last_at' => "(SELECT MAX(o.created_at) FROM orders o WHERE $live)",
        ];
    }

    public function index(Request $request)
    {
        $s = self::sql();
        $q = Customer::query()->select('customers.*');
        foreach ($s as $k => $expr) {
            $q->selectRaw("$expr as $k");
        }
        if ($request->filled('q')) {
            $t = trim($request->q);
            $q->where(fn ($w) => \App\Support\Search::customer($w, $t));
        }
        $tag = $request->input('tag');
        match ($tag) {
            'vip' => $q->where('customers.is_vip', true),
            'new' => $q->where('customers.created_at', '>=', now()->startOfMonth()),
            'debt' => $q->whereRaw("{$s['due']} > 0.009"),
            'idle' => $q->whereRaw("({$s['last_at']} IS NULL OR {$s['last_at']} < ?)", [now()->subDays(60)]),
            default => null,
        };
        if ($request->filled('city')) {
            $city = $request->city;
            $q->where(fn ($w) => $w->where('address', 'like', '%' . $city . '%')->when(\App\Support\Fast::hasColumn('customers', 'city'), fn ($x) => $x->orWhere('city', 'like', '%' . $city . '%')->orWhere('area', 'like', '%' . $city . '%')));
        }
        $sort = $request->input('sort', 'last');
        match ($sort) {
            'spent' => $q->orderByDesc('spent'),
            'due' => $q->orderByDesc('due'),
            'orders' => $q->orderByDesc('orders_n'),
            default => $q->orderByRaw('last_at IS NULL')->orderByDesc('last_at')->orderByDesc('customers.id'),
        };

        if ($request->export === 'csv') {
            return Csv::download('customers.csv', ['الاسم', 'الكود', 'هاتف 1', 'هاتف 2', 'العنوان', 'الطلبات', 'صرف', 'عليه'],
                $q->get()->map(fn ($c) => [$c->name, $c->code, $c->phone_1, $c->phone_2, $c->address, $c->orders_n, round($c->spent, 2), round($c->due, 2)]));
        }
        $list = $q->paginate(25)->withQueryString();

        // ملف العميل المختار
        $sel = null;
        $prof = [];
        $selId = $request->input('id', optional($list->first())->id);
        if ($selId) {
            $sel = Customer::query()->select('customers.*');
            foreach ($s as $k => $expr) {
                $sel->selectRaw("$expr as $k");
            }
            $sel = $sel->find($selId);
        }
        if ($sel) {
            $orders = Order::with('platform')->where('customer_id', $sel->id)->where('status', '!=', 'canceld')->orderByDesc('id')->get();
            $months = collect(range(7, 0))->map(fn ($i) => now()->startOfMonth()->subMonths($i));
            $prof = [
                'orders' => $orders->take(5),
                'spark' => $months->map(fn ($m) => round($orders->filter(fn ($o) => $o->created_at && $o->created_at->format('Y-m') === $m->format('Y-m'))->sum(fn ($o) => $o->total_price - $o->discount)))->values(),
                'avg' => $orders->count() ? $orders->avg(fn ($o) => $o->total_price - $o->discount) : 0,
                'platforms' => $orders->groupBy(fn ($o) => optional($o->platform)->name ?: '—')->map->count()->sortDesc()->take(2),
                'delivery' => $orders->where('delivered', true)->groupBy('delivery_type')->map->count()->sortDesc()->keys()->first(),
                'shelf' => ($d = $orders->where('delivered', true)->filter(fn ($o) => $o->received_at && $o->delivered_time)->map(fn ($o) => $o->received_at->diffInDays($o->delivered_time)))->count() ? round($d->avg()) : null,
                'depts' => $sel->depts()->where('status', 'dept')->get(),
                'messages' => $sel->messages()->take(5)->get(),
                'pointsLog' => \App\Models\PointsHistory::where('customer_id', $sel->id)->latest()->take(5)->get(),
            ];
        }

        $total = Customer::count();
        $newCur = Customer::where('created_at', '>=', now()->startOfMonth())->count();
        $newPrev = Customer::whereBetween('created_at', [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()])->count();
        $active = Order::where('created_at', '>=', now()->subDays(30))->distinct('customer_id')->count('customer_id');
        $counts = Order::where('status', '!=', 'canceld')->selectRaw('customer_id, COUNT(*) n')->groupBy('customer_id')->pluck('n');
        $repeat = $counts->count() ? round($counts->filter(fn ($n) => $n > 1)->count() / $counts->count() * 100) : 0;

        return view('sg.clients.index', [
            'nav' => 'cust', 'list' => $list, 'sel' => $sel, 'prof' => $prof, 'tag' => $tag,
            'kpi' => compact('total', 'newCur', 'newPrev', 'active', 'repeat'),
            'prevMonth' => Period::MONTHS[now()->subMonthNoOverflow()->month - 1],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'phone_1' => 'required|string|max:20|unique:customers,phone_1',
            'phone_2' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:190',
            'city' => 'required|string|max:80', 'area' => 'required|string|max:120',
            'code' => 'nullable|string|max:20|unique:customers,code',
            'notes' => 'nullable|string|max:2000',
        ], ['city.required' => 'اكتب المدينة', 'area.required' => 'اكتب المنطقة', 'phone_1.unique' => 'رقم الهاتف مسجل لعميل آخر', 'code.unique' => 'هذا الكود مستخدم — جرب: ' . implode('، ', self::codeSuggestions((string) $request->input('code'), preg_replace('/\D/', '', (string) $request->input('phone_1'))))]);
        $c = new Customer($data);
        $c->code = $data['code'] ?? self::codeFor($data['phone_1']);
        $c->save();
        Log::create(['user_id' => auth()->id(), 'type' => 'customer', 'title' => ' عميل جديد (' . $c->name . ')', 'log' => ' تم اضافة عميل جديد (' . $c->name . ')']);

        return redirect()->route('sg.clients', ['id' => $c->id])->with('success', 'تمت إضافة ' . $c->name . ' · الكود ' . $c->code);
    }

    public function update(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'phone_1' => 'required|string|max:20|unique:customers,phone_1,' . $customer->id,
            'phone_2' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:190',
            'city' => 'required|string|max:80', 'area' => 'required|string|max:120',
        ], ['city.required' => 'اكتب المدينة', 'area.required' => 'اكتب المنطقة']);
        $customer->update($data);

        return back()->with('success', 'تم حفظ بيانات العميل');
    }

    public function note(Request $request, Customer $customer)
    {
        $customer->notes = trim((string) $request->notes) ?: null;
        $customer->save();

        return back()->with('success', 'تم حفظ الملاحظة');
    }

    /** رسالة للعميل: تظهر في حسابه وتنبعت واتساب */
    public function message(Request $request, Customer $customer)
    {
        $data = $request->validate(['body' => 'required|string|max:1000']);
        \App\Services\CustomerNotify::$enabled = false; // الرسالة تُسجَّل بنصها كما كتبها الموظف
        $sent = \App\Services\MessageService::sendTemplate('customer_note', $customer, \App\Services\MessageService::customerVars($customer, ['{الملاحظة}' => $data['body']]));
        \App\Services\CustomerNotify::$enabled = true;
        \App\Models\CustomerMessage::create(['customer_id' => $customer->id, 'body' => $data['body'], 'sent' => $sent, 'created_id' => auth()->id()]);

        return back()->with($sent ? 'success' : 'warning', $sent ? 'أُرسلت الرسالة ✓' : 'سُجّلت في حسابه، فقط الواتساب لم يُرسل');
    }

    /** ربح كل عميل: ربح الرحلة يتقسم على إيصالاتها حسب قيمة كل إيصال */
    public function profit(Request $request)
    {
        $p = Period::make($request->input('p', 'month'));
        $trips = \App\Models\TripPurchase::countable()->with('orders')->whereBetween('purchased_at', [$p->from->toDateString(), $p->to->toDateTimeString()])->get();
        $rows = [];
        foreach ($trips as $t) {
            $sum = $t->summary();
            $sales = (float) $sum['sales_dinar'];
            if ($sales <= 0) {
                continue;
            }
            foreach ($t->orders->where('status', '!=', 'canceld') as $o) {
                $v = $t->toDinar((float) $o->total_price, $o->currency);
                $r = &$rows[$o->customer_id];
                $r ??= ['orders' => 0, 'sales' => 0.0, 'profit' => 0.0];
                $r['orders']++;
                $r['sales'] += $v;
                $r['profit'] += $sum['profit_dinar'] * $v / $sales;
                unset($r);
            }
        }
        $ids = array_keys($rows);
        $customers = Customer::whereIn('id', $ids)->get(['id', 'name', 'code', 'phone_1'])->keyBy('id');
        $due = Order::whereIn('customer_id', $ids)->where('status', '!=', 'canceld')->whereRaw(OrdersController::debtSql())->get()
            ->groupBy('customer_id')->map(fn ($g) => $g->sum(fn ($o) => $o->remaining()));
        $ret = \Illuminate\Support\Facades\Schema::hasTable('order_returns') ? \App\Models\OrderReturn::whereIn('customer_id', $ids)->whereBetween('created_at', [$p->from, $p->to])
            ->selectRaw('customer_id, COUNT(*) n, SUM(amount) a')->groupBy('customer_id')->get()->keyBy('customer_id') : collect();
        $list = collect($rows)->map(fn ($r, $id) => (object) ($r + ['c' => $customers[$id] ?? null, 'due' => (float) ($due[$id] ?? 0),
            'ret_n' => (int) optional($ret[$id] ?? null)->n, 'ret_a' => (float) optional($ret[$id] ?? null)->a,
            'margin' => $r['sales'] > 0 ? $r['profit'] / $r['sales'] * 100 : 0]))->filter(fn ($r) => $r->c);
        $sort = $request->input('sort', 'profit');
        $list = (match ($sort) { 'margin' => $list->sortByDesc('margin'), 'low' => $list->sortBy('profit'), 'due' => $list->sortByDesc('due'), default => $list->sortByDesc('profit') })->values();

        return view('sg.clients.profit', ['nav' => 'cust', 'period' => $p, 'list' => $list, 'sort' => $sort,
            'tot' => ['profit' => $list->sum('profit'), 'sales' => $list->sum('sales'), 'n' => $list->count()]]);
    }

    /** VIP يدوي: تشغيل / إطفاء */
    /** ✅ موثوق: يأكد بدون عربون */
    public function trusted(Customer $customer)
    {
        abort_unless(auth()->user()->hasRole('Admin'), 403);
        $customer->trusted = !$customer->trusted;
        $customer->save();
        Log::create(['user_id' => auth()->id(), 'type' => 'customer', 'title' => ' موثوق (' . $customer->name . ')', 'log' => $customer->trusted ? ' صار موثوق (بدون عربون)' : ' انشال من الموثوقين']);

        return back()->with('success', $customer->trusted ? '✅ ' . $customer->name . ' ولّى موثوق — يأكد بدون عربون' : 'انشال من الموثوقين');
    }

    /** نلصقو قائمة أرقام ونعلّموهم كلهم موثوقين */
    public function trustedBulk(Request $request)
    {
        abort_unless(auth()->user()->hasRole('Admin'), 403);
        $request->validate(['phones' => 'required|string|max:20000'], ['phones.required' => 'الصق الأرقام أو الأكواد']);
        // كل سطر: رقم هاتف (9 أرقام فأكثر) أو كود عميل
        $tokens = collect(preg_split('/[\s,،;]+/u', (string) $request->phones))->map(fn ($p) => trim($p))->filter()->unique();
        $n = 0;
        $miss = [];
        foreach ($tokens as $tok) {
            $digits = preg_replace('/\D/', '', $tok);
            if (strlen($digits) >= 9) {
                $t = substr($digits, -9);
                $c = Customer::withoutGlobalScopes()->where(fn ($q) => $q->where('phone_1', 'like', '%' . $t)->orWhere('phone_2', 'like', '%' . $t))->first();
            } else {
                $c = Customer::withoutGlobalScopes()->where('code', $tok)->first();
            }
            if ($c) {
                $c->trusted = true;
                $c->save();
                $n++;
            } else {
                $miss[] = $tok;
            }
        }
        Log::create(['user_id' => auth()->id(), 'type' => 'customer', 'title' => ' موثوقين بالجملة', 'log' => ' ' . $n . ' عميل']);

        return back()->with('success', '✅ تعلّم ' . $n . ' عميل موثوق' . ($miss ? ' · ما لقيناش ' . count($miss) . ': ' . implode('، ', array_slice($miss, 0, 15)) . (count($miss) > 15 ? '…' : '') : ''));
    }

    /** نقرو ملف تصدير شات قروب الحجز (.txt أو .zip) ونطلعو الأكواد اللي جنبها ✅ */
    public function trustedPreview(Request $request)
    {
        abort_unless(auth()->user()->hasRole('Admin'), 403);
        $request->validate(['chat' => 'required|file|max:51200'], ['chat.required' => 'ارفع ملف الشات']);
        $f = $request->file('chat');
        $text = '';
        if (strtolower($f->getClientOriginalExtension()) === 'zip' && class_exists(\ZipArchive::class)) {
            $z = new \ZipArchive;
            if ($z->open($f->getRealPath()) === true) {
                for ($i = 0; $i < $z->numFiles; $i++) {
                    if (str_ends_with(strtolower($z->getNameIndex($i)), '.txt')) {
                        $text .= $z->getFromIndex($i) . "\n";
                    }
                }
                $z->close();
            }
        } else {
            $text = (string) file_get_contents($f->getRealPath());
        }
        if (trim($text) === '') {
            return back()->with('error', 'الملف فاضي أو مش ملف شات واتساب (.txt أو .zip)');
        }
        $text = strtr($text, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
        $found = [];
        foreach (preg_split('/\R/u', $text) as $line) {
            if (!preg_match('/✅|✔|☑|✓/u', $line)) {
                continue;
            }
            // نشيلو علامات الاتجاه المخفية، وبعدين التاريخ والوقت من أول السطر
            $line = preg_replace('/[\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{00A0}\x{202F}]/u', ' ', $line);
            $line = preg_replace('/(\d)\s+([\/\.\-])\s*(\d)/u', '$1$2$3', $line);
            $body = preg_replace('/^\[?\d{1,4}[\/\.\-]\d{1,2}[\/\.\-]\d{1,4},?\s*\d{1,2}:\d{2}(?::\d{2})?\s*(?:[AaPp]\.?[Mm]\.?|ص|م)?\]?\s*[-–]?\s*/u', '', $line);
            if (preg_match_all('/(?<!\d)(\d{3,12})(?!\d)/', $body, $m)) {
                foreach ($m[1] as $num) {
                    $found[$num] = ($found[$num] ?? 0) + 1;
                }
            }
        }
        $rows = [];
        foreach ($found as $num => $times) {
            $c = strlen($num) >= 9
                ? Customer::withoutGlobalScopes()->where(fn ($q) => $q->where('phone_1', 'like', '%' . substr($num, -9))->orWhere('phone_2', 'like', '%' . substr($num, -9)))->first()
                : Customer::withoutGlobalScopes()->where('code', $num)->first();
            if ($c && !isset($rows[$c->id])) {
                $rows[$c->id] = ['c' => $c, 'key' => $c->code ?: $c->phone_1, 'times' => $times, 'by' => $num];
            }
        }
        uasort($rows, fn ($a, $b) => $b['times'] <=> $a['times']);

        return view('sg.clients.trusted_preview', ['nav' => 'cust', 'rows' => $rows, 'unknown' => count($found) - count($rows)]);
    }

    public function vip(Customer $customer)
    {
        $customer->is_vip = !$customer->is_vip;
        $customer->save();
        if ($customer->is_vip && request()->boolean('notify', true)) {
            try {
                \App\Services\LoyaltyService::notifyVip($customer);
            } catch (\Throwable $e) {
                report($e);
            }
        }
        Log::create(['user_id' => auth()->id(), 'type' => 'customer', 'title' => ' VIP (' . $customer->name . ')', 'log' => $customer->is_vip ? ' صار VIP' : ' انشال من VIP']);

        return back()->with('success', $customer->name . ($customer->is_vip ? ' صار ⭐ VIP' : ' انشال من VIP'));
    }

    /** رصيد العميل عندنا: زيادة أو نقص، واختياري يدخل/يطلع من الخزينة */
    public function credit(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|not_in:0', 'reason' => 'nullable|string|max:150',
            'treasury' => 'nullable|boolean', 'payment_id' => 'required_if:treasury,1|nullable|exists:payment_methods,id',
        ]);
        $amt = round((float) $data['amount'], 2);
        if ($amt < 0 && (float) $customer->credit + $amt < -0.009) {
            return back()->with('error', 'رصيده ' . \App\Services\TreasuryService::fmt($customer->credit) . ' بس');
        }
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            if ($request->boolean('treasury')) {
                $amt > 0
                    ? \App\Services\TreasuryService::deposit($amt, 'dinar', (int) $data['payment_id'], 'customer_credit', $customer->id, 'رصيد للعميل ' . $customer->name)
                    : \App\Services\TreasuryService::withdraw(-$amt, 'dinar', (int) $data['payment_id'], 'customer_credit', $customer->id, 'سحب من رصيد العميل ' . $customer->name);
            }
            $customer->credit = round((float) $customer->credit + $amt, 2);
            $customer->save();
            Log::create(['user_id' => auth()->id(), 'type' => 'customer', 'title' => ' رصيد عميل (' . $customer->name . ')', 'log' => ' ' . ($amt > 0 ? 'زيادة ' : 'نقص ') . abs($amt) . ' · الرصيد ' . $customer->credit . ($request->boolean('treasury') ? ' · مع الخزينة' : '') . (($data['reason'] ?? '') ? ' · ' . $data['reason'] : '')]);
            \Illuminate\Support\Facades\DB::commit();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\DB::rollBack();

            return back()->with('error', $e instanceof \RuntimeException ? $e->getMessage() : 'للأسف حدث خطأ، يرجى إعادة المحاولة');
        }

        return back()->with('success', 'رصيد ' . $customer->name . ' الآن ' . \App\Services\TreasuryService::fmt($customer->credit) . ' د.ل');
    }

    /** رسائل تنشيط/تذكير: كل عميل رسالته بروحه */
    public function bulkMessage(Request $request)
    {
        $data = $request->validate(['ids' => 'required|array|min:1|max:150', 'ids.*' => 'integer', 'body' => 'required|string|max:1000'], ['ids.required' => 'اختار عملاء']);
        $sent = 0;
        $failed = 0;
        foreach (Customer::whereIn('id', $data['ids'])->get() as $c) {
            $body = str_replace(['{الاسم}', '{الكود}'], [$c->name, $c->code], $data['body']);
            \App\Services\CustomerNotify::$enabled = false;
            try {
                $ok = \App\Services\MessageService::sendTemplate('customer_note', $c, \App\Services\MessageService::customerVars($c, ['{الملاحظة}' => $body]));
            } catch (\Throwable $e) {
                report($e);
                $ok = false;
            }
            \App\Services\CustomerNotify::$enabled = true;
            \App\Models\CustomerMessage::create(['customer_id' => $c->id, 'body' => $body, 'sent' => $ok, 'created_id' => auth()->id()]);
            $ok ? $sent++ : $failed++;
        }
        Log::create(['user_id' => auth()->id(), 'type' => 'customer', 'title' => ' رسائل تنشيط', 'log' => ' أُرسلت لـ ' . $sent . ($failed ? ' · فشل ' . $failed : '')]);

        return back()->with($sent ? 'success' : 'warning', "أُرسلت لـ $sent عميل" . ($failed ? " · $failed ما وصلتش" : ''));
    }

    /** بيانات دخول العميل لحسابه (رقم الهاتف + كلمة المرور) */
    public function account(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'phone_1' => 'required|string|max:20|unique:customers,phone_1,' . $customer->id,
            'code' => 'required|string|max:20|unique:customers,code,' . $customer->id,
            'password' => 'nullable|string|min:8',
        ], ['phone_1.unique' => 'هذا الرقم مسجل لعميل آخر', 'code.unique' => 'الكود مستخدم']);
        $customer->phone_1 = $data['phone_1'];
        $customer->code = $data['code'];
        if (!empty($data['password'])) {
            $customer->password = \Illuminate\Support\Facades\Hash::make($data['password']);
        }
        $customer->save();
        Log::create(['user_id' => auth()->id(), 'type' => 'customer', 'title' => ' حساب عميل (' . $customer->name . ')', 'log' => ' تعديل بيانات الدخول' . (!empty($data['password']) ? ' وكلمة السر' : '')]);

        return back()->with('success', 'تم حفظ بيانات الدخول' . (!empty($data['password']) ? ' · كلمة المرور الجديدة: ' . $data['password'] : ''));
    }

    /** زيادة أو نقص نقاط يدوي */
    public function points(Request $request, Customer $customer)
    {
        $data = $request->validate(['points' => 'required|integer|not_in:0', 'reason' => 'nullable|string|max:150']);
        \App\Services\PointsService::add($customer, (int) $data['points'], $data['reason'] ?: 'تعديل يدوي');

        return back()->with('success', 'نقاط ' . $customer->name . ' الآن ' . $customer->fresh()->points);
    }

    /** كود العميل: موجود؟ وإذا موجود نقترحو بدائل فارغة */
    public function codeCheck(Request $request)
    {
        $code = trim((string) $request->input('code'));
        $phone = preg_replace('/\D/', '', (string) $request->input('phone'));
        $except = (int) $request->input('except');
        $taken = fn ($c) => Customer::where('code', $c)->when($except, fn ($q) => $q->where('id', '!=', $except))->exists();
        $exists = $code !== '' && $taken($code);
        $owner = $exists ? Customer::where('code', $code)->first(['id', 'name', 'phone_1']) : null;

        return response()->json([
            'exists' => $exists,
            'owner' => $owner ? ['name' => $owner->name, 'phone' => $owner->phone_1] : null,
            'suggestions' => ($exists || $code === '') ? self::codeSuggestions($code, $phone, $taken) : [],
        ]);
    }

    /** بدائل للكود: من أرقام الهاتف، والكود + رقم، والرقم الذي بعده */
    public static function codeSuggestions(string $code, string $phone, ?callable $taken = null, int $limit = 5): array
    {
        $taken = $taken ?: fn ($c) => Customer::where('code', $c)->exists();
        $out = [];
        $add = function ($c) use (&$out, $taken, $limit) {
            $c = (string) $c;
            if ($c !== '' && count($out) < $limit && !in_array($c, $out, true) && !$taken($c)) {
                $out[] = $c;
            }
        };
        for ($len = 4; $len <= min(8, strlen($phone)); $len++) {
            $add(substr($phone, -$len));
        }
        if ($code !== '') {
            if (ctype_digit($code)) {
                for ($i = 1; $i <= 30 && count($out) < $limit; $i++) {
                    $add(str_pad((string) ((int) $code + $i), strlen($code), '0', STR_PAD_LEFT));
                }
            }
            for ($i = 1; $i <= 9 && count($out) < $limit; $i++) {
                $add($code . $i);
            }
            foreach (['A', 'B', 'C'] as $l) {
                $add($code . $l);
            }
        }

        return $out;
    }

    public static function codeFor(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);
        $len = 4;
        $code = substr($phone, -$len);
        while (Customer::where('code', $code)->exists() && $len < strlen($phone)) {
            $code = substr($phone, -++$len);
        }

        return Customer::where('code', $code)->exists() ? $code . '-' . random_int(10, 99) : $code;
    }
}
