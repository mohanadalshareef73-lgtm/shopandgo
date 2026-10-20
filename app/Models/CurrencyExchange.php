<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurrencyExchange extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['date' => 'date'];

    public const KINDS = [
        'usdt' => 'شراء USDT',
        'cash' => 'شراء دولار كاش',
        'visa_load' => 'تعبئة فيزا',
    ];

    public function fromPayment()
    {
        return $this->belongsTo(PaymentMethod::class, 'from_payment_id');
    }

    public function toPayment()
    {
        return $this->belongsTo(PaymentMethod::class, 'to_payment_id');
    }

    public function createdUser()
    {
        return $this->belongsTo(User::class, 'created_id');
    }

    /** آخر سعر صرف مسجل (دينار مقابل 1 دولار) */
    public static function latestRate(string $to = 'dollar'): ?float
    {
        $row = static::where('from_currency', 'dinar')->where('to_currency', $to)->latest('date')->latest('id')->first();

        return $row ? (float) $row->rate : null;
    }
}
