<?php

namespace App\Http\Controllers\Sg;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Log;
use App\Models\Order;
use App\Models\TreasuryHistory;
use App\Models\User;
use App\Services\AppSettings;
use App\Services\MessageService;
use App\Services\TreasuryService as TS;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** 🛵 تجهيز طلعة: نختارو الجاهز، نعطوه لمندوب (بحساب أو بالاسم) أو لطابور خارج بنغازي، ونطبعو كشف PDF */
class DispatchController extends Controller
{
    public const ACTIVE = ['requested', 'assigned', 'out', 'problem'];

    protected ?float $silentAmount = null;

    protected float $silentGot = 0;

    public function __construct()
    {
        $this->middleware('permission:courier-list', ['only' => ['index', 'pdf', 'receipt']]);
        $this->middleware('permission:courier-edit', ['only' => ['assign', 'act', 'bulk']]);
    }

    protected function readyQuery()
    {
        $active = Delivery::whereIn('status', ['requested', 'assigned', 'out', 'problem'])->pluck('id');

        return Order::where('arrived', true)->where('delivered', false)->where('status', '!=', 'canceld')
            ->where(fn ($w) => $w->whereNull('delivery_id')->orWhereNotIn('delivery_id', $active));
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->q);
        $tab = $request->input('tab') === 'out' ? 'out' : 'in';
        $ready = $this->readyQuery()->with('customer')
            ->when($q !== '', function ($w) use ($q) {
                $ids = Customer::where(fn ($c) => $c->where('code', 'like', '%' . $q . '%')->orWhere('name', 'like', '%' . $q . '%')->orWhere('phone_1', 'like', '%' . $q . '%'))->pluck('id');
                $w->where(fn ($x) => $x->whereIn('customer_id', $ids)->orWhere('code', 'like', $q . '%'));
            })
            ->orderBy('arrived_at')->get()->groupBy('customer_id')->take(300);
        if (!\Illuminate\Support\Facades\Schema::hasColumn('deliveries', 'zone')) {
            return redirect()->route('sg.couriers')->with('error', 'لازم تفتح /system-update مرة وحدة باش تخدم صفحة طلبيات دليفري');
        }
        // كل التوصيلات المفتوحة — حتى طلبات العملاء من حساباتهم تطلع هني طول
        $all = Delivery::with(['customer', 'orders', 'courier'])->where(fn ($w) => $w->whereIn('status', self::ACTIVE)->when(\Illuminate\Support\Facades\Schema::hasColumn('deliveries', 'kept'), fn ($x) => $x->orWhere('kept', true)))->latest('id')->get();
        $all->each(fn ($d) => $d->zone = $d->zone ?: (static::outside($d->city) ? 'out' : 'in'));
        // 💵 تحصيل عربون/حجز جاي من رابط العميل ← يقعد «عربون» تلقائي
        foreach ($all as $d) {
            if (($d->deposit_order_id || $d->cart_id) && $d->kind !== 'deposit') {
                Delivery::whereKey($d->id)->update(['kind' => 'deposit']);
                $d->kind = 'deposit';
            }
        }
        $queue = $all->where('zone', $tab)->values();
        // 🔍 خارج بنغازي: بحث بكود شركة التوصيل
        // 🛵 فرز: استلمها مندوب · مازال
        $taken = fn ($d) => $d->courier_id || $d->courier_name;
        $fc = ['all' => $queue->count(), 'taken' => $queue->filter($taken)->count(), 'wait' => $queue->reject($taken)->count()];
        $fl = in_array($request->f, ['taken', 'all', 'wait'], true) ? $request->f : (trim((string) $request->xq) !== '' ? 'all' : 'wait'); // الافتراضي: اللي مازال ما خذاهاش مندوب
        if ($fl !== 'all') {
            $queue = ($fl === 'taken' ? $queue->filter($taken) : $queue->reject($taken))->values();
        }
        $xq = trim((string) $request->xq);
        if ($xq !== '') {
            $queue = $queue->filter(fn ($d) => str_contains(mb_strtolower((string) $d->ext_code), mb_strtolower($xq)) || str_contains((string) optional($d->customer)->code, $xq) || str_contains((string) $d->recipient_phone, $xq))->values();
        }

        // ⚠ مشاكل الطلبيات: مشكلة التوصيل + مشاكل الإيصال المفتوحة (نواقص · لم يصل · تالف …)
        $oids = $queue->flatMap(fn ($d) => $d->orders->pluck('id'))->unique();
        $iss = $oids->isNotEmpty() && \Illuminate\Support\Facades\Schema::hasTable('order_issues') ? \App\Models\OrderIssue::whereIn('order_id', $oids)->where('status', 'open')->get()->groupBy('order_id') : collect();
        $probs = $queue->mapWithKeys(function ($d) use ($iss) {
            $l = [];
            if ($d->problem) {
                $l[] = '🛵 ' . $d->problem;
            }
            foreach ($d->orders as $o) {
                if ((int) $o->missing_qty > 0 && in_array($o->missing_action, [null, ''], true)) {
                    $l[] = '📉 ' . $o->code . ': نواقص ' . (int) $o->missing_qty . ' قطعة';
                }
                foreach ($iss[$o->id] ?? [] as $i) {
                    $l[] = (\App\Models\OrderIssue::TYPES[$i->type][1] ?? '⚠') . ' ' . $o->code . ': ' . (\App\Models\OrderIssue::TYPES[$i->type][0] ?? $i->type) . ($i->qty ? ' ' . $i->qty . ' قطعة' : '') . ($i->note ? ' — ' . $i->note : '');
                }
            }

            return [$d->id => $l];
        });

