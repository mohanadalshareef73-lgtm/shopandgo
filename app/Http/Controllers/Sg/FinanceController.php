<?php

namespace App\Http\Controllers\Sg;

use App\Http\Controllers\Controller;
use App\Models\CurrencyExchange;
use App\Models\Log;
use App\Models\Order;
use App\Models\Partner;
use App\Models\PartnerTransaction;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\Shift;
use App\Models\ShippingCharges;
use App\Models\ShippingCompany;
use App\Models\ShippingType;
use App\Models\TreasuryHistory;
use App\Models\TripPurchase;
use App\Models\User;
use App\Services\AppSettings;
use App\Services\Csv;
use App\Services\FinanceReport;
use App\Services\MessageService;
use App\Services\Period;
use App\Services\ShiftService;
use App\Services\TreasuryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** المالية: الخزينة · الأرباح والشركاء · شراء الدولار · شركة الشحن */
class FinanceController extends Controller
{
    /** وصف كل نوع حركة في سجل الخزينة: [العنوان، الأيقونة] */
    public const TYPES = [
        'order' => ['قبض من عميل', 'rec'], 'trip_purchase' => ['شراء رحلة', 'trip'], 'trip_purchase_reverse' => ['إلغاء شراء رحلة', 'trip'],
        'exchange' => ['شراء دولار', 'usd'], 'exchange_reverse' => ['إلغاء شراء دولار', 'usd'], 'purchase' => ['مصروف', 'exp'], 'purchase_reverse' => ['إلغاء مصروف', 'exp'],
        'shippingCharge' => ['دفع شركة الشحن', 'ship'], 'shippingCharge_reverse' => ['إلغاء دفع شحن', 'ship'], 'partner' => ['شريك', 'prt'], 'partner_reverse' => ['إلغاء حركة شريك', 'prt'],
        'dept' => ['تسديد دين علينا', 'exp'], 'transfer' => ['تحويل بين الخزائن', 'usd'], 'adjust' => ['تسوية جرد', 'usd'], 'paymentExpenses' => ['مصروفات دفع', 'exp'], 'user' => ['حركة يدوية', 'n'], 'refund' => ['إرجاع لزبون', 'exp'], 'courier' => ['إقفال حساب مندوب', 'rec'], 'shein_card' => ['شراء بطاقات SHEIN', 'usd'], 'shein_card_reverse' => ['إلغاء بطاقة SHEIN', 'usd'],
    ];

    public function __construct()
    {
        $this->middleware('permission:treasury-list', ['only' => ['index']]);
        $this->middleware('permission:treasury-create', ['only' => ['transfer']]);
        $this->middleware('permission:shippingCharge-create', ['only' => ['shipStore', 'companyStore']]);
        $this->middleware('permission:shippingCharge-delete', ['only' => ['shipDestroy']]);
    }

    public function index(Request $request)
    {
        $tab = in_array($request->tab, ['safe', 'profit', 'usd', 'ship']) ? $request->tab : 'safe';
        if ($tab === 'profit' && !auth()->user()->can('profit-view')) {
            $tab = 'safe'; // الأرباح للمدير فقط
        }
        $p = Period::make($request->input('p', $tab === 'safe' ? 'day' : 'month'));
        $payments = PaymentMethod::orderBy('id')->get();
        $balances = FinanceReport::balances();
        $rate = CurrencyExchange::latestRate() ?: (float) (TripPurchase::latest('purchased_at')->value('rate') ?: 0);
        $data = ['nav' => 'fin', 'tab' => $tab, 'period' => $p, 'payments' => $payments, 'balances' => $balances, 'rate' => $rate, 'cashId' => AppSettings::cashPaymentId()];

        if ($request->export === 'csv' && $tab === 'safe') {
            return Csv::download('treasury.csv', ['الوقت', 'النوع', 'التفاصيل', 'الخزينة', 'دخل/طلع', 'المبلغ', 'العملة', 'الرصيد بعد', 'الموظف'],
                $this->ledgerQuery($request, $p)->get()->map(fn ($h) => [$h->created_at->format('Y-m-d H:i'), self::TYPES[$h->type][0] ?? $h->type, $h->note, optional($h->payment)->name, $h->status === 'pay' ? 'دخل' : 'طلع', $h->price, $h->currency, $h->price_after, optional($h->createdUser)->name]));
        }

        return view('sg.finance.' . $tab, $data + $this->{$tab}($request, $p, $balances, $rate));
    }

