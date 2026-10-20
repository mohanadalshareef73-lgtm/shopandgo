<?php

namespace App\Http\Controllers\Sg;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Log;
use App\Models\Order;
use App\Models\Shift;
use App\Models\TreasuryHistory;
use App\Models\User;
use App\Services\Period;
use App\Services\ShiftService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

/** الموظفين: حساب لكل موظف + صلاحيات بالأقسام + الوردية */
class StaffController extends Controller
{
    /** الأقسام: [الاسم، الشرح، ترى، تضيف، تعدّل، تحذف] */
    public const SECTIONS = [
        'orders' => ['الإيصالات', 'إضافة إيصال وطباعته وقبض العربون', 'order-list', 'order-create', 'order-edit', 'order-delete'],
        'delivery' => ['التسليم', 'ترى · تسلّم وتقبض · — · تتراجع عن تسليم', 'delivery-list', 'delivery-create', null, 'delivery-undo'],
        'customers' => ['العملاء', 'بيانات وأرقام', 'customer-list', 'customer-create', 'customer-edit', 'customer-delete'],
        'debts' => ['الديون', 'قبض ديون وتذكير', 'dept-list', 'dept-create', 'dept-edit', 'dept-delete'],
        'trips' => ['الرحلات', 'ترى · تضيف رحلة · تعدّل وتزيد/تطلّع إيصالات وتغيّر الحالة · تحذف', 'trip-purchase-list', 'trip-purchase-create', 'trip-purchase-edit', 'trip-purchase-delete'],
        'receive' => ['الاستلام والرفوف', 'ترى الرفوف والجرد · تستلم البضاعة وتحطها في الرف · تجرد وتذكّر', 'shelf-list', 'order-receive', 'shelf-edit', null],
        'carts' => ['السلات', '', 'cart-list', 'cart-create', 'cart-edit', null],
        'treasury' => ['الخزينة', 'حركات الخزينة والتحويل', 'treasury-list', 'treasury-create', 'treasury-edit', 'treasury-delete'],
        'expenses' => ['المصاريف', '', 'purchase-list', 'purchase-create', 'purchase-edit', 'purchase-delete'],
        'usd' => ['شراء الدولار', '', 'exchange-list', 'exchange-create', null, 'exchange-delete'],
        'ship' => ['شركة الشحن', 'دفع الشحن', 'shippingCharge-list', 'shippingCharge-create', 'shippingCharge-edit', 'shippingCharge-delete'],
        'profit' => ['رؤية الأرباح والتكاليف', 'الأرباح، تكلفة البضاعة والبطاقات، صافي الربح', 'profit-view', null, null, null],
        'partners' => ['الأرباح والشركاء', 'رأس المال والسحوبات', 'partner-list', 'partner-create', null, 'partner-delete'],
        'reports' => ['التقارير', '', 'reports-list', null, null, null],
        'staff' => ['الموظفين', 'إضافة موظفين وصلاحيات', 'user-list', 'user-create', 'user-edit', 'user-delete'],
        'settings' => ['الإعدادات', 'الحالات، المنصات، رسائل الواتساب', 'system-edit', 'status-create', 'status-edit', 'status-delete'],
        'returns' => ['طلبيات مسترجعة', 'تسجيل مرتجع واستبدال وإيصال مرتجع', 'return-list', 'return-create', null, null],
        'tickets' => ['بلاغات العملاء', 'مشاكل العملاء من حسابهم', 'ticket-list', null, null, null],
        'soldouts' => ['المنتجات اللي نفدت', 'تسجيلها وإبلاغ العميل', 'soldout-manage', null, null, null],
        'shein' => ['بطاقات SHEIN', 'ترى · تضيف بطاقة · تستعملها في رحلة · تحذف', 'shein-card-list', 'shein-card-create', 'shein-card-use', 'shein-card-delete'],
        'shein_pin' => ['كشف رمز بطاقات SHEIN', 'يشوف الرقم السري للبطاقة', 'shein-card-pin', null, null, null],
        'card_sell' => ['بيع بطاقات SHEIN', 'إيصال بيع بطاقات للعملاء', 'shein-card-sell', null, null, null],
        'shipping' => ['الشحن', 'ترى · تضيف شحنة · تعدّل الحالة وترسل العناوين', 'shipping-list', 'shipping-create', 'shipping-edit', null],
        'couriers' => ['المندوبين', 'ترى · تضيف مندوب · تسند وتعدّل', 'courier-list', 'courier-create', 'courier-edit', null],
        'courier_settle' => ['تقفيل حسابات المندوبين', 'استلام الفلوس من المندوب', 'courier-settle', null, null, null],
        'offers' => ['العروض والكوبونات', '', 'offer-manage', null, null, null],
        'points' => ['تعديل نقاط العملاء', 'زيادة ونقص يدوي', 'points-edit', null, null, null],
        'unlock' => ['فك الحسابات المقفولة', 'حل مشاكل دخول العملاء للمنظومة', 'customer-unlock', null, null, null],
    ];

