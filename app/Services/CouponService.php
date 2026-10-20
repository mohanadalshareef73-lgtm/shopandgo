<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponUse;
use App\Models\Customer;
use App\Models\CustomerMessage;
use App\Models\Log;
use App\Models\Offer;
use App\Models\Order;
use Illuminate\Support\Str;
use RuntimeException;

/** الكوبونات (خصم بنسبة أو مبلغ) والعروض */
class CouponService
{
    public static function newCode(string $prefix = 'SG'): string
    {
        do {
            $code = strtoupper($prefix . Str::random(6));
            $code = strtr($code, ['O' => '7', '0' => '8', 'I' => '9', 'L' => '4']);
        } while (Coupon::where('code', $code)->exists());

        return $code;
    }

    /** قيمة الخصم المتوقعة لكوبون على إيصال (بدون تطبيق) */
    public static function preview(Coupon $c, Order $o): float
    {
        $base = max(0, $o->grandTotal());
        if ($c->min_order && $base + 0.009 < (float) $c->min_order) {
            throw new RuntimeException('الحد الأدنى للإيصال لاستخدام الكوبون ' . TreasuryService::fmt($c->min_order) . ' د.ل');
        }
        $v = $c->type === 'percent' ? $base * (float) $c->value / 100 : (float) $c->value;
        if ($c->max_discount) {
            $v = min($v, (float) $c->max_discount);
        }

        return round(min($v, max(0, $o->remaining())), 2);
    }

    public static function find(string $code): Coupon
    {
        $c = Coupon::where('code', strtoupper(trim($code)))->first();
        if (!$c) {
            throw new RuntimeException('الكوبون غير موجود');
        }

        return $c;
    }

    /** يطبّق الكوبون على الإيصال كخصم */
    public static function apply(Order $o, string $code): float
    {
        $c = static::find($code);
        if (!$c->isUsable()) {
            throw new RuntimeException('الكوبون منتهي أو مستخدم بالكامل');
        }
        if ($c->customer_id && (int) $c->customer_id !== (int) $o->customer_id) {
            throw new RuntimeException('هذا الكوبون خاص بعميل آخر');
        }
        if (CouponUse::where('coupon_id', $c->id)->where('order_id', $o->id)->exists()) {
            throw new RuntimeException('الكوبون مستخدم على هذا الإيصال مسبقاً');
        }
        if ($c->uses_per_customer && CouponUse::where('coupon_id', $c->id)->where('customer_id', $o->customer_id)->count() >= $c->uses_per_customer) {
            throw new RuntimeException('العميل استخدم هذا الكوبون الحد المسموح من المرات');
        }
        $v = static::preview($c, $o);
        if ($v <= 0) {
            throw new RuntimeException('لا يوجد مبلغ متبقٍ على الإيصال لتطبيق الكوبون');
        }
        $o->discount = (float) $o->discount + $v;
        $o->discount_reason = trim(($o->discount_reason ? $o->discount_reason . ' + ' : '') . 'كوبون ' . $c->code);
        $o->refreshPaymentStatus();
        $o->save();
        CouponUse::create(['coupon_id' => $c->id, 'order_id' => $o->id, 'customer_id' => $o->customer_id, 'amount' => $v, 'created_id' => auth('web')->id()]);
        $c->used_count = (int) $c->used_count + 1;
        $c->save();
        Log::create(['user_id' => auth('web')->id(), 'type' => 'order', 'title' => ' كوبون على ' . $o->code, 'log' => ' ' . $c->code . ' (' . $c->label() . ') · خصم ' . $v]);
        CustomerNotify::inbox($o->customer, '🎟 طُبّق الكوبون ' . $c->code . ' على الإيصال ' . $o->code . ' · خصم ' . TreasuryService::fmt($v) . ' د.ل', $o, 'coupon');

        return $v;
    }