        // 🛒 حجوزات السلات اللي في الطابور: تأكيد الحجز ← ⚡ احجز الان من هني
        $cartIds = $queue->pluck('cart_id')->filter()->unique();
        $carts = $cartIds->isNotEmpty() ? \App\Models\CartQuote::whereIn('id', $cartIds)->get()->keyBy('id') : collect();
        $reps = $carts->mapWithKeys(function ($ct) {
            try {
                $q = $ct->retailer === 'amazon' ? (array) $ct->amazon_pricing : app(\App\Services\SheinQuotationPdf::class)->quotationForPdf($ct);
                $r = $ct->retailer === 'amazon' ? (int) data_get($q, 'quantity', 1) > 1 : collect((array) ($q['items'] ?? []))->contains(fn ($i) => (int) ($i['qty'] ?? 0) > 1 && ($i['availability'] ?? 'available') === 'available');
            } catch (\Throwable $e) {
                $r = false;
            }

            return [$ct->id => $r];
        });
        $cartOrders = $carts->pluck('order_id')->filter()->isNotEmpty() ? Order::whereIn('id', $carts->pluck('order_id')->filter())->pluck('code', 'id') : collect();
        $nums = (array) AppSettings::get('booking_numbers', []);
        $bookTo = (string) AppSettings::get('booking_last_phone', $nums[0] ?? '');

