<?php

namespace App\Http\Controllers\Sg;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerMessage;
use App\Models\Log;
use App\Models\Order;
use App\Models\PaidHistory;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\AppSettings;
use App\Services\CourierService;
use App\Services\PointsService;
use App\Services\MessageService;
use App\Services\OrderPaymentService;
use App\Services\Period;
use App\Services\ShiftService;
use App\Services\TreasuryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** تسليم الطلبيات للعميل وقبض الباقي */
class DeliveryController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:delivery-list', ['only' => ['index', 'done', 'slip']]);
        $this->middleware('permission:delivery-create', ['only' => ['store']]);
        $this->middleware('permission:delivery-undo', ['only' => ['undo']]);
        $this->middleware('permission:delivery-list', ['only' => ['suggest']]);
        $this->middleware('permission:shelf-list|shelf-edit|delivery-list', ['only' => ['audit']]);
        $this->middleware('permission:shelf-edit|delivery-list', ['only' => ['remindShelf']]);
    }

    protected function readyQuery()
    {
        return Order::where('arrived', true)->where('delivered', false)->where('status', '!=', 'canceld');
    }

    /** أي طلبية مش مستلمة ومش ملغاة (ومش مع مندوب توا — لو التوصيل القديم تلغى أو رجع تطلع عادي) */
    protected function openQuery()
    {
        return Order::where('delivered', false)->where('status', '!=', 'canceld');
    }

    protected function freeOfCourier($q)
    {
        $active = \App\Models\Delivery::whereIn('status', ['requested', 'assigned', 'out', 'problem'])->pluck('id');

        return $q->where(fn ($w) => $w->whereNull('delivery_id')->orWhereNotIn('delivery_id', $active));
    }

    public function index(Request $request)
    {
        $customer = null;
        $q = trim((string) $request->q);
        $matches = collect();
        if ($q !== '') {
            // 🔍 كود العميل · رقم هاتفه · رقم الإيصال · اسمه — حتى الحسابات اللي لسه ما تفعلتش
            $C = fn () => Customer::withoutGlobalScope('approved');
            $digits = preg_replace('/\D/', '', $q);
            $hit = Order::where('code', $q)->first();
            if ($hit && !$request->filled('pick')) {
                $request->merge(['pick' => $hit->id]); // رقم إيصال: يتختار طول
            }
            // 🔢 كود بجزء منه: 6823 يطلع 6823 و 76823 وأي كود فيه الأرقام هذي
            if (ctype_digit($q)) {
                $matches = $C()->where('code', 'like', '%' . \App\Support\Search::esc($q) . '%')
                    ->withCount(['orders as open_n' => fn ($o) => $o->where('delivered', false)->where('status', '!=', 'canceld')])
                    ->orderByRaw('CASE WHEN code = ? THEN 0 ELSE 1 END', [$q])->orderByDesc('open_n')->orderBy('code')->limit(15)->get(['id', 'name', 'code', 'phone_1']);
                if ($hit && !$matches->contains('id', $hit->customer_id) && ($hc = $C()->find($hit->customer_id, ['id', 'name', 'code', 'phone_1']))) {
                    $matches->prepend($hc); // صاحب الإيصال اللي رقمه نفس البحث
                }
            }
            $customer = ($request->filled('cid') ? $C()->find((int) $request->cid) : null)
                ?? ($hit ? $C()->find($hit->customer_id) : null)
                ?? ($matches->count() ? $matches->first() : null)
                ?? ($digits && strlen($digits) >= 6 ? $C()->where(fn ($w) => $w->where('phone_1', 'like', '%' . substr($digits, -9))->orWhere('phone_2', 'like', '%' . substr($digits, -9)))->first() : null)
                ?? (($oh = Order::where('code', 'like', '%' . $q)->first()) ? $C()->find($oh->customer_id) : null);
            if (!$customer && mb_strlen($q) >= 2 && !$matches->count()) {
                // اللي عنده طلبيات ما تسلمتش يطلع الأول
                $matches = $C()->where('name', 'like', "%$q%")
                    ->withCount(['orders as open_n' => fn ($o) => $o->where('delivered', false)->where('status', '!=', 'canceld')])
                    ->orderByDesc('open_n')->orderBy('name')->limit(15)->get(['id', 'name', 'code', 'phone_1']);
                $customer = $matches->first();
            }
        }
        // 🔍 لو بحثت: تطلع كل طلبياته المفتوحة (حتى اللي مش جاهزة) وتسلّمها عادي
        $showAll = $request->has('all') ? $request->boolean('all') : $q !== '';
        $ready = $customer ? $this->freeOfCourier($showAll ? $this->openQuery() : clone $this->readyQuery())->where('customer_id', $customer->id)->orderByDesc('arrived')->orderByDesc('created_at')->orderByDesc('id')->get() : collect(); // الجاهز فوق، وبعدين الجديد
        $withCourier = $customer ? \App\Models\Delivery::with('courier', 'orders')->where('customer_id', $customer->id)->whereIn('status', ['requested', 'assigned', 'out', 'problem'])->get() : collect();
        $delivered = $customer ? Order::where('customer_id', $customer->id)->where('delivered', true)->where('status', '!=', 'canceld')->orderByDesc('delivered_time')->orderByDesc('id')->get() : collect();
        $later = $customer && !$showAll ? Order::where('customer_id', $customer->id)->where('arrived', false)->where('delivered', false)->where('status', '!=', 'canceld')->get() : collect();
        $due = $customer ? Order::where('customer_id', $customer->id)->where('status', '!=', 'canceld')->get()->sum(fn ($o) => max(0, $o->remaining())) : 0;
        $queue = (clone $this->readyQuery())->whereNull('delivery_id')->with('customer')->orderBy('arrived_at')->get()->groupBy('customer_id');
        $cashId = AppSettings::cashPaymentId();

        return view('sg.delivery.index', [
            'nav' => 'del', 'q' => $q, 'customer' => $customer, 'matches' => $matches,
            // ⚠ المشاكل المفتوحة على إيصالاته (تطلع في بطاقة كل إيصال ونقدر نخصمها وقت التسليم)
            'missing' => $this->missingFor($ready),
            'issues' => $ready->isNotEmpty() && \Illuminate\Support\Facades\Schema::hasTable('order_issues') ? \App\Models\OrderIssue::whereIn('order_id', $ready->pluck('id'))->where('status', 'open')->where('type', '!=', 'missing')->get()->groupBy('order_id') : collect(), 'delivered' => $delivered, 'showAll' => $showAll, 'ready' => $ready, 'later' => $later, 'due' => $due,
            'queue' => $queue->take(30), 'queueMore' => max(0, $queue->count() - 30),
            'todayCount' => Order::where('delivered', true)->whereDate('delivered_at', now()->toDateString())->count(),
            'custOrders' => $customer ? Order::where('customer_id', $customer->id)->count() : 0,
            'cashId' => $cashId, 'banks' => PaymentMethod::where('status', true)->where('id', '!=', $cashId)->get(),
            'withCourier' => $withCourier, 'couriers' => User::where('type_id', 4)->where('status', true)->orderBy('name')->get(),
            'pointsOn' => PointsService::moneyEnabled(), 'couponOn' => PointsService::couponEnabled(), 'pointValue' => PointsService::value(), 'pointsMin' => (int) AppSettings::get('points_min_redeem', 0),
            'courierFee' => (float) AppSettings::get('courier_default_fee', 0),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'order_ids' => 'required|array|min:1',
            'order_ids.*' => 'integer|exists:orders,id',
            'rows' => 'nullable|array',
            'rows.*.qty' => 'nullable|integer|min:0',
            'rows.*.missing_discount' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'discount_reason' => 'nullable|string|max:150',
            'points' => 'nullable|integer|min:0',
            'cash' => 'nullable|numeric|min:0',
            'bank' => 'nullable|numeric|min:0',
            'bank_id' => 'nullable|exists:payment_methods,id',
            'credit' => 'nullable|numeric|min:0',
            'delivery_type' => 'required|in:توصيل شخصي,توصيل عن طريق مندوب,توصيل عن طريق مكتب',
            'delivery_name' => 'nullable|string|max:100',
            // المندوب
            'courier_id' => 'nullable|exists:users,id',
            'recipient_name' => 'nullable|string|max:120',
            'recipient_phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:250',
            'location_url' => 'nullable|string|max:500',
            'fee' => 'nullable|numeric|min:0',
            'fee_payer' => 'nullable|in:customer,company',
            'pay_type' => 'nullable|in:cash,bank,mixed,paid',
            'courier_note' => 'nullable|string|max:500',
            'customer_note' => 'nullable|string|max:500',
            'send_whatsapp' => 'nullable|boolean',
            'print' => 'nullable|boolean',
            'stay' => 'nullable|boolean',
            'embed' => 'nullable|boolean',
            'ship_pay' => 'nullable|array', 'ship_pay.*' => 'nullable|in:cash,bank',
            'issue_deduct' => 'nullable|array', 'issue_deduct.*' => 'nullable|numeric|min:0',
            'miss_deduct' => 'nullable|array', 'miss_deduct.*' => 'nullable|in:1',
            'miss_val' => 'nullable|array', 'miss_val.*' => 'nullable|numeric|min:0',
        ], ['order_ids.required' => 'اختر الإيصالات التي بيستلمها']);

        $customer = Customer::withoutGlobalScope('approved')->findOrFail($data['customer_id']);
        // نقدر نسلّم حتى الطلبيات اللي مش مسجلة جاهزة
        $orders = $this->freeOfCourier($this->openQuery())->where('customer_id', $customer->id)->whereIn('id', $data['order_ids'])->orderBy('arrived_at')->get();
        if ($orders->isEmpty()) {
            return back()->with('error', 'الإيصالات هذه غير جاهزة للتسليم');
        }
        // 🏢 مكتب توصيل (من حسابات المكاتب): الطلبية تتسلم للمكتب طول، والمبلغ يتسجل عليه لين نقفلو الحساب معاه
        $office = $data['delivery_type'] === 'توصيل عن طريق مكتب' && !empty($data['courier_id']) ? User::where('type_id', 4)->find($data['courier_id']) : null;
        $office = $office && $office->isOffice() ? $office : null;
        $viaCourier = ($data['delivery_type'] === 'توصيل عن طريق مندوب' && !empty($data['courier_id'])) || $office;
        $cashId = AppSettings::cashPaymentId();

        DB::beginTransaction();
        try {
            ShiftService::ensureOpen(auth()->user());

            // 💵/🏦 شحنات بالدولار: الموظف يختار سعر الكاش ولا المصرف (أو يدفع دولار ويختار الأنسب) — يتثبت قبل حساب المستحق
            foreach ((array) ($data['ship_pay'] ?? []) as $oid => $pay) {
                $o = $orders->firstWhere('id', (int) $oid);
                if ($o && \App\Services\ShippingService::isDual($o) && $o->ship_pay !== $pay) {
                    $before = (float) $o->ship_price;
                    \App\Services\ShippingService::applyPay($o, $pay);
                    $o->save();
                    Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ' سعر الشحن وقت التسليم (' . $o->code . ')',
                        'log' => ' ' . ($pay === 'bank' ? 'مصرف' : 'كاش') . ': من ' . $before . ' إلى ' . $o->ship_price . ' د.ل']);
                }
            }
            $orders = $orders->map->fresh();

            // 0) 📉 النواقص المسجلة (ما تقررش فيها) واخترنا «خصم النواقص»: تنخصم من الإيصال
            // النواقص اللي سعرها مش متسجل: الموظف يكتب قيمتها وقت التسليم وتنخصم
            foreach ((array) ($data['miss_val'] ?? []) as $oid => $mv) {
                $o = $orders->firstWhere('id', (int) $oid);
                if ($o && (float) $mv > 0.009 && (int) $o->missing_qty > 0 && !$o->missing_action) {
                    $o->missing_value = round((float) $mv, 2);
                    $o->save();
                    $data['miss_deduct'][$oid] = '1';
                } elseif ($o && (float) $mv > 0.009) {
                    // نواقص مضافة من الزر (من غير سعر): القيمة اللي كتبها الموظف تنخصم على أول وحدة
                    $first = \App\Models\OrderIssue::where('order_id', $o->id)->where('type', 'missing')->where('status', 'open')->first();
                    if ($first) {
                        $first->value = round((float) $mv, 2);
                        $first->save();
                        $data['miss_deduct'][$oid] = '1';
                    }
                }
            }
            foreach (array_keys((array) ($data['miss_deduct'] ?? [])) as $oid) {
                $o = $orders->firstWhere('id', (int) $oid);
                if (!$o) {
                    continue;
                }
                if ((int) $o->missing_qty > 0 && !$o->missing_action && (float) $o->missing_value > 0) {
                    $o->discount = (float) $o->discount + (float) $o->missing_value;
                    $o->discount_reason = trim(($o->discount_reason ? $o->discount_reason . ' + ' : '') . 'قطع ناقصة (وقت التسليم)');
                    $o->missing_action = 'deduct';
                    $o->refreshPaymentStatus();
                    $o->save();
                }
                if (\Illuminate\Support\Facades\Schema::hasTable('order_soldouts')) {
                    foreach (\App\Models\OrderSoldout::where('order_id', $o->id)->where('status', 'pending')->get() as $so) {
                        \App\Services\SoldoutService::cancel($so, 'credit');
                    }
                }
                foreach (\App\Models\OrderIssue::where('order_id', $o->id)->where('type', 'missing')->where('status', 'open')->get() as $mi) {
                    \App\Services\IssueService::resolve($mi, ['value' => (float) $mi->value, 'action' => (float) $mi->value > 0 ? 'deduct' : 'later', 'resolution' => 'خصم وقت التسليم']);
                }
                Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ' خصم نواقص وقت التسليم (' . $o->code . ')', 'log' => ' تخصمت النواقص من الإيصال']);
            }
            $orders = $orders->map->fresh();

            // 0) ⚠ مشكلة مفتوحة على الإيصال واخترنا نخصمها: تنخصم من الإيصال وتتقفل المشكلة
            foreach ((array) ($data['issue_deduct'] ?? []) as $isId => $val) {
                if ((float) $val <= 0.009) {
                    continue;
                }
                $is = \App\Models\OrderIssue::where('status', 'open')->whereIn('order_id', $orders->pluck('id'))->find((int) $isId);
                if ($is) {
                    \App\Services\IssueService::resolve($is, ['value' => (float) $val, 'action' => 'deduct', 'resolution' => 'خصم وقت التسليم']);
                }
            }
            $orders = $orders->map->fresh();

            // 1) قطع ناقصة: نسلّم عدد أقل ونخصم قيمتها
            foreach ($orders as $o) {
                $row = $data['rows'][$o->id] ?? [];
                $have = (int) ($o->received_qty ?? $o->qty);
                $qty = isset($row['qty']) && $row['qty'] !== '' ? min((int) $row['qty'], $have) : $have;
                $o->delivered_qty = $qty;
                $md = round((float) ($row['missing_discount'] ?? 0), 2);
                if ($qty < (int) $o->qty && $md > 0) {
                    $md = min($md, max(0, $o->remaining()) + (float) $o->paid_price);
                    $o->missing_qty = (int) $o->qty - $qty;
                    $o->missing_value = (float) $o->missing_value + $md;
                    $o->missing_action = 'deduct';
                    $o->discount = (float) $o->discount + $md;
                    $o->refreshPaymentStatus();
                    Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ' قطع ناقصة (' . $o->code . ')', 'log' => ' سلّمنا ' . $qty . ' من ' . $o->qty . ' وخصمنا ' . $md . ' د.ل']);
                }
                $o->save();
            }

            // 2) تخفيض وقت التسليم (يتوزع على الإيصالات بالترتيب)
            $disc = round((float) ($data['discount'] ?? 0), 2);
            if ($disc > 0) {
                $left = $disc;
                foreach ($orders as $o) {
                    $take = round(min($left, max(0, $o->remaining())), 2);
                    if ($take <= 0) {
                        continue;
                    }
                    $o->delivery_discount = (float) $o->delivery_discount + $take;
                    $o->discount = (float) $o->discount + $take;
                    $o->discount_reason_note = trim(($o->discount_reason_note ? $o->discount_reason_note . ' · ' : '') . 'تخفيض وقت التسليم' . (!empty($data['discount_reason']) ? ': ' . $data['discount_reason'] : ''));
                    $o->refreshPaymentStatus();
                    $o->save();
                    $left -= $take;
                }
                Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ' تخفيض وقت التسليم (' . $customer->name . ')', 'log' => ' تخفيض ' . ($disc - $left) . ' د.ل ' . ($data['discount_reason'] ?? '')]);
            }

            // 3) نقاط
            $ptsVal = 0;
            if (!empty($data['points']) && PointsService::moneyEnabled()) {
                $want = (int) $data['points'];
                foreach ($orders as $o) {
                    if ($want <= 0) {
                        break;
                    }
                    $o->setRelation('customer', $customer);
                    $v = PointsService::redeem($o, $want);
                    $ptsVal += $v;
                    $want -= (int) ceil($v / max(0.0001, PointsService::value()));
                    $customer->refresh();
                }
            }
            $orders = $orders->map->fresh();

            if ($viaCourier) {
                // 4أ) مع المندوب: لو دفع حاجة توا (كاش/مصرف) تتسجل، والمندوب يقبض الباقي بس
                $courier = User::where('type_id', 4)->findOrFail($data['courier_id']);
                $pc = (float) ($data['cash'] ?? 0);
                $pb = (float) ($data['bank'] ?? 0);
                $pr = min((float) ($data['credit'] ?? 0), (float) $customer->credit);
                if ($pc + $pb + $pr > 0.009) {
                    $due0 = $orders->sum(fn ($o) => max(0, $o->remaining()));
                    if ($pc + $pb + $pr > $due0 + 0.01) {
                        throw new \RuntimeException('المدفوع (' . TreasuryService::fmt($pc + $pb + $pr) . ') أكبر من المطلوب (' . TreasuryService::fmt($due0) . ')');
                    }
                    if ($pb > 0 && empty($data['bank_id'])) {
                        throw new \RuntimeException('اختر حساب المصرف');
                    }
                    $this->payPools($orders, $customer, $pr, $pc, $pb, $data['bank_id'] ?? null, $cashId);
                    $customer->save();
                    $orders = $orders->map->fresh();
                }
                $dl = CourierService::assign($customer, $orders, [
                    'recipient_name' => $data['recipient_name'] ?? null, 'recipient_phone' => $data['recipient_phone'] ?? null,
                    'address' => $data['address'] ?? null, 'location_url' => $data['location_url'] ?? null,
                    'fee' => $data['fee'] ?? AppSettings::get('courier_default_fee', 0), 'fee_payer' => $data['fee_payer'] ?? 'customer', 'pay_type' => $data['pay_type'] ?? 'cash',
                    'note' => $data['courier_note'] ?? null, 'bank_payment_id' => $data['bank_id'] ?? null,
                ], $courier);
                if (!empty($data['address']) && !$customer->address) {
                    $customer->address = $data['address'];
                }
                if (!empty($data['location_url'])) {
                    $customer->location_url = $data['location_url'];
                }
                $customer->save();
                if ($office) {
                    // تم التسليم للمكتب: الإيصالات «مستلمة»، والمكتب عليه المبلغ (يظهر في حسابه ونقفلوه بتسوية)
                    $due = $dl->toCollect();
                    CourierService::markDelivered($dl->fresh(['orders', 'courier', 'customer']), $due, 0, 'تسليم لمكتب التوصيل ' . $office->name);
                    foreach ($dl->orders()->get() as $oo) {
                        $oo->delivery_type = 'توصيل عن طريق مكتب';
                        $oo->delivery_name = $office->name;
                        $oo->save();
                    }
                    DB::commit();
                    \Illuminate\Support\Facades\Cache::forget('sg.badge.ready');
                    if ($request->boolean('print')) {
                        session()->flash('print_url', route('sg.couriers.label', $dl));
                    }

                    return $this->finish($request, '🏢 تسلّمت ' . $dl->orders()->count() . ' طلبية لمكتب ' . $office->name . ' · عليه ' . TreasuryService::fmt($due) . ' د.ل (تلقاها في حسابه في المندوبين)');
                }
                DB::commit();
                if ($request->boolean('send_whatsapp')) {
                    CourierService::notifyCustomer($dl->fresh(['orders', 'courier', 'customer']), 'courier_assigned');
                }
                if ($request->boolean('print')) {
                    session()->flash('print_url', route('sg.couriers.label', $dl));
                }

                return $this->finish($request, 'الطلبية ' . $dl->code . ' مع المندوب ' . $courier->name . ' · يقبض ' . TreasuryService::fmt($dl->toCollect()) . ' د.ل' . ($disc ? ' · تخفيض ' . TreasuryService::fmt($disc) : ''));
            }

            // 4ب) يستلم بنفسه / مكتب: نقبض الآن
            $cash = (float) ($data['cash'] ?? 0);
            $bank = (float) ($data['bank'] ?? 0);
            $credit = min((float) ($data['credit'] ?? 0), (float) $customer->credit);
            $due = $orders->sum(fn ($o) => max(0, $o->remaining()));
            if ($cash + $bank + $credit > $due + 0.01) {
                throw new \RuntimeException('المدفوع (' . TreasuryService::fmt($cash + $bank + $credit) . ') أكبر من المطلوب (' . TreasuryService::fmt($due) . ')');
            }
            if ($bank > 0 && empty($data['bank_id'])) {
                throw new \RuntimeException('اختر حساب المصرف');
            }
            $this->payPools($orders, $customer, $credit, $cash, $bank, $data['bank_id'] ?? null, $cashId);
            foreach ($orders as $o) {
                $o->refresh();
                if (!$o->arrived) {
                    $o->arrived = true;
                    $o->arrived_at = now()->toDateString();
                }
                $o->delivered = true;
                $o->delivered_at = now()->toDateString();
                $o->delivered_time = now();
                $o->delivered_by = auth()->id();
                $o->delivery_type = $data['delivery_type'];
                $o->delivery_name = $data['delivery_name'] ?? null;
                $o->save();
                Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ' تسليم طلب للعميل (' . $customer->name . ')', 'log' => ' تم تسليم الطلب (' . $o->code . ') للعميل ' . $customer->name]);
            }
            $customer->save();
            // ادعُ صديق: أول استلام للعميل اللي جاء بدعوة = نقاط للي دعاه
            if ($customer->referred_by && !$customer->referral_rewarded && ($pts = (int) AppSettings::get('referral_points', 50)) > 0) {
                if ($ref = Customer::find($customer->referred_by)) {
                    PointsService::add($ref, $pts, 'دعوة صديق: ' . $customer->name);
                }
                $customer->referral_rewarded = true;
                $customer->save();
            }
            if (!empty($data['customer_note'])) {
                CustomerMessage::create(['customer_id' => $customer->id, 'body' => $data['customer_note'], 'created_id' => auth()->id()]);
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
        \Illuminate\Support\Facades\Cache::forget('sg.badge.ready');

        $paid = $cash + $bank + $credit;
        $left = $orders->map->fresh()->sum(fn ($o) => max(0, $o->remaining()));
        $msg = 'تسلّمت ' . $orders->count() . ' طلبية لـ ' . $customer->name . ' · قبضنا ' . TreasuryService::fmt($paid) . ' د.ل'
            . ($disc ? ' · تخفيض ' . TreasuryService::fmt($disc) : '') . ($ptsVal ? ' · نقاط ' . TreasuryService::fmt($ptsVal) : '')
            . ($left > 0.009 ? ' · باقي عليه ' . TreasuryService::fmt($left) : '');
        if ($request->boolean('send_whatsapp')) {
            $o = $orders->first()->fresh();
            MessageService::sendTemplate('delivered', $customer, MessageService::orderVars($o, ['{رقم_الإيصال}' => $orders->pluck('code')->implode('، '), '{المدفوع_الآن}' => TreasuryService::fmt($paid), '{الباقي}' => TreasuryService::fmt($left)]), $o);
            if (!empty($data['customer_note'])) {
                MessageService::sendTemplate('customer_note', $customer, MessageService::customerVars($customer, ['{الملاحظة}' => $data['customer_note']]));
            }
        }
        if ($request->boolean('print')) {
            session()->flash('print_url', route('sg.delivery.slip', ['ids' => $orders->pluck('id')->implode(','), 'paid' => $paid]));
        }

        return $this->finish($request, $msg);
    }

    /** 📉 النواقص اللي ما تقررش فيها لكل إيصال: قطع ناقصة متسجلة + منتجات نفدت (بانتظار رد العميل) */
    protected function missingFor($orders): array
    {
        $out = [];
        if ($orders->isEmpty()) {
            return $out;
        }
        $sold = \Illuminate\Support\Facades\Schema::hasTable('order_soldouts')
            ? \App\Models\OrderSoldout::whereIn('order_id', $orders->pluck('id'))->where('status', 'pending')->get()->groupBy('order_id') : collect();
        $missIss = \Illuminate\Support\Facades\Schema::hasTable('order_issues')
            ? \App\Models\OrderIssue::whereIn('order_id', $orders->pluck('id'))->where('type', 'missing')->where('status', 'open')->get()->groupBy('order_id') : collect();
        foreach ($orders as $o) {
            $rows = [];
            if ((int) $o->missing_qty > 0 && !$o->missing_action) {
                $rows[] = ['t' => 'قطع ناقصة ما وصلتش', 'n' => (int) $o->missing_qty, 'v' => round((float) $o->missing_value, 2)];
            }
            foreach ($sold[$o->id] ?? [] as $s) {
                $rows[] = ['t' => '🚫 نفد: ' . $s->item, 'n' => (int) $s->qty, 'v' => round((float) $s->price, 2)];
            }
            foreach ($missIss[$o->id] ?? [] as $i) {
                $rows[] = ['t' => '📉 ' . ($i->note ?: 'نواقص'), 'n' => (int) $i->qty, 'v' => round((float) $i->value, 2)];
            }
            if ($rows) {
                $out[$o->id] = ['rows' => $rows, 'qty' => array_sum(array_column($rows, 'n')), 'value' => round(array_sum(array_column($rows, 'v')), 2)];
            }
        }

        return $out;
    }

    /** توزيع المدفوع (رصيد → كاش → مصرف) على الإيصالات بالترتيب */
    protected function payPools($orders, Customer $customer, float $credit, float $cash, float $bank, $bankId, int $cashId): void
    {
        $pools = [['credit', $credit, null], ['cash', $cash, $cashId], ['bank', $bank, $bankId]];
        foreach ($orders as $o) {
            $need = max(0, $o->remaining());
            foreach ($pools as &$pool) {
                if ($need <= 0.009 || $pool[1] <= 0.009) {
                    continue;
                }
                $take = round(min($need, $pool[1]), 2);
                if ($pool[0] === 'credit') {
                    $o->paid_price += $take;
                    $o->refreshPaymentStatus();
                    $o->save();
                    $customer->credit = max(0, (float) $customer->credit - $take);
                    Log::create(['user_id' => auth()->id(), 'type' => 'customer', 'title' => ' رصيد عميل (' . $customer->name . ')', 'log' => ' استخدام رصيد ' . $take . ' د.ل للطلب ' . $o->code]);
                } else {
                    OrderPaymentService::pay($o, $take, (int) $pool[2], 'باقي عند التسليم');
                }
                $pool[1] -= $take;
                $need -= $take;
            }
            unset($pool);
        }
    }

    /** بعد التسليم: من نافذة واجهة الطلبات تتسكر النافذة وتتحدث الصفحة، وإلا نرجعو لواجهة التسليم */
    protected function finish(Request $request, string $msg)
    {
        if ($request->boolean('embed')) {
            session()->flash('success', $msg);

            return response('<!doctype html><script>try{top.location.reload()}catch(e){location.href=' . json_encode(route('sg.orders.index')) . '}</script>✅');
        }

        return ($request->boolean('stay') ? back() : redirect()->route('sg.delivery'))->with('success', $msg);
    }

    /** إيصال الاستلام (ورقة صغيرة للطباعة) */
    public function slip(Request $request)
    {
        $ids = collect(explode(',', (string) $request->ids))->filter()->map(fn ($v) => (int) $v);
        $orders = Order::with(['customer', 'deliveredUser'])->whereIn('id', $ids)->get();
        abort_if($orders->isEmpty(), 404);

        return view('sg.delivery.slip', ['orders' => $orders, 'paid' => (float) $request->paid]);
    }

    /** اقتراحات البحث وأنت تكتب */
    public function suggest(Request $request)
    {
        $t = trim((string) $request->q);
        if ($t === '') {
            return response()->json([]);
        }
        $d = preg_replace('/\D/', '', $t);
        $ids = Order::where('code', 'like', "%$t%")->limit(20)->pluck('customer_id');
        $cs = Customer::withoutGlobalScope('approved')->where(fn ($w) => $w->where('code', 'like', '%' . $t . '%')->orWhere('name', 'like', "%$t%")
                ->when(strlen($d) >= 4, fn ($x) => $x->orWhere('phone_1', 'like', "%$d%")->orWhere('phone_2', 'like', "%$d%"))
                ->orWhereIn('id', $ids))
            ->orderByRaw('code = ? desc, code like ? desc, length(code)', [$t, '%' . $t . '%'])->limit(25)->get(['id', 'name', 'code', 'phone_1']);
        $ready = (clone $this->readyQuery())->whereIn('customer_id', $cs->pluck('id'))->selectRaw('customer_id, COUNT(*) n')->groupBy('customer_id')->pluck('n', 'customer_id');
        $open = $this->openQuery()->whereIn('customer_id', $cs->pluck('id'))->selectRaw('customer_id, COUNT(*) n')->groupBy('customer_id')->pluck('n', 'customer_id');
        // اللي عنده طلبيات ما تسلمتش فوق
        $cs = $cs->sortByDesc(fn ($c) => ($c->code === $t ? 100000 : 0) + (str_contains((string) $c->code, $t) ? 1000 : 0) + (int) ($open[$c->id] ?? 0))->values();

        return response()->json($cs->map(fn ($c) => ['id' => $c->id, 'code' => $c->code, 'name' => $c->name, 'phone' => $c->phone_1, 'ready' => (int) ($ready[$c->id] ?? 0), 'open' => (int) ($open[$c->id] ?? 0)]));
    }

    /** جرد الرفوف: نكتب الأكواد الموجودة فعلاً ونقارنها بالمنظومة */
    public function audit(Request $request)
    {
        $res = null;
        $shelf = trim((string) $request->shelf);
        if ($request->isMethod('post')) {
            $codes = collect(preg_split('/[\s,،;]+/u', (string) $request->codes))->map(fn ($c) => trim($c))->filter()->unique()->values();
            $inSys = (clone $this->readyQuery())->with('customer')->when($shelf !== '', fn ($q) => $q->where('storage_num', $shelf))->get();
            $found = Order::with('customer')->whereIn('code', $codes)->get()->keyBy(fn ($o) => (string) $o->code);
            $ok = collect();
            $extra = collect();
            foreach ($codes as $c) {
                $o = $found[$c] ?? null;
                if ($o && $inSys->contains('id', $o->id)) {
                    $ok->push($o);
                } else {
                    $extra->push((object) ['code' => $c, 'order' => $o, 'why' => !$o ? 'الكود مش موجود في المنظومة' : ($o->delivered ? 'مسجلة مستلمة' : ($o->status === 'canceld' ? 'ملغاة' : (!$o->arrived ? 'مش مسجلة واصلة' : 'مسجلة في رف ثاني (' . ($o->storage_num ?: '—') . ')')))]);
                }
            }
            $missing = $inSys->reject(fn ($o) => $codes->contains((string) $o->code))->values();
            $res = compact('ok', 'extra', 'missing') + ['count' => $codes->count()];
        }

        return view('sg.delivery.audit', ['nav' => 'del', 'res' => $res, 'shelf' => $shelf, 'codes' => $request->codes,
            'shelves' => (clone $this->readyQuery())->whereNotNull('storage_num')->where('storage_num', '!=', '')->distinct()->orderBy('storage_num')->pluck('storage_num')]);
    }

    /** إلغاء استلام بالغلط: يرجع الإيصال غير مستلم (الفلوس المدفوعة تقعد كيف ما هي) */
    /** ↩ إلغاء التسليم: يرجع جاهز للاستلام في نفس الرف · ويرجّع دفعة التسليم لو اخترت */
    public function undo(Request $request, Order $order)
    {
        if (!$order->delivered) {
            return back()->with('warning', 'الإيصال هذا مش مستلم أصلاً');
        }
        $refund = $request->boolean('refund');
        $since = $order->delivered_time ? \Carbon\Carbon::parse($order->delivered_time)->subMinutes(10) : now()->startOfDay();
        $note = '';
        DB::beginTransaction();
        try {
            $dl = $order->delivery_id ? \App\Models\Delivery::find($order->delivery_id) : null;
            if ($refund) {
                if ($dl && $dl->status === 'delivered') {
                    // مندوب: الكاش لسه معاه لو ما تقفلش حسابه
                    if ($dl->settlement_id) {
                        $note = ' · ⚠ حساب المندوب تقفل، الفلوس ما رجعتش';
                    } else {
                        $rows = PaidHistory::where('order_id', $order->id)->where('created_at', '>=', $since)->get();
                        $sum = 0;
                        foreach ($rows as $p) {
                            if ((int) $p->payment_id === (int) \App\Services\AppSettings::cashPaymentId()) {
                                $sum += (float) $p->paid_price;
                                $p->delete();
                            } else {
                                \App\Services\OrderPaymentService::edit($p, 0, (int) $p->payment_id);
                            }
                        }
                        $order->refresh();
                        $order->paid_price = max(0, round((float) $order->paid_price - $sum, 2));
                        $order->refreshPaymentStatus();
                        $dl->collected_cash = max(0, round((float) $dl->collected_cash - $sum, 2));
                        $dl->save();
                        $note = ' · رجعت دفعة المندوب ' . $sum;
                    }
                } else {
                    $n = 0;
                    foreach (PaidHistory::where('order_id', $order->id)->where('created_at', '>=', $since)->get() as $p) {
                        $n += (float) $p->paid_price;
                        \App\Services\OrderPaymentService::edit($p, 0, (int) $p->payment_id);
                    }
                    $order->refresh();
                    $note = $n ? ' · رجعت دفعة التسليم ' . $n . ' من الخزينة' : '';
                }
            }
            $order->delivered = false;
            $order->delivered_at = null;
            $order->delivered_time = null;
            $order->delivered_by = null;
            $order->delivered_qty = null;
            $order->delivery_type = null;
            $order->delivery_name = null;
            $order->delivery_id = null;
            $order->save();
            \App\Models\Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ' إلغاء تسليم (' . $order->code . ')', 'log' => ' رجع الإيصال ' . $order->code . ' جاهز للاستلام · ' . optional($order->customer)->name . ($refund ? ' · مع إرجاع الدفعة' : ' · الفلوس قعدت') . $note]);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()->with('error', 'ما تلغاش التسليم: ' . mb_substr($e->getMessage(), 0, 150));
        }
        \Illuminate\Support\Facades\Cache::forget('sg.badge.ready');

        return back()->with('success', '↩ الإيصال ' . $order->code . ' رجع جاهز للاستلام' . ($order->storage_num ? ' في الرف ' . $order->storage_num : '') . $note);
    }

    public function done(Request $request)
    {
        $p = Period::make($request->input('p', 'day'));
        $base = Order::where('delivered', true)->whereBetween('delivered_at', [$p->from->toDateString(), $p->to->toDateTimeString()]);
        $prevCount = Order::where('delivered', true)->whereBetween('delivered_at', [$p->prevFrom->toDateString(), $p->prevTo->toDateTimeString()])->count();
        $q = (clone $base)->with(['customer', 'deliveredUser', 'paids.paymentMethod']);
        if ($request->filled('q')) {
            $t = trim($request->q);
            $q->where(fn ($w) => \App\Support\Search::code($w, $t)->orWhereHas('customer', fn ($c) => \App\Support\Search::customer($c, $t)));
        }
        if ($request->filled('type')) {
            $q->where('delivery_type', $request->type);
        }
        if ($request->filled('by')) {
            $q->where('delivered_by', $request->by);
        }
        if ($request->filled('trip')) {
            $q->where('trip_details', $request->trip);
        }
        if ($request->export === 'csv') {
            return \App\Services\Csv::download('delivered-' . $p->from->format('Y-m-d') . '.csv',
                ['الوقت', 'الإيصال', 'العميل', 'الكود', 'الرحلة', 'التسليم', 'سلّمها', 'الباقي'],
                $q->orderByDesc('delivered_time')->get()->map(fn ($o) => [optional($o->delivered_time)->format('Y-m-d H:i') ?: $o->delivered_at, $o->code, optional($o->customer)->name, optional($o->customer)->code, $o->trip_details, $o->delivery_type, optional($o->deliveredUser)->name, round(max(0, $o->remaining()), 2)]));
        }
        $orders = $q->orderByDesc('delivered_time')->orderByDesc('id')->paginate(30)->withQueryString();
        $ids = (clone $base)->pluck('id');
        $collect = PaidHistory::whereIn('order_id', $ids)->whereBetween('created_at', [$p->from, $p->to])->with('paymentMethod')->get();
        $cashId = AppSettings::cashPaymentId();
        $all = (clone $base)->get();
        $shelf = $all->filter(fn ($o) => $o->received_at)->map(fn ($o) => $o->received_at->diffInHours($o->delivered_time ?: \Carbon\Carbon::parse($o->delivered_at)) / 24);

        return view('sg.delivery.done', [
            'nav' => 'del', 'period' => $p, 'orders' => $orders, 'count' => $ids->count(), 'prevCount' => $prevCount,
            'collected' => $collect->sum('paid_price'), 'cash' => $collect->where('payment_id', $cashId)->sum('paid_price'), 'bank' => $collect->where('payment_id', '!=', $cashId)->sum('paid_price'),
            'customers' => $all->pluck('customer_id')->unique()->count(), 'multi' => $all->groupBy('customer_id')->filter(fn ($g) => $g->count() > 1)->count(),
            'avgShelf' => $shelf->count() ? round($shelf->avg(), 1) : null,
            'byType' => $all->groupBy('delivery_type')->map->count(),
            'byUser' => $all->groupBy('delivered_by')->map->count()->sortDesc(),
            'users' => User::pluck('name', 'id'),
            'old' => (clone $this->readyQuery())->with('customer')->get()->filter(fn ($o) => $o->shelfDays() !== null && $o->shelfDays() > 7)->sortByDesc(fn ($o) => $o->shelfDays())->values(),
        ]);
    }

    /** تذكير الذي نسيوا بضاعتهم في الرف */
    public function remindShelf()
    {
        $days = (int) AppSettings::get('shelf_reminder_days', 5);
        $orders = (clone $this->readyQuery())->with('customer')->get()->filter(fn ($o) => $o->shelfDays() !== null && $o->shelfDays() >= $days);
        $sent = 0;
        foreach ($orders->groupBy('customer_id') as $g) {
            $o = $g->first();
            if (MessageService::sendTemplate('shelf_reminder', $o->customer, MessageService::orderVars($o, ['{رقم_الإيصال}' => $g->pluck('code')->implode('، ')]), $o)) {
                $sent++;
            }
        }

        return back()->with('success', 'أُرسلت ' . $sent . ' رسالة تذكير');
    }
}
