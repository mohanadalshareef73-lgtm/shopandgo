<?php

namespace App\Http\Controllers\Sg;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Services\Csv;
use App\Services\MessageService;
use App\Services\Period;
use App\Services\ReturnService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** المرتجعات والاستبدال */
class ReturnsController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:return-list', ['only' => ['index']]);
        $this->middleware('permission:return-create', ['only' => ['store', 'customerOrders']]);
    }

    public function index(Request $request)
    {
        $p = Period::make($request->input('p', 'month'));
        $q = OrderReturn::with(['order', 'customer', 'payment', 'createdUser', 'receiver'])->whereBetween('created_at', [$p->from, $p->to]);
        if ($request->filled('type')) {
            $q->where('type', $request->type);
        }
        if ($request->filled('q')) {
            $t = trim($request->q);
            $q->where(fn ($w) => $w->whereHas('order', fn ($o) => \App\Support\Search::code($o, $t))->orWhereHas('customer', fn ($c) => \App\Support\Search::customer($c, $t)));
        }
        $all = (clone $q)->get();
        if ($request->export === 'csv') {
            return Csv::download('returns.csv', ['التاريخ', 'النوع', 'الإيصال', 'العميل', 'القطع', 'ماذا رجع', 'القيمة', 'زيادة', 'المبلغ', 'صُرف من الخزينة', 'البضاعة', 'السبب'],
                $all->map(fn ($r) => [$r->created_at->format('Y-m-d H:i'), $r->typeLabel(), optional($r->order)->code, optional($r->customer)->name, $r->qty, $r->items, $r->amount, $r->extra, $r->methodLabel(), $r->refunded, OrderReturn::GOODS[$r->goods] ?? $r->goods, $r->reason]));
        }

        return view('sg.returns.index', [
            'nav' => 'ret', 'period' => $p, 'rows' => $q->latest()->paginate(30)->withQueryString(),
            'kpi' => [
                'count' => $all->count(), 'qty' => $all->sum('qty'), 'amount' => $all->sum('amount'), 'refunded' => $all->sum('refunded'),
                'credit' => $all->where('method', 'credit')->sum('amount'), 'exchanges' => $all->where('type', 'exchange')->count(),
                'orders' => Order::whereBetween('created_at', [$p->from, $p->to])->where('status', '!=', 'canceld')->count(),
            ],
            'users' => \App\Models\User::where('status', true)->orderBy('name')->get(['id', 'name']),
            'payments' => \App\Models\PaymentMethod::where('status', true)->get(),
            'reasons' => $all->whereNotNull('reason')->groupBy('reason')->map->count()->sortDesc()->take(6),
        ]);
    }

    /** إيصالات العميل (لفورم المرتجع من صفحة المرتجعات) */
    public function customerOrders(Request $request)
    {
        $t = trim((string) $request->q);
        if ($t === '') {
            return response()->json([]);
        }
        $orders = Order::with('customer')->where('status', '!=', 'canceld')
            ->where(fn ($w) => $w->where('code', 'like', "%$t%")->orWhereHas('customer', fn ($c) => $c->where('code', 'like', '%' . $t . '%')->orWhere('name', 'like', "%$t%")->orWhere('phone_1', 'like', "%$t%")))
            ->latest()->limit(25)->get();

        return response()->json($orders->map(fn ($o) => ['id' => $o->id, 'code' => $o->code, 'name' => optional($o->customer)->name, 'ccode' => optional($o->customer)->code, 'qty' => $o->qty, 'total' => round($o->total_price, 2), 'paid' => round($o->paid_price, 2), 'st' => $o->stageLabel()]));
    }

    public function store(Request $request, Order $order)
    {
        $data = $request->validate([
            'type' => 'required|in:return,exchange',
            'qty' => 'nullable|integer|min:0|max:100000',
            'items' => 'nullable|string|max:500',
            'amount' => 'nullable|numeric|min:0',
            'extra' => 'nullable|numeric|min:0',
            'method' => 'required|in:deduct,cash,credit,none',
            'payment_id' => 'nullable|exists:payment_methods,id',
            'goods' => 'required|in:shop,supplier,damaged',
            'reason' => 'nullable|string|max:300',
            'new_items' => 'nullable|string|max:500',
            'send_whatsapp' => 'nullable|boolean',
            'received_by' => 'nullable|exists:users,id', 'location' => 'nullable|string|max:120',
        ]);
        DB::beginTransaction();
        try {
            $r = ReturnService::create($order, collect($data)->except(['received_by', 'location'])->all());
            $r->received_by = $data['received_by'] ?? auth()->id();
            $r->location = $data['location'] ?? null;
            $r->save();
            DB::commit();
        } catch (\RuntimeException $e) {
            DB::rollBack();

            return back()->with('error', $e->getMessage())->withInput();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()->with('error', 'للأسف حدث خطأ، يرجى إعادة المحاولة');
        }
        if ($request->boolean('send_whatsapp')) {
            MessageService::sendTemplate('return_done', $order->customer, MessageService::orderVars($order->fresh(), ['{المرتجع}' => ReturnService::customerText($r)]), $order);
        }

        return back()->with('success', 'سُجّل ال' . $r->typeLabel() . ($r->refunded > 0 ? ' · صُرف من الخزينة ' . \App\Services\TreasuryService::fmt($r->refunded) . ' د.ل' : '') . ($r->method === 'credit' ? ' · وُضع رصيد للعميل' : ''));
    }
}