    protected function ledgerQuery(Request $request, Period $p)
    {
        $q = TreasuryHistory::with(['payment', 'createdUser'])->whereBetween('created_at', [$p->from, $p->to]);
        if ($request->dir === 'in') {
            $q->where('status', 'pay');
        } elseif ($request->dir === 'out') {
            $q->where('status', 'pull');
        }
        if ($request->filled('pm')) {
            $q->where('payment_id', $request->pm);
        }
        if ($request->filled('cur')) {
            $q->where('currency', $request->cur);
        }
        if ($request->filled('q')) {
            $q->where('note', 'like', '%' . $request->q . '%');
        }

        return $q->orderByDesc('created_at')->orderByDesc('id');
    }

    /* ---------------- الخزينة ---------------- */
    protected function safe(Request $request, Period $p, array $balances, float $rate): array
    {
        $io = TreasuryHistory::whereBetween('created_at', [$p->from, $p->to])
            ->selectRaw('payment_id, currency, status, SUM(price) s')->groupBy('payment_id', 'currency', 'status')->get();
        $wallets = [];
        foreach ($balances as $pid => $b) {
            foreach (array_keys(TreasuryService::CURRENCIES) as $c) {
                $in = (float) $io->where('payment_id', $pid)->where('currency', $c)->where('status', 'pay')->sum('s');
                $out = (float) $io->where('payment_id', $pid)->where('currency', $c)->where('status', 'pull')->sum('s');
                if ($c === 'dinar' || abs($b[$c]) > 0.009 || $in || $out) {
                    $wallets[] = (object) ['pid' => $pid, 'name' => $b['name'], 'cur' => $c, 'bal' => $b[$c], 'in' => $in, 'out' => $out];
                }
            }
        }
        $toDinar = fn ($w) => $w->cur === 'dinar' ? $w->bal : ($w->cur === 'dollar' ? $w->bal * $rate : 0);
        $totalDinar = collect($wallets)->sum($toDinar);
        $startDinar = $totalDinar - collect($wallets)->where('cur', 'dinar')->sum(fn ($w) => $w->in - $w->out) - collect($wallets)->where('cur', 'dollar')->sum(fn ($w) => ($w->in - $w->out) * $rate);

        $ledger = $this->ledgerQuery($request, $p)->paginate(40)->withQueryString();

        $me = auth()->user();
        $shift = ShiftService::summary($me);
        $lastClosed = Shift::with('user')->whereNotNull('closed_at')->latest('closed_at')->first();

        return [
            'wallets' => $wallets, 'totalDinar' => $totalDinar, 'startDinar' => $startDinar, 'toDinar' => $toDinar,
            'ledger' => $ledger, 'shift' => $shift, 'lastClosed' => $lastClosed,
            'partners' => Partner::where('status', true)->get(),
        ];
    }

