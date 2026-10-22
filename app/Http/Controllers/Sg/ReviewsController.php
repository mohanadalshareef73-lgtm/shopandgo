<?php

namespace App\Http\Controllers\Sg;

use App\Http\Controllers\Controller;
use App\Models\CompanyReview;
use App\Models\Log;
use App\Services\ReviewService;
use Illuminate\Http\Request;

/** ⭐ تقييمات الشركة: الإدارة توافق قبل ما التقييم يطلع للناس */
class ReviewsController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:ticket-list');
    }

    public function index(Request $request)
    {
        $tab = in_array($request->tab, ['pending', 'approved', 'rejected'], true) ? $request->tab : 'pending';
        $has = \Illuminate\Support\Facades\Schema::hasTable('company_reviews');
        $rows = $has ? CompanyReview::with(['customer:id,name,code,phone_1', 'order:id,code', 'decider:id,name'])->where('status', $tab)->latest()->paginate(24)->withQueryString() : collect();
        $counts = $has ? CompanyReview::selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status') : collect();

        return view('sg.reviews.index', ['nav' => 'reviews', 'tab' => $tab, 'rows' => $rows, 'counts' => $counts, 'stats' => ReviewService::stats(), 'has' => $has]);
    }

    public function act(Request $request, CompanyReview $review)
    {
        $do = $request->validate(['do' => 'required|in:approve,reject,pending,delete'])['do'];
        if ($do === 'delete') {
            Log::create(['user_id' => auth()->id(), 'type' => 'review', 'title' => ' حذف تقييم #' . $review->id, 'log' => ' ' . $review->rating . '/5 · ' . mb_substr((string) $review->body, 0, 120)]);
            $review->delete();
            ReviewService::forget();

            return back()->with('success', '🗑 انحذف التقييم');
        }
        $review->status = ['approve' => 'approved', 'reject' => 'rejected', 'pending' => 'pending'][$do];
        $review->decided_by = auth()->id();
        $review->decided_at = now();
        $review->save();
        ReviewService::forget();

        return back()->with('success', $do === 'approve' ? '✓ التقييم طلع للناس' : ($do === 'reject' ? '✕ التقييم ما يطلعش' : 'رجع للمراجعة'));
    }
}