    /** أدوار جاهزة: القسم => [ترى، تضيف، تعدّل، تحذف] */
    public const PRESETS = [
        'admin' => ['مدير', 'كل شي', '*'],
        'front' => ['استقبال وتسليم', 'إيصالات، قبض، تسليم، مرتجعات، تأكيد الحجوزات', ['orders' => [1, 1, 1, 0], 'delivery' => [1, 1], 'customers' => [1, 1, 1, 0], 'debts' => [1, 1, 0, 0], 'carts' => [1, 1, 1], 'returns' => [1, 1], 'tickets' => [1], 'couriers' => [1, 0, 1], 'card_sell' => [1]]],
        'buyer' => ['مشتريات', 'رحلات، دولار، سلات، بطاقات SHEIN', ['orders' => [1, 0, 0, 0], 'trips' => [1, 1, 1, 0], 'usd' => [1, 1, 0, 0], 'carts' => [1, 1, 1], 'customers' => [1, 0, 0, 0], 'shein' => [1, 1, 1, 0], 'soldouts' => [1]]],
        'store' => ['مخزن', 'استلام، رفوف، شحن', ['orders' => [1, 0, 0, 0], 'receive' => [1, 1, 1], 'trips' => [1, 0, 0, 0], 'delivery' => [1, 1], 'ship' => [1, 1, 0, 0], 'shipping' => [1, 1, 1], 'returns' => [1, 1]]],
        'office' => ['موظف مكتب', 'إيصالات وقبض وبيانات الشحن، بيع كروت، عناوين الشحن، تقفيل المندوب، البلاغات، الطلبيات المسترجعة، تأكيد الحجوزات، إلغاء تسليم، طلبات التسجيل وفك الحسابات، مصروفات دوامه — بدون مالية الشركة', ['orders' => [1, 1, 1, 0], 'delivery' => [1, 1, 0, 1], 'customers' => [1, 1, 1, 0], 'debts' => [1, 1, 0, 0], 'receive' => [1, 1, 1], 'shipping' => [1, 1, 1], 'card_sell' => [1], 'couriers' => [1, 0, 0], 'courier_settle' => [1], 'tickets' => [1], 'returns' => [1, 1], 'carts' => [1, 1, 1], 'expenses' => [1, 1, 0, 0], 'unlock' => [1], 'soldouts' => [1]]],
        'courier_admin' => ['مسؤول توصيل', 'المندوبين والتقفيل والتسليم', ['orders' => [1, 0, 0, 0], 'delivery' => [1, 1], 'customers' => [1, 0, 0, 0], 'couriers' => [1, 1, 1], 'courier_settle' => [1]]],
    ];

    /** صلاحيات إضافية تُستخدم مع القسم (لأجل الشاشات القديمة) */
    public const EXTRA = [
        'orders' => ['invoice-list', 'platform-list', 'status-list'],
        'trips' => ['trip-list', 'trip-create', 'trip-edit'],
        'treasury' => ['finance-dashboard'],
        'settings' => ['system-list', 'platform-create', 'platform-edit', 'whatsapp-templates', 'role-list'],
        'delivery' => ['shift-close'],
    ];

    public function __construct()
    {
        $this->middleware('permission:user-list', ['only' => ['index']]);
        $this->middleware('permission:user-create', ['only' => ['store']]);
        $this->middleware('permission:user-edit', ['only' => ['update', 'perms', 'password', 'toggle']]);
        $this->middleware('permission:user-edit', ['only' => ['destroy']]);
    }

    public static function permsFor(array $matrix): array
    {
        $out = [];
        foreach ($matrix as $sec => $flags) {
            $def = self::SECTIONS[$sec] ?? null;
            if (!$def) {
                continue;
            }
            foreach ([0, 1, 2, 3] as $i) {
                if (!empty($flags[$i]) && $def[2 + $i]) {
                    $out[] = $def[2 + $i];
                }
            }
            if (!empty($flags[0])) {
                $out = array_merge($out, self::EXTRA[$sec] ?? []);
            }
        }

        return array_values(array_unique($out));
    }

