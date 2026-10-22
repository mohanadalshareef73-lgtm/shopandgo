<?php

namespace App\Http\Controllers\Sg;

use App\Http\Controllers\Controller;
use App\Models\CartQuote;
use App\Models\Log;
use App\Services\SheinQuotationPdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Services\TreasuryService as TS;

/** طلبات السلات اللي أكدها العملاء من حسابهم */
class PortalCartsController extends Controller
{
    /** null = عميل عادي (تذكير بحسابه) · true = حساب جديد انفتح توا · false = لقيناه برقمه */
    protected ?bool $accNew = null;

    /** السلات اللي تطلع للموظفين: سلات الحسابات + حجوزات من غير حساب (اسم ورقم من رابط السلة) + طلبات مندوب من غير حساب */
    protected function cartScope(): \Closure
    {
        $hasG = \Illuminate\Support\Facades\Schema::hasColumn('cart_quotes', 'guest_phone');

        // أي حجز «ينتظر التأكيد» يطلع (نفس الرقم اللي في القائمة) — حتى لو بدون حساب عميل
        return fn ($w) => $w->whereNotNull('customer_id')
            ->orWhere('portal_status', 'ordered')
            ->when($hasG, fn ($x) => $x->orWhere(fn ($g) => $g->whereNotNull('guest_phone')->whereIn('portal_status', ['ordered', 'confirmed', 'rejected'])));
    }

    public function __construct()
    {
        $this->middleware('permission:cart-list', ['only' => ['index', 'receipt', 'booking', 'download']]);
        // الموظف العادي (يشوف السلات) يقدر يأكد ويحجز ويبعت للرقم زادة
        $this->middleware('permission:cart-edit|cart-list', ['only' => ['decide', 'office', 'bookNow', 'orderToGroup', 'confirmBook', 'toDelivery', 'note']]);
        $this->middleware('permission:cart-edit', ['only' => ['resend']]);
    }

    public function index(Request $request)
    {
        $tab = $request->input('tab', 'ordered');
        $t = trim((string) $request->q);
        // + طلبات المندوب من عملاء غير مسجلين (بدون حساب)
        // 🧮 «حسبها وما أكدهاش»: حتى سلات الواتساب (آخر 7 أيام) — تنسخ رابطها وتبعتها لرقم أو تأكدها
        if ($tab === 'draft' || $t !== '') {
            // 🔍 البحث (من أي تبويب): كل السلات اللي انحسبت آخر 30 يوم — من الموقع أو الواتساب (الرقمين)
            // رقم السلة · كود/اسم/رقم العميل · رقم الواتساب (حتى لو الواتساب مخبيه) · اسم الزائر
            // جزء من الرقم (حتى آخر 3–4 أرقام): نشيلو 0 و218 من البداية
            $dig = preg_replace('/^(00218|218|0)/', '', preg_replace('/\D/', '', $t));
            $tail = strlen($dig) >= 3 ? substr($dig, -9) : '';
            $base = $t !== '' && $tab !== 'draft' ? CartQuote::where('status', 'succeeded')->where('created_at', '>=', now()->subDays(30)) : $this->draftQuery();
            $rows = $base->with(['customer', 'incomingMessage'])
                ->when($t !== '', fn ($q) => $q->where(fn ($w) => $w->where('id', ctype_digit($t) ? (int) $t : 0)
                    ->orWhereHas('customer', fn ($c) => \App\Support\Search::customer($c, $t))
                    ->when($tail !== '', fn ($x) => $x->orWhere('whatsapp_customer_phone', 'like', '%' . $tail . '%'))
                    ->orWhereHas('incomingMessage', fn ($m) => $m->where('name', 'like', '%' . $t . '%')->when($tail !== '', fn ($y) => $y->orWhere('from', 'like', '%' . $tail . '%')->orWhere('contact_phone', 'like', '%' . $tail . '%')->orWhere('phone', 'like', '%' . $tail . '%')))
                    ->when(\Illuminate\Support\Facades\Schema::hasColumn('cart_quotes', 'guest_phone'), fn ($x) => $x->orWhere('guest_phone', 'like', '%' . ($tail ?: $t) . '%')->orWhere('guest_name', 'like', '%' . $t . '%'))))
                ->latest('id')->paginate(30)->withQueryString();
        } else
        $rows = CartQuote::with('customer')->where($this->cartScope())
            // بحث بكود العميل أو اسمه أو رقمه أو رقم السلة: يطلع كل سلاته حتى اللي حسبها وما أكدهاش
            ->when($t !== '', fn ($q) => $q->where(fn ($w) => $w->where('id', ctype_digit($t) ? (int) $t : 0)->orWhereHas('customer', fn ($c) => \App\Support\Search::customer($c, $t))->when(\Illuminate\Support\Facades\Schema::hasColumn('cart_quotes', 'guest_phone'), fn ($x) => $x->orWhere('guest_phone', 'like', '%' . $t . '%')->orWhere('guest_name', 'like', '%' . $t . '%')))->where('status', 'succeeded'),
                fn ($q) => $q->when($tab === 'all', fn ($x) => $x->whereIn('portal_status', ['ordered', 'confirmed', 'rejected']), fn ($x) => $x->where('portal_status', $tab)->when($tab === 'ordered' && !request()->filled('pay'), fn ($y) => $y->where(fn ($w) => $w->whereNotIn('id', \App\Models\Delivery::whereNotNull('cart_id')->whereIn('status', ['requested', 'assigned', 'out', 'problem'])->pluck('cart_id'))))))
            ->when($request->filled('pay'), fn ($q) => $q->where('pay_method', $request->pay))
            ->orderByRaw('ordered_at IS NULL')->latest('ordered_at')->latest('id')->paginate(30)->withQueryString();

        return view('sg.carts.portal', ['nav' => 'pcart', 'rows' => $rows, 'tab' => $t !== '' && $tab !== 'draft' ? 'search' : $tab, 'payments' => \App\Models\PaymentMethod::where('status', true)->get(),
            'counts' => CartQuote::where($this->cartScope())->where(fn ($w) => $w->where('portal_status', '!=', 'ordered')->orWhereNotIn('id', \App\Models\Delivery::whereNotNull('cart_id')->whereIn('status', ['requested', 'assigned', 'out', 'problem'])->pluck('cart_id')))->selectRaw('portal_status, COUNT(*) n')->groupBy('portal_status')->pluck('n', 'portal_status')->put('draft', $this->draftQuery()->count())]);
    }

