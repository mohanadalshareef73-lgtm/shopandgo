<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** يسجل آخر ظهور للموظف (مرة كل دقيقتين بالكثير) */
class TouchLastSeen
{
    public function handle(Request $request, Closure $next)
    {
        // للموظفين بس (مش حساب الزبون)
        $user = $request->user();
        if ($user instanceof \App\Models\User && (!$user->last_seen_at || now()->diffInSeconds($user->last_seen_at) > 120)) {
            try {
                $user->forceFill(['last_seen_at' => now()])->saveQuietly();
            } catch (\Throwable $e) {
                $user->syncOriginalAttribute('last_seen_at');
            }
        }

        return $next($request);
    }
}
