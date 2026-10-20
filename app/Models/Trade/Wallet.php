<?php

namespace App\Models\Trade;

use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    protected $table = 'trade_wallets';

    protected $guarded = ['id'];
}
