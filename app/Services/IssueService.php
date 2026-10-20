<?php

namespace App\Services;

use App\Models\Log;
use App\Models\Order;
use App\Models\OrderIssue;
use RuntimeException;

/**
 * مشكلة في طلب (زر «مشكلة»):
 *  - نواقص / لم يصل / تالف / أخرى، مع كمية وقيمة اختياريتين
 *  - الإجراء المالي: يُقرَّر لاحقاً، خصم من الإيصال، رصيد للعميل، أو إرجاع نقدي من الخزينة
 *  - يُبلَّغ العميل (حسابه + واتساب اختياري) وتظهر في مركز التنبيهات حتى تُحل
 */
class IssueService
{
    public static function create(Order $order, array $d): OrderIssue
    {
        $type = $d['type'];
        $value = round((float) ($d['value'] ?? 0), 2);
        $action = $value > 0 ? ($d['action'] ?? 'later') : 'later';
        if ($value > $order->grandTotal() + 0.01) {
            throw new RuntimeException('القيمة أكبر من قيمة الإيصال');
        }
        $issue = OrderIssue::create([
            'order_id' => $order->id, 'customer_id' => $order->customer_id, 'type' => $type,
            'qty' => !empty($d['qty']) ? (int) $d['qty'] : null, 'value' => $value ?: null, 'action' => $action,
            'note' => $d['note'] ?? null, 'status' => 'open', 'created_id' => auth('web')->id(),
        ]);
        if ($action !== 'later') {
            static::applyMoney($issue, $order, $action, $value, $d['payment_id'] ?? null);
            // المشكلة المالية المحسومة تُغلق مباشرة
            $issue->status = 'resolved';
            $issue->resolution = OrderIssue::ACTIONS[$action] . ' ' . TreasuryService::fmt($value) . ' د.ل';
            $issue->resolved_by = auth('web')->id();
            $issue->resolved_at = now();
            $issue->save();
        }
        if ($type === 'missing' && $issue->qty && !$order->missing_action) {
            $order->missing_qty = $issue->qty;
            $order->missing_value = $value ?: $order->missing_value;
            $order->missing_action = $action !== 'later' ? $action : null;
            $order->save();
        }
        Log::create(['user_id' => auth('web')->id(), 'type' => 'order', 'title' => ' مشكلة في طلب (' . $order->code . ')',
            'log' => ' ' . $issue->typeLabel() . ($issue->qty ? ' · ' . $issue->qty . ' قطعة' : '') . ($value ? ' · ' . $value . ' د.ل · ' . OrderIssue::ACTIONS[$action] : '') . ($issue->note ? ' · ' . $issue->note : '')]);
        if ($issue->status === 'open') {
            AlertService::push('order_issue', '⚠ مشكلة في طلب ' . $order->code, $issue->typeLabel() . ' · ' . optional($order->customer)->name . ($issue->note ? ' · ' . $issue->note : ''), route('sg.orders.show', $order) . '#issues', 'warn');
        }

        return $issue;
    }

    protected static function applyMoney(OrderIssue $issue, Order $order, string $action, float $value, $paymentId): void
    {
        if ($value <= 0) {
            return;
        }
        if ($action === 'credit') {
            $c = $order->customer;
            $c->credit = (float) $c->credit + $value;
            $c->save();

            return;
        }
        $order->discount = (float) $order->discount + $value;
        $order->discount_reason = trim(($order->discount_reason ? $order->discount_reason . ' + ' : '') . $issue->typeLabel());
        if ($action === 'refund') {
            if (!$paymentId) {
                throw new RuntimeException('اختر الخزينة التي يُصرف منها المبلغ');
            }
            $refund = round(min($value, max(0, (float) $order->paid_price - $order->grandTotal())), 2);
            if ($refund > 0) {
                $order->paid_price = (float) $order->paid_price - $refund;
                TreasuryService::withdraw($refund, $order->currency ?: 'dinar', (int) $paymentId, 'order', $order->id, 'إرجاع مبلغ (' . $issue->typeLabel() . ') ' . $order->code);
            }
        }
        $order->refreshPaymentStatus();
        $order->save();
    }

    public static function resolve(OrderIssue $issue, array $d): void
    {
        if ($issue->status !== 'open') {
            throw new RuntimeException('المشكلة محلولة مسبقاً');
        }
        $order = $issue->order;
        $value = round((float) ($d['value'] ?? 0), 2);
        $action = $d['action'] ?? 'later';
        if ($value > 0 && $action !== 'later') {
            static::applyMoney($issue, $order, $action, $value, $d['payment_id'] ?? null);
            $issue->value = $value;
            $issue->action = $action;
        }
        $issue->status = 'resolved';
        $issue->resolution = trim(($value > 0 && $action !== 'later' ? OrderIssue::ACTIONS[$action] . ' ' . TreasuryService::fmt($value) . ' د.ل' . (!empty($d['resolution']) ? ' · ' : '') : '') . ($d['resolution'] ?? ''));
        $issue->resolved_by = auth('web')->id();
        $issue->resolved_at = now();
        $issue->save();
        Log::create(['user_id' => auth('web')->id(), 'type' => 'order', 'title' => ' حل مشكلة (' . $order->code . ')', 'log' => ' ' . $issue->typeLabel() . ' · ' . $issue->resolution]);
    }

    public static function customerText(OrderIssue $issue, bool $resolved = false): string
    {
        $o = $issue->order->fresh();
        $what = $issue->typeLabel() . ($issue->qty ? ' (' . $issue->qty . ' قطعة)' : '') . ($issue->note ? ' — ' . $issue->note : '');
        $act = $resolved || $issue->status === 'resolved'
            ? ('تم الحل: ' . ($issue->resolution ?: 'تمت المعالجة'))
            : 'سجّلنا المشكلة وسنتابعها معك';
        $tpl = MessageService::template('order_issue');
        $vars = MessageService::orderVars($o, ['{المشكلة}' => $what, '{الإجراء}' => $act . "\nالمتبقي على الإيصال: " . TreasuryService::fmt(max(0, $o->remaining())) . ' د.ل']);

        return $tpl ? MessageService::render($tpl->message, $vars) : "بخصوص طلبك {$o->code}: {$what}\n{$act}";
    }

    public static function notify(OrderIssue $issue, bool $whatsapp, bool $resolved = false): bool
    {
        $c = $issue->order->customer;
        $text = static::customerText($issue, $resolved);
        if ($whatsapp) {
            return MessageService::sendText('order_issue', $text, array_values(array_filter([optional($c)->phone_1, optional($c)->phone_2])), $c, $issue->order);
        }
        CustomerNotify::inbox($c, $text, $issue->order, 'order_issue');

        return false;
    }
}
