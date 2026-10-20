<?php

namespace App\Http\Controllers\Sg;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderIssue;
use App\Services\IssueService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** مشاكل الطلبات: إضافة، حل، وقائمة */
class IssuesController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:order-list', ['only' => ['index']]);
        $this->middleware('permission:order-edit', ['only' => ['store', 'resolve']]);
    }

    public function index(Request $request)
    {
        $status = in_array($request->status, ['open', 'resolved', 'all'], true) ? $request->status : 'open';
        $q = OrderIssue::with(['order', 'customer', 'creator', 'resolver'])->latest();
        if ($status !== 'all') {
            $q->where('status', $status);
        }
        if ($request->filled('type') && isset(OrderIssue::TYPES[$request->type])) {
            $q->where('type', $request->type);
        }

        return view('sg.issues.index', [
            'nav' => 'ord', 'rows' => $q->paginate(40)->withQueryString(), 'status' => $status,
            'counts' => OrderIssue::selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status'),
            'payments' => \App\Models\PaymentMethod::where('status', true)->get(),
        ]);
    }

    public function store(Request $request, Order $order)
    {
        $data = $request->validate([
            'type' => 'required|in:' . implode(',', array_keys(OrderIssue::TYPES)),
            'qty' => 'nullable|integer|min:1|max:9999', 'value' => 'nullable|numeric|min:0',
            'action' => 'nullable|in:' . implode(',', array_keys(OrderIssue::ACTIONS)),
            'payment_id' => 'nullable|exists:payment_methods,id', 'note' => 'nullable|string|max:500',
            'send_whatsapp' => 'nullable|boolean',
        ], ['type.required' => 'اختر نوع المشكلة']);
        DB::beginTransaction();
        try {
            $issue = IssueService::create($order, $data);
            DB::commit();
        } catch (\RuntimeException $e) {
            DB::rollBack();
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'msg' => $e->getMessage()], 422);
            }

            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'msg' => 'ما تحفظتش: ' . mb_substr($e->getMessage(), 0, 160)], 500);
            }

            return back()->withInput()->with('error', 'المشكلة ما تحفظتش، عاود');
        }
        try {
            $sent = IssueService::notify($issue, $request->boolean('send_whatsapp'));
        } catch (\Throwable $e) {
            report($e);
            $sent = false;
        }
        if ($request->expectsJson()) { // 💾 من النافذة: نحفظو من غير ما الصفحة تتحدث (ما يضيعش اللي كنت تكتب فيه)
            return response()->json(['ok' => true, 'msg' => '✓ سُجّلت المشكلة (' . $issue->typeLabel() . ') على ' . $order->code . ($request->boolean('send_whatsapp') ? ($sent ? ' · أُبلغ العميل' : ' · الواتساب ما مشاش (ظهرت في حسابه)') : '')]);
        }

        return back()->with('success', 'سُجّلت المشكلة (' . $issue->typeLabel() . ') على ' . $order->code . ($issue->status === 'resolved' ? ' وحُسمت مالياً' : ' · مفتوحة حتى تُحل') . ($request->boolean('send_whatsapp') ? ($sent ? ' · أُبلغ العميل ✓' : ' · تعذّر الواتساب (ظهرت في حسابه)') : ' · ظهرت في حساب العميل'));
    }

    public function resolve(Request $request, OrderIssue $issue)
    {
        $data = $request->validate([
            'value' => 'nullable|numeric|min:0', 'action' => 'nullable|in:' . implode(',', array_keys(OrderIssue::ACTIONS)),
            'payment_id' => 'nullable|exists:payment_methods,id', 'resolution' => 'nullable|string|max:500', 'send_whatsapp' => 'nullable|boolean',
        ]);
        DB::beginTransaction();
        try {
            IssueService::resolve($issue, $data);
            DB::commit();
        } catch (\RuntimeException $e) {
            DB::rollBack();

            return back()->with('error', $e->getMessage());
        }
        IssueService::notify($issue->fresh(), $request->boolean('send_whatsapp'), true);

        return back()->with('success', 'حُلّت المشكلة · ' . $issue->fresh()->resolution);
    }
}
