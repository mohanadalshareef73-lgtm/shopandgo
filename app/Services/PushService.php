<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * إشعارات الجوال المجانية (Web Push / VAPID) بدون أي خدمة مدفوعة ولا مكتبات:
 * نبعتو "نبضة" فاضية لخدمة المتصفح (Google / Apple / Mozilla — مجانية)،
 * والجوال يفتح ويجيب آخر رسالة للعميل من المنظومة ويعرضها.
 */
class PushService
{
    public static function b64u(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    /** مفاتيح VAPID: تتولد مرة وحدة وتتحفظ في الإعدادات */
    public static function keys(): array
    {
        $k = (array) AppSettings::get('vapid', []);
        if (!empty($k['private']) && !empty($k['public'])) {
            return $k;
        }
        $res = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        openssl_pkey_export($res, $pem);
        $d = openssl_pkey_get_details($res)['ec'];
        $pub = "\x04" . str_pad($d['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['y'], 32, "\0", STR_PAD_LEFT);
        $k = ['private' => $pem, 'public' => static::b64u($pub)];
        AppSettings::put(['vapid' => $k]);

        return $k;
    }

    public static function publicKey(): string
    {
        return static::keys()['public'];
    }

    /** توقيع ES256: openssl يرجع DER، والـ JWT يبي r||s (64 بايت) */
    protected static function jwt(string $aud): string
    {
        $k = static::keys();
        $head = static::b64u(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $body = static::b64u(json_encode(['aud' => $aud, 'exp' => time() + 12 * 3600, 'sub' => 'mailto:' . (AppSettings::get('backup_email') ?: 'admin@' . (parse_url(config('app.url'), PHP_URL_HOST) ?: 'shopandgo.ly'))]));
        openssl_sign($head . '.' . $body, $der, openssl_pkey_get_private($k['private']), OPENSSL_ALGO_SHA256);
        $off = 2;
        $int = function () use ($der, &$off) {
            $len = ord($der[$off + 1]);
            $v = substr($der, $off + 2, $len);
            $off += 2 + $len;

            return str_pad(ltrim($v, "\0"), 32, "\0", STR_PAD_LEFT);
        };
        if (ord($der[1]) & 0x80) {
            $off = 2 + (ord($der[1]) & 0x7f);
        }
        $r = $int();
        $s = $int();

        return $head . '.' . $body . '.' . static::b64u($r . $s);
    }

    /** نبضة لكل أجهزة العميل */
    public static function notify(?Customer $c): int
    {
        if (!$c || !Schema::hasTable('push_subscriptions')) {
            return 0;
        }

        return static::send(DB::table('push_subscriptions')->where('customer_id', $c->id)->get());
    }

    /** نبضة لموظف أو مندوب */
    public static function notifyUser($user): int
    {
        if (!$user || !Schema::hasColumn('push_subscriptions', 'user_id')) {
            return 0;
        }

        return static::send(DB::table('push_subscriptions')->where('user_id', $user->id)->get());
    }

    /** كل الموظفين اللي فعّلوا الإشعارات (مش المندوبين) */
    public static function notifyStaff(): int
    {
        if (\App\Services\AppSettings::mobileOff()) {
            return 0; // 🔕 إشعارات الجوال موقفة
        }
        if (!Schema::hasTable('push_subscriptions') || !Schema::hasColumn('push_subscriptions', 'user_id')) {
            return 0;
        }
        $ids = \App\Models\User::where('status', true)->where(fn ($q) => $q->whereNull('type_id')->orWhere('type_id', '!=', 4))->pluck('id');

        return static::send(DB::table('push_subscriptions')->whereIn('user_id', $ids)->get());
    }

    protected static function send($subs): int
    {
        $ok = 0;
        foreach ($subs as $sub) {
            try {
                $u = parse_url($sub->endpoint);
                $aud = ($u['scheme'] ?? 'https') . '://' . ($u['host'] ?? '');
                $res = Http::withHeaders(['TTL' => '86400', 'Urgency' => 'normal', 'Authorization' => 'vapid t=' . static::jwt($aud) . ', k=' . static::publicKey()])
                    ->withBody('', 'application/octet-stream')->timeout(6)->post($sub->endpoint);
                if (in_array($res->status(), [404, 410], true)) {
                    DB::table('push_subscriptions')->where('id', $sub->id)->delete(); // الجهاز لغى الاشتراك
                } elseif ($res->successful()) {
                    $ok++;
                }
            } catch (\Throwable $e) {
                // ما نوقفوش شي لو جهاز ما ردش
            }
        }

        return $ok;
    }
}
