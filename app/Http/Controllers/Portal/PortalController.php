<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\CustomerMessage;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderNotes;
use App\Models\PaymentMethod;
use App\Services\AlertService;
use App\Services\AppSettings;
use App\Services\CourierService;
use App\Services\MessageService;
use App\Services\PointsService;
use App\Services\TreasuryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** حساب العميل: طلباته، كم عليه، نقاطه، رسائل المحل، وطلب توصيل */
class PortalController extends Controller
{
    protected function me()
    {
        return auth('customer')->user();
    }

    protected function readyOrders()
    {
        return Order::where('customer_id', $this->me()->id)->where('arrived', true)->where('delivered', false)
            ->where('status', '!=', 'canceld')->whereNull('delivery_id')->orderBy('arrived_at')->get();
    }

    public const TABS = ['home', 'orders', 'delivery', 'problem', 'account', 'prices', 'addresses', 'settings', 'calc', 'returns', 'faq', 'invite'];

    public function index(Request $request)
    {
        $c = $this->me();
        $tab = in_array($request->tab, self::TABS) ? $request->tab : 'home';
        $orders = Order::with('platform')->where('customer_id', $c->id)->where('status', '!=', 'canceld')->latest()->get();
        $open = $orders->where('delivered', false);
        $notes = OrderNotes::whereIn('order_id', $orders->pluck('id'))->where('visible', true)->latest()->take(20)->get();
        $messages = CustomerMessage::where('customer_id', $c->id)->latest()->take(20)->get();
        $feed = $notes->map(fn ($n) => (object) ['t' => $n->created_at, 'body' => $n->note, 'code' => optional($orders->firstWhere('id', $n->order_id))->code])
            ->concat($messages->map(fn ($m) => (object) ['t' => $m->created_at, 'body' => $m->body, 'code' => null]))->sortByDesc('t')->values();
        $unread = $messages->whereNull('read_at')->count();
        if ($tab === 'home' && $unread) {
            CustomerMessage::where('customer_id', $c->id)->whereNull('read_at')->update(['read_at' => now()]);
        }
        $debts = $orders->filter(fn ($o) => $o->remaining() > 0.009)->values();
        $special = json_decode((string) $c->ship_prices, true) ?: [];

        return view('portal.index', [
            'c' => $c, 'tab' => $tab, 'orders' => $orders, 'open' => $open, 'debts' => $debts,
            'ready' => $this->readyOrders(), 'due' => $debts->sum(fn ($o) => $o->remaining()),
            'feed' => $feed, 'unread' => $unread,
            'deliveries' => Delivery::with(['orders', 'courier'])->where('customer_id', $c->id)->latest()->take(10)->get(),
            'pointsOn' => PointsService::moneyEnabled(), 'couponOn' => PointsService::couponEnabled(), 'pointValue' => PointsService::value(), 'pointsMin' => (int) AppSettings::get('points_min_redeem', 0),
            'settings' => AppSettings::all(),
            'offers' => \Illuminate\Support\Facades\Schema::hasTable('offers') ? \App\Models\Offer::live()->latest()->take(5)->get() : collect(),
            'myReturns' => \Illuminate\Support\Facades\Schema::hasTable('order_returns') ? \App\Models\OrderReturn::with('order')->where('customer_id', $c->id)->latest()->take(30)->get() : collect(),
            'invited' => \Illuminate\Support\Facades\Schema::hasColumn('customers', 'referred_by') ? \App\Models\Customer::withoutGlobalScopes()->where('referred_by', $c->id)->get(['id', 'name', 'referral_rewarded']) : collect(),
            'toRateDl' => \Illuminate\Support\Facades\Schema::hasColumn('deliveries', 'rating') ? Delivery::with('courier')->where('customer_id', $c->id)->where('status', 'delivered')->whereNull('rating')->where('delivered_at', '>=', now()->subDays(7))->latest('delivered_at')->take(2)->get() : collect(),
            'toRate' => \Illuminate\Support\Facades\Schema::hasColumn('orders', 'rating') ? $orders->where('delivered', true)->whereNull('rating')->filter(fn ($o) => $o->delivered_at && \Carbon\Carbon::parse($o->delivered_at)->gte(now()->subDays(14)))->take(3) : collect(),
            'banks' => PaymentMethod::where('status', true)->where('id', '!=', AppSettings::cashPaymentId())->get(),
            'tickets' => \App\Models\SupportTicket::with('order')->where('customer_id', $c->id)->latest()->take(15)->get(),
            'origins' => AppSettings::origins(true), 'special' => $special,
            'platforms' => \App\Models\Platform::visible()->orderBy('id')->get(), 'pfNotes' => (array) AppSettings::get('platform_notes', []),
            'rate' => \App\Services\ShippingService::rate(),
            'offers' => \Illuminate\Support\Facades\Schema::hasTable('offers') ? \App\Models\Offer::live()->latest()->take(5)->get() : collect(),
            'myCoupons' => \Illuminate\Support\Facades\Schema::hasTable('coupons') ? \App\Models\Coupon::where('customer_id', $c->id)->where('active', true)->latest()->take(10)->get() : collect(),
            'couponCost' => (int) AppSettings::get('coupon_points', 100), 'couponPct' => (float) AppSettings::get('coupon_percent', 10),
            'inbox' => $messages,
            'moves' => \App\Models\PaidHistory::with(['paymentMethod', 'order'])->where('customer_id', $c->id)->latest()->take(12)->get()->map(fn ($p) => (object) ['t' => $p->created_at, 'kind' => 'pay', 'title' => 'دفعة · ' . optional($p->order)->code, 'sub' => optional($p->paymentMethod)->name, 'amount' => $p->paid_price])
                ->concat(\App\Models\PointsHistory::where('customer_id', $c->id)->latest()->take(8)->get()->map(fn ($p) => (object) ['t' => $p->created_at, 'kind' => 'pts', 'title' => 'نقاط · ' . ($p->reason ?: ''), 'sub' => '', 'amount' => $p->points]))
                ->sortByDesc('t')->take(14)->values(),
        ]);
    }

