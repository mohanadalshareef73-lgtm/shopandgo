<?php

namespace App\Services;

use App\Models\CompanyReview;
use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/** ⭐ طلب تقييم بعد التسليم + الأرقام الحية (كم سلّمنا، متوسط النجوم) */
class ReviewService
{
    public static function token(int $orderId): string
    {
        return substr(hash_hmac('sha256', 'review|' . $orderId, (string) config('app.key')), 0, 20);
    }

    public static function link(Order $o): string
    {
        return route('review.form', ['order' => $o->id, 't' => static::token($o->id)]);
    }

    /** بعد ما الطلبية تتسلم: رسالة واتساب فيها رابط التقييم (مرة وحدة للزبون كل 3 أيام) */
    public static function request(Order $o): void
    {
        if (!CustomerNotify::$enabled || !AppSettings::get('review_requests', true) || !Schema::hasTable('company_reviews')) {
            return; // تسليم صامت، أو الخاصية موقفة
        }
        $c = $o->customer;
        if (!$c || !$c->phone_1 || in_array($o->kind ?? null, ['card_sale', 'site_pay'], true)) {
            return;
        }
        if (!Cache::add('rev-req-' . $c->id, 1, now()->addDays(3))) {
            return;
        }
        if (CompanyReview::where('customer_id', $c->id)->where('created_at', '>=', now()->subDays(30))->exists()) {
            return; // قيّم قريب
        }
        $first = preg_split('/\s+/u', trim((string) $c->name))[0] ?? '';
        $text = "أهلاً {$first} 💙\nنتمنى طلبك {$o->code} وصلك وعجبك 🛍\n\nرأيك يهمنا ✨ قيّمنا في دقيقة (وتقدر تزيد صورة لطلبك):\n" . static::link($o) . "\n\nشكراً لثقتك في Shop & Go 🙏";
        try {
            MessageService::sendText('review_request', $text, [$c->phone_1], $c, $o);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** الأرقام الحية اللي تطلع للناس (تتحدث كل 10 دقايق) */
    public static function stats(): array
    {
        return Cache::remember('sg.public.stats', 600, function () {
            $delivered = Order::where('delivered', true)->where('status', '!=', 'canceld')->count();
            $customers = \App\Models\Customer::count();
            $avg = 0;
            $n = 0;
            if (Schema::hasTable('company_reviews')) {
                $q = CompanyReview::where('status', 'approved');
                $n = (clone $q)->count();
                $avg = $n ? round((float) (clone $q)->avg('rating'), 1) : 0;
            }

            return ['delivered' => $delivered, 'customers' => $customers, 'reviews' => $n, 'rating' => $avg];
        });
    }

    /** آخر التقييمات اللي وافقت عليها الإدارة */
    public static function latest(int $n = 6)
    {
        if (!Schema::hasTable('company_reviews')) {
            return collect();
        }

        return Cache::remember('sg.public.reviews.' . $n, 600, fn () => CompanyReview::with('customer:id,name')->where('status', 'approved')->latest('decided_at')->limit($n)->get());
    }

    public static function forget(): void
    {
        foreach (['sg.public.stats', 'sg.public.reviews.6', 'sg.public.reviews.12', 'sg.badge.reviews'] as $k) {
            Cache::forget($k);
        }
    }
}
