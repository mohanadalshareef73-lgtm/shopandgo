<?php

namespace App\Services;

use App\Models\Order;

/** ترقيم الإيصالات بالتسلسل (SG-20001, SG-20002 …) لكل الشاشات القديمة والجديدة */
class OrderNumber
{
    public static function prefix(): string
    {
        return (string) AppSettings::get('order_prefix', 'SG-');
    }

    /** رقم شحنة جديد (SH-1001 …) */
    public static function nextShipping(): string
    {
        $prefix = (string) AppSettings::get('ship_prefix', 'SH-');
        $n = max(static::lastNumber($prefix) + 1, (int) AppSettings::get('ship_start', 1001));
        while (Order::where('code', $prefix . $n)->exists()) {
            $n++;
        }

        return $prefix . $n;
    }

    protected static function lastNumber(?string $prefix = null): int
    {
        $prefix = $prefix ?? static::prefix();
        $max = 0;
        Order::where('code', 'like', $prefix . '%')->select('code')->orderBy('id')->chunk(2000, function ($rows) use ($prefix, &$max) {
            foreach ($rows as $r) {
                $rest = substr($r->code, strlen($prefix));
                if ($rest !== '' && ctype_digit($rest)) {
                    $max = max($max, (int) $rest);
                }
            }
        });

        return $max;
    }

    /** الرقم القادم (للعرض فقط) */
    public static function peek(): string
    {
        return static::prefix() . max(static::lastNumber() + 1, (int) AppSettings::get('order_start', 20001));
    }

    /** رقم جديد مضمون إنه غير مستخدم */
    public static function next(): string
    {
        $n = max(static::lastNumber() + 1, (int) AppSettings::get('order_start', 20001));
        while (Order::where('code', static::prefix() . $n)->exists()) {
            $n++;
        }

        return static::prefix() . $n;
    }
}
