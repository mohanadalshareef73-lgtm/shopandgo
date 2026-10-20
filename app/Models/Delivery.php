<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** توصيل طلبية لزبون عن طريق مندوب (أو طلب توصيل من الزبون) */
class Delivery extends Model
{
    protected $guarded = ['id'];

    /** توصيلات انبعتت رسالتها للمندوب في نفس الطلب (باش ما تتكررش) */
    protected static array $waSent = [];

    protected static function booted()
    {
        // 🛵 أول ما توصيلة تتعين لمندوب (جديدة أو تبدل المندوب): تجيه رسالة واتساب طول
        static::saved(function (Delivery $d) {
            try {
                if (!$d->courier_id || !$d->isOpen() || !($d->wasRecentlyCreated || $d->wasChanged('courier_id'))) {
                    return;
                }
                if (!\App\Services\AppSettings::get('courier_wa_notify', true)) {
                    return; // 🔕 موقفة من الإعدادات
                }
                $key = $d->id . ':' . $d->courier_id;
                if (isset(static::$waSent[$key])) {
                    return;
                }
                static::$waSent[$key] = true;
                $id = $d->id;
                app()->terminating(function () use ($id) {
                    try {
                        static::notifyCourierWhatsApp($id);
                    } catch (\Throwable $e) {
                        report($e);
                    }
                });
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

    /** رسالة واتساب للمندوب بتفاصيل التوصيلة */
    public static function notifyCourierWhatsApp(int $id): bool
    {
        $d = static::with(['courier', 'orders'])->find($id);
        if (!$d || !$d->courier || !$d->isOpen() || !trim((string) $d->courier->phone)) {
            return false;
        }
        $coll = $d->toCollect();
        $map = $d->location_url ?: null;
        $link = rtrim((string) config('app.url'), '/') . '/courier';
        try {
            $link = route('courier.home');
        } catch (\Throwable $e) {
        }
        $codes = $d->orders->pluck('code')->filter()->implode('، ');
        $msg = "🛵 *توصيلة جديدة عندك* · {$d->code}\n\n"
            . '👤 ' . ($d->recipient_name ?: optional($d->customer)->name ?: 'عميل') . ($d->recipient_phone ? ' · ' . $d->recipient_phone : '') . "\n"
            . ($d->address ? '📍 ' . $d->address . "\n" : '')
            . ($map ? '🗺 ' . $map . "\n" : '')
            . ($d->package_type ? '📦 ' . $d->package_type . ((int) $d->parcels > 0 ? ' · ' . (int) $d->parcels . ' طرد' : '') . "\n" : '')
            . ($codes ? '🧾 ' . $codes . "\n" : '')
            . ($coll > 0.009 ? '💵 *اقبض: ' . \App\Services\TreasuryService::fmt($coll) . " د.ل*\n" : "✅ خالص — ما تقبضش شي\n")
            . ($d->note ? '📝 ' . mb_substr((string) $d->note, 0, 200) . "\n" : '')
            . "\n👉 افتح تطبيق المندوب:\n" . $link;

        return \App\Services\MessageService::sendText('courier_job', $msg, [$d->courier->phone]);
    }

    protected $casts = [
        'assigned_at' => 'datetime', 'out_at' => 'datetime', 'delivered_at' => 'datetime',
    ];

    public const STATUSES = [
        'requested' => ['طلب من الزبون · ينتظر مندوب', 'w'],
        'assigned' => ['قيد استلام المندوب', 'b'],
        'out' => ['قيد التوصيل', 'v'],
        'delivered' => ['مستلمة ✓', 'u'],
        'problem' => ['في مشكلة', 'd'],
        'returned' => ['رجعت للشركة (للرف)', 'n'],
        'cancelled' => ['ملغي', 'n'],
    ];

    public const PAY_TYPES = ['cash' => 'كاش', 'bank' => 'مصرف', 'mixed' => 'كاش + مصرف', 'paid' => 'خالص'];

    public function orders()
    {
        return $this->belongsToMany(Order::class, 'delivery_order')->withPivot('due')->withTimestamps();
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function courier()
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    public function events()
    {
        return $this->hasMany(DeliveryEvent::class)->latest();
    }

    public function createdUser()
    {
        return $this->belongsTo(User::class, 'created_id');
    }

    public function settlement()
    {
        return $this->belongsTo(CourierSettlement::class, 'settlement_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['requested', 'assigned', 'out', 'problem'], true);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function statusChip(): string
    {
        return self::STATUSES[$this->status][1] ?? 'n';
    }

    public const FEE_PAYERS = ['customer' => 'على العميل', 'company' => 'على الشركة'];

    /** الشركة تتحمل أجرة التوصيل (لا تُطلب من العميل) */
    public function companyPays(): bool
    {
        return $this->fee_payer === 'company';
    }

    /** الأجرة المطلوبة من العميل */
    public function customerFee(): float
    {
        return $this->companyPays() ? 0.0 : (float) $this->fee;
    }

    /** أجرة المندوب/المكتب المستحقة له في الحساب: كاملة للمكتب أو إذا كانت على الشركة، وإلا من الكاش الذي قبضه */
    public function courierFee(?User $courier = null): float
    {
        $courier = $courier ?: $this->courier;
        if ($this->companyPays() || ($courier && $courier->isOffice())) {
            return (float) $this->fee;
        }

        return min((float) $this->fee, (float) $this->collected_cash);
    }

    /** المطلوب يقبضه المندوب = باقي الإيصالات + أجرة التوصيل (إذا كانت على العميل) */
    public function toCollect(): float
    {
        return round((float) $this->amount + $this->customerFee(), 2);
    }

    /** رابط الخريطة: الرابط اللي حطه الزبون، أو بحث بالعنوان */
    public function mapUrl(): ?string
    {
        if ($this->location_url) {
            return $this->location_url;
        }
        $q = trim(($this->address ?? '') . ' ' . ($this->city ?? ''));

        return $q ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($q) : null;
    }

    public static function newCode(): string
    {
        do {
            $c = 'D-' . random_int(10000, 99999);
        } while (static::where('code', $c)->exists());

        return $c;
    }

    public function log(string $status, ?string $note = null): void
    {
        DeliveryEvent::create(['delivery_id' => $this->id, 'user_id' => auth('web')->id(), 'status' => $status, 'note' => $note]);
    }
}
