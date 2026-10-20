<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** رسالة/ملاحظة من المحل للزبون (تبان في حسابه وتنبعت واتساب) */
class CustomerMessage extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['read_at' => 'datetime'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdUser()
    {
        return $this->belongsTo(User::class, 'created_id');
    }
}
