<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** يسجل الزيارة (صفحات GET بس) في جدول site_visits — لو صار أي خطأ ما يوقفش الموقع */
class TrackVisit
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        try {
            if (!$request->isMethod('get') || $request->ajax() || $request->expectsJson() || $request->is('sw.js', 'push/*', 'api/*', '*/ping', 'app/alerts', 'storage/*')) {
                return $response;
            }
            $route = optional($request->route())->getName();
            if (!$route || $response->getStatusCode() >= 400) {
                return $response;
            }
            $page = mb_substr($route . ($request->filled('tab') ? ':' . preg_replace('/[^a-z_]/', '', (string) $request->tab) : ''), 0, 80);
            if ($c = auth('customer')->user()) {
                [$actor, $id] = ['customer', $c->id];
            } elseif ($u = auth('web')->user()) {
                [$actor, $id] = [(int) $u->type_id === 4 ? 'courier' : 'staff', $u->id];
            } else {
                [$actor, $id] = ['guest', 0];
            }
            $ua = (string) $request->userAgent();
            if (preg_match('/bot|crawl|spider|slurp|facebookexternalhit|whatsapp|preview/i', $ua)) {
                return $response;
            }
            $device = $request->cookie('sg_app') || str_contains($ua, 'wv') ? 'app' : (preg_match('/Mobile|Android|iPhone/i', $ua) ? 'mobile' : 'web');
            $now = now();
            $key = ['date' => $now->toDateString(), 'actor' => $actor, 'actor_id' => $id, 'page' => $page, 'device' => $device];
            if (!DB::table('site_visits')->where($key)->increment('hits', 1, ['last_at' => $now])) {
                DB::table('site_visits')->insertOrIgnore($key + ['hits' => 1, 'first_at' => $now, 'last_at' => $now]);
            }
        } catch (\Throwable $e) {
            // الجدول لسه ما تعملش أو أي مشكلة: نكملو عادي
        }

        return $response;
    }
}
