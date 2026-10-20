<?php

namespace App\Http\Controllers;

use App\Models\CurrencyExchange;
use App\Models\Log;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Platform;
use App\Models\ShippingCharges;
use App\Models\ShippingCompany;
use App\Models\ShippingType;
use App\Models\Status;
use App\Models\TripPurchase;
use App\Services\AppSettings;
use App\Services\MessageService;
use App\Services\TreasuryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * رحلات الشراء: نجمعو إيصالات تحت رقم رحلة، تسجيل كم دفعنا بالدولار وسعر الصرف،
 * نتابعو الحالة، ونستلمو البضاعة لما توصل.
 */
class TripPurchaseController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:trip-purchase-list', ['only' => ['index', 'show']]);
        $this->middleware('permission:trip-purchase-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:trip-purchase-edit|trip-purchase-create', ['only' => ['update', 'attach', 'attachTo', 'detach', 'status', 'bulkPreview', 'bulkStatus', 'noTrip']]);
        $this->middleware('permission:trip-purchase-delete', ['only' => ['destroy']]);
        $this->middleware('permission:order-receive', ['only' => ['receiveForm', 'receiveStore']]);
    }

    /** إيصالات ما دخلتش في رحلة شراء */
    public static function freeOrdersQuery()
    {
        $codes = TripPurchase::pluck('trip_code');

        return Order::with(['customer', 'platform'])
            ->where('status', '!=', 'canceld')->where('delivered', false)->where('arrived', false)
            ->whereNotIn('kind', ['shipping', 'card_sale', 'site_pay']) // الشحنات وبيع البطاقات لا تدخل الرحلات
            ->when(\Illuminate\Support\Facades\Schema::hasColumn('orders', 'no_trip'), fn ($q) => $q->where('no_trip', false)) // «بدون رحلة» ما تظهرش في قائمة «ليس في رحلة»
            ->where(function ($q) use ($codes) {
                $q->whereNull('trip_details')->orWhere('trip_details', '')->orWhereNotIn('trip_details', $codes);
            });
    }

    /** أين وصلت الرحلة (للوحة) */
    public static function tripColumn(TripPurchase $t): string
    {
        $orders = $t->orders->where('status', '!=', 'canceld');
        if ($orders->count() && $orders->every(fn ($o) => $o->delivered)) {
            return 'done';
        }
        if ($orders->contains(fn ($o) => $o->arrived)) {
            return 'deliv';
        }
        $stage = AppSettings::statusStage(optional($orders->first())->trip_status);

        return in_array($stage, ['prep', 'way', 'arrived'], true) ? ($stage === 'arrived' ? 'arr' : $stage) : 'way';
    }

    public function index(Request $request)
    {
        // رقم رحلة كامل (مثلاً B-0404 أو 0404) → نفتحها على طول
        if ($request->filled('q')) {
            $qq = strtoupper(trim($request->q));
            $hit = TripPurchase::whereRaw('UPPER(trip_code) = ?', [$qq])->first();
            if (!$hit && ctype_digit($qq)) {
                $many = TripPurchase::where('trip_code', 'like', '%-' . $qq)->take(2)->get();
                $hit = $many->count() === 1 ? $many->first() : null;
            }
            if ($hit instanceof TripPurchase) {
                return redirect()->route('finance.trips.show', $hit->id);
            }
        }
        $months = (int) $request->input('m', 2);
        $trips = TripPurchase::with(['orders', 'payment'])
            ->where('purchased_at', '>=', now()->subMonths($months)->startOfMonth())
            ->latest('purchased_at')->latest('id')->get();
        $cols = ['prep' => [], 'way' => [], 'arr' => [], 'deliv' => [], 'done' => []];
        foreach ($trips as $t) {
            $t->col = self::tripColumn($t);
            $cols[$t->col][] = $t;
        }
        // القائمة (تحت بعض مثل الطلبات) + فلتر المرحلة والبحث
        $list = $trips;
        if ($request->filled('stage') && isset($cols[$request->stage])) {
            $list = $list->where('col', $request->stage);
        }
        $tripCustomers = collect();
        if ($request->filled('q')) {
            // البحث يبحث في كل الرحلات (غير فقط آخر شهرين)
            $t = trim($request->q);
            $list = TripPurchase::with(['orders.customer', 'payment'])->where(fn ($w) => \App\Support\Search::code($w, $t, 'trip_code')
                ->orWhereIn('trip_code', \App\Support\Search::code(Order::query(), $t)->whereNotNull('trip_details')->pluck('trip_details')))
                ->latest('purchased_at')->take(30)->get()->each(fn ($x) => $x->col = self::tripColumn($x));
            if ($request->filled('stage') && isset($cols[$request->stage])) {
                $list = $list->where('col', $request->stage);
            }
            // عملاء الرحلة (إذا البحث لقى رحلة أو زوز)
            foreach ($list->take(3) as $trip) {
                $tripCustomers->push((object) [
                    'trip' => $trip,
                    'rows' => $trip->orders->where('status', '!=', 'canceld')->groupBy('customer_id')->map(fn ($g) => (object) [
                        'customer' => $g->first()->customer, 'orders' => $g, 'qty' => (int) $g->sum('qty'),
                        'received' => $g->whereNotNull('received_qty')->sum('received_qty'), 'bags' => (int) $g->sum('bags_num'),
                        'due' => $g->sum(fn ($o) => max(0, $o->remaining())), 'arrived' => $g->where('arrived', true)->count(), 'delivered' => $g->where('delivered', true)->count(),
                    ])->sortBy(fn ($r) => optional($r->customer)->name)->values(),
                ]);
            }
        }
        $month = TripPurchase::countable()->with('orders')->whereBetween('purchased_at', [now()->startOfMonth()->toDateString(), now()->endOfDay()->toDateTimeString()])->get();
        $kpi = [
            'count' => $month->count(),
            'orders' => $month->sum(fn ($t) => $t->summary()['orders']),
            'usd' => $month->where('cost_currency', 'dollar')->sum('cost'),
            'profit' => $month->sum(fn ($t) => $t->summary()['profit_dinar']),
            'sales' => $month->sum(fn ($t) => $t->summary()['sales_dinar']),
        ];
        $unpaidShip = TripPurchase::whereHas('orders', fn ($q) => $q->where('arrived', true))
            ->whereNotIn('id', ShippingCharges::whereNotNull('trip_purchase_id')->pluck('trip_purchase_id'))
            ->where('purchased_at', '>=', now()->subMonths(3))->get();

        return view('sg.trips.index', [
            'nav' => 'trip', 'cols' => $cols, 'list' => $list->values(), 'kpi' => $kpi, 'unpaidShip' => $unpaidShip, 'tripCustomers' => $tripCustomers,
            'statuses' => Status::orderBy('sort')->get(),
            'legacyPending' => auth()->user()->can('trip-purchase-create') ? \App\Services\LegacyTrips::pending() : [],
            'cards' => \App\Models\SheinCard::whereIn('status', ['new', 'partial'])->where('balance', '>', 0)->count(),
            'freeCount' => self::freeOrdersQuery()->count(),
        ]);
    }

    /** العملاء وإيصالاتهم (للقائمة متاع إضافة الرحلة) */
    public static function customersPayload($orders)
    {
        return $orders->groupBy('customer_id')->map(function ($g) {
            $c = $g->first()->customer;
            $pf = optional($g->first()->platform)->name;

            return [
                'id' => optional($c)->id, 'name' => optional($c)->name, 'code' => optional($c)->code,
                'orders' => $g->map(fn ($o) => ['id' => $o->id, 'code' => $o->code, 'total' => (float) $o->total_price, 'paid' => (float) $o->paid_price, 'qty' => (int) $o->qty, 'pf' => optional($o->platform)->name, 'trip' => $o->trip_details, 'cur' => $o->currency ?: 'dinar', 'st' => $o->stageLabel()])->values(),
                'pf' => $pf, 'days' => (int) $g->min(fn ($o) => $o->created_at->diffInDays(now())),
            ];
        })->values();
    }

    public function create(Request $request)
    {
        $orders = self::freeOrdersQuery()->latest()->get();
        $pre = collect(explode(',', (string) $request->ids))->filter()->map(fn ($v) => (int) $v)->all();
        $customers = self::customersPayload($orders);
        $payments = PaymentMethod::where('status', true)->get();
        $visaId = optional($payments->first(fn ($p) => preg_match('/visa|فيزا|usdt/i', $p->name)))->id;
        $avgRate = CurrencyExchange::where('to_currency', 'dollar')->where('date', '>=', now()->subDays(45)->toDateString())->selectRaw('SUM(from_amount) as a, SUM(to_amount) as b')->first();
        $avgRate = $avgRate && $avgRate->b > 0 ? round($avgRate->a / $avgRate->b, 3) : CurrencyExchange::latestRate();

        return view('sg.trips.create', [
            'nav' => 'trip', 'customers' => $customers, 'pre' => $pre,
            'platforms' => Platform::orderBy('id')->get(), 'statuses' => Status::orderBy('sort')->get(),
            'payments' => $payments, 'visaId' => $visaId, 'avgRate' => $avgRate,
            'dollarBalances' => $payments->mapWithKeys(fn ($p) => [$p->id => TreasuryService::balance('dollar', $p->id)]),
            'cashId' => \App\Services\AppSettings::cashPaymentId(),
            'dinarBalances' => $payments->mapWithKeys(fn ($p) => [$p->id => TreasuryService::balance('dinar', $p->id)]),
            'nextCode' => $this->suggestCode(),
            'cards' => \App\Models\SheinCard::whereIn('status', ['new', 'partial'])->where('balance', '>', 0)->orderBy('purchased_at')->get(),
            'allFree' => $customers->count(),
        ]);
    }

    protected function suggestCode(): string
    {
        $last = TripPurchase::latest('id')->value('trip_code');
        if ($last && preg_match('/^(.*?)(\d+)$/', $last, $m)) {
            return $m[1] . str_pad((string) ((int) $m[2] + 1), strlen($m[2]), '0', STR_PAD_LEFT);
        }

        return 'B-0001';
    }

    protected function rules(?TripPurchase $trip = null): array
    {
        return [
            'trip_code' => 'required|string|max:100|unique:trip_purchases,trip_code' . ($trip ? ',' . $trip->id : ''),
            'trip_status' => 'nullable|string|max:191',
            'purchased_at' => 'required|date',
            'cost' => 'required|numeric|min:' . ($trip && $trip->legacy ? '0' : '0.01'), // الرحلة القديمة تقبل 0 (التكلفة غير مسجلة)
            'cost_currency' => 'required|in:dollar,dinar,euro',
            'payment_id' => 'required|exists:payment_methods,id',
            'rate' => 'required|numeric|min:0.0001',
            'coupon_used' => 'nullable|boolean',
            'note' => 'nullable|string|max:500',
            'pay_currency' => 'nullable|in:dollar,dinar',
        ];
    }

    protected function messages(): array
    {
        return [
            'trip_code.required' => 'اكتب كود الرحلة',
            'trip_code.unique' => 'كود الرحلة هذا مسجل قبل، افتحه من الرحلات وعدل عليه',
            'cost.required' => 'اكتب كم كلفتك الرحلة بالدولار',
            'rate.required' => 'اكتب بكم شاري الدولار',

        ];
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules() + [
            'order_ids' => 'nullable|array', // الرحلة يمكن أن تُنشأ بدون عملاء ويُضاف لها لاحقاً
            'order_ids.*' => 'integer|exists:orders,id',
            'cards' => 'nullable|array',
            'cards.*' => 'nullable|numeric|min:0',
            'pay_source' => 'nullable|in:cards,treasury,both',
        ], $this->messages());
        $data['order_ids'] = $data['order_ids'] ?? [];
        $src = $data['pay_source'] ?? 'treasury';
        if ($src === 'treasury') {
            $data['cards'] = [];
        }
        $cardsTotal = collect($data['cards'] ?? [])->sum(fn ($v) => (float) $v);
        if ($src === 'cards' && abs($cardsTotal - (float) $data['cost']) > 0.009) {
            return back()->withInput()->with('error', 'مجموع البطاقات (' . TreasuryService::fmt($cardsTotal) . ' $) يجب أن يساوي تكلفة الرحلة (' . TreasuryService::fmt($data['cost']) . ' $)');
        }
        if ($cardsTotal > (float) $data['cost'] + 0.009) {
            return back()->withInput()->with('error', 'البطاقات (' . TreasuryService::fmt($cardsTotal) . ' $) أكثر من تكلفة الرحلة');
        }

        DB::beginTransaction();
        try {
            $trip = TripPurchase::create([
                'trip_code' => trim($data['trip_code']),
                'purchased_at' => $data['purchased_at'],
                'cost' => $data['cost'],
                'cost_currency' => $data['cost_currency'],
                'payment_id' => $data['payment_id'],
                'rate' => $data['rate'],
                'pay_currency' => $data['pay_currency'] ?? null,
                'coupon_used' => $request->boolean('coupon_used'),
                'note' => $data['note'] ?? null,
                'created_id' => auth()->id(),
            ]);
            $status = $data['trip_status'] ?: optional(Status::orderBy('sort')->first())->name;
            Order::whereIn('id', $data['order_ids'])->update(['trip_details' => $trip->trip_code, 'trip_status' => $status]);
            Order::where('trip_details', $trip->trip_code)->update(['trip_status' => $status]);

            // بطاقات SHEIN: الجزء المدفوع بها لا يظهر من الخزينة
            if ($cardsTotal > 0) {
                \App\Services\SheinCardService::payTrip($trip, $data['cards'], false);
                $trip->refresh();
            }
            if ($trip->cashCost() > 0) {
                [$amt, $cur] = $trip->treasuryCharge();
                TreasuryService::withdraw($amt, $cur, (int) $trip->payment_id, 'trip_purchase', $trip->id, 'شراء رحلة ' . $trip->trip_code . ($cardsTotal > 0 ? ' (والباقي ' . TreasuryService::fmt($cardsTotal) . ' $ ببطاقات SHEIN)' : '') . ($cur === 'dinar' ? ' بسعر ' . $trip->rate : ''));
            }

            $s = $trip->summary();
            Log::create([
                'user_id' => auth()->id(), 'type' => 'order', 'title' => ' رحلة شراء (' . $trip->trip_code . ')',
                'log' => ' شراء ' . $s['orders'] . ' إيصال بـ ' . $trip->cost . ' ' . TreasuryService::sign($trip->cost_currency) . ' بسعر ' . $trip->rate . ' - الربح ' . $s['profit_dinar'] . ' د.ل',
            ]);
            DB::commit();
        } catch (\RuntimeException $e) {
            DB::rollBack();

            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()->withInput()->with('error', 'للأسف حدث خطأ، يرجى إعادة المحاولة');
        }

        $sent = 0;
        if ($request->boolean('send_whatsapp')) {
            $trip->load('orders.customer');
            foreach ($trip->orders->groupBy('customer_id') as $group) {
                $o = $group->first();
                if (MessageService::sendTemplate('trip_added', $o->customer, MessageService::orderVars($o, ['{رقم_الإيصال}' => $group->pluck('code')->implode('، ')]), $o)) {
                    $sent++;
                }
            }
        }

        return redirect()->route('finance.trips.show', $trip->id)
            ->with('success', 'سُجّلت رحلة ' . $trip->trip_code . (auth()->user()->can('profit-view') ? ' · الربح ' . TreasuryService::fmt($s['profit_dinar']) . ' د.ل (' . TreasuryService::fmt($s['profit_dollar']) . ' $)' : '') . ($request->boolean('send_whatsapp') && $sent ? ' · أُرسلت ' . $sent . ' رسالة' : '') . (!$s['orders'] ? ' · الرحلة فارغة، أضف لها الإيصالات من صفحتها' : ''));
    }

    public function show(TripPurchase $trip)
    {
        $trip->load(['orders.customer', 'payment', 'createdUser', 'cardUses.card']);

        return view('sg.trips.show', [
            'nav' => 'trip', 'trip' => $trip, 'statuses' => Status::orderBy('sort')->get(),
            'payments' => PaymentMethod::where('status', true)->get(),
            'freeOrders' => ($fo = self::freeOrdersQuery()->latest()->get()), 'customers' => self::customersPayload($fo),
            'cards' => \App\Models\SheinCard::whereIn('status', ['new', 'partial'])->where('balance', '>', 0)->orderBy('purchased_at')->get(),
        ]);
    }

    /** تغيير حالة الرحلة لكل الإيصالات + رسالة */
    /**
     * تحويل رحلة لـ«رحلة قديمة»: يرجع المبلغ اللي انخصم للخزينة (والبطاقات لرصيدها)، التكلفة تصير غير مسجلة
     * (تطلع من تقارير الأرباح)، واختيارياً إيصالاتها تتعلّم «تم التسليم». الديون تبقى. للمدير فقط.
     */
    public function toLegacy(Request $request, TripPurchase $trip)
    {
        abort_unless(auth()->user()->hasRole('Admin'), 403, 'للمدير فقط');
        $n = 0;
        DB::beginTransaction();
        try {
            if (!$trip->legacy) {
                if ($trip->cashCost() > 0) {
                    [$amt, $cur] = $trip->treasuryCharge();
                    TreasuryService::deposit($amt, $cur, (int) $trip->payment_id, 'trip_purchase_reverse', $trip->id, 'تحويل رحلة ' . $trip->trip_code . ' لرحلة قديمة');
                }
                foreach ($trip->cardUses()->with('card', 'trip')->get() as $use) {
                    \App\Services\SheinCardService::reverse($use, false);
                }
                $trip->refresh();
                $trip->legacy = true;
                $trip->cost = 0;
                $trip->note = trim(($trip->note ? $trip->note . ' · ' : '') . 'حُوّلت لرحلة قديمة');
                $trip->save();
            }
            if ($request->boolean('deliver')) {
                $days = max(0, (int) $request->input('days', 30));
                $orders = Order::where('trip_details', $trip->trip_code)->where('delivered', false)->where('status', '!=', 'canceld')->whereNull('delivery_id')
                    ->where('created_at', '<=', now()->subDays($days))->get();
                foreach ($orders as $o) {
                    $d = optional($o->created_at)->toDateString() ?: now()->toDateString();
                    $o->arrived = true;
                    $o->arrived_at = $o->arrived_at ?: $d;
                    $o->arrived_notified_at = $o->arrived_notified_at ?: now();
                    $o->delivered = true;
                    $o->delivered_at = $o->delivered_at ?: $o->arrived_at;
                    $o->delivered_by = auth()->id();
                    $o->save();
                    $n++;
                }
                \Illuminate\Support\Facades\Cache::forget('sg.badge.ready');
            }
            Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ' تحويل رحلة لقديمة (' . $trip->trip_code . ')', 'log' => ' رجع المبلغ للخزينة · ' . $n . ' إيصال تعلّم تم التسليم (الديون باقية)']);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()->with('error', 'للأسف حدث خطأ، يرجى إعادة المحاولة');
        }

        return back()->with('success', 'الرحلة ' . $trip->trip_code . ' صارت «رحلة قديمة» · رجع المبلغ للخزينة' . ($n ? ' · ' . $n . ' إيصال تعلّم تم التسليم' : '') . ' · الديون باقية');
    }

    /** استيراد رحلات المنظومة القديمة (أرقام الرحلات المكتوبة على الطلبات) */
    public function importLegacy()
    {
        abort_unless(auth()->user()->can('trip-purchase-create'), 403);
        $n = \App\Services\LegacyTrips::import();

        return redirect()->route('sg.trips.index', ['m' => 120])->with('success', $n ? 'تم استيراد ' . $n . ' رحلة قديمة · ما تحركت أي فلوس في الخزينة · تقدر توا تغيّر حالاتها وتعدل بيانات عملائها' : 'لا توجد رحلات قديمة غير مستوردة');
    }

    public function status(Request $request, TripPurchase $trip)
    {
        $data = $request->validate(['trip_status' => 'required|string|max:191', 'send_whatsapp' => 'nullable|boolean']);
        Order::where('trip_details', $trip->trip_code)->where('delivered', false)->update(['trip_status' => $data['trip_status']]);
        Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ' حالة رحلة (' . $trip->trip_code . ')', 'log' => ' الحالة أصبحت ' . $data['trip_status']]);
        $sent = 0;
        if ($request->boolean('send_whatsapp')) {
            $orders = Order::with('customer')->where('trip_details', $trip->trip_code)->where('delivered', false)->where('status', '!=', 'canceld')->get();
            foreach ($orders->groupBy('customer_id') as $group) {
                $o = $group->first();
                if (MessageService::sendTemplate('trip_status', $o->customer, MessageService::orderVars($o, ['{رقم_الإيصال}' => $group->pluck('code')->implode('، ')]), $o)) {
                    $sent++;
                }
            }
        }

        return back()->with('success', 'الحالة أصبحت "' . $data['trip_status'] . '"' . ($request->boolean('send_whatsapp') ? ' · أُرسلت ' . $sent . ' رسالة' : ''));
    }

    /** يفك أرقام الرحلات المكتوبة (فاصلة/سطر/مسافة) ويلقى كل رحلة */
    protected static function findTrips(string $raw): array
    {
        $out = [];
        foreach (array_unique(array_filter(array_map('trim', preg_split('/[\s,،;]+/u', strtoupper($raw))))) as $code) {
            $t = TripPurchase::whereRaw('UPPER(trip_code) = ?', [$code])->first();
            if (!$t && ctype_digit($code)) {
                $many = TripPurchase::where('trip_code', 'like', '%-' . $code)->take(2)->get();
                $t = $many->count() === 1 ? $many->first() : null;
            }
            $out[$code] = $t;
        }

        return $out;
    }

    /** معاينة: كل رحلة شن حالتها توا قبل التغيير */
    public function bulkPreview(Request $request)
    {
        $rows = [];
        foreach (self::findTrips((string) $request->input('codes')) as $code => $t) {
            if (!$t) {
                $rows[] = ['code' => $code, 'found' => false];
                continue;
            }
            $open = Order::where('trip_details', $t->trip_code)->where('delivered', false)->where('status', '!=', 'canceld');
            $st = (clone $open)->selectRaw('trip_status, COUNT(*) n')->groupBy('trip_status')->orderByDesc('n')->pluck('n', 'trip_status');
            $rows[] = ['code' => $t->trip_code, 'found' => true, 'id' => $t->id, 'status' => $st->keys()->first() ?: '—', 'mixed' => $st->count() > 1, 'n' => (int) $st->sum(),
                'all' => $st->map(fn ($n, $k) => ($k ?: '—') . ' (' . $n . ')')->values()->implode(' · ')];
        }

        return response()->json(['rows' => $rows]);
    }

    /** 🔁 تغيير حالة مجموعة رحلات مرة وحدة */
    public function bulkStatus(Request $request)
    {
        $data = $request->validate(['codes' => 'required|string|max:3000', 'trip_status' => 'required|string|max:191', 'send_whatsapp' => 'nullable|boolean']);
        $done = [];
        $missing = [];
        $sent = 0;
        foreach (self::findTrips($data['codes']) as $code => $t) {
            if (!$t) {
                $missing[] = $code;
                continue;
            }
            Order::where('trip_details', $t->trip_code)->where('delivered', false)->update(['trip_status' => $data['trip_status']]);
            Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ' حالة رحلة (' . $t->trip_code . ')', 'log' => ' الحالة أصبحت ' . $data['trip_status'] . ' (تغيير جماعي)']);
            if ($request->boolean('send_whatsapp')) {
                $orders = Order::with('customer')->where('trip_details', $t->trip_code)->where('delivered', false)->where('status', '!=', 'canceld')->get();
                foreach ($orders->groupBy('customer_id') as $group) {
                    $o = $group->first();
                    if (MessageService::sendTemplate('trip_status', $o->customer, MessageService::orderVars($o, ['{رقم_الإيصال}' => $group->pluck('code')->implode('، ')]), $o)) {
                        $sent++;
                    }
                }
            }
            $done[] = $t->trip_code;
        }

        return back()->with($done ? 'success' : 'error', ($done ? '✓ ' . count($done) . ' رحلة صارت "' . $data['trip_status'] . '": ' . implode('، ', $done) : 'ما لقيناش ولا رحلة')
            . ($missing ? ' · ⚠ ما لقيناش: ' . implode('، ', $missing) : '') . ($request->boolean('send_whatsapp') ? ' · ' . $sent . ' رسالة' : ''));
    }

    public function update(Request $request, TripPurchase $trip)
    {
        $data = $request->validate($this->rules($trip), $this->messages());

        DB::beginTransaction();
        try {
            if ((float) $data['cost'] + 0.009 < (float) $trip->cards_amount) {
                throw new \RuntimeException('التكلفة أقل من الذي دُفع ببطاقات SHEIN (' . TreasuryService::fmt($trip->cards_amount) . ' $)');
            }
            if (!$trip->legacy && $trip->cashCost() > 0) {
                [$amt0, $cur0] = $trip->treasuryCharge();
                TreasuryService::deposit($amt0, $cur0, (int) $trip->payment_id, 'trip_purchase_reverse', $trip->id, 'تعديل رحلة ' . $trip->trip_code);
            }
            $oldCode = $trip->trip_code;
            $trip->fill([
                'trip_code' => trim($data['trip_code']), 'purchased_at' => $data['purchased_at'], 'cost' => $data['cost'],
                'cost_currency' => $data['cost_currency'], 'payment_id' => $data['payment_id'], 'rate' => $data['rate'],
                'coupon_used' => $request->boolean('coupon_used'), 'note' => $data['note'] ?? null,
            ] + ($request->filled('pay_currency') ? ['pay_currency' => $data['pay_currency']] : []))->save();
            if ($oldCode !== $trip->trip_code) {
                Order::where('trip_details', $oldCode)->update(['trip_details' => $trip->trip_code]);
            }
            if (!$trip->legacy && $trip->cashCost() > 0) {
                [$amt1, $cur1] = $trip->treasuryCharge();
                TreasuryService::withdraw($amt1, $cur1, (int) $trip->payment_id, 'trip_purchase', $trip->id, 'شراء رحلة ' . $trip->trip_code);
            }
            Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ' تعديل رحلة شراء (' . $trip->trip_code . ')', 'log' => ' تكلفة ' . $trip->cost . ' بسعر ' . $trip->rate]);
            DB::commit();

            return back()->with('success', 'تعدلت الرحلة');
        } catch (\RuntimeException $e) {
            DB::rollBack();

            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()->withInput()->with('error', 'للأسف حدث خطأ، يرجى إعادة المحاولة');
        }
    }

    public function attach(Request $request, TripPurchase $trip)
    {
        $data = $request->validate(['order_ids' => 'required|array|min:1', 'order_ids.*' => 'integer|exists:orders,id'], array_merge($this->messages(), ['order_ids.required' => 'اختار إيصال واحد على الأقل']));
        // الرحلة فاضية؟ ناخذو أول حالة في الترتيب
        $status = optional($trip->orders()->first())->trip_status ?: Status::orderBy('sort')->value('name');
        $ids = self::freeOrdersQuery()->whereIn('id', $data['order_ids'])->pluck('id');
        Order::whereIn('id', $ids)->update(array_filter(['trip_details' => $trip->trip_code, 'trip_status' => $status]));
        Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ' إضافة إيصالات لرحلة (' . $trip->trip_code . ')', 'log' => $ids->count() . ' إيصال']);
        $sent = 0;
        if ($request->boolean('send_whatsapp') && $ids->count()) {
            foreach (Order::with('customer')->whereIn('id', $ids)->get()->groupBy('customer_id') as $group) {
                $o = $group->first();
                if (MessageService::sendTemplate('trip_added', $o->customer, MessageService::orderVars($o, ['{رقم_الإيصال}' => $group->pluck('code')->implode('، ')]), $o)) {
                    $sent++;
                }
            }
        }

        return back()->with('success', 'تزاد ' . $ids->count() . ' إيصال لرحلة ' . $trip->trip_code . ($request->boolean('send_whatsapp') ? ' · تبعتت ' . $sent . ' رسالة' : ''));
    }

    /** من صفحة العميل: نختارو الرحلة ونزيدولها إيصالاته */
    /** 🚫✈ «بدون رحلة» لعدة إيصالات مرة وحدة (من شاشة الرحلات): تطلع من قائمة «ليس في رحلة» والزبون يشوف «سيصل قريباً» */
    public function noTrip(Request $request)
    {
        $data = $request->validate(['order_ids' => 'required|array|min:1', 'order_ids.*' => 'integer']);
        if (!\Illuminate\Support\Facades\Schema::hasColumn('orders', 'no_trip')) {
            return response()->json(['ok' => false, 'error' => 'شغّل التحديث أول من صفحة /system-update'], 422);
        }
        $orders = self::freeOrdersQuery()->whereIn('id', $data['order_ids'])->get();
        foreach ($orders as $o) {
            $o->no_trip = true;
            $o->save();
        }
        if ($orders->isNotEmpty()) {
            Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ' بدون رحلة (' . $orders->count() . ' إيصال)', 'log' => ' ' . $orders->pluck('code')->implode('، ')]);
        }

        return response()->json(['ok' => true, 'ids' => $orders->pluck('id')->values()]);
    }

    public function attachTo(Request $request)
    {
        $request->validate(['trip_id' => 'required|integer|exists:trip_purchases,id'], ['trip_id.required' => 'اختار الرحلة']);

        return $this->attach($request, TripPurchase::findOrFail($request->trip_id));
    }

    public function detach(TripPurchase $trip, Order $order)
    {
        if ($order->trip_details === $trip->trip_code && !$order->arrived) {
            $order->trip_details = null;
            $order->trip_status = null;
            $order->save();
        }

        return back()->with('success', 'طلع الإيصال ' . $order->code . ' من الرحلة');
    }

    public function destroy(TripPurchase $trip)
    {
        DB::beginTransaction();
        try {
            // الرحلة القديمة ما دفعناش عليها من الخزينة الجديدة، فحذفها ما يرجّع حتى شي
            $cash = $trip->legacy ? 0 : $trip->cashCost();
            [$cashAmt, $cashCur] = $trip->treasuryCharge();
            if ($cash > 0) {
                TreasuryService::deposit($cashAmt, $cashCur, (int) $trip->payment_id, 'trip_purchase_reverse', $trip->id, 'حذف رحلة ' . $trip->trip_code);
            }
            // البطاقات التي انخلصت بها الرحلة يرجعلها رصيدها
            foreach ($trip->cardUses()->with('card', 'trip')->get() as $use) {
                \App\Services\SheinCardService::reverse($use, false);
            }
            Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ' حذف رحلة شراء (' . $trip->trip_code . ')', 'log' => ' عادت ' . $cash . ' للخزينة' . ($trip->cards_amount > 0 ? ' ورجع رصيد البطاقات' : '')]);
            $trip->delete();
            DB::commit();

            return redirect()->route('sg.trips.index')->with('success', 'انحذف تسجيل الشراء ورجع المبلغ للخزينة (الإيصالات باقية برقم الرحلة)');
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()->with('error', 'للأسف حدث خطأ، يرجى إعادة المحاولة');
        }
    }

    // ------------------------------------------------------------ الاستلام الجماعي

    public function receiveForm(TripPurchase $trip)
    {
        $trip->load('orders.customer');
        $orders = $trip->orders->where('status', '!=', 'canceld')->where('delivered', false)->sortBy('id')->values();

        return view('sg.trips.receive', [
            'nav' => 'trip', 'trip' => $trip, 'orders' => $orders,
            'companies' => ShippingCompany::orderBy('name')->get(),
            'payments' => PaymentMethod::where('status', true)->get(),
            'types' => AppSettings::get('package_types'),
            'perKg' => (float) AppSettings::get('ship_price_per_kg'), 'minShip' => (float) AppSettings::get('ship_min'),
            'shelves' => AppSettings::shelves(),
            'used' => Order::where('arrived', true)->where('delivered', false)->whereNotNull('storage_num')->pluck('storage_num')->countBy(),
        ]);
    }

    public function receiveStore(Request $request, TripPurchase $trip)
    {
        // أي سطر كتبت فيه حاجة (وصل كم، الوزن، الطرود، الشحن، الرف، النوع) أو علّمته جاهز يتحفظ — «وصل كم» لو فاضي = الكمية كاملة
        $rows = collect($request->input('rows', []))->filter(fn ($r) => !empty($r['ready'])
            || collect(['received_qty', 'kilo', 'bags', 'ship', 'shelf', 'type'])->contains(fn ($k) => trim((string) ($r[$k] ?? '')) !== ''));
        if ($rows->isEmpty()) {
            return back()->withInput()->with('error', 'اكتب بيانات إيصال واحد على الأقل (وصل كم، الوزن، الطرود…)');
        }
        $saved = collect();
        // ✅ «جاهز للاستلام»: الحالة تتبدل أوتوماتيك للحالة متاع مرحلة الوصول
        $readyStatus = Status::where('stage', 'arrived')->orderBy('sort')->value('name') ?: 'جاهز للاستلام';
        DB::beginTransaction();
        try {
            foreach ($rows as $id => $r) {
                $o = Order::where('trip_details', $trip->trip_code)->find($id);
                if (!$o || $o->delivered) {
                    continue;
                }
                $isNew = !$o->arrived;
                $o->received_qty = trim((string) ($r['received_qty'] ?? '')) !== '' ? (int) $r['received_qty'] : ($o->received_qty ?? $o->qty);
                $o->bags_num = (int) ($r['bags'] ?? 1) ?: 1;
                $kilo = ($r['kilo'] ?? '') !== '' ? (float) $r['kilo'] : null;
                if ($kilo !== null) {
                    $o->kilo = $kilo;
                }
                if (($r['ship'] ?? '') !== '') {
                    $o->ship_price = (float) $r['ship'];
                } elseif ($kilo !== null) {
                    $o->ship_price = AppSettings::shipPriceFor($kilo);
                }
                // النواقص: قيمتها، ونخصموها الآن / رصيد / يُقرَّر لاحقاً
                $short = max(0, (int) $o->qty - (int) $o->received_qty);
                if ($short > 0 && !$o->missing_action && ($r['miss_value'] ?? '') !== '') {
                    $val = round((float) $r['miss_value'], 2);
                    $o->missing_qty = $short;
                    $o->missing_value = $val;
                    $act = $r['miss_action'] ?? 'later';
                    if ($val > 0 && $act === 'deduct') {
                        $o->discount = (float) $o->discount + $val;
                        $o->discount_reason = trim(($o->discount_reason ? $o->discount_reason . ' + ' : '') . 'قطع ناقصة');
                        $o->missing_action = 'deduct';
                    } elseif ($val > 0 && $act === 'credit' && $o->customer) {
                        $o->customer->credit = (float) $o->customer->credit + $val;
                        $o->customer->save();
                        $o->missing_action = 'credit';
                    }
                }
                $o->storage_num = $r['shelf'] ?? null;
                $o->package_type = $r['type'] ?? null;
                // 📦 أكثر من نوع طرد: "2 كرتون + 1 كيس" والعدد = المجموع
                $extra = collect((array) ($r['types'] ?? []))->map(fn ($x) => ['t' => trim((string) ($x['t'] ?? '')), 'n' => max(0, (int) ($x['n'] ?? 0))])
                    ->filter(fn ($x) => $x['t'] !== '' && $x['n'] > 0)->values();
                if ($extra->isNotEmpty()) {
                    $all = collect(!empty($r['type']) ? [['t' => trim((string) $r['type']), 'n' => max(1, (int) (($r['type_n'] ?? '') !== '' ? $r['type_n'] : ($r['bags'] ?? 1)))]] : [])->concat($extra);
                    $o->package_type = mb_substr($all->map(fn ($x) => $x['n'] . ' ' . $x['t'])->implode(' + '), 0, 250);
                    $o->bags_num = (int) $all->sum('n');
                }
                // «حفظ البيانات» يسجل البيانات فقط أثناء الفرز، و«جاهز للاستلام» يجعل الطلب جاهزاً (ثم مراجعة الرسائل)
                $makeReady = $request->input('after') === 'ready' && !empty($r['ready']);
                if ($makeReady) {
                    $o->arrived = true;
                    $o->arrived_at = $o->arrived_at ?: now()->toDateString();
                    $o->trip_status = $readyStatus;
                }
                if (!$o->received_at) {
                    $o->received_at = now();
                    $o->received_by = auth()->id();
                }
                $isNew = $isNew && $makeReady;
                $o->refreshPaymentStatus();
                $o->save();
                $o->isNewArrival = $isNew;
                $saved->push($o);
            }
            Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ' استلام رحلة (' . $trip->trip_code . ')', 'log' => ' سُجّل استلام ' . $saved->count() . ' إيصال']);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()->withInput()->with('error', 'للأسف حدث خطأ، يرجى إعادة المحاولة');
        }

        $readyN = $saved->filter(fn ($o) => $o->isNewArrival)->count();
        // جاهز للاستلام → صفحة مراجعة الرسائل (تُعدّل البيانات أو النص ثم تُرسل)
        if ($request->input('after') === 'ready') {
            return redirect()->route('sg.trips.notify', $trip->id)->with('success', $readyN . ' إيصال أصبح جاهزاً للاستلام · راجع الرسائل قبل الإرسال');
        }

        return back()->with('success', 'حُفظت بيانات ' . $saved->count() . ' إيصال · عندما تنتهي من الفرز اضغط «جاهز للاستلام»');
    }

    /** الطلبات المستلمة التي لم تُرسل لها رسالة الوصول بعد */
    protected function pendingNotify(TripPurchase $trip)
    {
        return Order::with('customer')->where('trip_details', $trip->trip_code)->where('arrived', true)->where('delivered', false)
            ->where('status', '!=', 'canceld')->whereNull('arrived_notified_at')->orderBy('id')->get();
    }

    public function notifyForm(TripPurchase $trip)
    {
        $orders = $this->pendingNotify($trip);
        [$tpl, $previews] = \App\Services\ArrivalNotify::previews($orders);

        return view('sg.trips.notify', [
            'nav' => 'trip', 'trip' => $trip, 'orders' => $orders, 'tpl' => $tpl, 'previews' => $previews,
            'sentCount' => Order::where('trip_details', $trip->trip_code)->whereNotNull('arrived_notified_at')->count(),
        ]);
    }

    public function notifySend(Request $request, TripPurchase $trip)
    {
        return \App\Services\ArrivalNotify::flash(\App\Services\ArrivalNotify::send($request, $this->pendingNotify($trip)));
    }
}
