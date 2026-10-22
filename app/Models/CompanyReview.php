<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** ⭐ تقييم الزبون للشركة بعد التسليم */
class CompanyReview extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['decided_at' => 'datetime'];

    public function customer()
    {
        return $this->belongsTo(Customer::class)->withoutGlobalScope('approved');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function decider()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** الاسم اللي يطلع للناس: الاسم الأول + أول حرف من الثاني (خصوصية) */
    public function publicName(): string
    {
        $parts = preg_split('/\s+/u', trim((string) optional($this->customer)->name));
        $first = $parts[0] ?? '';
        $second = isset($parts[1]) ? mb_substr($parts[1], 0, 1) . '.' : '';

        return trim($first . ' ' . $second) ?: 'زبون Shop & Go';
    }
}
