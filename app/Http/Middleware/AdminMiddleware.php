<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if(!auth()->user()){
            return redirect()->route('login');
        }
        if(!auth()->user()->status){
            Auth::logout();
            return redirect()->route('login')->with('error', 'تم إلغاء تفعيل حسابك الرجاء التواصل مع المسؤولين');
        }

        // المندوب يمشي لواجهته
        if(auth()->user()->type_id == 4){
            return redirect()->route('courier.home');
        }
        if(auth()->user()->type_id != 1 && auth()->user()->type_id != 2){
            return redirect()->back()->with('error','غير مسموح لك بالدخول الي هادا الرابط');
        }
        if(auth()->user()->type_id == 1 || auth()->user()->type_id == 2){
            return $next($request);
        }
        return redirect()->back()->with('error','غير مسموح لك بالدخول الي هادا الرابط');
    }
}
