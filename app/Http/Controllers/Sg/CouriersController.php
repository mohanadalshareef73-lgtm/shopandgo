<?php

namespace App\Http\Controllers\Sg;

use App\Http\Controllers\Controller;
use App\Models\CourierSettlement;
use App\Models\Delivery;
use App\Models\Log;
use App\Models\PaymentMethod;
use App\Models\SgAlert;
use App\Models\User;
use App\Services\AppSettings;
use App\Services\CourierService;
use App\Services\TreasuryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** إدارة المندوبين: الحسابات، التوصيلات، طلبات العملاء، إقفال الحساب */
class CouriersController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:courier-list', ['only' => ['index', 'label']]);
        $this->middleware('permission:courier-create', ['only' => ['store']]);
        $this->middleware('permission:courier-edit', ['only' => ['update', 'updateDelivery', 'action']]);
        $this->middleware('permission:courier-settle', ['only' => ['settle']]);
    }

    public function index(Request $request)
    {
        $couriers = CourierService::couriers();
        $bal = $couriers->mapWithKeys(fn ($c) => [$c->id => CourierService::balance($c)]);
        $requests = Delivery::with(['customer', 'orders'])->where('status', 'requested')->latest()->get();
        // 🚚 طلبيات خارج بنغازي: ما تمشيش للمندوب أوتوماتيك — لروحها في واجهة
        $outside = $requests->filter(fn ($r) => CourierService::outsideBenghazi($r->city))->values();
        $requests = $requests->reject(fn ($r) => CourierService::outsideBenghazi($r->city))->values();
        // 🛵 طلبات العملاء (داخل وخارج بنغازي) صارت في «طلبيات دليفري»
        $requests = collect();
        $outside = collect();
        $problems = Delivery::with(['customer', 'courier'])->where('status', 'problem')->latest()->get();
        $sel = $request->filled('id') ? $couriers->firstWhere('id', (int) $request->id) : null;
        $focus = $request->filled('d') ? Delivery::with(['orders', 'customer', 'courier', 'events.user'])->find($request->d) : null;
        if ($focus && !$sel && $focus->courier_id) {
            $sel = $couriers->firstWhere('id', $focus->courier_id);
        }
        $detail = [];
        if ($sel) {
            $detail = [
                'pending' => Delivery::with(['orders', 'customer'])->where('courier_id', $sel->id)->whereIn('status', ['assigned', 'out', 'problem'])->orderBy('assigned_at')->get(),
                'recent' => Delivery::with(['orders', 'customer'])->where('courier_id', $sel->id)->whereIn('status', ['delivered', 'returned', 'cancelled'])->latest('updated_at')->take(25)->get(),
                'settlements' => CourierSettlement::with('createdUser')->where('courier_id', $sel->id)->latest()->take(10)->get(),
            ];
        }

        return view('sg.couriers.index', [
            'nav' => 'courier', 'couriers' => $couriers, 'bal' => $bal, 'requests' => $requests, 'outside' => $outside, 'problems' => $problems,
            'sel' => $sel, 'detail' => $detail, 'focus' => $focus,
            'payments' => PaymentMethod::where('status', true)->orderBy('id')->get(), 'cashId' => AppSettings::cashPaymentId(),
            'today' => Delivery::where('status', 'delivered')->where('delivered_at', '>=', now()->startOfDay())->count(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:80|unique:users,name', 'phone' => 'required|string|max:20',
            'password' => 'required_unless:courier_kind,office|nullable|string|min:8', 'courier_zone' => 'nullable|string|max:80', 'perms' => 'nullable|array',
            'courier_kind' => 'nullable|in:person,office',
        ], ['name.unique' => 'هذا الاسم مستخدم', 'password.min' => 'كلمة المرور 8 خانات على الأقل', 'password.required_unless' => 'اختر كلمة مرور للمندوب']);
        $office = ($data['courier_kind'] ?? 'person') === 'office';
        $u = new User;
        $u->name = $data['name'];
        $u->phone = $data['phone'];
        $u->email = 'courier.' . preg_replace('/\D/', '', $data['phone']) . '.' . time() . '@courier.local';
        $u->password = Hash::make($office || empty($data['password']) ? \Illuminate\Support\Str::random(40) : $data['password']); // المكتب لا يدخل للنظام
        $u->type_id = 4;
        if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'courier_kind')) {
            $u->courier_kind = $office ? 'office' : 'person';
        }
        $u->status = true;
        $u->courier_zone = $data['courier_zone'] ?? null;
        $u->courier_perms = json_encode($this->perms($request), JSON_UNESCAPED_UNICODE);
        $u->save();
        Log::create(['user_id' => auth()->id(), 'type' => 'user', 'title' => ' مندوب جديد (' . $u->name . ')', 'log' => ' تم إضافة المندوب ' . $u->name]);

        return redirect()->route('sg.couriers', ['id' => $u->id])->with('success', $office ? 'تم إضافة مكتب التوصيل ' . $u->name . ' · تسند له التوصيلات وتُقفل معه الحساب من هنا' : 'تم إضافة المندوب ' . $u->name . ' · يدخل من نفس صفحة الدخول باسمه وكلمة السر');
    }

    protected function perms(Request $request): array
    {
        return collect(User::COURIER_PERMS)->mapWithKeys(fn ($v, $k) => [$k => (bool) ($request->input('perms.' . $k))])->all();
    }

    public function update(Request $request, User $courier)
    {
        abort_unless($courier->isCourier(), 404);
        $data = $request->validate([
            'name' => 'required|string|max:80|unique:users,name,' . $courier->id, 'phone' => 'required|string|max:20',
            'password' => 'nullable|string|min:8', 'courier_zone' => 'nullable|string|max:80',
        ]);
        $courier->name = $data['name'];
        $courier->phone = $data['phone'];
        $courier->courier_zone = $data['courier_zone'] ?? null;
        $courier->courier_perms = json_encode($this->perms($request), JSON_UNESCAPED_UNICODE);
        $courier->status = $request->boolean('status');
        if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'courier_hide_phone')) {
            $courier->courier_hide_phone = $request->boolean('courier_hide_phone');
        }
        if (!empty($data['password'])) {
            $courier->password = Hash::make($data['password']);
        }
        $courier->save();

        return back()->with('success', 'تم حفظ بيانات المندوب');
    }

    /** تعديل بيانات المستلم (الرقم، العنوان، الموقع) أو تغيير المندوب */
    public function updateDelivery(Request $request, Delivery $delivery)
    {
        $data = $request->validate([
            'recipient_name' => 'nullable|string|max:120', 'recipient_phone' => 'nullable|string|max:30', 'recipient_phone2' => 'nullable|string|max:30',
            'city' => 'nullable|string|max:80', 'address' => 'nullable|string|max:250', 'location_url' => 'nullable|string|max:500',
            'note' => 'nullable|string|max:500', 'fee' => 'nullable|numeric|min:0', 'fee_payer' => 'nullable|in:customer,company', 'pay_type' => 'nullable|in:cash,bank,mixed,paid',
            'courier_id' => 'nullable|exists:users,id',
        ]);
        $changes = [];
        foreach (['recipient_name', 'recipient_phone', 'recipient_phone2', 'city', 'address', 'location_url', 'note', 'fee', 'fee_payer', 'pay_type'] as $k) {
            if ($request->has($k) && (string) $delivery->$k !== (string) ($data[$k] ?? '')) {
                $changes[] = $k;
                $delivery->$k = $data[$k] ?? ($k === 'fee' ? 0 : ($k === 'fee_payer' ? 'customer' : null));
            }
        }
        $delivery->save();
        if (!empty($data['courier_id']) && (int) $data['courier_id'] !== (int) $delivery->courier_id) {
            $cu = User::where('type_id', 4)->findOrFail($data['courier_id']);
            CourierService::setCourier($delivery, $cu);
            if ($cu->isOffice() && $delivery->fresh()->isOpen()) {
                // 🏢 مكتب توصيل: تتسلم له طول والمبلغ يتسجل عليه لين التسوية
                $d2 = $delivery->fresh(['orders', 'courier', 'customer']);
                $due = $d2->toCollect();
                \Illuminate\Support\Facades\DB::transaction(function () use ($d2, $due, $cu) {
                    CourierService::markDelivered($d2, $due, 0, 'تسليم لمكتب التوصيل ' . $cu->name);
                    foreach ($d2->orders()->get() as $oo) {
                        $oo->delivery_type = 'توصيل عن طريق مكتب';
                        $oo->delivery_name = $cu->name;
                        $oo->save();
                    }
                });

                return back()->with('success', '🏢 ' . $delivery->code . ' تسلّمت لمكتب ' . $cu->name . ' · عليه ' . \App\Services\TreasuryService::fmt($due) . ' د.ل');
            }
            if ($request->boolean('notify')) {
                CourierService::notifyCustomer($delivery->fresh(['orders', 'courier', 'customer']), 'courier_assigned');
            }
        }
        if ($changes) {
            $delivery->log($delivery->status, 'تعديل: ' . implode('، ', $changes));
        }

        return back()->with('success', 'تم حفظ التوصيل ' . $delivery->code);
    }

    /** أوامر على توصيل: تم (بالنيابة عن المندوب) / مشكلة / عادت للمحل / إلغاء */
    public function action(Request $request, Delivery $delivery)
    {
        $a = $request->input('a');
        DB::beginTransaction();
        try {
            match ($a) {
                'out' => CourierService::markOut($delivery),
                'delivered' => CourierService::markDelivered($delivery->load('orders', 'courier', 'customer'), (float) $request->input('cash', 0), (float) $request->input('bank', 0), $request->input('note')),
                'problem' => CourierService::problem($delivery, (string) $request->input('note', 'مشكلة')),
                'returned' => CourierService::returnToShop($delivery->load('orders'), 'returned', $request->input('note')),
                'cancelled' => CourierService::returnToShop($delivery->load('orders'), 'cancelled', $request->input('note')),
                default => throw new \RuntimeException('أمر غير معروف'),
            };
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()->with('error', $e instanceof \RuntimeException ? $e->getMessage() : 'للاسف حدث خطأ ما');
        }

        return back()->with('success', $delivery->code . ' · ' . (Delivery::STATUSES[$a][0] ?? 'تم'));
    }

    /** إقفال الحساب مع المندوب */
    public function settle(Request $request, User $courier)
    {
        abort_unless($courier->isCourier(), 404);
        $data = $request->validate([
            'received' => 'required|numeric', 'rounding' => 'nullable|numeric|min:0|max:100',
            'payment_id' => 'required|exists:payment_methods,id', 'note' => 'nullable|string|max:300',
        ], ['rounding.max' => 'الكسور لا تزيد على 100 د.ل']);
        DB::beginTransaction();
        try {
            $s = CourierService::settle($courier, (float) $data['received'], (float) ($data['rounding'] ?? 0), (int) $data['payment_id'], $data['note'] ?? null);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()->with('error', $e instanceof \RuntimeException ? $e->getMessage() : 'للاسف حدث خطأ ما');
        }

        return back()->with(abs($s->diff) < 0.01 ? 'success' : 'warning', 'أُقفل حساب ' . $courier->name . ' · استلمنا ' . TreasuryService::fmt($s->received) . ' د.ل'
            . ($s->rounding > 0 ? ' · كسور تلغت ' . TreasuryService::fmt($s->rounding) : '')
            . (abs($s->diff) >= 0.01 ? ' · ' . ($s->diff > 0 ? 'باقي عليه ' : 'زيادة له ') . TreasuryService::fmt(abs($s->diff)) : ' ✓'));
    }

    /** ورقة المندوب (تتلصق على الطرد) */
    public function label(Delivery $delivery)
    {
        return view('sg.couriers.label', ['d' => $delivery->load('orders', 'customer', 'courier')]);
    }

    /* ---------- التنبيهات (الجرس فوق) ---------- */
    public function alerts(Request $request)
    {
        $since = (int) $request->input('since', 0);

        return response()->json([
            'unread' => SgAlert::whereNull('read_at')->count(),
            'last' => (int) SgAlert::max('id'),
            'items' => SgAlert::latest('id')->take(12)->get()->map(fn ($a) => [
                'id' => $a->id, 'title' => $a->title, 'body' => $a->body, 'url' => $a->url, 'level' => $a->level,
                'new' => $a->id > $since, 'unread' => !$a->read_at, 'ago' => $a->created_at->diffForHumans(),
            ]),
        ]);
    }

    public function alertsRead(Request $request)
    {
        $q = SgAlert::whereNull('read_at');
        if ($request->filled('id')) {
            $q->where('id', (int) $request->id);
        }
        $q->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function clearCloseRequest(User $courier)
    {
        $courier->close_request = null;
        $courier->save();

        return back()->with('success', 'تم تجاهل طلب الإقفال');
    }
}
