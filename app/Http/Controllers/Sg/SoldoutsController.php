<?php

namespace App\Http\Controllers\Sg;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderSoldout;
use App\Services\MessageService;
use App\Services\SoldoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** المنتجات التي نفدت أثناء الشراء */
class SoldoutsController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:soldout-manage');
    }

    public function index(Request $request)
    {
        $status = in_array($request->status, ['pending', 'cancelled', 'replaced', 'all'], true) ? $request->status : 'pending';
        $q = OrderSoldout::with(['order', 'customer', 'newOrder', 'creator', 'decider'])->latest();
        if ($status !== 'all') {
            $q->where('status', $status);
        }
        if ($request->filled('q')) {
            $t = trim($request->q);
            $q->where(fn ($w) => $w->where('item', 'like', "%$t%")->orWhereHas('order', fn ($o) => $o->where('code', 'like', "%$t%"))
                ->orWhereHas('customer', fn ($c) => \App\Support\Search::customer($c, $t)));
        }

        return view('sg.soldouts.index', [
            'nav' => 'ord', 'status' => $status, 'rows' => $q->paginate(40)->withQueryString(),
            'counts' => OrderSoldout::selectRaw('status, COUNT(*) n, SUM(price) v')->groupBy('status')->get()->keyBy('status'),
            'payments' => \App\Models\PaymentMethod::where('status', true)->get(),
        ]);
    }

    /** البحث عن إيصال (للنافذة السريعة) */
    public function find(Request $request)
    {
        $t = trim((string) $request->q);
        if (mb_strlen($t) < 2) {
            return response()->json([]);
        }
        $base = fn () => Order::with(['customer', 'items'])->where('status', '!=', 'canceld')->where('delivered', false);
        // كود عميل بالضبط → كل إيصالاته اللي ما استلمهاش
        $cust = \App\Models\Customer::where('code', $t)->first();
        $rows = $cust ? $base()->where('customer_id', $cust->id)->latest()->take(40)->get() : collect();
        if ($rows->isEmpty()) {
            $rows = $base()->where(fn ($w) => $w->where('code', 'like', "%$t%")->orWhereHas('customer', fn ($c) => \App\Support\Search::customer($c, $t)))
                ->latest()->take(15)->get();
        }

        return response()->json($rows->map(fn ($o) => [
            'id' => $o->id, 'code' => $o->code, 'name' => optional($o->customer)->name, 'ccode' => optional($o->customer)->code,
            'total' => \App\Services\TreasuryService::fmt($o->grandTotal()), 'when' => optional($o->created_at)->format('Y-m-d'),
            'items' => $o->items->map(fn ($it) => ['name' => $it->name, 'max' => max(1, (int) $it->quantity), 'price' => $it->quantity ? round($it->price / $it->quantity, 2) : (float) $it->price])->values(),
        ]));
    }

    public function store(Request $request, Order $order)
    {
        $data = $request->validate([
            'rows' => 'required|array|min:1|max:30', 'rows.*.item' => 'nullable|string|max:200', 'rows.*.qty' => 'nullable|integer|min:1|max:999',
            'rows.*.price' => 'nullable|numeric|min:0', 'send_whatsapp' => 'nullable|boolean',
        ]);
        DB::beginTransaction();
        try {
            $made = SoldoutService::add($order, $data['rows']);
            DB::commit();
        } catch (\RuntimeException $e) {
            DB::rollBack();

            return back()->withInput()->with('error', $e->getMessage());
        }
        $msg = 'سُجّل ' . $made->count() . ' منتج نفد بقيمة ' . \App\Services\TreasuryService::fmt($made->sum('price')) . ' د.ل';
        if ($request->boolean('send_whatsapp')) {
            $msg .= SoldoutService::notify($order, $made) ? ' · أُبلغ العميل عبر واتساب ✓' : ' · ⚠ تعذّر إرسال واتساب';
        }

        return back()->with('success', $msg . ' · بانتظار رد العميل');
    }

    /** إعادة إبلاغ العميل بالمنتجات المعلّقة */
    public function notify(Request $request, Order $order)
    {
        $items = OrderSoldout::where('order_id', $order->id)->where('status', 'pending')->get();
        if ($items->isEmpty()) {
            return back()->with('error', 'لا توجد منتجات بانتظار الرد');
        }
        $ok = SoldoutService::notify($order, $items, $request->input('text'));

        return back()->with($ok ? 'success' : 'error', $ok ? 'أُرسلت الرسالة للعميل ✓' : 'تعذّر إرسال واتساب');
    }

    public function decide(Request $request, OrderSoldout $soldout)
    {
        $data = $request->validate([
            'action' => 'required|in:cancel,replace', 'excess' => 'nullable|in:credit,cash', 'payment_id' => 'nullable|exists:payment_methods,id',
            'replacement' => 'required_if:action,replace|nullable|string|max:200', 'replacement_price' => 'required_if:action,replace|nullable|numeric|min:0.01',
            'qty' => 'nullable|integer|min:1|max:999', 'send_whatsapp' => 'nullable|boolean',
        ], ['replacement.required_if' => 'اكتب المنتج البديل', 'replacement_price.required_if' => 'اكتب سعر المنتج البديل']);
        DB::beginTransaction();
        try {
            $new = null;
            if ($data['action'] === 'cancel') {
                SoldoutService::cancel($soldout, $data['excess'] ?? 'credit', $data['payment_id'] ?? null);
            } else {
                $new = SoldoutService::replace($soldout, $data['replacement'], (float) $data['replacement_price'], (int) ($data['qty'] ?? $soldout->qty), $data['excess'] ?? 'credit', $data['payment_id'] ?? null);
            }
            DB::commit();
        } catch (\RuntimeException $e) {
            DB::rollBack();

            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()->with('error', 'حدث خطأ، يرجى المحاولة مرة أخرى');
        }
        if ($new) {
            try {
                \App\Services\PointsService::award($new, false);
            } catch (\Throwable $e) {
                report($e);
            }
        }
        $soldout->refresh();
        $msg = $data['action'] === 'cancel' ? 'أُلغي «' . $soldout->item . '» وخُصمت قيمته' : 'سُجّل البديل في إيصال جديد ' . $new->code;
        if ($soldout->credited > 0) {
            $msg .= ' · ' . \App\Services\TreasuryService::fmt($soldout->credited) . ' د.ل أصبحت رصيداً للعميل';
        }
        if ($request->boolean('send_whatsapp')) {
            $c = $soldout->order->customer;
            $ok = MessageService::sendText('soldout_decision', SoldoutService::decisionText($soldout), array_values(array_filter([optional($c)->phone_1, optional($c)->phone_2])), $c, $soldout->order);
            $msg .= $ok ? ' · أُبلغ العميل ✓' : ' · تعذّر إرسال واتساب';
        }

        return back()->with('success', $msg);
    }
}