    /** تحويل نقاط العميل إلى كوبون خصم بالنسبة (حسب الإعدادات) */
    public static function fromPoints(Customer $c): Coupon
    {
        if (!PointsService::couponEnabled()) {
            throw new RuntimeException('تحويل النقاط إلى كوبون غير مفعّل');
        }
        $cost = (int) AppSettings::get('coupon_points', 100);
        if ((int) $c->points < $cost) {
            throw new RuntimeException('تحتاج ' . $cost . ' نقطة على الأقل، رصيدك ' . (int) $c->points);
        }
        PointsService::add($c, -$cost, 'تحويل إلى كوبون خصم');
        $days = (int) AppSettings::get('coupon_days', 30);
        $cp = Coupon::create([
            'code' => static::newCode('PT'), 'customer_id' => $c->id, 'source' => 'points', 'type' => 'percent',
            'value' => (float) AppSettings::get('coupon_percent', 10), 'max_discount' => (float) AppSettings::get('coupon_max', 0) ?: null,
            'uses_limit' => 1, 'points_spent' => $cost, 'expires_at' => $days > 0 ? now()->addDays($days)->toDateString() : null,
        ]);
        CustomerNotify::inbox($c, '🎟 كوبون جديد ' . $cp->code . ': خصم ' . $cp->label() . ($cp->max_discount ? ' (حتى ' . TreasuryService::fmt($cp->max_discount) . ' د.ل)' : '') . ($cp->expires_at ? ' · صالح حتى ' . $cp->expires_at->format('Y-m-d') : '') . ' · مقابل ' . $cost . ' نقطة', null, 'coupon');

        return $cp;
    }

    /** إنشاء عرض: كوبون عام (اختياري) + إشعار لكل العملاء في حساباتهم */
    public static function createOffer(array $d, bool $notify = true): Offer
    {
        $offer = Offer::create([
            'kind' => $d['kind'], 'title' => $d['title'], 'body' => $d['body'] ?? null,
            'discount_type' => $d['discount_type'] ?? null, 'discount_value' => $d['discount_value'] ?? null,
            'max_discount' => $d['max_discount'] ?? null, 'min_order' => $d['min_order'] ?? null,
            'uses_per_customer' => $d['uses_per_customer'] ?? null,
            'starts_at' => $d['starts_at'] ?? null, 'ends_at' => $d['ends_at'] ?? null, 'active' => true, 'created_id' => auth('web')->id(),
        ]);
        if ($offer->discount_type && (float) $offer->discount_value > 0) {
            $code = strtoupper(trim((string) ($d['coupon_code'] ?? ''))) ?: static::newCode('OF');
            if (Coupon::where('code', $code)->exists()) {
                throw new RuntimeException('كود الكوبون ' . $code . ' مستخدم، اختر كوداً آخر');
            }
            Coupon::create([
                'code' => $code, 'offer_id' => $offer->id, 'source' => 'offer', 'type' => $offer->discount_type, 'value' => $offer->discount_value,
                'max_discount' => $offer->max_discount, 'min_order' => $offer->min_order, 'uses_per_customer' => $offer->uses_per_customer ?: 1,
                'expires_at' => $offer->ends_at, 'created_id' => auth('web')->id(),
            ]);
            $offer->coupon_code = $code;
            $offer->save();
        }
        Log::create(['user_id' => auth('web')->id(), 'type' => 'customer', 'title' => ' عرض جديد', 'log' => ' ' . $offer->title . ($offer->coupon_code ? ' · كوبون ' . $offer->coupon_code : '')]);
        if ($notify) {
            $offer->notified = static::notifyAll($offer);
            $offer->save();
        }

        return $offer;
    }

    /** إشعار في حساب كل عميل معتمد (إدخال جماعي سريع) */
    public static function notifyAll(Offer $offer): int
    {
        $icon = Offer::KINDS[$offer->kind][1] ?? '🎁';
        $body = $icon . ' ' . $offer->title . ($offer->discountLabel() ? ' · ' . $offer->discountLabel() : '') . ($offer->coupon_code ? ' · الكود: ' . $offer->coupon_code : '')
            . ($offer->ends_at ? ' · حتى ' . $offer->ends_at->format('Y-m-d') : '') . ($offer->body ? "\n" . $offer->body : '');
        $n = 0;
        $hasKind = \Illuminate\Support\Facades\Schema::hasColumn('customer_messages', 'kind');
        Customer::query()->select('id')->chunkById(500, function ($chunk) use ($body, &$n, $hasKind) {
            $now = now();
            $rows = $chunk->map(fn ($c) => ['customer_id' => $c->id, 'body' => $body, 'created_id' => auth('web')->id(), 'created_at' => $now, 'updated_at' => $now] + ($hasKind ? ['kind' => 'offer'] : []))->all();
            CustomerMessage::insert($rows);
            $n += count($rows);
        });

        return $n;
    }
}
