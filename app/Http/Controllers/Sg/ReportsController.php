<?php

namespace App\Http\Controllers\Sg;

use App\Http\Controllers\Controller;
use App\Models\CurrencyExchange;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PaidHistory;
use App\Models\Purchase;
use App\Models\Shift;
use App\Models\TreasuryHistory;
use App\Models\TripPurchase;
use App\Models\User;
use App\Services\AppSettings;
use App\Services\Csv;
use App\Services\MessageService;
use App\Services\Period;
use App\Services\ReportService;
use App\Services\TreasuryService as TS;
use Illuminate\Http\Request;

/** التقارير: قائمة تقارير، كل واحد يظهر طباعة / PDF / Excel / واتساب */
class ReportsController extends Controller
{
    /** [المجموعة => [مفتاح => [الاسم، الشرح، اللون، الأيقونة]]] */
    public const LIST = [
        'عامة' => ['daily' => ['التقرير اليومي', 'تقفيل اليوم: قبض، صرف، تسليم', '#262c4a', '📅'], 'summary' => ['التقرير الشامل', 'الربح، المبلغ، النشاط', '#262c4a', '📊']],
        'المالية' => ['pl' => ['الأرباح والخسائر', 'ربح كل رحلة والصافي', '#12805c', '💰'], 'profit_detail' => ['الأرباح بالتفصيل', 'كل بند ربح أو تكلفة، مع فلاتر', '#0f8a5f', '🔍'], 'treasury' => ['حركة الخزينة', 'كل دخل وطلع بالتفصيل', '#12805c', '🏦'], 'expenses' => ['المصاريف', 'حسب النوع', '#eb6834', '🧾'], 'usd' => ['شراء الدولار', 'كم شرينا وبكم السعر', '#2a78d6', '💵']],
        'الطلبات والزبائن' => ['trips' => ['الرحلات', 'إيصالات كل رحلة وحالتها', '#6250d6', '✈️'], 'debts' => ['الديون', 'من عليه وكم ومن متى', '#c4352f', '⏳'], 'statement' => ['كشف حساب عميل', 'كل إيصالاته ودفعاته', '#2a78d6', '👤'], 'customers' => ['العملاء الجدد والنشطين', 'من طلب أكثر', '#2a78d6', '📈']],
        'الموظفين' => ['staff' => ['شغل الموظفين', 'إيصالات، قبض، تسليم لكل واحد', '#1baf7a', '👥'], 'shifts' => ['تقفيل الورديات', 'الفروقات إن وجدت', '#1baf7a', '🔐']],
    ];

    /** تقارير تكشف الأرباح والتكاليف: تظهر لمن لديه صلاحية profit-view فقط */
    public const PROFIT_ONLY = ['summary', 'pl', 'profit_detail'];

    /** مصادر الربح والتكلفة في تقرير الأرباح بالتفصيل */
    public const PROFIT_SOURCES = ['trips' => 'رحلات الشراء', 'shipping' => 'الشحن', 'cards' => 'بيع بطاقات SHEIN', 'expenses' => 'المصاريف', 'other' => 'خسائر أخرى'];

    public function __construct()
    {
        $this->middleware('permission:reports-list');
    }

    public static function find(string $key): ?array
    {
        foreach (self::LIST as $g) {
            if (isset($g[$key])) {
                return $g[$key];
            }
        }

        return null;
    }

    public function index(Request $request)
    {
        $canProfit = auth()->user()->can('profit-view');
        $type = self::find((string) $request->type) ? $request->type : ($canProfit ? 'summary' : 'daily');
        // تقارير الأرباح للمدير فقط
        if (!$canProfit && in_array($type, self::PROFIT_ONLY, true)) {
            $type = 'daily';
        }
        $preset = $request->input('p', $type === 'daily' ? 'day' : 'month');
        $p = Period::make($preset, $request->from, $request->to);
        if ($type === 'daily' || $type === 'summary') {
            $data = ['summary' => ReportService::summary($p)];
            if ($type === 'daily') {
                $data['daily'] = $this->daily($p);
            }
            $table = null;
        } else {
            $table = $this->{'t_' . $type}($p, $request);
            $data = [];
        }

        if ($request->export === 'csv') {
            $t = $table ?? $this->summaryTable($data['summary']);

            return Csv::download($type . '-' . $p->from->format('Y-m-d') . '.csv', $t['head'], array_merge($t['rows'], isset($t['foot']) ? [$t['foot']] : []));
        }

        return view('sg.reports.index', $data + [
            'nav' => 'rep', 'type' => $type, 'info' => self::find($type), 'period' => $p, 'table' => $table,
            'print' => $request->boolean('print'),
            'customers' => $type === 'statement' ? Customer::orderBy('name')->get(['id', 'name', 'code']) : collect(),
            'phones' => implode('، ', AppSettings::reportPhones()),
            'monthly' => (bool) AppSettings::get('monthly_report_enabled'),
        ]);
    }

