<?php

namespace App\Http\Controllers\Sg;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderIssue;
use App\Services\AlertService;
use App\Services\IssueService;
use Illuminate\Http\Request;

/** أي موظف يبلّغ الإدارة: "الكود هذا راجعوه" (نواقص، ما وصلش، تالف، أي شي) */
class FlagController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:40', 'type' => 'required|in:' . implode(',', array_keys(OrderIssue::TYPES)),
            'qty' => 'nullable|integer|min:1|max:9999', 'note' => 'required|string|max:500',
        ], ['code.required' => 'اكتب رقم الإيصال أو كود العميل', 'note.required' => 'اكتب شن المشكلة']);
        $code = trim($data['code']);
        $order = Order::where('code', $code)->first();
        $customer = $order?->customer ?? Customer::where('code', $code)->first();
        if (!$order && $customer) {
            // كود عميل: ناخدو آخر إيصال مفتوح أو آخر إيصال
            $order = Order::where('customer_id', $customer->id)->where('status', '!=', 'canceld')->orderByRaw('delivered = 1')->latest()->first();
        }
        if (!$customer) {
            return back()->withInput()->with('error', 'ما لقيناش إيصال أو عميل بالكود ' . $code);
        }
        $label = OrderIssue::TYPES[$data['type']][0];
        if ($order) {
            try {
                IssueService::create($order, ['type' => $data['type'], 'qty' => $data['qty'] ?? null, 'note' => '⚑ بلاغ ' . auth()->user()->name . ': ' . $data['note'], 'action' => 'later']);
            } catch (\Throwable $e) {
                report($e);
            }
        }
        AlertService::push('flag', '⚑ ' . auth()->user()->name . ' يقول راجعوا: ' . $label,
            ($order ? 'إيصال ' . $order->code . ' · ' : '') . $customer->name . ' (' . $customer->code . ')' . (!empty($data['qty']) ? ' · ' . $data['qty'] . ' قطعة' : '') . ' — ' . $data['note'],
            $order ? route('sg.orders.show', $order) : route('sg.clients', ['id' => $customer->id]), 'warning', true);

        return back()->with('success', 'وصل البلاغ للإدارة ✓' . ($order ? ' وتسجل في المشاكل على ' . $order->code : ''));
    }
}
