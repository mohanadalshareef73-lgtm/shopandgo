<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['expires_at' => 'date', 'active' => 'boolean'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function offer()
    {
        return $this->belongsTo(Offer::class);
    }

    public function uses()
    {
        return $this->hasMany(CouponUse::class);
    }

    public function label(): string
    {
        return $this->type === 'percent' ? rtrim(rtrim(number_format((float) $this->value, 2), '0'), '.') . '%' : \App\Services\TreasuryService::fmt($this->value) . ' د.ل';
    }

    public function isUsable(): bool
    {
        return $this->active && (!$this->expires_at || $this->expires_at->gte(now()->startOfDay())) && (!$this->uses_limit || $this->used_count < $this->uses_limit);
    }
}
