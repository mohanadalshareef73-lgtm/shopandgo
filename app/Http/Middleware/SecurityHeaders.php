<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** ترويسات أمان لكل الصفحات: منع التضمين في إطارات، ومنع تخمين نوع الملف، وإجبار HTTPS */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        if (function_exists('header_remove')) {
            @header_remove('X-Powered-By');
        }
        $h = $response->headers;
        $h->set('X-Frame-Options', 'SAMEORIGIN');
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(self), payment=()');
        $h->set('X-XSS-Protection', '0');
        $h->set('Cross-Origin-Opener-Policy', 'same-origin');
        $h->set('Content-Security-Policy', "frame-ancestors 'self'; base-uri 'self'; object-src 'none'");
        if ($request->isSecure()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        // صفحات الحساب لا تُخزّن في المتصفح أو الوسطاء
        if (auth('web')->check() || auth('customer')->check()) {
            $h->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
