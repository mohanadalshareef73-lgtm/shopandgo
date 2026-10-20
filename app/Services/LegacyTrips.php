<?php

namespace App\Services;

use App\Models\Log;
use App\Models\Order;
use App\Models\TripPurchase;
use Illuminate\Support\Facades\DB;

/**
 * رحلات المنظومة القديمة: الطلبات القديمة مكتوب عليها رقم الرحلة (trip_details مثل B-0389) بدون سجل رحلة.
 * الاستيراد ينشئ رحلة في المنظومة الجديدة لكل رقم، فتظهر في الرحلات بعملائها وتتعدل حالتها وبياناتها عادي.
 * - لا تتحرك أي فلوس في الخزينة (الرحلة اندفعت في المنظومة القديمة).
 * - التكلفة غير معروفة (0) → علامة «رحلة قديمة» ولا تدخل في تقارير الأرباح لين تُكتب تكلفتها.
 * - الطلبات نفسها ما تتغيرش.
 */
class LegacyTrips
{
    /** أرقام الرحلات القديمة اللي ما عندهاش رحلة: [رقم => [عدد الطلبات، أول تاريخ]] */
    public static function pending(): array
    {
        $existing = TripPurchase::pluck('trip_code')->map(fn ($c) => mb_strtolower(trim($c)))->flip();
        $rows = Order::query()->whereNotNull('trip_details')->where('trip_details', '!=', '')
            ->selectRaw('trip_details, COUNT(*) as n, MIN(created_at) as first_at')
            ->groupBy('trip_details')->get();
        $out = [];
        foreach ($rows as $r) {
            $code = trim((string) $r->trip_details);
            $key = mb_strtolower($code);
            if ($code === '' || isset($existing[$key])) {
                continue;
            }
            // نفس الرقم بحروف كبيرة/صغيرة أو مسافة زايدة = رحلة وحدة
            if (isset($out[$key])) {
                $out[$key]['n'] += (int) $r->n;
                $out[$key]['first_at'] = min($out[$key]['first_at'], (string) $r->first_at);
                continue;
            }
            $out[$key] = ['code' => $code, 'n' => (int) $r->n, 'first_at' => (string) $r->first_at];
        }

        return array_values($out);
    }

    public static function import(): int
    {
        $list = self::pending();
        if (!$list) {
            return 0;
        }
        $cash = AppSettings::cashPaymentId();
        $rate = (float) (\App\Models\CurrencyExchange::latestRate() ?: (TripPurchase::where('rate', '>', 0)->latest('purchased_at')->value('rate') ?: 1));
        $uid = auth()->id() ?: \App\Models\User::orderBy('id')->value('id');
        $n = 0;
        DB::transaction(function () use ($list, $cash, $rate, $uid, &$n) {
            foreach (array_chunk($list, 200) as $chunk) {
                foreach ($chunk as $t) {
                    TripPurchase::create([
                        'trip_code' => mb_substr($t['code'], 0, 191),
                        'purchased_at' => substr($t['first_at'] ?: now()->toDateString(), 0, 10),
                        'cost' => 0, 'cost_currency' => 'dollar', 'payment_id' => $cash, 'rate' => $rate,
                        'coupon_used' => false, 'legacy' => true,
                        'note' => 'رحلة من المنظومة القديمة (' . $t['n'] . ' إيصال) — التكلفة غير مسجلة',
                        'created_id' => $uid,
                    ]);
                    $n++;
                }
            }
        });
        Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ' استيراد رحلات المنظومة القديمة', 'log' => ' ' . $n . ' رحلة — بدون أي حركة مالية']);

        return $n;
    }
}
