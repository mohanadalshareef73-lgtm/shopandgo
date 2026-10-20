<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** تسكير الحساب مع المندوب */
class CourierSettlement extends Model
{
    protected $guarded = ['id'];

    public function courier()
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    public function deliveries()
    {
        return $this->hasMany(Delivery::class, 'settlement_id');
    }

    public function createdUser()
    {
        return $this->belongsTo(User::class, 'created_id');
    }
}
