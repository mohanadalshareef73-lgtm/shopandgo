<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Http\Controllers\Sg\OrdersController;
use Illuminate\Support\Collection;

/**
 * التذكيرات:
 *  - الرف (أوتوماتيك): الطرد السابق عليه N يوم في الرف يأخذ رسالة، وتتعاود كل X يوم حتى حد أقصى
 *  - الديون (بتأكيد): النظام يجهز القائمة وينبه الإدارة، وما ينبعتش شي حتى تأكد
 */
class ReminderService
{
    /** الطرود الذي يجب أن يأخذوا تذكير رف الآن */
    public static function shelfDue(): Collection
    {
        $days = (int) AppSettings::get('shelf_reminder_days', 5);
        $every = max(1, (int) AppSettings::get('shelf_remind_every', 3));
        $max = (int) AppSettings::get('shelf_remind_max', 3);

        return Order::with('customer')->where('arrived', true)->where('delivered', false)->where('status', '!=', 'canceld')
            ->whereNull('delivery_id')->get()
            ->filter(fn ($o) => $o->shelfDays() !== null && $o->shelfDays() >= $days)
            ->filter(fn ($o) => !$max || (int) $o->shelf_reminders < $max)
            ->filter(fn ($o) => !$o->shelf_reminded_at || \Carbon\Carbon::parse($o->shelf_reminded_at)->lte(now()->subDays($every)->addHour()))
            ->values();
    }

    /** يبعث تذكير الرف (رسالة وحدة لكل عميل فيها كل إيصالاته) */
    public static function sendShelf(?Collection $orders = null): array
    {
        $orders = $orders ?? static::shelfDue();
        $sent = 0;
        $failed = 0;
        foreach ($orders->groupBy('customer_id') as $g) {
            $o = $g->sortByDesc(fn ($x) => $x->shelfDays())->first();
            if (!$o->customer) {
                continue;
            }
            $ok = MessageService::sendTemplate('shelf_reminder', $o->customer, MessageService::orderVars($o, ['{رقم_الإيصال}' => $g->pluck('code')->implode('، ')]), $o);
            $ok ? $sent++ : $failed++;
            if ($ok) {
                Order::whereIn('id', $g->pluck('id'))->update(['shelf_reminded_at' => now()]);
                Order::whereIn('id', $g->pluck('id'))->increment('shelf_reminders');
            }
        }

        return ['customers' => $orders->pluck('customer_id')->unique()->count(), 'orders' => $orders->count(), 'sent' => $sent, 'failed' => $failed];
    }

    /** الطرود الذي فاتت الحد الأقصى من التذكيرات ولا يزال في الرف (تنبيه للإدارة تتصلوا بهم) */
    public static function shelfStuck(): Collection
    {
        $max = (int) AppSettings::get('shelf_remind_max', 3);
        if (!$max) {
            return collect();
        }

        return Order::with('customer')->where('arrived', true)->where('delivered', false)->where('status', '!=', 'canceld')
            ->whereNull('delivery_id')->where('shelf_reminders', '>=', $max)->get();
    }

    /** العملاء الذين يستحقوا تذكير دين (لا يُرسل حتى الإدارة تأكد) */
    public static function debtDue(): Collection
    {
        $minAge = (int) AppSettings::get('debt_remind_days', 14);
        $every = max(1, (int) AppSettings::get('debt_remind_every', 7));
        $minAmount = (float) AppSettings::get('debt_remind_min', 5);
        $orders = Order::with('customer')->where('status', '!=', 'canceld')->whereRaw(OrdersController::debtSql())->get();

        return $orders->groupBy('customer_id')->map(function ($g) {
            $c = $g->first()->customer;
            $oldest = $g->min('created_at');

            return (object) [
                'customer' => $c, 'orders' => $g, 'due' => round($g->sum(fn ($o) => max(0, $o->remaining())), 2),
                'age' => $oldest ? (int) $oldest->diffInDays(now()) : 0,
                'last' => $c ? $c->debt_reminded_at : null,
                'gone' => $g->where('delivered', true)->count(),
            ];
        })->filter(fn ($r) => $r->customer && $r->due >= $minAmount && $r->age >= $minAge)
            ->filter(fn ($r) => !$r->last || \Carbon\Carbon::parse($r->last)->lte(now()->subDays($every)->addHour()))
            ->sortByDesc('due')->values();
    }

    public static function debtText(Customer $c, $row = null): string
    {
        $vars = MessageService::customerVars($c, ['{أيام_الدين}' => $row->age ?? '', '{رقم_الإيصال}' => $row ? $row->orders->pluck('code')->implode('، ') : '']);
        $tpl = MessageService::template('debt_reminder');

        return $tpl ? MessageService::render($tpl->message, $vars) : '';
    }

    public static function sendDebt(array $customerIds): array
    {
        $sent = 0;
        $failed = 0;
        $rows = static::debtRowsFor($customerIds);
        foreach ($rows as $r) {
            $ok = MessageService::sendTemplate('debt_reminder', $r->customer, MessageService::customerVars($r->customer, ['{أيام_الدين}' => $r->age, '{رقم_الإيصال}' => $r->orders->pluck('code')->implode('، ')]));
            $ok ? $sent++ : $failed++;
            if ($ok) {
                $r->customer->debt_reminded_at = now();
                $r->customer->save();
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /** نفس صفوف الدين لزبائن معينين (بدون شروط العمر والتكرار) */
    public static function debtRowsFor(array $ids): Collection
    {
        $orders = Order::with('customer')->whereIn('customer_id', $ids)->where('status', '!=', 'canceld')->whereRaw(OrdersController::debtSql())->get();

        return $orders->groupBy('customer_id')->map(fn ($g) => (object) [
            'customer' => $g->first()->customer, 'orders' => $g, 'due' => round($g->sum(fn ($o) => max(0, $o->remaining())), 2),
            'age' => ($m = $g->min('created_at')) ? (int) $m->diffInDays(now()) : 0,
        ])->filter(fn ($r) => $r->customer)->values();
    }
}
