<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerTransaction extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['date' => 'date'];

    public const TYPES = [
        'withdrawal' => 'سحب يومي',
        'distribution' => 'توزيع فائض',
        'deposit' => 'إيداع من الشريك',
        'expense' => 'مصروف شخصي (دين على الشريك)',
        'settle' => 'تسديد دين الشريك',
    ];

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function payment()
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_id');
    }

    public function createdUser()
    {
        return $this->belongsTo(User::class, 'created_id');
    }

    public function isOutflow(): bool
    {
        return !in_array($this->type, ['deposit', 'settle'], true);
    }

    /** هل تتحرك الخزينة؟ (التسديد بالخصم من نصيب التوزيع لا يحرك الخزينة) */
    public function movesTreasury(): bool
    {
        return !($this->type === 'settle' && $this->via === 'distribution');
    }

    /** دين الشريك = مصروفاته الشخصية − ما سدده (نقداً أو بالخصم من التوزيع) */
    public static function debtOf(int $partnerId, string $currency = 'dinar'): float
    {
        $q = static::where('partner_id', $partnerId)->where('currency', $currency);

        return round((float) (clone $q)->where('type', 'expense')->sum('amount') - (float) (clone $q)->where('type', 'settle')->sum('amount'), 2);
    }
}
