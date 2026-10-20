<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** استعمال بطاقة SHEIN على رحلة شراء أو إيصال */
class SheinCardUse extends Model
{
    protected $guarded = ['id'];

    public function card()
    {
        return $this->belongsTo(SheinCard::class, 'card_id');
    }

    public function trip()
    {
        return $this->belongsTo(TripPurchase::class, 'trip_purchase_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function createdUser()
    {
        return $this->belongsTo(User::class, 'created_id');
    }
}