    /** تحويل النقاط إلى كوبون خصم */
    public function pointsCoupon()
    {
        try {
            $cp = DB::transaction(fn () => \App\Services\CouponService::fromPoints($this->me()));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('customer.dashboard', ['tab' => 'account'])->with('success', '🎟 كوبونك: ' . $cp->code . ' · خصم ' . $cp->label() . ' · استخدمه عند طلب التوصيل أو أعطه للموظف عند الاستلام');
    }

    /** العميل يرسل مشكلة */
    public function ticket(Request $request)
    {
        $c = $this->me();
        $data = $request->validate([
            'order_id' => 'nullable|integer', 'type' => 'required|in:' . implode(',', array_keys(\App\Models\SupportTicket::TYPES)),
            'message' => 'required|string|max:1500', 'image' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:6144',
        ], ['message.required' => 'اكتب وصف المشكلة', 'type.required' => 'اختر نوع المشكلة']);
        $order = !empty($data['order_id']) ? Order::where('customer_id', $c->id)->find($data['order_id']) : null;
        // بلاغ مكرر: نفس العميل ونفس المشكلة ولسه مفتوح → ما نسجلوش مرة ثانية
        $dup = \App\Models\SupportTicket::where('customer_id', $c->id)->where('type', $data['type'])->where('order_id', optional($order)->id)
            ->where('status', 'open')->where('created_at', '>=', now()->subDays(3))->exists();
        if ($dup) {
            return redirect()->route('customer.dashboard', ['tab' => 'problem'])->with('success', 'بلاغك على نفس المشكلة واصلنا من قبل، وفريقنا يشتغل عليه ✓');
        }
        $t = \App\Models\SupportTicket::create([
            'customer_id' => $c->id, 'order_id' => optional($order)->id, 'type' => $data['type'], 'message' => $data['message'],
            'image' => $request->hasFile('image') ? \App\Support\ImageShrink::store($request->file('image'), 'tickets', 'public') : null, 'status' => 'open',
        ]);
        \Illuminate\Support\Facades\Cache::forget('sg.badge.tickets');
        AlertService::push('ticket', '⚠ بلاغ من ' . $c->name . ' · ' . (\App\Models\SupportTicket::TYPES[$t->type] ?? ''), ($order ? $order->code . ' · ' : '') . \Illuminate\Support\Str::limit($t->message, 120), route('sg.tickets'), 'warn', true);

        return redirect()->route('customer.dashboard', ['tab' => 'problem'])->with('success', 'تم إرسال بلاغك، وسيرد عليك فريقنا قريباً');
    }

    /** العميل يسجل رقم تتبع قبل وصول الشحنة للمخزن */
    public function tracking(Request $request)
    {
        $c = $this->me();
        $data = $request->validate(['origin' => 'required|string|max:10', 'tracking_no' => 'required|string|max:80', 'note' => 'nullable|string|max:200'], ['tracking_no.required' => 'اكتب رقم التتبع']);
        if (Order::where('customer_id', $c->id)->where('tracking_no', $data['tracking_no'])->exists()) {
            return back()->with('error', 'رقم التتبع هذا مسجل مسبقاً');
        }
        try {
            $o = \App\Services\ShippingService::save(null, $c, ['origin' => $data['origin'], 'tracking_no' => $data['tracking_no'], 'note' => $data['note'] ?? null, 'stage' => 'expected', 'bags' => 1]);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        AlertService::push('ship_expected', '📦 ' . $c->name . ' سجّل شحنة قادمة', $o->code . ' · ' . $data['tracking_no'], route('sg.shipping', ['stage' => 'expected']), 'info');

        return redirect()->route('customer.dashboard', ['tab' => 'orders'])->with('success', 'تم تسجيل رقم التتبع ' . $data['tracking_no'] . '، وسنبلغك عند وصول الشحنة للمخزن');
    }

    /** تقييم طلبية بعد استلامها */
    public function rate(Request $request, int $id)
    {
        $data = $request->validate(['rating' => 'required|integer|min:1|max:5', 'note' => 'nullable|string|max:500']);
        $o = Order::where('customer_id', $this->me()->id)->where('delivered', true)->findOrFail($id);
        $o->rating = $data['rating'];
        $o->rating_note = $data['note'] ?? null;
        $o->rated_at = now();
        $o->save();
        if ($data['rating'] <= 2) {
            AlertService::push('low_rating', '⭐ تقييم منخفض من ' . $this->me()->name, $o->code . ' · ' . $data['rating'] . '/5 ' . ($data['note'] ?? ''), route('sg.orders.show', $o), 'warning');
        }

        return back()->with('success', 'شكراً على تقييمك 🙏');
    }

    /** تقييم المندوب بعد التوصيل */
    public function rateDelivery(Request $request, int $id)
    {
        $data = $request->validate(['rating' => 'required|integer|min:1|max:5', 'note' => 'nullable|string|max:500']);
        $d = Delivery::where('customer_id', $this->me()->id)->where('status', 'delivered')->findOrFail($id);
        $d->rating = $data['rating'];
        $d->rating_note = $data['note'] ?? null;
        $d->rated_at = now();
        $d->save();
        if ($data['rating'] <= 2) {
            AlertService::push('low_rating', '🛵 تقييم منخفض للمندوب ' . optional($d->courier)->name, $d->code . ' · ' . $data['rating'] . '/5 · ' . ($data['note'] ?? ''), route('sg.couriers'), 'warning');
        }

        return back()->with('success', 'شكراً على تقييمك 🙏');
    }

    /** إعدادات العميل: العنوان والموقع والهاتف الثاني */
    public function profile(Request $request)
    {
        $c = $this->me();
        $data = $request->validate(['address' => 'nullable|string|max:250', 'location_url' => 'nullable|string|max:500', 'phone_2' => 'nullable|string|max:30']);
        $c->fill(array_filter($data, fn ($v) => $v !== null))->save();

        return back()->with('success', 'تم حفظ بياناتك');
    }

    /** كشف حساب للطباعة / حفظ PDF */
    public function statement()
    {
        $c = $this->me();
        $orders = Order::where('customer_id', $c->id)->where('status', '!=', 'canceld')->orderBy('created_at')->get();

        return view('portal.statement', ['c' => $c, 'orders' => $orders, 'pays' => \App\Models\PaidHistory::with('paymentMethod', 'order')->where('customer_id', $c->id)->orderBy('created_at')->get(), 'settings' => AppSettings::all()]);
    }

    /** العميل يطلب توصيل */
    public function requestDelivery(Request $request)
    {
        abort_unless(AppSettings::get('portal_delivery_enabled', true), 403);
        $c = $this->me();
        $data = $request->validate([
            'order_ids' => 'required|array|min:1', 'order_ids.*' => 'integer',
            'recipient_name' => 'required|string|max:120', 'recipient_phone' => 'required|string|max:30', 'recipient_phone2' => 'nullable|string|max:30',
            'city' => 'nullable|string|max:80', 'address' => 'required|string|max:250', 'location_url' => 'nullable|string|max:500',
            'plan_cash' => 'nullable|numeric|min:0', 'plan_bank' => 'nullable|numeric|min:0', 'bank_payment_id' => 'nullable|exists:payment_methods,id',
            'receipt' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:6144', 'note' => 'nullable|string|max:500', 'use_points' => 'nullable|boolean',
            'coupon_code' => 'nullable|string|max:30',
        ], [
            'order_ids.required' => 'اختر الطلبيات التي تريد توصيلها', 'address.required' => 'اكتب العنوان', 'recipient_phone.required' => 'اكتب رقم هاتف المستلم',
        ]);
        $orders = $this->readyOrders()->whereIn('id', array_map('intval', $data['order_ids']))->values();
        if ($orders->isEmpty()) {
            return back()->with('error', 'هذه الطلبيات ليست جاهزة للتوصيل');
        }
        DB::beginTransaction();
        try {
            if ($request->boolean('use_points') && PointsService::moneyEnabled()) {
                $left = (int) $c->points;
                foreach ($orders as $o) {
                    if ($left <= 0) {
                        break;
                    }
                    $o->setRelation('customer', $c);
                    $v = PointsService::redeem($o, $left);
                    $left -= (int) ceil($v / max(0.0001, PointsService::value()));
                    $c->refresh();
                }
                $orders = $orders->map->fresh();
            }
            // كوبون الخصم: يُطبَّق على أول طلبية عليها مبلغ
            if (!empty($data['coupon_code'])) {
                $target = $orders->first(fn ($o) => $o->remaining() > 0.009);
                if (!$target) {
                    throw new \RuntimeException('لا يوجد مبلغ متبقٍ لتطبيق الكوبون');
                }
                \App\Services\CouponService::apply($target, $data['coupon_code']);
                $orders = $orders->map->fresh();
            }
            $plan = (float) ($data['plan_cash'] ?? 0) + (float) ($data['plan_bank'] ?? 0);
            $dl = CourierService::assign($c, $orders, [
                'recipient_name' => $data['recipient_name'], 'recipient_phone' => $data['recipient_phone'], 'recipient_phone2' => $data['recipient_phone2'] ?? null,
                'city' => $data['city'] ?? null, 'address' => $data['address'], 'location_url' => $data['location_url'] ?? null,
                'plan_cash' => $data['plan_cash'] ?? 0, 'plan_bank' => $data['plan_bank'] ?? 0, 'bank_payment_id' => $data['bank_payment_id'] ?? null,
                'pay_type' => ($data['plan_bank'] ?? 0) > 0 ? (($data['plan_cash'] ?? 0) > 0 ? 'mixed' : 'bank') : 'cash',
                'fee' => AppSettings::get('courier_default_fee', 0), 'note' => $data['note'] ?? null,
                'bank_receipt' => $request->hasFile('receipt') ? \App\Support\ImageShrink::store($request->file('receipt'), 'receipts', 'public') : null,
            ], null, 'customer');
            if (!$c->address) {
                $c->address = $data['address'];
            }
            if (!empty($data['location_url'])) {
                $c->location_url = $data['location_url'];
            }
            $c->save();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()->withInput()->with('error', $e instanceof \RuntimeException ? $e->getMessage() : 'حدث خطأ، يرجى المحاولة مرة أخرى');
        }
        // إسناد أوتوماتيك لمندوب متاح (الأقرب منطقة والأقل شغل) — يوصله إشعار طول
        $auto = null;
        if (AppSettings::get('courier_auto_assign', true)) {
            try {
                if ($auto = CourierService::autoPick($data['city'] ?? $data['address'])) {
                    CourierService::setCourier($dl, $auto);
                    CourierService::notifyCustomer($dl->fresh(['orders', 'courier', 'customer']), 'courier_assigned');
                }
            } catch (\Throwable $e) {
                report($e);
                $auto = null;
            }
        }
        AlertService::push('request', '🛵 طلب توصيل من ' . $c->name, $orders->pluck('code')->implode('، ') . ' · ' . $data['address'] . ' · نقداً ' . TreasuryService::fmt($data['plan_cash'] ?? 0) . ' / مصرف ' . TreasuryService::fmt($data['plan_bank'] ?? 0), route('sg.couriers'), 'info', true);
        MessageService::sendTemplate('delivery_request', $c, MessageService::orderVars($orders->first(), ['{رقم_الإيصال}' => $orders->pluck('code')->implode('، ')]), $orders->first());

        return redirect()->route('customer.dashboard', ['tab' => 'delivery'])->with('success', 'تم استلام طلب التوصيل ' . $dl->code . ' ✅ ' . ($auto ? 'المندوب ' . $auto->name . ' بيتواصل معاك' : 'سيتم إسناده لمندوب والتواصل معك'));
    }

    public function cancelRequest(int $id)
    {
        $d = Delivery::where('customer_id', $this->me()->id)->where('status', 'requested')->findOrFail($id);
        CourierService::returnToShop($d->load('orders'), 'cancelled', 'ألغى العميل الطلب');

        return back()->with('success', 'تم إلغاء طلب التوصيل');
    }

    /** الاسم ناقص أو غلط (نقطة، حرف واحد، أرقام) */
    public static function badName(?string $n): bool
    {
        $n = trim((string) $n);

        return preg_match_all('/\p{L}/u', $n) < 3;
    }

    /** العميل يكتب اسمه (إجباري لو اسمه غلط) */
    public function saveName(Request $request)
    {
        $c = $this->me();
        $request->validate(['name' => 'required|string|max:60'], ['name.required' => 'اكتب اسمك']);
        $name = trim(preg_replace('/\s+/u', ' ', $request->name));
        if (static::badName($name) || preg_match('/\d/', $name)) {
            return back()->with('error', 'اكتب اسمك الحقيقي بالحروف (مثلاً: محمد علي)');
        }
        $c->name = $name;
        $c->save();

        return back()->with('success', 'تم حفظ اسمك ✓ أهلاً ' . $name);
    }

    public function password(Request $request)
    {
        $c = $this->me();
        $request->validate(['current' => 'required', 'new' => 'required|min:8|confirmed'], ['new.min' => 'كلمة المرور 8 خانات على الأقل', 'new.confirmed' => 'تأكيد كلمة المرور غير مطابق']);
        if (!Hash::check($request->current, $c->password)) {
            return back()->with('error', 'كلمة المرور الحالية غير صحيحة');
        }
        $c->password = Hash::make($request->new);
        $c->save();

        return back()->with('success', 'تم تغيير كلمة المرور ✓');
    }
}
