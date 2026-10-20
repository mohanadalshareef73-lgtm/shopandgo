<?php

namespace App\Http\Controllers\Sg;

use App\Http\Controllers\Controller;
use App\Models\Log;
use App\Models\Order;
use App\Models\OrderIssue;
use App\Models\OrderSoldout;
use App\Services\TreasuryService as TS;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** 📉 نواقص الإيصال من برا (واجهة الطلبات/الإيصال): نشوفها، نضيف، ونلغي اللي تسجل بالغلط */
class MissingController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:order-list|order-edit', ['only' => ['index']]);
        $this->middleware('permission:order-edit', ['only' => ['store', 'remove', 'settle']]);
    }

    /** كل نواقص الإيصال (اللي ما تقررش فيها + المخصومة) */
    public static function rows(Order $o): array
    {
        $rows = [];
        if ((int) $o->missing_qty > 0 && in_array($o->missing_action, [null, '', 'deduct', 'refund'], true)) {
            $rows[] = ['kind' => 'order', 'id' => $o->id, 'title' => 'قطع ناقصة ما وصلتش', 'qty' => (int) $o->missing_qty, 'value' => (float) $o->missing_value,
                'state' => $o->missing_action === 'deduct' ? 'مخصومة من الإيصال' : ($o->missing_action === 'refund' ? 'اترجعت قيمتها' : 'ما تقررش فيها'), 'done' => in_array($o->missing_action, ['deduct', 'refund'], true)];
        }
        if (Schema::hasTable('order_soldouts')) {
            foreach (OrderSoldout::where('order_id', $o->id)->whereIn('status', ['pending', 'cancelled'])->get() as $s) {
                $rows[] = ['kind' => 'soldout', 'id' => $s->id, 'title' => '🚫 نفد: ' . $s->item, 'qty' => (int) $s->qty, 'value' => (float) $s->price,
                    'state' => $s->status === 'cancelled' ? 'اتلغى وتخصم' : 'بانتظار رد العميل', 'done' => $s->status === 'cancelled'];
            }
        }
        if (Schema::hasTable('order_issues')) {
            foreach (OrderIssue::where('order_id', $o->id)->where('type', 'missing')->where(fn ($w) => $w->where('status', 'open')->orWhere('action', 'deduct'))->get() as $i) {
                $rows[] = ['kind' => 'issue', 'id' => $i->id, 'title' => '📉 ' . ($i->note ?: 'نواقص'), 'qty' => (int) $i->qty, 'value' => (float) $i->value,
                    'state' => $i->status === 'open' ? 'مفتوحة' : ($i->resolution ?: 'محلولة'), 'done' => $i->status !== 'open'];
            }
        }

        return $rows;
    }

    /** عدد النواقص اللي ما تقررش فيها (للزر) */
    public static function openCount(Order $o): int
    {
        return collect(static::rows($o))->where('done', false)->count();
    }

    public function index(Order $order)
    {
        return response()->json(['code' => $order->code, 'rows' => static::rows($order), 'canEdit' => auth()->user()->can('order-edit'), 'remaining' => round(max(0, $order->remaining()), 2)]);
    }

    /** ➕ إضافة نقص: يتسجل «مشكلة نواقص» مفتوحة — تطلع وقت التسليم وتقدر تخصمها */
    public function store(Request $request, Order $order)
    {
        $d = $request->validate(['item' => 'required|string|max:200', 'qty' => 'required|integer|min:1|max:9999', 'value' => 'nullable|numeric|min:0'],
            ['item.required' => 'اكتب شن الناقص', 'qty.required' => 'اكتب كم قطعة']);
        OrderIssue::create(['order_id' => $order->id, 'customer_id' => $order->customer_id, 'type' => 'missing', 'qty' => (int) $d['qty'],
            'value' => isset($d['value']) && $d['value'] !== '' ? round((float) $d['value'], 2) : null, 'action' => 'later',
            'note' => $d['item'], 'status' => 'open', 'created_id' => auth()->id()]);
        Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ' إضافة نواقص (' . $order->code . ')', 'log' => ' ' . $d['item'] . ' · ' . $d['qty'] . ' قطعة' . (!empty($d['value']) ? ' · ' . TS::fmt($d['value']) . ' د.ل' : '')]);

        return response()->json(['ok' => true, 'rows' => static::rows($order->fresh()), 'open' => static::openCount($order->fresh())]);
    }

    /**
     * 💸 تسوية نقص: لو على العميل باقي ← يتخصم من الإيصال، ولو ما عليهش (أو الباقي أقل) ← «استرجاع قيمة» من الخزينة (كاش).
     * للنواقص اللي ما تقررش فيها (إيصال / مشكلة نواقص) وفيها قيمة.
     */
    public function settle(Request $request, Order $order)
    {
        $d = $request->validate(['kind' => 'required|in:order,issue', 'id' => 'required|integer', 'value' => 'nullable|numeric|min:0']);
        try {
            $msg = DB::transaction(function () use ($d, $order) {
                $issue = $d['kind'] === 'issue' ? OrderIssue::where('order_id', $order->id)->where('status', 'open')->findOrFail($d['id']) : null;
                if ($d['kind'] === 'order' && ((int) $order->missing_qty <= 0 || !in_array($order->missing_action, [null, ''], true))) {
                    throw new \RuntimeException('النقص هذا متسوّي من قبل');
                }
                $value = round((float) ($d['value'] ?? 0) ?: (float) ($issue ? $issue->value : $order->missing_value), 2);
                if ($value <= 0) {
                    throw new \RuntimeException('اكتب قيمة النقص أولاً');
                }
                $deduct = round(min($value, max(0, $order->remaining())), 2);
                $refund = round($value - $deduct, 2);
                if ($deduct > 0) {
                    $order->discount = (float) $order->discount + $deduct;
                    $order->discount_reason = trim(($order->discount_reason ? $order->discount_reason . ' + ' : '') . 'نواقص');
                }
                if ($refund > 0) {
                    \App\Services\TreasuryService::withdraw($refund, 'dinar', \App\Services\AppSettings::cashPaymentId(), 'missing_refund', $order->id, 'استرجاع قيمة نواقص · ' . $order->code . ' · ' . optional($order->customer)->name);
                }
                $how = trim(($deduct > 0 ? 'خُصم ' . TS::fmt($deduct) . ' من الإيصال' : '') . ($deduct > 0 && $refund > 0 ? ' و' : '') . ($refund > 0 ? 'رجعنا ' . TS::fmt($refund) . ' د.ل من الخزينة' : ''));
                if ($issue) {
                    $issue->value = $value;
                    $issue->status = 'resolved';
                    $issue->action = $refund > 0 ? 'refund' : 'deduct';
                    $issue->resolution = $how;
                    $issue->resolved_by = auth()->id();
                    $issue->resolved_at = now();
                    $issue->save();
                } else {
                    $order->missing_value = $value;
                    $order->missing_action = $refund > 0 ? 'refund' : 'deduct';
                }
                $order->refreshPaymentStatus();
                $order->save();
                Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ' تسوية نواقص (' . $order->code . ')', 'log' => ' ' . $how]);

                return $how;
            });
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'msg' => $e->getMessage()], 422);
        }
        $o = $order->fresh();

        return response()->json(['ok' => true, 'msg' => $msg, 'rows' => static::rows($o), 'open' => static::openCount($o), 'remaining' => round(max(0, $o->remaining()), 2)]);
    }

    /** ❌ إلغاء نقص تسجل بالغلط — ولو كان مخصوم من الإيصال يرجع الخصم */
    public function remove(Request $request, Order $order)
    {
        $d = $request->validate(['kind' => 'required|in:order,soldout,issue', 'id' => 'required|integer']);
        try {
            DB::transaction(function () use ($d, $order) {
                if ($d['kind'] === 'order') {
                    if ($order->missing_action === 'deduct') {
                        $order->discount = max(0, (float) $order->discount - (float) $order->missing_value);
                    } elseif (!in_array($order->missing_action, [null, ''], true)) {
                        throw new \RuntimeException('النواقص هذي اتسوّت (رصيد/إرجاع فلوس) — ما تنلغاش من هني');
                    }
                    $order->missing_qty = 0;
                    $order->missing_value = 0;
                    $order->missing_action = null;
                    $order->refreshPaymentStatus();
                    $order->save();
                } elseif ($d['kind'] === 'soldout') {
                    $s = OrderSoldout::where('order_id', $order->id)->findOrFail($d['id']);
                    if ($s->status === 'cancelled') {
                        if ((float) $s->credited > 0 || (float) $s->refunded > 0) {
                            throw new \RuntimeException('المنتج هذا اتسوّى (رصيد/إرجاع فلوس) — ما ينلغاش من هني');
                        }
                        $order->discount = max(0, (float) $order->discount - (float) $s->price);
                        $order->refreshPaymentStatus();
                        $order->save();
                    }
                    $s->status = 'void';
                    $s->note = trim(($s->note ? $s->note . ' · ' : '') . 'اتلغى (تسجيل غلط)');
                    $s->save();
                } else {
                    $i = OrderIssue::where('order_id', $order->id)->findOrFail($d['id']);
                    if ($i->status !== 'open') {
                        if ($i->action === 'deduct' && (float) $i->value > 0) {
                            $order->discount = max(0, (float) $order->discount - (float) $i->value);
                            $order->refreshPaymentStatus();
                            $order->save();
                        } else {
                            throw new \RuntimeException('النواقص هذي اتسوّت — ما تنلغاش من هني');
                        }
                    }
                    $i->status = 'resolved';
                    $i->action = 'later';
                    $i->resolution = 'اتلغت (تسجيل غلط)';
                    $i->resolved_by = auth()->id();
                    $i->resolved_at = now();
                    $i->save();
                }
                Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ' إلغاء نواقص (' . $order->code . ')', 'log' => ' ' . $d['kind'] . ' #' . $d['id'] . ' اتلغى']);
            });
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'msg' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'rows' => static::rows($order->fresh()), 'open' => static::openCount($order->fresh())]);
    }
}
