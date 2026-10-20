<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** فلتر الفترة (اليوم/الأسبوع/الشهر/السنة) يتذكر آخر اختيار لكل صفحة، بدل ما يرجع للشهر كل مرة */
class RememberPeriod
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('get') && ($route = optional($request->route())->getName())) {
            $key = 'sg_period.' . $route;
            if ($request->has('p')) {
                session([$key => array_filter($request->only(['p', 'from', 'to']), fn ($v) => $v !== null && $v !== '')]);
            } elseif ($saved = session($key)) {
                $request->merge($saved);
            }
        }

        return $next($request);
    }
}
