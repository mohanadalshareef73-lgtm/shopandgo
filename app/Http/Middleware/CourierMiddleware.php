<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** واجهة المندوب: لازم يكون داخل ونوعه مندوب (4) وحسابه مفعل */
class CourierMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $u = auth()->user();
        if (!$u) {
            return redirect()->route('admin.login');
        }
        if (!$u->status) {
            Auth::logout();

            return redirect()->route('admin.login')->with('error', 'حسابك موقوف، تواصل مع الإدارة');
        }
        if ((int) $u->type_id !== 4) {
            return redirect()->route('admin.home');
        }

        return $next($request);
    }
}
