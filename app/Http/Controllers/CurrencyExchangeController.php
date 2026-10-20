<?php

namespace App\Http\Controllers;

use App\Models\CurrencyExchange;
use App\Models\Log;
use App\Models\Notif_orgs;
use App\Models\PaymentMethod;
use App\Services\TreasuryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * شراء دولار / USDT وتعبئة الفيزا:
 * يخصم دينار من خزينة (كاش أو مصرف) ويزيد دولار في خزينة (الفيزا مثلا).
 */
class CurrencyExchangeController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:exchange-list', ['only' => ['index']]);
        $this->middleware('permission:exchange-create', ['only' => ['store']]);
        $this->middleware('permission:exchange-delete', ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {
        $exchanges = CurrencyExchange::with(['fromPayment', 'toPayment', 'createdUser'])
            ->when($request->filled('from'), fn ($q) => $q->whereDate('date', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('date', '<=', $request->to))
            ->latest('date')->latest('id')->paginate(25)->withQueryString();

        $payments = PaymentMethod::where('status', true)->get();
        $latestRate = CurrencyExchange::latestRate();
        $main_notifications = Notif_orgs::where('user_id', auth()->id())->latest()->take(5)->get();

        $balances = [];
        foreach ($payments as $p) {
            foreach (array_keys(TreasuryService::CURRENCIES) as $cur) {
                $balances[$p->id][$cur] = TreasuryService::balance($cur, $p->id);
            }
        }

        $monthStats = CurrencyExchange::where('from_currency', 'dinar')->where('to_currency', 'dollar')
            ->whereBetween('date', [now()->startOfMonth()->toDateString(), now()->toDateString()])
            ->selectRaw('COALESCE(SUM(from_amount),0) as lyd, COALESCE(SUM(to_amount),0) as usd, COUNT(*) as cnt')->first();

        return view('admin.finance.exchanges', compact('exchanges', 'payments', 'latestRate', 'balances', 'monthStats', 'main_notifications'));
    }

    public function store(Request $request)
    {
        // الغرض من شراء الدولار: فيزا / كاش / بطاقات SHEIN / USDT
        $purpose = in_array($request->purpose, ['visa', 'cash', 'shein', 'usdt'], true) ? $request->purpose : null;
        if ($purpose === 'shein') {
            return $this->buyShein($request);
        }
        if ($purpose) {
            $request->merge(['kind' => ['visa' => 'visa_load', 'cash' => 'cash', 'usdt' => 'usdt'][$purpose]]);
        }
        $data = $request->validate([
            'date' => 'required|date',
            'kind' => 'required|in:' . implode(',', array_keys(CurrencyExchange::KINDS)),
            'from_currency' => 'required|in:dinar,dollar,euro',
            'from_payment_id' => 'required|exists:payment_methods,id',
            'from_amount' => 'required|numeric|min:0.01',
            'to_currency' => 'required|in:dinar,dollar,euro',
            'to_payment_id' => 'required|exists:payment_methods,id',
            'to_amount' => 'required|numeric|min:0.01',
            'note' => 'nullable|string|max:500',
        ], [
            'from_amount.required' => 'اكتب المبلغ المدفوع',
            'to_amount.required' => 'اكتب المبلغ المستلم (دولار / USDT)',
        ]);
        if ($purpose && \Illuminate\Support\Facades\Schema::hasColumn('currency_exchanges', 'purpose')) {
            $data['purpose'] = $purpose;
        }

        if ($data['from_currency'] === $data['to_currency'] && (int) $data['from_payment_id'] === (int) $data['to_payment_id']) {
            return back()->withInput()->with('error', 'الخزينة المصدر هي نفسها الخزينة المستلمة');
        }

        DB::beginTransaction();
        try {
            $data['rate'] = round($data['from_amount'] / $data['to_amount'], 4);
            $data['created_id'] = auth()->id();
            $exchange = CurrencyExchange::create($data);

            $note = CurrencyExchange::KINDS[$data['kind']] . ' بسعر ' . $data['rate'];
            TreasuryService::withdraw((float) $data['from_amount'], $data['from_currency'], (int) $data['from_payment_id'], 'exchange', $exchange->id, $note);
            TreasuryService::deposit((float) $data['to_amount'], $data['to_currency'], (int) $data['to_payment_id'], 'exchange', $exchange->id, $note);

            Log::create([
                'user_id' => auth()->id(),
                'type' => 'Treasury',
                'title' => ' ' . CurrencyExchange::KINDS[$data['kind']],
                'log' => ' دفع ' . $data['from_amount'] . ' ' . TreasuryService::sign($data['from_currency']) . ' واستلم ' . $data['to_amount'] . ' ' . TreasuryService::sign($data['to_currency']) . ' بسعر ' . $data['rate'],
            ]);

            DB::commit();

            return back()->with('success', 'تم التسجيل. سعر الصرف ' . $data['rate']);
        } catch (\RuntimeException $e) {
            DB::rollBack();

            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()->withInput()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
        }
    }

    /** شراء دولار على شكل بطاقات SHEIN: الدينار يُخصم من الخزينة وتُسجَّل البطاقات بتكلفتها */
    protected function buyShein(Request $request)
    {
        $data = $request->validate([
            'date' => 'required|date',
            'from_payment_id' => 'required|exists:payment_methods,id',
            'from_amount' => 'required|numeric|min:0.01',
            'face' => 'required|numeric|min:0.01',
            'cards' => 'required|array|min:1|max:50',
            'cards.*.no' => 'nullable|string|max:40',
            'cards.*.pin' => 'nullable|string|max:12',
            'note' => 'nullable|string|max:500',
        ], ['from_amount.required' => 'اكتب المبلغ المدفوع بالدينار', 'face.required' => 'اكتب قيمة البطاقة بالدولار', 'cards.required' => 'أدخل بطاقة واحدة على الأقل']);
        $cards = collect($data['cards'])->map(fn ($c) => ['card_no' => preg_replace('/\D/', '', (string) ($c['no'] ?? '')), 'pin' => preg_replace('/\D/', '', (string) ($c['pin'] ?? '')), 'face' => null])
            ->filter(fn ($c) => $c['card_no'] !== '')->values();
        DB::beginTransaction();
        try {
            \App\Services\SheinCardService::validateFormat($cards);
            $made = \App\Services\SheinCardService::buy($cards, (float) $data['face'], (float) $data['from_amount'], 'dinar', (int) $data['from_payment_id'],
                ['purchased_at' => $data['date'], 'note' => $data['note'] ?? null]);
            DB::commit();
        } catch (\RuntimeException $e) {
            DB::rollBack();

            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()->withInput()->with('error', 'حدث خطأ، يرجى المحاولة مرة أخرى');
        }
        $face = $made->sum('face_value');

        return back()->with('success', 'سُجّلت ' . $made->count() . ' بطاقة SHEIN بقيمة ' . TreasuryService::fmt($face) . ' $ · سعر الدولار ' . number_format((float) $data['from_amount'] / max(0.01, $face), 3) . ' د.ل');
    }

    public function destroy(CurrencyExchange $exchange)
    {
        DB::beginTransaction();
        try {
            // نعكس الحركة: نرجع الدينار ونسحب الدولار (لو الدولار تصرف خلاص يرفض)
            TreasuryService::withdraw((float) $exchange->to_amount, $exchange->to_currency, (int) $exchange->to_payment_id, 'exchange_reverse', $exchange->id, 'حذف عملية صرف');
            TreasuryService::deposit((float) $exchange->from_amount, $exchange->from_currency, (int) $exchange->from_payment_id, 'exchange_reverse', $exchange->id, 'حذف عملية صرف');
            Log::create([
                'user_id' => auth()->id(),
                'type' => 'Treasury',
                'title' => ' حذف عملية صرف',
                'log' => ' تم حذف عملية صرف ' . $exchange->from_amount . ' → ' . $exchange->to_amount,
            ]);
            $exchange->delete();
            DB::commit();

            return back()->with('success', 'تم حذف العملية ورجعت الأرصدة');
        } catch (\RuntimeException $e) {
            DB::rollBack();

            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
        }
    }
}
