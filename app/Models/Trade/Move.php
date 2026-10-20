<?php

namespace App\Models\Trade;

use Illuminate\Database\Eloquent\Model;

class Move extends Model
{
    protected $table = 'trade_moves';

    protected $guarded = ['id'];

    public const TYPES = [
        'deposit' => '➕ إيداع', 'withdraw' => '➖ سحب', 'invest' => '🧾 مساهمة في فاتورة', 'purchase' => '🛒 شراء (دفع الفاتورة)',
        'customer_pay' => '💵 سداد عميل', 'profit' => '📈 ربح', 'loss' => '📉 خسارة', 'company_in' => '🏢 من خزينة الشركة', 'to_company' => '🏢 لمالية الشركة', 'adjust' => '✎ تعديل',
    ];

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_id');
    }
}
