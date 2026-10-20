<?php

namespace App\Support;

/**
 * قواعد البحث الموحدة في المنظومة:
 * - كود العميل: يحتوي الأرقام في أي مكان — 9246 ← يطلع 9246 و 69246 وأي كود فيه 9246.
 * - رقم الإيصال: يطابق من البداية — 9 ← كل رقم يبدأ بـ 9، 92 ← يبدأ بـ 92.
 *   رقم الإيصال SG-20431 يطابق برقمه أيضاً (204 ← SG-204…).
 * - الاسم: يحتوي النص في أي مكان.
 * - الهاتف: يحتوي الرقم، من 4 أرقام فأكثر (حتى لا تظهر كل الأرقام عند كتابة رقم واحد).
 */
class Search
{
    public static function esc(string $t): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $t);
    }

    /** شروط العميل (داخل where مجمّع) */
    public static function customer($w, string $t, string $prefix = '')
    {
        $e = self::esc($t);
        $digits = preg_replace('/\D/', '', $t);
        $w->where($prefix . 'name', 'like', "%$e%")->orWhere($prefix . 'code', 'like', "%$e%");
        if (strlen($digits) >= 4) {
            $w->orWhere($prefix . 'phone_1', 'like', "%$digits%")->orWhere($prefix . 'phone_2', 'like', "%$digits%");
        }

        return $w;
    }

    /** رقم إيصال/شحنة: من البداية، أو رقمه بعد الشرطة */
    public static function code($w, string $t, string $col = 'code')
    {
        $e = self::esc($t);

        return $w->where(fn ($x) => $x->where($col, 'like', "$e%")->orWhere($col, 'like', "%-$e%"));
    }
}
