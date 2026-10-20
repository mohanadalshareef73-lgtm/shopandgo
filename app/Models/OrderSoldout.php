<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderSoldout extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['notified_at' => 'datetime', 'decided_at' => 'datetime'];

    public const STATUSES = [
        'pending' => ['بانتظار رد العميل', 'w'],
        'cancelled' => ['أُلغي وخُصمت قيمته', 'd'],
        'replaced' => ['استُبدل بإيصال جديد', 'u'],
        'void' => ['اتلغى (تسجيل غلط)', 'n'],
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function newOrder()
    {
        return $this->belongsTo(Order::class, 'new_order_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_id');
    }

    public function decider()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