    public function whatsapp(Request $request)
    {
        $p = Period::make($request->input('p', 'month'), $request->from, $request->to);
        $phones = AppSettings::reportPhones();
        if (!$phones) {
            return back()->with('error', 'لا يوجد أرقام للتقارير. زيدها من الإعدادات');
        }
        $text = ReportService::whatsappText(ReportService::summary($p));
        $ok = 0;
        foreach ($phones as $ph) {
            $ok += MessageService::sendText('monthly_report', $text, [$ph]) ? 1 : 0;
        }

        return back()->with($ok ? 'success' : 'warning', $ok ? "أُرسل التقرير لـ $ok رقم" : 'لم يُرسل — تأكد من خدمة الواتساب');
    }

    protected function summaryTable(array $s): array
    {
        $r = $s['r'];

        return ['head' => ['البند', 'القيمة'], 'rows' => [
            ['الطلبات', $s['orders']], ['قيمة الإيصالات', $s['ordersValue']], ['مبيعات الرحلات', $r['sales']['dinar']], ['تكلفة الشراء', $r['cost']['dinar']],
            ['ربح الرحلات', $r['goodsProfit']['dinar']], ['دخل الشحن', $r['shipIncome']['dinar']], ['دفعنا للشحن', $r['shipCost']['dinar']], ['المصاريف', $r['expenses']['dinar']],
            ['صافي الربح', $r['net']['dinar']], ['قبضنا من العملاء', $s['collected']], ['شراء دولار (دينار)', $s['usdPaid']], ['سحوبات الشركاء', $s['partners']],
            ['الديون عند العملاء', $s['receivables']], ['عملاء جدد', $s['newCustomers']], ['طلبيات تسلمت', $s['delivered']],
        ]];
    }

    protected function daily(Period $p): array
    {
        $h = TreasuryHistory::whereBetween('created_at', [$p->from, $p->to])->where('currency', 'dinar')->get();
        $cash = AppSettings::cashPaymentId();

        return [
            'cashIn' => $h->where('payment_id', $cash)->where('status', 'pay')->sum('price'), 'cashOut' => $h->where('payment_id', $cash)->where('status', 'pull')->sum('price'),
            'bankIn' => $h->where('payment_id', '!=', $cash)->where('status', 'pay')->sum('price'), 'bankOut' => $h->where('payment_id', '!=', $cash)->where('status', 'pull')->sum('price'),
            'shifts' => Shift::with('user')->whereBetween('closed_at', [$p->from, $p->to])->get(),
        ];
    }