    public static function isAdmin(User $u): bool
    {
        return (int) $u->type_id === 1 || $u->hasRole('Admin');
    }

    public function index(Request $request)
    {
        $p = Period::make($request->input('p', 'day'));
        $users = User::with('roles')->whereIn('type_id', [1, 2])
            ->when(\Illuminate\Support\Facades\Schema::hasColumn('users', 'archived_at'), fn ($q) => $q->whereNull('archived_at'))->orderByDesc('status')->orderBy('id')->get();
        $range = [$p->from, $p->to];
        $stats = $users->mapWithKeys(fn ($u) => [$u->id => [
            'orders' => Order::where('created_id', $u->id)->whereBetween('created_at', $range)->count(),
            'delivered' => Order::where('delivered_by', $u->id)->whereBetween('delivered_time', $range)->count(),
            'received' => Order::where('received_by', $u->id)->whereBetween('received_at', $range)->count(),
            'collected' => (float) TreasuryHistory::where('created_id', $u->id)->where('status', 'pay')->where('currency', 'dinar')->whereBetween('created_at', $range)->sum('price'),
            'shift' => ShiftService::summary($u),
            'online' => $u->last_seen_at && $u->last_seen_at->gt(now()->subMinutes(10)),
        ]]);
        $sel = $request->filled('id') ? User::with('roles', 'permissions')->find($request->id) : null;
        $prof = [];
        if ($sel) {
            $perms = $sel->getAllPermissions()->pluck('name')->all();
            $month = [now()->startOfMonth(), now()];
            $prof = [
                'perms' => $perms,
                'isAdmin' => self::isAdmin($sel),
                'preset' => $this->detectPreset($perms),
                'shift' => ShiftService::summary($sel),
                'shifts' => Shift::where('user_id', $sel->id)->whereNotNull('closed_at')->latest('closed_at')->take(8)->get(),
                'avgShift' => (float) Shift::where('user_id', $sel->id)->whereNotNull('closed_at')->whereBetween('closed_at', $month)->avg('counted_cash'),
                'newCustomers' => Customer::whereBetween('created_at', $range)->count(),
                'log' => Log::where('user_id', $sel->id)->latest()->paginate(20, ['*'], 'lp')->withQueryString(),
                'monthOrders' => Order::where('created_id', $sel->id)->whereBetween('created_at', $month)->count(),
                'monthDelivered' => Order::where('delivered_by', $sel->id)->whereBetween('delivered_time', $month)->count(),
            ];
        }

        return view('sg.staff.index', [
            'nav' => 'staff', 'period' => $p, 'users' => $users, 'stats' => $stats, 'sel' => $sel, 'prof' => $prof,
            'feed' => Log::with('user')->latest()->take(12)->get(),
            'ordersAll' => Order::whereBetween('created_at', $range)->count(),
            'closedToday' => Shift::whereNotNull('closed_at')->where('closed_at', '>=', now()->startOfDay())->count(),
            'openShifts' => Shift::whereNull('closed_at')->count(),
        ]);
    }

