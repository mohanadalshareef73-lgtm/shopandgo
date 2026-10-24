<?php

namespace App\Support;

use Illuminate\Support\Facades\Artisan;

/**
 * ⚡ تسريع المنظومة: يحفظ الإعدادات والروابط في ملف جاهز (بدل ما تنقرا من جديد مع كل صفحة).
 * لو أي خطوة فشلت، يرجعها لوضعها العادي — المنظومة تقعد تشتغل عادي (بس أبطأ شوية).
 * لازم يعاد بعد أي رفع أو تعديل في .env (رابط «مسح الكاش» يديره وحده).
 */
class Optimizer
{
    public static function run(): array
    {
        @set_time_limit(120);
        $out = [];
        try {
            Artisan::call('config:cache');
            // نتأكدو إن الملف يتقرا صح، وإلا نمسحوه
            $f = app()->getCachedConfigPath();
            if (!is_file($f) || !is_array(@require $f)) {
                throw new \RuntimeException('config file invalid');
            }
            $out['config'] = true;
        } catch (\Throwable $e) {
            try { Artisan::call('config:clear'); } catch (\Throwable $x) {}
            $out['config'] = false;
            $out['config_err'] = mb_substr($e->getMessage(), 0, 200);
        }
        try {
            Artisan::call('route:cache');
            $out['routes'] = is_file(app()->getCachedRoutesPath());
        } catch (\Throwable $e) {
            try { Artisan::call('route:clear'); } catch (\Throwable $x) {}
            $out['routes'] = false;
            $out['routes_err'] = mb_substr($e->getMessage(), 0, 200);
        }

        return $out;
    }

    public static function status(): array
    {
        $op = function_exists('opcache_get_status') ? @opcache_get_status(false) : false;

        return [
            'config' => is_file(app()->getCachedConfigPath()),
            'routes' => is_file(app()->getCachedRoutesPath()),
            'opcache' => is_array($op) && !empty($op['opcache_enabled']),
            'php' => PHP_VERSION,
            'debug' => (bool) config('app.debug'),
            'cache' => (string) config('cache.default'),
            'session' => (string) config('session.driver'),
        ];
    }
}
