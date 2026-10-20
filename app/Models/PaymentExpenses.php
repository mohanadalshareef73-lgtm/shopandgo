<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentExpenses extends Model
{
    use HasFactory;

    public function platform(){
        return $this->hasOne(Platform::class, 'id', 'platform_id');
    }

}