    /* ----- تقارير الجداول: ترجع head / rows / foot ----- */
    /** الأرباح بالتفصيل: كل بند (رحلة، دخل شحن، دفع شحن، بطاقة مباعة، مصروف…) مع فلاتر المصدر والمنصة والبحث */
    protected function t_profit_detail(Period $p, Request $request): array
    {
        $from = $p->from->toDateString();
        $to = $p->to->toDateTimeString();
        $src = array_intersect((array) $request->input('src', array_keys(self::PROFIT_SOURCES)), array_keys(self::PROFIT_SOURCES)) ?: array_keys(self::PROFIT_SOURCES);
        $pf = $request->filled('pf') ? (int) $request->pf : null;
        $qtxt = trim((string) $request->q);
        $rows = [];
        $add = function ($date, $source, $item, $detail, $income, $cost) use (&$rows, $qtxt) {
            if ($qtxt !== '' && !str_contains($item . ' ' . $detail, $qtxt)) {
                return;
            }
            $rows[] = [(string) $date, self::PROFIT_SOURCES[$source], $item, $detail, round($income, 2), round($cost, 2), round($income - $cost, 2)];
        };
        if (in_array('trips', $src, true)) {
            foreach (TripPurchase::countable()->with('orders.platform')->whereBetween('purchased_at', [$from, $to])->orderBy('purchased_at')->get() as $t) {
                if ($pf && !$t->orders->contains('platform_id', $pf)) {
                    continue;
                }
                $s = $t->summary();
                $add($t->purchased_at->format('Y-m-d'), 'trips', 'رحلة ' . $t->trip_code, $s['orders'] . ' إيصال · ' . $t->orders->map(fn ($o) => optional($o->platform)->name)->filter()->unique()->implode('، ') . ' · ' . TS::fmt($t->cost) . ' ' . TS::sign($t->cost_currency) . ' × ' . $t->rate, $s['sales_dinar'], $s['cost_dinar']);
            }
        }
        if (in_array('shipping', $src, true)) {
            $ords = \App\Models\Order::with('customer')->where('status', '!=', 'canceld')->where('ship_price', '>', 0)
                ->where(fn ($q) => $q->where(fn ($q) => $q->whereNotNull('received_at')->whereBetween('received_at', [$from, $to]))->orWhere(fn ($q) => $q->whereNull('received_at')->whereBetween('arrived_at', [$from, $to])))
                ->when($pf, fn ($q) => $q->where('platform_id', $pf))->get();
            foreach ($ords as $o) {
                $add(optional($o->received_at)->format('Y-m-d') ?: $o->arrived_at, 'shipping', 'دخل شحن · ' . $o->code, optional($o->customer)->name . ($o->kilo ? ' · ' . $o->kilo . ' كغ' : ''), (float) $o->ship_price, 0);
            }
            foreach (\App\Models\ShippingCharges::with('company')->whereBetween('date', [$from, $to])->get() as $c) {
                $add($c->date, 'shipping', 'دفع لشركة الشحن', trim((optional($c->company)->name ?? '') . ($c->weight ? ' · ' . $c->weight . ' كغ' : '') . ' ' . ($c->note ?? '')), 0, (float) $c->cost);
            }
        }
        if (in_array('cards', $src, true) && \Illuminate\Support\Facades\Schema::hasColumn('shein_cards', 'sold_order_id')) {
            foreach (\App\Models\SheinCard::with(['soldOrder', 'soldCustomer'])->whereNotNull('sold_order_id')->whereBetween('sold_at', [$from, $to])->get() as $c) {
                if (optional($c->soldOrder)->status === 'canceld') {
                    continue;
                }
                $add(optional($c->sold_at)->format('Y-m-d'), 'cards', 'بطاقة ' . $c->masked() . ' · ' . TS::fmt($c->face_value) . '$', optional($c->soldCustomer)->name . ' · ' . optional($c->soldOrder)->code, (float) $c->sale_price, (float) $c->cost_dinar);
            }
        }
        if (in_array('expenses', $src, true) && !$pf) {
            foreach (\App\Models\Purchase::with('desc')->whereBetween('date', [$from, $to])->where(fn ($q) => $q->whereNull('currency')->orWhere('currency', 'dinar'))->get() as $e) {
                $add($e->date, 'expenses', optional($e->desc)->name ?: 'مصروف', (string) $e->note, 0, (float) $e->cost);
            }
            foreach (\App\Models\PaymentExpenses::where('status', true)->whereBetween('date', [$from, $to])->get() as $e) {
                $add($e->date, 'expenses', 'مصروفات دفع', (string) ($e->note ?? ''), 0, (float) $e->cost);
            }
        }
        if (in_array('other', $src, true) && !$pf) {
            if (\Illuminate\Support\Facades\Schema::hasTable('courier_settlements')) {
                foreach (\App\Models\CourierSettlement::with('courier')->whereBetween('created_at', [$from, $to])->where('rounding', '!=', 0)->get() as $cs) {
                    $add($cs->created_at->format('Y-m-d'), 'other', 'كسور مع المندوب', optional($cs->courier)->name, 0, (float) $cs->rounding);
                }
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('deliveries', 'fee_payer')) {
                foreach (\App\Models\Delivery::with('courier')->where('status', 'delivered')->where('fee_payer', 'company')->where('fee', '>', 0)->whereBetween('delivered_at', [$from, $to])->get() as $dl) {
                    $add($dl->delivered_at->format('Y-m-d'), 'other', 'أجرة توصيل على الشركة', $dl->code . ' · ' . optional($dl->courier)->name, 0, (float) $dl->fee);
                }
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('shein_cards')) {
                foreach (\App\Models\SheinCard::whereNotNull('lost_at')->whereBetween('lost_at', [$from, $to])->get() as $c) {
                    $add($c->lost_at->format('Y-m-d'), 'other', 'بطاقة SHEIN ' . $c->statusLabel(), $c->masked(), 0, (float) $c->lost_dinar);
                }
                foreach (\App\Models\SheinCardUse::with(['card', 'order'])->whereNull('trip_purchase_id')->whereBetween('created_at', [$from, $to])->get() as $u) {
                    $add($u->created_at->format('Y-m-d'), 'other', 'بطاقة SHEIN على إيصال بدون رحلة', optional($u->order)->code . ' · ' . optional($u->card)->masked(), 0, (float) $u->cost_dinar);
                }
            }
        }
        usort($rows, fn ($a, $b) => strcmp($a[0], $b[0]));
        $inc = array_sum(array_column($rows, 4));
        $cost = array_sum(array_column($rows, 5));

        return ['head' => ['التاريخ', 'المصدر', 'البند', 'التفاصيل', 'الدخل', 'التكلفة', 'الربح'], 'rows' => $rows,
            'foot' => ['المجموع', count($rows) . ' بند', '', '', $inc, $cost, $inc - $cost], 'num' => [4, 5, 6], 'filters' => 'profit'];
    }

