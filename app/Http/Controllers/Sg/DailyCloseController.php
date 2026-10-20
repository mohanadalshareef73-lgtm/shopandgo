<?php

namespace App\Http\Controllers\Sg;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Shift;
use App\Models\TreasuryHistory;
use App\Models\User;
use App\Services\AppSettings;
use App\Services\ShiftService;
use Illuminate\Http\Request;

/** 📒 إقفال اليومية مع الموظف: كم قبض · كم طلّع · كم لازم يسلّم + سجل الدفعات والتسليمات */
class DailyCloseController extends Controller
{
    public function index(Request $request)
    {
        $me = auth()->user();
        $isBoss = $me->can('user-edit');
        $user = $isBoss && $request->filled('user') ? User::findOrFail((int) $request->user) : $me;
        $shift = $request->filled('shift') ? Shift::where('user_id', $user->id)->findOrFail((int) $request->shift) : ShiftService::current($user);
        $sum = ShiftService::summary($user, $shift);
        $since = $sum['since'];
        $until = $shift && $shift->closed_at ? $shift->closed_at : now();
        $cashId = AppSettings::cashPaymentId();

        $moves = TreasuryHistory::where('created_id', $user->id)->whereBetween('created_at', [$since, $until])
            ->whereIn('status', ['pay', 'pull'])->orderBy('created_at')->get();
        $orderIds = $moves->where('type', 'order')->pluck('procedure_id')->filter()->unique();
        $orders = $orderIds->isNotEmpty() ? Order::with('customer')->whereIn('id', $orderIds)->get()->keyBy('id') : collect();
        $delivered = Order::with('customer')->where('delivered_by', $user->id)->whereBetween('delivered_time', [$since, $until])->orderBy('delivered_time')->get();

        $cur = fn ($m) => $m->currency ?: 'dinar';
        $dinar = $moves->filter(fn ($m) => $cur($m) === 'dinar');

        return view('sg.daily.index', [
            'nav' => 'daily', 'user' => $user, 'isBoss' => $isBoss, 'shift' => $shift, 'sum' => $sum, 'since' => $since, 'until' => $until,
            'moves' => $moves, 'orders' => $orders, 'delivered' => $delivered, 'cashId' => $cashId,
            'pm' => PaymentMethod::pluck('name', 'id'),
            'bankIn' => (float) $dinar->where('payment_id', '!=', $cashId)->where('status', 'pay')->sum('price'),
            'staff' => $isBoss ? User::whereIn('type_id', [1, 2])->where('status', true)->orderBy('name')->get(['id', 'name']) : collect(),
            'past' => Shift::where('user_id', $user->id)->whereNotNull('closed_at')->latest('closed_at')->take(10)->get(),
        ]);
    }
}
