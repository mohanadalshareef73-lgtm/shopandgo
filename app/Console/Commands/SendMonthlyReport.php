<?php

namespace App\Console\Commands;

use App\Services\AppSettings;
use App\Services\MessageService;
use App\Services\Period;
use App\Services\ReportService;
use Illuminate\Console\Command;

/** يبعث تقرير الشهر السابق لأرقام الإدارة (أول يوم في الشهر) */
class SendMonthlyReport extends Command
{
    protected $signature = 'sg:monthly-report {--force : يبعث حتى إذا الإرسال مقفول من الإعدادات}';

    protected $description = 'التقرير الشهري على الواتساب لأرقام الإدارة';

    public function handle(): int
    {
        if (!AppSettings::get('monthly_report_enabled') && !$this->option('force')) {
            $this->info('الإرسال الشهري مقفول من الإعدادات');

            return self::SUCCESS;
        }
        $phones = AppSettings::phonesFor('monthly');
        if (!$phones) {
            $this->warn('لا يوجد أرقام للتقارير');

            return self::SUCCESS;
        }
        $last = now()->subMonthNoOverflow();
        $p = Period::make('custom', $last->copy()->startOfMonth()->toDateString(), $last->copy()->endOfMonth()->toDateString());
        $p->label = Period::MONTHS[$last->month - 1] . ' ' . $last->year;
        $text = ReportService::whatsappText(ReportService::summary($p));
        foreach ($phones as $ph) {
            $ok = MessageService::sendText('monthly_report', $text, [$ph]);
            $this->line(($ok ? '✓ ' : '✗ ') . $ph);
        }

        return self::SUCCESS;
    }
}
