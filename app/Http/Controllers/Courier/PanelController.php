<?php

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\Models\CourierSettlement;
use App\Models\Delivery;
use App\Services\CourierService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** واجهة المندوب (موبايل): طلبياته، تم التسليم، في مشكلة، حسابه */
class PanelController extends Controller
{
    protected function mine(int $id): Delivery
    {
        return Delivery::with(['orders', 'customer', 'courier'])->where('courier_id', auth()->id())->findOrFail($id);
    }

    public function index(Request $request)
    {
        $me = auth()->user();
        $tab = in_array($request->tab, ['home', 'orders', 'money', 'close', 'notes', 'settings']) ? $request->tab : 'home';
        $bal = CourierService::balance($me);
        $now = Delivery::with(['orders', 'customer'])->where('courier_id', $me->id)->whereIn('status', ['assigned', 'out', 'problem'])
            ->orderByRaw("CASE status WHEN 'problem' THEN 0 WHEN 'out' THEN 1 ELSE 2 END")->orderBy('city')->orderBy('address')->orderBy('assigned_at')->get(); // نفس المنطقة ورا بعض
        $doneQ = Delivery::with(['orders', 'customer'])->where('courier_id', $me->id)->whereIn('status', ['delivered', 'returned']);
        $ids = Delivery::where('courier_id', $me->id)->pluck('id');
        $sel = $request->filled('d') ? Delivery::with(['orders.customer', 'customer'])->where('courier_id', $me->id)->find((int) $request->d) : null;

        return view('courier.index', [
            'me' => $me, 'tab' => $sel ? 'detail' : $tab, 'bal' => $bal, 'now' => $now, 'sel' => $sel, 'seg' => $request->input('seg', 'now'),
            'done' => (clone $doneQ)->latest('updated_at')->take(40)->get(),
            'settlements' => CourierSettlement::where('courier_id', $me->id)->latest()->take(15)->get(),
            'todayDone' => (clone $doneQ)->where('status', 'delivered')->where('delivered_at', '>=', now()->startOfDay())->count(),
            'todayCash' => (float) (clone $doneQ)->where('status', 'delivered')->where('delivered_at', '>=', now()->startOfDay())->sum('collected_cash'),
            'monthDone' => (clone $doneQ)->where('status', 'delivered')->where('delivered_at', '>=', now()->startOfMonth())->count(),
            'monthFees' => (float) (clone $doneQ)->where('status', 'delivered')->where('delivered_at', '>=', now()->startOfMonth())->get()->sum(fn ($d) => $d->courierFee(auth('web')->user())),
            'notes' => \App\Models\DeliveryEvent::with('user')->whereIn('delivery_id', $ids)->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', '!=', $me->id))->latest()->take(30)->get(),
            'codes' => Delivery::whereIn('id', $ids)->pluck('code', 'id'),
            'staff' => \App\Models\User::whereIn('type_id', [1, 2])->orderBy('name')->get(['id', 'name']),
            'writeoffMax' => (float) \App\Services\AppSettings::get('courier_writeoff_max', 5),
            'rate' => \Illuminate\Support\Facades\Schema::hasColumn('deliveries', 'rating') ? [
                'avg' => (float) Delivery::where('courier_id', $me->id)->whereNotNull('rating')->avg('rating'),
                'n' => Delivery::where('courier_id', $me->id)->whereNotNull('rating')->count(),
                'notes' => Delivery::where('courier_id', $me->id)->whereNotNull('rating')->latest('rated_at')->take(5)->get(['rating', 'rating_note', 'rated_at']),
            ] : null,
            'lastAssigned' => (int) Delivery::where('courier_id', $me->id)->whereIn('status', ['assigned', 'out', 'problem'])->max('id'),
        ]);
    }

    /** فحص كل شوية: فيه توصيلة جديدة؟ */
    public function ping()
    {
        $me = auth()->user();

        return response()->json([
            'last' => (int) Delivery::where('courier_id', $me->id)->whereIn('status', ['assigned', 'out', 'problem'])->max('id'),
            'n' => Delivery::where('courier_id', $me->id)->whereIn('status', ['assigned', 'out', 'problem'])->count(),
        ])->header('Cache-Control', 'no-store');
    }

    /** المندوب يطلب إقفال الحساب: يصل تنبيه للإدارة، ويُقفل عند تأكيد الإدارة الاستلام */
    public function close(Request $request)
    {
        $me = auth()->user();
        $data = $request->validate(['amount' => 'required|numeric|min:0', 'to' => 'nullable|string|max:80', 'note' => 'nullable|string|max:200']);
        $me->close_request = json_encode(['amount' => (float) $data['amount'], 'to' => $data['to'] ?? null, 'note' => $data['note'] ?? null, 'at' => now()->toDateTimeString()], JSON_UNESCAPED_UNICODE);
        $me->save();
        \App\Services\AlertService::push('courier_close', '📨 ' . $me->name . ' يطلب إقفال الحساب',
            'سلّم ' . \App\Services\TreasuryService::fmt($data['amount']) . ' د.ل' . (!empty($data['to']) ? ' إلى ' . $data['to'] : ''), route('sg.couriers', ['id' => $me->id]), 'info', true);

        return redirect()->route('courier.home', ['tab' => 'money'])->with('success', 'تم إرسال طلب إقفال الحساب للإدارة، ويُقفل عند تأكيد الاستلام');
    }

