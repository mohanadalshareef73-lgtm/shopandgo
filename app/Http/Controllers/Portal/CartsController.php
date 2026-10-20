<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\CartQuote;
use App\Models\Setting;
use App\Models\WhatsappMessages;
use App\Services\CartQuoteException;
use App\Services\CartQuoteService;
use App\Services\QuoteUrlExtractor;
use App\Services\SheinLinkClassifier;
use App\Services\SheinQuotationCalculator;
use App\Services\SheinQuotationPdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * سلات العميل من حسابه: يلصق الرابط، المنظومة تقرا القطع بنفس محرك الواتساب،
 * يعدّل الكميات (+/−)، يأكد الطلب ويطلعله PDF، ويرفع إيصال التحويل.
 */
class CartsController extends Controller
{
    /** حماية من السبام: أكثر من 5 سلات في 10 دقائق = إيقاف ساعة، وبعدها سلة وحدة في الساعة لمدة يوم */
    public const SPAM_COUNT = 5;
    public const SPAM_MINUTES = 10;

    protected function me()
    {
        return auth('customer')->user();
    }

    /** سلات الزائر (بدون حساب) محفوظة في الجلسة */
    public static function guestIds(): array
    {
        return array_map('intval', (array) session('guest_carts', []));
    }

    /** لما يدخل أو يسجّل: سلات الزائر تولي متاعه */
    public static function claimGuest($customer): int
    {
        $ids = static::guestIds();
        if (!$customer || !$ids) {
            return 0;
        }
        $n = CartQuote::whereIn('id', $ids)->whereNull('customer_id')->update(['customer_id' => $customer->id]);
        session()->forget('guest_carts');

        return $n;
    }

    /** رابط عام للسلة (يتبعت على الواتساب): يفتحها حتى بدون حساب */
    public static function publicToken(int $id): string
    {
        return substr(hash_hmac('sha256', 'cart-public-' . $id, (string) config('app.key')), 0, 16);
    }

    public static function publicUrl(int $id): string
    {
        return route('portal.cart.public', ['id' => $id, 't' => static::publicToken($id)]);
    }

    /** ⚡ سطر قصير برابط سلته (تحت كل رسايل السلة على الواتساب) — ما يتكررش لو الرابط موجود في الرسالة */
    public static function linkLine(int $id, string $msg = ''): string
    {
        try {
            $url = static::publicUrl($id);
        } catch (\Throwable $e) {
            return '';
        }
        if ($msg !== '' && str_contains($msg, $url)) {
            return '';
        }
        $tpl = trim((string) \App\Services\AppSettings::get('wa_cart_link_line', '')) ?: (\App\Services\AppSettings::DEFAULTS['wa_cart_link_line'] ?? '');

        return $tpl === '' ? '' : "\n\n" . (str_contains($tpl, '{رابط}') ? str_replace('{رابط}', $url, $tpl) : $tpl . "\n" . $url);
    }

    public function publicView(int $id, string $t)
    {
        abort_unless(hash_equals(static::publicToken($id), $t), 404);
        $cart = CartQuote::findOrFail($id);
        $c = $this->me();
        if ($cart->customer_id) {
            // السلة متاعة حساب: لازم يدخل بيه
            if (!$c || $c->id !== (int) $cart->customer_id) {
                session(['url.intended' => route('portal.cart', $id)]);

                return redirect()->route('customer.login')->with('warning', 'السلة هذي محفوظة في حسابك — ادخل وتلقاها');
            }

            return redirect()->route('portal.cart', $id);
        }
        if ($c) {
            $cart->customer_id = $c->id;
            $cart->portal_status = $cart->portal_status ?: 'draft';
            $cart->save();
        } else {
            if (!$cart->portal_status) {
                $cart->portal_status = 'draft';
                $cart->save();
            }
            session()->push('guest_carts', $id);
        }

        return redirect()->route('portal.cart', $id);
    }

