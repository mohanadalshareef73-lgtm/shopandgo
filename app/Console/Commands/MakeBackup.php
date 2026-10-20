<?php

namespace App\Console\Commands;

use App\Services\AlertService;
use App\Services\AppSettings;
use App\Services\BackupService;
use Illuminate\Console\Command;

/** نسخة احتياطية (أسبوعية من الجدولة) */
class MakeBackup extends Command
{
    protected $signature = 'sg:backup {--send : يبعثها للإيميل والواتساب} {--force}';

    protected $description = 'نسخة احتياطية للمنظومة';

    public function handle(): int
    {
        if (!AppSettings::get('backup_enabled', true) && !$this->option('force') && $this->option('send')) {
            $this->info('النسخ الأسبوعي مقفول من الإعدادات');

            return self::SUCCESS;
        }
        try {
            $b = BackupService::make();
        } catch (\Throwable $e) {
            report($e);
            AlertService::push('backup', '⚠ النسخة الاحتياطية فشلت', mb_substr($e->getMessage(), 0, 200), route('sg.backups'), 'danger', true);
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info($b['name'] . ' · ' . round($b['size'] / 1048576, 2) . ' MB');
        if ($this->option('send')) {
            $r = BackupService::send($b);
            $this->info('إيميل: ' . ($r['email'] ?? 'لا يوجد إيميل') . ' · واتساب: ' . $r['whatsapp']);
            AlertService::push('backup', '🔒 النسخة الاحتياطية الأسبوعية جاهزة', $b['name'] . ($r['email'] === 'sent' ? ' · أُرسلت للإيميل' : ''), route('sg.backups'), $r['email'] && $r['email'] !== 'sent' ? 'warn' : 'info');
        }

        return self::SUCCESS;
    }
}
