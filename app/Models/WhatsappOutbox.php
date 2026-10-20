<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappOutbox extends Model
{
    protected $table = 'whatsapp_outbox';

    protected $guarded = ['id'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
