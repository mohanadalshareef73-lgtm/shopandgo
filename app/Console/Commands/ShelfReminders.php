<?php

namespace App\Console\Commands;

use App\Services\AlertService;
use App\Services\AppSettings;
use App\Services\ReminderService;
use Illuminate\Console\Command;

/** كل يوم: تذكير أوتوماتيكي للي نسيوا بضاعتهم في الرف + تنبيه للإدارة */
class ShelfReminders extends Command
{
    protected $signature = 'sg:shelf-reminders {--dry : يعرض فقط، لا يُرسل}';

    protected $description = 'تذكير الرف الأوتوماتيكي';

    public function handle(): int
    {
        if (!AppSettings::get('shelf_auto_remind', true) && !$this->option('dry')) {
            $this->info('التذكير الأوتوماتيكي مقفول من الإعدادات');

            return self::SUCCESS;
        }
        $due = ReminderService::shelfDue();
        $this->info($due->count() . ' طرد · ' . $due->pluck('customer_id')->unique()->count() . ' عميل');
        if ($this->option('dry')) {
            return self::SUCCESS;
        }
        $r = $due->count() ? ReminderService::sendShelf($due) : ['sent' => 0, 'failed' => 0, 'customers' => 0, 'orders' => 0];
        $stuck = ReminderService::shelfStuck();
        if ($r['orders'] || $stuck->count()) {
            AlertService::push('shelf', '📦 تذكير الرف: أُرسل لـ ' . $r['sent'] . ' عميل' . ($r['failed'] ? ' (' . $r['failed'] . ' لم تصل)' : ''),
                ($stuck->count() ? $stuck->count() . ' طرد خذا كل التذكيرات ولا يزال في الرف — اتصلوا بهم' : null), route('sg.delivery.done'), $stuck->count() ? 'warn' : 'info');
        }
        $this->info('أُرسل: ' . $r['sent'] . ' · فشل: ' . $r['failed']);

        return self::SUCCESS;
    }
}
