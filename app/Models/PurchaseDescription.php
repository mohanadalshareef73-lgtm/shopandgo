<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseDescription extends Model
{
    use HasFactory;

    public function purchases(){
        return $this->hasMany(Purchase::class, 'desc_id', 'id');
    }

}
