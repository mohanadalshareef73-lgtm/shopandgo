<?php

namespace App\Services;

/**
 * 🚨 تنبيه عاجل على واتساب المدير طول — حتى لو إشعارات الجوال موقفة أو النوع مكتوم.
 * الأرقام: «رقم التنبيهات العاجلة» من الإعدادات، ولو فاضي: أرقام الإدارة.
 * نفس التنبيه ما يتكررش قبل ما تفوت مدة $everyMinutes (باش ما يغرقش الواتساب).
 */
class UrgentAlert
{
    protected static bool $sending = false;

    public static function phones(): array
    {
        $split = function ($v) {
            if (is_string($v)) {
                $v = preg_split('/[\s,،]+/u', $v);
            }

            return array_values(array_filter(array_map('trim', (array) $v)));
        };
        $mine = $split(AppSettings::get('urgent_phones', [])) ?: AppSettings::reportPhones();
        // 🏪 هاتف المحل + الأرقام الزايدة (علامة +) يوصلهم نفس التنبيه
        $shop = array_merge($split((string) AppSettings::get('shop_phone', '')), $split(AppSettings::get('shop_phones_extra', [])));

        return array_values(array_unique(array_merge($mine, $shop)));
    }

    public static function send(string $key, string $text, int $everyMinutes = 30): bool
    {
        if (static::$sending) {
            return false;
        }
        static::$sending = true;
        try {
            $phones = static::phones();
            if (!$phones) {
                return false;
            }
            $cache = app('cache');
            $ck = 'urgent-' . md5($key);
            if ($cache->has($ck) || (int) $cache->get('urgent-count', 0) >= 20) {
                return false; // نفس التنبيه قريب، أو وصلنا 20 تنبيه في الساعة
            }
            $cache->put($ck, 1, now()->addMinutes(max(1, $everyMinutes)));
            $cache->put('urgent-count', (int) $cache->get('urgent-count', 0) + 1, now()->addHour());

            return MessageService::sendText('alert', "🚨 " . $text . "\n🕒 " . now()->format('Y-m-d H:i'), $phones);
        } catch (\Throwable $e) {
            return false; // التنبيه نفسه ما يطيحش المنظومة
        } finally {
            static::$sending = false;
        }
    }

    /** رصيد محفظة تسعير السلات: لو وصل الحد (افتراضياً 50$) أو أقل */
    public static function checkCartWallet(array $wallet): void
    {
        try {
            $amount = (float) ($wallet['available']['amount'] ?? 0);
            $cur = (string) ($wallet['available']['currency'] ?? 'USD');
            $limit = (float) AppSettings::get('cart_wallet_alert', 50);
            if ($limit > 0 && $amount <= $limit) {
                static::send('cart-wallet-low', '💳 رصيد السلات وصل ' . number_format($amount, 2) . ' ' . $cur . ' (الحد ' . $limit . ')' . "\nاشحن المحفظة قبل ما توقف ردود الأسعار على الواتساب", 360);
            }
        } catch (\Throwable $e) {
        }
    }
}
