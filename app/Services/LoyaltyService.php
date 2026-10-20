<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;

/**
 * مستويات العملاء أوتوماتيك حسب مجموع صرفهم (بالدينار، كل الإيصالات غير الملغاة):
 * برونزي / فضي / ذهبي — وكل مستوى ينقص من سعر كيلو الشحن.
 */
class LoyaltyService
{
    protected static array $cache = [];

    public static function enabled(): bool
    {
        return (bool) AppSettings::get('loyalty_enabled', false);
    }

    /** [مفتاح، الاسم، الأيقونة، أقل صرف، خصم الكيلو $، اللون] */
    public static function tiers(): array
    {
        return [
            ['bronze', 'برونزي', '🥉', (float) AppSettings::get('loyalty_t1', 2000), (float) AppSettings::get('loyalty_d1', 0.25), '#b5733c'],
            ['silver', 'فضي', '🥈', (float) AppSettings::get('loyalty_t2', 5000), (float) AppSettings::get('loyalty_d2', 0.5), '#7c8594'],
            ['gold', 'ذهبي', '🥇', (float) AppSettings::get('loyalty_t3', 10000), (float) AppSettings::get('loyalty_d3', 1), '#c99a06'],
        ];
    }

    public static function spent(Customer $c): float
    {
        return static::$cache['s' . $c->id] ??= (float) Order::where('customer_id', $c->id)->where('status', '!=', 'canceld')
            ->selectRaw('COALESCE(SUM(total_price - COALESCE(discount,0)),0) s')->value('s');
    }

    /** المستوى الحالي + الجاي وكم باقي عليه */
    public static function info(?Customer $c): ?array
    {
        if (!$c || !static::enabled()) {
            return null;
        }
        $spent = static::spent($c);
        $cur = null;
        $next = null;
        foreach (static::tiers() as $t) {
            if ($spent >= $t[3]) {
                $cur = $t;
            } elseif (!$next) {
                $next = $t;
            }
        }

        return ['spent' => $spent, 'tier' => $cur, 'next' => $next, 'left' => $next ? max(0, $next[3] - $spent) : 0,
            'progress' => $next ? min(100, round($spent / max(1, $next[3]) * 100)) : 100, 'discount' => $cur ? $cur[4] : 0];
    }

    /** خصم الكيلو ($) للعميل حسب مستواه */
    public static function shipDiscount(?Customer $c): float
    {
        $i = static::info($c);

        return $i ? (float) $i['discount'] : 0.0;
    }

    /** لو العميل طلع لمستوى أعلى من آخر مرة: نبلغوه (واتساب + يظهر في حسابه) */
    public static function check(?Customer $c): void
    {
        if (!$c || !static::enabled() || !\Illuminate\Support\Facades\Schema::hasColumn('customers', 'loyalty_tier')) {
            return;
        }
        unset(static::$cache['s' . $c->id]);
        $i = static::info($c);
        $t = $i['tier'] ?? null;
        $rank = fn ($k) => ['bronze' => 1, 'silver' => 2, 'gold' => 3][$k] ?? 0;
        if (!$t || $rank($t[0]) <= $rank($c->loyalty_tier)) {
            return;
        }
        $c->loyalty_tier = $t[0];
        $c->saveQuietly();
        $first = trim(explode(' ', (string) $c->name)[0]);
        $disc = rtrim(rtrim(number_format($t[4], 2), '0'), '.');
        $text = "مبروك يا {$first} 🎉\nوليت عميل {$t[2]} {$t[1]} في " . AppSettings::get('shop_name', 'Shop and Go') . "!\n"
            . ($t[4] > 0 ? "من توا عندك خصم {$disc}$ على كل كيلو شحن ✨\n" : '')
            . ($i['next'] ? 'باقيلك ' . number_format($i['left']) . " د.ل وتولّي {$i['next'][2]} {$i['next'][1]}." : 'وصلت لأعلى مستوى، شكراً على ثقتك 💛');
        MessageService::sendText('customer_note', $text, [$c->phone_1], $c); // يتسجل في حسابه + واتساب
    }

    /** VIP يدوي: رسالة ترحيب */
    public static function notifyVip(Customer $c): void
    {
        $first = trim(explode(' ', (string) $c->name)[0]);
        $text = "اهلا {$first} 👑\nيسعدنا نبلغوك إنك صرت من عملاء VIP في " . AppSettings::get('shop_name', 'Shop and Go') . "!\nطلباتك عندها أولوية في الحجز والمتابعة. شكراً على ثقتك 💛";
        MessageService::sendText('customer_note', $text, [$c->phone_1], $c); // يتسجل في حسابه + واتساب
    }
}
