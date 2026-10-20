<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeptHistory extends Model
{
    use HasFactory;

    public function dept(){
        return $this->hasOne(Dept::class, 'id', 'dept_id');
    }

    public function paids(){
        return $this->hasMany(DeptHistory::class, 'dept_id', 'dept_id');
    }

    public function payment(){
        return $this->hasOne(PaymentMethod::class, 'id', 'payment_id');
    }

}
