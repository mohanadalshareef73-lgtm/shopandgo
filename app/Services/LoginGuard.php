<?php

namespace App\Services;

use Illuminate\Support\Facades\RateLimiter;

/** قفل الحساب مؤقتاً بعد محاولات دخول فاشلة متكررة (حتى لو من أجهزة مختلفة) وتنبيه الإدارة */
class LoginGuard
{
    public const MAX = 8;       // محاولات فاشلة
    public const DECAY = 900;   // خلال 15 دقيقة

    protected static function key(string $scope, string $login): string
    {
        return 'acct:' . $scope . ':' . mb_strtolower(trim($login));
    }

    public static function lockedFor(string $scope, string $login): int
    {
        $k = static::key($scope, $login);

        return RateLimiter::tooManyAttempts($k, static::MAX) ? RateLimiter::availableIn($k) : 0;
    }

    public static function failed(string $scope, string $login): void
    {
        $k = static::key($scope, $login);
        RateLimiter::hit($k, static::DECAY);
        if (RateLimiter::attempts($k) === static::MAX) {
            static::remember($scope, $login);
            try {
                AlertService::push('security', '🔐 محاولات دخول فاشلة متكررة',
                    ($scope === 'admin' ? 'حساب الموظف: ' : 'حساب العميل: ') . $login . ' · آخر عنوان IP: ' . request()->ip() . ' · أُقفل الحساب مؤقتاً 15 دقيقة',
                    route('sg.locks'), 'warn', true);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    public static function clear(string $scope, string $login): void
    {
        RateLimiter::clear(static::key($scope, $login));
        static::forget($scope, $login);
    }

    /** قائمة الحسابات اللي تقفلت (عشان الإدارة تشوفها وتفك القفل) */
    public static function list(): array
    {
        return collect((array) \Illuminate\Support\Facades\Cache::get('login_locks', []))
            ->filter(fn ($x) => \Carbon\Carbon::parse($x['at'])->gt(now()->subDays(7)))->sortByDesc('at')->all();
    }

    protected static function remember(string $scope, string $login): void
    {
        $all = static::list();
        $id = md5($scope . '|' . mb_strtolower(trim($login)));
        $all[$id] = ['id' => $id, 'scope' => $scope, 'login' => trim($login), 'ip' => request()->ip(), 'at' => now()->toDateTimeString(), 'times' => (int) ($all[$id]['times'] ?? 0) + 1];
        \Illuminate\Support\Facades\Cache::put('login_locks', $all, now()->addDays(7));
    }

    public static function forget(string $scope, string $login): void
    {
        $all = static::list();
        unset($all[md5($scope . '|' . mb_strtolower(trim($login)))]);
        \Illuminate\Support\Facades\Cache::put('login_locks', $all, now()->addDays(7));
    }

    public static function lockedMessage(int $sec): string
    {
        return 'تم إيقاف الدخول لهذا الحساب مؤقتاً بسبب محاولات خاطئة متكررة. حاول بعد ' . max(1, (int) ceil($sec / 60)) . ' دقيقة';
    }
}
