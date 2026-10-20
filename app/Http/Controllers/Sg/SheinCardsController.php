<?php

namespace App\Http\Controllers\Sg;

use App\Http\Controllers\Controller;
use App\Models\CurrencyExchange;
use App\Models\Log;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\SheinCard;
use App\Models\SheinCardUse;
use App\Models\TripPurchase;
use App\Services\AppSettings;
use App\Services\Csv;
use App\Services\FinanceReport;
use App\Services\Period;
use App\Services\SheinCardService;
use App\Services\TreasuryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** بطاقات SHEIN: شراء، رصيد، استخدام على الرحلات والإيصالات، انتهاء */
class SheinCardsController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:shein-card-list', ['only' => ['index', 'check']]);
        $this->middleware('permission:shein-card-create', ['only' => ['store', 'update']]);
        $this->middleware('permission:shein-card-use', ['only' => ['use', 'reverse', 'status']]);
        $this->middleware('permission:shein-card-delete', ['only' => ['destroy']]);
        $this->middleware('permission:shein-card-pin', ['only' => ['pin']]);
    }

    public function index(Request $request)
    {
        $p = Period::make($request->input('p', 'month'));
        $tab = in_array($request->tab, ['active', 'partial', 'sold', 'used', 'lost', 'all']) ? $request->tab : 'active';
        $q = SheinCard::query()->withCount('uses');
        match ($request->filled('q') ? 'all' : $tab) { // البحث يبحث في كل البطاقات
            'active' => $q->where('status', 'new')->whereNull('sold_order_id'), // الجديدة بس
            'partial' => $q->where('status', 'partial'),
            'used' => $q->where('status', 'used'),
            'sold' => $q->where('status', 'sold'),
            'lost' => $q->whereIn('status', ['expired', 'invalid']),
            default => null,
        };
        if ($request->filled('q')) {
            $t = trim($request->q);
            if ($request->input('by') === 'pin') {
                // الرمز متشفر، ندوروا عليه بعد فك التشفير (يحتاج صلاحية كشف الرمز)
                abort_unless(auth()->user()->can('shein-card-pin'), 403);
                $ids = SheinCard::query()->select(['id', 'pin'])->get()->filter(function ($c) use ($t) {
                    try {
                        return $c->pin !== null && str_contains((string) $c->pin, $t);
                    } catch (\Throwable $e) {
                        return false;
                    }
                })->pluck('id');
                $q->whereIn('id', $ids);
            } elseif ($request->input('by') === 'card') {
                $q->where('card_no', 'like', '%' . preg_replace('/\D/', '', $t) . '%');
            } else {
                $q->where(fn ($w) => $w->where('card_no', 'like', "%$t%")->orWhere('supplier', 'like', "%$t%")->orWhere('batch', $t)->orWhere('account', 'like', "%$t%")->orWhere('note', 'like', "%$t%"));
            }
        }
        if ($request->filled('supplier')) {
            $q->where('supplier', $request->supplier);
        }
        $sort = $request->input('sort', 'smart');
        match ($sort) {
            // 🆕 الجديدة فوق، بعدها المستعملة، وبعدها الخالصة… وفي كل وحدة الأقل رصيد لوطى
            'smart' => $q->orderByRaw("CASE status WHEN 'new' THEN 0 WHEN 'partial' THEN 1 WHEN 'used' THEN 2 WHEN 'sold' THEN 3 ELSE 4 END")->orderByDesc('balance')->orderBy('id'),
            'balance' => $q->orderByDesc('balance'),
            'new' => $q->orderByDesc('purchased_at')->orderByDesc('id'),
            'expiry' => $q->orderByRaw('expires_at IS NULL')->orderBy('expires_at'),
            default => $q->orderBy('purchased_at')->orderBy('id'),
        };
        if ($request->export === 'csv') {
            return Csv::download('shein-cards.csv', ['الكود', 'القيمة $', 'الرصيد $', 'التكلفة', 'العملة', 'تكلفة الدولار', 'المورد', 'تاريخ الشراء', 'تنتهي', 'الحساب', 'الحالة'],
                $q->get()->map(fn ($c) => [$c->masked(), $c->face_value, $c->balance, $c->cost, $c->cost_currency, $c->costPerDollar(), $c->supplier, $c->purchased_at->toDateString(), optional($c->expires_at)->toDateString(), $c->account, $c->statusLabel()]));
        }
        $cards = $q->paginate(30)->withQueryString();

        $active = SheinCard::whereIn('status', ['new', 'partial'])->get();
        $faceActive = $active->sum('face_value');
        $usedPeriod = SheinCardUse::whereBetween('created_at', [$p->from, $p->to]);
        $boughtPeriod = SheinCard::whereBetween('purchased_at', [$p->from->toDateString(), $p->to->toDateTimeString()])->get();
        $marketRate = CurrencyExchange::latestRate() ?: SheinCardService::defaultRate();
        $avgCardRate = $active->sum('balance') > 0 ? $active->sum(fn ($c) => $c->balanceDinar()) / $active->sum('balance') : null;

        $sel = $request->filled('id') ? SheinCard::with(['uses.trip', 'uses.order.customer', 'uses.createdUser', 'payment', 'createdUser'])->find($request->id) : null;

        return view('sg.shein.index', [
            'nav' => 'shein', 'period' => $p, 'tab' => $tab, 'cards' => $cards, 'sel' => $sel,
            'kpi' => [
                'balance' => $active->sum('balance'), 'count' => $active->count(), 'balanceDinar' => $active->sum(fn ($c) => $c->balanceDinar()),
                'avgRate' => $avgCardRate, 'marketRate' => $marketRate,
                'used' => (float) (clone $usedPeriod)->sum('amount'), 'usedN' => (clone $usedPeriod)->count(),
                'bought' => $boughtPeriod->sum('face_value'), 'boughtCost' => $boughtPeriod->sum('cost_dinar'), 'boughtN' => $boughtPeriod->count(),
                'expiring' => $active->filter(fn ($c) => $c->expires_at && $c->expires_at->lte(now()->addDays(30)))->count(),
                'lost' => (float) SheinCard::whereBetween('lost_at', [$p->from, $p->to])->sum('lost_dinar'),
                'saving' => $avgCardRate && $marketRate ? ($marketRate - $avgCardRate) * $active->sum('balance') : 0,
            ],
            'counts' => [
                'active' => SheinCard::where('status', 'new')->whereNull('sold_order_id')->count(), 'partial' => SheinCard::where('status', 'partial')->count(), 'used' => SheinCard::where('status', 'used')->count(), 'sold' => SheinCard::where('status', 'sold')->count(),
                'lost' => SheinCard::whereIn('status', ['expired', 'invalid'])->count(), 'all' => SheinCard::count(),
            ],
            'suppliers' => SheinCard::whereNotNull('supplier')->distinct()->orderBy('supplier')->pluck('supplier'),
            'trips' => TripPurchase::where('cost_currency', 'dollar')->latest('purchased_at')->take(40)->get()->filter(fn ($t) => $t->cards_amount < $t->cost - 0.009)->values(),
            'usable' => $active->sortBy('purchased_at')->values(),
            'payments' => PaymentMethod::where('status', true)->orderBy('id')->get(),
            'balances' => FinanceReport::balances(), 'cashId' => AppSettings::cashPaymentId(),
            'recentUses' => SheinCardUse::with(['card', 'trip', 'order', 'createdUser'])->latest()->take(10)->get(),
        ]);
    }

    /** هل البطاقة موجودة؟ (للتنبيه وقت الكتابة) */
    public function check(Request $request)
    {
        $no = preg_replace('/\D/', '', (string) $request->input('card_no'));
        $c = $no !== '' ? SheinCard::where('card_no', $no)->first() : null;

        return response()->json(['exists' => (bool) $c, 'card' => $c ? ('سُجّلت ' . optional($c->purchased_at)->format('Y-m-d') . ' · ' . $c->statusLabel() . ' · رصيد ' . $c->balance . ' $') : null]);
    }

    /** شراء بطاقة أو مجموعة بطاقات */
    public function store(Request $request)
    {
        $data = $request->validate([
            'codes' => 'required|string|max:20000',
            'face_value' => 'required|numeric|min:0.01',
            'cost' => 'required|numeric|min:0',
            'cost_mode' => 'nullable|in:total,each',
            'cost_currency' => 'required|in:dinar,dollar',
            'rate' => 'nullable|numeric|min:0.0001',
            'payment_id' => 'nullable|exists:payment_methods,id',
            'supplier' => 'nullable|string|max:120',
            'account' => 'nullable|string|max:150',
            'purchased_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
            'note' => 'nullable|string|max:500',
        ], ['codes.required' => 'اكتب كود البطاقة', 'face_value.required' => 'اكتب قيمة البطاقة بالدولار', 'cost.required' => 'اكتب كم دفعت']);
        $cards = SheinCardService::parseLines($data['codes']);
        $cd = (int) AppSettings::get('shein_card_digits', 16);
        $pd = (int) AppSettings::get('shein_pin_digits', 4);
        foreach ($cards as $c) {
            if (!ctype_digit($c['card_no']) || strlen($c['card_no']) !== $cd) {
                return back()->withInput()->with('error', 'رقم البطاقة ' . $c['card_no'] . ' يجب أن يكون ' . $cd . ' رقم بالضبط');
            }
            if (!$c['pin'] || !ctype_digit($c['pin']) || strlen($c['pin']) !== $pd) {
                return back()->withInput()->with('error', 'رمز البطاقة ' . $c['card_no'] . ' يجب أن يكون ' . $pd . ' أرقام');
            }
        }
        $dupes = SheinCard::whereIn('card_no', $cards->pluck('card_no'))->pluck('card_no');
        if ($dupes->count()) {
            return back()->withInput()->with('error', '⚠ البطاقة موجودة من قبل في النظام: ' . $dupes->implode('، ') . ' — لا يمكنك أن تدخلها مرة ثانية');
        }
        $cost = ($data['cost_mode'] ?? 'total') === 'each' ? (float) $data['cost'] * $cards->count() : (float) $data['cost'];
        DB::beginTransaction();
        try {
            $made = SheinCardService::buy($cards, (float) $data['face_value'], $cost, $data['cost_currency'], $data['payment_id'] ?? null, [
                'supplier' => $data['supplier'] ?? null, 'account' => $data['account'] ?? null, 'purchased_at' => $data['purchased_at'] ?? null,
                'expires_at' => $data['expires_at'] ?? null, 'note' => $data['note'] ?? null, 'rate' => $data['rate'] ?? null,
            ]);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()->withInput()->with('error', $e instanceof \RuntimeException ? $e->getMessage() : 'للأسف حدث خطأ، يرجى إعادة المحاولة');
        }
        $face = $made->sum('face_value');

        return redirect()->route('sg.shein', $made->count() === 1 ? ['id' => $made->first()->id] : [])
            ->with('success', 'سُجّلت ' . $made->count() . ' بطاقة بقيمة ' . TreasuryService::fmt($face) . ' $ · الدولار كلفك ' . number_format($made->sum('cost_dinar') / max(0.01, $face), 3) . ' د.ل');
    }

    /** استخدام بطاقة (أو أكثر) على رحلة أو إيصال */
    public function use(Request $request)
    {
        $data = $request->validate([
            'amounts' => 'required|array', 'amounts.*' => 'nullable|numeric|min:0',
            'trip_purchase_id' => 'nullable|exists:trip_purchases,id',
            'order_code' => 'nullable|string|max:40',
            'note' => 'nullable|string|max:300',
        ], ['amounts.required' => 'اختر بطاقة']);
        $amounts = collect($data['amounts'])->map(fn ($v) => (float) $v)->filter(fn ($v) => $v > 0);
        if ($amounts->isEmpty()) {
            return back()->with('error', 'اكتب كم بتستخدم من البطاقة');
        }
        $trip = !empty($data['trip_purchase_id']) ? TripPurchase::find($data['trip_purchase_id']) : null;
        $order = null;
        if (!empty($data['order_code'])) {
            $code = trim($data['order_code']);
            $order = Order::where('code', $code)->orWhere('code', 'SG-' . $code)->first();
            if (!$order) {
                return back()->with('error', 'لم نجد إيصال ' . $code);
            }
        }
        if (!$trip && !$order) {
            return back()->with('error', 'اختر الرحلة أو اكتب رقم الإيصال');
        }
        DB::beginTransaction();
        try {
            foreach ($amounts as $cardId => $amount) {
                SheinCardService::use(SheinCard::lockForUpdate()->findOrFail($cardId), $amount, $trip, $order, $data['note'] ?? null);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()->with('error', $e instanceof \RuntimeException ? $e->getMessage() : 'للاسف حدث خطأ ما');
        }
        $trip = $trip ?: ($order && $order->trip_details ? TripPurchase::where('trip_code', $order->trip_details)->first() : null);

        return back()->with('success', 'استعملنا ' . TreasuryService::fmt($amounts->sum()) . ' $ من البطاقات' . ($trip ? ' على رحلة ' . $trip->trip_code . ' · ربح الرحلة الآن ' . TreasuryService::fmt($trip->fresh()->summary()['profit_dinar']) . ' د.ل' : '') . ($order ? ' للإيصال ' . $order->code : ''));
    }

    public function reverse(SheinCardUse $use)
    {
        DB::beginTransaction();
        try {
            SheinCardService::reverse($use->load('card', 'trip'));
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()->with('error', $e instanceof \RuntimeException ? $e->getMessage() : 'للاسف حدث خطأ ما');
        }

        return back()->with('success', 'رجع الرصيد للبطاقة');
    }

    public function update(Request $request, SheinCard $card)
    {
        $data = $request->validate([
            'supplier' => 'nullable|string|max:120', 'account' => 'nullable|string|max:150', 'expires_at' => 'nullable|date',
            'note' => 'nullable|string|max:500', 'pin' => 'nullable|string|max:120',
        ]);
        $card->supplier = $data['supplier'] ?? null;
        $card->account = $data['account'] ?? null;
        $card->expires_at = $data['expires_at'] ?? null;
        $card->note = $data['note'] ?? null;
        if (!empty($data['pin']) && auth()->user()->can('shein-card-pin')) {
            $card->pin = $data['pin'];
        }
        $card->save();

        return back()->with('success', 'تم الحفظ');
    }

    /** انتهت / خطأ */
    public function status(Request $request, SheinCard $card)
    {
        $data = $request->validate(['status' => 'required|in:expired,invalid', 'note' => 'nullable|string|max:200']);
        DB::transaction(fn () => SheinCardService::writeOff($card, $data['status'], $data['note'] ?? null));

        return back()->with('warning', 'البطاقة ' . $card->masked() . ' ' . SheinCard::STATUSES[$data['status']][0] . ' · خسارة ' . TreasuryService::fmt($card->lost_dinar) . ' د.ل');
    }

    public function destroy(SheinCard $card)
    {
        DB::beginTransaction();
        try {
            SheinCardService::delete($card);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->with('error', $e instanceof \RuntimeException ? $e->getMessage() : 'للاسف حدث خطأ ما');
        }

        return redirect()->route('sg.shein')->with('success', 'انحذفت البطاقة ورجع المبلغ للخزينة');
    }

    /** يكشف الرقم السري (لصاحب الصلاحية) ويتسجل في السجل */
    public function pin(SheinCard $card)
    {
        Log::create(['user_id' => auth()->id(), 'type' => 'Treasury', 'title' => ' عرض رقم سري', 'log' => ' عرض الرقم السري للبطاقة ' . $card->masked()]);

        return response()->json(['card_no' => $card->card_no, 'pin' => $card->pin]);
    }
}
