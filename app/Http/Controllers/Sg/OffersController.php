<?php

namespace App\Http\Controllers\Sg;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Offer;
use App\Models\Order;
use App\Services\CouponService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** العروض والخصومات والمناسبات + الكوبونات */
class OffersController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:offer-manage', ['except' => ['applyCoupon']]);
        $this->middleware('permission:order-edit', ['only' => ['applyCoupon']]);
    }

    public function index(Request $request)
    {
        return view('sg.offers.index', [
            'nav' => 'offers',
            'offers' => Offer::with('coupon')->latest()->paginate(20),
            'coupons' => Coupon::with(['customer', 'offer'])->latest()->take(30)->get(),
            'live' => Offer::live()->count(),
            'used' => \App\Models\CouponUse::sum('amount'),
            'customers' => Customer::count(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'kind' => 'required|in:' . implode(',', array_keys(Offer::KINDS)), 'title' => 'required|string|max:150', 'body' => 'nullable|string|max:1000',
            'discount_type' => 'nullable|in:percent,amount', 'discount_value' => 'nullable|numeric|min:0|max:100000',
            'max_discount' => 'nullable|numeric|min:0', 'min_order' => 'nullable|numeric|min:0', 'uses_per_customer' => 'nullable|integer|min:1|max:100',
            'coupon_code' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-]+$/'],
            'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date|after_or_equal:starts_at', 'notify' => 'nullable|boolean',
        ], ['title.required' => 'اكتب عنوان العرض', 'coupon_code.regex' => 'كود الكوبون حروف إنجليزية وأرقام فقط', 'ends_at.after_or_equal' => 'تاريخ النهاية قبل البداية']);
        if (($data['discount_type'] ?? null) === 'percent' && (float) ($data['discount_value'] ?? 0) > 100) {
            return back()->withInput()->with('error', 'النسبة لا تزيد عن 100%');
        }
        DB::beginTransaction();
        try {
            $offer = CouponService::createOffer($data, $request->boolean('notify'));
            DB::commit();
        } catch (\RuntimeException $e) {
            DB::rollBack();

            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'نُشر «' . $offer->title . '»' . ($offer->coupon_code ? ' · كود الكوبون ' . $offer->coupon_code : '') . ($offer->notified ? ' · وصل إشعار لـ ' . $offer->notified . ' عميل في حساباتهم' : ''));
    }

    public function toggle(Offer $offer)
    {
        $offer->active = !$offer->active;
        $offer->save();
        Coupon::where('offer_id', $offer->id)->update(['active' => $offer->active]);

        return back()->with('success', $offer->active ? 'تم تفعيل العرض' : 'تم إيقاف العرض وكوبونه');
    }

    /** كوبون يدوي لعميل معيّن */
    public function coupon(Request $request)
    {
        $data = $request->validate([
            'customer_id' => 'nullable|exists:customers,id', 'type' => 'required|in:percent,amount', 'value' => 'required|numeric|min:0.01',
            'max_discount' => 'nullable|numeric|min:0', 'expires_at' => 'nullable|date', 'uses_limit' => 'nullable|integer|min:1',
        ]);
        $c = Coupon::create($data + ['code' => CouponService::newCode('CP'), 'source' => 'manual', 'created_id' => auth()->id(), 'uses_limit' => $data['uses_limit'] ?? 1]);
        if ($c->customer) {
            \App\Services\CustomerNotify::inbox($c->customer, '🎟 لديك كوبون خصم ' . $c->label() . ': ' . $c->code . ($c->expires_at ? ' · صالح حتى ' . $c->expires_at->format('Y-m-d') : ''), null, 'coupon');
        }

        return back()->with('success', 'أُنشئ الكوبون ' . $c->code);
    }

    /** تطبيق كوبون على إيصال (من صفحة الإيصال) */
    public function applyCoupon(Request $request, Order $order)
    {
        $data = $request->validate(['code' => 'required|string|max:30'], ['code.required' => 'اكتب كود الكوبون']);
        DB::beginTransaction();
        try {
            $v = CouponService::apply($order, $data['code']);
            DB::commit();
        } catch (\RuntimeException $e) {
            DB::rollBack();

            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'طُبّق الكوبون · خصم ' . \App\Services\TreasuryService::fmt($v) . ' د.ل');
    }
}
