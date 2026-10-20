<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingCompany extends Model
{
    protected $guarded = ['id'];

    public function charges()
    {
        return $this->hasMany(ShippingCharges::class, 'company_id');
    }
}