    /** قيمة السلة بالدينار */
    public static function cartTotal(CartQuote $cart): float
    {
        try {
            if ($cart->retailer === 'amazon') {
                return (float) data_get($cart->amazon_pricing, 'total.amount', 0);
            }

            return (float) data_get(app(SheinQuotationPdf::class)->quotationForPdf($cart), 'total.amount', 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public static function courierUrl(int $id): string
    {
        return route('portal.cart.courier', ['id' => $id, 't' => static::publicToken($id)]);
    }

    /** 🛵 صفحة صغيرة من رابط الواتساب: يعبي بياناته والمندوب يمشيله يستلم العربون (بدون تسجيل دخول) */
    public function courierForm(int $id, string $t)
    {
        abort_unless(hash_equals(static::publicToken($id), $t), 404);
        $cart = CartQuote::findOrFail($id);
        abort_unless($cart->status === 'succeeded', 404);
        $total = static::cartTotal($cart);
        [, $pct] = static::deposit();
        $done = \App\Models\Delivery::where('cart_id', $cart->id)->whereIn('status', ['requested', 'assigned', 'out', 'problem', 'delivered'])->exists();

        return view('portal.courier', ['cart' => $cart, 't' => $t, 'c' => $cart->customer ?: $this->me(), 'total' => $total, 'pct' => $pct,
            'deposit' => ceil($total * $pct / 100), 'fee' => (float) \App\Services\AppSettings::get('courier_default_fee', 10), 'done' => $done,
            'phone' => preg_replace('/^218/', '0', preg_replace('/\D/', '', (string) $cart->whatsapp_customer_phone))]);
    }

    public function courierSend(Request $request, int $id, string $t)
    {
        abort_unless(hash_equals(static::publicToken($id), $t), 404);
        $data = $request->validate(['name' => 'required|string|max:120', 'phone' => 'required|string|max:20', 'address' => 'required|string|max:200',
            'location_url' => 'nullable|string|max:500', 'note' => 'nullable|string|max:200'],
            ['name.required' => 'اكتب اسمك', 'phone.required' => 'اكتب رقمك', 'address.required' => 'اكتب عنوانك']);
        $cart = CartQuote::findOrFail($id);
        abort_unless($cart->status === 'succeeded', 404);
        if (\App\Models\Delivery::where('cart_id', $cart->id)->whereIn('status', ['requested', 'assigned', 'out', 'problem'])->exists()) {
            return redirect()->route('portal.cart.courier', ['id' => $id, 't' => $t]);
        }
        $total = static::cartTotal($cart);
        if ($total <= 0) {
            return back()->with('error', 'ما قدرناش نحسبو قيمة السلة — كلمنا على الواتساب');
        }
        [, $pct] = static::deposit();
        $deposit = ceil($total * $pct / 100);
        try {
            $phone = preg_replace('/\D/', '', $data['phone']);
            $tail = substr($phone, -9);
            // عميل مسجل من قبل؟ يتربط بحسابه طول · غير مسجل: بدون حساب وبدون إيصال (الإدارة تعمله إيصال بعدين)
            $c = $cart->customer ?: ($tail !== '' ? \App\Models\Customer::where('phone_1', 'like', '%' . $tail)->first() : null);
            $cart->pay_method = 'cash_courier';
            $cart->courier_pay = $deposit;
            $cart->portal_status = 'ordered';
            $cart->ordered_at = now();
            if ($c) {
                if (empty($c->address)) {
                    $c->address = trim($data['address']);
                    $c->save();
                }
                $cart->customer_id = $c->id;
                $cart->save();
                $dl = \App\Services\CourierService::cartCollect($cart->fresh(), $deposit);
                if ($dl) {
                    $dl->address = trim($data['address']);
                    if (!empty($data['location_url'])) {
                        $dl->location_url = $data['location_url'];
                    }
                    $dl->recipient_phone = $phone;
                    if (!empty($data['note'])) {
                        $dl->note = trim($dl->note . ' · ' . $data['note']);
                    }
                    $dl->save();
                }
            } else {
                if (\Illuminate\Support\Facades\Schema::hasColumn('cart_quotes', 'guest_phone')) {
                    $cart->guest_name = mb_substr(trim($data['name']), 0, 120);
                    $cart->guest_phone = \App\Http\Controllers\Portal\SignupController::normalizePhone($phone);
                    $cart->guest_address = mb_substr(trim($data['address']), 0, 200);
                }
                $cart->save();
                \App\Services\CourierService::cartCollectGuest($cart, $deposit, ['name' => trim($data['name']), 'phone' => $phone, 'address' => trim($data['address']),
                    'location_url' => $data['location_url'] ?? null, 'note' => $data['note'] ?? null]);
            }
            \App\Models\Log::create(['user_id' => null, 'type' => 'cart', 'title' => ' يبي مندوب يستلم العربون (سلة #' . $cart->id . ')', 'log' => ' ' . $data['name'] . ' · ' . $data['phone'] . ' · عربون ' . $deposit]);
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput()->with('error', 'صار مشكل، جرب مرة ثانية ولا كلمنا على الواتساب');
        }

        return redirect()->route('portal.cart.courier', ['id' => $id, 't' => $t])->with('success', '✅ وصلنا طلبك');
    }

    /** رقم الإيصال بالأرقام والحروف بس (بدون فراغات وشرطات) */
    public static function normRef(?string $r): ?string
    {
        $r = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', strtr((string) $r, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9'])));

