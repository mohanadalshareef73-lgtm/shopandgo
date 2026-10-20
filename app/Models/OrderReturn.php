<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** مرتجع أو استبدال على إيصال */
class OrderReturn extends Model
{
    protected $table = 'order_returns';

    protected $guarded = ['id'];

    public const METHODS = [
        'deduct' => 'ينقص من اللي عليه',
        'cash' => 'رجعناله فلوس',
        'credit' => 'رصيد عندنا للطلبية الجاية',
        'none' => 'بدون فلوس',
    ];

    public const GOODS = [
        'shop' => 'رجعت للمحل (نبيعوها)',
        'supplier' => 'رجعت للمورد / الموقع',
        'damaged' => 'خربانة',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function payment()
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_id');
    }

    public function createdUser()
    {
        return $this->belongsTo(User::class, 'created_id');
    }

    public function typeLabel(): string
    {
        return $this->type === 'exchange' ? 'استبدال' : 'مرتجع';
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->method] ?? $this->method;
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