    protected function detectPreset(array $perms): ?string
    {
        sort($perms);
        foreach (self::PRESETS as $k => $pr) {
            if ($pr[2] === '*') {
                continue;
            }
            $want = array_values(array_intersect(self::permsFor($pr[2]), Permission::pluck('name')->all()));
            sort($want);
            if ($want === $perms) {
                return $k;
            }
        }

        return null;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:80|unique:users,name',
            'email' => 'nullable|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8',
            'preset' => 'required|in:' . implode(',', array_keys(self::PRESETS)),
        ], ['name.unique' => 'هذا الاسم مستخدم لموظف آخر', 'password.min' => 'كلمة المرور 8 خانات على الأقل']);
        $u = new User;
        $u->name = $data['name'];
        $u->email = $data['email'] ?? (\Illuminate\Support\Str::slug($data['name']) ?: 'user') . '.' . time() . '@staff.local';
        $u->phone = $data['phone'] ?? null;
        $u->password = Hash::make($data['password']);
        $u->type_id = 2;
        $u->status = true;
        $u->save();
        $this->applyPreset($u, $data['preset']);
        Log::create(['user_id' => auth()->id(), 'type' => 'user', 'title' => ' موظف جديد (' . $u->name . ')', 'log' => ' تم اضافة الموظف ' . $u->name . ' بدور ' . self::PRESETS[$data['preset']][0]]);

        return redirect()->route('sg.staff', ['id' => $u->id])->with('success', 'تم إضافة ' . $u->name . ' · يدخل باسمه وكلمة السر');
    }

    protected function applyPreset(User $u, string $preset): void
    {
        if ($preset === 'admin') {
            $role = \Spatie\Permission\Models\Role::where('name', 'Admin')->first();
            $u->syncPermissions([]);
            $role ? $u->syncRoles([$role]) : $u->syncPermissions(Permission::all());

            return;
        }
        $u->syncRoles([]);
        $u->syncPermissions(array_intersect(self::permsFor(self::PRESETS[$preset][2]), Permission::pluck('name')->all()));
    }

    public function perms(Request $request, User $user)
    {
        abort_if((int) $user->type_id === 1, 403, 'مدير النظام لديه كل الصلاحيات');
        if ($request->filled('preset')) {
            $this->applyPreset($user, $request->preset);
        } else {
            $matrix = collect($request->input('p', []))->map(fn ($f) => [!empty($f[0]), !empty($f[1]), !empty($f[2]), !empty($f[3])])->all();
            $user->syncRoles([]);
            $user->syncPermissions(array_intersect(self::permsFor($matrix), Permission::pluck('name')->all()));
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        Log::create(['user_id' => auth()->id(), 'type' => 'user', 'title' => ' صلاحيات (' . $user->name . ')', 'log' => ' تم تعديل صلاحيات ' . $user->name]);

        return back()->with('success', 'تم حفظ صلاحيات ' . $user->name);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate(['name' => 'required|string|max:80|unique:users,name,' . $user->id, 'email' => 'nullable|email|unique:users,email,' . $user->id, 'phone' => 'nullable|string|max:20'],
            ['name.unique' => 'هذا الاسم مستخدم لموظف آخر', 'email.unique' => 'الإيميل هذا مستخدم']);
        $data['email'] = $data['email'] ?: $user->email;
        $user->update($data);
        Log::create(['user_id' => auth()->id(), 'type' => 'user', 'title' => ' تعديل موظف (' . $user->name . ')', 'log' => ' تعديل البيانات']);

        return back()->with('success', 'تم الحفظ');
    }

    public function password(Request $request, User $user)
    {
        $request->validate(['password' => 'required|string|min:8'], ['password.min' => 'كلمة المرور 8 خانات على الأقل']);
        $user->password = Hash::make($request->password);
        $user->save();

        return back()->with('success', 'تغيرت كلمة المرور لـ ' . $user->name);
    }

    public function toggle(User $user)
    {
        abort_if($user->id === auth()->id() || (int) $user->type_id === 1, 403);
        $user->status = !$user->status;
        $user->save();
        Log::create(['user_id' => auth()->id(), 'type' => 'user', 'title' => ($user->status ? ' تفعيل' : ' إيقاف') . ' (' . $user->name . ')', 'log' => ' حساب ' . $user->name . ($user->status ? ' تم تفعيل' : ' تم إيقاف')]);

        return back()->with('success', $user->status ? 'تم تفعيل الحساب' : 'تم إيقاف الحساب، لا يمكنه الدخول');
    }

    /** حذف موظف: لو ما عندهش حركات ينحذف نهائي، ولو عنده يتقفل ويتخبى وحركاته تبقى */
    public function destroy(User $user)
    {
        abort_if($user->id === auth()->id(), 403, 'ما تقدرش تحذف روحك');
        abort_if((int) $user->type_id === 1, 403, 'ما ينفعش حذف مدير النظام');
        $name = $user->name;
        $user->syncRoles([]);
        $user->syncPermissions([]);
        try {
            \Illuminate\Support\Facades\DB::transaction(fn () => $user->delete());
            $msg = 'انحذف ' . $name . ' نهائياً';
        } catch (\Throwable $e) {
            // عنده إيصالات أو حركات خزينة باسمه → نقفلوه ونخبوه
            $user = User::find($user->id) ?? $user;
            $user->status = false;
            if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'archived_at')) {
                $user->archived_at = now();
            }
            $user->name = mb_substr($name, 0, 60) . ' (محذوف ' . $user->id . ')';
            $user->save();
            $msg = 'انحذف ' . $name . ' من القائمة وما يقدرش يدخل · حركاته القديمة باقية باسمه';
        }
        Log::create(['user_id' => auth()->id(), 'type' => 'user', 'title' => ' حذف موظف (' . $name . ')', 'log' => $msg]);

        return redirect()->route('sg.staff')->with('success', $msg);
    }
}
