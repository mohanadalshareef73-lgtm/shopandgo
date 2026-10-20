<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CouponUse extends Model
{
    protected $guarded = ['id'];

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
