<?php

namespace App\Http\Controllers\Sg;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Dept;
use App\Models\DeptHistory;
use App\Models\Log;
use App\Models\Order;
use App\Models\PaidHistory;
use App\Models\PaymentMethod;
use App\Services\Csv;
use App\Services\MessageService;
use App\Services\OrderPaymentService;
use App\Services\ShiftService;
use App\Services\TreasuryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * الديون:
 *  - ديون العملاء = باقي الإيصالات (الفاتورة + الشحن − الخصم − المدفوع)
 *  - علينا للناس = جدول depts القديم (تسديده يسحب من الخزينة)
 *  - رصيد العملاء لدينا = customers.credit (قطع ناقصة مثلا)
 */
class DebtsController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:dept-list', ['only' => ['index', 'statement', 'reminders']]);
        $this->middleware('permission:dept-create', ['only' => ['collect', 'remind', 'storePayable', 'payPayable']]);
    }

    /** الإيصال في الرف: وصل (arrived) أو حالته جاهز/وصل ومش مستلم */
    public static function onShelf($o): bool
    {
        if ($o->delivered) {
            return false;
        }
        if ($o->arrived) {
            return true;
        }
        $st = (string) $o->trip_status;

        return $st !== '' && (str_contains($st, 'جاهز') || \App\Services\AppSettings::statusStage($st) === 'arrived');
    }

    /** كل الإيصالات التي عليها باقي، مجمعة حسب العميل */
    protected function customerDebts()
    {
        $orders = Order::with('customer')->where('status', '!=', 'canceld')->whereRaw(OrdersController::debtSql())->get();
        $lastPay = PaidHistory::whereIn('customer_id', $orders->pluck('customer_id')->unique())->selectRaw('customer_id, MAX(created_at) d')->groupBy('customer_id')->pluck('d', 'customer_id');

        return $orders->groupBy('customer_id')->map(function ($g) use ($lastPay) {
            $oldest = $g->min('created_at');
            $where = $g->some(fn ($o) => $o->delivered) ? 'gone' : ($g->some(fn ($o) => self::onShelf($o)) ? 'shelf' : 'way');

            return (object) [
                'customer' => $g->first()->customer,
                'orders' => $g,
                'total' => $g->sum(fn ($o) => $o->total_price + $o->ship_price - $o->discount),
                'paid' => $g->sum('paid_price'),
                'due' => $g->sum(fn ($o) => $o->remaining()),
                'age' => $oldest ? (int) $oldest->diffInDays(now()) : 0,
                'where' => $where,
                'shelf' => $g->filter(fn ($o) => self::onShelf($o))->count(),
                'way' => $g->filter(fn ($o) => !$o->delivered && !self::onShelf($o))->count(),
                'gone' => $g->where('delivered', true)->count(),
                'last_pay' => $lastPay[$g->first()->customer_id] ?? null,
            ];
        })->filter(fn ($r) => $r->customer)->values();
    }

    public function index(Request $request)
    {
        $tab = $request->input('tab', 'customers');
        $all = $this->customerDebts();
        $orders = $all->flatMap(fn ($r) => $r->orders);
        $kpi = [
            'due' => $all->sum('due'), 'customers' => $all->count(), 'orders' => $orders->count(),
            'shelf' => $orders->filter(fn ($o) => self::onShelf($o))->sum(fn ($o) => $o->remaining()),
            'way' => $orders->filter(fn ($o) => !$o->delivered && !self::onShelf($o))->sum(fn ($o) => $o->remaining()),
            'gone' => $orders->where('delivered', true)->sum(fn ($o) => $o->remaining()),
            'goneN' => $orders->where('delivered', true)->pluck('customer_id')->unique()->count(),
        ];
        $buckets = [
            'w1' => ['أقل من أسبوع', 0, 7], 'm1' => ['من 8 لـ 30 يوم', 8, 30], 'm2' => ['من 31 لـ 60 يوم', 31, 60], 'old' => ['أكثر من 60 يوم', 61, 100000],
        ];
        $ageing = collect($buckets)->map(fn ($b) => ['label' => $b[0], 'rows' => $all->filter(fn ($r) => $r->age >= $b[1] && $r->age <= $b[2])]);

        $rows = $all;
        if ($request->filled('q')) {
            $t = mb_strtolower(trim($request->q));
            $d = preg_replace('/\D/', '', $t);
            $rows = $rows->filter(fn ($r) => str_contains(mb_strtolower($r->customer->name), $t) || str_contains((string) $r->customer->code, trim($request->q)) || (strlen($d) >= 4 && (str_contains((string) $r->customer->phone_1, $d) || str_contains((string) $r->customer->phone_2, $d))));
        }
        if ($request->filled('w')) {
            $rows = $rows->filter(fn ($r) => $r->{$request->w} > 0);
        }
        if ($request->filled('age') && isset($buckets[$request->age])) {
            [$l, $a, $b] = $buckets[$request->age];
            $rows = $rows->filter(fn ($r) => $r->age >= $a && $r->age <= $b);
        }
        $rows = (match ($request->input('sort', 'old')) {
            'big' => $rows->sortByDesc('due'),
            'new' => $rows->sortBy('age'),
            default => $rows->sortByDesc('age'),
        })->values();

        if ($request->export === 'csv') {
            return Csv::download('debts.csv', ['العميل', 'الكود', 'الهاتف', 'الإيصالات', 'الفاتورة+الشحن', 'دفع', 'الباقي', 'عمر الدين (يوم)'],
                $rows->map(fn ($r) => [$r->customer->name, $r->customer->code, $r->customer->phone_1, $r->orders->pluck('code')->implode(' '), round($r->total, 2), round($r->paid, 2), round($r->due, 2), $r->age]));
        }

        $payables = Dept::with(['customer', 'paids'])->orderByRaw("status = 'paid'")->orderByDesc('date')->get();
        $credits = Customer::where('credit', '>', 0)->orderByDesc('credit')->get();
        $history = null;
        if ($tab === 'payments') {
            $history = PaidHistory::with(['customer', 'order', 'paymentMethod'])->latest()->paginate(40)->withQueryString();
        }

        return view('sg.debts.index', [
            'nav' => 'debt', 'tab' => $tab, 'kpi' => $kpi, 'ageing' => $ageing, 'rows' => $rows,
            'payables' => $payables, 'credits' => $credits, 'history' => $history,
            'payments' => PaymentMethod::where('status', true)->get(),
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    /** قبض مبلغ من عميل: يتوزع على إيصالاته من الأقدم */
    public function collect(Request $request, Customer $customer)
    {
        $data = $request->validate(['amount' => 'required|numeric|min:0.01', 'payment_id' => 'required|exists:payment_methods,id', 'send_whatsapp' => 'nullable|boolean']);
        $orders = Order::where('customer_id', $customer->id)->where('status', '!=', 'canceld')->whereRaw(OrdersController::debtSql())->orderBy('created_at')->get();
        $due = $orders->sum(fn ($o) => $o->remaining());
        if ($data['amount'] > $due + 0.01) {
            return back()->with('error', 'المبلغ أكبر من الذي عليه (' . TreasuryService::fmt($due) . ')');
        }
        $left = (float) $data['amount'];
        $touched = collect();
        DB::beginTransaction();
        try {
            ShiftService::ensureOpen(auth()->user());
            foreach ($orders as $o) {
                if ($left <= 0.009) {
                    break;
                }
                $take = round(min($left, $o->remaining()), 2);
                OrderPaymentService::pay($o, $take, (int) $data['payment_id'], 'تسديد دين');
                $left -= $take;
                $touched->push($o->code);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()->with('error', $e instanceof \RuntimeException ? $e->getMessage() : 'للأسف حدث خطأ، يرجى إعادة المحاولة');
        }
        if ($request->boolean('send_whatsapp')) {
            $rest = $due - $data['amount'];
            MessageService::sendTemplate('payment_received', $customer, MessageService::customerVars($customer, ['{رقم_الإيصال}' => $touched->implode('، '), '{المدفوع_الآن}' => TreasuryService::fmt($data['amount']), '{الباقي}' => TreasuryService::fmt($rest)]));
        }

        return back()->with('success', 'قبضنا ' . TreasuryService::fmt($data['amount']) . ' د.ل من ' . $customer->name . ' على ' . $touched->implode('، '));
    }

    /** تذكير على واتساب: لا يُرسل إلا بتأكيد (زر من الإدارة) */
    public function remind(Request $request)
    {
        $ids = $request->filled('customer_id') ? [(int) $request->customer_id] : collect($request->input('ids', []))->map(fn ($v) => (int) $v)->filter()->all();
        if (!$ids) {
            return back()->with('warning', 'اختر عميل واحد على الأقل');
        }
        $r = \App\Services\ReminderService::sendDebt($ids);
        Log::create(['user_id' => auth()->id(), 'type' => 'dept', 'title' => ' تذكير ديون', 'log' => ' أُرسل لـ ' . $r['sent'] . ' عميل' . ($r['failed'] ? ' · فشل ' . $r['failed'] : '')]);

        return back()->with($r['sent'] ? 'success' : 'warning', $r['sent'] ? "أُرسل التذكير لـ {$r['sent']} عميل" . ($r['failed'] ? " · {$r['failed']} لم تصل" : '') : 'لم يُرسل أو تذكير — تأكد من قالب "تذكير دين" ومن خدمة الواتساب');
    }

    /** قائمة التذكيرات التي تستنى تأكيد */
    public function reminders(Request $request)
    {
        $all = $request->boolean('all');
        $rows = $all ? $this->customerDebts()->map(fn ($r) => (object) ['customer' => $r->customer, 'orders' => $r->orders, 'due' => $r->due, 'age' => $r->age, 'last' => $r->customer->debt_reminded_at, 'gone' => $r->gone])->sortByDesc('due')->values()
            : \App\Services\ReminderService::debtDue();
        $tpl = \App\Services\MessageService::template('debt_reminder');

        return view('sg.debts.reminders', [
            'nav' => 'debt', 'rows' => $rows, 'all' => $all, 'tpl' => $tpl,
            'rules' => ['days' => \App\Services\AppSettings::get('debt_remind_days', 14), 'every' => \App\Services\AppSettings::get('debt_remind_every', 7), 'min' => \App\Services\AppSettings::get('debt_remind_min', 5)],
        ]);
    }

    /** دين على الشركة: لعميل أو لأي جهة خارجية */
    public function storePayable(Request $request)
    {
        $data = $request->validate([
            'customer_id' => 'nullable|exists:customers,id', 'org' => 'required_without:customer_id|nullable|string|max:120',
            'phone' => 'nullable|string|max:30', 'total' => 'required|numeric|min:0.01', 'date' => 'required|date',
            'due_date' => 'nullable|date', 'note' => 'nullable|string|max:500',
            'into_treasury' => 'nullable|boolean', 'payment_id' => 'required_if:into_treasury,1|nullable|exists:payment_methods,id',
        ], ['org.required_without' => 'اكتب اسم الدائن أو اختر عميل']);
        DB::beginTransaction();
        try {
            $d = new Dept;
            $d->customer_id = $data['customer_id'] ?? null;
            $d->org = $data['org'] ?? null;
            $d->phone = $data['phone'] ?? null;
            $d->total = $data['total'];
            $d->date = $data['date'];
            $d->due_date = $data['due_date'] ?? null;
            $d->note = $data['note'] ?? null;
            $d->status = 'dept';
            $d->created_id = auth()->id();
            $d->save();
            // لو استلفنا كاش فعلا: يدخل الخزينة
            if ($request->boolean('into_treasury')) {
                TreasuryService::deposit((float) $d->total, 'dinar', (int) $data['payment_id'], 'dept_in', $d->id, 'دين علينا من ' . ($d->org ?: optional($d->customer)->name));
            }
            Log::create(['user_id' => auth()->id(), 'type' => 'dept', 'title' => ' دين على الشركة (' . ($d->org ?: optional($d->customer)->name) . ')', 'log' => ' تم اضافة دين بقيمة (' . $d->total . ')' . ($request->boolean('into_treasury') ? ' ودخل الخزينة' : '')]);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()->withInput()->with('error', $e instanceof \RuntimeException ? $e->getMessage() : 'للأسف حدث خطأ، يرجى إعادة المحاولة');
        }

        return back()->with('success', 'سُجّل الدين على الشركة');
    }

    /** نسدد جزء من دين علينا: يُصرف من الخزينة */
    public function payPayable(Request $request, Dept $dept)
    {
        $data = $request->validate(['amount' => 'required|numeric|min:0.01', 'payment_id' => 'required|exists:payment_methods,id']);
        $rest = $dept->total - $dept->paids->sum('paid_price');
        if ($data['amount'] > $rest + 0.01) {
            return back()->with('error', 'المبلغ أكبر من الباقي (' . TreasuryService::fmt($rest) . ')');
        }
        DB::beginTransaction();
        try {
            $h = new DeptHistory;
            $h->dept_id = $dept->id;
            $h->paid_price = $data['amount'];
            $h->created_id = auth()->id();
            $h->currency = 'dinar';
            $h->currency_sign = 'د.ل';
            $h->payment_id = $data['payment_id'];
            $h->save();
            TreasuryService::withdraw((float) $data['amount'], 'dinar', (int) $data['payment_id'], 'dept', $dept->id, 'تسديد دين ' . ($dept->org ?: optional($dept->customer)->name));
            $dept->status = $rest - $data['amount'] <= 0.01 ? 'paid' : 'dept';
            $dept->save();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->with('error', $e instanceof \RuntimeException ? $e->getMessage() : 'للأسف حدث خطأ، يرجى إعادة المحاولة');
        }

        return back()->with('success', 'تسدد ' . TreasuryService::fmt($data['amount']) . ' د.ل');
    }

    /** كشف حساب للطباعة / PDF */
    public function statement(Request $request)
    {
        $rows = $this->customerDebts()->sortByDesc('age')->values();
        if ($request->filled('customer_id')) {
            $rows = $rows->where('customer.id', (int) $request->customer_id)->values();
        }

        return view('sg.debts.statement', ['rows' => $rows]);
    }
}