    /* ---------------- الأرباح والشركاء ---------------- */
    protected function profit(Request $request, Period $p): array
    {
        $r = FinanceReport::period($p->from->toDateString(), $p->to->toDateTimeString());
        $prev = FinanceReport::period($p->prevFrom->toDateString(), $p->prevTo->toDateTimeString());

        // صافي الربح لكل يوم/شهر
        if ($p->unit === 'month') {
            $buckets = collect(range(0, $p->to->month - 1))->map(fn ($i) => [$p->from->copy()->addMonths($i)->startOfMonth(), $p->from->copy()->addMonths($i)->endOfMonth(), Period::MONTHS[$i]]);
        } else {
            $start = $p->preset === 'day' ? now()->subDays(13)->startOfDay() : $p->from->copy();
            $n = (int) $start->diffInDays($p->to) + 1;
            $buckets = collect(range(0, $n - 1))->map(fn ($i) => [$start->copy()->addDays($i), $start->copy()->addDays($i)->endOfDay(), $start->copy()->addDays($i)->format('j/n')]);
        }
        $daily = ['labels' => [], 'values' => []];
        $tripsAll = TripPurchase::countable()->with('orders')->whereBetween('purchased_at', [$buckets->first()[0]->toDateString(), $buckets->last()[1]->toDateTimeString()])->get();
        $shipIn = Order::where('status', '!=', 'canceld')->where('ship_price', '>', 0)->whereNotNull('received_at')->whereBetween('received_at', [$buckets->first()[0], $buckets->last()[1]])->get(['ship_price', 'received_at']);
        $shipOut = ShippingCharges::whereBetween('date', [$buckets->first()[0]->toDateString(), $buckets->last()[1]->toDateTimeString()])->get(['cost', 'date']);
        $exp = Purchase::whereBetween('date', [$buckets->first()[0]->toDateString(), $buckets->last()[1]->toDateTimeString()])->get(['cost', 'date']);
        foreach ($buckets as [$a, $b, $lbl]) {
            $in = fn ($d) => $d && Carbon::parse($d)->between($a, $b);
            $v = $tripsAll->filter(fn ($t) => $in($t->purchased_at))->sum(fn ($t) => $t->summary()['profit_dinar'])
                + $shipIn->filter(fn ($o) => $in($o->received_at))->sum('ship_price')
                - $shipOut->filter(fn ($s) => $in($s->date))->sum('cost')
                - $exp->filter(fn ($e) => $in($e->date))->sum('cost');
            $daily['labels'][] = $lbl;
            $daily['values'][] = round($v);
        }

        $trips = TripPurchase::countable()->with('orders')->whereBetween('purchased_at', [$p->from->toDateString(), $p->to->toDateTimeString()])->latest('purchased_at')->get();
        $partners = Partner::where('status', true)->get();
        $ptx = PartnerTransaction::whereBetween('date', [$p->from->toDateString(), $p->to->toDateTimeString()])->selectRaw('partner_id, type, SUM(amount) s')->where('currency', 'dinar')->groupBy('partner_id', 'type')->get();

        return [
            'r' => $r, 'prev' => $prev, 'daily' => $daily, 'trips' => $trips, 'partners' => $partners, 'ptx' => $ptx,
            'surplus' => FinanceReport::undistributedSurplus()['dinar'],
            'recentTx' => PartnerTransaction::with('partner')->latest('date')->latest('id')->take(8)->get(),
        ];
    }

    /* ---------------- شراء الدولار ---------------- */
    protected function usd(Request $request, Period $p, array $balances, float $rate): array
    {
        $q = CurrencyExchange::with(['fromPayment', 'toPayment', 'createdUser'])->whereBetween('date', [$p->from->toDateString(), $p->to->toDateTimeString()]);
        if ($request->filled('kind')) {
            $q->where('kind', $request->kind);
        }
        $rows = $q->latest('date')->latest('id')->get();
        $buy = $rows->where('to_currency', 'dollar');
        $prevBuy = CurrencyExchange::where('to_currency', 'dollar')->whereBetween('date', [$p->prevFrom->toDateString(), $p->prevTo->toDateTimeString()])->get();
        $avg = $buy->sum('to_amount') ? $buy->sum('from_amount') / $buy->sum('to_amount') : null;
        $prevAvg = $prevBuy->sum('to_amount') ? $prevBuy->sum('from_amount') / $prevBuy->sum('to_amount') : null;
        $spent = (float) TripPurchase::where('cost_currency', 'dollar')->whereBetween('purchased_at', [$p->from->toDateString(), $p->to->toDateTimeString()])->sum('cost');
        $chartRows = CurrencyExchange::where('to_currency', 'dollar')->where('date', '>=', now()->subDays(90))->orderBy('date')->orderBy('id')->get();
        $dollarWallets = collect($balances)->map(fn ($b, $id) => ['id' => $id, 'name' => $b['name'], 'bal' => $b['dollar']]);

        return [
            'rows' => $rows, 'buyUsd' => $buy->sum('to_amount'), 'buyLyd' => $buy->sum('from_amount'), 'avg' => $avg, 'prevAvg' => $prevAvg,
            'usdNow' => $dollarWallets->sum('bal'), 'spent' => $spent, 'dollarWallets' => $dollarWallets,
            'chart' => ['labels' => $chartRows->map(fn ($e) => $e->date->format('j/n'))->values(), 'cur' => $chartRows->pluck('rate')->map(fn ($v) => (float) $v)->values(), 'curName' => 'السعر', 'prevName' => ''],
            'visaId' => PaymentMethod::where('name', 'like', '%فيزا%')->orWhere('name', 'like', '%visa%')->value('id'),
            'cardDigits' => (int) AppSettings::get('shein_card_digits', 16), 'pinDigits' => (int) AppSettings::get('shein_pin_digits', 4),
        ];
    }

