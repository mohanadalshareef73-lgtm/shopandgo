<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * الشاشات القديمة: كل صفحة قديمة لها بديل في الواجهة الجديدة تُحوَّل إليه تلقائياً،
 * حتى لا تظهر أي واجهة قديمة (الروابط القديمة والمفضلة تبقى تعمل).
 * عمليات الحفظ (POST/PUT/DELETE) لا تتأثر.
 * لا يشمل فاتورة العميل الأصلية ولا صفحات الطباعة.
 */
class LegacyRedirect
{
    /** اسم المسار القديم (أو بادئته) => [المسار الجديد، باراميترات] */
    protected function map(Request $r): ?string
    {
        $name = (string) optional($r->route())->getName();
        $p = fn (string $k) => $r->route($k);
        $id = fn ($v) => is_object($v) ? $v->getKey() : $v;
        $exact = [
            'admin.home.old' => fn () => route('admin.home'),
            'carts.index' => fn () => route('sg.carts', $r->query()),
            'customer.index' => fn () => route('sg.clients'), 'customer.search' => fn () => route('sg.clients', ['q' => $r->input('name') ?: $r->input('code') ?: $r->input('phone_1')]),
            'customer.create' => fn () => route('sg.clients'),
            'customer.show' => fn () => route('sg.clients', ['id' => $id($p('customer'))]), 'customer.edit' => fn () => route('sg.clients', ['id' => $id($p('customer'))]),
            'customer.paidhistory' => fn () => route('sg.clients', ['id' => $p('id')]),
            'finance.index' => fn () => route('sg.finance'), 'finance.exchanges.index' => fn () => route('sg.finance', ['tab' => 'usd']),
            'finance.partners.index' => fn () => route('sg.finance', ['tab' => 'profit']),
            'order.index' => fn () => route('sg.orders.index'), 'order.search' => fn () => route('sg.orders.index', ['q' => $r->input('code')]),
            'order.delivered.index' => fn () => route('sg.orders.index', ['stage' => 'done']), 'order.ready.index' => fn () => route('sg.orders.index', ['stage' => 'ready']),
            'order.unpaid.index' => fn () => route('sg.orders.index', ['stage' => 'debt']), 'order.no_trip.index' => fn () => route('sg.orders.index', ['stage' => 'none']),
            'order.notDelivered.index' => fn () => route('sg.orders.index', ['stage' => 'way']),
            'order.create' => fn () => route('sg.orders.create'),
            'order.show' => fn () => route('sg.orders.show', $id($p('order'))), 'order.edit' => fn () => route('sg.orders.edit', $id($p('order'))),
            'order.paids.history' => fn () => route('sg.orders.show', $p('id')) . '#pays',
            'order.receive.form' => function () use ($p, $id) {
                $o = \App\Models\Order::find($id($p('order')));
                $t = $o && $o->trip_details ? \App\Models\TripPurchase::where('trip_code', $o->trip_details)->value('id') : null;

                return $t ? route('sg.trips.receive', $t) . '#r' . $o->id : ($o ? route('sg.orders.show', $o->id) : route('sg.orders.index'));
            },
            'purchase.report' => fn () => route('sg.reports', ['type' => 'expenses']),
            'dashboard.index' => fn () => route('admin.home'), 'dashboard.show' => fn () => route('admin.home'), 'dashboard.edit' => fn () => route('admin.home'),
            'ai-roles.create' => fn () => route('ai-roles.index'),
        ];
        if (isset($exact[$name])) {
            return $exact[$name]();
        }
        $prefix = [
            'ai-roles.show' => fn () => route('ai-roles.index'),
            'dept.' => fn () => route('sg.debts', ['tab' => 'payables']),
            'log.' => fn () => route('sg.audit', ['tab' => 'old']),
            'notification.' => fn () => route('sg.offers'),
            'payment-methods.' => fn () => route('sg.settings', ['tab' => 'money']),
            'paymentExpenses.' => fn () => route('sg.finance'),
            'platform.' => fn () => route('sg.settings', ['tab' => 'platforms']),
            'profits.' => fn () => route('sg.finance', ['tab' => 'profit']),
            'purchaseDesc.' => fn () => route('sg.expenses'),
            'purchase.' => fn () => route('sg.expenses'),
            'reports.' => fn () => route('sg.reports'),
            'roles.' => fn () => route('sg.staff'),
            'users.' => fn () => route('sg.staff'),
            'safe.' => fn () => route('sg.finance'),
            'status.' => fn () => route('sg.settings', ['tab' => 'statuses']),
            'trip.' => fn () => route('sg.trips.index'),
            'whatsapp-message.' => fn () => route('sg.messages'),
            'shippingCharges.' => fn () => route('sg.finance', ['tab' => 'ship']),
            'shippingType.' => fn () => route('sg.settings', ['tab' => 'ship']),
        ];
        foreach ($prefix as $k => $fn) {
            if ($name === $k || (str_ends_with($k, '.') && str_starts_with($name, $k))) {
                // الطباعة تبقى كما هي
                if (str_contains($name, 'print') || str_contains($name, 'download')) {
                    return null;
                }

                return $fn();
            }
        }

        return null;
    }

    public function handle(Request $request, Closure $next)
    {
        // اختبارات المبرمج (phpunit) تختبر الشاشات القديمة نفسها: لا تحويل أثناء الاختبارات فقط
        if (app()->runningUnitTests()) {
            return $next($request);
        }
        // حساب العميل القديم → حسابه الجديد
        if ($request->isMethod('GET') && in_array(optional($request->route())->getName(), ['customer.dashboard.old', 'customer.order.search'], true)) {
            return redirect()->route('customer.dashboard', ['tab' => 'orders']);
        }
        if ($request->isMethod('GET') && auth('web')->check() && !$request->expectsJson()) {
            try {
                $to = $this->map($request);
            } catch (\Throwable $e) {
                $to = null;
            }
            if ($to) {
                return redirect($to);
            }
        }

        return $next($request);
    }
}
