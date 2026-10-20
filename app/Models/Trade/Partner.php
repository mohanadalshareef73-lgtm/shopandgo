<?php

namespace App\Models\Trade;

use Illuminate\Database\Eloquent\Model;

class Partner extends Model
{
    protected $table = 'trade_partners';

    protected $guarded = ['id'];

    protected $casts = ['is_company' => 'boolean', 'active' => 'boolean'];

    public function moves()
    {
        return $this->hasMany(Move::class, 'partner_id')->latest('id');
    }

    public function shares()
    {
        return $this->hasMany(Share::class, 'partner_id');
    }
}
