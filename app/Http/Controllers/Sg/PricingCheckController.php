<?php

namespace App\Http\Controllers\Sg;

use App\Http\Controllers\Controller;
use App\Models\CartQuote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** 🩺 فحص التسعير: الجدولة (cron) شغالة؟ القفل عالق؟ المحفظة؟ آخر السلات وأخطاءها؟ الواتساب يبعث؟ */
class PricingCheckController extends Controller
{
    protected function guard(): void
    {
        abort_unless(auth()->user()->hasRole('Admin'), 403);
    }

    public function index()
    {
        $this->guard();
        $hb = Cache::get('hb.cron');
        $hbq = Cache::get('hb.cart-quotes'); // آخر مرة أمر التسعير بدا فعلاً
        $wallet = null;
        $walletErr = null;
        try {
            $wallet = app(\App\Services\CartQuoteClient::class)->wallet();
        } catch (\Throwable $e) {
            $walletErr = $e->getMessage();
        }
        $quotes = CartQuote::latest('id')->take(20)->get();
        $waiting = CartQuote::whereNull('notified_at')->where('created_at', '>=', now()->subDays(2))->count();
        $failedToday = CartQuote::where('status', 'failed')->where('updated_at', '>=', now()->startOfDay())->count();
        $lastIn = optional(CartQuote::latest('id')->first())->created_at;
        $wa = Schema::hasTable('whatsapp_outbox') ? DB::table('whatsapp_outbox')->latest('id')->take(12)->get() : collect();
        $waFail = Schema::hasTable('whatsapp_outbox') ? DB::table('whatsapp_outbox')->where('status', 'failed')->where('created_at', '>=', now()->subHours(3))->count() : 0;
        $token = (string) config('services.cart_quotes.token') !== '';
        $debug = (bool) config('app.debug');
        $testMode = (string) config('services.cart_quotes.whatsapp_test_number', '') !== '';
        $lastWaIn = null;
        try {
            $lastWaIn = optional(\App\Models\WhatsappMessages::latest('id')->first())->created_at;
        } catch (\Throwable $e) {
        }

        return view('sg.pricing-check.index', compact('hb', 'hbq', 'wallet', 'walletErr', 'quotes', 'waiting', 'failedToday', 'lastIn', 'wa', 'waFail', 'token', 'debug', 'testMode', 'lastWaIn'));
    }

    /** يفك القفل العالق (لو الأمر طاح في نصه، Laravel يقعد يعتبره شغال لمدة 24 ساعة) */
    public function unlock()
    {
        $this->guard();
        // القفل محفوظ في الكاش: نمسحو الكاش كامل (نفس «مسح الكاش») — ما يمسش البيانات ولا يطلّع حد من حسابه
        Cache::flush();

        return back()->with('success', 'تم فك القفل — التسعير يرجع يشتغل خلال دقيقة');
    }

    /** يشغّل التسعير توا من غير ما يستنى الـ cron */
    public function run()
    {
        $this->guard();
        @set_time_limit(170);
        try {
            Artisan::call('cart-quotes:process');
            $out = trim(Artisan::output());

            return back()->with('success', 'تم التشغيل: ' . ($out ?: 'خلص'));
        } catch (\Throwable $e) {
            return back()->with('error', 'التشغيل طاح: ' . mb_substr($e->getMessage(), 0, 250));
        }
    }
}
