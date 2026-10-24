<?php

namespace App\Services;

use App\Models\Delivery;
use Illuminate\Support\Facades\Cache;

/**
 * 🛵 قروب «دليفري» على الواتساب: كل طلب توصيل (طلبية ولا عربون) يمشي بياناته للقروب أول ما يتسجل،
 * ولو دفع بالمصرف تمشي صورة الإيصال معاه.
 * القروب: من الإعدادات، ولا القروب اللي انكتب فيه #دليفري، ولا أي قروب اسمه فيه «دليفري».
 */
class DeliveryGroup
{
    public static function group(): ?array
    {
        $g = AppSettings::get('delivery_group');
        if (is_array($g) && !empty($g['id'])) {
            return $g;
        }
        foreach (['wa_groups_list', 'wa_groups_seen'] as $k) {
            foreach ((array) AppSettings::get($k, []) as $x) {
                $n = is_array($x) ? mb_strtolower((string) ($x['name'] ?? '')) : '';
                if (!empty($x['id']) && (str_contains($n, 'دليفري') || str_contains($n, 'delivery') || str_contains($n, 'توصيل'))) {
                    return $x;
                }
            }
        }

        return null;
    }

    /** نبعتو بعد ما الطلب يكمل يتسجل (والصفحة تفتح للعميل من غير ما يستنى) */
    public static function later(int $id): void
    {
        if (isset(static::$queued[$id])) {
            return;
        }
        static::$queued[$id] = true;
        app()->terminating(function () use ($id) {
            try {
                static::post($id);
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

    protected static array $queued = [];

    public static function text(Delivery $d): string
    {
        $d->loadMissing('customer', 'orders');
        $c = $d->customer;
        $f = fn ($n) => TreasuryService::fmt($n);
        $dep = $d->kind === 'deposit' || $d->cart_id || $d->deposit_order_id;
        $pay = ['cash' => '💵 كاش عند الاستلام', 'bank' => '🏦 تحويل مصرفي', 'mixed' => '💵 كاش + 🏦 مصرف', 'paid' => '✅ مدفوعة'][$d->pay_type] ?? ($d->pay_type ?: '—');
        $area = $d->area ?? null;
        $what = $d->cart_id ? '🛒 سلة #' . $d->cart_id : ($d->orders->pluck('code')->implode('، ') ?: optional(\App\Models\Order::find($d->deposit_order_id))->code);

        return "🛵 *طلب دليفري جديد* · {$d->code}\n\n"
            . '📦 نوع الطلب: *' . ($dep ? 'عربون' : 'طلبية') . "*\n"
            . '👤 كود العميل: *' . (optional($c)->code ?: 'غير مسجل') . '*' . (($d->recipient_name ?: optional($c)->name) ? ' · ' . ($d->recipient_name ?: optional($c)->name) : '') . "\n"
            . '📱 الهاتف: ' . ($d->recipient_phone ?: optional($c)->phone_1 ?: '—') . "\n"
            . '🏙 المدينة: ' . ($d->city ?: optional($c)->city ?: '—') . "\n"
            . '📍 المنطقة: ' . ($area ?: optional($c)->area ?: '—') . "\n"
            . ($d->address && trim($d->address) !== trim((string) ($area ?: optional($c)->area)) ? '🏠 العنوان: ' . $d->address . "\n" : '')
            . '💰 ' . ($dep ? 'العربون' : 'الباقي') . ': *' . $f($d->amount) . " د.ل*\n"
            . '💳 نوع الدفع: ' . $pay . "\n"
            . ($what ? '🧾 ' . ($dep ? 'الحجز' : 'الإيصالات') . ': ' . $what . "\n" : '')
            . ($d->location_url ? '🗺 ' . $d->location_url . "\n" : '')
            . ($d->note ? '📝 ' . $d->note . "\n" : '')
            . ($d->bank_receipt ? "📎 إيصال الدفع في الصورة 👇\n" : '');
    }

    public static function post(int $id, bool $force = false): bool
    {
        $d = Delivery::with('customer', 'orders')->find($id);
        if (!$d) {
            return false;
        }
        if (!$force && \App\Support\Fast::hasColumn('deliveries', 'group_sent_at') && $d->group_sent_at) {
            return true;
        }
        $g = static::group();
        if (!$g) {
            AlertService::push('delivery_request', '⚠ قروب الدليفري مش مسجل', 'طلب ' . $d->code . ' ما مشاش للقروب — اختار القروب من الإعدادات أو اكتب #دليفري في القروب', route('sg.settings'), 'warn');

            return false;
        }
        if (Cache::has('dlg-' . $d->id) && !$force) {
            return true;
        }
        Cache::put('dlg-' . $d->id, 1, now()->addMinutes(5));
        @set_time_limit(90);
        $wa = app(WhatsAppService::class);
        $session = $g['session'] ?? null;
        $ok = $wa->sendToGroup((string) $g['id'], static::text($d), null, null, $session);
        if ($ok && $d->bank_receipt) {
            $path = \Illuminate\Support\Facades\Storage::disk('public')->path($d->bank_receipt);
            if (is_file($path)) {
                sleep(2);
                $wa->sendToGroup((string) $g['id'], '📎 إيصال الدفع · ' . $d->code . ' · ' . (optional($d->customer)->code ?: $d->recipient_phone), $path, 'receipt-' . $d->code . '.' . pathinfo($path, PATHINFO_EXTENSION), $session);
            }
        }
        if ($ok && \App\Support\Fast::hasColumn('deliveries', 'group_sent_at')) {
            Delivery::whereKey($d->id)->update(['group_sent_at' => now()]);
        }
        if (!$ok) {
            Cache::forget('dlg-' . $d->id);
            AlertService::push('delivery_request', '⚠ طلب دليفري ما وصلش للقروب · ' . $d->code, ($d->recipient_name ?: optional($d->customer)->name) . ' · ' . $d->recipient_phone, route('sg.dispatch'), 'warn');
        }

        return $ok;
    }
}
