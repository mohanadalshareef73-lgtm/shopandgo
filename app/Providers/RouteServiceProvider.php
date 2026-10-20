<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            // Provider signatures authenticate these routes; API throttling
            // would reject legitimate bursts of completed quotes.
            Route::group([], base_path('routes/hooks.php'));

            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // حماية من التخمين والإغراق (Spam / Brute force)
        $tooMany = fn (string $msg) => fn () => back()->withInput(request()->except('password', 'password_confirmation', 'code'))->with('error', $msg);
        RateLimiter::for('login', fn (Request $r) => [
            Limit::perMinute(20)->by('login-ip:' . $r->ip())->response($tooMany('محاولات كثيرة، يرجى الانتظار دقيقة ثم المحاولة مرة أخرى')),
            Limit::perHour(200)->by('login-ip-h:' . $r->ip())->response($tooMany('تم إيقاف المحاولات مؤقتاً بسبب كثرتها، حاول لاحقاً')),
        ]);
        RateLimiter::for('signup', fn (Request $r) => [
            Limit::perMinute(3)->by('signup:' . $r->ip())->response($tooMany('محاولات كثيرة، يرجى الانتظار قليلاً')),
            Limit::perDay(10)->by('signup-d:' . $r->ip())->response($tooMany('تجاوزت الحد اليومي لطلبات التسجيل من هذا الجهاز')),
        ]);
        RateLimiter::for('otp', fn (Request $r) => [
            Limit::perMinute(6)->by('otp:' . $r->ip())->response($tooMany('محاولات كثيرة، يرجى الانتظار دقيقة')),
            Limit::perHour(30)->by('otp-h:' . $r->ip())->response($tooMany('محاولات كثيرة، حاول لاحقاً')),
        ]);
        RateLimiter::for('portal', fn (Request $r) => [
            Limit::perMinute(5)->by('portal:' . (auth('customer')->id() ?: $r->ip()))->response($tooMany('أرسلت طلبات كثيرة، يرجى الانتظار قليلاً')),
            Limit::perDay(40)->by('portal-d:' . (auth('customer')->id() ?: $r->ip()))->response($tooMany('تجاوزت الحد اليومي، حاول غداً')),
        ]);
        RateLimiter::for('invoice', fn (Request $r) => Limit::perMinute(40)->by('inv:' . $r->ip()));
    }
}
