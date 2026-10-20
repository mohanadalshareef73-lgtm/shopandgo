<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Partner extends Model
{
    protected $guarded = ['id'];

    public function transactions()
    {
        return $this->hasMany(PartnerTransaction::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