    /* ---------------- مصروفات الشحن (الذي ندفعوه لشركة الشحن) ---------------- */
    protected function ship(Request $request, Period $p): array
    {
        $r = FinanceReport::period($p->from->toDateString(), $p->to->toDateTimeString());
        $q = ShippingCharges::with(['company', 'payment'])->whereBetween('date', [$p->from->toDateString(), $p->to->toDateTimeString()]);
        if ($request->filled('co')) {
            $q->where('company_id', $request->co);
        }
        $charges = $q->orderByDesc('date')->orderByDesc('id')->get();
        $companies = ShippingCompany::orderBy('id')->get();
        $byCo = $charges->groupBy(fn ($c) => $c->company ?: 'بدون شركة')->map->sum('cost')->sortDesc();
        $months = collect(range(5, 0))->map(fn ($i) => now()->startOfMonth()->subMonths($i));

        return [
            'r' => $r, 'charges' => $charges, 'companies' => $companies, 'byCo' => $byCo,
            'six' => [
                'labels' => $months->map(fn ($m) => Period::MONTHS[$m->month - 1])->values(),
                'values' => $months->map(fn ($m) => (float) ShippingCharges::whereBetween('date', [$m->toDateString(), $m->copy()->endOfMonth()->toDateTimeString()])->sum('cost'))->values(),
            ],
        ];
    }

    /** مصروف شحن: دفعنا لشركة الشحن (بدون ربط برحلة) */
    public function shipStore(Request $request)
    {
        $data = $request->validate([
            'trip_purchase_id' => 'nullable|exists:trip_purchases,id',
            'company_id' => 'nullable|exists:shipping_companies,id',
            'weight' => 'nullable|numeric|min:0',
            'parcels_count' => 'nullable|integer|min:0',
            'cost' => 'required|numeric|min:0.01',
            'payment_id' => 'required|exists:payment_methods,id',
            'currency' => 'nullable|in:dinar,dollar,euro',
            'date' => 'nullable|date',
            'note' => 'nullable|string|max:300',
        ], ['cost.required' => 'اكتب المبلغ']);
        $cur = $data['currency'] ?? 'dinar';
        DB::beginTransaction();
        try {
            $type = ShippingType::first();
            if (!$type) {
                $type = new ShippingType;
                $type->name = 'شحن جوي';
                $type->created_id = auth()->id();
                $type->save();
            }
            $trip = !empty($data['trip_purchase_id']) ? TripPurchase::find($data['trip_purchase_id']) : null;
            $co = !empty($data['company_id']) ? ShippingCompany::find($data['company_id']) : null;
            $c = new ShippingCharges;
            $c->weight = $data['weight'] ?? 0;
            $c->cost = $data['cost'];
            $c->type_id = $type->id;
            $c->date = $data['date'] ?? now()->toDateString();
            $c->note = $data['note'] ?? null;
            $c->created_id = auth()->id();
            $c->payment_id = $data['payment_id'];
            $c->currency = $cur;
            $c->currency_sign = TreasuryService::sign($cur);
            $c->parcels_count = $data['parcels_count'] ?? null;
            $c->company_id = optional($co)->id;
            $c->company = optional($co)->name;
            $c->trip_purchase_id = optional($trip)->id;
            $c->save();
            TreasuryService::withdraw((float) $data['cost'], $cur, (int) $data['payment_id'], 'shippingCharge', $c->id, 'مصروف شحن' . ($co ? ' · ' . $co->name : '') . (!empty($data['note']) ? ' · ' . $data['note'] : ''));
            Log::create(['user_id' => auth()->id(), 'type' => 'shippingCharge', 'title' => ' دفع شحن', 'log' => ' دفع ' . $data['cost'] . ' لشركة الشحن ' . ($trip ? 'للرحلة ' . $trip->trip_code : '')]);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()->withInput()->with('error', $e instanceof \RuntimeException ? $e->getMessage() : 'للأسف حدث خطأ، يرجى إعادة المحاولة');
        }

        return back()->with('success', 'سُجّل مصروف الشحن · ' . TreasuryService::fmt($data['cost']) . ' ' . TreasuryService::sign($cur));
    }

