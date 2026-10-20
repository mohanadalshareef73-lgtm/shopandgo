<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/** رمز تحقق من 6 أرقام يُرسل عبر واتساب (صالح 10 دقائق، 5 محاولات، إعادة الإرسال بعد 60 ثانية) */
class Otp
{
    public const TTL = 600;
    public const TRIES = 5;
    public const COOLDOWN = 60;

    public static function enabled(): bool
    {
        return (bool) AppSettings::get('otp_enabled', true);
    }

    protected static function key(string $purpose, string $phone): string
    {
        return 'otp:' . $purpose . ':' . MessageService::normalize($phone);
    }

    public static function cooldownLeft(string $purpose, string $phone): int
    {
        $at = Cache::get(static::key($purpose, $phone) . ':sent');

        return $at ? max(0, static::COOLDOWN - (time() - (int) $at)) : 0;
    }

    /** يرجع true إذا أُرسل الرمز */
    public static function send(string $purpose, string $phone): bool
    {
        if (static::cooldownLeft($purpose, $phone) > 0) {
            return true; // رمز سابق ما زال صالحاً
        }
        $code = (string) random_int(100000, 999999);
        $k = static::key($purpose, $phone);
        Cache::put($k, ['hash' => Hash::make($code), 'tries' => 0], static::TTL);
        Cache::put($k . ':sent', time(), static::TTL);
        $shop = AppSettings::get('shop_name', 'Shop & Go');
        $text = "رمز التحقق الخاص بك في {$shop}: *{$code}*\nصالح لمدة 10 دقائق.\nلا تشارك هذا الرمز مع أي شخص، فموظفونا لن يطلبوه منك.";

        return MessageService::sendText('otp', $text, [$phone]);
    }

    /** يرجع: ok | wrong | expired | locked */
    public static function check(string $purpose, string $phone, string $code): string
    {
        $k = static::key($purpose, $phone);
        $row = Cache::get($k);
        if (!$row) {
            return 'expired';
        }
        if ($row['tries'] >= static::TRIES) {
            return 'locked';
        }
        if (!preg_match('/^\d{6}$/', $code) || !Hash::check($code, $row['hash'])) {
            $row['tries']++;
            Cache::put($k, $row, static::TTL);

            return $row['tries'] >= static::TRIES ? 'locked' : 'wrong';
        }
        Cache::forget($k);
        Cache::forget($k . ':sent');

        return 'ok';
    }

    public static function message(string $res): string
    {
        return [
            'wrong' => 'رمز التحقق غير صحيح',
            'expired' => 'انتهت صلاحية الرمز، اطلب رمزاً جديداً',
            'locked' => 'تجاوزت عدد المحاولات المسموح، اطلب رمزاً جديداً',
        ][$res] ?? 'تعذّر التحقق';
    }
}
