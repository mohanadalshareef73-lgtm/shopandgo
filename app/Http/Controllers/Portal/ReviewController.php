<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\CompanyReview;
use App\Models\Order;
use App\Services\ReviewService;
use Illuminate\Http\Request;

/** ⭐ صفحة التقييم العامة (رابط في رسالة الواتساب بعد التسليم) + صفحة التقييمات + الأرقام الحية */
class ReviewController extends Controller
{
    protected function load(int $order, string $t): Order
    {
        abort_unless(hash_equals(ReviewService::token($order), $t), 404);

        return Order::with('customer')->findOrFail($order);
    }

    public function form(int $order, string $t)
    {
        $o = $this->load($order, $t);
        $done = CompanyReview::where('order_id', $o->id)->first();

        return view('portal.review', ['o' => $o, 't' => $t, 'done' => $done]);
    }

    public function store(Request $request, int $order, string $t)
    {
        $o = $this->load($order, $t);
        if (CompanyReview::where('order_id', $o->id)->exists()) {
            return back()->with('success', 'وصلنا تقييمك قبل، شكراً 💙');
        }
        $data = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'body' => 'nullable|string|max:1000',
            'photo' => 'nullable|image|max:8192',
        ], ['rating.required' => 'اختار عدد النجوم', 'photo.image' => 'الملف لازم يكون صورة', 'photo.max' => 'الصورة كبيرة شوية']);
        $photo = null;
        if ($request->hasFile('photo')) {
            try {
                $photo = \App\Support\ImageShrink::store($request->file('photo'), 'reviews', 'public');
            } catch (\Throwable $e) {
                report($e);
            }
        }
        $r = CompanyReview::create([
            'customer_id' => $o->customer_id, 'order_id' => $o->id, 'rating' => (int) $data['rating'],
            'body' => trim(strip_tags((string) ($data['body'] ?? ''))) ?: null, 'photo' => $photo, 'status' => 'pending',
        ]);
        ReviewService::forget();
        try {
            \App\Services\AlertService::push('review', '⭐ تقييم جديد (' . $r->rating . '/5) يستنى موافقتك',
                optional($o->customer)->name . ' · ' . $o->code . ($r->body ? ' — ' . mb_substr($r->body, 0, 80) : ''), route('sg.reviews'), $r->rating <= 2 ? 'warning' : 'info');
        } catch (\Throwable $e) {
        }

        return back()->with('success', 'شكراً على تقييمك 💙');
    }

    /** صفحة عامة: كل التقييمات اللي وافقت عليها الإدارة */
    public function index()
    {
        return view('portal.reviews', ['stats' => ReviewService::stats(), 'rows' => CompanyReview::with('customer:id,name')->where('status', 'approved')->latest('decided_at')->paginate(24)]);
    }

    /** 📊 الأرقام الحية (للموقع والإعلانات): كم سلّمنا، كم زبون، متوسط النجوم */
    public function stats()
    {
        return response()->json(ReviewService::stats())->header('Access-Control-Allow-Origin', '*');
    }
}
