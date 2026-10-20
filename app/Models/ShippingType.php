<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingType extends Model
{
    use HasFactory;

    public function shippingCharges(){
        return $this->hasMany(ShippingCharges::class, 'type_id', 'id');
    }

}
