<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Platform extends Model
{
    use HasFactory;

    /** منصات داخلية للنظام (لا تظهر في القوائم) */
    public const INTERNAL = ['شحن فقط', 'بيع بطاقات SHEIN'];

    public function scopeVisible($q)
    {
        return $q->whereNotIn('name', self::INTERNAL);
    }

    public function orders(){
        return $this->hasMany(Order::class, 'platform_id', 'id');
    }

}
