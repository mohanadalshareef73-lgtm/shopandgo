<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * ⏱ يقيس وقت كل صفحة وعدد استعلامات الداتابيز. الصفحات اللي تاخذ أكثر من ثانية تنحفظ
 * (آخر 80) وتبان في صفحة «فحص التسعير والسرعة». ما يكتبش في الداتابيز.
 */
class RequestTimer
{
    public static int $queries = 0;
    public static float $dbMs = 0;

    public function handle(Request $request, Closure $next)
    {
        try {
            DB::listen(function ($q) {
                static::$queries++;
                static::$dbMs += (float) $q->time;
            });
        } catch (\Throwable $e) {
        }
        $response = $next($request);
        try {
            $ms = (int) round((microtime(true) - (defined('LARAVEL_START') ? LARAVEL_START : $_SERVER['REQUEST_TIME_FLOAT'])) * 1000);
            $response->headers->set('Server-Timing', 'app;dur=' . $ms . ', db;dur=' . (int) static::$dbMs . ';desc="' . static::$queries . ' q"');
            $path = $request->path();
            if ($ms >= 1000 && !str_starts_with($path, 'app/pricing-check')) {
                $list = Cache::get('sg.slow', []);
                array_unshift($list, [
                    'at' => time(), 'ms' => $ms, 'db' => (int) static::$dbMs, 'q' => static::$queries,
                    'route' => optional($request->route())->getName() ?: $path, 'path' => mb_substr($path, 0, 80),
                    'mem' => (int) round(memory_get_peak_usage() / 1048576),
                ]);
                Cache::put('sg.slow', array_slice($list, 0, 80), now()->addDays(3));
            }
        } catch (\Throwable $e) {
        }

        return $response;
    }
}
