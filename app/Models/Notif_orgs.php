<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notif_orgs extends Model
{
    use HasFactory;

    public function notification(){
        return $this->hasOne(Notification::class, 'id', 'notification_id');
    }

    public function org(){
        return $this->hasOne(User::class, 'id', 'user_id');
    }

}
