<?php

namespace App\Console\Commands;

use App\Services\AppSettings;
use App\Services\DailyReport;
use App\Services\MessageService;
use Illuminate\Console\Command;

/** تقرير يومي على الواتساب لأرقام الإدارة */
class SendDailyReport extends Command
{
    protected $signature = 'sg:daily-report {--force} {--show : يطبع التقرير فقط}';

    protected $description = 'التقرير اليومي على الواتساب';

    public function handle(): int
    {
        $text = DailyReport::text();
        if ($this->option('show')) {
            $this->line($text);

            return self::SUCCESS;
        }
        if (!AppSettings::get('daily_report_enabled') && !$this->option('force')) {
            $this->info('التقرير اليومي مقفول من الإعدادات');

            return self::SUCCESS;
        }
        foreach (AppSettings::phonesFor('daily') as $ph) {
            $ok = MessageService::sendText('daily_report', $text, [$ph]);
            $this->line(($ok ? '✓ ' : '✗ ') . $ph);
        }

        return self::SUCCESS;
    }
}
