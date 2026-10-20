<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    protected $table = 'support_tickets';

    protected $guarded = ['id'];

    protected $casts = ['replied_at' => 'datetime'];

    public const TYPES = ['missing' => 'قطعة ناقصة', 'wrong' => 'مقاس أو لون خاطئ', 'damaged' => 'قطعة تالفة', 'late' => 'تأخير', 'account' => 'خطأ في الحساب', 'other' => 'أمر آخر'];

    public const STATUSES = ['open' => ['جديد', 'd'], 'answered' => ['تم الرد', 'b'], 'closed' => ['تم الحل', 'u']];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function replier()
    {
        return $this->belongsTo(User::class, 'replied_by');
    }
}