    /** سلات انحسبت وما تأكدتش (من الحساب أو من الواتساب) — آخر 7 أيام، مش متجاهلة ومش متحولة لإيصال */
    protected function draftQuery()
    {
        return CartQuote::where('status', 'succeeded')->where('created_at', '>=', now()->subDays(7))
            ->where(fn ($w) => $w->whereNull('portal_status')->orWhere('portal_status', 'draft'))
            ->when(\Illuminate\Support\Facades\Schema::hasColumn('cart_quotes', 'order_id'), fn ($q) => $q->whereNull('order_id'));
    }

    /** 📝 ملاحظة للعميل على الواتساب (بخصوص حجزه) */
    public function note(Request $request, CartQuote $cart)
    {
        $d = $request->validate(['text' => 'required|string|max:1000']);
        $c = $cart->customer;
        $ph = optional($c)->phone_1 ?: ($cart->guest_phone ?? null);
        if (!$ph) {
            $ph = preg_replace('/^218/', '0', preg_replace('/\D/', '', (string) $cart->whatsapp_customer_phone));
            if (($ph === '' || strlen($ph) > 13) && ($im = $cart->incomingMessage)) {
                $ph = preg_replace('/^218/', '0', preg_replace('/\D/', '', (string) ($im->contact_phone ?: $im->phone)));
            }
            $ph = strlen((string) $ph) > 13 ? '' : $ph;
        }
        if (!$ph) {
            return back()->with('error', 'رقم العميل مش واضح — ما نقدروش نبعتوله');
        }
        $name = optional($c)->name ?: ($cart->guest_name ?? '');
        $text = trim(($name ? $name . ' 👋' . "\n" : '') . '📝 بخصوص طلبك (سلة ' . $cart->retailer_label . ' #' . $cart->id . "):\n\n" . trim($d['text']));
        $ok = \App\Services\MessageService::sendText('cart_note', $text, [$ph], $c);
        \App\Models\Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ' ملاحظة لحجز #' . $cart->id, 'log' => ' ' . $ph . ' · ' . mb_substr($d['text'], 0, 200)]);

        return back()->with($ok ? 'success' : 'error', $ok ? '📝 انبعتت الملاحظة للعميل' : 'الرسالة ما وصلتش (الواتساب)');
    }

    /** 🛵 ارسال دليفري: الحجز (كاش مندوب) يمشي لصفحة «طلبيات دليفري» ببياناته — المندوب يقبض العربون */
    public function toDelivery(CartQuote $cart)
    {
        $active = ['requested', 'assigned', 'out', 'problem'];
        $dl = \App\Models\Delivery::where('cart_id', $cart->id)->whereIn('status', $active)->latest('id')->first();
        if (!$dl) {
            $amt = (float) ($cart->courier_pay ?? 0);
            if ($amt <= 0.009) {
                return back()->with('error', 'الحجز هذا ما عليهش عربون يتقبض — أكده طول');
            }
            if ($cart->customer_id) {
                $dl = \App\Services\CourierService::cartCollect($cart, $amt);
            } else {
                // 👤 بدون حساب: ناخذو بياناته من الحجز (الاسم · الرقم · العنوان) أو من رقم الواتساب
                $ph = $cart->guest_phone ?? null;
                if (!$ph) {
                    $ph = preg_replace('/^218/', '0', preg_replace('/\D/', '', (string) $cart->whatsapp_customer_phone));
                    if (($ph === '' || strlen($ph) > 13) && ($im = $cart->incomingMessage)) {
                        $ph = preg_replace('/^218/', '0', preg_replace('/\D/', '', (string) ($im->contact_phone ?: $im->phone)));
                    }
                    $ph = strlen((string) $ph) > 13 ? '' : $ph;
                }
                $dl = \App\Services\CourierService::cartCollectGuest($cart, $amt, [
                    'name' => $cart->guest_name ?? (optional($cart->incomingMessage)->name ?: 'عميل'), 'phone' => $ph,
                    'address' => $cart->guest_address ?? null, 'location_url' => $cart->guest_location ?? null,
                ]);
            }
            if (!$dl) {
                // 🛟 آخر حل: نعملو التوصيل مباشرة بالبيانات اللي عندنا (حتى لو ناقصة — تكملها من طلبيات دليفري)
                $cu = $cart->customer;
                $dl = \App\Models\Delivery::create([
                    'code' => \App\Models\Delivery::newCode(), 'customer_id' => $cart->customer_id, 'source' => 'customer', 'status' => 'requested',
                    'recipient_name' => optional($cu)->name ?: ($cart->guest_name ?? (optional($cart->incomingMessage)->name ?: 'عميل')),
                    'recipient_phone' => optional($cu)->phone_1 ?: ($cart->guest_phone ?? null), 'city' => optional($cu)->city ?? null, 'address' => ($cu ? \App\Services\CourierService::withArea($cu->area ?? null, $cu->address) : '') ?: ($cart->guest_address ?? null),
                    'package_type' => 'تحصيل حجز', 'parcels' => 0, 'amount' => round($amt, 2), 'fee' => (float) \App\Services\AppSettings::get('courier_default_fee', 10),
                    'fee_payer' => 'customer', 'pay_type' => 'cash', 'cart_id' => $cart->id,
                    'note' => '💵 تحصيل حجز سلة ' . $cart->retailer_label . ' #' . $cart->id . ' — ما فيش طرد، اقبض المبلغ بس',
                ]);
                $dl->log('requested', 'تحصيل حجز سلة #' . $cart->id);
            }
        }
        $dl->zone = \App\Http\Controllers\Sg\DispatchController::outside($dl->city, $dl->address) ? 'out' : 'in';
        $dl->kind = 'deposit';
        $dl->note = trim(($dl->note ? $dl->note . ' · ' : '') . 'من تأكيد الحجوزات');
        $dl->save();
        $dl->log($dl->status, 'ارسال دليفري من تأكيد الحجوزات · سلة #' . $cart->id);
        \Illuminate\Support\Facades\Cache::forget('sg.badge.pcart');

        return back()->with('success', '🛵 الحجز #' . $cart->id . ' مشى لطلبيات دليفري');
    }

    public function decide(Request $request, CartQuote $cart, SheinQuotationPdf $pdf)
    {
        $data = $request->validate(['do' => 'required|in:confirm,reject,ignore', 'send_whatsapp' => 'nullable|boolean', 'to' => 'nullable|array', 'to.*' => 'string|max:60']);
        if ($data['do'] === 'ignore') {
            $cart->portal_status = 'ignored'; // ✕ تجاهل/🗑 حذف: تختفي من كل القوائم (السلة نفسها تقعد في السجل)
            $cart->save();
            // 🗑 توصيل تحصيل العربون المفتوح (لو فيه) يتمسح باش ما يطلعش في طلبيات دليفري
            foreach (\App\Models\Delivery::where('cart_id', $cart->id)->whereIn('status', ['requested', 'assigned', 'out', 'problem'])->get() as $dl) {
                \Illuminate\Support\Facades\DB::table('delivery_events')->where('delivery_id', $dl->id)->delete();
                $dl->delete();
            }
            \Illuminate\Support\Facades\Cache::forget('sg.badge.pcart');
            \Illuminate\Support\Facades\Cache::forget('sg.badge.courier');
            if ($request->boolean('del')) {
                Log::create(['user_id' => auth()->id(), 'type' => 'cart', 'title' => ' حذف حجز #' . $cart->id, 'log' => ' ' . (optional($cart->customer)->name ?: ($cart->guest_name ?? ''))]);

                return back()->with('success', '🗑 انحذف الحجز #' . $cart->id);
            }

            return back()->with('success', 'تخفّت السلة #' . $cart->id . ' من القائمة');
        }
        // 👤 حجز من غير حساب: وقت التأكيد نلقوه برقمه، ولو ما عندهش نفتحوله حساب أوتوماتيك
        $newAcc = null;
        if (!$cart->customer_id && $data['do'] === 'confirm') {
            $gd = \App\Models\Delivery::where('cart_id', $cart->id)->latest('id')->first();
            $waPh = preg_replace('/^218/', '0', preg_replace('/\D/', '', (string) $cart->whatsapp_customer_phone)); // سلة من الواتساب: رقمه متاع الواتساب
            $waPh = (str_contains((string) $cart->whatsapp_customer_phone, '@') || strlen($waPh) > 13) ? '' : $waPh; // معرّف جهاز مش رقم
            if ($waPh === '' && ($im = $cart->incomingMessage)) { // الواتساب مخبي الرقم: ناخذوه من رقم جهة الاتصال
                $waPh = preg_replace('/^218/', '0', preg_replace('/\D/', '', (string) ($im->contact_phone ?: $im->phone)));
                $waPh = strlen($waPh) > 13 ? '' : $waPh;
            }
            $waNm = optional($cart->incomingMessage)->name;
            [$gc, $isNew] = \App\Support\GuestAccount::resolve($cart->guest_name ?? optional($gd)->recipient_name ?? $waNm, $cart->guest_phone ?? optional($gd)->recipient_phone ?? ($waPh ?: null), $cart->guest_address ?? optional($gd)->address);
            if (!$gc) {
                return back()->with('error', 'رقم العميل مش واضح — ما قدرناش نفتحوله حساب. اعمله إيصال يدوي');
            }
            $cart->customer_id = $gc->id;
            $cart->save();
            $cart->setRelation('customer', $gc);
            if ($gd && !$gd->customer_id) {
                $gd->customer_id = $gc->id;
                $gd->save();
            }
            $newAcc = $isNew;
        }
        $this->accNew = $newAcc;
        $cart->portal_status = $data['do'] === 'confirm' ? 'confirmed' : 'rejected';
        $cart->confirmed_at = $data['do'] === 'confirm' ? now() : null;
        $cart->save();
        Log::create(['user_id' => auth()->id(), 'type' => 'cart', 'title' => ' حجز سلة #' . $cart->id, 'log' => $data['do'] === 'confirm' ? ' تأكيد الحجز' : ' رفض الحجز']);
        $c = $cart->customer;
        $sentB = 0;
        // تحويل: وقت الموافقة ينعمل الإيصال في الطلبات، والدفعة تدخل الخزينة كتحويل باسم الموظف اللي وافق، ويجي العميل إيصال
        $receiptSent = false;
        if ($data['do'] === 'confirm' && $cart->pay_method === 'transfer' && empty($cart->order_id) && $c) {
            try {
                $receiptSent = $this->approveTransfer($cart);
            } catch (\Throwable $e) {
                report($e);

                return back()->with('error', 'ما قدرناش نعملو الإيصال: ' . mb_substr($e->getMessage(), 0, 150));
            }
        }
        // باقي طرق الدفع (كاش مع المندوب / بدون): الإيصال ينعمل توا ويوصل العميل تأكيد ورابط الإيصال
        if ($data['do'] === 'confirm' && empty($cart->order_id) && $c && $cart->pay_method !== 'transfer') {
            try {
                $order = \App\Services\CourierService::orderFromCart($cart, 0, null);
                if ($order) {
                    $cart->order_id = $order->id;
                    $cart->ordered_at = $cart->ordered_at ?: now();
                    $cart->save();
                    $receiptSent = $this->sendConfirmMsg($cart, $order);
                }
            } catch (\Throwable $e) {
                report($e);

                return back()->with('error', 'ما قدرناش نعملو الإيصال: ' . mb_substr($e->getMessage(), 0, 150));
            }
        }
        if (!$c && $data['do'] === 'reject' && !empty($cart->guest_phone) && $request->boolean('send_whatsapp')) {
            \App\Services\MessageService::sendText('customer_note', 'اهلا ' . ($cart->guest_name ?: '') . "، للأسف ما قدرناش نأكدو حجز سلتك #{$cart->id}. تواصل معانا للتفاصيل.", [$cart->guest_phone]);
        }
        if ($c && !$receiptSent && $request->boolean('send_whatsapp')) {
            $total = $this->total($cart, $pdf);
            $txt = $data['do'] === 'confirm'
                ? "اهلا {$c->name} 👋\n✓ تم تأكيد حجز سلتك #{$cart->id}" . ($total ? " بقيمة {$total} د.ل" : '') . "\nبنبدو في حجز القطع، ونبلغوك أول بأول.\nكودك: {$c->code}"
                : "اهلا {$c->name}، للأسف ما قدرناش نأكدو حجز سلتك #{$cart->id}. تواصل معانا للتفاصيل.";
            \App\Services\MessageService::sendText('customer_note', $txt, [$c->phone_1], $c);
        }

        return back()->with('success', ($data['do'] === 'confirm' ? 'تأكد الحجز ✓' : 'انرفض الحجز') . ($newAcc === true ? ' · 🎉 انفتحله حساب جديد (كود ' . optional($cart->customer)->code . ')' : ($newAcc === false ? ' · تربط بحسابه (كود ' . optional($cart->customer)->code . ')' : '')) . ($cart->order_id ? ' · إيصال ' . optional(\App\Models\Order::find($cart->order_id))->code : '') . ($receiptSent ? ' · وصل العميل الإيصال' : '') . ' · اضغط ⚡ احجز باش تبعتها للحجز');
    }

    /** رسالة تأكيد الحجز للعميل + رابط الإيصال */
    protected function sendConfirmMsg(CartQuote $cart, \App\Models\Order $order): bool
    {
        $c = $cart->customer;
        if (!$c) {
            return false;
        }
        $order->refresh();
        $msg = "✅ *تم تسجيل طلبك في المنظومة* — Shop & Go\n\n👤 {$c->name} · كود *{$c->code}* · 📱 {$c->phone_1}\n🛒 سلة {$cart->retailer_label} #{$cart->id}\n"
            . "🔢 رقم الإيصال: *{$order->code}*\n🛍 قيمة الطلب: " . TS::fmt($order->total_price) . " د.ل\n🚚 _الشحن غير محسوب من ضمن هذه التكلفة_\n✅ المدفوع: " . TS::fmt($order->paid_price) . " د.ل\n⏳ الباقي: " . TS::fmt(max(0, $order->remaining())) . " د.ل\n"
            . '📅 ' . now()->format('Y-m-d g:i a') . "\n\nبدينا في حجز القطع، ونبلغوك أول بأول 💙\n\n🧾 إيصالك 👇\n" . route('order.invoice', $order->code)
            . "\n\n" . \App\Support\GuestAccount::accountText($c, (bool) $this->accNew);
        try {
            return (bool) \App\Services\MessageService::sendText('cart_paid', $msg, array_filter([$c->phone_1]), $c, $order);
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    /** موافقة على تحويل: إيصال + الدفعة في خزينة التحويل باسم الموظف + إيصال للعميل */
    protected function approveTransfer(CartQuote $cart): bool
    {
        $c = $cart->customer;
        $receiptSent = false;
        DB::transaction(function () use ($cart, $c, &$receiptSent) {
            $pay = (float) ($cart->courier_pay ?? 0);
            $order = \App\Services\CourierService::orderFromCart($cart, 0, null);
            if (!$order) {
                return;
            }
            $pay = $pay > 0 ? min($pay, (float) $order->total_price) : 0;
            $pid = (int) (\App\Services\AppSettings::get('transfer_payment_id') ?: \App\Models\PaymentMethod::where('id', '!=', \App\Services\AppSettings::cashPaymentId())->where('status', true)->value('id'));
            if ($pay > 0 && $pid) {
                \App\Services\OrderPaymentService::pay($order, $pay, $pid, 'تحويل مصرفي · حجز سلة #' . $cart->id . ' · وافق: ' . auth()->user()->name);
            }
            $cart->order_id = $order->id;
            $cart->paid_amount = $pay;
            $cart->paid_at = now();
            $cart->save();
            $order->refresh();
            $msg = "✅ *تم تسجيل طلبك في المنظومة* — Shop & Go\n\n👤 {$c->name} · كود *{$c->code}* · 📱 {$c->phone_1}\n🔢 رقم الإيصال: *{$order->code}*\n🛒 سلة {$cart->retailer_label} #{$cart->id}\n"
                . '🛍 قيمة الطلب: ' . TS::fmt($order->total_price) . " د.ل\n🚚 _الشحن غير محسوب من ضمن هذه التكلفة_\n"
                . '✅ دفعت (تحويل): ' . TS::fmt($pay) . " د.ل\n"
                . '⏳ الباقي وقت الاستلام: ' . TS::fmt(max(0, $order->total_price - $pay)) . " د.ل\n"
                . '📅 ' . now()->format('Y-m-d g:i a') . "\n\n🛍 بدينا في حجز طلبك، ونبلغوك أول بأول 💙\n\n🧾 فاتورتك 👇\n" . route('order.invoice', $order->code)
                . "\n\n" . \App\Support\GuestAccount::accountText($c, (bool) $this->accNew);
            $receiptSent = \App\Services\MessageService::sendText('cart_paid', $msg, array_filter([$c->phone_1]), $c, $order);
        });

        return $receiptSent;
    }

    protected function total(CartQuote $cart, SheinQuotationPdf $pdf)
    {
        try {
            return $cart->retailer === 'amazon' ? data_get($cart->amazon_pricing, 'total.amount') : data_get($pdf->quotationForPdf($cart), 'total.amount');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** يبعت للرقمين: PDF الكميات + رابط السلة + كود العميل */
    /** قائمة القطع بالكميات (القطع المكررة يبان عددها) */
    protected function itemsText(CartQuote $cart, SheinQuotationPdf $pdf): string
    {
        try {
            if ($cart->retailer === 'amazon') {
                return '🧾 الكمية: ' . (int) (data_get($cart->amazon_pricing, 'quantity') ?: 1);
            }
            $q = $pdf->quotationForPdf($cart);
            $lines = [];
            $sum = 0;
            foreach ((array) ($q['items'] ?? []) as $it) {
                $n = (int) ($it['qty'] ?? 0);
                if ($n < 1 || ($it['availability'] ?? 'available') !== 'available') {
                    continue;
                }
                $sum += $n;
                $lines[] = (count($lines) + 1) . ') ' . mb_substr((string) ($it['name'] ?? 'منتج'), 0, 60) . (!empty($it['variant']) ? ' · ' . $it['variant'] : '') . ' ← *×' . $n . '*' . ($n > 1 ? ' 🔁' : '');
            }

            return $lines ? "🧾 القطع ({$sum} قطعة):\n" . implode("\n", $lines) : '';
        } catch (\Throwable $e) {
            report($e);

            return '';
        }
    }

    protected function sendToBooking(CartQuote $cart, SheinQuotationPdf $pdf, array $pick, bool $withItems = false, bool $withPdf = true): int
    {
        // نبعتو بس للأرقام المسجلة اللي اخترتها
        $phones = array_values(array_intersect((array) \App\Services\AppSettings::get('booking_phones', ['0920703777', '0920403444']), $pick));
        try {
            $cart = $pdf->prepare($cart);
        } catch (\Throwable $e) {
            report($e);
        }
        $file = $cart->pdf_path && Storage::disk('local')->exists($cart->pdf_path) ? Storage::disk('local')->path($cart->pdf_path) : null;
        $wa = app(\App\Services\WhatsAppService::class);
        $n = 0;
        foreach ($phones as $ph) {
            // قروب واتساب (ID ينتهي بـ @g.us) يتبعت زي ما هو، والرقم العادي نظبطوه
            $to = str_contains((string) $ph, '@') ? trim((string) $ph) : \App\Services\MessageService::normalize((string) $ph);
            // رسالة: كود العميل بخط عريض وتحته الرابط ← بعدها ملف PDF
            $ok = false;
            try {
                $txt = '*' . optional($cart->customer)->code . "*\n" . $cart->source_url;
                if ($withItems && ($it = $this->itemsText($cart, $pdf)) !== '') {
                    $txt .= "\n\n" . $it;
                }
                if (str_contains($to, '@g.us')) {
                    $ok = $wa->sendToGroup($to, $txt);
                    if ($ok && $file && $withPdf) {
                        sleep(1);
                        $wa->sendToGroup($to, "🛒 حجز {$cart->retailer_label} #{$cart->id}", $file, 'cart-' . $cart->id . '.pdf');
                    }
                    $n += $ok ? 1 : 0;
                    continue;
                }
                $ok = $wa->sendMessage($to, $txt);
                if ($file && $withPdf) {
                    sleep(1);
                    $ok = $wa->sendDocument($to, $file, 'cart-' . $cart->id . '.pdf', "🛒 حجز {$cart->retailer_label} #{$cart->id}") || $ok;
                }
            } catch (\Throwable $e) {
                report($e);
            }
            $n += $ok ? 1 : 0;
        }

        return $n;
    }

    public function download(CartQuote $cart, SheinQuotationPdf $pdf)
    {
        $cart = $pdf->prepare($cart);
        abort_unless($cart->pdf_path && Storage::disk('local')->exists($cart->pdf_path), 404);

        return response()->download(Storage::disk('local')->path($cart->pdf_path), 'cart-' . $cart->id . '-' . optional($cart->customer)->code . '.pdf');
    }

    public function resend(Request $request, CartQuote $cart, SheinQuotationPdf $pdf)
    {
        $n = $this->sendToBooking($cart, $pdf, (array) $request->input('to', []), $request->boolean('with_items'), $request->boolean('with_pdf', true));

        return back()->with($n ? 'success' : 'warning', $n ? "انبعت لـ {$n} رقم حجز" : 'ما وصلش — تأكد من خدمة الواتساب');
    }

    public function receipt(CartQuote $cart)
    {
        abort_unless($cart->receipt_path && Storage::disk('local')->exists($cart->receipt_path), 404);

        return response()->file(Storage::disk('local')->path($cart->receipt_path));
    }

    /** قائمة الحجز: كل قطعة مكررة على حسب كميتها، باش تحجزها وحدة وحدة */
    /** التكرار: يفتح ملف الـ PDF (القطع المكررة ملوّنة في خانة الكمية) */
    public function booking(CartQuote $cart, SheinQuotationPdf $pdf)
    {
        abort_unless($cart->status === 'succeeded', 409);
        $cart = $pdf->prepare($cart);
        abort_unless($cart->pdf_path && Storage::disk('local')->exists($cart->pdf_path), 404);

        return response()->file(Storage::disk('local')->path($cart->pdf_path), ['Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="cart-' . $cart->id . '-' . optional($cart->customer)->code . '.pdf"']);
    }

    /** العميل جا للمكتب: نحجزوله سلته اللي حسبها، ندفع في المكتب، وينعمل الإيصال */
    public function office(Request $request, CartQuote $cart, SheinQuotationPdf $pdf)
    {
        abort_unless($cart->customer_id && $cart->status === 'succeeded', 404);
        $data = $request->validate(['paid' => 'required|numeric|min:0', 'payment_id' => 'required|exists:payment_methods,id', 'to' => 'nullable|array', 'to.*' => 'string|max:60'],
            ['paid.required' => 'اكتب كم دفع']);
        if (!empty($cart->order_id)) {
            return back()->with('warning', 'السلة هذي محجوزة وعندها إيصال من قبل');
        }
        $c = $cart->customer;
        $order = null;
        try {
            DB::transaction(function () use ($cart, $data, &$order) {
                $order = \App\Services\CourierService::orderFromCart($cart, 0, null);
                if (!$order) {
                    throw new \RuntimeException('ما قدرناش نحسبو قيمة السلة');
                }
                [, $pct] = \App\Http\Controllers\Portal\CartsController::deposit();
                $min = !empty(optional($cart->customer)->trusted) ? 0 : ceil($order->total_price * $pct / 100); // الموثوق بدون عربون
                $paid = min((float) $data['paid'], (float) $order->total_price);
                if ($paid + 0.009 < $min) {
                    throw new \RuntimeException('المبلغ شوية — أقل شي ' . number_format($min) . ' د.ل (' . $pct . '%)');
                }
                if ($paid > 0) {
                    \App\Services\OrderPaymentService::pay($order, $paid, (int) $data['payment_id'], 'حجز في المكتب · سلة #' . $cart->id);
                }
                $cart->portal_status = 'confirmed';
                $cart->pay_method = 'office';
                $cart->ordered_at = $cart->ordered_at ?: now();
                $cart->confirmed_at = now();
                $cart->courier_pay = $paid;
                $cart->paid_amount = $paid;
                $cart->paid_at = now();
                $cart->order_id = $order->id;
                $cart->save();
                Log::create(['user_id' => auth()->id(), 'type' => 'cart', 'title' => ' حجز في المكتب سلة #' . $cart->id, 'log' => ' إيصال ' . $order->code . ' · دفع ' . $paid]);
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        $order->refresh();
        $msg = "✅ *تم تسجيل طلبك في المنظومة* — Shop & Go\n\n👤 {$c->name} · كود *{$c->code}* · 📱 {$c->phone_1}\n🔢 رقم الإيصال: *{$order->code}*\n🛒 سلة {$cart->retailer_label} #{$cart->id}\n"
            . '🛍 قيمة الطلب: ' . TS::fmt($order->total_price) . " د.ل\n🚚 _الشحن غير محسوب من ضمن هذه التكلفة_\n✅ دفعت: " . TS::fmt($order->paid_price) . " د.ل\n⏳ الباقي وقت الاستلام: " . TS::fmt(max(0, $order->total_price - $order->paid_price)) . " د.ل\n📅 " . now()->format('Y-m-d g:i a')
            . "\n\n🛍 بدينا في حجز طلبك، ونبلغوك أول بأول 💙\n\n🧾 فاتورتك 👇\n" . route('order.invoice', $order->code);
        $sent = false;
        try {
            $sent = \App\Services\MessageService::sendText('cart_paid', $msg, array_filter([$c->phone_1]), $c, $order);
        } catch (\Throwable $e) {
            report($e);
        }
        $nB = !empty($data['to']) ? $this->sendToBooking($cart->fresh(), $pdf, $data['to'], $request->boolean('with_items', true), $request->boolean('with_pdf')) : 0;

        return back()->with('success', '✓ اتحجزت · إيصال ' . $order->code . ($sent ? ' · وصل العميل الإيصال' : '') . ($nB ? " · انبعت لـ {$nB} حجز" : ''));
    }

    /** نص المشاركة للقروب (يفتح واتساب على جوال الموظف ويختار القروب بروحه) */
    public function shareText(CartQuote $cart, SheinQuotationPdf $pdf): string
    {
        $txt = (string) $cart->source_url;

        return $txt . "\n\n*" . optional($cart->customer)->code . '*';
    }

    /** ⚡ احجز الآن: للقروب (الرابط ← PDF لو فيه قطع مكررة ← كود العميل) + رسالة الحجز للعميل */
    /**
     * ✓ تأكيد الحجز من «السلات» بضغطة وحدة:
     * يتربط بحساب العميل (ولا يتفتحله) ← ينعمل إيصال (والعربون لو مكتوب) ويوصل العميل ← يمشي لقروب منتظرة (الرابط + PDF لو فيه تكرار + الكود) ← ينزل في «تحجزت»
     */
    public function confirmBook(Request $request, CartQuote $cart, SheinQuotationPdf $pdf)
    {
        $data = $request->validate(['paid' => 'nullable|numeric|min:0', 'payment_id' => 'nullable|exists:payment_methods,id']);
        if ($cart->status !== 'succeeded') {
            return back()->with('error', 'السلة #' . $cart->id . ' ما انحسبتش — ما نقدروش نأكدو حجزها');
        }
        // 👤 العميل: لو السلة من الواتساب ومش مربوطة بحساب، نلقوه برقمه ولا نفتحوله حساب
        $newAcc = null;
        if (!$cart->customer_id) {
            $waPh = preg_replace('/^218/', '0', preg_replace('/\D/', '', (string) $cart->whatsapp_customer_phone));
            $waPh = (str_contains((string) $cart->whatsapp_customer_phone, '@') || strlen($waPh) > 13) ? '' : $waPh;
            if ($waPh === '' && ($im = $cart->incomingMessage)) {
                $waPh = preg_replace('/^218/', '0', preg_replace('/\D/', '', (string) ($im->contact_phone ?: $im->phone)));
                $waPh = strlen($waPh) > 13 ? '' : $waPh;
            }
            [$gc, $isNew] = \App\Support\GuestAccount::resolve($cart->guest_name ?? optional($cart->incomingMessage)->name, $cart->guest_phone ?? ($waPh ?: null), $cart->guest_address ?? null);
            if (!$gc) {
                return back()->with('error', 'رقم العميل في السلة #' . $cart->id . ' مش واضح — ما قدرناش نربطوه بحساب. اعمله إيصال يدوي');
            }
            $cart->customer_id = $gc->id;
            $cart->save();
            $cart->setRelation('customer', $gc);
            $newAcc = $isNew;
        }
        $this->accNew = $newAcc;
        $c = $cart->customer;
        $order = $cart->order_id ? \App\Models\Order::find($cart->order_id) : null;
        $receiptSent = false;
        if (!$order) {
            try {
                DB::transaction(function () use ($cart, $data, &$order) {
                    $order = \App\Services\CourierService::orderFromCart($cart, 0, null);
                    if (!$order) {
                        throw new \RuntimeException('ما قدرناش نحسبو قيمة السلة');
                    }
                    $paid = min((float) ($data['paid'] ?? 0), (float) $order->total_price);
                    if ($paid > 0) {
                        \App\Services\OrderPaymentService::pay($order, $paid, (int) ($data['payment_id'] ?: \App\Services\AppSettings::cashPaymentId()), 'عربون · تأكيد حجز سلة #' . $cart->id);
                    }
                    $cart->portal_status = 'confirmed';
                    $cart->pay_method = $cart->pay_method ?: 'office';
                    $cart->ordered_at = $cart->ordered_at ?: now();
                    $cart->confirmed_at = $cart->confirmed_at ?: now();
                    $cart->courier_pay = $paid;
                    $cart->paid_amount = $paid;
                    $cart->paid_at = $paid > 0 ? now() : $cart->paid_at;
                    $cart->order_id = $order->id;
                    $cart->save();
                });
            } catch (\RuntimeException $e) {
                return back()->with('error', $e->getMessage());
            } catch (\Throwable $e) {
                report($e);

                return back()->with('error', 'ما قدرناش نعملو الإيصال: ' . mb_substr($e->getMessage(), 0, 150));
            }
            $receiptSent = $this->sendConfirmMsg($cart, $order);
        } elseif ($cart->portal_status !== 'confirmed') {
            $cart->portal_status = 'confirmed';
            $cart->confirmed_at = $cart->confirmed_at ?: now();
            $cart->save();
        }
        // 📤 قروب منتظرة: الرابط + PDF لو فيه تكرار + الكود
        $grpMsg = '';
        $g = collect((array) \App\Services\AppSettings::get('wa_groups_recent', []))->filter(fn ($x) => !empty($x['tag']) && !empty($x['id']))->first();
        if ($g) {
            $req2 = Request::create('', 'POST', ['to_phone' => $g['id'], 'wa' => 1]);
            $req2->setUserResolver($request->getUserResolver());
            try {
                $this->bookNow($req2, $cart->fresh(), $pdf);
                $grpMsg = session()->has('error') ? ' · ⚠ ما وصلش للقروب' : ' · 📤 انبعتت لقروب منتظرة';
                session()->forget(['error', 'success']);
            } catch (\Throwable $e) {
                report($e);
                $grpMsg = ' · ⚠ ما وصلش للقروب';
            }
        } else {
            $grpMsg = ' · ⚠ قروب منتظرة مش مسجل (من الإعدادات)';
        }
        // 📋 تنزل في «تحجزت»
        if (\App\Support\Fast::hasColumn('cart_quotes', 'booked_done_at')) {
            $cart = $cart->fresh();
            $cart->booked_done_at = $cart->booked_done_at ?: now();
            $cart->booked_by = $cart->booked_by ?: auth()->id();
            $cart->save();
        }
        foreach (['sg.badge.pcart', 'sg.badge.bqueue', 'sg.badge.courier'] as $k) {
            \Illuminate\Support\Facades\Cache::forget($k);
        }
        Log::create(['user_id' => auth()->id(), 'type' => 'cart', 'title' => ' تأكيد حجز سلة #' . $cart->id, 'log' => ' إيصال ' . optional($order)->code . ' · عربون ' . (float) ($data['paid'] ?? 0)]);

        return back()->with('success', '✓ تأكد حجز السلة #' . $cart->id . ' · إيصال ' . optional($order)->code . ($receiptSent ? ' · وصل العميل الإيصال' : '') . ($newAcc === true ? ' · 🎉 انفتحله حساب (كود ' . $c->code . ')' : '') . $grpMsg . ' · نزلت في «تحجزت»');
    }

    /** 📤 من أوامر الإيصال: يبعث سلة الإيصال لقروب الحجز (منتظرة): الرابط + PDF لو فيه تكرار + الكود */
    public function orderToGroup(Request $request, \App\Models\Order $order, SheinQuotationPdf $pdf)
    {
        $cart = CartQuote::where('order_id', $order->id)->latest('id')->first();
        if (!$cart || !$cart->source_url) {
            return back()->with('error', 'الإيصال ' . $order->code . ' مش من سلة، فما عندهش رابط نبعتوه');
        }
        if ($cart->status !== 'succeeded') {
            return back()->with('error', 'سلة الإيصال ' . $order->code . ' ما انحسبتش، فما نقدروش نبعتوها');
        }
        $g = collect((array) \App\Services\AppSettings::get('wa_groups_recent', []))->filter(fn ($x) => !empty($x['tag']) && !empty($x['id']))->first();
        if (!$g) {
            return back()->with('error', 'قروب الحجز (منتظرة) مش مسجل · من الإعدادات: خلّي حد يكتب #حجز في القروب وجرّبه');
        }
        $request->merge(['to_phone' => $g['id'], 'to_new' => null, 'wa' => 1]);

        return $this->bookNow($request, $cart, $pdf);
    }

    public function bookNow(Request $request, CartQuote $cart, SheinQuotationPdf $pdf)
    {
        // الرقم اللي اخترته يوصله الحجز (وانت تحوّله للقروب بروحك)
        $pickTo = trim((string) ($request->input('to_new') ?: $request->input('to_phone')));
        // 📢 قروب الحجز (اللي انكتب فيه #حجز) — نقبلو بس القروبات المسجلة عندنا
        $groups = collect((array) \App\Services\AppSettings::get('wa_groups_recent', []))->filter(fn ($g) => !empty($g['tag']) && !empty($g['id']))->pluck('id')
            ->merge(collect((array) \App\Services\AppSettings::get('wa_groups_list', []))->filter(fn ($g) => is_array($g) && !empty($g['id']))->pluck('id'))->unique()->all();
        $isGrp = str_contains($pickTo, '@g.us') && in_array($pickTo, $groups, true);
        if ($pickTo === '' || (str_contains($pickTo, '@') && !$isGrp)) {
            return back()->with('error', 'اختار الرقم اللي يمشيله الحجز');
        }
        $nums = (array) \App\Services\AppSettings::get('booking_numbers', []);
        if (!$isGrp && !in_array($pickTo, $nums, true)) {
            $nums[] = $pickTo; // رقم جديد يتحفظ في القائمة
        }
        \App\Services\AppSettings::put(['booking_last_phone' => $pickTo, 'booking_numbers' => array_values($nums)]);
        // ⚡ من «حسبها وما أكدهاش»: يمشي للرقم طول من غير تأكيد ولا إيصال
        $waMode = $request->boolean('wa');
        abort_unless($cart->status === 'succeeded' && ($waMode || $cart->customer_id), 404);
        $c = $cart->customer;
        // لازم يتأكد الحجز أول (ينعمل الإيصال ويوصل العميل) وبعدين ⚡ احجز
        if (!$waMode && $cart->portal_status !== 'confirmed') {
            return back()->with('error', 'أكّد الحجز أول بزر ✓ تأكيد الحجز (ولا 🏪 حجز في المكتب)، وبعدين اضغط ⚡ احجز');
        }
        if (!$waMode && empty($cart->order_id)) { // سلات قديمة تأكدت قبل التحديث
            try {
                if ($o0 = \App\Services\CourierService::orderFromCart($cart, 0, null)) {
                    $cart->order_id = $o0->id;
                    $cart->save();
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }
        // فيه تكرار؟ (قطعة كميتها أكثر من 1)
        $repeat = false;
        try {
            if ($cart->retailer === 'amazon') {
                $repeat = (int) data_get($cart->amazon_pricing, 'quantity', 1) > 1;
            } else {
                foreach ((array) ($pdf->quotationForPdf($cart)['items'] ?? []) as $it) {
                    if ((int) ($it['qty'] ?? 0) > 1 && ($it['availability'] ?? 'available') === 'available') {
                        $repeat = true;
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }
        $file = null;
        if ($repeat) { // 🔁 الـ PDF يمشي بس لو فيه تكرار — غيره: الرابط والكود بس
            try {
                $cart = $pdf->prepare($cart);
                $file = $cart->pdf_path && Storage::disk('local')->exists($cart->pdf_path) ? Storage::disk('local')->path($cart->pdf_path) : null;
            } catch (\Throwable $e) {
                report($e);
            }
        }
        $wa = app(\App\Services\WhatsAppService::class);
        $n = 0;
        foreach ($pickTo !== '' ? [$pickTo] : (array) \App\Services\AppSettings::get('booking_phones', ['0920703777', '0920403444']) as $ph) {
            $isGroup = str_contains((string) $ph, '@g.us');
            $to = $isGroup ? trim((string) $ph) : \App\Services\MessageService::normalize((string) $ph);
            try {
                // 3 رسايل بالترتيب: (1) رابط السلة (2) الـ PDF (3) كود العميل بروحه بخط عريض
                $txt = (string) $cart->source_url;
                $cap = '🛒 ' . $cart->retailer_label . ' #' . $cart->id . ($repeat ? ' · 🔁 فيها قطع مكررة (ملوّنة في الكمية)' : '');
                $codeMsg = '*' . (optional($c)->code ?: ($cart->guest_phone ?? null) ?: preg_replace('/^218/', '0', preg_replace('/\D/', '', (string) $cart->whatsapp_customer_phone))) . '*'; // غير مسجل: رقمه بدل الكود
                @set_time_limit(60);
                if ($isGroup) {
                    // 📢 للقروب: نفس الترتيب (الرابط ← PDF ← الكود) من نفس رقم الواتساب اللي في القروب
                    $ok = $wa->sendToGroup($to, $txt);
                    if ($ok && $file) {
                        sleep(3);
                        $wa->sendToGroup($to, $cap, $file, 'cart-' . $cart->id . '-' . optional($c)->code . '.pdf');
                    }
                    if ($ok && trim($codeMsg, '*') !== '') {
                        sleep($file ? 5 : 3);
                        $wa->sendToGroup($to, $codeMsg);
                    }
                    $n += $ok ? 1 : 0;
                    continue;
                }
                $ok = $wa->sendMessage($to, $txt);
                // واتسي يبعث الخفيف قبل التقيل، فنستنوا بين كل رسالة باش يجي الترتيب صح
                if ($file) {
                    sleep(3);
                    $wa->sendDocument($to, $file, 'cart-' . $cart->id . '-' . optional($c)->code . '.pdf', $cap);
                }
                if (trim($codeMsg, '*') !== '') {
                    sleep($file ? 5 : 3);
                    $ok = $wa->sendMessage($to, $codeMsg) || $ok;
                }
                $n += $ok ? 1 : 0;
            } catch (\Throwable $e) {
                report($e);
            }
        }
        $order = \App\Models\Order::find($cart->order_id);
        $cart->ordered_at = $cart->ordered_at ?: now();
        if ($n && \App\Support\Fast::hasColumn('cart_quotes', 'book_sent_to')) {
            $cart->book_sent_to = $pickTo;
            $cart->book_sent_at = now();
        }
        $cart->save();
        Log::create(['user_id' => auth()->id(), 'type' => 'cart', 'title' => ' احجز سلة #' . $cart->id, 'log' => ' انبعت لـ ' . $n . ($order ? ' · إيصال ' . $order->code : '')]);
        $sent = false;

        $gErr = $n === 0 ? \Illuminate\Support\Facades\Cache::get('wa_group_last') : null;

        return back()->with($n ? 'success' : 'error', $n
            ? '✅ انبعت الحجز ' . ($isGrp ? 'لقروب الحجز 📢' : 'للرقم ' . $pickTo) . ' (الرابط' . ($file ? ' + PDF' . ($repeat ? ' فيه تكرار' : '') : '') . ' + الكود)' . ($order ? ' · إيصال ' . $order->code : '')
            : ($isGrp ? '✕ ما وصلش للقروب · ' . mb_substr((string) \Illuminate\Support\Facades\Cache::get('wa_group_last'), 0, 200) . ' — تأكد إن جلسة واتسي مربوطة' : '✕ ما وصلش للرقم ' . $pickTo . ' — تأكد من الرقم ومن خدمة الواتساب'));
    }

    /** 📋 قائمة الحجز: السلات المتأكدة اللي لازم تنحجز، كل وحدة بزر ✅ تم الحجز */
    public function queue(Request $request, SheinQuotationPdf $pdf)
    {
        $has = \App\Support\Fast::hasColumn('cart_quotes', 'booked_done_at');
        $done = $request->input('v') === 'done';
        // + طلبات المندوب من عملاء غير مسجلين (بدون حساب)
        $rows = CartQuote::with('customer')->where($this->cartScope())->where('portal_status', 'confirmed')
            ->when($has, fn ($q) => $done ? $q->whereNotNull('booked_done_at')->latest('booked_done_at') : $q->whereNull('booked_done_at')->oldest('confirmed_at'))
            ->when(!$has, fn ($q) => $q->latest('confirmed_at'))
            ->limit($done ? 50 : 100)->get();
        $items = [];
        foreach ($rows as $r) {
            $items[$r->id] = $this->itemsText($r, $pdf);
        }
        $users = $done ? \App\Models\User::whereIn('id', $rows->pluck('booked_by')->filter()->unique())->pluck('name', 'id') : collect();

        return view('sg.carts.queue', ['nav' => 'bqueue', 'rows' => $rows, 'items' => $items, 'done' => $done, 'users' => $users,
            'left' => $has ? CartQuote::whereNotNull('customer_id')->where('portal_status', 'confirmed')->whereNull('booked_done_at')->count() : $rows->count()]);
    }

    public function queueDone(Request $request, CartQuote $cart)
    {
        abort_unless(\App\Support\Fast::hasColumn('cart_quotes', 'booked_done_at'), 404);
        $undo = $request->boolean('undo');
        $cart->booked_done_at = $undo ? null : now();
        $cart->booked_by = $undo ? null : auth()->id();
        $cart->save();
        \Illuminate\Support\Facades\Cache::forget('sg.badge.bqueue');
        Log::create(['user_id' => auth()->id(), 'type' => 'cart', 'title' => ' قائمة الحجز سلة #' . $cart->id, 'log' => $undo ? ' رجعت للقائمة' : ' تم الحجز']);

        return back()->with('success', $undo ? '↩ رجعت للقائمة' : '✅ سلة #' . $cart->id . ' تحجزت');
    }
}