    public function shipDestroy(ShippingCharges $charge)
    {
        DB::beginTransaction();
        try {
            if ($charge->payment_id) {
                TreasuryService::deposit((float) $charge->cost, $charge->currency ?: 'dinar', (int) $charge->payment_id, 'shippingCharge_reverse', $charge->id, 'إلغاء دفع شحن');
            }
            $charge->delete();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->with('error', 'تعذّر علينا حذفه');
        }

        return back()->with('success', 'انحذف ورجع المبلغ للخزينة');
    }

    public function companyStore(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:80', 'route' => 'nullable|string|max:120', 'price_per_kg' => 'nullable|numeric|min:0', 'icon' => 'nullable|string|max:8']);
        ShippingCompany::create($data);

        return back()->with('success', 'انضافت الشركة');
    }

    /** تحويل بين خزينتين بنفس العملة (نقداً ↔ مصرف) */
    public function transfer(Request $request)
    {
        $data = $request->validate([
            'from_id' => 'required|exists:payment_methods,id', 'to_id' => 'required|exists:payment_methods,id|different:from_id',
            'currency' => 'required|in:dinar,dollar,euro', 'amount' => 'required|numeric|min:0.01', 'note' => 'nullable|string|max:200',
        ], ['to_id.different' => 'اختر خزينتين مختلفتين']);
        DB::beginTransaction();
        try {
            $from = PaymentMethod::find($data['from_id']);
            $to = PaymentMethod::find($data['to_id']);
            $note = 'تحويل من ' . $from->name . ' إلى ' . $to->name . (!empty($data['note']) ? ' — ' . $data['note'] : '');
            $h = TreasuryService::withdraw((float) $data['amount'], $data['currency'], $from->id, 'transfer', 0, $note);
            TreasuryService::deposit((float) $data['amount'], $data['currency'], $to->id, 'transfer', $h->id, $note);
            Log::create(['user_id' => auth()->id(), 'type' => 'Treasury', 'title' => ' تحويل بين الخزائن', 'log' => ' ' . $note . ' بقيمة ' . $data['amount']]);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->with('error', $e instanceof \RuntimeException ? $e->getMessage() : 'للأسف حدث خطأ، يرجى إعادة المحاولة');
        }

        return back()->with('success', 'تم التحويل · ' . TreasuryService::fmt($data['amount']) . ' ' . TreasuryService::sign($data['currency']));
    }

    /** صفحة ضبط الأرصدة (جرد): كل الخزائن بكل العملات */
    public function adjustForm()
    {
        abort_unless(auth()->user()->hasRole('Admin'), 403, 'للمدير فقط');
        $rows = [];
        foreach (PaymentMethod::orderBy('id')->get() as $pm) {
            foreach (array_keys(TreasuryService::CURRENCY_NAMES) as $cur) {
                $rows[] = ['pm' => $pm, 'cur' => $cur, 'bal' => TreasuryService::balance($cur, $pm->id)];
            }
        }
        $recent = \App\Models\TreasuryHistory::where('type', 'adjust')->latest('id')->take(10)->get();

        return view('sg.finance.adjust', ['nav' => 'fin', 'rows' => $rows, 'recent' => $recent]);
    }