        return $r !== '' ? $r : null;
    }

    /** بصمة الإيصال + تنبيه لو نفس الصورة أو نفس رقم الإيصال استعمل قبل في سلة ثانية */
    public static function checkReceipt(CartQuote $cart, string $path): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasColumn('cart_quotes', 'receipt_hash')) {
            return;
        }
        try {
            $hash = hash_file('sha256', Storage::disk('local')->path($path));
        } catch (\Throwable $e) {
            return;
        }
        $cart->receipt_hash = $hash;
        $cart->save();
        $dups = CartQuote::where('id', '!=', $cart->id)->where(function ($w) use ($hash, $cart) {
            $w->where('receipt_hash', $hash);
            if (!empty($cart->receipt_ref) && \Illuminate\Support\Facades\Schema::hasColumn('cart_quotes', 'receipt_ref')) {
                $w->orWhere('receipt_ref', $cart->receipt_ref); // نفس رقم إيصال المصرف
            }
        })->with('customer')->get();
        if ($dups->isNotEmpty()) {
            \App\Services\AlertService::push('security', '🚨 إيصال تحويل مكرر — راجع، يحتمل نصب',
                'سلة #' . $cart->id . ' (' . optional($cart->customer)->name . ')' . ($cart->receipt_ref ? ' رقم إيصال ' . $cart->receipt_ref : '') . ' — نفس الإيصال مستعمل قبل في: ' . $dups->map(fn ($d) => '#' . $d->id . ' ' . optional($d->customer)->name)->implode('، '),
                route('sg.pcarts', ['tab' => 'all']), 'warn', true);
        }
    }

    protected function mine(int $id): CartQuote
    {
        if ($c = $this->me()) {
            static::claimGuest($c);

            return CartQuote::where('customer_id', $c->id)->findOrFail($id);
        }
        abort_unless(in_array($id, static::guestIds(), true), 404);

        return CartQuote::whereNull('customer_id')->findOrFail($id);
    }

    public function index()
    {
        $c = $this->me();
        if ($c) {
            static::claimGuest($c);
        }
        $carts = $c ? CartQuote::where('customer_id', $c->id)->latest()->take(50)->get()
            : CartQuote::whereIn('id', static::guestIds())->whereNull('customer_id')->latest()->take(10)->get();
        $pdf = app(SheinQuotationPdf::class);
        foreach ($carts as $ct) {
            $ct->x_total = null;
            $ct->x_qty = null;
            if ($ct->status === 'succeeded') {
                try {
                    $q = $ct->retailer === 'amazon' ? (array) $ct->amazon_pricing : ($ct->quotation ? $pdf->quotationForPdf($ct) : []);
                    $ct->x_total = data_get($q, 'total.amount');
                    $ct->x_qty = $ct->retailer === 'amazon' ? data_get($q, 'quantity') : ($q['available_quantity'] ?? null);
                } catch (\Throwable $e) {
                }
            }
        }

        return view('portal.carts', ['c' => $c, 'carts' => $carts, 'block' => $c ? $this->blockReason($c) : null, 'wa' => $c ? $this->whatsappCarts($c) : collect()]);
    }

    /** سلات حسبها على الواتساب (آخر 3 أيام) — يطلبها من هني بدون ما تنحسب مرة ثانية */
    protected function whatsappCarts($c)
    {
        $phone = \App\Services\MessageService::normalize((string) $c->phone_1);
        $tail = substr($phone, -9);
        if (strlen($tail) < 9) {
            return collect();
        }

        return CartQuote::whereNull('customer_id')->where('status', 'succeeded')->where('created_at', '>=', now()->subDays(3))
            ->whereHas('incomingMessage', fn ($m) => $m->where('from', 'like', '%' . $tail))->latest()->take(10)->get();
    }

    public function claim(int $id)
    {
        $c = $this->me();
        $cart = $this->whatsappCarts($c)->firstWhere('id', $id);
        abort_unless($cart, 404);
        $cart->customer_id = $c->id;
        $cart->portal_status = 'draft';
        $cart->save();

        return redirect()->route('portal.cart', $cart->id);
    }

    /** null = مسموح، وإلا رسالة السبب */
    protected function blockReason($c): ?string
    {
        if ($c->cart_ban_until && now()->lt($c->cart_ban_until)) {
            return 'وقفنا حساب السلات مؤقتاً بسبب كثرة الطلبات. ترجع تقدر بعد ' . now()->diffInMinutes($c->cart_ban_until) . ' دقيقة.';
        }
        if ($c->cart_probation_until && now()->lt($c->cart_probation_until)
            && CartQuote::where('customer_id', $c->id)->where('created_at', '>=', now()->subHour())->exists()) {
            return 'مسموحلك سلة وحدة كل ساعة لمدة يوم. جرّب بعد شوية.';
        }

        return null;
    }

    public function store(Request $request, QuoteUrlExtractor $urls, SheinLinkClassifier $links, CartQuoteService $quotes)
    {
        $data = $request->validate(['link' => 'required|string|max:8000', 'qty' => 'nullable|integer|min:1|max:99'], ['link.required' => 'الصق رابط السلة']);
        $c = $this->me();
        if (!$c) {
            // زائر بدون حساب: 3 سلات في الساعة من نفس الجهاز/الشبكة بس (كل سلة تكلفنا)
            $k = 'guest-cart:' . $request->ip();
            if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($k, (int) \App\Services\AppSettings::get('guest_carts_per_hour', 3))) {
                return back()->withInput()->with('error', 'وصلت حد السلات بدون حساب. سجّل دخول أو اعمل حساب وكمّل تحسب براحتك 👇');
            }
            \Illuminate\Support\Facades\RateLimiter::hit($k, 3600);
        } elseif ($why = $this->blockReason($c)) {
            return back()->with('error', $why);
        }
        // سبام؟ إيقاف ساعة، وبعدها سلة في الساعة لمدة يوم
        $recent = $c ? CartQuote::where('customer_id', $c->id)->where('created_at', '>=', now()->subMinutes(self::SPAM_MINUTES))->count() : 0;
        if ($c && $recent >= self::SPAM_COUNT) {
            $c->cart_ban_until = now()->addHour();
            $c->cart_probation_until = now()->addHours(25);
            $c->save();

            return back()->with('error', 'طلبات كثيرة في وقت قصير — وقفنا حساب السلات ساعة.');
        }
        $url = collect($urls->extract($data['link']))->first();
        if (!$url) {
            return back()->withInput()->with('error', 'ما لقيناش رابط. انسخ رابط مشاركة السلة من التطبيق والصقه هني.');
        }
        $retailer = $urls->isAmazonUrl($url) ? 'amazon' : ($urls->isIherbUrl($url) ? 'iherb' : 'shein');
        if (!Setting::find(1)?->isCartStoreEnabled($retailer)) {
            return back()->withInput()->with('error', 'حساب السلات من المتجر هذا موقف حالياً.');
        }
        if ($retailer === 'shein' && $links->classify($url) !== 'cart') {
            return back()->withInput()->with('error', 'الرابط هذا مش رابط سلة. من تطبيق SHEIN افتح السلة ← مشاركة ← انسخ الرابط.');
        }
        // نفس الرابط اليوم؟ نرجعوه بدل ما نحسبوه مرة ثانية
        $same = CartQuote::when($c, fn ($q) => $q->where('customer_id', $c->id), fn ($q) => $q->whereIn('id', static::guestIds()))->where('source_url', $url)->where('created_at', '>=', now()->subDay())
            ->whereNotIn('status', ['failed', 'expired', 'cancelled'])->latest()->first();
        if ($same) {
            return redirect()->route('portal.cart', $same->id);
        }
        $cart = DB::transaction(function () use ($c, $url, $retailer, $data) {
            $incoming = WhatsappMessages::create([
                'message_id' => 'portal-' . Str::uuid(), 'from' => $c ? \App\Services\MessageService::normalize((string) $c->phone_1) : 'guest-' . substr(md5((string) request()->ip()), 0, 10),
                'phone' => '', 'name' => $c ? $c->name : 'زائر', 'type' => 'text', 'message' => $url, 'timestamp' => now(), 'is_reply' => false,
            ]);

            return CartQuote::create([
                'whatsapp_messages_id' => $incoming->id, 'source_url' => $url, 'idempotency_key' => 'portal-' . Str::uuid(),
                'origin' => 'manual', 'retailer' => $retailer, 'requested_quantity' => $retailer === 'amazon' ? (int) ($data['qty'] ?? 1) : 1,
                'status' => 'pending', 'operator_status' => 'new', 'next_attempt_at' => now(),
                'customer_id' => optional($c)->id, 'portal_status' => 'draft',
            ]);
        });
        if (!$c) {
            session()->push('guest_carts', $cart->id);
        }
        try {
            $quotes->process($cart->id);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('portal.cart', $cart->id);
    }

    /** بيانات السلة (للصفحة وللتحديث التلقائي) */
    protected function data(CartQuote $cart, SheinQuotationPdf $pdf): array
    {
        $out = ['id' => $cart->id, 'status' => $cart->status, 'portal' => $cart->portal_status, 'retailer' => $cart->retailer, 'items' => [], 'total' => null];
        // وقت تقريبي: عادة 3 دقائق، وكحد أقصى موعد الخدمة (15 دقيقة)
        $out['eta'] = $cart->created_at->copy()->addMinutes(3)->toIso8601String();
        $out['max'] = data_get($cart->payload, 'deadlineAt') ?: $cart->created_at->copy()->addMinutes(15)->toIso8601String();
        if ($cart->status !== 'succeeded') {
            return $out;
        }
        try {
            $cart = $pdf->prepare($cart);
        } catch (CartQuoteException $e) {
            $out['error'] = 'السعر مش جاهز توا، نحاولو بعد شوية';

            return $out;
        }
        if ($cart->retailer === 'amazon') {
            $p = (array) $cart->amazon_pricing;
            $out['total'] = data_get($p, 'total.amount');
            $out['qty'] = $p['quantity'] ?? $cart->requested_quantity;
            $out['items'][] = ['i' => 0, 'name' => data_get($cart->payload, 'result.title') ?? data_get($cart->payload, 'result.items.0.name') ?? 'منتج أمازون', 'variant' => '', 'qty' => (int) $out['qty'], 'unit' => null, 'ok' => true, 'fixed' => true];

            return $out;
        }
        // الكميات: لو طلبها قبل نوريو اللي اختاره، وإلا من السلة
        $q = $cart->pdf_overrides ? $pdf->quotationForPdf($cart) : $cart->quotation;
        foreach ($cart->quotation['items'] as $i => $it) {
            $out['items'][] = [
                'i' => $i, 'name' => $it['name'], 'variant' => $it['variant'], 'qty' => (int) ($q['items'][$i]['qty'] ?? $it['qty']),
                'unit' => $it['unit_lyd'] !== null ? (float) $it['unit_lyd'] : null, 'ok' => $it['availability'] === 'available',
            ];
        }
        $out['total'] = $q['total']['amount'] ?? null;

        return $out;
    }

    public function show(int $id, SheinQuotationPdf $pdf)
    {
        $cart = $this->mine($id);

        if ($this->me() && session()->pull('guest_order.' . $cart->id)) {
            session()->now('success', '✓ سلتك رجعتلك ومحفوظة في حسابك — راجع الكميات واضغط «أكد الطلب»');
        }

        return view('portal.cart', ['c' => $this->me(), 'cart' => $cart, 'd' => $this->data($cart, $pdf), 'guest' => !$this->me()]);
    }

    public function poll(int $id, SheinQuotationPdf $pdf)
    {
        return response()->json($this->data($this->mine($id), $pdf));
    }

    public function image(int $id, int $i, SheinQuotationPdf $pdf)
    {
        $path = $pdf->productImage($this->mine($id), $i);
        abort_unless($path && is_file($path), 404);

        return response()->file($path, ['Cache-Control' => 'private, max-age=86400']);
    }

    /** يأكد الطلب بالكميات اللي اختارها */
    public function order(Request $request, int $id, SheinQuotationPdf $pdf, SheinQuotationCalculator $calc)
    {
        $cart = $this->mine($id);
        // 👤 زائر بدون حساب: يحجز بإسمه ورقمه بس، والحساب ينفتحله أوتوماتيك وقت نأكدو طلبه
        $guest = !$this->me();
        $hasG = \Illuminate\Support\Facades\Schema::hasColumn('cart_quotes', 'guest_phone');
        if ($guest && !$hasG) {
            session(['url.intended' => route('portal.cart', $cart->id), 'guest_order.' . $cart->id => $request->only('qty', 'pay_method')]);

            return redirect()->route('customer.login')->with('warning', '👋 باش تأكد طلبك، ادخل لحسابك أو اعمل حساب جديد — سلتك محفوظة وترجعلها على طول');
        }
        abort_unless($cart->status === 'succeeded', 409);
        if (in_array($cart->portal_status, ['confirmed'], true)) {
            return back()->with('warning', 'الطلب هذا متأكد خلاص');
        }
        if (in_array($cart->portal_status, ['ordered'], true) && $guest) {
            return back()->with('warning', 'وصلنا طلبك من قبل ✓ — نأكدولك قريب');
        }
        $rules = ['qty' => 'nullable|array', 'qty.*' => 'integer|min:0|max:999', 'pay_method' => 'required|in:transfer,cash_courier', 'courier_pay' => 'nullable|numeric|min:0',
            'receipt' => ($guest || $this->trusted() || (float) $request->input('courier_pay', 1) <= 0 ? 'nullable' : 'required_if:pay_method,transfer|nullable') . '|file|mimes:jpg,jpeg,png,webp,heic,pdf|max:10240',
            'receipt_ref' => 'nullable|string|max:60'];
        if ($guest) {
            $request->merge(['g_phone' => \App\Http\Controllers\Portal\SignupController::normalizePhone((string) $request->input('g_phone'))]);
            $rules += ['g_name' => 'required|string|min:2|max:120', 'g_phone' => ['required', 'regex:/^0\d{8,11}$/'],
                'g_address' => 'required_if:pay_method,cash_courier|nullable|string|max:200', 'g_location' => 'nullable|string|max:500'];
        }
        $data = $request->validate($rules,
            ['receipt.required_if' => 'ارفع صورة إيصال التحويل أو ملف PDF', 'receipt.mimes' => 'الإيصال لازم صورة أو PDF', 'receipt.max' => 'الملف كبير برشا',
                'g_name.required' => 'اكتب اسمك', 'g_name.min' => 'اكتب اسمك كامل', 'g_phone.required' => 'اكتب رقم هاتفك', 'g_phone.regex' => 'رقم الهاتف مش صحيح (مثال: 0912345678)',
                'g_address.required_if' => 'اكتب عنوانك باش يجيك المندوب']);
        $rcPath = $request->hasFile('receipt') ? \App\Support\ImageShrink::store($request->file('receipt'), 'cart-receipts', 'local') : null;
        DB::transaction(function () use ($cart, $data, $pdf, $calc, $rcPath, $guest) {
            $rec = CartQuote::lockForUpdate()->findOrFail($cart->id);
            if ($rec->retailer !== 'amazon') {
                $rec = $pdf->prepare($rec);
                $values = $calc->editorValues($rec->quotation, null);
                $any = false;
                foreach ($values['items'] as $i => &$row) {
                    if (isset($data['qty'][$i])) {
                        $row['qty'] = (int) $data['qty'][$i];
                    }
                    $any = $any || ($row['qty'] > 0 && $rec->quotation['items'][$i]['availability'] === 'available');
                }
                unset($row);
                if (!$any) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['qty' => 'اختار قطعة وحدة على الأقل']);
                }
                $rec->pdf_overrides = $values;
            }
            // عربون إجباري: السلات الكبيرة لازم تحويل (ما فيش كاش عند المندوب)
            $total = $rec->retailer === 'amazon' ? (float) data_get($rec->amazon_pricing, 'total.amount', 0)
                : (float) data_get($calc->applyPdfOverrides($rec->quotation, $rec->pdf_overrides), 'total.amount', 0);
            if ($rcPath) {
                $rec->receipt_path = $rcPath;
            }
            if (!empty($data['receipt_ref']) && \Illuminate\Support\Facades\Schema::hasColumn('cart_quotes', 'receipt_ref')) {
                $rec->receipt_ref = static::normRef($data['receipt_ref']);
            }
            // كاش عند المندوب أو تحويل: يختار كم يدفع توا (أقل شي نسبة العربون من الإعدادات)
            if (in_array($data['pay_method'], ['cash_courier', 'transfer'], true)) {
                [, $dPct] = self::deposit();
                $min = $this->trusted() ? 0 : ceil($total * $dPct / 100); // الموثوق ✅ بدون عربون
                $pay = round((float) ($data['courier_pay'] ?? $total), 2);
                if ($pay + 0.009 < $min) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['courier_pay' => 'المبلغ شوية 🙏 أقل شي ' . number_format($min) . ' د.ل (' . $dPct . '% من قيمة الطلب)']);
                }
                if (\Illuminate\Support\Facades\Schema::hasColumn('cart_quotes', 'courier_pay')) {
                    $rec->courier_pay = min($pay, $total);
                }
            }
            if ($guest) {
                $rec->guest_name = mb_substr(trim($data['g_name']), 0, 120);
                $rec->guest_phone = $data['g_phone'];
                $rec->guest_address = !empty($data['g_address']) ? mb_substr(trim($data['g_address']), 0, 200) : null;
            }
            $rec->portal_status = 'ordered';
            $rec->pay_method = $data['pay_method'];
            $rec->ordered_at = now();
            $rec->save();
        });
        if ($rcPath) {
            static::checkReceipt($cart->fresh(), $rcPath);
        }
        try {
            $pdf->prepare($cart->fresh());
        } catch (\Throwable $e) {
            report($e);
        }
        try {
            \App\Models\Log::create(['user_id' => null, 'type' => 'cart', 'title' => ' طلب سلة من الموقع #' . $cart->id, 'log' => ' العميل ' . ($guest ? ($data['g_name'] . ' (بدون حساب) · ' . $data['g_phone']) : optional($this->me())->name) . ' أكد طلب سلة ' . $cart->retailer_label]);
            if ($guest && $data['pay_method'] === 'transfer') {
                \App\Services\AlertService::push('request', '🛒 حجز سلة #' . $cart->id . ' من الرابط (بدون حساب)', $data['g_name'] . ' · ' . $data['g_phone'] . ' · تحويل' . ($rcPath ? ' · رفع إيصال' : ' · لسه ما رفعش إيصال') . ' — أكد الحجز وينفتحله حساب', route('sg.pcarts', ['tab' => 'ordered']), 'info', true);
            }
        } catch (\Throwable $e) {
        }

        // كاش عند المندوب: المندوب يمشي يقبض منه مباشرة (من غير ما يجي للمحل ياخذ إيصال)
        $fresh = $cart->fresh();
        if ($fresh->pay_method === 'cash_courier' && \Illuminate\Support\Facades\Schema::hasColumn('deliveries', 'cart_id')) {
            try {
                $tot = $fresh->retailer === 'amazon' ? (float) data_get($fresh->amazon_pricing, 'total.amount', 0)
                    : (float) data_get($calc->applyPdfOverrides($fresh->quotation, $fresh->pdf_overrides), 'total.amount', 0);
                $pay = $fresh->courier_pay !== null ? (float) $fresh->courier_pay : $tot;
                if ($pay <= 0.009) {
                    return redirect()->route('portal.cart', $cart->id)->with('success', 'وصلنا طلبك ✓ بدون عربون (حسابك موثوق ✅) — نأكدولك الحجز قريب');
                }
                $dl = null;
                if ($tot > 0 && $guest) {
                    $dl = \App\Services\CourierService::cartCollectGuest($fresh, $pay, ['name' => $data['g_name'], 'phone' => $data['g_phone'], 'address' => $data['g_address'] ?? null, 'location_url' => $data['g_location'] ?? null]);
                } elseif ($tot > 0) {
                    $dl = \App\Services\CourierService::cartCollect($fresh, $pay);
                }
                if ($dl) {
                    return redirect()->route('portal.cart', $cart->id)->with('success', 'وصلنا طلبك ✓ المندوب بيكلمك ويجيك يقبض ' . number_format($dl->toCollect(), 2) . ' د.ل (' . number_format($pay, 2) . ' عربون + ' . number_format($dl->customerFee(), 2) . ' توصيل)، وأول ما يقبض يجيك الإيصال على الواتساب وطلبك يتحجز');
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('portal.cart', $cart->id)->with('success', $guest
            ? 'وصلنا طلبك ✓ — أول ما نأكدوه يجيك الإيصال على الواتساب، وينفتحلك حساب تتابع منه طلبك 💙'
            : 'وصلنا طلبك ✓');
    }

    /** العميل موثوق ✅ (يأكد بدون عربون) */
    protected function trusted(): bool
    {
        $c = $this->me();

        return $c && !empty($c->trusted);
    }

    /** [أقل مبلغ يحتاج عربون، نسبة العربون %] */
    public static function deposit(): array
    {
        return [(float) \App\Services\AppSettings::get('deposit_min', 1000), (float) \App\Services\AppSettings::get('deposit_pct', 30)];
    }

    public function pdf(int $id, SheinQuotationPdf $pdf)
    {
        $cart = $pdf->prepare($this->mine($id));
        abort_unless($cart->pdf_path && Storage::disk('local')->exists($cart->pdf_path), 404);

        return response()->file(Storage::disk('local')->path($cart->pdf_path), ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="cart-' . $cart->id . '.pdf"']);
    }

    public function receipt(Request $request, int $id)
    {
        $cart = $this->mine($id);
        $request->validate(['receipt' => 'required|file|mimes:jpg,jpeg,png,webp,heic,pdf|max:10240', 'receipt_ref' => 'nullable|string|max:60'], ['receipt.required' => 'اختار صورة الإيصال']);
        if ($request->filled('receipt_ref') && \Illuminate\Support\Facades\Schema::hasColumn('cart_quotes', 'receipt_ref')) {
            $cart->receipt_ref = static::normRef($request->receipt_ref);
        }
        $cart->receipt_path = \App\Support\ImageShrink::store($request->file('receipt'), 'cart-receipts', 'local');
        $cart->save();
        static::checkReceipt($cart, $cart->receipt_path);

        return back()->with('success', 'وصلنا الإيصال ✓ — خلال 24 ساعة يتم تأكيد طلبك');
    }
}