        return view('sg.dispatch.index', [
            'recent' => Delivery::with(['customer', 'orders'])->whereIn('status', ['delivered', 'returned', 'cancelled'])->where('updated_at', '>=', now()->subHours(36))->latest('updated_at')->take(40)->get(),
            'nav' => 'dispatch', 'fl' => $fl, 'fc' => $fc, 'xq' => $xq, 'payments' => \App\Models\PaymentMethod::where('status', true)->orderBy('id')->get(), 'cashId' => AppSettings::cashPaymentId(), 'carts' => $carts, 'probs' => $probs, 'reps' => $reps, 'cartOrders' => $cartOrders, 'bookTo' => $bookTo, 'q' => $q, 'tab' => $tab, 'ready' => $ready, 'queue' => $queue,
            'couriers' => User::where('type_id', 4)->where('status', true)->orderBy('name')->get(),
            'counts' => $all->countBy('zone'),
            'batches' => $queue->pluck('batch')->filter()->unique()->sortDesc()->values(),
            'defFee' => (float) AppSettings::get('courier_default_fee', 0),
        ]);
    }

    /** نعيّنو الزبائن المختارين: كل زبون = توصيل واحد */
    public function assign(Request $request)
    {
        $d = $request->validate([
            'c' => 'required|array|min:1', 'c.*' => 'integer',
            'kind' => 'nullable|array', 'kind.*' => 'in:order,deposit',
            'courier_id' => 'nullable|integer', 'courier_name' => 'nullable|string|max:100',
            'zone' => 'required|in:in,out', 'fee' => 'nullable|numeric|min:0', 'fee_c' => 'nullable|array', 'fee_c.*' => 'nullable|numeric|min:0',
        ]);
        $courier = !empty($d['courier_id']) ? User::where('type_id', 4)->find($d['courier_id']) : null;
        $name = trim((string) ($d['courier_name'] ?? ''));
        if (!$courier && $name === '' && $d['zone'] === 'in') {
            return back()->with('error', 'اختار مندوب ولا اكتب اسمه');
        }
        $batch = 'T-' . now()->format('md-Hi');
        $made = collect();
        DB::transaction(function () use ($d, $courier, $name, $batch, &$made) {
            foreach (array_unique($d['c']) as $cid) {
                $cu = Customer::find($cid);
                $orders = $cu ? $this->readyQuery()->where('customer_id', $cu->id)->get() : collect();
                if (!$cu || $orders->isEmpty()) {
                    continue;
                }
                $dl = $this->make($cu, $orders, (float) ($d['fee_c'][$cid] ?? $d['fee'] ?? 0), $d['zone'] === 'out' ? 'خارج بنغازي' : null, $courier);
                $dl->courier_name = $courier ? null : ($name !== '' ? $name : null);
                $dl->kind = ($d['kind'][$cid] ?? 'order') === 'deposit' ? 'deposit' : 'order';
                $dl->zone = $d['zone'];
                $dl->batch = $batch;
                if (!$courier && $name !== '') {
                    $dl->status = 'out';
                    $dl->assigned_at = now();
                }
                $dl->save();
                if (!$courier && $name !== '') {
                    $dl->log('out', 'طلعة ' . $batch . ' مع ' . $name);
                }
                $made->push($dl);
            }
        });
        if ($made->isEmpty()) {
            return back()->with('error', 'ما فيش طلبيات جاهزة للزبائن المختارين');
        }
        foreach ($made->where('kind', 'deposit') as $dl) {
            $this->depositMsg($dl);
        }
        Cache::forget('sg.badge.ready');
        Cache::forget('sg.badge.courier');

        return redirect()->route('sg.dispatch', ['tab' => $d['zone']])
            ->with('success', '✓ ' . $made->count() . ' زبون مع ' . ($courier ? $courier->name : ($name ?: 'طابور خارج بنغازي')))
            ->with('print_url', route('sg.dispatch.pdf', ['batch' => $batch]));
    }

    /** إجراء على توصيل في الطابور: نوع · تحويل · تغيير المندوب · سلّم · رجّع */
    public function act(Request $request, Delivery $delivery)
    {
        $d = $request->validate(['a' => 'required|in:kind,move,courier,done,back,fee,silent,collect,ext,settle,note,hide,problem,delete,restore', 'text' => 'nullable|string|max:1000', 'amount' => 'nullable|numeric|min:0', 'ext_code' => 'nullable|string|max:60', 'payment_id' => 'nullable|integer', 'courier_id' => 'nullable|integer', 'courier_name' => 'nullable|string|max:100', 'fee' => 'nullable|numeric|min:0']);
        if ($d['a'] === 'delete') {
            if ($delivery->status === 'delivered') {
                return back()->with('error', 'التوصيل هذا تسلّم وفلوسه تسجلت — ما ينحذفش (استعمل ✓ للإخفاء)');
            }
            // 🗑 حذف نهائي: التوصيل ينمسح · الإيصالات ترجع للرف · الحجز (لو سلة) يتخفى نهائي
            $info = $delivery->code . ' · ' . ($delivery->recipient_name ?: optional($delivery->customer)->name) . ($delivery->cart_id ? ' · سلة #' . $delivery->cart_id : '') . ' · ' . $delivery->orders->pluck('code')->implode('، ');
            DB::transaction(function () use ($delivery) {
                Order::where('delivery_id', $delivery->id)->where('delivered', false)->update(['delivery_id' => null]);
                if ($delivery->cart_id && ($cart = \App\Models\CartQuote::find($delivery->cart_id)) && $cart->portal_status === 'ordered') {
                    $cart->portal_status = 'ignored';
                    $cart->save();
                }
                DB::table('delivery_order')->where('delivery_id', $delivery->id)->delete();
                if (\Illuminate\Support\Facades\Schema::hasTable('delivery_events')) {
                    DB::table('delivery_events')->where('delivery_id', $delivery->id)->delete();
                }
                $delivery->delete();
            });
            Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ' حذف توصيل نهائي', 'log' => ' ' . $info]);
            Cache::forget('sg.badge.ready');
            Cache::forget('sg.badge.courier');
            Cache::forget('sg.badge.pcart');

            return back()->with('success', '🗑 انحذف نهائي');
        }
        if ($d['a'] === 'hide') {
            $delivery->kept = false;
            $delivery->save();

            return back()->with('success', '✓ تخفى · تلقاه في «تأكيد الحجوزات ← متأكدة»');
        }
        if ($d['a'] === 'restore') {
            try {
                $msg = $this->restore($delivery);
            } catch (\Throwable $e) {
                report($e);

                return back()->with('error', 'ما رجعش: ' . $e->getMessage());
            }

            return back()->with('success', '↩ ' . $msg);
        }
        abort_unless(in_array($delivery->status, self::ACTIVE, true), 422, 'التوصيل هذا مقفول');
        $cu = $delivery->customer;
        switch ($d['a']) {
            case 'problem':
                // ⚠ مشكلة في الطلبية (فاضي = تنحل)
                $txt = trim((string) ($d['text'] ?? ''));
                $delivery->problem = $txt !== '' ? $txt : null;
                $delivery->status = $txt !== '' ? 'problem' : ($delivery->courier_id || $delivery->courier_name ? 'out' : 'requested');
                $delivery->save();
                $delivery->log($delivery->status, $txt !== '' ? '⚠ مشكلة: ' . $txt : 'المشكلة انحلت');

                return back()->with('success', $txt !== '' ? '⚠ تسجلت المشكلة' : '✓ المشكلة انحلت');
            case 'note':
                $txt = trim((string) ($d['text'] ?? ''));
                $ph = optional($cu)->phone_1 ?: $delivery->recipient_phone;
                if ($txt === '' || !$ph) {
                    return back()->with('error', $txt === '' ? 'اكتب الملاحظة' : 'ما فيش رقم للعميل');
                }
                $nm = optional($cu)->name ?: $delivery->recipient_name;
                $msg = trim(($nm ? $nm . " 👋\n" : '') . '📝 بخصوص طلبك' . ($delivery->cart_id ? ' (سلة #' . $delivery->cart_id . ')' : ($delivery->orders->count() ? ' (' . $delivery->orders->pluck('code')->implode('، ') . ')' : '')) . ":\n\n" . $txt);
                $ok = MessageService::sendText('cart_note', $msg, [$ph], $cu);
                $delivery->log($delivery->status, '📝 ملاحظة للعميل: ' . mb_substr($txt, 0, 200));

                return back()->with($ok ? 'success' : 'error', $ok ? '📝 انبعتت الملاحظة' : 'الرسالة ما وصلتش (الواتساب)');
            case 'ext':
                $delivery->ext_code = trim((string) ($d['ext_code'] ?? '')) ?: null;
                $delivery->save();
                $delivery->log($delivery->status, 'كود شركة التوصيل: ' . ($delivery->ext_code ?: '—'));

                return $request->expectsJson() ? response()->json(['ok' => true]) : back()->with('success', '✓ تحفظ الكود');
            case 'settle':
                try {
                    $got = $this->settleOut($delivery, (int) ($d['payment_id'] ?? AppSettings::cashPaymentId()), $request->filled('amount') ? (float) $request->amount : null);
                } catch (\Throwable $e) {
                    report($e);

                    return back()->with('error', $e->getMessage());
                }

                return back()->with('success', '💰 تحصّل ' . TS::fmt($got) . ' د.ل' . ($delivery->ext_code ? ' · كود ' . $delivery->ext_code : '') . ' (بدون رسالة)');
            case 'collect':
                // ✓ تأكيد: المندوب قبض ← نتأكدو من القيمة (لو تغيرت نعدلوها) ← ينعمل الإيصال ويوصل العميل
                if (!$delivery->cart_id) {
                    try {
                        if ($request->filled('amount')) {
                            $delivery->amount = round((float) $request->amount, 2);
                            $delivery->save();
                        }
                        $this->deliverSilent($delivery);
                    } catch (\RuntimeException $e) {
                        return back()->with('error', $e->getMessage());
                    }

                    return back()->with('success', '✅ تأكد');
                }
                try {
                    if ($request->filled('amount')) {
                        $delivery->amount = round((float) $request->amount, 2);
                        $delivery->save();
                    }
                    $dl = $delivery->fresh(['orders', 'courier', 'customer']);
                    \App\Services\CourierService::markDelivered($dl, $dl->toCollect(), 0, 'تأكيد من طلبيات دليفري' . ($dl->courier_name ? ' · ' . $dl->courier_name : ''));
                } catch (\Throwable $e) {
                    report($e);

                    return back()->with('error', 'ما تمش: ' . $e->getMessage());
                }
                Cache::forget('sg.badge.pcart');
                $cart = \App\Models\CartQuote::find($delivery->cart_id);
                if ($cart) {
                    $cart->portal_status = 'confirmed';
                    if (\Illuminate\Support\Facades\Schema::hasColumn('cart_quotes', 'confirmed_at')) {
                        $cart->confirmed_at = now();
                    }
                    $cart->save();
                }
                if (\Illuminate\Support\Facades\Schema::hasColumn('deliveries', 'kept')) {
                    Delivery::whereKey($delivery->id)->update(['kept' => true]);
                }
                $o = $cart && $cart->order_id ? Order::find($cart->order_id) : null;
                $r = back()->with('success', '✅ تأكد الحجز #' . $delivery->cart_id . ($o ? ' · الإيصال ' . $o->code . ' انعمل ووصل العميل' : ''));

                return $o ? $r->with('print_url', route('order.invoice', $o->code)) : $r;
            case 'silent':
                try {
                    $this->silentAmount = $request->filled('amount') ? round((float) $request->amount, 2) : null;
                    if ($this->silentAmount !== null && $delivery->kind === 'deposit') {
                        $delivery->amount = $this->silentAmount;
                        $delivery->save();
                    }
                    $oids = $delivery->orders->pluck('id')->implode(',');
                    $this->deliverSilent($delivery);
                } catch (\RuntimeException $e) {
                    return back()->with('error', $e->getMessage());
                }
                if ($oids !== '' && $delivery->kind !== 'deposit') {
                    return back()->with('success', '✅ ' . (optional($cu)->name ?: $delivery->recipient_name) . ' مستلمة (بدون رسالة)')
                        ->with('print_url', route('sg.delivery.slip', ['ids' => $oids, 'paid' => $this->silentGot]));
                }

                return back()->with('success', '✅ ' . (optional($cu)->name ?: $delivery->recipient_name) . ' مستلمة (بدون رسالة)');
            case 'fee':
                $old = (float) $delivery->fee;
                $delivery->fee = round((float) ($d['fee'] ?? 0), 2);
                $delivery->save();
                $delivery->log($delivery->status, 'سعر التوصيل: ' . TS::fmt($old) . ' ← ' . TS::fmt($delivery->fee));

                return $request->expectsJson() ? response()->json(['ok' => true, 'fee' => $delivery->fee]) : back()->with('success', '✓ تغير سعر التوصيل');
            case 'kind':
                $delivery->kind = $delivery->kind === 'deposit' ? 'order' : 'deposit';
                $delivery->save();
                $delivery->log($delivery->status, 'النوع: ' . ($delivery->kind === 'deposit' ? 'عربون' : 'طلبية'));
                if ($delivery->kind === 'deposit') {
                    $this->depositMsg($delivery);
                }

                return back()->with('success', 'تحول لـ' . ($delivery->kind === 'deposit' ? 'عربون (انبعتله الرابط والتفاصيل)' : 'طلبية'));
            case 'move':
                $cur = $delivery->zone ?: (static::outside($delivery->city) ? 'out' : 'in');
                $delivery->zone = $cur === 'out' ? 'in' : 'out';
                $delivery->save();
                $delivery->log($delivery->status, $delivery->zone === 'out' ? 'تحول لطابور خارج بنغازي' : 'تحول لطابور المندوب');

                return back()->with('success', 'تحول ' . ($delivery->zone === 'out' ? 'لخارج بنغازي' : 'للمندوب'));
            case 'courier':
                $c = !empty($d['courier_id']) ? User::where('type_id', 4)->find($d['courier_id']) : null;
                $n = trim((string) ($d['courier_name'] ?? ''));
                if (!$c && $n === '') {
                    return back()->with('error', 'اختار مندوب ولا اكتب اسمه');
                }
                if ($c) {
                    $delivery->courier_id = $c->id;
                    $delivery->courier_name = null;
                    $delivery->status = 'assigned';
                    $delivery->assigned_at = now();
                    $delivery->log('assigned', 'تعيين للمندوب ' . $c->name);
                } else {
                    $delivery->courier_id = null;
                    $delivery->courier_name = $n;
                    $delivery->status = 'out';
                    $delivery->assigned_at = now();
                    $delivery->log('out', 'مع ' . $n);
                }
                $delivery->save();

                return back()->with('success', '✓ تغيّر المندوب');
            case 'back':
                foreach ($delivery->orders as $o) {
                    if (!$o->delivered) {
                        $o->delivery_id = null;
                        $o->save();
                    }
                }
                $delivery->status = 'returned';
                $delivery->save();
                $delivery->log('returned', 'رجعت من الطلعة للرف');
                Cache::forget('sg.badge.ready');

                return back()->with('success', '↩ رجعت للرف');
            case 'done':
                // يتسلّم عند الكاشير: نفكو الإيصالات من التوصيل ونفتحو صفحة التسليم للزبون (الدفع والتسليم العادي)
                foreach ($delivery->orders as $o) {
                    if (!$o->delivered) {
                        $o->delivery_id = null;
                        $o->save();
                    }
                }
                $delivery->status = 'delivered';
                $delivery->delivered_at = now();
                $delivery->save();
                $delivery->log('delivered', 'سلّم عن طريق ' . ($delivery->courier_name ?: optional($delivery->courier)->name) . ' · الحساب عند الكاشير');
                Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ' طلعة: تم (' . optional($cu)->name . ')', 'log' => ' ' . $delivery->code . ' · ' . $delivery->batch]);
                Cache::forget('sg.badge.ready');

                return redirect()->route('sg.delivery', ['q' => optional($cu)->code, 'cid' => optional($cu)->id])->with('success', '🛵 كمّل التسليم والدفع لـ' . optional($cu)->name);
        }

        return back();
    }

    /** ☑ إجراء على المختارين: ترحيل · تعيين مندوب · رجوع للرف */
    public function bulk(Request $request)
    {
        $d = $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer', 'a' => 'required|in:move_out,move_in,courier,back,silent,settle',
            'courier_id' => 'nullable|integer', 'courier_name' => 'nullable|string|max:100', 'payment_id' => 'nullable|integer']);
        $rows = Delivery::with('orders')->whereIn('id', $d['ids'])->whereIn('status', self::ACTIVE)->get();
        $c = !empty($d['courier_id']) ? User::where('type_id', 4)->find($d['courier_id']) : null;
        $n = trim((string) ($d['courier_name'] ?? ''));
        if ($d['a'] === 'courier' && !$c && $n === '') {
            return back()->with('error', 'اختار مندوب ولا اكتب اسمه');
        }
        foreach ($rows as $dl) {
            if ($d['a'] === 'settle') {
                try {
                    $this->settleOut($dl, (int) ($d['payment_id'] ?? AppSettings::cashPaymentId()), null);
                } catch (\Throwable $e) {
                    report($e);
                    session()->flash('error', $dl->code . ': ' . $e->getMessage());
                }
                continue;
            }
            if ($d['a'] === 'silent') {
                try {
                    $this->deliverSilent($dl);
                } catch (\RuntimeException $e) {
                    session()->flash('error', $dl->code . ': ' . $e->getMessage());
                }
                continue;
            }
            if ($d['a'] === 'move_out' || $d['a'] === 'move_in') {
                $dl->zone = $d['a'] === 'move_out' ? 'out' : 'in';
                $dl->save();
                $dl->log($dl->status, $dl->zone === 'out' ? 'ترحيل لخارج بنغازي' : 'ترحيل لطابور المندوب');
            } elseif ($d['a'] === 'courier') {
                $dl->courier_id = $c ? $c->id : null;
                $dl->courier_name = $c ? null : $n;
                $dl->status = $c ? 'assigned' : 'out';
                $dl->assigned_at = now();
                $dl->save();
                $dl->log($dl->status, 'مع ' . ($c ? $c->name : $n));
            } else {
                foreach ($dl->orders as $o) {
                    if (!$o->delivered) {
                        $o->delivery_id = null;
                        $o->save();
                    }
                }
                $dl->status = 'returned';
                $dl->save();
                $dl->log('returned', 'رجعت للرف');
            }
        }
        Cache::forget('sg.badge.ready');

        return back()->with('success', '✓ ' . $rows->count() . ' ' . ['move_out' => 'ترحلوا لخارج بنغازي', 'move_in' => 'ترحلوا للمندوب', 'courier' => 'مع ' . ($c ? $c->name : $n), 'back' => 'رجعوا للرف', 'silent' => 'مستلمين (بدون رسالة)', 'settle' => 'تحصّلوا (بدون رسالة)'][$d['a']]);
    }

    /** 🧾 إيصال عربون للطباعة (A4) — المندوب يسلمه للزبون */
    public function receipt(Delivery $delivery)
    {
        $delivery->loadMissing('customer', 'courier', 'orders');
        $cart = $delivery->cart_id ? \App\Models\CartQuote::find($delivery->cart_id) : null;
        $order = $delivery->deposit_order_id ? Order::find($delivery->deposit_order_id) : $delivery->orders->first();
        $order = $order ?: ($cart && !empty($cart->order_id) ? Order::find($cart->order_id) : null);

        // نفس بيانات وشكل فاتورة الشركة: المنصة · الكمية · الشحن · الإجمالي · المدفوع · المستحق
        $c = $delivery->customer;
        $dep = round((float) $delivery->amount, 2);
        if ($order) {
            $paid = (float) $order->paid_price + ($delivery->status === 'delivered' ? 0 : $dep);
            $r = ['code' => $order->code, 'date' => optional($order->created_at)->format('Y-m-d'), 'platform' => optional($order->platform)->name ?: '-', 'qty' => $order->qty,
                'ship' => (float) $order->ship_price, 'total' => (float) $order->total_price, 'discount' => (float) $order->discount];
        } else {
            $tot = 0;
            $qty = '-';
            if ($cart) {
                try {
                    $q = $cart->retailer === 'amazon' ? (array) $cart->amazon_pricing : app(\App\Services\SheinQuotationPdf::class)->quotationForPdf($cart);
                    $tot = (float) data_get($q, 'total.amount', 0);
                    $qty = $cart->retailer === 'amazon' ? data_get($q, 'quantity', '-') : ($q['available_quantity'] ?? '-');
                } catch (\Throwable $e) {
                    report($e);
                }
            }
            $paid = (float) optional($cart)->paid_amount + ($delivery->status === 'delivered' ? 0 : $dep);
            $r = ['code' => $cart ? 'SG-' . $cart->id : $delivery->code, 'date' => optional(optional($cart)->created_at ?? $delivery->created_at)->format('Y-m-d'),
                'platform' => $cart ? $cart->retailer_label : '-', 'qty' => $qty, 'ship' => 0.0, 'total' => $tot, 'discount' => 0.0];
        }
        $r += ['name' => optional($c)->name ?: $delivery->recipient_name, 'ccode' => optional($c)->code, 'deposit' => $dep, 'paid' => round($paid, 2)];
        $r['due'] = round(max(0, $r['total'] + $r['ship'] - $r['discount'] - $r['paid']), 2);

        return view('sg.dispatch.receipt', ['r' => $r, 'invoice' => \App\Models\Invoice::first(), 'f' => fn ($n) => TS::fmt($n)]);
    }

    /** 📄 كشف الطلعة PDF: كل عمود بيان والزبائن تحت بعض */
    public function pdf(Request $request)
    {
        $tab = $request->input('tab') === 'out' ? 'out' : 'in';
        $rows = Delivery::with(['customer', 'orders', 'courier'])
            ->when($request->filled('batch'), fn ($q) => $q->where('batch', $request->batch))
            ->when(!$request->filled('batch'), fn ($q) => $q->whereIn('status', self::ACTIVE))
            ->when($request->filled('ids'), fn ($q) => $q->whereIn('id', array_filter(array_map('intval', explode(',', (string) $request->ids)))))
            ->orderBy('id')->get();
        if (!$request->filled('batch')) {
            $rows = $rows->filter(fn ($d) => ($d->zone ?: (static::outside($d->city) ? 'out' : 'in')) === $tab)->values();
        }
        abort_if($rows->isEmpty(), 404);
        $temp = storage_path('app/mpdf-temp');
        if (!is_dir($temp)) {
            mkdir($temp, 0700, true);
        }
        $fontDirs = (new \Mpdf\Config\ConfigVariables())->getDefaults()['fontDir'];
        $fontData = (new \Mpdf\Config\FontVariables())->getDefaults()['fontdata'];
        $pdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4', 'orientation' => 'P',
            'fontDir' => array_merge($fontDirs, [resource_path('fonts/somar')]),
            'fontdata' => array_merge($fontData, ['somar' => ['R' => 'Somar-Regular.ttf', 'B' => 'Somar-Bold.ttf', 'useOTL' => 0xFF, 'useKashida' => 75]]),
            'tempDir' => $temp, 'default_font' => 'somar', 'default_font_size' => 16,
            'margin_top' => 8, 'margin_bottom' => 8, 'margin_left' => 6, 'margin_right' => 6, 'shrink_tables_to_fit' => 1, 'autoScriptToLang' => true, 'autoLangToFont' => false]);
        $pdf->SetDirectionality('rtl');
        $title = 'طلعة ' . ($request->batch ?: now()->format('Y-m-d'));
        $pdf->SetTitle($title);
        $pdf->WriteHTML(view('pdf.dispatch', ['rows' => $rows, 'title' => $title, 'f' => fn ($n) => TS::fmt($n)])->render());

        return response($pdf->Output('', \Mpdf\Output\Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="dispatch-' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($request->batch ?: now()->format('md'))) . '.pdf"',
        ]);
    }

    /**
     * 💰 تحصيل قيمة من شركة التوصيل (خارج بنغازي): بدون رسالة للعميل.
     * المبلغ يدخل الخزينة حسب طريقة الدفع اللي تختارها، والإيصالات تصير مستلمة (العربون: دفعة بس والبضاعة تقعد).
     */
    protected function settleOut(Delivery $dl, int $pid, ?float $amount): float
    {
        $got = 0;
        $was = \App\Services\CustomerNotify::$enabled;
        \App\Services\CustomerNotify::$enabled = false; // 🤫 بدون أي إشعار للعميل
        try {
        DB::transaction(function () use ($dl, $pid, $amount, &$got) {
            $dl->loadMissing('orders');
            $tag = 'تحصيل شركة التوصيل' . ($dl->ext_code ? ' · كود ' . $dl->ext_code : '') . ' (' . $dl->code . ')';
            $isDep = $dl->kind === 'deposit' || $dl->deposit_order_id || $dl->cart_id;
            $orders = $dl->deposit_order_id ? Order::whereKey($dl->deposit_order_id)->get() : $dl->orders->where('delivered', false)->values();
            $left = $amount !== null ? round($amount, 2) : ($isDep ? round((float) $dl->amount, 2) : round($orders->sum(fn ($o) => max(0, $o->remaining())), 2));
            foreach ($orders as $o) {
                if ($left <= 0.009) {
                    break;
                }
                $take = $isDep ? $left : round(min($left, max(0, $o->remaining())), 2);
                if ($take > 0.009) {
                    \App\Services\OrderPaymentService::pay($o, $take, $pid, $tag);
                    $left -= $take;
                    $got += $take;
                }
            }
            if ($left > 0.009 && $dl->cart_id) {
                // حجز سلة بدون إيصال: المبلغ يدخل الخزينة ويتسجل على السلة
                \App\Services\TreasuryService::deposit($left, 'dinar', $pid, 'order', 0, $tag . ' · سلة #' . $dl->cart_id);
                $cart = \App\Models\CartQuote::find($dl->cart_id);
                if ($cart) {
                    $cart->paid_amount = round((float) $cart->paid_amount + $left, 2);
                    $cart->paid_at = now();
                    $cart->save();
                }
                $got += $left;
                $left = 0;
            }
            foreach ($dl->orders as $o) {
                if ($o->delivered) {
                    continue;
                }
                if ($isDep) {
                    if ((int) $o->delivery_id === (int) $dl->id) {
                        $o->delivery_id = null;
                        $o->save();
                    }
                    continue;
                }
                $o = $o->fresh();
                $o->delivered = true;
                $o->delivered_at = now()->toDateString();
                $o->delivered_time = now();
                $o->delivered_by = auth()->id();
                $o->delivery_type = 'توصيل عن طريق مكتب';
                $o->delivery_name = $dl->courier_name ?: optional($dl->courier)->name;
                $o->delivery_id = $dl->id;
                $o->save();
            }
            $dl->collected_cash = round($got, 2);
            $dl->status = 'delivered';
            $dl->delivered_at = now();
            $dl->save();
            $dl->log('delivered', $tag . ' · ' . TS::fmt($got) . ' د.ل');
            Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ' تحصيل خارج بنغازي (' . ($dl->recipient_name ?: optional($dl->customer)->name) . ')', 'log' => ' ' . $tag . ' · ' . TS::fmt($got) . ' د.ل']);
        });
        } finally {
            \App\Services\CustomerNotify::$enabled = $was;
        }
        Cache::forget('sg.badge.ready');

        return $got;
    }

    /**
     * ✅ تسليم بدون رسالة للعميل: الباقي يتسجل مقبوض كاش والإيصالات تصير مستلمة.
     * مندوب بحساب: الكاش يقعد عليه لين إقفال حسابه (زي العادة) · مندوب بالاسم: الكاش يدخل الخزينة طول.
     */
    protected function deliverSilent(Delivery $dl): void
    {
        $was = \App\Services\CustomerNotify::$enabled;
        \App\Services\CustomerNotify::$enabled = false; // 🤫 بدون إشعار للعميل
        try {
            $this->deliverSilentRun($dl);
        } finally {
            \App\Services\CustomerNotify::$enabled = $was;
        }
    }

    protected function deliverSilentRun(Delivery $dl): void
    {
        if ($dl->cart_id) {
            // 🛒 عربون حجز سلة: المندوب قبض ← الحجز يتأكد ويوصل العميل إيصاله (نفس نظام المندوبين)
            \App\Services\CourierService::markDelivered($dl->fresh(['orders', 'courier', 'customer']), $dl->toCollect(), 0, 'قبض من طلبيات دليفري' . ($dl->courier_name ? ' · ' . $dl->courier_name : ''));
            Cache::forget('sg.badge.pcart');

            return;
        }
        DB::transaction(function () use ($dl) {
            $dl->loadMissing('orders', 'courier');
            $cashId = AppSettings::cashPaymentId();
            $who = $dl->courier_name ?: optional($dl->courier)->name;
            $got = 0;
            if ($dl->kind === 'deposit' || $dl->deposit_order_id) {
                // 💵 عربون: نسجلو المبلغ دفعة على الإيصال بس — البضاعة ما تتسلمش وتقعد في الرف
                $o = $dl->deposit_order_id ? Order::find($dl->deposit_order_id) : $dl->orders->first();
                $amt = round((float) $dl->amount, 2);
                if ($o && $amt > 0.009) {
                    if ($dl->courier_id) {
                        $ph = new \App\Models\PaidHistory;
                        $ph->customer_id = $o->customer_id;
                        $ph->order_id = $o->id;
                        $ph->paid_price = $amt;
                        $ph->payment_id = $cashId;
                        $ph->save();
                        $o->paid_price = (float) $o->paid_price + $amt;
                        $o->refreshPaymentStatus();
                        $o->save();
                    } else {
                        \App\Services\OrderPaymentService::pay($o, $amt, $cashId, 'عربون كاش (دليفري ' . $dl->code . ($who ? ' · ' . $who : '') . ')');
                    }
                }
                foreach ($dl->orders as $x) {
                    if (!$x->delivered && (int) $x->delivery_id === (int) $dl->id) {
                        $x->delivery_id = null;
                        $x->save();
                    }
                }
                $dl->collected_cash = round($amt + (float) $dl->fee, 2);
                $dl->status = 'delivered';
                $dl->delivered_at = now();
                $dl->save();
                $dl->log('delivered', 'عربون مقبوض بدون رسالة · ' . TS::fmt($amt) . ' د.ل' . ($who ? ' · ' . $who : ''));
                Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ' دليفري: عربون (' . optional($o)->code . ')', 'log' => ' ' . $dl->code . ' · ' . TS::fmt($amt) . ' د.ل']);

                return;
            }
            foreach ($dl->orders as $o) {
                if ($o->delivered) {
                    continue;
                }
                $need = round(max(0, $o->remaining()), 2);
                if ($this->silentAmount !== null) {
                    $need = round(min($need, max(0, $this->silentAmount - $got)), 2); // المبلغ اللي كتبته (لو أقل يقعد الباقي دين)
                }
                if ($need > 0.009) {
                    if ($dl->courier_id) {
                        $ph = new \App\Models\PaidHistory;
                        $ph->customer_id = $o->customer_id;
                        $ph->order_id = $o->id;
                        $ph->paid_price = $need;
                        $ph->payment_id = $cashId;
                        $ph->save();
                        $o->paid_price = (float) $o->paid_price + $need;
                        $o->refreshPaymentStatus();
                    } else {
                        \App\Services\OrderPaymentService::pay($o, $need, $cashId, 'كاش (دليفري ' . $dl->code . ($who ? ' · ' . $who : '') . ')');
                        $o->refresh();
                    }
                    $got += $need;
                }
                $o->delivered = true;
                $o->delivered_at = now()->toDateString();
                $o->delivered_time = now();
                $o->delivered_by = $dl->courier_id ?: auth()->id();
                $o->delivery_type = 'توصيل عن طريق مندوب';
                $o->delivery_name = $who;
                $o->delivery_id = $dl->id;
                $o->save();
            }
            $this->silentGot = round($got, 2);
            $dl->collected_cash = round($got + (float) $dl->fee, 2);
            $dl->status = 'delivered';
            $dl->delivered_at = now();
            $dl->save();
            $dl->log('delivered', 'تسليم بدون رسالة · قبض ' . TS::fmt($got) . ' د.ل' . ($who ? ' · ' . $who : ''));
            Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ' دليفري: مستلمة (' . ($dl->recipient_name ?: optional($dl->customer)->name) . ')', 'log' => ' ' . $dl->code . ' · ' . $dl->orders->pluck('code')->implode('، ') . ' · ' . TS::fmt($got) . ' د.ل']);
        });
        Cache::forget('sg.badge.ready');
    }

    /**
     * ↩ تراجع عن آخر حركة (غلطة): المستلمة ترجع غير مستلمة والفلوس اللي تسجلت وقتها ترجع · المرجّعة/الملغية ترجع للطابور
     */
    protected function restore(Delivery $dl): string
    {
        $cashId = AppSettings::cashPaymentId();
        $back = 0;
        DB::transaction(function () use ($dl, $cashId, &$back) {
            $dl->loadMissing('orders');
            $since = ($dl->delivered_at ?: $dl->updated_at ?: now())->copy()->subMinutes(10);
            if ($dl->cart_id && $dl->status === 'delivered') {
                // 🛒 حجز سلة اتقبض بالغلط: الدفعة ترجع · الإيصال اللي انعمل منه يتلغى · الحجز يرجع «يستنى تأكيد»
                $cart = \App\Models\CartQuote::find($dl->cart_id);
                $o = $cart && !empty($cart->order_id) ? Order::find($cart->order_id) : null;
                if ($o) {
                    foreach (\App\Models\PaidHistory::where('order_id', $o->id)->where('created_at', '>=', $since)->get() as $p) {
                        $back += (float) $p->paid_price;
                        $this->reversePaid($p, $o);
                    }
                    $o->refresh();
                    if (!$o->delivered && $o->created_at && $o->created_at->gte($since)) {
                        $o->status = 'canceld'; // الإيصال انعمل بالغلط من القبض
                        $o->note = trim(($o->note ? $o->note . ' · ' : '') . 'تلغى: تراجع عن قبض دليفري ' . $dl->code);
                        $o->save();
                    }
                }
                if ($cart) {
                    $cart->paid_amount = max(0, round((float) $cart->paid_amount - max($back, (float) $dl->amount), 2));
                    if ((float) $cart->paid_amount <= 0.009) {
                        $cart->paid_at = null;
                    }
                    if ($o && (int) $cart->order_id === (int) $o->id && $o->status === 'canceld') {
                        $cart->order_id = null;
                    }
                    if (in_array($cart->portal_status, ['confirmed'], true)) {
                        $cart->portal_status = 'ordered';
                    }
                    $cart->save();
                }
            } elseif ($dl->status === 'delivered') {
                $targets = $dl->deposit_order_id ? Order::whereKey($dl->deposit_order_id)->get() : $dl->orders;
                foreach ($targets as $o) {
                    foreach (\App\Models\PaidHistory::where('order_id', $o->id)->where('created_at', '>=', $since)->get() as $p) {
                        $back += (float) $p->paid_price;
                        $this->reversePaid($p, $o);
                    }
                }
                foreach ($dl->orders as $o) {
                    $o->refresh();
                    if ($o->delivered) {
                        $o->delivered = false;
                        $o->delivered_at = null;
                        $o->delivered_time = null;
                        $o->delivered_by = null;
                        $o->delivery_type = null;
                        $o->delivery_name = null;
                    }
                    $o->delivery_id = $dl->id;
                    $o->save();
                }
            } else {
                foreach ($dl->orders as $o) {
                    $o->refresh();
                    if ($o->delivered || ($o->delivery_id && (int) $o->delivery_id !== (int) $dl->id && Delivery::whereKey($o->delivery_id)->whereIn('status', self::ACTIVE)->exists())) {
                        continue; // تسلّمت ولا مع توصيل ثاني
                    }
                    $o->delivery_id = $dl->id;
                    $o->save();
                }
            }
            $dl->status = $dl->courier_id ? 'assigned' : ($dl->courier_name ? 'out' : 'requested');
            $dl->delivered_at = null;
            $dl->collected_cash = 0;
            $dl->collected_bank = 0;
            if (\Illuminate\Support\Facades\Schema::hasColumn('deliveries', 'kept')) {
                $dl->kept = false;
            }
            $dl->save();
            $dl->log($dl->status, '↩ تراجع (رجعت للطابور)' . ($back ? ' · رجعت دفعة ' . TS::fmt($back) . ' د.ل' : ''));
            Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ' تراجع دليفري (' . ($dl->recipient_name ?: optional($dl->customer)->name) . ')', 'log' => ' ' . $dl->code . ' · ' . $dl->orders->pluck('code')->implode('، ') . ($back ? ' · رجعت ' . TS::fmt($back) : '')]);
        });
        Cache::forget('sg.badge.ready');
        Cache::forget('sg.badge.courier');

        return ($dl->recipient_name ?: optional($dl->customer)->name) . ' رجع للطابور' . ($back ? ' · ورجعت الدفعة ' . TS::fmt($back) . ' د.ل' : '');
    }

    /** يرجّع دفعة: لو دخلت الخزينة تطلع منها، ولو كاش مع المندوب (ما دخلش) تنشال من الإيصال بس */
    protected function reversePaid(\App\Models\PaidHistory $p, Order $o): void
    {
        $inTreasury = TreasuryHistory::where('type', 'order')->where('procedure_id', $o->id)->where('status', 'pay')
            ->where('payment_id', $p->payment_id)->whereBetween('created_at', [$p->created_at->copy()->subMinutes(2), $p->created_at->copy()->addMinutes(2)])
            ->whereRaw('ABS(price - ?) < 0.01', [(float) $p->paid_price])->exists();
        if ($inTreasury) {
            \App\Services\OrderPaymentService::edit($p, 0, (int) $p->payment_id);

            return;
        }
        $o->refresh();
        $o->paid_price = max(0, round((float) $o->paid_price - (float) $p->paid_price, 2));
        $o->refreshPaymentStatus();
        $o->save();
        $p->delete();
    }

    /** إنشاء توصيل لإيصالات زبون (مستقل عن خدمة المندوبين) */
    protected function make(Customer $cu, $orders, float $fee, ?string $city, ?User $courier): Delivery
    {
        $due = $orders->sum(fn ($o) => max(0, $o->remaining()));
        $dl = Delivery::create([
            'code' => Delivery::newCode(), 'customer_id' => $cu->id, 'courier_id' => optional($courier)->id, 'source' => 'admin',
            'status' => $courier ? 'assigned' : 'requested', 'recipient_name' => $cu->name, 'recipient_phone' => $cu->phone_1,
            'city' => $city, 'address' => $cu->address, 'location_url' => $cu->location_url ?? null,
            'package_type' => $orders->pluck('package_type')->filter()->unique()->implode('، '),
            'parcels' => max(1, $orders->sum(fn ($o) => $o->bags_num ?: 1)), 'amount' => round($due, 2), 'fee' => $fee,
            'pay_type' => $due <= 0.009 ? 'paid' : 'cash', 'assigned_at' => $courier ? now() : null, 'created_id' => auth()->id(),
        ]);
        foreach ($orders as $o) {
            $dl->orders()->attach($o->id, ['due' => max(0, $o->remaining())]);
            $o->delivery_id = $dl->id;
            $o->save();
        }
        $dl->log($dl->status, $courier ? 'تعيين للمندوب ' . $courier->name : 'طلبيات دليفري');
        Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ' دليفري (' . $cu->name . ')', 'log' => ' ' . $dl->code . ' · ' . $orders->pluck('code')->implode('، ') . ($courier ? ' مع ' . $courier->name : '')]);

        return $dl;
    }

    /** 🚚 خارج بنغازي؟ (المدينة مكتوبة ومش بنغازي) */
    public static function outside(?string $city): bool
    {
        $c = trim((string) $city);
        if ($c === '') {
            return false;
        }
        $n = str_replace(['ى', 'إ', 'أ', 'آ'], ['ي', 'ا', 'ا', 'ا'], mb_strtolower($c));

        return !str_contains($n, 'بنغازي') && !str_contains($n, 'benghazi') && !str_contains($n, 'banghazi');
    }

    /** 💵 عربون: نبعتو للزبون الرابط والتفاصيل */
    protected function depositMsg(Delivery $dl): void
    {
        $c = $dl->customer;
        if (!$c || !$c->phone_1) {
            return;
        }
        $who = $dl->courier_name ?: optional($dl->courier)->name;
        $codes = $dl->orders->pluck('code')->implode('، ');
        $phone = $dl->courier ? ($dl->courier->phone ?? null) : null;
        $url = rtrim((string) config('app.url'), '/');
        $text = "{$c->name} 👋\n🛵 المندوب" . ($who ? " *{$who}*" : '') . " جاي يقبض *العربون*\n"
            . "🔢 الإيصال: *{$codes}*\n💰 المبلغ: *" . TS::fmt($dl->amount) . " د.ل*"
            . ($phone ? "\n📞 المندوب: {$phone}" : '')
            . "\n\nتفاصيل طلبك من حسابك 👇\n{$url}";
        try {
            MessageService::sendText('deposit_link', $text, [$c->phone_1], $c);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
