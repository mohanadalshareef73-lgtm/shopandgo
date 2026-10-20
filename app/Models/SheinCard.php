<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** بطاقة SHEIN: قيمتها بالدولار، تكلفتها علينا، ورصيدها الباقي */
class SheinCard extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['pin'];

    protected $casts = [
        'pin' => 'encrypted',
        'purchased_at' => 'date',
        'expires_at' => 'date',
        'redeemed_at' => 'datetime',
        'lost_at' => 'datetime',
        'sold_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public const STATUSES = [
        'new' => ['جديدة', 'b'],
        'partial' => ['مستعملة جزئياً', 'w'],
        'used' => ['خلصت', 'n'],
        'expired' => ['انتهت', 'd'],
        'invalid' => ['مرفوضة / غلط', 'd'],
        'sold' => ['مباعة لعميل', 'u'],
    ];

    public function uses()
    {
        return $this->hasMany(SheinCardUse::class, 'card_id')->latest();
    }

    public function soldOrder()
    {
        return $this->belongsTo(Order::class, 'sold_order_id');
    }

    public function soldCustomer()
    {
        return $this->belongsTo(Customer::class, 'sold_customer_id');
    }

    public function soldUser()
    {
        return $this->belongsTo(User::class, 'sold_by');
    }

    public function payment()
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_id');
    }

    public function createdUser()
    {
        return $this->belongsTo(User::class, 'created_id');
    }

    public function isUsable(): bool
    {
        return in_array($this->status, ['new', 'partial'], true) && $this->balance > 0.009;
    }

    /** كم كلفنا الدولار الواحد في البطاقة هذي (دينار) */
    public function costPerDollar(): float
    {
        return $this->face_value > 0 ? round($this->cost_dinar / $this->face_value, 4) : 0;
    }

    /** تكلفة الرصيد الباقي بالدينار */
    public function balanceDinar(): float
    {
        return round($this->balance * $this->costPerDollar(), 2);
    }

    public function masked(): string
    {
        $n = (string) $this->card_no;

        return strlen($n) > 6 ? str_repeat('•', min(8, strlen($n) - 4)) . substr($n, -4) : $n;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function statusChip(): string
    {
        return self::STATUSES[$this->status][1] ?? 'n';
    }

    public function refreshStatus(): void
    {
        if (in_array($this->status, ['expired', 'invalid'], true)) {
            return;
        }
        $this->status = $this->balance <= 0.009 ? 'used' : ($this->balance < $this->face_value - 0.009 ? 'partial' : 'new');
    }
}
