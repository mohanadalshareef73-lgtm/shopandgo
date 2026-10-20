<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dept extends Model
{
    use HasFactory;

    public function customer(){
        // دين خارجي بدون عميل: نرجعو اسم الجهة باش الشاشات القديمة ما تطيحش
        return $this->hasOne(Customer::class, 'id', 'customer_id')->withDefault(fn ($c, $d) => $c->forceFill(['name' => $d->org, 'code' => '', 'phone_1' => $d->phone]));
    }

    public function paids(){
        return $this->hasMany(DeptHistory::class, 'dept_id', 'id');
    }

    public function createdUser(){
        return $this->hasOne(User::class, 'id', 'created_id');
    }

    public function unpaidDepts()
    {
        return $this->hasMany(Order::class, 'customer_id', 'id')
            ->where(function ($query) {
                $query->where('status', 'unpaid')
                      ->orWhere(function ($query) {
                          $query->where('status', 'paid')
                                ->where('ship_price', '>', 0);
                      });
            });
    }

}
