<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

            /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'customer_id',
        'trip_id',
        'code',
        'status',
        'qty',
        'bags_qty',
        'total_price',
        'currency',
        'currency_sign',
        'paid_price',
        'ship_price',
        'discount',
        'discount_reason',
        'discount_reason_note',
        'kilo',
        'note',
        'created_id',
        'received_qty',
        'package_type',
        'received_at',
        'received_by',
        'missing_qty',
        'missing_value',
        'missing_action',
        'delivered_by',
        'delivered_time',
        'delivery_discount',
        'points_used',
        'points_earned',
        'delivery_id',
        'delivered_qty',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'arrived_notified_at' => 'datetime',
        'delivered_time' => 'datetime',
    ];

    /** النقاط تتحسب أوتوماتيك لأي إيصال جديد (من الفورم القديم أو الجديد) */
    protected static function booted()
    {
        static::created(function (Order $o) {
            try {
                \App\Services\PointsService::award($o);
            } catch (\Throwable $e) {
                report($e);
            }
        });
        // ⭐ تسلّمت؟ نطلبو من الزبون يقيّمنا (ما يمشيش في التسليم الصامت)
        static::updated(function (Order $o) {
            if ($o->wasChanged('delivered') && $o->delivered) {
                try {
                    \App\Services\ReviewService::request($o);
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        });
        // العميل طلع مستوى جديد (برونزي/فضي/ذهبي)؟ نبلغوه
        static::saved(function (Order $o) {
            if ($o->wasRecentlyCreated || $o->wasChanged(['total_price', 'discount', 'status'])) {
                try {
                    \App\Services\LoyaltyService::check($o->customer);
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        });
    }

    public const STAGES = [
        'none' => 'مش في رحلة',
        'prep' => 'قيد التجهيز',
        'way' => 'في الطريق',
        'ready' => 'جاهزة للاستلام',
        'done' => 'تم التسليم',
        'cx' => 'ملغاة',
    ];

    /** المرحلة اللي فيها الإيصال توا */
    public function stage(): string
    {
        if ($this->status === 'canceld') {
            return 'cx';
        }
        if ($this->delivered) {
            return 'done';
        }
        if ($this->arrived) {
            return 'ready';
        }
        if (!$this->trip_details) {
            return 'none';
        }
        return \App\Services\AppSettings::statusStage($this->trip_status) === 'prep' ? 'prep' : 'way';
    }

    public function stageLabel(): string
    {
        $stage = $this->stage();
        if (in_array($stage, ['prep', 'way'], true) && $this->trip_status) {
            return $this->trip_status;
        }

        return self::STAGES[$stage];
    }

    /** كم خطوة من 5 (تسجّل، دخل رحلة، في الطريق، وصل، تسلّم) */
    public function stepsDone(): int
    {
        return ['none' => 1, 'prep' => 2, 'way' => 3, 'ready' => 4, 'done' => 5, 'cx' => 0][$this->stage()];
    }

    /** كم يوم البضاعة قاعدة في الرف */
    public function shelfDays(): ?int
    {
        if (!$this->arrived || $this->delivered) {
            return null;
        }
        $from = $this->received_at ?: ($this->arrived_at ? \Carbon\Carbon::parse($this->arrived_at) : null);

        return $from ? (int) $from->startOfDay()->diffInDays(now()->startOfDay()) : null;
    }

    public const PACKAGE_TYPES = ['كيس', 'كرتونة صغيرة', 'كرتونة كبيرة', 'ظرف', 'شنطة'];

    /** الإجمالي المطلوب من الزبون = الفاتورة + الشحن - التخفيض */
    public function grandTotal(): float
    {
        return (float) $this->total_price + (float) $this->ship_price - (float) $this->discount;
    }

    /** المتبقي على الزبون مع الشحن */
    public function remaining(): float
    {
        return round($this->grandTotal() - (float) $this->paid_price, 2);
    }

    public function refreshPaymentStatus(): void
    {
        if (in_array($this->status, ['canceld'], true)) {
            return;
        }
        $paid = (float) $this->paid_price;
        if ($this->ship_price > 0 && $paid >= $this->grandTotal() - 0.001) {
            $this->status = 'paid_full';
        } elseif ($paid >= (float) $this->total_price - (float) $this->discount - 0.001) {
            $this->status = 'paid';
        } else {
            $this->status = 'unpaid';
        }
    }

    /** التوصيل عن طريق مندوب (لو موجود) */
    public function delivery(){
        return $this->belongsTo(Delivery::class, 'delivery_id');
    }

    /** الإيصال مع مندوب توا (طلع للتوصيل وما وصلش) */
    public function withCourier(): bool
    {
        return $this->delivery_id && !$this->delivered && optional($this->delivery)->isOpen();
    }

    public function tripPurchase(){
        return $this->hasOne(TripPurchase::class, 'trip_code', 'trip_details');
    }

    public function deliveredUser(){
        return $this->hasOne(User::class, 'id', 'delivered_by');
    }

    public function receivedUser(){
        return $this->hasOne(User::class, 'id', 'received_by');
    }

    public function createdUser(){
        return $this->hasOne(User::class, 'id', 'created_id');
    }

    public function customer(){
        return $this->hasOne(Customer::class, 'id', 'customer_id');
    }

    public function platform(){
        return $this->hasOne(Platform::class, 'id', 'platform_id');
    }

    public function trip(){
        return $this->hasOne(Trip::class, 'id', 'trip_id');
    }

    public function returns()
    {
        return $this->hasMany(OrderReturn::class)->latest();
    }

    public function paids(){
        return $this->hasMany(PaidHistory::class, 'order_id', 'id');
    }

    public function notes(){
        return $this->hasMany(OrderNotes::class, 'order_id', 'id');
    }

    public function items(){
        return $this->hasMany(OrderItem::class, 'order_id', 'id');
    }

    public function paymentMethod(){
        return $this->hasOne(PaymentMethod::class, 'id', 'payment_id');
    }

}
