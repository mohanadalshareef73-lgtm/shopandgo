<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TreasuryHistory extends Model
{
    use HasFactory;

    public function order(){
        return $this->hasOne(Order::class, 'id', 'procedure_id');
    }

    public function trip(){
        return $this->hasOne(Trip::class, 'id', 'procedure_id');
    }

    public function customer(){
        return $this->hasOne(Customer::class, 'id', 'procedure_id');
    }

    public function shippingCharge(){
        return $this->hasOne(ShippingCharges::class, 'id', 'procedure_id');
    }

    public function purchase(){
        return $this->hasOne(Purchase::class, 'id', 'procedure_id');
    }

    public function paymentExpenses(){
        return $this->hasOne(PaymentExpenses::class, 'id', 'procedure_id');
    }

    public function user(){
        return $this->hasOne(User::class, 'id', 'procedure_id');
    }

    public function createdUser(){
        return $this->hasOne(User::class, 'id', 'created_id');
    }

    public function dept(){
        return $this->hasOne(Dept::class, 'id', 'procedure_id');
    }

    public function payment(){
        return $this->hasOne(PaymentMethod::class, 'id', 'payment_id');
    }

}
