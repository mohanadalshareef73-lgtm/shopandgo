<?php

namespace App\Services;

use App\Models\SgAlert;

/** تنبيه للإدارة: يظهر في الجرس فوق فوراً، وإذا مفعل يتبعث واتساب لأرقام الإدارة */
class AlertService
{
    public static function push(string $type, string $title, ?string $body = null, ?string $url = null, string $level = 'info', bool $whatsapp = false): SgAlert
    {
        // نفس التنبيه بالضبط لسه ما تقراش خلال 6 ساعات → ما نكرروش
        $dup = SgAlert::where('type', $type)->where('title', $title)->where('body', $body)->whereNull('read_at')->where('created_at', '>=', now()->subHours(6))->latest('id')->first();
        if ($dup) {
            return $dup;
        }
        $a = SgAlert::notify($type, $title, $body, $url, $level);
        try {
            PushService::notifyStaff(); // إشعار على جوالات الموظفين (مجاني)
        } catch (\Throwable $e) {
            report($e);
        }
        if ($whatsapp && AppSettings::get('courier_alert_whatsapp')) {
            foreach (AppSettings::phonesFor('alert_' . \App\Http\Controllers\Sg\AlertsController::groupOf($type)) as $ph) {
                try {
                    MessageService::sendText('alert', "🔔 $title\n" . ($body ?? ''), [$ph]);
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        return $a;
    }
}
