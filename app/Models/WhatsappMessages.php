<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappMessages extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_messagess'; // double-s is the real DB table name

    protected $fillable = [
        'message_id',
        'session_uuid',
        'session_name',
        'receiving_phone',
        'contact_phone',
        'contact_lookup_after',
        'from',
        'phone',
        'name',
        'type',
        'message',
        'timestamp',
        'is_reply',
        'shein_product_reply_sent_at',
    ];

    protected $casts = [
        'is_reply'  => 'boolean',
        'timestamp' => 'datetime',
        'contact_lookup_after' => 'datetime',
        'shein_product_reply_sent_at' => 'datetime',
    ];

    /**
     * The AI reply associated with this incoming message.
     */
    public function replyMessage()
    {
        return $this->hasOne(ReplayMessages::class, 'whatsapp_messages_id');
    }
}
