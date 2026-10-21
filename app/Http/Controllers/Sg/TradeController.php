<?php

namespace App\Http\Controllers\Sg;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Trade\Invoice;
use App\Models\Trade\Move;
use App\Models\Trade\Partner;
use App\Models\Trade\Wallet;
use App\Services\TradeService;
use App\Services\TreasuryService as TS;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/** 🌍 واجهة التجارة الخارجية (خزينة ومالية بروحها) */
class TradeController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $u = auth()->user();
            abort_unless($u && ($u->hasRole('Admin') || $u->can('trade-manage')), 403);

            return $next($request);
        });
    }

    protected function ready()
    {
        return Schema::hasTable('trade_wallets');
    }

    public function index(Request $request)
    {
        if (!$this->ready()) {
            return redirect()->route('admin.home')->with('error', 'افتح /system-update مرة وحدة باش تخدم التجارة الخارجية');
        }
        $tab = in_array($request->tab, ['partners', 'invoices', 'moves', 'report', 'customers'], true) ? $request->tab : 'home';
        $partners = Partner::orderByDesc('is_company')->orderBy('name')->get();
        $invoices = Invoice::with(['shares.partner', 'customer'])->latest('id')->take(100)->get();
        $pid = (int) $request->p;

        return view('sg.trade.index', [
            'nav' => 'trade', 'tab' => $tab, 'partners' => $partners, 'wallets' => Wallet::orderBy('id')->get(), 'invoices' => $invoices,
            'moves' => Move::with(['partner', 'wallet', 'invoice', 'user'])->when($pid, fn ($q) => $q->where('partner_id', $pid))->latest('id')->take(200)->get(),
            'sel' => $pid ? $partners->firstWhere('id', $pid) : null,
            'payments' => PaymentMethod::where('status', true)->orderBy('id')->get(),
            'tcustomers' => $tab === 'customers' && Schema::hasTable('trade_customers') ? \App\Models\Trade\Customer::orderBy('name')->get() : collect(),
            'tcSel' => $tab === 'customers' && $request->filled('c') && Schema::hasTable('trade_customers') ? \App\Models\Trade\Customer::with('moves.invoice')->find((int) $request->c) : null,
            'allInv' => $tab === 'report' ? Invoice::with(['shares.partner', 'customer'])->latest('id')->take(200)->get() : collect(),
            'report' => $tab === 'report' ? Move::selectRaw('partner_id, type, SUM(partner_change) s, COUNT(*) n')->whereNotNull('partner_id')->groupBy('partner_id', 'type')->get()->groupBy('partner_id') : collect(),
            'monthly' => $tab === 'report' ? Invoice::where('status', 'closed')->selectRaw("DATE_FORMAT(closed_at, '%Y-%m') m, COUNT(*) n, SUM(profit) p, SUM(cost_lyd + extra_lyd) c, SUM(collected) s")->groupBy('m')->orderByDesc('m')->take(12)->get() : collect(),
            'stats' => [
                'open' => $invoices->where('status', 'open')->count(),
                'profit' => (float) Invoice::where('status', 'closed')->sum('profit'),
                'due' => (float) Invoice::where('status', 'open')->get()->sum(fn ($i) => max(0, (float) $i->sale_lyd - (float) $i->collected)),
            ],
        ]);
    }

    public function partner(Request $request)
    {
        $d = $request->validate(['name' => 'required|string|max:100', 'phone' => 'nullable|string|max:30', 'note' => 'nullable|string|max:300']);
        Partner::create($d);

        return back()->with('success', '✓ تضاف الشريك ' . $d['name']);
    }

    /** ➕ إيداع / ➖ سحب لشريك · 🏢 من خزينة الشركة · 🏢 لمالية الشركة */
    public function money(Request $request)
    {
        $d = $request->validate(['a' => 'required|in:deposit,withdraw,company_in,to_company', 'partner_id' => 'nullable|integer', 'wallet_id' => 'required|integer',
            'amount' => 'required|numeric|min:0.01', 'payment_id' => 'nullable|integer', 'kind' => 'nullable|string|max:100', 'note' => 'nullable|string|max:300']);
        $w = Wallet::findOrFail($d['wallet_id']);
        $amt = round((float) $d['amount'], 2);
        try {
            if ($d['a'] === 'company_in' || $d['a'] === 'to_company') {
                $pm = PaymentMethod::findOrFail((int) ($d['payment_id'] ?? 0));
                $d['a'] === 'company_in' ? TradeService::companyIn($w, $amt, $pm->id, $d['kind'] ?? null) : TradeService::toCompany($w, $amt, $pm->id, $d['note'] ?? null);
                $msg = $d['a'] === 'company_in' ? '🏢 دخل ' . TS::fmt($amt) . ' د.ل من خزينة الشركة (' . $pm->name . ') لرصيد Shop & Go' : '🏢 تدرج ' . TS::fmt($amt) . ' د.ل لمالية الشركة (' . $pm->name . ')';
            } else {
                $p = Partner::findOrFail((int) ($d['partner_id'] ?? 0));
                $d['a'] === 'deposit' ? TradeService::deposit($p, $w, $amt, $d['note'] ?? null) : TradeService::withdraw($p, $w, $amt, $d['note'] ?? null);
                $msg = ($d['a'] === 'deposit' ? '➕ إيداع ' : '➖ سحب ') . TS::fmt($amt) . ' د.ل · ' . $p->name;
            }
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $msg);
    }

    public function invoiceStore(Request $request)
    {
        $d = $request->validate([
            'kind' => 'nullable|string|max:40', 'source' => 'nullable|string|max:150', 'usd_total' => 'nullable|numeric|min:0', 'rate' => 'nullable|numeric|min:0',
            'cost_lyd' => 'nullable|numeric|min:0', 'extra_lyd' => 'nullable|numeric|min:0', 'sale_lyd' => 'nullable|numeric|min:0', 'wallet_id' => 'required|integer',
            'customer_code' => 'nullable|string|max:30', 'note' => 'nullable|string|max:500', 'qty' => 'nullable|integer|min:1',
            's' => 'required|array', 's.*.partner_id' => 'nullable|integer', 's.*.paid' => 'nullable|numeric|min:0', 's.*.source' => 'nullable|string|max:20', 's.*.kind' => 'nullable|string|max:100', 's.*.pct' => 'nullable|numeric|min:0|max:100',
            'customer_name' => 'nullable|string|max:120', 'cust_paid' => 'nullable|numeric|min:0', 'cust_cur' => 'nullable|in:lyd,usd', 'to_account' => 'nullable|boolean', 'items' => 'nullable|array', 'items.*.name' => 'nullable|string|max:190', 'items.*.qty' => 'nullable|integer|min:1', 'items.*.price' => 'nullable|numeric|min:0', 'sale_cur' => 'nullable|in:lyd,usd',
        ]);
        if (!empty($d['customer_code'])) {
            $c = Customer::withoutGlobalScope('approved')->where('code', trim($d['customer_code']))->first();
            if (!$c) {
                return back()->withInput()->with('error', 'ما لقيناش عميل بالكود ' . $d['customer_code']);
            }
            $d['customer_id'] = $c->id;
        }
        // 💲 سعر البيع بالدولار ← يتحول للدينار بسعر الصرف
        $rate = (float) ($d['rate'] ?? 0) ?: ((float) ($d['usd_total'] ?? 0) > 0 && (float) ($d['cost_lyd'] ?? 0) > 0 ? (float) $d['cost_lyd'] / (float) $d['usd_total'] : 0);
        if (($d['sale_cur'] ?? 'lyd') === 'usd' && !empty($d['sale_lyd'])) {
            $d['sale_lyd'] = round((float) $d['sale_lyd'] * $rate, 2);
        }
        if (empty($d['cost_lyd']) && (float) ($d['usd_total'] ?? 0) > 0 && $rate > 0) {
            $d['cost_lyd'] = round((float) $d['usd_total'] * $rate, 2);
        }
        try {
            $inv = TradeService::createInvoice($d, $d['s']);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        TradeService::setItems($inv, (array) ($d['items'] ?? []));

        return redirect()->route('sg.trade.invoice', $inv)->with('success', '🧾 تعملت الفاتورة ' . $inv->code);
    }

    public function invoice(Invoice $invoice)
    {
        $invoice->load(['shares.partner', 'customer', 'order']);
        $tc = TradeService::tcustomer($invoice);

        return view('sg.trade.invoice', [
            'nav' => 'trade', 'inv' => $invoice, 'tc' => $tc, 'wallets' => Wallet::orderBy('id')->get(), 'partners' => Partner::where('active', true)->orderByDesc('is_company')->orderBy('name')->get(),
            'moves' => Move::with(['partner', 'wallet', 'user'])->where('invoice_id', $invoice->id)->oldest('id')->get(),
        ]);
    }

    public function invoiceAct(Request $request, Invoice $invoice)
    {
        $d = $request->validate(['a' => 'required|in:pay,close,sale,customer,order,pct,charge', 'mode' => 'nullable|in:usd,lyd,debt', 'rate' => 'nullable|numeric|min:0', 'pct' => 'nullable|array', 'pct.*' => 'nullable|numeric|min:0|max:100', 'wallet_id' => 'nullable|integer', 'amount' => 'nullable|numeric|min:0', 'note' => 'nullable|string|max:300', 'customer_code' => 'nullable|string|max:30']);
        try {
            if ($d['a'] === 'pay') {
                abort_if($invoice->status === 'closed', 422, 'الفاتورة مقفولة');
                TradeService::customerPay($invoice, Wallet::findOrFail((int) $d['wallet_id']), round((float) $d['amount'], 2), $d['note'] ?? null);

                return back()->with('success', '💵 تسجل سداد ' . TS::fmt($d['amount']) . ' د.ل');
            }
            if ($d['a'] === 'charge') {
                abort_if($invoice->status === 'closed', 422, 'الفاتورة مقفولة');
                $r = TradeService::chargeCustomer($invoice, round((float) $d['amount'], 2), $d['mode'] ?? 'lyd', (float) ($d['rate'] ?? $invoice->rate));
                $c = $r['c'];

                return back()->with('success', ($d['mode'] === 'usd' ? '💳 انخصم ' . $r['usd'] . ' $ من رصيده الدولار' : ($d['mode'] === 'debt' ? '🧾 قعدت عليه دين ' . TS::fmt($d['amount']) . ' د.ل' : '💳 انخصم ' . TS::fmt($d['amount']) . ' د.ل من رصيده')) . ' · رصيده توا: ' . TS::fmt($c->usd) . ' $ / ' . TS::fmt($c->lyd) . ' د.ل');
            }
            if ($d['a'] === 'pct') {
                abort_if($invoice->status === 'closed', 422, 'الفاتورة مقفولة');
                foreach ($invoice->shares as $sh) {
                    $v = $d['pct'][$sh->id] ?? null;
                    $sh->pct = ($v === null || $v === '') ? null : round((float) $v, 2);
                    $sh->save();
                }

                return back()->with('success', '✓ تعدلت نسب الأرباح');
            }
            if ($d['a'] === 'customer') {
                $c = Customer::withoutGlobalScope('approved')->where('code', trim((string) $d['customer_code']))->first();
                if (!$c) {
                    return back()->with('error', 'ما لقيناش عميل بالكود هذا');
                }
                $invoice->customer_id = $c->id;
                $invoice->save();
                $o = TradeService::makeOrder($invoice->fresh());

                return back()->with('success', '👤 تربطت بالعميل ' . $c->name . ($o ? ' · وتعملتله الطلبية ' . $o->code . ' في حسابه' : ''));
            }
            if ($d['a'] === 'order') {
                $o = TradeService::makeOrder($invoice);

                return back()->with($o ? 'success' : 'error', $o ? '📦 تعملت الطلبية ' . $o->code . ' في حساب العميل' : 'لازم عميل وسعر بيع، ولا الطلبية معمولة من قبل');
            }
            if ($d['a'] === 'sale') {
                $invoice->sale_lyd = round((float) $d['amount'], 2);
                $invoice->save();
                if ($invoice->order_id && ($o = \App\Models\Order::find($invoice->order_id))) {
                    $o->total_price = $invoice->sale_lyd;
                    $o->refreshPaymentStatus();
                    $o->save();
                }

                return back()->with('success', '✓ تعدل سعر البيع');
            }
            $p = TradeService::close($invoice);

            return back()->with('success', '🔒 تقفلت الفاتورة · ' . ($p >= 0 ? 'الربح ' : 'الخسارة ') . TS::fmt(abs($p)) . ' د.ل اتقسم على الشركاء');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function invoiceUpdate(Request $request, Invoice $invoice)
    {
        $d = $request->validate([
            'source' => 'nullable|string|max:150', 'usd_total' => 'nullable|numeric|min:0', 'rate' => 'nullable|numeric|min:0', 'cost_lyd' => 'required|numeric|min:0',
            'extra_lyd' => 'nullable|numeric|min:0', 'sale_lyd' => 'nullable|numeric|min:0', 'qty' => 'nullable|integer|min:1', 'customer_name' => 'nullable|string|max:120', 'note' => 'nullable|string|max:500',
            's' => 'nullable|array', 's.*.partner_id' => 'nullable|integer', 's.*.paid' => 'nullable|numeric|min:0', 's.*.pct' => 'nullable|numeric|min:0|max:100',
            'items' => 'nullable|array', 'items.*.name' => 'nullable|string|max:190', 'items.*.qty' => 'nullable|integer|min:1', 'items.*.price' => 'nullable|numeric|min:0',
        ]);
        try {
            $inv2 = TradeService::editInvoice($invoice, $d, $d['s'] ?? []);
            TradeService::setItems($inv2, (array) ($d['items'] ?? []));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', '✏️ تعدلت الفاتورة');
    }

    public function invoiceDelete(Invoice $invoice)
    {
        try {
            $code = $invoice->code;
            TradeService::deleteInvoice($invoice);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('sg.trade', ['tab' => 'invoices'])->with('success', '🗑 انحذفت الفاتورة ' . $code . ' ورجعت كل حركاتها');
    }

    /** ✏️ تعديل رصيد خزينة التجارة (كاش/مصرف) — الفرق يتسجل حركة تعديل */
    public function walletUpdate(Request $request, Wallet $wallet)
    {
        $d = $request->validate(['balance' => 'required|numeric', 'note' => 'nullable|string|max:200']);
        $dd = round((float) $d['balance'] - (float) $wallet->balance, 2);
        if (abs($dd) > 0.009) {
            \Illuminate\Support\Facades\DB::transaction(fn () => TradeService::move('adjust', $wallet, $dd, null, 0, null, trim('تعديل رصيد ' . $wallet->name . ' ' . TS::fmt($wallet->balance) . ' ← ' . TS::fmt($d['balance']) . (!empty($d['note']) ? ' · ' . $d['note'] : '')), true));
        }

        return back()->with('success', '✏️ تعدل رصيد ' . $wallet->name);
    }

    /** 👤 عميل تجارة: جديد · إيداع (دولار/دينار) · تعديل رصيد */
    public function tcustomer(Request $request)
    {
        $d = $request->validate(['a' => 'required|in:add,deposit,set', 'id' => 'nullable|integer', 'name' => 'nullable|string|max:120', 'phone' => 'nullable|string|max:30',
            'cur' => 'nullable|in:usd,lyd', 'amount' => 'nullable|numeric', 'note' => 'nullable|string|max:200']);
        try {
            if ($d['a'] === 'add') {
                abort_if(empty($d['name']), 422);
                \App\Models\Trade\Customer::create(['name' => $d['name'], 'phone' => $d['phone'] ?? null]);

                return back()->with('success', '✓ تضاف العميل ' . $d['name']);
            }
            $c = \App\Models\Trade\Customer::findOrFail((int) $d['id']);
            $cur = ($d['cur'] ?? 'lyd') === 'usd' ? 'usd' : 'lyd';
            $amt = round((float) ($d['amount'] ?? 0), 2);
            if ($d['a'] === 'deposit') {
                abort_if($amt <= 0, 422, 'اكتب المبلغ');
                TradeService::customerDeposit($c, $cur, $amt, $d['note'] ?? null);

                return back()->with('success', '➕ دخل ' . TS::fmt($amt) . ($cur === 'usd' ? ' $' : ' د.ل') . ' لرصيد ' . $c->name);
            }
            \Illuminate\Support\Facades\DB::transaction(fn () => TradeService::cmove($c, 'adjust', $cur, round($amt - (float) $c->{$cur}, 2), null, 0, null, trim('تعديل الرصيد' . (!empty($d['note']) ? ' · ' . $d['note'] : ''))));

            return back()->with('success', '✏️ تعدل رصيد ' . $c->name);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function partnerUpdate(Request $request, Partner $partner)
    {
        $d = $request->validate(['name' => 'required|string|max:100', 'phone' => 'nullable|string|max:30', 'balance' => 'nullable|numeric', 'note' => 'nullable|string|max:200']);
        $partner->name = $d['name'];
        $partner->phone = $d['phone'] ?? null;
        $partner->save();
        if ($request->filled('balance')) {
            TradeService::setBalance($partner, (float) $d['balance'], $d['note'] ?? null);
        }

        return back()->with('success', '✏️ تعدل ' . $partner->name);
    }
}
