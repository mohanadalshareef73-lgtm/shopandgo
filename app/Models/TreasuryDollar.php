<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TreasuryDollar extends Model
{
    use HasFactory;

        public function payment(){
        return $this->hasOne(PaymentMethod::class, 'id', 'payment_id');
    }

}
