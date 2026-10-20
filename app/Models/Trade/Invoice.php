<?php

namespace App\Models\Trade;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $table = 'trade_invoices';

    protected $guarded = ['id'];

    protected $casts = ['closed_at' => 'datetime'];

    public function shares()
    {
        return $this->hasMany(Share::class, 'invoice_id');
    }

    public function customer()
    {
        return $this->belongsTo(\App\Models\Customer::class);
    }

    public function order()
    {
        return $this->belongsTo(\App\Models\Order::class);
    }

    /** إجمالي التكلفة = الفاتورة + المصاريف الإضافية */
    public function totalCost(): float
    {
        return round((float) $this->cost_lyd + (float) $this->extra_lyd, 2);
    }

    public function paidByPartners(): float
    {
        return round((float) $this->shares->sum('paid'), 2);
    }
}