    protected function t_pl(Period $p): array
    {
        $trips = TripPurchase::countable()->with('orders')->whereBetween('purchased_at', [$p->from->toDateString(), $p->to->toDateTimeString()])->orderBy('purchased_at')->get();
        $rows = $trips->map(function ($t) {
            $s = $t->summary();

            return [$t->trip_code, $t->purchased_at->format('Y-m-d'), $s['orders'], round($s['sales_dinar']), $t->cost . ' ' . TS::sign($t->cost_currency), $t->rate, round($s['cost_dinar']), round($s['profit_dinar']), $s['margin'] . '%'];
        })->all();

        return ['head' => ['الرحلة', 'التاريخ', 'إيصالات', 'للعملاء', 'دفعنا', 'السعر', 'بالدينار', 'الربح', 'النسبة'], 'rows' => $rows,
            'foot' => ['المجموع', '', array_sum(array_column($rows, 2)), array_sum(array_column($rows, 3)), '', '', array_sum(array_column($rows, 6)), array_sum(array_column($rows, 7)), ''], 'num' => [2, 3, 6, 7]];
    }

    protected function t_treasury(Period $p): array
    {
        $rows = TreasuryHistory::with(['payment', 'createdUser'])->whereBetween('created_at', [$p->from, $p->to])->orderBy('created_at')->get()
            ->map(fn ($h) => [$h->created_at->format('Y-m-d H:i'), FinanceController::TYPES[$h->type][0] ?? $h->type, (string) $h->note, optional($h->payment)->name, $h->status === 'pay' ? round($h->price, 2) : '', $h->status === 'pull' ? round($h->price, 2) : '', TS::sign($h->currency), round($h->price_after, 2), optional($h->createdUser)->name])->all();

        return ['head' => ['الوقت', 'النوع', 'التفاصيل', 'الخزينة', 'دخل', 'طلع', 'العملة', 'الرصيد بعد', 'الموظف'], 'rows' => $rows, 'num' => [4, 5, 7]];
    }

