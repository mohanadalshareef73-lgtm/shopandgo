<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReplayMessages extends Model
{
    use HasFactory;

    protected $table = 'replay_messages';

    protected $fillable = [
        'message',
        'whatsapp_messages_id',
    ];

    /**
     * The incoming message this reply belongs to.
     */
    public function whatsappMessage()
    {
        return $this->belongsTo(WhatsappMessages::class, 'whatsapp_messages_id');
    }
}
