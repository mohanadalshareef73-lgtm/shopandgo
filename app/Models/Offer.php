<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** عرض / خصم / مناسبة يظهر للعملاء في حساباتهم */
class Offer extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['starts_at' => 'date', 'ends_at' => 'date', 'active' => 'boolean'];

    public const KINDS = ['offer' => ['عرض', '🎁'], 'discount' => ['خصم', '🏷'], 'occasion' => ['مناسبة', '🎉']];

    public function scopeLive($q)
    {
        $today = now()->toDateString();

        return $q->where('active', true)->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', $today))
            ->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>=', $today));
    }

    public function coupon()
    {
        return $this->hasOne(Coupon::class);
    }

    public function discountLabel(): string
    {
        if (!$this->discount_type || !$this->discount_value) {
            return '';
        }

        return $this->discount_type === 'percent' ? 'خصم ' . rtrim(rtrim(number_format((float) $this->discount_value, 2), '0'), '.') . '%' : 'خصم ' . \App\Services\TreasuryService::fmt($this->discount_value) . ' د.ل';
    }
}
