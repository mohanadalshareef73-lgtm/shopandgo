<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingCharges extends Model
{
    use HasFactory;

    public function type(){
        return $this->hasOne(ShippingType::class, 'id', 'type_id');
    }

    public function tripPurchase(){
        return $this->belongsTo(TripPurchase::class, 'trip_purchase_id');
    }
    public function company(){
        return $this->belongsTo(ShippingCompany::class, 'company_id');
    }
    public function trip(){
        return $this->hasOne(Trip::class, 'id', 'trip_id');
    }
    public function payment(){
        return $this->hasOne(PaymentMethod::class, 'id', 'payment_id');
    }

}
