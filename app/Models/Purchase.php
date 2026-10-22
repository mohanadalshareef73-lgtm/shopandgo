<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use HasFactory;

    public function desc(){
        return $this->hasOne(PurchaseDescription::class, 'id', 'desc_id');
    }

        public function payment(){
        return $this->hasOne(PaymentMethod::class, 'id', 'payment_id');
    }

    /** 👥 الموظف اللي عليه المصروف (سلفة / مصروف شغل / مرتب) */
    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }


}
