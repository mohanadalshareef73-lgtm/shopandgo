<?php

namespace App\Services;

use App\Models\CourierSettlement;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Log;
use App\Models\Order;
use App\Models\PaidHistory;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * التوصيل عن طريق المندوب:
 *  1) نعطوه الطلبية (assign) → الإيصالات تظهر من الرف "مع المندوب"
 *  2) المندوب يضغط "تم التسليم" ويكتب كم قبض نقداً وكم مصرف
 *     - المصرف يدخل الخزينة طول (العميل حوّل لحساب الشركة)
 *     - الكاش يبقى في رصيد المندوب حتى إقفال معه
 *  3) إقفال الحساب (settle): يأخذ أجرته، يسلّم الباقي، الكسور تتلغى
 */
class CourierService
{
    public static function couriers()
    {
        return User::where('type_id', 4)->orderByDesc('status')->when(\Illuminate\Support\Facades\Schema::hasColumn('users', 'courier_available'), fn ($q) => $q->orderByDesc('courier_available'))->orderBy('name')->get();
    }

    /** إنشاء توصيل لإيصالات عميل */
    public static function assign(Customer $customer, Collection $orders, array $d, ?User $courier, string $source = 'admin'): Delivery
    {
        $due = $orders->sum(fn ($o) => max(0, $o->remaining()));
        $dl = Delivery::create([
            'code' => Delivery::newCode(),
            'customer_id' => $customer->id,
            'courier_id' => optional($courier)->id,
            'source' => $source,
            'status' => $courier ? 'assigned' : 'requested',
            'recipient_name' => $d['recipient_name'] ?? $customer->name,
            'recipient_phone' => $d['recipient_phone'] ?? $customer->phone_1,
            'recipient_phone2' => $d['recipient_phone2'] ?? null,
            'city' => ($d['city'] ?? null) ?: ($customer->city ?? null),
            'address' => ($d['address'] ?? null) ?: static::withArea($customer->area ?? null, $customer->address),
            'location_url' => $d['location_url'] ?? $customer->location_url,
            'package_type' => $d['package_type'] ?? $orders->pluck('package_type')->filter()->unique()->implode('، '),
            'parcels' => (int) ($d['parcels'] ?? max(1, $orders->sum(fn ($o) => $o->bags_num ?: 1))),
            'amount' => round($due, 2),
            'fee' => (float) ($d['fee'] ?? 0),
            'fee_payer' => ($d['fee_payer'] ?? 'customer') === 'company' ? 'company' : 'customer',
            'pay_type' => $due <= 0.009 ? 'paid' : ($d['pay_type'] ?? 'cash'),
            'plan_cash' => (float) ($d['plan_cash'] ?? 0),
            'plan_bank' => (float) ($d['plan_bank'] ?? 0),
            'bank_receipt' => $d['bank_receipt'] ?? null,
            'bank_payment_id' => $d['bank_payment_id'] ?? null,
            'note' => $d['note'] ?? null,
            'assigned_at' => $courier ? now() : null,
            'created_id' => auth('web')->id(),
        ]);
        if (!empty($d['area']) && \App\Support\Fast::hasColumn('deliveries', 'area')) {
            $dl->area = mb_substr((string) $d['area'], 0, 120);
            $dl->saveQuietly();
        }
        // 🚚 المدينة مش بنغازي (أو العنوان في مدينة ثانية) → تنزل في طلبيات خارج بنغازي
        if (\App\Support\Fast::hasColumn('deliveries', 'zone') && static::outsideBenghazi($dl->city, $dl->address)) {
            $dl->zone = 'out';
            $dl->saveQuietly();
        }
        foreach ($orders as $o) {
            $dl->orders()->attach($o->id, ['due' => max(0, $o->remaining())]);
            $o->delivery_id = $dl->id;
            $o->save();
        }
        $dl->log($dl->status, $source === 'customer' ? 'العميل طلب توصيل' : 'تعيين للمندوب ' . optional($courier)->name);
        Log::create(['user_id' => auth('web')->id(), 'type' => 'order', 'title' => ' توصيل (' . $customer->name . ')', 'log' => ' ' . $dl->code . ' · ' . $orders->pluck('code')->implode('، ') . ($courier ? ' مع المندوب ' . $courier->name : ' طلب توصيل')]);
        \Illuminate\Support\Facades\Cache::forget('sg.badge.ready');
        if ($courier) {
            try {
                PushService::notifyUser($courier); // إشعار على جوال المندوب
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $dl;
    }

    /** تعيين/تغيير المندوب لتوصيل موجود (مثلا طلب من العميل) */
    public static function setCourier(Delivery $dl, User $courier): void
    {
        $dl->courier_id = $courier->id;
        if ($dl->status === 'requested' || $dl->status === 'problem') {
            $dl->status = 'assigned';
        }
        $dl->assigned_at = now();
        $dl->save();
        $dl->log('assigned', 'تعيين للمندوب ' . $courier->name);
        try {
            PushService::notifyUser($courier);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** مدن برا بنغازي (لو انكتبت في العنوان والمدينة فاضية أو مكتوب فيها بنغازي بالغلط) */
    public const OTHER_CITIES = ['طرابلس', 'مصراتة', 'البيضاء', 'درنة', 'سرت', 'الزاوية', 'سبها', 'طبرق', 'اجدابيا', 'المرج', 'الخمس', 'زليتن', 'غريان',
        'ترهونة', 'الكفرة', 'صبراتة', 'زوارة', 'بني وليد', 'شحات', 'القبة', 'الابيار', 'توكرة', 'سلوق', 'قمينس', 'جالو', 'اوجلة', 'البريقة', 'راس لانوف',
        'هون', 'ودان', 'غات', 'اوباري', 'مرزق', 'يفرن', 'نالوت', 'الجميل', 'العجيلات', 'صرمان', 'جنزور', 'تاجوراء', 'القره بوللي', 'مسلاتة', 'سوسة',
        'امساعد', 'البيضا', 'الجفرة', 'براك', 'الشاطئ', 'رقدالين', 'زلطن', 'الرجبان', 'الزنتان', 'كاباو', 'الاصابعة', 'تيجي', 'بدر', 'العزيزية', 'ككلة',
        'tripoli', 'misrata', 'misurata', 'bayda', 'derna', 'sirte', 'zawiya', 'sabha', 'tobruk', 'ajdabiya', 'marj', 'khoms', 'zliten', 'gharyan'];

    /** العنوان = المنطقة - التفاصيل (لو المنطقة مش مكتوبة فيه) */
    public static function withArea(?string $area, ?string $address): string
    {
        $area = trim((string) $area);
        $address = trim((string) $address);
        if ($area === '' || ($address !== '' && str_contains(static::norm($address), static::norm($area)))) {
            return $address;
        }

        return $address === '' ? $area : $area . ' - ' . $address;
    }

    /** نحفظو مدينة ومنطقة العميل (لو الخانات موجودة) */
    public static function rememberCity(\App\Models\Customer $c, ?string $city, ?string $area): void
    {
        if (!\App\Support\Fast::hasColumn('customers', 'city')) {
            return;
        }
        if (trim((string) $city) !== '') {
            $c->city = trim($city);
        }
        if (trim((string) $area) !== '') {
            $c->area = trim($area);
        }
    }

    protected static function norm(string $s): string
    {
        return str_replace(['ى', 'إ', 'أ', 'آ', 'ة'], ['ي', 'ا', 'ا', 'ا', 'ه'], mb_strtolower(trim($s)));
    }

    /** 🚚 خارج بنغازي؟ المدينة مكتوبة ومش بنغازي، أو العنوان فيه مدينة ثانية — هذي تنزل في «خارج بنغازي» */
    public static function outsideBenghazi(?string $city, ?string $address = null): bool
    {
        $isBgh = fn ($n) => str_contains($n, 'بنغازي') || str_contains($n, 'benghazi') || str_contains($n, 'banghazi');
        $c = static::norm((string) $city);
        if ($c !== '' && !$isBgh($c)) {
            return true;
        }
        $a = static::norm((string) $address);
        if ($a === '' || $isBgh($a)) {
            return false;
        }
        foreach (static::OTHER_CITIES as $other) {
            $o = preg_quote(static::norm($other), '/');
            // كلمة كاملة، ومش اسم شارع (شارع طرابلس / طريق المطار…)
            if (preg_match('/(?<![\p{L}])' . $o . '(?![\p{L}])/u', $a) && !preg_match('/(شارع|طريق|ش\.)\s*' . $o . '/u', $a)) {
                return true;
            }
        }

        return false;
    }

    /** نختارو مندوب أوتوماتيك: متاح، ومنطقته نفس المدينة لو فيه، والأقل شغل توا */
    public static function autoPick(?string $city = null): ?User
    {
        if (!AppSettings::get('couriers_enabled', false)) {
            return null; // 🚫 المناديب موقوفين — الطلب يقعد في واجهة الدليفري
        }
        $q = User::where('type_id', 4)->where('status', true);
        if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'courier_available')) {
            $q->where('courier_available', true);
        }
        $list = $q->get();
        if ($list->isEmpty()) {
            return null;
        }
        $load = Delivery::whereIn('courier_id', $list->pluck('id'))->whereIn('status', ['assigned', 'out'])->selectRaw('courier_id, COUNT(*) n')->groupBy('courier_id')->pluck('n', 'courier_id');
        $city = trim((string) $city);
        $zone = $city !== '' ? $list->filter(fn ($u) => $u->courier_zone && (str_contains($city, $u->courier_zone) || str_contains((string) $u->courier_zone, $city))) : collect();

        return ($zone->isNotEmpty() ? $zone : $list)->sortBy(fn ($u) => (int) ($load[$u->id] ?? 0))->first();
    }

    public static function notifyCustomer(Delivery $dl, string $type): void
    {
        $o = $dl->orders->first();
        if (!$o || !$dl->customer) {
            return;
        }
        MessageService::sendTemplate($type, $dl->customer, MessageService::orderVars($o, [
            '{رقم_الإيصال}' => $dl->orders->pluck('code')->implode('، '),
            '{الباقي}' => TreasuryService::fmt($dl->amount),
            '{المندوب}' => optional($dl->courier)->name,
            '{تليفون_المندوب}' => static::publicPhone($dl->courier),
        ]), $o);
    }

    /** رقم المندوب اللي يشوفه العميل: لو مخفي (عام أو لهذا المندوب) يطلع رقم المحل */
    public static function publicPhone(?User $c): ?string
    {
        if (!$c) {
            return null;
        }
        $hide = AppSettings::get('courier_phone_hidden') || !empty($c->courier_hide_phone);

        return $hide ? (AppSettings::get('shop_phone') ?: '—') : $c->phone;
    }

    /** حجز سلة كاش: المندوب يمشي للعميل يقبض (بدون إيصال، الفلوس تولي رصيد للعميل لين ينعمل الإيصال) */
    public static function cartCollect(\App\Models\CartQuote $cart, float $amount): ?Delivery
    {
        $c = $cart->customer;
        if (!$c || Delivery::where('cart_id', $cart->id)->whereIn('status', ['requested', 'assigned', 'out', 'problem'])->exists()) {
            return null;
        }
        $courier = static::autoPick($c->address);
        $dl = Delivery::create([
            'code' => Delivery::newCode(), 'customer_id' => $c->id, 'courier_id' => optional($courier)->id, 'source' => 'customer',
            'status' => $courier ? 'assigned' : 'requested', 'recipient_name' => $c->name, 'recipient_phone' => $c->phone_1, 'recipient_phone2' => $c->phone_2,
            'city' => $c->city ?? null, 'address' => static::withArea($c->area ?? null, $c->address), 'location_url' => $c->location_url ?? null, 'package_type' => 'تحصيل حجز', 'parcels' => 0,
            'amount' => round($amount, 2), 'fee' => (float) AppSettings::get('courier_default_fee', 10), 'fee_payer' => 'customer', 'pay_type' => 'cash',
            'note' => '💵 تحصيل حجز سلة ' . $cart->retailer_label . ' #' . $cart->id . ' — ما فيش طرد، اقبض المبلغ بس', 'assigned_at' => $courier ? now() : null,
            'cart_id' => $cart->id,
        ]);
        $dl->log($dl->status, 'تحصيل حجز سلة #' . $cart->id . ($courier ? ' مع ' . $courier->name : ''));
        if ($courier) {
            try {
                PushService::notifyUser($courier);
            } catch (\Throwable $e) {
                report($e);
            }
        }
        AlertService::push('request', '🛵 تحصيل حجز سلة #' . $cart->id, $c->name . ' · ' . TreasuryService::fmt($dl->toCollect()) . ' د.ل (السلة ' . TreasuryService::fmt($amount) . ' + توصيل ' . TreasuryService::fmt($dl->customerFee()) . ')' . ($courier ? ' · مع ' . $courier->name : ' · ⚠ ما فيش مندوب متاح، عيّن واحد'), route('sg.dispatch', $courier ? ['id' => $courier->id] : []), $courier ? 'info' : 'warn');

        return $dl;
    }

    /** 🛵 المندوب يمشي يقبض عربون إيصال موجود (ما فيش طرد) */
    public static function orderDeposit(Order $o, float $amount, ?User $courier, float $fee): Delivery
    {
        $c = $o->customer;
        if (!$c) {
            throw new RuntimeException('الإيصال ما عندهش عميل');
        }
        if (Delivery::where('deposit_order_id', $o->id)->whereIn('status', ['requested', 'assigned', 'out', 'problem'])->exists()) {
            throw new RuntimeException('فيه مندوب ماشي يقبض عربون الإيصال هذا من قبل');
        }
        $courier = $courier ?: static::autoPick($c->address);
        $dl = Delivery::create([
            'code' => Delivery::newCode(), 'customer_id' => $c->id, 'courier_id' => optional($courier)->id, 'source' => 'admin',
            'status' => $courier ? 'assigned' : 'requested', 'recipient_name' => $c->name, 'recipient_phone' => $c->phone_1, 'recipient_phone2' => $c->phone_2,
            'city' => $c->city ?? null, 'address' => static::withArea($c->area ?? null, $c->address), 'location_url' => $c->location_url ?? null, 'package_type' => 'تحصيل عربون', 'parcels' => 0,
            'amount' => round($amount, 2), 'fee' => max(0, $fee), 'fee_payer' => 'customer', 'pay_type' => 'cash',
            'note' => '💵 تحصيل عربون إيصال ' . $o->code . ' — ما فيش طرد، اقبض المبلغ بس', 'assigned_at' => $courier ? now() : null,
            'deposit_order_id' => $o->id, 'created_id' => auth('web')->id(),
        ]);
        $dl->log($dl->status, 'تحصيل عربون إيصال ' . $o->code . ($courier ? ' مع ' . $courier->name : ''));
        Log::create(['user_id' => auth('web')->id(), 'type' => 'order', 'title' => ' عربون مع المندوب (' . $o->code . ')', 'log' => ' ' . $dl->code . ' · ' . TreasuryService::fmt($amount) . ' د.ل' . ($courier ? ' مع ' . $courier->name : '')]);
        if ($courier) {
            try {
                PushService::notifyUser($courier);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $dl;
    }

    /** المندوب قبض عربون الإيصال: الدفعة تتسجل على الإيصال + إيصال للعميل */
    protected static function depositPaid(Delivery $dl, float $cash, float $bank): void
    {
        $o = Order::find($dl->deposit_order_id);
        if (!$o) {
            return;
        }
        $fee = min($dl->customerFee(), $cash);
        $cashPart = round(max(0, $cash - $fee), 2);
        if ($cashPart > 0.009) {
            // الكاش مع المندوب: على الإيصال، ويدخل الخزينة وقت إقفال حساب المندوب
            $ph = new PaidHistory;
            $ph->customer_id = $o->customer_id;
            $ph->order_id = $o->id;
            $ph->paid_price = $cashPart;
            $ph->payment_id = AppSettings::cashPaymentId();
            if (\Illuminate\Support\Facades\Schema::hasColumn('paid_histories', 'note')) {
                $ph->note = 'عربون كاش مع المندوب ' . optional($dl->courier)->name;
            }
            $ph->save();
            $o->paid_price = (float) $o->paid_price + $cashPart;
            $o->refreshPaymentStatus();
            $o->save();
        }
        if ($bank > 0.009) {
            $bankId = $dl->bank_payment_id ?: PaymentMethod::where('id', '!=', AppSettings::cashPaymentId())->where('status', true)->value('id');
            if ($bankId) {
                OrderPaymentService::pay($o, $bank, (int) $bankId, 'عربون تحويل (مندوب ' . $dl->code . ')');
            }
        }
        $o->refresh();
        $c = $dl->customer;
        $got = $cashPart + $bank;
        if ($c) {
            $msg = "🧾 *إيصال عربون — Shop & Go*\n\n👤 {$c->name} · كود {$c->code}\n🔢 رقم الإيصال: *{$o->code}*\n"
                . '🛍 قيمة الطلب: ' . TreasuryService::fmt($o->total_price) . " د.ل\n"
                . '✅ دفعت (عربون): ' . TreasuryService::fmt($got) . " د.ل\n"
                . '⏳ الباقي: ' . TreasuryService::fmt(max(0, $o->remaining())) . " د.ل\n"
                . '📅 ' . now()->format('Y-m-d g:i a') . "\n\nشكراً 💙\n\n🧾 إيصالك 👇\n" . route('order.invoice', $o->code);
            try {
                MessageService::sendText('cart_paid', $msg, array_filter([$c->phone_1]), $c, $o);
            } catch (\Throwable $e) {
                report($e);
            }
        }
        AlertService::push('delivered', '💵 ' . optional($dl->courier)->name . ' قبض عربون ' . $o->code, $dl->recipient_name . ' · ' . TreasuryService::fmt($got) . ' د.ل', route('sg.orders.show', $o), 'ok');
    }

    /** 🛵 طلب مندوب من عميل غير مسجل (بدون حساب وبدون إيصال) */
    public static function cartCollectGuest(\App\Models\CartQuote $cart, float $amount, array $d): ?Delivery
    {
        if (Delivery::where('cart_id', $cart->id)->whereIn('status', ['requested', 'assigned', 'out', 'problem'])->exists()) {
            return null;
        }
        $courier = static::autoPick($d['address'] ?? null);
        $dl = Delivery::create([
            'code' => Delivery::newCode(), 'customer_id' => null, 'courier_id' => optional($courier)->id, 'source' => 'customer',
            'status' => $courier ? 'assigned' : 'requested', 'recipient_name' => $d['name'], 'recipient_phone' => $d['phone'],
            'address' => $d['address'] ?? null, 'location_url' => $d['location_url'] ?? null, 'package_type' => 'تحصيل حجز', 'parcels' => 0,
            'amount' => round($amount, 2), 'fee' => (float) AppSettings::get('courier_default_fee', 10), 'fee_payer' => 'customer', 'pay_type' => 'cash',
            'note' => '👤 بدون حساب (ينفتحله أوتوماتيك وقت تقبض) · 💵 تحصيل حجز سلة ' . $cart->retailer_label . ' #' . $cart->id . ' — ما فيش طرد، اقبض المبلغ بس' . (!empty($d['note']) ? ' · ' . $d['note'] : ''),
            'assigned_at' => $courier ? now() : null, 'cart_id' => $cart->id,
        ]);
        $dl->log($dl->status, 'تحصيل حجز سلة #' . $cart->id . ' (عميل غير مسجل)' . ($courier ? ' مع ' . $courier->name : ''));
        if ($courier) {
            try {
                PushService::notifyUser($courier);
            } catch (\Throwable $e) {
                report($e);
            }
        }
        AlertService::push('request', '🛵 طلب مندوب لحجز سلة (عميل بدون حساب)',
            $d['name'] . ' · ' . $d['phone'] . ' · ' . ($d['address'] ?? '') . ' · سلة #' . $cart->id . ' · عربون ' . TreasuryService::fmt($amount) . ' د.ل' . ($courier ? ' · مع ' . $courier->name : ' · ⚠ ما فيش مندوب متاح، عيّن واحد'),
            route('sg.dispatch', $courier ? ['id' => $courier->id] : []), 'warn', true);

        return $dl;
    }

    /** المندوب قبض حجز السلة: ينعمل إيصال في المنظومة أوتوماتيك + إيصال فوري للعميل + تنبيه للإدارة باش تبعت للقروب */
    protected static function cartPaid(Delivery $dl, float $got): void
    {
        $cart = \App\Models\CartQuote::find($dl->cart_id);
        if (!$cart) {
            return;
        }
        $fee = min($dl->customerFee(), $got);
        $forCart = round($got - $fee, 2); // العربون (بعد أجرة التوصيل)
        $order = null;
        try {
            $order = static::orderFromCart($cart, $forCart, $dl);
        } catch (\Throwable $e) {
            report($e);
        }
        $cart->paid_amount = round((float) $cart->paid_amount + $forCart, 2);
        $cart->paid_at = now();
        if ($order && \Illuminate\Support\Facades\Schema::hasColumn('cart_quotes', 'order_id')) {
            $cart->order_id = $order->id;
        }
        $cart->save();
        $c = $dl->customer;
        $newAcc = null;
        if (!$c) {
            // 👤 حجز من غير حساب: نلقوه برقمه، ولو ما عندهش نفتحوله حساب طول، ويتعمل الإيصال أوتوماتيك
            try {
                [$c, $isNew] = \App\Support\GuestAccount::resolve($cart->guest_name ?? $dl->recipient_name, $cart->guest_phone ?? $dl->recipient_phone, $cart->guest_address ?? $dl->address);
                if ($c) {
                    $newAcc = $isNew;
                    $dl->customer_id = $c->id;
                    $dl->save();
                    $cart->customer_id = $c->id;
                    $cart->save();
                    $dl->setRelation('customer', $c);
                    $cart->setRelation('customer', $c);
                    if (!$order) {
                        $order = static::orderFromCart($cart, $forCart, $dl);
                        if ($order && \Illuminate\Support\Facades\Schema::hasColumn('cart_quotes', 'order_id')) {
                            $cart->order_id = $order->id;
                            $cart->save();
                        }
                    } elseif (empty($order->customer_id)) {
                        $order->customer_id = $c->id;
                        $order->save();
                    }
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }
        if ($c) {
            $total = $order ? (float) $order->total_price : null;
            $msg = "✅ *تم تسجيل طلبك في المنظومة* — Shop & Go\n\n"
                . "👤 {$c->name} · كود *{$c->code}* · 📱 {$c->phone_1}\n"
                . ($order ? "🔢 رقم الإيصال: *{$order->code}*\n" : '')
                . "🛒 سلة {$cart->retailer_label} #{$cart->id}\n"
                . ($total !== null ? '🛍 قيمة الطلب: ' . TreasuryService::fmt($total) . " د.ل\n🚚 _الشحن غير محسوب من ضمن هذه التكلفة_\n" : '')
                . '✅ دفعت (عربون): ' . TreasuryService::fmt($forCart) . " د.ل\n"
                . ($total !== null ? '⏳ الباقي وقت الاستلام: ' . TreasuryService::fmt(max(0, $total - $forCart)) . " د.ل\n" : '')
                . '💵 *اللي استلمه المندوب: ' . TreasuryService::fmt($forCart) . " د.ل*\n"
                . '📅 ' . now()->format('Y-m-d g:i a') . ' · المندوب ' . optional($dl->courier)->name . "\n\n"
                . "🛍 بدينا في حجز طلبك، ونبلغوك أول بأول 💙"
                . ($order ? "\n\n🧾 فاتورتك 👇\n" . route('order.invoice', $order->code) : '')
                . ($newAcc !== null ? "\n\n" . \App\Support\GuestAccount::accountText($c, $newAcc) : '');
            try {
                MessageService::sendText('cart_paid', $msg, array_filter([$c->phone_1]), $c, $order);
            } catch (\Throwable $e) {
                report($e);
            }
        }
        if (!$c) {
            // ⚠ عميل غير مسجل: ما ينعملش إيصال أوتوماتيك — نبهو الإدارة تعمله إيصال
            $url = route('sg.orders.create', ['phone' => $dl->recipient_phone, 'name' => $dl->recipient_name, 'total' => $order ? null : null]);
            AlertService::push('request', '💵 المندوب قبض ' . TreasuryService::fmt($got) . ' د.ل من عميل غير مسجل — اعمله إيصال',
                '⚠ غير مسجل · بدون إيصال · ' . $dl->recipient_name . ' · ' . $dl->recipient_phone . ' · سلة #' . $cart->id . ' · عربون ' . TreasuryService::fmt($forCart) . ' د.ل · المندوب ' . optional($dl->courier)->name, $url, 'warn', true);

            return;
        }
        AlertService::push('request', '✅ قبض حجز سلة #' . $cart->id . ' — أكد الحجز', optional($c)->name . ' دفع ' . TreasuryService::fmt($forCart) . ' د.ل عربون' . ($order ? ' · إيصال ' . $order->code : '') . ' · ابعت الرابط والبيانات لقروب الحجز', route('sg.pcarts', ['tab' => 'ordered']), 'warn', true);
    }

    /** إيصال جديد في الطلبات من السلة (بالقطع والكميات اللي اختارها) والعربون مسجل كاش مع المندوب */
    public static function orderFromCart(\App\Models\CartQuote $cart, float $paid, ?Delivery $dl = null): ?Order
    {
        if (!empty($cart->order_id) && ($old = Order::find($cart->order_id))) {
            // الإيصال انعمل وقت التأكيد: الكاش اللي قبضه المندوب يتسجل عليه
            if ($paid > 0.009 && $cart->customer) {
                static::cashOnOrder($old, $cart->customer, $paid, $dl);
            }

            return $old;
        }
        $c = $cart->customer;
        if (!$c) {
            return null;
        }
        // الإيصال بالعدد بس (بدون أسماء المنتجات) واسم الموقع كمنصة
        $qty = 0;
        if ($cart->retailer === 'amazon') {
            $total = (float) data_get($cart->amazon_pricing, 'total.amount', 0);
            $qty = (int) (data_get($cart->amazon_pricing, 'quantity') ?: 1);
        } else {
            $q = app(\App\Services\SheinQuotationCalculator::class)->applyPdfOverrides($cart->quotation, $cart->pdf_overrides);
            foreach ((array) ($q['items'] ?? []) as $it) {
                if ((int) ($it['qty'] ?? 0) > 0 && ($it['availability'] ?? 'available') === 'available' && $it['line_lyd'] !== null) {
                    $qty += (int) $it['qty'];
                }
            }
            $total = (float) data_get($q, 'total.amount', 0);
        }
        if ($total <= 0) {
            return null;
        }
        // المنصة = الموقع اللي شرى منه (بالإنجليزي أو العربي)
        $names = ['shein' => ['shein', 'شي ان', 'شي إن', 'شين'], 'amazon' => ['amazon', 'أمازون', 'امازون'], 'iherb' => ['iherb', 'هيرب']][$cart->retailer] ?? [$cart->retailer];
        $platform = \App\Models\Platform::where(function ($w) use ($names) {
            foreach ($names as $n) {
                $w->orWhereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($n) . '%']);
            }
        })->first() ?? \App\Models\Platform::first();
        $cashId = AppSettings::cashPaymentId();
        $o = new Order;
        $o->code = \App\Services\OrderNumber::next();
        $o->customer_id = $c->id;
        $o->platform_id = optional($platform)->id;
        $o->qty = max(1, $qty);
        $o->total_price = round($total, 2);
        $o->paid_price = 0;
        $o->status = 'unpaid';
        $o->currency = 'dinar';
        $o->currency_sign = 'د.ل';
        $o->payment_id = $cashId;
        $o->note = 'حجز سلة ' . $cart->retailer_label . ' #' . $cart->id;
        $o->created_id = $dl ? $dl->courier_id : auth('web')->id();
        $o->save();
        if ($paid > 0.009) {
            static::cashOnOrder($o, $c, $paid, $dl);
        }
        Log::create(['user_id' => null, 'type' => 'order', 'title' => ' إيصال من حجز سلة (' . $o->code . ')', 'log' => ' ' . $c->name . ' · سلة #' . $cart->id . ' · ' . $total . ' · عربون ' . $paid . ($dl ? ' مع ' . optional($dl->courier)->name : '')]);

        return $o;
    }

    /** الكاش مع المندوب: يتسجل على الإيصال ويدخل الخزينة وقت إقفال حساب المندوب */
    protected static function cashOnOrder(Order $o, Customer $c, float $paid, ?Delivery $dl = null): void
    {
        $cashId = AppSettings::cashPaymentId();
        $take = round(min($paid, max(0, $o->grandTotal() - (float) $o->paid_price)), 2);
        if ($take <= 0.009) {
            $c->credit = (float) $c->credit + round($paid, 2);
            $c->save();

            return;
        }
        $ph = new PaidHistory;
        $ph->customer_id = $c->id;
        $ph->order_id = $o->id;
        $ph->paid_price = $take;
        $ph->payment_id = $cashId;
        // نوع الدفع للمنظومة بس (العميل ما يشوفوش)
        if (\Illuminate\Support\Facades\Schema::hasColumn('paid_histories', 'note')) {
            $ph->note = 'عربون كاش مع المندوب ' . optional(optional($dl)->courier)->name . ' · توصيل ' . optional($dl)->code;
        }
        if ($dl && $dl->courier_id && \Illuminate\Support\Facades\Schema::hasColumn('paid_histories', 'created_id')) {
            $ph->created_id = $dl->courier_id;
        }
        $ph->save();
        $o->paid_price = (float) $o->paid_price + $ph->paid_price;
        $o->refreshPaymentStatus();
        $o->save();
        if ($paid > $ph->paid_price + 0.009) { // دفع أكثر من الطلب → الباقي رصيد
            $c->credit = (float) $c->credit + round($paid - $ph->paid_price, 2);
            $c->save();
        }
    }

    public static function markOut(Delivery $dl): void
    {
        $dl->status = 'out';
        $dl->out_at = now();
        $dl->problem = null;
        $dl->save();
        $dl->log('out', 'المندوب طلع بالطلبية');
        try {
            $dl->loadMissing('orders', 'customer', 'courier');
            static::notifyCustomer($dl, 'courier_out'); // رسالة للعميل: المندوب في الطريق
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** المندوب سلّم: يُسجَّل الدفع وتتقفل الإيصالات */
    public static function markDelivered(Delivery $dl, float $cash, float $bank, ?string $note = null, bool $writeOff = false): void
    {
        if (!$dl->isOpen()) {
            throw new RuntimeException('التوصيل هذا مقفول');
        }
        // قبض أقل من المطلوب بفرق بسيط (مثلاً 220 بدل 221): الفرق يُشطب على الشركة كتخفيض، أو يُحسب على المندوب أو دَيناً على العميل
        $short = round($dl->orders->sum(fn ($o) => max(0, $o->remaining())) + min($dl->customerFee(), $cash) - $cash - $bank, 2);
        if ($writeOff && $short > 0.009) {
            $max = (float) AppSettings::get('courier_writeoff_max', 5);
            if ($short > $max + 0.001) {
                throw new RuntimeException('الفرق (' . TreasuryService::fmt($short) . ' د.ل) أكبر من المسموح بشطبه (' . TreasuryService::fmt($max) . ' د.ل) — سجّلها مشكلة أو كلّم الإدارة');
            }
            $left = $short;
            foreach ($dl->orders->sortByDesc(fn ($o) => $o->remaining()) as $o) {
                if ($left <= 0.009) {
                    break;
                }
                $cut = round(min($left, max(0, $o->remaining())), 2);
                if ($cut <= 0) {
                    continue;
                }
                $o->delivery_discount = (float) $o->delivery_discount + $cut;
                $o->discount = (float) $o->discount + $cut;
                $o->discount_reason = trim(($o->discount_reason ? $o->discount_reason . ' + ' : '') . 'فرق تقريب عند التسليم');
                $o->save();
                $left -= $cut;
            }
            $note = trim(($note ? $note . ' · ' : '') . 'شُطب فرق ' . TreasuryService::fmt($short) . ' د.ل على الشركة');
        }
        $courier = $dl->courier;
        $cashId = AppSettings::cashPaymentId();
        $bankId = $dl->bank_payment_id ?: PaymentMethod::where('id', '!=', $cashId)->where('status', true)->value('id');
        $fee = min($dl->customerFee(), $cash);
        $orderCash = max(0, $cash - $fee);
        $pools = [['cash', $orderCash], ['bank', $bank]];
        foreach ($dl->orders as $o) {
            $need = max(0, $o->remaining());
            foreach ($pools as &$pool) {
                if ($need <= 0.009 || $pool[1] <= 0.009) {
                    continue;
                }
                $take = round(min($need, $pool[1]), 2);
                if ($pool[0] === 'cash') {
                    // الكاش مع المندوب: يُسجَّل على الإيصال، ويدخل الخزينة وقت إقفال الحساب
                    $ph = new PaidHistory;
                    $ph->customer_id = $o->customer_id;
                    $ph->order_id = $o->id;
                    $ph->paid_price = $take;
                    $ph->payment_id = $cashId;
                    $ph->save();
                    $o->paid_price = (float) $o->paid_price + $take;
                    $o->refreshPaymentStatus();
                    $o->save();
                } else {
                    if (!$bankId) {
                        throw new RuntimeException('لا يوجد حساب مصرف مفعل');
                    }
                    OrderPaymentService::pay($o, $take, (int) $bankId, 'تحويل مصرف (توصيل ' . $dl->code . ')');
                    $o->refresh();
                }
                $pool[1] -= $take;
                $need -= $take;
            }
            unset($pool);
            $o->delivered = true;
            $o->delivered_at = now()->toDateString();
            $o->delivered_time = now();
            $o->delivered_by = optional($courier)->id;
            $o->delivery_type = 'توصيل عن طريق مندوب';
            $o->delivery_name = optional($courier)->name;
            $o->save();
        }
        // الزيادة (إذا قبض أكثر) تبقى رصيد للعميل
        $extra = round($pools[0][1] + $pools[1][1], 2);
        if ($extra > 0.009 && $dl->customer && empty($dl->cart_id) && empty($dl->deposit_order_id)) {
            $dl->customer->credit = (float) $dl->customer->credit + $extra;
            $dl->customer->save();
        }
        $dl->collected_cash = $cash;
        $dl->collected_bank = $bank;
        $dl->status = 'delivered';
        $dl->delivered_at = now();
        $dl->problem = null;
        $dl->save();
        $dl->log('delivered', trim('نقداً ' . TreasuryService::fmt($cash) . ' · مصرف ' . TreasuryService::fmt($bank) . ($note ? ' · ' . $note : '')));
        Log::create(['user_id' => auth('web')->id(), 'type' => 'order', 'title' => ' تم التوصيل ' . $dl->code, 'log' => ' ' . optional($courier)->name . ' سلّم ' . $dl->orders->pluck('code')->implode('، ') . ' وقبض نقداً ' . $cash . ' ومصرف ' . $bank]);
        if (!empty($dl->deposit_order_id)) {
            static::depositPaid($dl, $cash, $bank);

            return;
        }
        if (!empty($dl->cart_id)) {
            static::cartPaid($dl, $cash + $bank);

            return;
        }
        $left = $dl->orders->sum(fn ($o) => max(0, $o->fresh()->remaining()));
        AlertService::push('delivered', '✅ ' . optional($courier)->name . ' سلّم ' . $dl->code, $dl->recipient_name . ' · قبض ' . TreasuryService::fmt($cash + $bank) . ' د.ل' . ($left > 0.009 ? ' · باقي ' . TreasuryService::fmt($left) : '') . ($note ? ' · ' . $note : ''), route('sg.dispatch', ['id' => $dl->courier_id]), 'ok');
        static::notifyCustomer($dl->fresh(['orders', 'courier', 'customer']), 'courier_delivered');
    }

    public static function problem(Delivery $dl, string $text): void
    {
        $dl->status = 'problem';
        $dl->problem = $text;
        $dl->save();
        $dl->log('problem', $text);
        AlertService::push('problem', '⚠ مشكلة في توصيل ' . $dl->code, optional($dl->courier)->name . ': ' . $text . ' · ' . $dl->recipient_name . ' ' . $dl->recipient_phone, route('sg.dispatch', ['id' => $dl->courier_id]), 'warn', true);
    }

    /** عادت الطلبية للمحل: الإيصالات ترجع للرف */
    public static function returnToShop(Delivery $dl, string $status = 'returned', ?string $note = null): void
    {
        foreach ($dl->orders as $o) {
            if (!$o->delivered) {
                $o->delivery_id = null;
                $o->save();
            }
        }
        $dl->status = $status;
        $dl->save();
        $dl->log($status, $note);
        \Illuminate\Support\Facades\Cache::forget('sg.badge.ready');
    }

    /** حساب المندوب الآن */
    public static function balance(User $courier): array
    {
        $open = Delivery::where('courier_id', $courier->id)->where('status', 'delivered')->whereNull('settlement_id')->get();
        $cash = (float) $open->sum('collected_cash');
        // المندوب يأخذ أجرته من الكاش الذي قبضه. مكتب التوصيل (أو أجرة على الشركة) تُحسب كاملة حتى لو لم يقبض شيئاً (فيصبح الحساب لصالحه)
        $fees = (float) $open->sum(fn ($d) => $d->courierFee($courier));
        $carry = (float) (CourierSettlement::where('courier_id', $courier->id)->latest('id')->value('diff') ?? 0);
        $pending = Delivery::where('courier_id', $courier->id)->whereIn('status', ['assigned', 'out', 'problem'])->get();

        return [
            'open' => $open, 'count' => $open->count(), 'cash' => $cash, 'bank' => (float) $open->sum('collected_bank'), 'fees' => $fees, 'carry' => $carry,
            'expected' => round($cash - $fees + $carry, 2),
            'pending' => $pending, 'pendingAmount' => $pending->sum(fn ($d) => $d->toCollect()),
        ];
    }

    /** إقفال الحساب مع المندوب */
    public static function settle(User $courier, float $received, float $rounding, int $paymentId, ?string $note = null): CourierSettlement
    {
        $b = static::balance($courier);
        $diff = round($b['expected'] - $received - $rounding, 2);
        $s = CourierSettlement::create([
            'courier_id' => $courier->id, 'deliveries_count' => $b['count'], 'collected_cash' => $b['cash'], 'fees' => $b['fees'],
            'expected' => $b['expected'], 'received' => $received, 'rounding' => $rounding, 'diff' => $diff,
            'payment_id' => $paymentId, 'note' => $note, 'created_id' => auth('web')->id(),
        ]);
        if ($received > 0) {
            TreasuryService::deposit($received, 'dinar', $paymentId, 'courier', $s->id, 'إقفال حساب المندوب ' . $courier->name . ' (' . $b['count'] . ' توصيل)');
        } elseif ($received < 0) {
            TreasuryService::withdraw(-$received, 'dinar', $paymentId, 'courier', $s->id, ($courier->isOffice() ? 'دفعنا لمكتب التوصيل ' : 'دفعنا للمندوب ') . $courier->name);
        }
        Delivery::whereIn('id', $b['open']->pluck('id'))->update(['settlement_id' => $s->id]);
        if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'close_request')) {
            $courier->close_request = null;
            $courier->save();
        }
        Log::create(['user_id' => auth('web')->id(), 'type' => 'Treasury', 'title' => ' إقفال حساب مندوب (' . $courier->name . ')', 'log' => ' المفروض ' . $b['expected'] . ' · استلمنا ' . $received . ' · كسور ' . $rounding . ' · باقي عليه ' . $diff]);

        return $s;
    }
}