    protected function t_expenses(Period $p): array
    {
        $rows = Purchase::with(['desc', 'payment'])->whereBetween('date', [$p->from->toDateString(), $p->to->toDateTimeString()])->orderBy('date')->get()
            ->map(fn ($e) => [$e->date, optional($e->desc)->name, (string) $e->note, optional($e->payment)->name, round($e->cost, 2), TS::sign($e->currency ?: 'dinar')])->all();

        return ['head' => ['التاريخ', 'النوع', 'التفاصيل', 'الخزينة', 'المبلغ', 'العملة'], 'rows' => $rows, 'foot' => ['المجموع', '', '', '', array_sum(array_column($rows, 4)), ''], 'num' => [4]];
    }

    protected function t_usd(Period $p): array
    {
        $rows = CurrencyExchange::with(['fromPayment', 'toPayment'])->whereBetween('date', [$p->from->toDateString(), $p->to->toDateTimeString()])->orderBy('date')->get()
            ->map(fn ($e) => [$e->date->format('Y-m-d'), CurrencyExchange::KINDS[$e->kind] ?? $e->kind, optional($e->fromPayment)->name, round($e->from_amount, 2), optional($e->toPayment)->name, round($e->to_amount, 2), $e->rate])->all();
        $paid = array_sum(array_column($rows, 3));
        $got = array_sum(array_column($rows, 5));

        return ['head' => ['التاريخ', 'النوع', 'من', 'دفعنا', 'أين', 'استلمنا', 'السعر'], 'rows' => $rows, 'foot' => ['المجموع', '', '', $paid, '', $got, $got ? round($paid / $got, 3) : ''], 'num' => [3, 5]];
    }

    protected function t_trips(Period $p): array
    {
        $rows = TripPurchase::countable()->with('orders')->whereBetween('purchased_at', [$p->from->toDateString(), $p->to->toDateTimeString()])->orderBy('purchased_at')->get()
            ->map(function ($t) {
                $o = $t->orders->where('status', '!=', 'canceld');

                return [$t->trip_code, $t->purchased_at->format('Y-m-d'), $t->summary()['status'], $o->count(), $o->pluck('customer_id')->unique()->count(), $o->where('arrived', true)->count(), $o->where('delivered', true)->count(), round($o->sum(fn ($x) => max(0, $x->remaining())))];
            })->all();

        return ['head' => ['الرحلة', 'التاريخ', 'الحالة', 'إيصالات', 'عملاء', 'وصلت', 'تسلّمت', 'باقي على العملاء'], 'rows' => $rows, 'num' => [3, 4, 5, 6, 7]];
    }

    protected function t_debts(Period $p): array
    {
        $rows = Order::with('customer')->where('status', '!=', 'canceld')->whereRaw(OrdersController::debtSql())->orderBy('created_at')->get()
            ->map(fn ($o) => [optional($o->customer)->name, optional($o->customer)->code, optional($o->customer)->phone_1, $o->code, $o->delivered ? 'استلم' : ($o->arrived ? 'في الرف' : 'في الطريق'), round($o->total_price + $o->ship_price - $o->discount), round($o->paid_price), round($o->remaining(), 2), (int) $o->created_at->diffInDays(now())])->all();

        return ['head' => ['العميل', 'الكود', 'الهاتف', 'الإيصال', 'أين', 'الفاتورة+الشحن', 'دفع', 'الباقي', 'العمر (يوم)'], 'rows' => $rows, 'foot' => ['المجموع', '', '', count($rows) . ' إيصال', '', '', '', array_sum(array_column($rows, 7)), ''], 'num' => [5, 6, 7]];
    }

