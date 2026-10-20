<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartStatusAudit extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['changed_at' => 'datetime'];

    public function cart()
    {
        return $this->belongsTo(CartQuote::class, 'cart_quote_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
