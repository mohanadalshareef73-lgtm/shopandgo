<?php

namespace App\Console\Commands;

use App\Services\AlertService;
use App\Services\AppSettings;
use App\Services\ReminderService;
use App\Services\TreasuryService;
use Illuminate\Console\Command;

/** كل يوم: يجهز قائمة تذكير الديون وينبه الإدارة. لا يُرسل للعملاء حتى الإدارة تأكد */
class DebtRemindersCheck extends Command
{
    protected $signature = 'sg:debt-reminders';

    protected $description = 'تجهيز تذكيرات الديون (تحتاج تأكيد)';

    public function handle(): int
    {
        if (!AppSettings::get('debt_remind_enabled', true)) {
            return self::SUCCESS;
        }
        $due = ReminderService::debtDue();
        if ($due->count()) {
            AlertService::push('debt', '💰 ' . $due->count() . ' عميل يستحقوا تذكير دين',
                'المجموع ' . TreasuryService::fmt($due->sum('due')) . ' د.ل — راجع القائمة وأكد الإرسال', route('sg.debts.reminders'), 'warn');
        }
        $this->info($due->count() . ' عميل');

        return self::SUCCESS;
    }
}
