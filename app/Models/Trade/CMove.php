<?php

namespace App\Models\Trade;

use Illuminate\Database\Eloquent\Model;

class CMove extends Model
{
    protected $table = 'trade_cmoves';

    protected $guarded = ['id'];

    public const TYPES = ['deposit' => '➕ إيداع', 'charge' => '💳 خصم فاتورة', 'debt' => '🧾 دين فاتورة', 'withdraw' => '➖ رجعناله', 'adjust' => '✎ تعديل'];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_id');
    }
}
