<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaidHistory extends Model
{
    use HasFactory;

    /** مين قبض الدفعة: يتسجل أوتوماتيك من أي شاشة (قديمة أو جديدة) */
    protected static function booted()
    {
        static::creating(function (PaidHistory $p) {
            if (empty($p->created_id) && \Illuminate\Support\Facades\Schema::hasColumn('paid_histories', 'created_id')) {
                $p->created_id = auth('web')->id();
            }
        });
    }

    public function createdUser()
    {
        return $this->belongsTo(User::class, 'created_id');
    }

    /** اسم اللي قبضها — للدفعات القديمة نجيبوه من حركة الخزينة اللي في نفس الوقت */
    public function receiverName(): ?string
    {
        if ($this->created_id && $this->createdUser) {
            return $this->createdUser->name;
        }
        try {
            $h = TreasuryHistory::where('procedure_id', $this->order_id)->whereBetween('created_at', [$this->created_at->copy()->subMinute(), $this->created_at->copy()->addMinute()])
                ->where('price', $this->paid_price)->whereNotNull('created_id')->first();

            return $h ? optional(User::find($h->created_id))->name : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function order(){
        return $this->hasOne(Order::class, 'id', 'order_id');
    }

    public function customer(){
        return $this->hasOne(Customer::class, 'id', 'customer_id');
    }

    public function paymentMethod(){
        return $this->hasOne(PaymentMethod::class, 'id', 'payment_id');
    }

}