    /**
     * ضبط الرصيد (جرد): المدير يكتب الرصيد الحقيقي لخزينة أو أكثر، والفرق يتسجل حركة «تسوية جرد».
     * لا يدخل في الأرباح ولا الديون — يصحح رصيد الخزينة فقط، ويتسجل قبل/بعد في السجل.
     * actual[pm_id][currency] = الرصيد الحقيقي (الخانات الفارغة تُتجاهل). only = "pm:cur" لضبط سطر واحد.
     */
    public function adjust(Request $request)
    {
        abort_unless(auth()->user()->hasRole('Admin'), 403, 'للمدير فقط');
        $data = $request->validate([
            'actual' => 'required|array', 'actual.*' => 'array', 'actual.*.*' => 'nullable|numeric|min:0',
            'note' => 'required|string|max:200', 'only' => 'nullable|string|max:40',
        ], ['note.required' => 'اكتب سبب الضبط (مثلاً: جرد 15 أكتوبر، كشف حساب المصرف)', 'actual.*.*.numeric' => 'اكتب أرقام فقط في خانات الرصيد']);
        $only = $data['only'] ?? null;
        $done = [];
        DB::beginTransaction();
        try {
            foreach ($data['actual'] as $pmId => $curs) {
                foreach ($curs as $cur => $val) {
                    if ($val === null || $val === '' || !array_key_exists($cur, TreasuryService::CURRENCY_NAMES)) {
                        continue;
                    }
                    if ($only && $only !== $pmId . ':' . $cur) {
                        continue;
                    }
                    $pm = PaymentMethod::find($pmId);
                    if (!$pm) {
                        continue;
                    }
                    $before = TreasuryService::balance($cur, $pm->id);
                    $actual = round((float) $val, 2);
                    $diff = round($actual - $before, 2);
                    if (abs($diff) < 0.01) {
                        continue;
                    }
                    $note = 'تسوية جرد ' . $pm->name . ' (' . TreasuryService::CURRENCY_NAMES[$cur] . '): كان ' . TreasuryService::fmt($before) . ' وأصبح ' . TreasuryService::fmt($actual) . ' — ' . $data['note'];
                    $diff > 0
                        ? TreasuryService::deposit($diff, $cur, $pm->id, 'adjust', 0, $note)
                        : TreasuryService::withdraw(-$diff, $cur, $pm->id, 'adjust', 0, $note, true);
                    Log::create(['user_id' => auth()->id(), 'type' => 'Treasury', 'title' => ' ضبط رصيد خزينة', 'log' => ' ' . $note . ' (الفرق ' . ($diff > 0 ? '+' : '') . TreasuryService::fmt($diff) . ')']);
                    $done[] = $pm->name . ' ' . TreasuryService::sign($cur) . ' ← ' . TreasuryService::fmt($actual) . ' (' . ($diff > 0 ? '+' : '') . TreasuryService::fmt($diff) . ')';
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()->withInput()->with('error', 'للأسف حدث خطأ، لم يتغير أي رصيد. يرجى إعادة المحاولة');
        }
        if (!$done) {
            return back()->withInput()->with('success', 'لا يوجد فرق — الأرصدة مظبوطة كما هي');
        }

        return redirect()->route('sg.finance.adjust.form')->with('success', 'تم ضبط ' . count($done) . ' رصيد: ' . implode(' · ', $done));
    }

    /** تقفيل الكاش (وردية الموظف الحالي أو موظف آخر للمدير) */
    public function closeCash(Request $request)
    {
        $data = $request->validate(['counted' => 'required|numeric|min:0', 'note' => 'nullable|string|max:300', 'user_id' => 'nullable|exists:users,id']);
        $user = !empty($data['user_id']) && auth()->user()->can('user-edit') ? User::find($data['user_id']) : auth()->user();
        $shift = ShiftService::close($user, (float) $data['counted'], $data['note'] ?? null);
        $phones = AppSettings::phonesFor('shift');
        if ($phones) {
            $tpl = MessageService::template('shift_closed');
            if ($tpl && ($tpl->enabled ?? true)) {
                MessageService::sendText('shift_closed', MessageService::render($tpl->message, [
                    '{الموظف}' => $user->name, '{المفروض}' => TreasuryService::fmt($shift->expected_cash), '{المسلّم}' => TreasuryService::fmt($shift->counted_cash), '{الفرق}' => TreasuryService::fmt($shift->diff),
                ]), $phones);
            }
        }
        $d = (float) $shift->diff;
        if (abs($d) > 0.009) {
            \App\Services\AlertService::push('shift_diff', '💼 فرق في تقفيل وردية ' . $user->name, 'المفروض ' . TreasuryService::fmt($shift->expected_cash) . ' · المسلَّم ' . TreasuryService::fmt($shift->counted_cash) . ' · الفرق ' . TreasuryService::fmt($d) . ' د.ل', route('sg.reports', ['type' => 'shifts']), $d < 0 ? 'warn' : 'info');
        }

        return back()->with(abs($d) < 0.01 ? 'success' : 'warning', 'تقفلت وردية ' . $user->name . (abs($d) < 0.01 ? ' بدون فرق ✓' : ' · الفرق ' . TreasuryService::fmt($d) . ' د.ل'));
    }
}
