<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** تنبيه لحظي يطلع للإدارة (جرس فوق + إشعار المتصفح) */
class SgAlert extends Model
{
    protected $table = 'sg_alerts';

    protected $guarded = ['id'];

    protected $casts = ['read_at' => 'datetime'];

    /** تنبيهات للمدير بس (الموظفين ما يشوفوهاش) */
    public const ADMIN_ONLY = ['card_sold'];

    protected static function booted()
    {
        static::addGlobalScope('admin_only', function ($q) {
            try {
                $u = auth('web')->user();
                if ($u && !$u->hasRole('Admin')) {
                    $q->whereNotIn('type', self::ADMIN_ONLY);
                }
            } catch (\Throwable $e) {
            }
        });
    }

    public static function notify(string $type, string $title, ?string $body = null, ?string $url = null, string $level = 'info'): self
    {
        return static::create(compact('type', 'title', 'body', 'url', 'level'));
    }
}
