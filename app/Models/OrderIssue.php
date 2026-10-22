<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** مشكلة في طلب: نواقص، لم يصل، تالف، أخرى */
class OrderIssue extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['resolved_at' => 'datetime'];

    public const TYPES = [
        'missing' => ['نواقص', '📉'],
        'not_arrived' => ['لم يصل', '📭'],
        'damaged' => ['تالف / فيه عيب', '💔'],
        'review' => ['مراجعة الطلب', '🔎'],
        'other' => ['مشكلة أخرى', '⚠'],
    ];

    public const ACTIONS = [
        'later' => 'يُقرَّر لاحقاً',
        'deduct' => 'خصم من الإيصال',
        'credit' => 'رصيد للعميل',
        'refund' => 'إرجاع المبلغ نقداً',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_id');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function typeLabel(): string
    {
        return static::TYPES[$this->type][0] ?? $this->type;
    }
}
