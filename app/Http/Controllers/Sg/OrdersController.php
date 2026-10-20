<?php

namespace App\Http\Controllers\Sg;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Log;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderNotes;
use App\Models\PaidHistory;
use App\Models\PaymentMethod;
use App\Models\Platform;
use App\Models\Status;
use App\Models\TreasuryHistory;
use App\Models\TripPurchase;
use App\Models\User;
use App\Services\MessageService;
use App\Services\OrderPaymentService;
use App\Services\TreasuryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrdersController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:order-list', ['only' => ['index', 'show', 'lookup']]);
        $this->middleware('permission:order-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:order-edit', ['only' => ['pay', 'missing', 'note', 'bulk', 'cancel', 'edit', 'noTrip']]);
        $this->middleware('permission:order-delete', ['only' => ['destroy']]);
    }

    public static function debtSql(): string
    {
        return 'total_price + COALESCE(ship_price,0) - COALESCE(discount,0) - paid_price > 0.009';
    }

    public static function applyStage(Builder $q, ?string $stage): Builder
    {
        $prep = Status::where('stage', 'prep')->pluck('name')->all();
        $open = fn ($q) => $q->where('status', '!=', 'canceld')->where('delivered', false);

        return match ($stage) {
            'none' => $q->where($open)->where('arrived', false)->where(fn ($q) => $q->whereNull('trip_details')->orWhere('trip_details', '')),
            'prep' => $q->where($open)->where('arrived', false)->whereNotNull('trip_details')->where('trip_details', '!=', '')
                ->where(fn ($q) => $q->whereNull('trip_status')->orWhereIn('trip_status', $prep)),
            'way' => $q->where($open)->where('arrived', false)->whereNotNull('trip_details')->where('trip_details', '!=', '')
                ->whereNotNull('trip_status')->whereNotIn('trip_status', $prep),
            'ready' => $q->where($open)->where('arrived', true),
            'debt' => $q->where('status', '!=', 'canceld')->whereRaw(self::debtSql()),
            'done' => $q->where('delivered', true),
            'cx' => $q->where('status', 'canceld'),
            default => $q,
        };
    }

    public function index(Request $request)
    {
        $base = Order::query();
        // نتجاهل قيمة الشهر لو مش بصيغة YYYY-MM (مثلاً لو اتكتب فيها كود زبون بالغلط)
        if ($request->filled('month') && preg_match('/^\d{4}-\d{2}$/', trim($request->month))) {
            [$y, $m] = explode('-', trim($request->month)) + [null, null];
            $base->whereYear('created_at', (int) $y)->whereMonth('created_at', (int) $m);
        }
        $counts = [];
        foreach (['all', 'none', 'prep', 'way', 'ready', 'debt', 'done', 'cx'] as $s) {
            $counts[$s] = self::applyStage(clone $base, $s === 'all' ? null : $s)->count();
        }

        $q = self::applyStage(clone $base, $request->stage)->with(['customer', 'platform']);
        if ($request->filled('q')) {
            $term = trim($request->q);
            // لو المكتوب كود زبون بالضبط: نجيب كل طلباته (ونتجنب الأكواد المشابهة)
            $exactCustIds = Customer::where('code', $term)->pluck('id');
            if ($exactCustIds->isNotEmpty()) {
                $q->where(function ($w) use ($term, $exactCustIds) {
                    $w->whereIn('customer_id', $exactCustIds)->orWhere('code', $term);
                });
            } else {
                $q->where(function ($w) use ($term) {
                    $w->where('code', 'like', "%$term%")->orWhere('trip_details', 'like', "%$term%")
                        ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$term%")->orWhere('code', 'like', "%$term%")
                            ->orWhere('phone_1', 'like', "%$term%")->orWhere('phone_2', 'like', "%$term%"));
                });
            }
        }
        if ($request->filled('platform')) {
            $q->where('platform_id', $request->platform);
        }
        if ($request->filled('trip')) {
            // كل ما يزيد رقم يتفلتر (جزء من رقم الرحلة)
            $q->where('trip_details', 'like', '%' . trim($request->trip) . '%');
        }
        if ($request->filled('user')) {
            $q->where('created_id', $request->user);
        }
        $totals = (clone $q)->selectRaw('COUNT(*) as c, SUM(total_price + COALESCE(ship_price,0) - COALESCE(discount,0)) as v, SUM(paid_price) as p')->first();
        $remaining = (float) (clone $q)->where('status', '!=', 'canceld')->whereRaw(self::debtSql())
            ->selectRaw('SUM(total_price + COALESCE(ship_price,0) - COALESCE(discount,0) - paid_price) as r')->value('r');
        $orders = $q->latest()->paginate(25)->withQueryString();

        return view('sg.orders.index', [
            'nav' => 'ord', 'orders' => $orders, 'counts' => $counts, 'totals' => $totals, 'remaining' => $remaining,
            'platforms' => Platform::visible()->orderBy('name')->get(),
            'trips' => Order::whereNotNull('trip_details')->where('trip_details', '!=', '')->distinct()->orderByDesc('trip_details')->limit(60)->pluck('trip_details'),
            'users' => User::orderBy('name')->get(['id', 'name']),
            'statuses' => Status::orderBy('sort')->get(),
        ]);
    }

    /** إجراءات على إيصالات محددة */
    public function bulk(Request $request)
    {
        $data = $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer|exists:orders,id', 'action' => 'required|in:status,whatsapp,trip,print,shelf', 'shelf' => 'nullable|string|max:30', 'status' => 'nullable|string', 'message' => 'nullable|string|max:1000']);
        $orders = Order::with('customer')->whereIn('id', $data['ids'])->get();
        if ($data['action'] === 'trip') {
            return redirect()->route('finance.trips.create', ['ids' => implode(',', $data['ids'])]);
        }
        if ($data['action'] === 'shelf') {
            // 🗄 مجموعة كبيرة من الطلبات في نفس الرف (ما فيش حد للعدد)
            $shelf = trim((string) ($data['shelf'] ?? ''));
            if ($shelf === '') {
                return back()->with('error', 'اكتب رقم الرف');
            }
            $n = Order::whereIn('id', $data['ids'])->where('delivered', false)->update(['storage_num' => $shelf]);
            \App\Models\Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ' رف ' . $shelf, 'log' => ' حطينا ' . $n . ' إيصال في الرف ' . $shelf]);

            return back()->with('success', '🗄 ' . $n . ' إيصال تحطو في الرف ' . $shelf);
        }
        if ($data['action'] === 'print') {
            return redirect()->route('order.ready.print');
        }
        if ($data['action'] === 'status') {
            if (empty($data['status'])) {
                return back()->with('error', 'اختر الحالة');
            }
            Order::whereIn('id', $data['ids'])->whereNotNull('trip_details')->update(['trip_status' => $data['status']]);
            $sent = 0;
            foreach ($orders as $o) {
                $o->trip_status = $data['status'];
                if ($o->trip_details && MessageService::sendTemplate('trip_status', $o->customer, MessageService::orderVars($o), $o)) {
                    $sent++;
                }
            }

            return back()->with('success', 'تغيرت الحالة وانبعتت ' . $sent . ' رسالة');
        }
        $text = trim((string) $request->message);
        if ($text === '') {
            return back()->with('error', 'اكتب نص الرسالة');
        }
        $sent = 0;
        foreach ($orders as $o) {
            if (MessageService::sendText('manual', MessageService::render($text, MessageService::orderVars($o)), array_filter([$o->customer->phone_1, $o->customer->phone_2]), $o->customer, $o)) {
                $sent++;
            }
        }

        return back()->with('success', 'أُرسلت ' . $sent . ' رسالة من ' . $orders->count());
    }

    // ------------------------------------------------------------ إيصال جديد

    public function create()
    {
        return view('sg.orders.create', [
            'nav' => 'ord',
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'code', 'phone_1']),
            'platforms' => Platform::visible()->orderBy('id')->get(),
            'payments' => PaymentMethod::where('status', true)->get(),
            'nextCode' => \App\Services\OrderNumber::peek(),
        ]);
    }

    /** يبحث على عميل بالتليفون (AJAX) */
    public function lookup(Request $request)
    {
        $phone = preg_replace('/\D/', '', (string) $request->phone);
        if (strlen($phone) < 6) {
            return response()->json(null);
        }
        $tail = substr($phone, -9);
        $c = Customer::where('phone_1', 'like', "%$tail")->orWhere('phone_2', 'like', "%$tail")->first();
        if (!$c) {
            return response()->json(null);
        }
        $orders = Order::where('customer_id', $c->id)->where('status', '!=', 'canceld')->get();

        return response()->json([
            'id' => $c->id, 'name' => $c->name, 'code' => $c->code, 'address' => $c->address,
            'orders' => $orders->count(), 'due' => round($orders->sum(fn ($o) => max(0, $o->remaining())), 2),
            'last' => optional($orders->sortByDesc('created_at')->first())->created_at?->diffForHumans(),
            'vip' => (bool) $c->is_vip,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|string|max:20',
            'customer_id' => 'nullable|exists:customers,id',
            'name' => 'required_without:customer_id|nullable|string|max:120',
            'address' => 'nullable|string|max:150',
            'platform_id' => 'required|exists:platforms,id',
            'mode' => 'required|in:items,total',
            'items' => 'nullable|array',
            'items.*.name' => 'nullable|string|max:200',
            'items.*.variant' => 'nullable|string|max:100',
            'items.*.qty' => 'nullable|integer|min:0',
            'items.*.price' => 'nullable|numeric|min:0',
            'total' => 'nullable|numeric|min:0',
            'qty' => 'nullable|integer|min:0',
            'paid' => 'required|numeric|min:0',
            'payment_id' => 'required|exists:payment_methods,id',
            'note' => 'nullable|string|max:500',
            'send_whatsapp' => 'nullable|boolean',
            'print' => 'nullable|boolean',
        ], ['name.required_without' => 'اكتب اسم العميل الجديد', 'phone.required' => 'اكتب رقم الهاتف']);

        $items = collect($data['items'] ?? [])->filter(fn ($i) => trim($i['name'] ?? '') !== '' && (float) ($i['price'] ?? 0) > 0);
        if ($data['mode'] === 'items') {
            if ($items->isEmpty()) {
                return back()->withInput()->with('error', 'اكتب منتج واحد على الأقل بسعره');
            }
            $total = $items->sum(fn ($i) => max(1, (int) ($i['qty'] ?? 1)) * (float) $i['price']);
            $qty = $items->sum(fn ($i) => max(1, (int) ($i['qty'] ?? 1)));
        } else {
            $total = (float) ($data['total'] ?? 0);
            $qty = (int) ($data['qty'] ?? 1);
            if ($total <= 0) {
                return back()->withInput()->with('error', 'اكتب قيمة الإيصال');
            }
        }
        if ($data['paid'] > $total) {
            return back()->withInput()->with('error', 'العربون أكبر من قيمة الإيصال');
        }

        // ⚠ إيصال مكرر؟ (نفس العميل + نفس القيمة + نفس الكمية، اليوم ولا أمس)
        $dup = \App\Support\DupCheck::find(!empty($data['customer_id']) ? (int) $data['customer_id'] : null, $data['phone'] ?? null, (float) $total, (int) $qty);
        if ($dup && !$request->boolean('confirm_dup')) {
            return back()->withInput()->with('dup_warn', \App\Support\DupCheck::message($dup));
        }
        DB::beginTransaction();
        try {
            $customer = !empty($data['customer_id']) ? Customer::find($data['customer_id']) : null;
            if (!$customer) {
                $phone = preg_replace('/\D/', '', $data['phone']);
                if (Customer::where('phone_1', $phone)->exists()) {
                    $customer = Customer::where('phone_1', $phone)->first();
                } else {
                    $customer = new Customer;
                    $customer->name = $data['name'];
                    $customer->phone_1 = $phone;
                    $customer->address = $data['address'] ?? null;
                    $code = substr($phone, -4);
                    $len = 4;
                    while (Customer::where('code', $code)->exists() && $len < strlen($phone)) {
                        $len++;
                        $code = substr($phone, -$len);
                    }
                    $customer->code = $code;
                    $customer->password = bcrypt($phone);
                    $customer->save();
                }
            }

            $order = new Order;
            $order->code = $this->newCode();
            $order->customer_id = $customer->id;
            $order->platform_id = $data['platform_id'];
            $order->qty = $qty;
            $order->total_price = $total;
            $order->paid_price = 0;
            $order->status = 'unpaid';
            $order->currency = 'dinar';
            $order->currency_sign = 'د.ل';
            $order->payment_id = $data['payment_id'];
            $order->note = $data['note'] ?? null;
            $order->created_id = auth()->id();
            $order->save();

            if ($data['mode'] === 'items') {
                foreach ($items as $i) {
                    $q = max(1, (int) ($i['qty'] ?? 1));
                    OrderItem::create([
                        'order_id' => $order->id,
                        'name' => trim($i['name'] . (!empty($i['variant']) ? ' · ' . $i['variant'] : '')),
                        'quantity' => $q,
                        'price' => $q * (float) $i['price'],
                    ]);
                }
            }
            if ($data['paid'] > 0) {
                OrderPaymentService::pay($order, (float) $data['paid'], (int) $data['payment_id'], 'عربون');
            }
            Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ' طلب جديد (' . $order->code . ')', 'log' => ' تم اضافة طلب جديد للعميل (' . $customer->name . ') بقيمة ' . $total]);
            if ($dup) {
                \App\Support\DupCheck::logOverride($order, $dup);
            }
            DB::commit();
        } catch (\RuntimeException $e) {
            DB::rollBack();

            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()->withInput()->with('error', 'للأسف حدث خطأ، يرجى إعادة المحاولة');
        }

        $msg = 'سُجّل الإيصال ' . $order->code;
        try {
            $order->setRelation('customer', $customer);
            $pts = \App\Services\PointsService::award($order, $request->boolean('send_whatsapp'));
            if ($pts) {
                $msg .= ' · ⭐ ' . $pts . ' نقطة للعميل';
            }
        } catch (\Throwable $e) {
            report($e);
        }
        if ($request->boolean('send_whatsapp')) {
            // 🧾 الإيصال PDF + رسالة قصيرة (رقم الطلب + رابط المنظومة)
            $msg .= \App\Support\OrderPdf::sendNew($order->fresh(['customer', 'items'])) ? ' وانبعت الإيصال PDF على الواتساب ✅' : ' (رسالة الواتساب لم تصل)';
        }
        if ($request->boolean('print')) {
            session()->flash('print_url', route('order.invoice', $order->code));
        }

        return redirect()->route('sg.orders.show', $order)->with('success', $msg);
    }

    protected function newCode(): string
    {
        return \App\Services\OrderNumber::next();
    }

    /** تعديل الإيصال: نفس خانات الفورم القديم بالشكل الجديد، ويحفظ بنفس الكود القديم (order.update) */
    public function edit(Order $order)
    {
        session(['search_results_url' => route('sg.orders.show', $order)]);

        return view('sg.orders.edit', [
            'nav' => 'ord', 'order' => $order->load(['customer', 'items']), 'platforms' => \App\Models\Platform::all(),
        ]);
    }

    // ------------------------------------------------------------ صفحة الإيصال

    public function show(Order $order)
    {
        $order->load(['customer', 'items', 'returns.payment', 'returns.createdUser', 'paids.paymentMethod', 'paids.createdUser', 'notes.user', 'platform', 'createdUser', 'receivedUser', 'deliveredUser', 'tripPurchase']);
        $logs = Log::where('log', 'like', '%' . $order->code . '%')->orWhere('title', 'like', '%' . $order->code . '%')->with('user')->latest()->take(30)->get();
        $messages = \App\Models\WhatsappOutbox::where('order_id', $order->id)->latest()->take(20)->get();
        $custOrders = Order::where('customer_id', $order->customer_id)->count();

        return view('sg.orders.show', [
            'nav' => 'ord', 'order' => $order, 'logs' => $logs, 'messages' => $messages, 'custOrders' => $custOrders,
            'payments' => PaymentMethod::where('status', true)->get(),
            'soldouts' => \Illuminate\Support\Facades\Schema::hasTable('order_soldouts') ? \App\Models\OrderSoldout::with(['newOrder', 'decider'])->where('order_id', $order->id)->orderBy('id')->get() : collect(),
            'replacedFrom' => \Illuminate\Support\Facades\Schema::hasTable('order_soldouts') ? \App\Models\OrderSoldout::with('order')->where('new_order_id', $order->id)->first() : null,
        ]);
    }

    public function pay(Request $request, Order $order)
    {
        $data = $request->validate(['amount' => 'required|numeric|min:0.01', 'payment_id' => 'required|exists:payment_methods,id', 'send_whatsapp' => 'nullable|boolean']);
        if ($data['amount'] > $order->remaining() + 0.01) {
            return back()->with('error', 'المبلغ أكبر من الباقي (' . TreasuryService::fmt($order->remaining()) . ')');
        }
        DB::beginTransaction();
        try {
            OrderPaymentService::pay($order, (float) $data['amount'], (int) $data['payment_id'], 'دفعة');
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()->with('error', 'للأسف حدث خطأ، يرجى إعادة المحاولة');
        }
        if ($request->boolean('send_whatsapp')) {
            MessageService::sendTemplate('payment_received', $order->customer, MessageService::orderVars($order, ['{المدفوع_الآن}' => TreasuryService::fmt($data['amount'])]), $order);
        }

        return back()->with('success', 'سُجّلت الدفعة. الباقي ' . TreasuryService::fmt($order->remaining()) . ' د.ل');
    }

    /** القطع الناقصة: خصم من الباقي / رصيد للعميل / إرجاع أموال */
    public function missing(Request $request, Order $order)
    {
        $data = $request->validate([
            'missing_qty' => 'required|integer|min:1',
            'missing_value' => 'required|numeric|min:0.01',
            'action' => 'required|in:deduct,credit,refund',
            'payment_id' => 'required_if:action,refund|nullable|exists:payment_methods,id',
        ]);
        if ($order->missing_action) {
            return back()->with('error', 'القطع الناقصة متسجلة قبل على الإيصال هذا');
        }
        DB::beginTransaction();
        try {
            $value = (float) $data['missing_value'];
            $order->missing_qty = $data['missing_qty'];
            $order->missing_value = $value;
            $order->missing_action = $data['action'];
            $customer = $order->customer;
            if ($data['action'] === 'deduct') {
                $order->discount = (float) $order->discount + $value;
                $order->discount_reason = trim(($order->discount_reason ? $order->discount_reason . ' + ' : '') . 'قطع ناقصة');
            } elseif ($data['action'] === 'credit') {
                // لا يُخصم من الإيصال، يصبح رصيداً عند العميل للطلبية القادمة
                $customer->credit = (float) $customer->credit + $value;
                $customer->save();
            } else {
                // إرجاع أموال: يُخصم من الإيصال ويظهر من الخزينة
                $order->discount = (float) $order->discount + $value;
                $order->discount_reason = trim(($order->discount_reason ? $order->discount_reason . ' + ' : '') . 'قطع ناقصة (رجعنا المبلغ)');
                $refund = min($value, max(0, (float) $order->paid_price - ($order->grandTotal())));
                if ($refund > 0) {
                    $order->paid_price -= $refund;
                    TreasuryService::withdraw($refund, $order->currency ?: 'dinar', (int) $data['payment_id'], 'order', $order->id, 'إرجاع قطع ناقصة ' . $order->code);
                }
            }
            $order->refreshPaymentStatus();
            $order->save();
            Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ' قطع ناقصة (' . $order->code . ')', 'log' => ' ' . $data['missing_qty'] . ' قطع بقيمة ' . $value . ' · ' . ['deduct' => 'خصم من الباقي', 'credit' => 'رصيد للعميل', 'refund' => 'إرجاع أموال'][$data['action']]]);
            DB::commit();
        } catch (\RuntimeException $e) {
            DB::rollBack();

            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()->with('error', 'للأسف حدث خطأ، يرجى إعادة المحاولة');
        }

        return back()->with('success', 'سُجّلت القطع الناقصة');
    }

    public function note(Request $request, Order $order)
    {
        $data = $request->validate(['note' => 'required|string|max:1000']);
        $visible = $request->boolean('to_customer');
        $sent = false;
        if ($visible && $order->customer) {
            $sent = \App\Services\MessageService::sendTemplate('customer_note', $order->customer, \App\Services\MessageService::orderVars($order, ['{الملاحظة}' => $data['note']]), $order);
        }
        OrderNotes::forceCreate([
            'order_id' => $order->id, 'note' => $data['note'], 'note_hash' => hash('sha256', $data['note'] . microtime()),
            'note_date' => now()->toDateString(), 'user_id' => auth()->id(), 'visible' => $visible, 'sent' => $sent,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return back()->with('success', $visible ? ($sent ? 'سُجّلت الملاحظة وانبعتت للعميل ✓' : 'سُجّلت وتظهر في حساب العميل (الواتساب لم يُرسل)') : 'سُجّلت الملاحظة (داخلية)');
    }

    /** 🚫✈ «بدون رحلة»: الطلبية ما تدخل رحلة — الزبون يشوف «سيصل قريباً» بدل حالة الرحلة (والزر نفسه يرجّعها) */
    public function noTrip(Order $order)
    {
        if ($order->delivered || $order->status === 'canceld') {
            return back()->with('error', 'الطلبية متسلمة أو ملغاة');
        }
        if (!\Illuminate\Support\Facades\Schema::hasColumn('orders', 'no_trip')) {
            return back()->with('error', 'شغّل التحديث أول من صفحة /system-update');
        }
        if (!$order->no_trip && $order->trip_details) {
            return back()->with('error', 'الطلبية داخلة في رحلة ' . $order->trip_details . ' — ما تنفعش بدون رحلة');
        }
        $order->no_trip = !$order->no_trip;
        $order->save();
        Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ($order->no_trip ? ' بدون رحلة (' : ' إلغاء بدون رحلة (') . $order->code . ')',
            'log' => $order->no_trip ? ' الزبون يشوف: سيصل قريباً' : ' رجعت الحالة العادية']);

        return back()->with('success', $order->no_trip ? 'الطلبية ' . $order->code . ' بدون رحلة · الزبون يشوف «سيصل قريباً»' : 'رجعنا الحالة العادية للطلبية ' . $order->code);
    }

    public function cancel(Order $order)
    {
        if ($order->delivered) {
            return back()->with('error', 'لا يمكن تلغي طلبية متسلمة');
        }
        $order->status = 'canceld';
        $order->save();
        \App\Services\PointsService::reverse($order);
        Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ' إلغاء الطلب (' . $order->code . ')', 'log' => ' تم إلغاء الطلب ' . $order->code . ' · المدفوع ' . $order->paid_price . ' يحتاج يترجع يدوي إذا يجب']);

        return back()->with('success', 'أُلغي الإيصال. إذا العميل دفع عربون، أعِده من الخزينة');
    }

    /**
     * حذف إيصال نهائي حتى إذا دافع:
     *  refund = cash   → المدفوع يُصرف من الخزينة (رجعناله فلوسه)
     *  refund = credit → المدفوع يصبح رصيداً للعميل
     *  refund = keep   → المبلغ تبقى في الخزينة (مثلا عربون ما يترجعش)
     */
    public function destroy(Request $request, Order $order)
    {
        $data = $request->validate([
            'refund' => 'required|in:cash,credit,keep',
            'payment_id' => 'required_if:refund,cash|nullable|exists:payment_methods,id',
            'reason' => 'nullable|string|max:200',
        ]);
        if (\Illuminate\Support\Facades\Schema::hasTable('shein_card_uses') && \App\Models\SheinCardUse::where('order_id', $order->id)->exists()) {
            return back()->with('error', 'الإيصال عليه بطاقات SHEIN — رجّع البطاقات من صفحة البطاقات قبل الحذف');
        }
        $paid = round((float) $order->paid_price, 2);
        $code = $order->code;
        $customer = $order->customer;
        DB::beginTransaction();
        try {
            if ($paid > 0.009 && $order->status !== 'canceld') {
                if ($data['refund'] === 'cash') {
                    TreasuryService::withdraw($paid, $order->currency ?: 'dinar', (int) $data['payment_id'], 'order', $order->id, 'إرجاع أموال حذف إيصال ' . $code);
                } elseif ($data['refund'] === 'credit' && $customer) {
                    $customer->credit = (float) $customer->credit + $paid;
                    $customer->save();
                }
            }
            \App\Services\PointsService::reverse($order);
            if (\Illuminate\Support\Facades\Schema::hasTable('delivery_order')) {
                DB::table('delivery_order')->where('order_id', $order->id)->delete();
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('order_returns')) {
                \App\Models\OrderReturn::where('order_id', $order->id)->delete();
            }
            PaidHistory::where('order_id', $order->id)->get()->each->delete();
            \App\Models\OrderItem::where('order_id', $order->id)->get()->each->delete();
            \App\Models\OrderNotes::where('order_id', $order->id)->get()->each->delete();
            $order->delete();
            Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ' حذف الإيصال (' . $code . ')',
                'log' => ' حذف نهائي · ' . optional($customer)->name . ' · المدفوع ' . $paid . ' · ' . ['cash' => 'رجعناله أموال من الخزينة', 'credit' => 'أصبح رصيداً للعميل', 'keep' => 'المبلغ قعدت'][$data['refund']] . ($data['reason'] ?? '' ? ' · السبب: ' . $data['reason'] : '')]);
            DB::commit();
        } catch (\RuntimeException $e) {
            DB::rollBack();

            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()->with('error', 'للأسف حدث خطأ، يرجى إعادة المحاولة');
        }
        \Illuminate\Support\Facades\Cache::forget('sg.badge.ready');

        return redirect()->route('sg.orders.index')->with('success', 'انحذف الإيصال ' . $code . ($paid > 0.009 ? ' · ' . ['cash' => 'رجعنا ' . TreasuryService::fmt($paid) . ' د.ل من الخزينة', 'credit' => TreasuryService::fmt($paid) . ' د.ل أصبحت رصيداً للعميل', 'keep' => 'المدفوع قعد في الخزينة'][$data['refund']] : ''));
    }

    /** 🛵 ابعت مندوب يقبض العربون على الإيصال */
    public function depositCourier(Request $request, Order $order)
    {
        abort_unless(auth()->user()->can('order-edit') || auth()->user()->can('courier-list'), 403);
        $data = $request->validate(['amount' => 'required|numeric|min:0', 'courier_id' => 'nullable|integer', 'fee' => 'nullable|numeric|min:0', 'notify' => 'nullable|boolean']);
        $courier = !empty($data['courier_id']) ? \App\Models\User::find((int) $data['courier_id']) : null;
        try {
            $dl = \App\Services\CourierService::orderDeposit($order, (float) $data['amount'], $courier, (float) ($data['fee'] ?? 0));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        $c = $order->customer;
        if ($request->boolean('notify') && $c && $dl->courier) {
            try {
                \App\Services\MessageService::sendText('customer_note', "مرحبا {$c->name} 👋\n🛵 المندوب *{$dl->courier->name}* جاي يقبض عربون طلبك *{$order->code}*\n💵 المبلغ: *" . \App\Services\TreasuryService::fmt($dl->amount) . " د.ل*\nبيكلمك قبل ما يوصل 📱", [$c->phone_1], $c, $order);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return back()->with('success', '🛵 انبعت للمندوب ' . (optional($dl->courier)->name ?: '— ما فيش مندوب متاح، عيّنه من صفحة المندوبين') . ' · يقبض ' . \App\Services\TreasuryService::fmt($dl->toCollect()) . ' د.ل');
    }

    /** ✎ تعديل قيمة دفعة على الإيصال */
    public function payEdit(Request $request, Order $order, PaidHistory $paid)
    {
        abort_unless((int) $paid->order_id === (int) $order->id, 404);
        abort_unless(auth()->user()->can('order-edit'), 403);
        $data = $request->validate(['amount' => 'required|numeric|min:0', 'payment_id' => 'required|exists:payment_methods,id']);
        $max = max(0, $order->grandTotal() - ((float) $order->paid_price - (float) $paid->paid_price));
        if ((float) $data['amount'] > $max + 0.01) {
            return back()->with('error', 'المبلغ أكبر من قيمة الإيصال — أقصى شي ' . \App\Services\TreasuryService::fmt($max) . ' د.ل');
        }
        DB::beginTransaction();
        try {
            \App\Services\OrderPaymentService::edit($paid, (float) $data['amount'], (int) $data['payment_id']);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()->with('error', 'ما تعدلتش: ' . mb_substr($e->getMessage(), 0, 150));
        }

        return back()->with('success', (float) $data['amount'] > 0 ? '✓ تعدلت الدفعة · المدفوع توا ' . \App\Services\TreasuryService::fmt($order->fresh()->paid_price) . ' د.ل' : '✓ انحذفت الدفعة');
    }
}