    protected function t_statement(Period $p, Request $request): array
    {
        $c = Customer::find($request->customer_id);
        if (!$c) {
            return ['head' => ['اختر عميل من فوق'], 'rows' => [], 'need' => 'customer'];
        }
        $rows = [];
        foreach (Order::where('customer_id', $c->id)->orderBy('created_at')->get() as $o) {
            $rows[] = [$o->created_at->format('Y-m-d'), 'إيصال ' . $o->code . ($o->status === 'canceld' ? ' (ملغي)' : ''), $o->status === 'canceld' ? 0 : round($o->total_price - $o->discount, 2), ''];
            if ($o->ship_price > 0 && $o->status !== 'canceld') {
                $rows[] = [optional($o->received_at)->format('Y-m-d') ?: $o->arrived_at, 'شحن ' . $o->code, round($o->ship_price, 2), ''];
            }
            foreach (PaidHistory::with('paymentMethod')->where('order_id', $o->id)->orderBy('created_at')->get() as $ph) {
                $rows[] = [$ph->created_at->format('Y-m-d'), 'دفعة ' . optional($ph->paymentMethod)->name . ' على ' . $o->code, '', round($ph->paid_price, 2)];
            }
        }
        usort($rows, fn ($a, $b) => strcmp((string) $a[0], (string) $b[0]));
        $bal = 0;
        foreach ($rows as &$r) {
            $bal += (float) ($r[2] ?: 0) - (float) ($r[3] ?: 0);
            $r[] = round($bal, 2);
        }

        return ['head' => ['التاريخ', 'البيان', 'عليه', 'دفع', 'الرصيد'], 'rows' => $rows, 'foot' => ['', 'الباقي عليه', '', '', round($bal, 2)], 'num' => [2, 3, 4], 'subtitle' => $c->name . ' · ' . $c->code . ' · ' . $c->phone_1];
    }

    protected function t_customers(Period $p): array
    {
        $rows = Order::with('customer')->where('status', '!=', 'canceld')->whereBetween('created_at', [$p->from, $p->to])->get()->groupBy('customer_id')
            ->map(fn ($g) => [optional($g->first()->customer)->name, optional($g->first()->customer)->code, optional($g->first()->customer)->phone_1, optional($g->first()->customer)->created_at && $g->first()->customer->created_at->gte($p->from) ? 'جديد' : 'قديم', $g->count(), round($g->sum('total_price'))])
            ->sortByDesc(fn ($r) => $r[5])->values()->all();

        return ['head' => ['العميل', 'الكود', 'الهاتف', 'النوع', 'طلبات', 'قيمة'], 'rows' => $rows, 'foot' => [count($rows) . ' عميل', '', '', count(array_filter($rows, fn ($r) => $r[3] === 'جديد')) . ' جديد', array_sum(array_column($rows, 4)), array_sum(array_column($rows, 5))], 'num' => [4, 5]];
    }

    protected function t_staff(Period $p): array
    {
        $rows = User::whereIn('type_id', [1, 2])->get()->map(fn ($u) => [
            $u->name, Order::where('created_id', $u->id)->whereBetween('created_at', [$p->from, $p->to])->count(),
            Order::where('received_by', $u->id)->whereBetween('received_at', [$p->from, $p->to])->count(),
            Order::where('delivered_by', $u->id)->whereBetween('delivered_time', [$p->from, $p->to])->count(),
            round(TreasuryHistory::where('created_id', $u->id)->where('status', 'pay')->where('currency', 'dinar')->whereBetween('created_at', [$p->from, $p->to])->sum('price')),
            round(TreasuryHistory::where('created_id', $u->id)->where('status', 'pull')->where('currency', 'dinar')->whereBetween('created_at', [$p->from, $p->to])->sum('price')),
        ])->sortByDesc(fn ($r) => $r[1] + $r[3])->values()->all();

        return ['head' => ['الموظف', 'إيصالات', 'استلام بضاعة', 'تسليم', 'قبض', 'صرف'], 'rows' => $rows, 'num' => [1, 2, 3, 4, 5]];
    }

    protected function t_shifts(Period $p): array
    {
        $rows = Shift::with(['user', 'closer'])->whereBetween('closed_at', [$p->from, $p->to])->orderBy('closed_at')->get()
            ->map(fn ($s) => [optional($s->user)->name, $s->opened_at->format('Y-m-d H:i'), $s->closed_at->format('H:i'), round($s->expected_cash, 2), round($s->counted_cash, 2), round($s->diff, 2), optional($s->closer)->name, (string) $s->note])->all();

        return ['head' => ['الموظف', 'فتح', 'قفل', 'المفروض', 'سلّم', 'الفرق', 'قفلها', 'ملاحظة'], 'rows' => $rows, 'foot' => ['المجموع', '', '', array_sum(array_column($rows, 3)), array_sum(array_column($rows, 4)), array_sum(array_column($rows, 5)), '', ''], 'num' => [3, 4, 5]];
    }
}
