<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Log;
use App\Models\Order;
use App\Services\AppSettings;
use App\Services\CourierService;
use App\Support\DeliveryLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** 🛵 العميل يطلب مندوب من رابط رسالة «جاهز للاستلام» — من غير ما يدخل لحسابه */
class DeliveryRequestController extends Controller
{
    protected function load(int $order, string $t): Order
    {
        abort_unless(DeliveryLink::check($order, $t), 404);
        if (!DeliveryLink::enabled()) {
            // 🚫 طلب التوصيل عبر المنظومة موقوف
            abort(response()->view('portal.delivery-off', [], 200));
        }

        return Order::with('customer')->findOrFail($order);
    }

    /** طلبياته اللي ينفع تتوصل: ما تسلمتش، مش ملغية، ومش مع مندوب توا */
    protected function openOrders(Order $o)
    {
        $active = Delivery::whereIn('status', ['requested', 'assigned', 'out', 'problem'])->pluck('id');

        return Order::where('customer_id', $o->customer_id)->where('delivered', false)->where('status', '!=', 'canceld')
            ->where(fn ($w) => $w->whereNull('delivery_id')->orWhereNotIn('delivery_id', $active))
            ->where(fn ($w) => $w->where('arrived', true)->orWhere('id', $o->id))
            ->orderBy('arrived_at')->get();
    }

    public function show(int $order, string $t)
    {
        $o = $this->load($order, $t);
        $dl = $o->delivery_id ? Delivery::find($o->delivery_id) : null;

        return view('portal.delivery-request', [
            'o' => $o, 'c' => $o->customer, 't' => $t, 'dl' => $dl && in_array($dl->status, ['requested', 'assigned', 'out', 'problem', 'delivered'], true) ? $dl : null,
            'orders' => $o->delivered ? collect() : $this->openOrders($o), 'fee' => (float) AppSettings::get('courier_default_fee', 0),
        ]);
    }

    public function store(Request $request, int $order, string $t)
    {
        $o = $this->load($order, $t);
        $data = $request->validate([
            'name' => 'required|string|max:120', 'phone' => 'required|string|max:30', 'city' => 'required|string|max:80', 'area' => 'required|string|max:120',
            'address' => 'nullable|string|max:250', 'location_url' => 'nullable|string|max:500', 'note' => 'nullable|string|max:500',
            'pay_type' => 'required|in:cash,bank', 'receipt' => 'nullable|required_if:pay_type,bank|file|mimes:jpg,jpeg,png,webp,pdf|max:6144',
            'ids' => 'nullable|array', 'ids.*' => 'integer',
        ], ['name.required' => 'اكتب اسمك', 'phone.required' => 'اكتب رقم هاتفك', 'city.required' => 'اكتب المدينة', 'area.required' => 'اكتب المنطقة',
            'pay_type.required' => 'اختار طريقة الدفع', 'receipt.required_if' => 'ارفع صورة إيصال التحويل', 'receipt.mimes' => 'الإيصال لازم صورة أو PDF', 'receipt.max' => 'صورة الإيصال كبيرة (أقصى 6 ميقا)']);
        $data['address'] = CourierService::withArea($data['area'], $data['address'] ?? '');
        $c = $o->customer;
        abort_unless($c, 404);
        $ids = array_map('intval', $data['ids'] ?? [$o->id]);
        $orders = $this->openOrders($o)->whereIn('id', $ids)->values();
        if ($orders->isEmpty()) {
            return back()->withInput()->with('error', 'الطلبية هذي مسجلة توصيلها قبل، أو تسلمت ✓');
        }
        $receipt = null;
        if ($request->hasFile('receipt')) {
            try {
                $receipt = \App\Support\ImageShrink::store($request->file('receipt'), 'receipts', 'public');
            } catch (\Throwable $e) {
                report($e);

                return back()->withInput()->with('error', 'ما قدرناش نحفظو صورة الإيصال، جرب صورة ثانية');
            }
        }
        DB::beginTransaction();
        try {
            // 🛵 بدون مناديب: الطلب يقعد في «طلبيات دليفري» وبياناته تمشي لقروب الدليفري
            $dl = CourierService::assign($c, $orders, [
                'recipient_name' => $data['name'], 'recipient_phone' => $data['phone'], 'city' => $data['city'], 'area' => $data['area'],
                'address' => $data['address'], 'location_url' => $data['location_url'] ?? null,
                'fee' => 0, 'pay_type' => $data['pay_type'], 'bank_receipt' => $receipt, 'note' => $data['note'] ?? null,
            ], null, 'customer');
            if (\App\Support\Fast::hasColumn('deliveries', 'kind')) {
                $dl->kind = 'order';
                $dl->saveQuietly();
            }
            CourierService::rememberCity($c, $data['city'], $data['area']);
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

            return back()->withInput()->with('error', 'صار خطأ، عاود جرب بعد شوية');
        }
        try {
            \App\Services\AlertService::push('delivery_request', '🛵 طلب توصيل من الرابط · ' . $dl->code, $c->name . ' · ' . $data['phone'] . ' · ' . $data['city'] . ' - ' . $data['area'] . ($receipt ? ' · 🏦 دافع تحويل' : ''), route('sg.dispatch'), 'info');
        } catch (\Throwable $e) {
        }
        try {
            Log::create(['user_id' => null, 'type' => 'order', 'title' => ' طلب توصيل من الرابط (' . $c->name . ')', 'log' => ' ' . $dl->code . ' · ' . $orders->pluck('code')->implode('، ')]);
        } catch (\Throwable $e) {
        }

        return redirect()->route('deliv.req', ['order' => $o->id, 't' => $t])->with('success', self::DONE_MSG);
    }

    public const DONE_MSG = '✅ تم تسجيل طلبك في قائمة التوصيل، سنتواصل معك قريباً';
}
