<?php

namespace App\Http\Controllers\Sg;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\SheinCard;
use App\Services\AppSettings;
use App\Services\CardSaleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** إيصال بيع بطاقة SHEIN لعميل */
class CardSalesController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:shein-card-sell');
    }

    public function create(Request $request)
    {
        $cards = CardSaleService::available()->orderBy('face_value')->orderBy('purchased_at')->orderBy('id')->get();

        return view('sg.cards.sell', [
            'nav' => 'ord', 'cards' => $cards, 'rate' => CardSaleService::rate(), 'mrate' => CardSaleService::marketRate(),
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'code', 'phone_1']),
            'payments' => PaymentMethod::where('status', true)->get(), 'cashId' => AppSettings::cashPaymentId(),
            'pre' => $request->filled('customer') ? Customer::find((int) $request->customer) : null,
            // المبيوعة تختفي على الموظف (المدير بس يشوفها)
            'recent' => auth()->user()->hasRole('Admin') ? SheinCard::with(['soldOrder', 'soldCustomer', 'soldUser'])->whereNotNull('sold_order_id')->latest('sold_at')->take(10)->get() : collect(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'cards' => 'required|array|min:1|max:20', 'cards.*' => 'integer',
            'price' => 'required|array', 'price.*' => 'nullable|numeric|min:0',
            'paid' => 'nullable|numeric|min:0', 'payment_id' => 'nullable|exists:payment_methods,id',
            'note' => 'nullable|string|max:300', 'send_whatsapp' => 'nullable|boolean',
        ], ['customer_id.required' => 'اختر العميل', 'cards.required' => 'اختر بطاقة واحدة على الأقل']);
        $prices = [];
        foreach ($data['cards'] as $id) {
            $prices[(int) $id] = (float) ($data['price'][$id] ?? 0);
        }
        DB::beginTransaction();
        try {
            $o = CardSaleService::sell(Customer::findOrFail($data['customer_id']), $prices, (float) ($data['paid'] ?? 0), $data['payment_id'] ?? null, $data['note'] ?? null);
            DB::commit();
        } catch (\RuntimeException $e) {
            DB::rollBack();

            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()->withInput()->with('error', 'حدث خطأ، يرجى المحاولة مرة أخرى');
        }
        try {
            \App\Services\PointsService::award($o, false);
        } catch (\Throwable $e) {
            report($e);
        }
        $msg = 'سُجّل إيصال بيع البطاقات ' . $o->code;
        // 💳 تنبيه للمدير بس
        try {
            $sold = \App\Services\CardSaleService::cardsOf($o);
            \App\Services\AlertService::push('card_sold', '💳 انباع كرت ' . $sold->map(fn ($c) => \App\Services\TreasuryService::fmt($c->face_value) . '$')->implode(' + '),
                optional($o->customer)->name . ' · ' . auth()->user()->name . ' · إيصال ' . $o->code . ' · ' . \App\Services\TreasuryService::fmt($o->total_price) . ' د.ل', route('sg.orders.show', $o), 'info');
        } catch (\Throwable $e) {
            report($e);
        }
        // الموظف (مش المدير): الكرت يمشي للعميل ديما
        if ($request->boolean('send_whatsapp') || !auth()->user()->hasRole('Admin')) {
            $msg .= CardSaleService::send($o) ? ' · أُرسلت البطاقة للعميل عبر واتساب ✓' : ' · ⚠ تعذّر إرسال واتساب، يمكنك نسخ البطاقة وإرسالها يدوياً من صفحة الإيصال';
        }

        return redirect()->route('sg.orders.show', $o)->with('success', $msg);
    }

    public function resend(Order $order)
    {
        abort_unless($order->kind === 'card_sale', 404);
        $ok = CardSaleService::send($order);

        return back()->with($ok ? 'success' : 'error', $ok ? 'أُعيد إرسال البطاقة للعميل ✓' : 'تعذّر إرسال واتساب');
    }

    /** نص البطاقة كاملاً للنسخ اليدوي — لمن لديه صلاحية رؤية الرمز */
    public function reveal(Order $order)
    {
        abort_unless($order->kind === 'card_sale' && auth()->user()->can('shein-card-pin'), 403);
        \App\Models\Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ' عرض رمز بطاقة مباعة (' . $order->code . ')', 'log' => ' للنسخ والإرسال اليدوي']);

        return response()->json(['text' => CardSaleService::plainText($order)]);
    }
}
