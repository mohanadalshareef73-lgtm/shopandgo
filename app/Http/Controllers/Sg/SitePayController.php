<?php

namespace App\Http\Controllers\Sg;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Log;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Platform;
use App\Services\AppSettings;
use App\Services\OrderNumber;
use App\Services\OrderPaymentService;
use App\Services\TreasuryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** 🌐 إيصال دفع مواقع: العميل يدفع لنا وإحنا ندفعوا للموقع عنه */
class SitePayController extends Controller
{
    public const PLATFORM = 'دفع مواقع';

    public function __construct()
    {
        $this->middleware('permission:order-create', ['only' => ['index', 'store']]);
        $this->middleware('permission:order-edit', ['only' => ['cost', 'sites']]);
    }

    protected static function platformId(): int
    {
        $p = Platform::where('name', self::PLATFORM)->first();
        if (!$p) {
            $p = new Platform;
            $p->name = self::PLATFORM;
            $p->save();
        }

        return (int) $p->id;
    }

    public static function rate(): float
    {
        return (float) (\App\Models\CurrencyExchange::latestRate() ?: (\App\Models\Setting::query()->value('usd_to_lyd_rate') ?: 0));
    }

    public function index(Request $request)
    {
        $tab = $request->input('tab', 'new');
        $q = Order::with(['customer', 'createdUser'])->where('kind', 'site_pay')->latest('id');
        if ($tab === 'nocost') {
            $q->whereNull('site_cost');
        }
        $rows = $q->paginate(30)->withQueryString();
        $base = Order::where('kind', 'site_pay')->where('status', '!=', 'canceld');
        $month = (clone $base)->where('created_at', '>=', now()->startOfMonth());

        $data = [
            'nav' => 'ord', 'tab' => $tab, 'rows' => $rows,
            'sites' => (array) AppSettings::get('pay_sites', []),
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'code', 'phone_1']),
            'payments' => PaymentMethod::where('status', true)->get(), 'cashId' => AppSettings::cashPaymentId(),
            'rate' => self::rate(),
            'saleRate' => (float) (AppSettings::get('site_sale_rate') ?: AppSettings::get('card_sale_rate') ?: self::rate()),
            'commPct' => (float) AppSettings::get('site_commission_pct', 5),
            'usdPays' => PaymentMethod::where('status', true)->get()->filter(fn ($pm) => TreasuryService::balance('dollar', $pm->id) != 0)->values(),
            'noCost' => (clone $base)->whereNull('site_cost')->count(),
            'billCurs' => self::billCurrencies(),
            'kpi' => [
                'n' => (clone $month)->count(),
                'sales' => (float) (clone $month)->where(fn ($w) => $w->whereNull('currency')->orWhere('currency', 'dinar'))->sum('total_price'),
                'salesUsd' => (float) (clone $month)->where('currency', 'dollar')->sum('total_price'),
                // الربح: اللي انكتب بزر «إضافة ربح»، ولو ما انكتبش: القيمة − الكلفة (دينار بس)
                'profit' => (float) (clone $month)->get()->sum(fn ($o) => $o->site_profit_lyd !== null ? (float) $o->site_profit_lyd
                    : (($o->site_cost_lyd !== null && ($o->currency ?: 'dinar') === 'dinar') ? (float) $o->total_price - (float) $o->site_cost_lyd : 0)),
            ],
        ];
        // لو صار أي خطأ، يطلع مكتوب في الصفحة بدل 500
        try {
            return response(view('sg.sitepay.index', $data)->render());
        } catch (\Throwable $e) {
            report($e);

            return response('<meta charset="utf-8"><div dir="rtl" style="font-family:Tahoma;padding:30px;font-size:16px">⚠ صفحة دفع المواقع فيها مشكلة — صوّر هذا وابعته:<pre dir="ltr" style="white-space:pre-wrap;background:#f4f4f4;padding:12px">'
                . e(mb_substr($e->getMessage(), 0, 800)) . "\n" . e(basename($e->getFile())) . ':' . $e->getLine() . '</pre><a href="' . route('sg.orders.index') . '">← رجوع للطلبات</a></div>', 200);
        }
    }

    /** عملات الفواتير (تقدر تزيد عليها من الصفحة) */
    public static function billCurrencies(): array
    {
        $l = (array) AppSettings::get('bill_currencies', []);

        return $l ?: ['يوان', 'دولار', 'درهم', 'يورو', 'ليرة تركي', 'ريال سعودي'];
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'name' => 'required_without:customer_id|nullable|string|max:120',
            'phone' => 'required_without:customer_id|nullable|string|max:20',
            'site' => 'required|string|max:80',
            'total' => 'required|numeric|min:0.01',
            'paid' => 'nullable|numeric|min:0',
            'payment_id' => 'nullable|exists:payment_methods,id',
            'note' => 'nullable|string|max:300',
            'send_whatsapp' => 'nullable|boolean',
            'bill_usd' => 'nullable|numeric|min:0', 'bill_cur' => 'nullable|string|max:20', 'recv_cur' => 'nullable|in:dinar,dollar', 'paid_now' => 'nullable|boolean', 'cost_pid' => 'nullable|exists:payment_methods,id', 'cost_usd' => 'nullable|numeric|min:0',
        ], ['name.required_without' => 'اكتب اسم العميل', 'phone.required_without' => 'اكتب رقم العميل', 'site.required' => 'اختار الموقع', 'total.required' => 'اكتب قيمة الإيصال']);
        $paid = min((float) ($data['paid'] ?? 0), (float) $data['total']);
        if ($request->filled('sale_rate') || $request->filled('comm_pct')) {
            AppSettings::put(array_filter(['site_sale_rate' => (float) $request->input('sale_rate') ?: null, 'site_commission_pct' => $request->filled('comm_pct') ? (float) $request->input('comm_pct') : null], fn ($v) => $v !== null));
        }
        if ($paid > 0 && empty($data['payment_id'])) {
            return back()->withInput()->with('error', 'اختار طريقة الدفع');
        }
        $dup = \App\Support\DupCheck::find(!empty($data['customer_id']) ? (int) $data['customer_id'] : null, $data['phone'] ?? null, (float) $data['total'], 1);
        if ($dup && !$request->boolean('confirm_dup')) {
            return back()->withInput()->with('dup_warn', \App\Support\DupCheck::message($dup));
        }
        DB::beginTransaction();
        try {
            $c = !empty($data['customer_id']) ? Customer::find($data['customer_id']) : null;
            if (!$c) {
                $phone = preg_replace('/\D/', '', (string) $data['phone']);
                $c = Customer::where('phone_1', $phone)->orWhere('phone_1', 'like', '%' . substr($phone, -9))->first();
                if (!$c) {
                    $c = new Customer;
                    $c->name = trim($data['name']);
                    $c->phone_1 = $phone;
                    $c->code = ClientsController::codeFor($phone);
                    $c->password = bcrypt($phone);
                    $c->save();
                }
            }
            $o = new Order;
            $o->code = OrderNumber::next();
            $o->kind = 'site_pay';
            $o->customer_id = $c->id;
            $o->platform_id = self::platformId();
            $o->site_name = trim($data['site']);
            $o->site_bill_usd = !empty($data['bill_usd']) ? round((float) $data['bill_usd'], 2) : null; // قيمة الفاتورة (بعملتها)
            if (\Illuminate\Support\Facades\Schema::hasColumn('orders', 'site_bill_currency')) {
                $o->site_bill_currency = $data['bill_cur'] ?? null;
            }
            $o->qty = 1;
            $o->total_price = round((float) $data['total'], 2);
            $o->paid_price = 0;
            $o->status = 'unpaid';
            $rc = ($data['recv_cur'] ?? 'dinar') === 'dollar' ? 'dollar' : 'dinar'; // استلمنا من العميل دينار ولا دولار
            $o->currency = $rc;
            $o->currency_sign = $rc === 'dollar' ? '$' : 'د.ل';
            $o->payment_id = $data['payment_id'] ?? null;
            $o->note = trim('دفع موقع: ' . $o->site_name . (!empty($data['note']) ? ' · ' . $data['note'] : ''));
            $o->created_id = auth()->id();
            // خدمة دفع: ما فيش بضاعة، تعتبر مسلّمة طول
            $o->arrived = true;
            $o->arrived_at = now()->toDateString();
            $o->received_at = now();
            $o->received_by = auth()->id();
            $o->received_qty = 1;
            $o->delivered = true;
            $o->delivered_time = now();
            $o->delivered_by = auth()->id();
            $o->arrived_notified_at = now();
            $o->save();
            \App\Models\OrderItem::create(['order_id' => $o->id, 'name' => 'دفع موقع ' . $o->site_name, 'quantity' => 1, 'price' => $o->total_price]);
            if ($paid > 0) {
                OrderPaymentService::pay($o, $paid, (int) $data['payment_id'], 'دفع موقع ' . $o->site_name);
            } else {
                $o->refreshPaymentStatus();
                $o->save();
            }
            // خلصناها توا بالفيزا: الكلفة بالدولار تنسحب من خزينة الدولار
            if ($request->boolean('use_calc') && $request->boolean('paid_now') && !empty($data['cost_pid'])) {
                $usd = (float) ($data['cost_usd'] ?? 0) ?: (float) ($data['bill_usd'] ?? 0);
                if ($usd > 0) {
                    self::applyCost($o, $usd, 'dollar', self::rate(), (int) $data['cost_pid']);
                }
            }
            if ($dup) {
                \App\Support\DupCheck::logOverride($o, $dup);
            }
            Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ' إيصال دفع مواقع (' . $o->code . ')', 'log' => ' ' . $c->name . ' · ' . $o->site_name . ' · ' . $o->total_price . ' د.ل · دفع ' . $paid]);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()->withInput()->with('error', 'ما تسجلش: ' . mb_substr($e->getMessage(), 0, 150));
        }
        if ($request->boolean('send_whatsapp')) {
            $o->refresh();
            $msg = "🧾 *إيصال — Shop & Go*\n\n👤 {$c->name} · كود {$c->code}\n🔢 رقم الإيصال: *{$o->code}*\n🌐 دفع موقع: *{$o->site_name}*\n" . ($o->site_bill_usd ? '💵 الفاتورة: ' . TreasuryService::fmt($o->site_bill_usd) . ' ' . ($o->site_bill_currency ?? '$') . "\n" : '')
                . '💰 القيمة: ' . TreasuryService::fmt($o->total_price) . ' ' . $o->currency_sign . "\n✅ دفعت: " . TreasuryService::fmt($o->paid_price) . ' ' . $o->currency_sign . "\n"
                . ($o->remaining() > 0.009 ? '⏳ الباقي: ' . TreasuryService::fmt($o->remaining()) . ' ' . $o->currency_sign . "\n" : "✔️ مدفوع بالكامل\n")
                . '📅 ' . now()->format('Y-m-d g:i a') . "\n\n🧾 إيصالك 👇\n" . route('order.invoice', $o->code);
            try {
                \App\Services\MessageService::sendText('customer_note', $msg, [$c->phone_1], $c, $o);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('sg.sitepay')->with('success', '✓ تسجل إيصال ' . $o->code . ' · ' . $c->name . ' · ' . $o->site_name);
    }

    /** يسجل الكلفة، ولو فيه خزينة: يرجّع القديم ويسحب الجديد (ما يتكررش السحب) */
    protected static function applyCost(Order $o, float $cost, string $cur, float $rate, ?int $pid): void
    {
        if (!empty($o->site_cost_pid) && $o->site_cost !== null && (float) $o->site_cost > 0) {
            TreasuryService::deposit((float) $o->site_cost, $o->site_cost_currency ?: 'dollar', (int) $o->site_cost_pid, 'site_pay_reverse', $o->id, 'تعديل كلفة دفع موقع ' . $o->code);
        }
        if ($pid && $cost > 0) {
            TreasuryService::withdraw($cost, $cur, $pid, 'site_pay', $o->id, 'دفع موقع ' . $o->site_name . ' · ' . $o->code, true);
        }
        $o->site_cost = round($cost, 2);
        $o->site_cost_currency = $cur;
        $o->site_cost_rate = $cur === 'dollar' ? $rate : null;
        $o->site_cost_lyd = round($cost * ($cur === 'dollar' ? $rate : 1), 2);
        $o->site_cost_pid = $pid ?: null;
        $o->site_cost_by = auth()->id();
        $o->site_cost_at = now();
        $o->save();
    }

    /** المسؤول يكتب كلفة الدفع (دولار أو دينار) والربح ينحسب */
    public function cost(Request $request, Order $order)
    {
        abort_unless($order->kind === 'site_pay', 404);
        $data = $request->validate(['cost' => 'required|numeric|min:0', 'currency' => 'required|in:dinar,dollar', 'rate' => 'nullable|numeric|min:0', 'pid' => 'nullable|exists:payment_methods,id']);
        $rate = $data['currency'] === 'dollar' ? ((float) ($data['rate'] ?? 0) ?: self::rate()) : 1;
        if ($data['currency'] === 'dollar' && $rate <= 0) {
            return back()->with('error', 'اكتب سعر الدولار');
        }
        DB::beginTransaction();
        try {
            self::applyCost($order, (float) $data['cost'], $data['currency'], $rate, !empty($data['pid']) ? (int) $data['pid'] : null);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()->with('error', 'ما تسجلتش الكلفة: ' . mb_substr($e->getMessage(), 0, 150));
        }
        $profit = (float) $order->total_price - (float) $order->site_cost_lyd;
        Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ' كلفة دفع مواقع (' . $order->code . ')', 'log' => ' ' . $order->site_cost . ($data['currency'] === 'dollar' ? '$ × ' . $rate : ' د.ل') . ' · ربح ' . round($profit, 2) . (!empty($data['pid']) ? ' · من الخزينة' : '')]);

        return back()->with('success', '✓ ' . $order->code . ' · الكلفة ' . TreasuryService::fmt($order->site_cost_lyd) . ' د.ل · الربح ' . TreasuryService::fmt($profit) . ' د.ل' . (!empty($data['pid']) ? ' · انسحبت من الخزينة' : ''));
    }

    /** المنصات/المواقع اللي ندفعولها (تضيفهم الإدارة) */
    public function sites(Request $request)
    {
        $list = array_values(array_unique(array_filter(array_map('trim', preg_split('/[,،\n]+/u', (string) $request->input('sites'))))));
        $put = ['pay_sites' => $list];
        if ($request->has('bill_currencies')) {
            $put['bill_currencies'] = array_values(array_unique(array_filter(array_map('trim', preg_split('/[,،\n]+/u', (string) $request->input('bill_currencies'))))));
        }
        AppSettings::put($put);

        return back()->with('success', '✓ تحفظت المواقع (' . count($list) . ')');
    }

    /** تحويل بين دينار ودولار (سعر = كم دينار في الدولار) */
    protected static function conv(float $amt, string $from, string $to, float $rate): float
    {
        if ($from === $to) {
            return round($amt, 2);
        }

        return round($from === 'dollar' ? $amt * $rate : $amt / max(0.0001, $rate), 2);
    }

    /** ➕ إضافة/تعديل ربح الإيصال (المدير بس): يتسجل، وينتقل من خزينة لخزينة لو اخترت */
    public function profit(Request $request, Order $order)
    {
        abort_unless($order->kind === 'site_pay' && auth()->user()->hasRole('Admin'), 403);
        $data = $request->validate(['amount' => 'required|numeric|min:0', 'currency' => 'required|in:dinar,dollar', 'target' => 'nullable|string|max:40', 'rate' => 'nullable|numeric|min:0', 'move' => 'nullable|boolean']);
        $rate = (float) ($data['rate'] ?? 0) ?: self::rate();
        $amt = round((float) $data['amount'], 2);
        [$toPid, $toCur] = array_pad(explode(':', (string) ($data['target'] ?? '')), 2, null);
        $toPid = (int) $toPid;
        $toCur = in_array($toCur, ['dinar', 'dollar'], true) ? $toCur : $data['currency'];
        $fromPid = (int) ($order->payment_id ?: AppSettings::cashPaymentId());
        $fromCur = $order->currency ?: 'dinar';
        $needRate = $data['currency'] !== $toCur || $data['currency'] !== $fromCur;
        if ($needRate && $rate <= 0) {
            return back()->with('error', 'اكتب سعر الصرف');
        }
        DB::beginTransaction();
        try {
            // نرجّعو الحركة القديمة (لو كانت) باش التعديل ما يتكررش
            $old = json_decode((string) $order->site_profit_move, true);
            if (is_array($old) && !empty($old['from_amt'])) {
                TreasuryService::deposit((float) $old['from_amt'], $old['from_cur'], (int) $old['from_pid'], 'site_profit_reverse', $order->id, 'تعديل ربح ' . $order->code);
                TreasuryService::withdraw((float) $old['to_amt'], $old['to_cur'], (int) $old['to_pid'], 'site_profit_reverse', $order->id, 'تعديل ربح ' . $order->code, true);
            }
            $move = null;
            if ($amt > 0 && $request->boolean('move') && $toPid && !($toPid === $fromPid && $toCur === $fromCur)) {
                $fromAmt = self::conv($amt, $data['currency'], $fromCur, $rate);
                $toAmt = self::conv($amt, $data['currency'], $toCur, $rate);
                TreasuryService::withdraw($fromAmt, $fromCur, $fromPid, 'site_profit', $order->id, 'ربح ' . $order->code . ' (نقل)', true);
                TreasuryService::deposit($toAmt, $toCur, $toPid, 'site_profit', $order->id, 'ربح ' . $order->code . ' · ' . $order->site_name);
                $move = ['from_pid' => $fromPid, 'from_cur' => $fromCur, 'from_amt' => $fromAmt, 'to_pid' => $toPid, 'to_cur' => $toCur, 'to_amt' => $toAmt];
            }
            $order->site_profit = $amt > 0 ? $amt : null;
            $order->site_profit_currency = $amt > 0 ? $data['currency'] : null;
            $order->site_profit_rate = $data['currency'] === 'dollar' || $needRate ? $rate : null;
            $order->site_profit_lyd = $amt > 0 ? self::conv($amt, $data['currency'], 'dinar', $rate) : null;
            $order->site_profit_move = $move ? json_encode($move) : null;
            $order->site_profit_by = auth()->id();
            $order->site_profit_at = now();
            $order->save();
            Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ' ربح دفع مواقع (' . $order->code . ')', 'log' => ' ' . $amt . ' ' . ($data['currency'] === 'dollar' ? '$' : 'د.ل') . ($move ? ' · انتقل ' . $move['from_amt'] . ' → ' . $move['to_amt'] . ' ' . $toCur : ' · بدون نقل')]);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()->with('error', 'ما تسجلش الربح: ' . mb_substr($e->getMessage(), 0, 150));
        }

        return back()->with('success', $amt > 0 ? '✓ ربح ' . $order->code . ': ' . TreasuryService::fmt($amt) . ' ' . ($data['currency'] === 'dollar' ? '$' : 'د.ل') . ($move ? ' · انتقل للخزينة' : '') : '✓ انشال الربح');
    }
}
