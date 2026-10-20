<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\PointsHistory;

/**
 * نقاط العملاء: كل ما يطلب يأخذ نقاط أوتوماتيك (إذا مفعلة من الإعدادات)،
 * ويمكنه استخدامها خصم وقت التسليم أو من طلب التوصيل.
 */
class PointsService
{
    public static function enabled(): bool
    {
        return (bool) AppSettings::get('points_enabled');
    }

    /** طريقة استخدام النقاط: money (خصم مباشر) · coupon (كوبون نسبة) · both */
    public static function mode(): string
    {
        $m = (string) AppSettings::get('points_mode', 'money');

        return in_array($m, ['money', 'coupon', 'both'], true) ? $m : 'money';
    }

    public static function moneyEnabled(): bool
    {
        return static::enabled() && in_array(static::mode(), ['money', 'both'], true);
    }

    public static function couponEnabled(): bool
    {
        return static::enabled() && in_array(static::mode(), ['coupon', 'both'], true);
    }

    public static function value(): float
    {
        return (float) AppSettings::get('point_value', 0.5);
    }

    /** كم نقطة يأخذ على الإيصال */
    public static function forOrder(Order $o): int
    {
        $base = (float) $o->total_price - (float) $o->discount;

        return max(0, (int) AppSettings::get('points_per_order', 0) + (int) floor($base / 100 * (float) AppSettings::get('points_per_100', 0)));
    }

    /** يعطي النقاط على إيصال جديد (مرة وحدة فقط) */
    public static function award(Order $o, bool $notify = true): int
    {
        if (!static::enabled() || $o->points_earned || $o->status === 'canceld' || !$o->customer) {
            return 0;
        }
        $pts = static::forOrder($o);
        if ($pts <= 0) {
            return 0;
        }
        static::add($o->customer, $pts, 'طلب ' . $o->code, $o);
        $o->points_earned = $pts;
        $o->save();
        if ($notify && AppSettings::get('points_notify')) {
            $c = $o->customer->fresh();
            MessageService::sendTemplate('points_earned', $c, MessageService::orderVars($o, [
                '{النقاط}' => $pts, '{رصيد_النقاط}' => $c->points, '{قيمة_النقاط}' => TreasuryService::fmt($c->points * static::value()),
            ]), $o);
        }

        return $pts;
    }

    /** إذا الإيصال أُلغي ترجع النقاط */
    public static function reverse(Order $o): void
    {
        if ($o->points_earned && $o->customer) {
            static::add($o->customer, -$o->points_earned, 'إلغاء ' . $o->code, $o);
            $o->points_earned = 0;
            $o->save();
        }
    }

    /** يستخدم نقاط كخصم على إيصال. يرجع قيمة الخصم بالدينار */
    public static function redeem(Order $o, int $points): float
    {
        $c = $o->customer;
        $points = min($points, (int) $c->points);
        if ($points <= 0 || $points < (int) AppSettings::get('points_min_redeem', 0)) {
            return 0;
        }
        $value = round($points * static::value(), 2);
        $value = min($value, max(0, $o->remaining()));
        $points = (int) ceil($value / max(0.0001, static::value()));
        if ($value <= 0) {
            return 0;
        }
        static::add($c, -$points, 'خصم على ' . $o->code, $o);
        $o->points_used = (int) $o->points_used + $points;
        $o->discount = (float) $o->discount + $value;
        $o->refreshPaymentStatus();
        $o->save();

        return $value;
    }

    public static function add(Customer $c, int $points, string $reason, ?Order $o = null): void
    {
        PointsHistory::create(['customer_id' => $c->id, 'order_id' => optional($o)->id, 'points' => $points, 'reason' => $reason, 'created_id' => auth('web')->id()]);
        $c->points = max(0, (int) $c->points + $points);
        $c->save();
    }
}