    public function available(Request $request)
    {
        $me = auth()->user();
        $me->courier_available = !$me->courier_available;
        $me->save();

        return back()->with('success', $me->courier_available ? 'أنت متاح للتوصيل اليوم' : 'تم إيقاف استقبال الطلبيات لليوم');
    }

    public function password(Request $request)
    {
        $me = auth()->user();
        $request->validate(['current' => 'required', 'new' => 'required|min:8|confirmed'], ['new.min' => 'كلمة المرور 8 خانات على الأقل', 'new.confirmed' => 'تأكيد كلمة المرور غير مطابق']);
        if (!\Illuminate\Support\Facades\Hash::check($request->current, $me->password)) {
            return back()->with('error', 'كلمة المرور الحالية غير صحيحة');
        }
        $me->password = \Illuminate\Support\Facades\Hash::make($request->new);
        $me->save();

        return back()->with('success', 'تم تغيير كلمة المرور');
    }

    public function out(int $id)
    {
        $d = $this->mine($id);
        CourierService::markOut($d);

        return back()->with('success', 'بالتوفيق 🚚 · ' . $d->code);
    }

    public function done(Request $request, int $id)
    {
        $d = $this->mine($id);
        $data = $request->validate(['cash' => 'nullable|numeric|min:0', 'bank' => 'nullable|numeric|min:0', 'note' => 'nullable|string|max:300', 'writeoff' => 'nullable|boolean',
            'proof' => 'nullable|file|mimes:jpg,jpeg,png,webp,heic|max:10240'], ['proof.max' => 'الصورة كبيرة برشا (أكثر من 10MB)', 'proof.mimes' => 'لازم تكون صورة']);
        // صورة إثبات التسليم (اختيارية)
        if ($request->hasFile('proof') && \Illuminate\Support\Facades\Schema::hasColumn('deliveries', 'proof_path')) {
            $d->proof_path = \App\Support\ImageShrink::store($request->file('proof'), 'proofs/' . now()->format('Y-m'), 'public');
            $d->save();
        }
        $bank = auth()->user()->courierCan('collect_bank') ? (float) ($data['bank'] ?? 0) : 0;
        DB::beginTransaction();
        try {
            CourierService::markDelivered($d, (float) ($data['cash'] ?? 0), $bank, $data['note'] ?? null, $request->boolean('writeoff'));
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()->with('error', $e instanceof \RuntimeException ? $e->getMessage() : 'حدث خطأ، يرجى المحاولة مرة أخرى');
        }

        return redirect()->route('courier.home', ['tab' => 'orders'])->with('success', '✅ تم تسليم ' . $d->code . ' وإبلاغ الإدارة');
    }

    /** ↩ المندوب ألغى التوصيل: الإيصالات ترجع للشركة وتقعد في نفس الرف متاعها (جاهزة للاستلام) */
    public function cancel(Request $request, int $id)
    {
        $d = $this->mine($id);
        abort_unless(in_array($d->status, ['assigned', 'out', 'problem'], true), 403);
        $data = $request->validate(['note' => 'required|string|max:300'], ['note.required' => 'اكتب سبب الإلغاء']);
        $shelves = $d->orders->pluck('storage_num')->filter()->unique()->implode('، ');
        CourierService::returnToShop($d, 'returned', 'المندوب ألغى التوصيل: ' . $data['note']);
        try {
            \App\Services\AlertService::push('problem', '↩ ' . auth()->user()->name . ' ألغى توصيل ' . $d->code, $data['note'] . ' · رجّع الإيصالات ' . $d->orders->pluck('code')->implode('، ') . ($shelves ? ' للرف ' . $shelves : ' للرف'), route('sg.couriers', ['d' => $d->id]), 'warn', true);
        } catch (\Throwable $e) {
        }

        return redirect()->route('courier.home', ['tab' => 'orders'])->with('success', '↩ تلغى التوصيل · رجّع الطلبية للشركة' . ($shelves ? ' (الرف ' . $shelves . ')' : ''));
    }

    public function problem(Request $request, int $id)
    {
        $d = $this->mine($id);
        $data = $request->validate(['note' => 'required|string|max:500'], ['note.required' => 'اكتب وصف المشكلة']);
        CourierService::problem($d, $data['note']);

        return back()->with('success', 'تم إرسال المشكلة للإدارة');
    }

    /** المندوب يعدل رقم/عنوان المستلم (إذا لديه صلاحية) */
    public function recipient(Request $request, int $id)
    {
        abort_unless(auth()->user()->courierCan('edit_recipient'), 403);
        $d = $this->mine($id);
        $data = $request->validate(['recipient_phone' => 'nullable|string|max:30', 'recipient_phone2' => 'nullable|string|max:30', 'address' => 'nullable|string|max:250', 'location_url' => 'nullable|string|max:500']);
        $d->fill(array_filter($data, fn ($v) => $v !== null))->save();
        $d->log($d->status, 'عدّل المندوب بيانات المستلم');

        return back()->with('success', 'تم الحفظ');
    }
}
