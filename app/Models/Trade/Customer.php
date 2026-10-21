<?php

namespace App\Models\Trade;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $table = 'trade_customers';

    protected $guarded = ['id'];

    public function moves()
    {
        return $this->hasMany(CMove::class, 'trade_customer_id')->latest('id');
    }

    public function account()
    {
        return $this->belongsTo(\App\Models\Customer::class, 'customer_id');
    }
}
