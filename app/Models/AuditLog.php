<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'audit_logs';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['created_at' => 'datetime', 'changes' => 'array'];

    public const ACTIONS = ['created' => 'أضاف', 'updated' => 'عدّل', 'deleted' => 'مسح'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
