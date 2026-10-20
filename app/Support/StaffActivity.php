<?php

namespace App\Support;

use App\Models\Log;
use App\Models\Order;
use App\Models\PaidHistory;
use App\Models\User;
use App\Services\AppSettings;
use Illuminate\Support\Facades\Schema;

/** حركة الموظفين في الفترة: قداش قبضوا، قداش سلّموا، قداش سجلوا… */
class StaffActivity
{
    public static function for($from, $to): array
    {
        $cashId = AppSettings::cashPaymentId();
        $rows = [];
        $add = function ($uid, $k, $v = 1) use (&$rows) {
            if (!$uid) {
                return;
            }
            $rows[$uid][$k] = ($rows[$uid][$k] ?? 0) + $v;
        };
        $pays = [];
        if (Schema::hasColumn('paid_histories', 'created_id')) {
            foreach (PaidHistory::with(['order:id,code', 'paymentMethod:id,name'])->whereBetween('created_at', [$from, $to])->where('paid_price', '>', 0)->get() as $p) {
                $add($p->created_id, 'money', (float) $p->paid_price);
                $add($p->created_id, (int) $p->payment_id === (int) $cashId ? 'cash' : 'bank', (float) $p->paid_price);
                $add($p->created_id, 'pays');
                $pays[$p->created_id][] = [optional($p->order)->code, (float) $p->paid_price, optional($p->paymentMethod)->name, optional($p->created_at)->format('g:i a')];
            }
        }
        $dels = [];
        foreach (Order::where('delivered', true)->whereNotNull('delivered_by')->whereBetween('delivered_time', [$from, $to])->get(['id', 'code', 'delivered_by', 'delivered_time']) as $o) {
            $add($o->delivered_by, 'delivered');
            $dels[$o->delivered_by][] = $o->code;
        }
        foreach (Order::whereBetween('created_at', [$from, $to])->where('status', '!=', 'canceld')->get(['created_id', 'total_price']) as $o) {
            $add($o->created_id, 'created');
            $add($o->created_id, 'createdValue', (float) $o->total_price);
        }
        foreach (Order::whereNotNull('received_by')->whereBetween('received_at', [$from, $to])->get(['received_by']) as $o) {
            $add($o->received_by, 'arrived');
        }
        if (Schema::hasColumn('cart_quotes', 'booked_by')) {
            foreach (\App\Models\CartQuote::whereNotNull('booked_by')->whereBetween('booked_done_at', [$from, $to])->get(['booked_by']) as $c) {
                $add($c->booked_by, 'booked');
            }
        }
        foreach (Log::whereBetween('created_at', [$from, $to])->selectRaw('user_id, COUNT(*) n, MIN(created_at) a, MAX(created_at) b')->groupBy('user_id')->get() as $l) {
            if ($l->user_id) {
                $rows[$l->user_id]['actions'] = (int) $l->n;
                $rows[$l->user_id]['first'] = \Carbon\Carbon::parse($l->a)->format('g:i a');
                $rows[$l->user_id]['last'] = \Carbon\Carbon::parse($l->b)->format('g:i a');
            }
        }
        // ↩ إلغاء تسليم + ⚠ إيصالات سُجّلت رغم التكرار
        foreach (['undo' => '%إلغاء تسليم%', 'dupOver' => '%رغم التكرار%'] as $k => $like) {
            foreach (Log::whereBetween('created_at', [$from, $to])->where('title', 'like', $like)->get(['user_id', 'title']) as $l) {
                if (!$l->user_id) {
                    continue;
                }
                $add($l->user_id, $k);
                $rows[$l->user_id][$k . 'List'][] = trim(preg_replace('/^.*\((.*)\).*$/u', '$1', (string) $l->title));
            }
        }
        $users = User::whereIn('id', array_keys($rows))->get()->keyBy('id');
        $out = [];
        foreach ($rows as $uid => $r) {
            $u = $users[$uid] ?? null;
            if (!$u || $u->isCourier()) {
                continue; // المندوبين عندهم صفحتهم
            }
            $out[] = $r + ['user' => $u, 'payList' => $pays[$uid] ?? [], 'delList' => $dels[$uid] ?? []];
        }
        usort($out, fn ($a, $b) => ($b['money'] ?? 0) <=> ($a['money'] ?? 0) ?: ($b['actions'] ?? 0) <=> ($a['actions'] ?? 0));

        return $out;
    }
}
