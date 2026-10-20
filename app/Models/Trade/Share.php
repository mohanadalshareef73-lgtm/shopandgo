<?php

namespace App\Models\Trade;

use Illuminate\Database\Eloquent\Model;

class Share extends Model
{
    protected $table = 'trade_shares';

    protected $guarded = ['id'];

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
